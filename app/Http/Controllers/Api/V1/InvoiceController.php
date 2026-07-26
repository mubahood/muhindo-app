<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in user's own invoices — a client's project invoices or a student's course purchases. */
class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $client = $user->client;

        $invoices = Invoice::where(function ($q) use ($user, $client) {
            $q->where(fn ($qq) => $qq->where('billable_type', \App\Models\User::class)->where('billable_id', $user->id));
            if ($client) {
                $q->orWhere(fn ($qq) => $qq->where('billable_type', Client::class)->where('billable_id', $client->id));
            }
        })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return ApiResponse::paginated($invoices, InvoiceResource::collection($invoices->items()));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return ApiResponse::success(new InvoiceResource($invoice->load(['billable', 'items'])));
    }
}
