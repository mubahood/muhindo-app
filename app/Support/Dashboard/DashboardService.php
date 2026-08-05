<?php

namespace App\Support\Dashboard;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\ContactMessage;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Learning\StreakService;
use App\Services\ReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only metric provider for the role-based dashboards. Money is summed in
 * bcmath (decimal strings) (never float) matching ReportService.
 */
class DashboardService
{
    public function __construct(private readonly ReportService $reports, private readonly StreakService $streaks) {}

    private function todayRange(): array
    {
        return [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()];
    }

    private function weekRange(): array
    {
        return [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()];
    }

    private function monthRange(): array
    {
        return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
    }

    // Owner / admin overview

    public function coursesTotal(): int
    {
        return Course::count();
    }

    public function publishedCoursesTotal(): int
    {
        return Course::where('is_published', true)->count();
    }

    public function enrollmentsTotal(): int
    {
        return Enrollment::count();
    }

    public function newEnrollmentsThisWeek(): int
    {
        return Enrollment::whereBetween('created_at', $this->weekRange())->count();
    }

    /** Count tagged by the nightly app:detect-at-risk-enrollments command. */
    public function atRiskEnrollmentsCount(): int
    {
        return Enrollment::whereNotNull('at_risk_reason')->count();
    }

    public function clientsTotal(): int
    {
        return Client::count();
    }

    public function projectsTotal(): int
    {
        return Project::count();
    }

    public function activeProjectsTotal(): int
    {
        return Project::whereIn('status', ['proposal', 'active'])->count();
    }

    /** @return array<string,int> */
    public function projectsByStatus(): array
    {
        return Project::selectRaw('status, count(*) as c')->groupBy('status')
            ->pluck('c', 'status')->map(fn ($c) => (int) $c)->toArray();
    }

    public function revenueToday(): string
    {
        return $this->sumPayments($this->todayRange());
    }

    public function revenueThisMonth(): string
    {
        return $this->sumPayments($this->monthRange());
    }

    private function sumPayments(array $range): string
    {
        return $this->reports->revenue($range[0], $range[1])['total'];
    }

