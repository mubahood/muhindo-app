<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\BillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Invoices. Generation and payment recording live in BillingService; this
 * controller authorizes, validates, and renders (incl. the DomPDF invoice).
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        return view('admin.invoices.index', [
            'invoices' => Invoice::with('billable')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
                ->latest()->paginate(20)->withQueryString(),
            'statuses' => InvoiceStatus::options(),
            'filterStatus' => $request->get('status'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Invoice::class);

        return view('admin.invoices.form', [
            'clients' => Client::orderBy('name')->get(),
            'projects' => Project::orderBy('title')->get(),
            'selectedClientId' => $request->integer('client_id') ?: null,
            'selectedProjectId' => $request->integer('project_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        // Drop blank rows from the fixed-size line-item form before validating.
        $request->merge([
            'items' => array_values(array_filter(
                $request->input('items', []),
                fn ($row) => filled($row['description'] ?? null)
            )),
        ]);

        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'currency' => 'nullable|string|max:5',
            'discount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:200',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $client = Client::findOrFail($data['client_id']);
        $project = ! empty($data['project_id']) ? Project::find($data['project_id']) : null;

        try {
            $invoice = $this->billing->generateInvoice(
                billable: $client,
                items: array_map(fn ($i) => [
                    'description' => $i['description'],
                    'quantity' => (int) $i['quantity'],
                    'unit_price' => number_format((float) $i['unit_price'], 2, '.', ''),
                ], $data['items']),
                project: $project,
                discount: number_format((float) ($data['discount'] ?? 0), 2, '.', ''),
                currency: $data['currency'] ?: 'UGX',
                by: $request->user()->id,
            );
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.invoices.show', $invoice)->with('success', "Invoice {$invoice->invoice_no} generated.");
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load(['billable', 'project', 'items', 'payments.receivedBy']);

        return view('admin.invoices.show', ['invoice' => $invoice]);
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('update', $invoice);

        return view('admin.invoices.edit', ['invoice' => $invoice]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $data = $request->validate([
            'notes' => 'nullable|string',
            'void' => 'nullable|boolean',
        ]);

        $invoice->update([
            'notes' => $data['notes'] ?? $invoice->notes,
            'status' => $request->boolean('void') ? InvoiceStatus::Void : $invoice->status,
        ]);

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $invoice->update(['status' => InvoiceStatus::Void]);

        return redirect()->route('admin.invoices.index')->with('success', 'Invoice voided.');
    }

    public function pdf(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load(['billable', 'items']);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->download("{$invoice->invoice_no}.pdf");
    }
}
