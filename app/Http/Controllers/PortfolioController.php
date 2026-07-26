<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Education;
use App\Models\Experience;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\Skill;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function home(): View
    {
        return view('portfolio.home', [
            'identity' => $this->json('portfolio.identity'),
            'contact' => $this->json('portfolio.contact'),
            'stats' => $this->json('portfolio.stats', []),
            'about' => $this->json('portfolio.about'),
            'clients' => $this->json('portfolio.clients', []),
            'services' => Service::orderBy('sort_order')->get(),
            'projects' => PortfolioProject::orderBy('sort_order')->get(),
            'skills' => Skill::orderBy('sort_order')->get()->groupBy('category'),
            'experience' => Experience::orderBy('sort_order')->get(),
            'education' => Education::orderBy('sort_order')->get(),
            'research' => $this->json('portfolio.research'),
            'products' => $this->json('portfolio.products', []),
            'languages' => $this->json('portfolio.languages', []),
        ]);
    }

    public function project(PortfolioProject $portfolioProject): View
    {
        return view('portfolio.project', [
            'project' => $portfolioProject,
            'identity' => $this->json('portfolio.identity'),
            'contact' => $this->json('portfolio.contact'),
            'related' => PortfolioProject::where('id', '!=', $portfolioProject->id)
                ->orderBy('sort_order')->limit(3)->get(),
        ]);
    }

    public function contact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|max:5000',
            'website' => 'nullable|max:0', // honeypot — real visitors never fill this
        ]);

        if ($request->filled('website')) {
            // Honeypot tripped — pretend success, do nothing.
            return redirect(route('home').'#contact')->with('success', 'Thanks — I\'ll be in touch shortly.');
        }

        $message = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
        ]);

        $notifyTo = ($this->json('portfolio.contact')['emails'] ?? [])[0] ?? config('mail.from.address');
        if ($notifyTo) {
            Mail::raw(
                "New contact message from {$message->name} <{$message->email}>\n\n{$message->message}",
                fn ($mail) => $mail->to($notifyTo)->subject('New portfolio contact: '.($message->subject ?: 'No subject'))
            );
        }

        return redirect(route('home').'#contact')->with('success', 'Thanks for reaching out — I\'ll reply as soon as I can.');
    }

    public function inbox()
    {
        return view('admin.messages.index', [
            'messages' => ContactMessage::latest()->paginate(20),
        ]);
    }

    public function markRead(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->update(['read_at' => now()]);

        return back()->with('success', 'Marked as read.');
    }

    /** @return array<string,mixed> */
    private function json(string $key, mixed $default = []): array
    {
        $raw = Settings::get($key);

        return $raw ? json_decode($raw, true) : $default;
    }
}
