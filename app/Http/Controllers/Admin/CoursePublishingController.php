<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseModule;
use App\Services\Catalog\CourseReadiness;
use Illuminate\Http\RedirectResponse;

/**
 * "I recorded eight videos this weekend, put them all live."
 *
 * The alternative is eight trips through the curriculum tree, and the ninth
 * click landing on the wrong row. This publishes only topics that have a video
 * and are still drafts, so the one thing it cannot do is put an empty topic in
 * front of a student, which is the mistake a button called "publish all" would
 * otherwise make trivially easy.
 */
class CoursePublishingController extends Controller
{
    public function course(Course $course): RedirectResponse
    {
        return $this->done(
            CourseReadiness::publishReady($course),
            route('admin.courses.show', $course),
        );
    }

    public function module(CourseModule $module): RedirectResponse
    {
        return $this->done(
            CourseReadiness::publishReady($module),
            route('admin.courses.show', $module->course),
        );
    }

    /**
     * @param  array{published:int, skipped:int}  $result
     */
    private function done(array $result, string $back): RedirectResponse
    {
        if ($result['published'] === 0 && $result['skipped'] === 0) {
            return redirect($back)->with('success', 'Nothing to publish. Every topic here is already live.');
        }

        if ($result['published'] === 0) {
            // Saying "0 published" and stopping would read as a failure. It is
            // not: there was simply nothing recorded yet.
            return redirect($back)->with('error', trans_choice(
                '{1}Nothing published. :count topic is still waiting for its video.'
                .'|[2,*]Nothing published. :count topics are still waiting for their videos.',
                $result['skipped'],
                ['count' => $result['skipped']],
            ));
        }

        $message = trans_choice(
            '{1}:count topic is now live.|[2,*]:count topics are now live.',
            $result['published'],
            ['count' => $result['published']],
        );

        if ($result['skipped'] > 0) {
            $message .= ' '.trans_choice(
                '{1}:count more is still waiting for its video.'
                .'|[2,*]:count more are still waiting for their videos.',
                $result['skipped'],
                ['count' => $result['skipped']],
            );
        }

        return redirect($back)->with('success', $message);
    }
}
