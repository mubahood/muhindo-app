<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\District;
use App\Models\ProjectInquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        return view('admin.clients.index', [
            'clients' => Client::withCount('projects')->latest()->get(),
        ]);
    }

    /** §4.3 — "Convert" from the project-inquiry inbox pre-fills a new client from the lead. */
    public function create(Request $request): View
    {
        $client = new Client;

        if ($request->filled('from_inquiry')) {
            $inquiry = ProjectInquiry::find($request->integer('from_inquiry'));
            if ($inquiry) {
                $client->fill([
                    'name' => $inquiry->name,
                    'email' => $inquiry->email,
                    'phone' => $inquiry->phone,
                    'company' => $inquiry->organisation,
                    'notes' => "From project inquiry #{$inquiry->id} ({$inquiry->project_type}): {$inquiry->description}",
                ]);
            }
        }

        return view('admin.clients.form', [
            'client' => $client,
            'districts' => District::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = null;
        if ($request->boolean('create_portal_account') && $data['email']) {
            $password = Str::password(14);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($password),
                'role' => 'client',
                'is_active' => true,
                'password_change_required' => true,
            ]);
        }

        $client = Client::create($data + [
            'uuid' => (string) Str::uuid(),
            'client_number' => 'CL-'.now()->format('Y').'-'.str_pad((string) (Client::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'user_id' => $user?->id,
            'created_by' => $request->user()->id,
        ]);

        $message = 'Client created.';
        if ($user) {
            $message .= " Portal login: {$user->email} / {$password} (temporary — change required at first login).";
        }

        return redirect()->route('admin.clients.show', $client)->with('success', $message);
    }

    public function show(Client $client): View
    {
        return view('admin.clients.show', [
            'client' => $client->load('projects'),
        ]);
    }

    public function edit(Client $client): View
    {
        return view('admin.clients.form', [
            'client' => $client,
            'districts' => District::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request));

        return redirect()->route('admin.clients.show', $client)->with('success', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('admin.clients.index')->with('success', 'Client deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:32',
            'company' => 'nullable|string|max:150',
            'address' => 'nullable|string|max:255',
            'district_id' => 'nullable|exists:districts,id',
            'notes' => 'nullable|string',
        ]);
    }
}
