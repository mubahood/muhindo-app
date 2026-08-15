@php
    $out = $svc->outstandingInvoices();
@endphp

{{-- Hero KPIs --}}
<div class="tb-stats-grid">
  <x-dash.stat :value="number_format($svc->coursesTotal())" label="Courses" icon="fa-book"
    :sub="$svc->publishedCoursesTotal().' published'" :href="route('admin.courses.index')" />
  <x-dash.stat :value="number_format($svc->enrollmentsTotal())" label="Enrollments" icon="fa-user-graduate"
    :sub="$svc->newEnrollmentsThisWeek().' new this week'" :href="route('admin.enrollments.index')" />
  <x-dash.stat :value="number_format($svc->atRiskEnrollmentsCount())" label="Students at risk" icon="fa-triangle-exclamation"
    :tone="$svc->atRiskEnrollmentsCount() ? 'warn' : ''" :href="route('admin.enrollments.index')" />
  <x-dash.stat :value="number_format($svc->clientsTotal())" label="Clients" icon="fa-address-book"
    :href="route('admin.clients.index')" />
  <x-dash.stat :value="number_format($svc->activeProjectsTotal())" label="Active projects" icon="fa-diagram-project"
    :sub="$svc->projectsTotal().' total'" :href="route('admin.projects.index')" />
  <x-dash.stat :value="'UGX '.number_format((float) $svc->revenueThisMonth())" label="Revenue this month" icon="fa-coins" tone="ok"
    :href="route('admin.invoices.index')" />
  <x-dash.stat :value="'UGX '.number_format((float) $out['total'])" label="Outstanding" icon="fa-file-invoice-dollar"
    :tone="$out['count'] ? 'warn' : ''" :sub="$out['count'].' unpaid invoices'" :href="route('admin.invoices.index')" />
  <x-dash.stat :value="number_format($svc->unreadMessagesCount())" label="Unread messages" icon="fa-envelope"
    :tone="$svc->unreadMessagesCount() ? 'warn' : ''" :href="route('admin.messages.index')" />
  <x-dash.stat :value="number_format($svc->myPendingTasksCount())" label="My tasks" icon="fa-list-check"
    :sub="$svc->myOverdueCount() ? $svc->myOverdueCount().' overdue' : 'nothing overdue'"
    :tone="$svc->myOverdueCount() ? 'bad' : ''"
    :href="route('admin.today')" />
  <x-dash.stat :value="number_format($svc->clientsGoingQuietCount())" label="Clients going quiet" icon="fa-comment-slash"
    :tone="$svc->clientsGoingQuietCount() ? 'warn' : ''" :sub="'a week or more with no update'"
    :href="route('admin.today')" />
</div>

<div class="dash-grid cols-2">
  <x-dash.section title="Projects by status" icon="fa-diagram-project" :href="route('admin.projects.index')" viewLabel="All projects">
    <x-dash.bars :data="$svc->projectsByStatus()" />
  </x-dash.section>

  {{-- Days since each client last heard anything. Silence with a client is not
       an event that happens on a day, it is an absence that grows while nothing
       counts it, which is how two months can pass without a decision ever being
       made to let them. This is the counter. --}}
  <x-dash.section title="Days since each client last heard from you" icon="fa-comment-dots"
                  :href="route('admin.today')" viewLabel="My day">
    @forelse($svc->clientContactHealth(6) as $row)
      <a class="tk-health-row lvl-{{ $row['level'] }}"
         href="{{ route('admin.projects.show', $row['project']) }}" wire:navigate>
        <span class="tk-health-days">{{ $row['days'] !== null ? $row['days'] : '?' }}</span>
        <span class="tk-health-main">
          <b>{{ $row['client']->name }}</b>
          <em>{{ $row['project']->title }}</em>
        </span>
        <span class="tk-health-note">
          {{ $row['last_at'] ? 'last update '.$row['last_at']->diffForHumans() : 'never updated' }}
        </span>
      </a>
    @empty
      <x-dash.empty icon="fa-handshake" text="No active client projects" />
    @endforelse
  </x-dash.section>
</div>

<div class="dash-grid cols-2">
  <x-dash.section title="Recent projects" icon="fa-diagram-project" :href="route('admin.projects.index')" viewLabel="View all">
    @forelse($svc->recentProjects(6) as $project)
      <div class="dash-queue-row">
        <div class="qmain"><div class="qname">{{ $project->title }}</div>
          <div class="qmeta">{{ $project->client->name ?? '-' }}</div></div>
        <span class="qtime">{{ ucfirst($project->status) }}</span>
      </div>
    @empty
      <x-dash.empty icon="fa-diagram-project" text="No projects yet" />
    @endforelse
  </x-dash.section>

  <x-dash.section title="Recent enrollments" icon="fa-user-graduate" :href="route('admin.enrollments.index')" viewLabel="View all">
    @forelse($svc->recentEnrollments(6) as $enrollment)
      <div class="dash-queue-row">
        <div class="qmain"><div class="qname">{{ $enrollment->user->name ?? '-' }}</div>
          <div class="qmeta">{{ $enrollment->course->title ?? '-' }}</div></div>
        <span class="qtime">{{ ucfirst($enrollment->status) }}</span>
      </div>
    @empty
      <x-dash.empty icon="fa-user-graduate" text="No enrollments yet" />
    @endforelse
  </x-dash.section>
</div>

<div class="dash-section">
  <div class="dash-section-title"><i class="fas fa-bolt"></i> Quick actions</div>
  <div class="dash-actions">
    <x-dash.quick :href="route('admin.today')" icon="fa-sun" label="My day" />
    <x-dash.quick :href="route('admin.courses.create')" icon="fa-book-medical" label="New course" />
    <x-dash.quick :href="route('admin.clients.create')" icon="fa-user-plus" label="New client" />
    <x-dash.quick :href="route('admin.projects.create')" icon="fa-diagram-project" label="New project" />
    <x-dash.quick :href="route('admin.invoices.create')" icon="fa-file-invoice" label="New invoice" />
    <x-dash.quick :href="route('admin.portfolio-projects.index')" icon="fa-id-card" label="Portfolio" />
    <x-dash.quick :href="route('admin.messages.index')" icon="fa-envelope" label="Messages" />
  </div>
</div>
