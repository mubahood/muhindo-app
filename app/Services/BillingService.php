<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Events\Billing\InvoicePaid;
use App\Exceptions\OverpaymentException;
use App\Models\Client;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Money for the platform: project invoices (billed to a Client) and course
 * purchases (billed to a User). Every amount is a scale-2 bcmath string end to
 * end (never a float). Invoice numbering and payment recording are the two
 * pieces every billing flow shares.
 */
class BillingService
{
    public function __construct(private readonly CouponService $coupons) {}

    /**
     * Create an invoice for a set of line items, billed to a Client or a User.
     *
     * @param  list<array{description:string,quantity?:int,unit_price:string,tax_exempt?:bool,source?:Model}>  $items
     */
    public function generateInvoice(
        Client|User $billable,
        array $items,
        ?Project $project = null,
        string $discount = '0.00',
        string $currency = 'UGX',
        ?int $by = null,
    ): Invoice {
        if (bccomp($discount, '0', 2) < 0) {
            throw new RuntimeException('Discount cannot be negative.');
        }
        if (empty($items)) {
            throw new RuntimeException('An invoice needs at least one line item.');
        }

        $subtotal = '0.00';
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $lineTotal = bcmul((string) $item['unit_price'], (string) $qty, 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);
        }

        if (bccomp($discount, $subtotal, 2) > 0) {
            throw new RuntimeException('Discount cannot exceed the invoice subtotal.');
        }
        $total = bcsub($subtotal, $discount, 2);

        return DB::transaction(function () use ($billable, $items, $project, $subtotal, $discount, $total, $currency, $by) {
            $invoice = $this->createInvoiceWithNumber([
                'billable_type' => $billable->getMorphClass(),
                'billable_id' => $billable->id,
                'project_id' => $project?->id,
                'currency' => $currency,
                'subtotal' => $subtotal,
                'tax_total' => '0.00',
                'discount' => $discount,
                'total' => $total,
                'amount_paid' => '0.00',
                'balance' => $total,
                'status' => InvoiceStatus::Issued,
                'issued_by' => $by,
                'issued_at' => Carbon::now(),
            ]);

            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 1);
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $qty,
                    'unit_price' => $item['unit_price'],
                    'line_total' => bcmul((string) $item['unit_price'], (string) $qty, 2),
                    'tax_exempt' => $item['tax_exempt'] ?? true,
                    'source_type' => ($item['source'] ?? null) instanceof Model ? $item['source']->getMorphClass() : null,
                    'source_id' => ($item['source'] ?? null)?->getKey(),
                ]);
            }

            return $invoice;
        });
    }

    /** Convenience: raise a single-line invoice for a course purchase, with an optional coupon. */
    public function generateCourseInvoice(User $student, Course $course, ?string $couponCode = null, ?int $by = null): Invoice
    {
        $discount = '0.00';

        if ($couponCode !== null) {
            $redemption = $this->coupons->redeem($couponCode, $course, (string) $course->price);
            $discount = $redemption['discount'];
        }

        return $this->generateInvoice(
            billable: $student,
            items: [[
                'description' => 'Course: '.$course->title,
                'quantity' => 1,
                'unit_price' => (string) $course->price,
                'source' => $course,
            ]],
            discount: $discount,
            currency: $course->currency,
            by: $by,
        );
    }

    /**
     * Record a payment against an invoice. Runs in a transaction with a locking
     * read on the invoice (no lost updates on concurrent payments). Rejects
     * overpayment and non-payable invoices.
     */
    public function recordPayment(Invoice $invoice, PaymentMethod $method, string $amount, array $opts = [], ?int $by = null): Payment
    {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($invoice, $method, $amount, $opts, $by) {
            /** @var Invoice $locked */
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isPayable()) {
                throw new RuntimeException("Invoice is {$locked->status->label()}; no payment allowed.");
            }
            if (bccomp($amount, (string) $locked->balance, 2) > 0) {
                throw OverpaymentException::make($amount, (string) $locked->balance);
            }

            $newPaid = bcadd((string) $locked->amount_paid, $amount, 2);
            $newBalance = bcsub((string) $locked->total, $newPaid, 2);
            $status = bccomp($newBalance, '0', 2) === 0 ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid;

            $locked->update(['amount_paid' => $newPaid, 'balance' => $newBalance, 'status' => $status]);

            $payment = Payment::create([
                'uuid' => (string) Str::uuid(),
                'invoice_id' => $locked->id,
                'method' => $method,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference' => $opts['reference'] ?? null,
                'received_by' => $by,
                'created_at' => Carbon::now(),
            ]);

            if ($status === InvoiceStatus::Paid) {
                InvoicePaid::dispatch($locked->fresh());
            }

            return $payment;
        });
    }

    private function createInvoiceWithNumber(array $attributes, int $attempt = 0): Invoice
    {
        $now = Carbon::now();

        try {
            $seq = Invoice::withTrashed()
                ->whereYear('created_at', (int) $now->format('Y'))
                ->lockForUpdate()
                ->count() + 1;

            return Invoice::create(array_merge($attributes, [
                'uuid' => (string) Str::uuid(),
                'invoice_no' => 'INV-'.$now->format('Y').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
            ]));
        } catch (UniqueConstraintViolationException $e) {
            if ($attempt >= 3) {
                throw $e;
            }

            return $this->createInvoiceWithNumber($attributes, $attempt + 1);
        }
    }
}