    /** @return array{count:int,total:string} */
    public function outstandingInvoices(): array
    {
        $rows = Invoice::whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])->get(['balance']);
        $total = '0.00';
        foreach ($rows as $r) {
            $total = bcadd($total, (string) $r->balance, 2);
        }

        return ['count' => $rows->count(), 'total' => $total];
    }

    public function unreadMessagesCount(): int
    {
        return ContactMessage::whereNull('read_at')->count();
    }

    public function staffTotal(): int
    {
        return User::whereIn('role', ['super_admin', 'admin'])->count();
    }

    public function recentProjects(int $limit = 6): Collection
    {
        return Project::with('client')->latest()->limit($limit)->get();
    }

    public function recentEnrollments(int $limit = 6): Collection
    {
        return Enrollment::with(['user', 'course'])->latest()->limit($limit)->get();
    }

    // Personal task list (owner's own to-dos: project_id is null)

    public function myPendingTasksCount(): int
    {
        return ProjectTask::whereNull('project_id')->where('status', '!=', 'done')->count();
    }

    public function myPendingTasks(int $limit = 8): Collection
    {
        return ProjectTask::whereNull('project_id')->where('status', '!=', 'done')
            ->orderBy('sort_order')->limit($limit)->get();
    }

    // Student dashboard

    public function studentEnrollments(User $user): Collection
    {
        return Enrollment::where('user_id', $user->id)
            ->with(['course' => fn ($query) => $query->withCount('lessons')])
            ->withCount(['progressRecords as completed_lessons_count' => fn ($query) => $query->whereNotNull('completed_at')])
            ->latest()->get();
    }

    public function studentCompletedCount(User $user): int
    {
        return Enrollment::where('user_id', $user->id)->where('status', 'completed')->count();
    }

    /** The earned-badges shelf, most recent first. @return Collection<int, \App\Models\UserBadge> */
    public function studentBadges(User $user): Collection
    {
        return $user->badges;
    }

    public function studentWeeklyStreak(User $user): int
    {
        return $this->streaks->currentWeeklyStreak($user);
    }

    /** Total focused learning time across every lesson, in seconds (the active_seconds tracker). */
    public function studentLearningSeconds(User $user): int
    {
        return (int) \App\Models\LessonProgress::whereIn(
            'enrollment_id',
            Enrollment::where('user_id', $user->id)->select('id')
        )->sum('active_seconds');
    }

    /** @return Collection<int, \App\Models\Certificate> Earned certificates, newest first. */
    public function studentCertificates(User $user): Collection
    {
        return \App\Models\Certificate::whereIn(
            'enrollment_id',
            Enrollment::where('user_id', $user->id)->select('id')
        )->with('enrollment.course')->latest('issued_at')->get();
    }

    /**
     * Required quizzes and assignments the student still owes across every active
     * enrollment. The "what do I need to do next" widget. Mirrors the same
     * submitted-check ProgressService::completionBlockers uses.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function studentPendingActivities(User $user): Collection
    {
        // Deliberately a fixed number of queries regardless of how many courses the
        // student is enrolled in. The per-enrollment loop this replaced was a real
        // N+1 (StudentDashboardQueryCountTest guards this).
        $enrollments = Enrollment::where('user_id', $user->id)
            ->where('status', 'active')->with('course')->get();

        if ($enrollments->isEmpty()) {
            return collect();
        }

        $courseIds = $enrollments->pluck('course_id');
        $enrollmentIds = $enrollments->pluck('id');
        // One enrollment per course per user, so course_id maps back to its course.
        $coursesById = $enrollments->pluck('course', 'course_id');

        $submittedQuizIds = \App\Models\QuizAttempt::whereIn('enrollment_id', $enrollmentIds)
            ->whereNotNull('submitted_at')->pluck('quiz_id')->unique();
        $submittedAssignmentIds = \App\Models\AssignmentSubmission::whereIn('enrollment_id', $enrollmentIds)
            ->whereNotNull('submitted_at')->pluck('assignment_id')->unique();

        $pending = collect();

        foreach (\App\Models\Quiz::whereIn('course_id', $courseIds)
            ->where('is_published', true)->where('is_required', true)
            ->whereNotIn('id', $submittedQuizIds)->get() as $quiz) {
            $pending->push([
                'kind' => 'quiz',
                'title' => $quiz->title,
                'course' => $coursesById[$quiz->course_id]->title,
                'url' => route('learn.quiz.show', [$coursesById[$quiz->course_id], $quiz]),
            ]);
        }

        foreach (\App\Models\Assignment::whereIn('course_id', $courseIds)
            ->where('is_published', true)->where('is_required', true)
            ->whereNotIn('id', $submittedAssignmentIds)->get() as $assignment) {
            $pending->push([
                'kind' => 'assignment',
                'title' => $assignment->title,
                'course' => $coursesById[$assignment->course_id]->title,
                'due_at' => $assignment->due_at,
                'url' => route('learn.assignment.show', [$coursesById[$assignment->course_id], $assignment]),
            ]);
        }

        return $pending;
    }

    // Client dashboard

    public function clientProjects(Client $client): Collection
    {
        return Project::where('client_id', $client->id)->with('updates')->latest()->get();
    }

    /**
     * Project requests this account submitted that haven't become a project yet,
     * the first step of the client journey, so it doesn't vanish after sending.
     *
     * @return Collection<int, \App\Models\ProjectInquiry>
     */
    public function clientOpenRequests(User $user): Collection
    {
        return \App\Models\ProjectInquiry::where('user_id', $user->id)
            ->whereIn('status', ['new', 'contacted'])
            ->latest()->get();
    }

    /**
     * Task completion per project, keyed by project id, one grouped query, not one
     * per project.
     *
     * @return Collection<int|string, array{done:int,total:int}>
     */
    public function clientTaskProgress(Collection $projects): Collection
    {
        if ($projects->isEmpty()) {
            return collect();
        }

        return ProjectTask::whereIn('project_id', $projects->pluck('id'))
            ->get(['project_id', 'status'])
            ->groupBy('project_id')
            ->map(fn (Collection $tasks) => $this->taskCounts($tasks));
    }

    /**
     * @param  Collection<int,ProjectTask>  $tasks
     * @return array{done:int,total:int}
     */
    private function taskCounts(Collection $tasks): array
    {
        return [
            'done' => $tasks->where('status', 'done')->count(),
            'total' => $tasks->count(),
        ];
    }

    /** @return Collection<int, \App\Models\ProjectUpdate> Latest progress notes across all the client's projects. */
    public function clientRecentUpdates(Collection $projects, int $limit = 5): Collection
    {
        if ($projects->isEmpty()) {
            return collect();
        }

        return \App\Models\ProjectUpdate::whereIn('project_id', $projects->pluck('id'))
            ->with('project')->latest()->limit($limit)->get();
    }

    public function clientOutstandingBalance(Client $client): string
    {
        $rows = Invoice::where('billable_type', Client::class)->where('billable_id', $client->id)
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->get(['balance']);
        $total = '0.00';
        foreach ($rows as $r) {
            $total = bcadd($total, (string) $r->balance, 2);
        }

        return $total;
    }
}
