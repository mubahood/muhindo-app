<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseNotifyRequest;
use App\Services\Analytics\Tracker;
use App\Support\Analytics\Events;
use App\Support\Spam\Captcha;
use App\Support\Spam\FormShield;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Tell me when this course opens."
 *
 * Deliberately open to a stranger: no account, three fields. The entire value
 * of the page this sits on is that somebody read it, decided yes, and found
 * nothing to buy; asking them to register first would lose exactly the person
 * it exists to catch.
 *
 * Being open to a stranger is also why it carries the full spam shield, a
 * throttle and a unique index. A public form on a site with a mailbox behind
 * it is a list-building tool for somebody else unless all three are there.
 */
class CourseNotifyController extends Controller
{
    public function __construct(private readonly Tracker $tracker) {}

    public function store(Request $request, Course $course): RedirectResponse
    {
        // Answered as success. A bot that is told it failed simply retries.
        if (FormShield::looksAutomated($request->all(), 'course-notify')) {
            return $this->thanks($course);
        }

        FormShield::assertHumanTiming($request->all());

        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email:rfc|max:150',
            // Loose on purpose: people write 0783..., +256 783..., 256-783...
            // and all three are the same number. It is normalised below rather
            // than refused, because a rejected number is a lost lead.
            'whatsapp' => ['required', 'string', 'min:7', 'max:32', 'regex:/^[0-9+()\s.-]+$/'],
        ] + Captcha::rules(), Captcha::messages() + [
            'whatsapp.regex' => 'That does not look like a phone number. Digits, spaces and + only.',
        ]);

        $whatsapp = CourseNotifyRequest::normaliseWhatsApp($data['whatsapp']);

        if (strlen($whatsapp) < 9) {
            return back()->withInput()->withErrors(['whatsapp' => 'That number looks too short. Include the full number.']);
        }

        // updateOrCreate against the unique index, so asking twice updates the
        // details rather than erroring at somebody who simply pressed twice.
        // A duplicate here would inflate the only number on this list anybody
        // would act on.
        $signup = CourseNotifyRequest::updateOrCreate(
            ['course_id' => $course->id, 'email' => mb_strtolower($data['email'])],
            [
                'user_id' => $request->user()?->id,
                'name' => $data['name'],
                'whatsapp' => $whatsapp,
                'ip' => $request->ip(),
                'source_path' => mb_substr($request->path(), 0, 190),
            ]
        );

        // Counted as a conversion: on a catalogue that cannot sell anything
        // yet, this IS the conversion, and without it the funnel reads as a
        // site nobody wants.
        if ($signup->wasRecentlyCreated) {
            $this->tracker->event(
                name: Events::SIGNUP,
                subject: $course,
                label: 'Waitlist: '.$course->title,
                meta: ['kind' => 'course_waitlist'],
            );
        }

        return $this->thanks($course);
    }

    /**
     * The same answer whether the row was new, updated, or silently dropped as
     * a bot. Three different messages would tell a script which of the three
     * it just triggered.
     */
    private function thanks(Course $course): RedirectResponse
    {
        return redirect()
            ->route('courses.show', $course)
            ->with('success', 'You are on the list. I will message you the day it opens.')
            ->withFragment('notify');
    }
}
