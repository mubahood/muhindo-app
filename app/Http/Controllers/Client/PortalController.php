<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\DocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The client's own portal — their projects, progress, documents and invoices. Nothing here is admin-writable. */
class PortalController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function index(Request $request): View
    {
        $client = $request->user()->client;
        // Task counts are aggregated in the same query — the listing shows a
        // completion bar per project, which a lazy `tasks` relation would turn
        // into one query per card.
        $projects = $client
            ? Project::where('client_id', $client->id)
                ->with('updates')
                ->withCount(['tasks', 'tasks as done_tasks_count' => fn ($q) => $q->where('status', 'done')])
                ->latest()->get()
            : collect();

        /* A proposal is stage one. Until it becomes a project it is the only
           thing this person has here, and hiding it would make the portal
           look like their submission went nowhere. */
        $proposals = \App\Models\ProjectInquiry::where('user_id', $request->user()->id)
            ->latest('id')->get();

        return view('portal.index', [
            'client' => $client,
            'projects' => $projects,
            'proposals' => $proposals,
        ]);
    }

    public function project(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        return view('portal.project', [
            'project' => $project->load([
                'tasks',
                'updates' => fn ($q) => $q->latest(),
                'documents' => fn ($q) => $q->where('is_confidential', false),
                'notes' => fn ($q) => $q->where('is_client_visible', true)->latest(),
            ]),
        ]);
    }

    public function downloadDocument(Request $request, Project $project, ProjectDocument $document): StreamedResponse
    {
        $this->authorize('view', $project);
        abort_unless($document->project_id === $project->id, 404);
        abort_if($document->is_confidential, 403, 'This document is internal only.');

        return $this->documents->disk()->download($document->file_path, $document->file_name);
    }

    public function invoices(Request $request): View
    {
        $client = $request->user()->client;
        $invoices = $client
            ? Invoice::where('billable_type', \App\Models\Client::class)->where('billable_id', $client->id)->latest()->get()
            : collect();

        return view('portal.invoices', ['invoices' => $invoices]);
    }

    public function invoicePdf(Request $request, Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $invoice);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice->load('items', 'payments')])
            ->stream("invoice-{$invoice->invoice_no}.pdf");
    }
}
