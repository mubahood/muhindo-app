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
        $identity = $this->json('portfolio.identity');
        $contact = $this->json('portfolio.contact');

        return view('portfolio.home', [
            'identity' => $identity,
            'stats' => $this->json('portfolio.stats', []),
            'about' => $this->json('portfolio.about'),
            'services' => Service::orderBy('sort_order')->limit(4)->get(),
            'projects' => PortfolioProject::orderBy('sort_order')->limit(3)->get(),
            'courses' => Course::where('is_published', true)->latest()->limit(3)->get(),
            'clients' => $this->json('portfolio.clients', []),
            'posts' => \App\Models\Post::published()->latest('published_at')->limit(3)->get(),
            'photos' => \App\Models\GalleryPhoto::published()->where('is_featured', true)
                ->orderBy('sort_order')->limit(6)->get(),
            'products' => \App\Models\Product::published()->orderBy('sort_order')->limit(4)->get(),
            // Omitted from the page entirely until real, attributable quotes exist.
            'testimonials' => $this->json('portfolio.testimonials', []),
            'jsonLd' => $this->homeJsonLd($identity, $contact),
        ]);
    }

    /**
     * §6.2 — Person + Organization structured data for the landing page.
     *
     * @return array<int,array<string,mixed>>
     */
    private function homeJsonLd(array $identity, array $contact): array
    {
        $sameAs = array_values(array_filter([$contact['github'] ?? null, $contact['youtube'] ?? null]));

        return [
            array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $identity['name'] ?? 'Muhindo Mubaraka',
                'jobTitle' => $identity['title'] ?? null,
                'url' => route('home'),
                'sameAs' => $sameAs,
            ]),
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $identity['name'] ?? 'Muhindo Mubaraka',
                'url' => route('home'),
                'logo' => asset('images/logo-square.png'),
            ],
        ];
    }

    public function work(): View
    {
        return view('portfolio.work', [
            'projects' => PortfolioProject::orderBy('sort_order')->get(),
        ]);
    }

    /** The full grid, handed off to from the /work chapter. */
    public function projects(): View
    {
        return view('portfolio.projects', [
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
            'posts' => \App\Models\Post::published()->latest('published_at')->limit(3)->get(),
            'photos' => \App\Models\GalleryPhoto::published()->where('is_featured', true)
                ->orderBy('sort_order')->limit(6)->get(),
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

    /**
     * The whole record on one page.
     *
     * Assembled from the same tables the individual pages read, so a CV is
     * never a stale copy of the site — editing an experience entry in the admin
     * updates the CV in the same breath. It carries print styles rather than a
     * PDF upload for the same reason: an uploaded file goes out of date the
     * moment anything else changes.
     */
    public function cv(): View
    {
        return view('portfolio.cv', [
            'identity' => $this->json('portfolio.identity'),
            'contact' => $this->json('portfolio.contact'),
            'about' => $this->json('portfolio.about'),
            'stats' => $this->json('portfolio.stats', []),
            'clients' => $this->json('portfolio.clients', []),
            'posts' => \App\Models\Post::published()->latest('published_at')->limit(3)->get(),
            'photos' => \App\Models\GalleryPhoto::published()->where('is_featured', true)
                ->orderBy('sort_order')->limit(6)->get(),
            'research' => $this->json('portfolio.research'),
            'experience' => Experience::orderBy('sort_order')->get(),
            'education' => Education::orderBy('sort_order')->get(),
            'skills' => Skill::orderBy('sort_order')->get()->groupBy('category'),
            'projects' => PortfolioProject::orderBy('sort_order')->limit(6)->get(),
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
        // A bot told it failed simply retries with the field cleared, so a
        // caught submission is answered exactly as a real one would be.
        if (\App\Support\Spam\FormShield::looksAutomated($request->all())) {
            return redirect()->route('contact')->with('success', 'Thanks — I\'ll be in touch shortly.');
        }

        \App\Support\Spam\FormShield::assertHumanTiming($request->all());

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|max:5000',
        ] + \App\Support\Spam\Captcha::rules(), \App\Support\Spam\Captcha::messages());

        unset($data[\App\Support\Spam\Captcha::FIELD]);

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

    /**
     * Delete a message from the inbox.
     *
     * The inbox could only ever grow: a message could be marked read and
     * nothing else, so every piece of spam that got past the shield stayed
     * there permanently.
     */
    public function destroyMessage(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return back()->with('success', 'Message deleted.');
    }

    /** @return array<string,mixed> */
    private function json(string $key, mixed $default = []): array
    {
        $raw = Settings::get($key);

        return $raw ? json_decode($raw, true) : $default;
    }
}
