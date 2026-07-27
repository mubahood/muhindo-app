<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Course;
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
    /** Lean landing page — a taste of everything, the detail lives on its own page. */
    public function home(): View
    {
        return view('portfolio.home', [
            'identity' => $this->json('portfolio.identity'),
            'stats' => $this->json('portfolio.stats', []),
            'about' => $this->json('portfolio.about'),
            'services' => Service::orderBy('sort_order')->limit(4)->get(),
            'projects' => PortfolioProject::orderBy('sort_order')->limit(3)->get(),
            'courses' => Course::where('is_published', true)->latest()->limit(3)->get(),
        ]);
    }

    public function work(): View
    {
        return view('portfolio.work', [
            'projects' => PortfolioProject::orderBy('sort_order')->get(),
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

    public function about(): View
    {
        return view('portfolio.about', [
            'about' => $this->json('portfolio.about'),
            'clients' => $this->json('portfolio.clients', []),
        ]);
    }

    public function services(): View
    {
        return view('portfolio.services', [
            'services' => Service::orderBy('sort_order')->get(),
        ]);
    }

    public function skills(): View
    {
        return view('portfolio.skills', [
            'skills' => Skill::orderBy('sort_order')->get()->groupBy('category'),
        ]);
    }

    public function experience(): View
    {
        return view('portfolio.experience', [
            'experience' => Experience::orderBy('sort_order')->get(),
        ]);
    }

    public function education(): View
    {
        return view('portfolio.education', [
            'education' => Education::orderBy('sort_order')->get(),
        ]);
    }

    public function research(): View
    {
        return view('portfolio.research', [
            'research' => $this->json('portfolio.research'),
        ]);
    }

    public function products(): View
    {
        return view('portfolio.products', [
            'products' => $this->json('portfolio.products', []),
        ]);
    }

    public function contactPage(): View
    {
        return view('portfolio.contact', [
            'contact' => $this->json('portfolio.contact'),
            'languages' => $this->json('portfolio.languages', []),
        ]);
    }

    public function contact(Request $request): RedirectResponse
    {
        // Checked before validation — a `max:0` rule on the honeypot would otherwise
        // reject a bot's non-empty value with a validation error before this branch
        // is ever reached, tipping the bot off instead of silently pretending success.
        if ($request->filled('website')) {
            return redirect()->route('contact')->with('success', 'Thanks — I\'ll be in touch shortly.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|max:5000',
        ]);

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

        return redirect()->route('contact')->with('success', 'Thanks for reaching out — I\'ll reply as soon as I can.');
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
