<?php

namespace App\Http\Controllers;

use App\Enums\ProjectInquiryStatus;
use App\Models\PortfolioProject;
use App\Models\ProjectInquiry;
use App\Models\User;
use App\Notifications\ProjectInquiryReceivedNotification;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The proposal.
 *
 * This replaced two things that were not working: a contact form producing
 * messages nobody could act on, and a lead form asking for a name, an email
 * and a paragraph — enough to start a conversation, not enough to price
 * anything.
 *
 * It sits behind sign-in on purpose. A proposal with an owner can be returned
 * to, added to and answered inside the portal; a proposal from an email
 * address is a thread. Registration is one screen, and it is the step that
 * turns somebody into a client rather than a message.
 */
class ProjectInquiryController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Somebody who has already told me about a project does not need to be
        // asked again — the portal is where the answer will appear.
        if ($existing = ProjectInquiry::where('user_id', $user->id)->latest('id')->first()) {
            return redirect()->route('portal.index')->with('success',
                'You have already told me about "'.($existing->title ?: 'your project')
                .'". I will come back to you on it.');
        }

        // Hiring makes somebody a client. Doing it here, rather than asking
        // them to choose a second time, means the account they made a minute
        // ago is already the right kind.
        if (! $user->is_client) {
            $user->forceFill(['is_client' => true])->save();
            $this->accounts->ensureClientProfile($user);
        }

        // A case study's "request a walkthrough" arrives with the system's
        // slug, so the form opens already saying which one.
        $demo = $request->filled('demo')
            ? PortfolioProject::where('slug', $request->string('demo'))->first()
            : null;

        return view('portfolio.propose', [
            'demo' => $demo,
            'categories' => ProjectInquiry::CATEGORIES,
            'timelines' => ProjectInquiry::TIMELINES,
            'countries' => ProjectInquiry::COUNTRIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'title' => 'required|string|min:4|max:150',
            'category' => 'required|in:'.implode(',', array_keys(ProjectInquiry::CATEGORIES)),
            'description' => 'required|string|min:40|max:5000',
            'who_uses_it' => 'nullable|string|max:1000',
            'success_looks_like' => 'nullable|string|max:1000',
            'timeline' => 'required|in:'.implode(',', array_keys(ProjectInquiry::TIMELINES)),
            'budget_currency' => 'required|in:UGX,USD',
            /* Deliberately optional. A number somebody has not worked out yet
               is worse than no number, and refusing the proposal without one
               loses the conversation entirely. */
            'budget_amount' => 'nullable|numeric|min:0|max:99999999999',
            'organisation' => 'nullable|string|max:150',
            'phone' => 'required|string|max:40',
            'country' => 'required|string|max:80',
        ], [
            'title.required' => 'Give it a name, even a rough one.',
            'description.min' => 'A few more words, please — I price from what this has to do.',
            'phone.required' => 'A WhatsApp number — it is how most of this gets discussed.',
        ]);

        $inquiry = ProjectInquiry::create($data + [
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'project_type' => $data['category'],
            'status' => ProjectInquiryStatus::New,
            'submitted_at' => now(),
        ]);

        if (! $user->phone) {
            $user->forceFill(['phone' => $data['phone']])->save();
        }

        Notification::send(
            User::whereIn('role', ['super_admin', 'admin'])->get(),
            new ProjectInquiryReceivedNotification($inquiry)
        );

        return redirect()->route('portal.index')->with('success',
            'Got it — "'.$inquiry->title.'" is with me. I read every one myself and reply '
            .'within one working day, usually sooner.');
    }
}
