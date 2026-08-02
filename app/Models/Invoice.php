<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money source of truth for a bill, raised against either a Client (project
 * billing) or a User (a course purchase) via billable_type/billable_id.
 * amount_paid/balance are kept in step with the append-only payments rows.
 *
 * @property InvoiceStatus $status
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property numeric-string $subtotal
 * @property numeric-string $tax_total
 * @property numeric-string $discount
 * @property numeric-string $total
 * @property numeric-string $amount_paid
 * @property numeric-string $balance
 * @property-read Client|User|null $billable
 */
class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'invoice_no', 'billable_type', 'billable_id', 'project_id', 'currency',
        'subtotal', 'tax_total', 'discount', 'total', 'amount_paid', 'balance',
        'status', 'notes', 'issued_by', 'issued_at', 'refunded_by', 'refunded_at',
        'direct_payment_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'discount' => 'decimal:2',
            'total' => 'decimal:2', 'amount_paid' => 'decimal:2', 'balance' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'refunded_at' => 'datetime',
            'direct_payment_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<User, $this> */
    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('created_at');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** Still owed something. */
    public function isOutstanding(): bool
    {
        return $this->status->isPayable() && bccomp((string) $this->balance, '0', 2) > 0;
    }

    /**
     * The buyer said they would pay Muhindo directly. Says nothing about
     * whether they have — an arrangement, not a payment.
     */
    public function isAwaitingDirectPayment(): bool
    {
        return $this->direct_payment_at !== null && $this->isOutstanding();
    }
}
