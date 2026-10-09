<?php

namespace App\Http\Controllers;

use App\Enums\ProjectInquiryStatus;
use App\Models\ProjectInquiry;
use App\Notifications\OwnerAlertNotification;
use App\Services\Analytics\Tracker;
use App\Support\Analytics\Events;
use App\Support\Spam\Captcha;
use App\Support\Spam\FormShield;
use App\Support\Spam\ImageCaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "I want you to build something", from somebody with no account.
 *
 * Before this, every Hire button on the site redirected a stranger to the
 * registration form. The reasoning was that a proposal needs an owner so it can
 * be tracked and returned to, which is true, and which is a fine thing to ask
 * for after the first message rather than before it. Asking somebody to choose
 * a password before they can tell you what they want built loses most of them,
 * and the ones it loses are the ones who were only half decided, which is most
 * people who ever hire anybody.
 *
 * So this is the short version: who you are, how to reach you, what you want.
 * Five fields. The full proposal form, with budget, timeline, audience and
 * success criteria, still exists behind sign in and a signed in visitor still
 * goes straight there. Nothing was taken from them; a door was added for
 * everybody else.
 *
 * The enquiry lands in the same project_inquiries table, whose user_id is
 * already nullable and which already carries name, email and phone, so the
 * admin screens, the statuses and the reporting all work on it unchanged.
 */
class PublicEnquiryController extends Controller
{
    public function __construct(private readonly Tracker $tracker) {}

    public function create(Request $request): View
    {
        $this->tracker->event(name: Events::CTA_CLICK, label: 'Enquiry form opened');

        return view('portfolio.enquire', [
            'categories' => ProjectInquiry::CATEGORIES,
            'timelines' => ProjectInquiry::TIMELINES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Answered as success. A bot told it failed simply tries again.
        if (FormShield::looksAutomated($request->all(), 'public-enquiry')) {
            return $this->thanks();
        }

        FormShield::assertHumanTiming($request->all());

        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email:rfc|max:150',
            'phone' => ['required', 'string', 'min:7', 'max:40', 'regex:/^[0-9+()\s.-]+$/'],
            'organisation' => 'nullable|string|max:150',
            'category' => 'required|in:'.implode(',', array_keys(ProjectInquiry::CATEGORIES)),
            'timeline' => 'required|in:'.implode(',', array_keys(ProjectInquiry::TIMELINES)),
            'description' => 'required|string|min:30|max:5000',
        ] + Captcha::rules() + ImageCaptcha::rules(), Captcha::messages() + ImageCaptcha::messages() + [
            'description.min' => 'A few more words, please. I reply from what this has to do.',
            'phone.required' => 'A WhatsApp number. It is how most of this gets discussed.',
            'phone.regex' => 'That does not look like a phone number. Digits, spaces and + only.',
        ]);

        if (ImageCaptcha::available() && ! ImageCaptcha::check($request->input(ImageCaptcha::FIELD))) {
            return back()->withInput()
                ->withErrors([ImageCaptcha::FIELD => 'That did not match the picture. Here is a new one.']);
        }

        $inquiry = ProjectInquiry::create([
            'uuid' => (string) Str::uuid(),
            // Null on purpose: nobody is signed in. If they make an account
            // later with the same address, the portal can claim it.
            'user_id' => $request->user()?->id,
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'],
            'organisation' => $data['organisation'] ?? null,
            'title' => Str::limit($data['description'], 90, ''),
            'category' => $data['category'],
            'project_type' => $data['category'],
            'timeline' => $data['timeline'],
            'description' => $data['description'],
            'country' => 'Not said',
            'budget_currency' => 'UGX',
            'status' => ProjectInquiryStatus::New,
            'submitted_at' => now(),
        ]);

        $this->tracker->event(
            name: Events::SIGNUP,
            subject: $inquiry,
            label: 'Project enquiry: '.$inquiry->name,
            meta: ['kind' => 'public_enquiry', 'category' => $inquiry->category],
        );

        OwnerAlertNotification::fire(
            subject: 'New project enquiry from '.$inquiry->name,
            headline: $inquiry->name.' wants something built.',
            rows: [
                'Name' => $inquiry->name,
                'Email' => $inquiry->email,
                'WhatsApp' => $inquiry->phone,
                'Organisation' => $inquiry->organisation ?: 'Not said',
                'Kind of work' => ProjectInquiry::CATEGORIES[$inquiry->category] ?? $inquiry->category,
                'When' => ProjectInquiry::TIMELINES[$inquiry->timeline] ?? $inquiry->timeline,
            ],
            body: $inquiry->description,
            replyTo: $inquiry->email,
            actionLabel: 'Open it in the admin',
            actionUrl: route('admin.project-inquiries.index'),
        );

        return $this->thanks();
    }

    private function thanks(): RedirectResponse
    {
        return redirect()->route('enquire')->with('enquiry_sent', true);
    }
}
