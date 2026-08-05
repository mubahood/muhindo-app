<?php

namespace App\Livewire\Admin;

use App\Models\CourseReview;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Moderation queue: every review, unpublished first, with publish/unpublish/delete. */
class ReviewModeration extends Component
{
    public function publish(int $id): void
    {
        CourseReview::whereKey($id)->update(['is_published' => true]);
    }

    public function unpublish(int $id): void
    {
        CourseReview::whereKey($id)->update(['is_published' => false]);
    }

    public function delete(int $id): void
    {
        CourseReview::whereKey($id)->delete();
    }

    public function render(): View
    {
        $reviews = CourseReview::with('course', 'enrollment.user')
            ->orderBy('is_published')
            ->latest()
            ->get();

        return view('livewire.admin.review-moderation', ['reviews' => $reviews])
            ->layout('layouts.admin')
            ->title('Reviews');
    }
}
