@php
    $client = $user->client;
    $projects = $client ? $svc->clientProjects($client) : collect();
    $balance = $client ? $svc->clientOutstandingBalance($client) : '0.00';
@endphp

@if(! $client)
  <x-dash.empty icon="fa-address-book" text="Your account isn't linked to a client profile yet. Contact us if this looks wrong." />
@else
  <div class="tb-stats-grid">
    <x-dash.stat :value="number_format($projects->count())" label="Projects" icon="fa-diagram-project" :href="route('portal.index')" />
    <x-dash.stat :value="'UGX '.number_format((float) $balance)" label="Outstanding balance" icon="fa-file-invoice-dollar"
      :tone="(float) $balance > 0 ? 'warn' : 'ok'" :href="route('portal.invoices')" />
  </div>

  <div class="dash-section">
    <div class="dash-section-title"><i class="fas fa-diagram-project"></i> My projects</div>
    <div class="dash-grid cols-2">
      @forelse($projects as $project)
        <div class="tb-card">
          <div class="tb-card-body">
            <div style="font-weight:600;margin-bottom:4px;">{{ $project->title }}</div>
            <div class="muted" style="font-size:.8rem;margin-bottom:10px;">{{ ucfirst($project->status) }}</div>
            <a href="{{ route('portal.project', $project) }}" class="btn-tb btn-tb-primary btn-tb-sm">View progress</a>
          </div>
        </div>
      @empty
        <x-dash.empty icon="fa-diagram-project" text="No projects yet." />
      @endforelse
    </div>
  </div>
@endif
