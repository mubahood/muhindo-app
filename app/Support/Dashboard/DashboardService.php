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
use App\Services\ReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only metric provider for the role-based dashboards. Money is summed in
 * bcmath (decimal strings) — never float — matching ReportService.
 */
class DashboardService
{
    public function __construct(private readonly ReportService $reports) {}

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

    // ── Owner / admin overview ──────────────────────────────────

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

    /** §6.4 — count tagged by the nightly app:detect-at-risk-enrollments command. */
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

    // ── Personal task list (owner's own to-dos: project_id is null) ─

    public function myPendingTasksCount(): int
    {
        return ProjectTask::whereNull('project_id')->where('status', '!=', 'done')->count();
    }

    public function myPendingTasks(int $limit = 8): Collection
    {
        return ProjectTask::whereNull('project_id')->where('status', '!=', 'done')
            ->orderBy('sort_order')->limit($limit)->get();
    }

    // ── Student dashboard ────────────────────────────────────────

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

    // ── Client dashboard ─────────────────────────────────────────

    public function clientProjects(Client $client): Collection
    {
        return Project::where('client_id', $client->id)->with('updates')->latest()->get();
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
