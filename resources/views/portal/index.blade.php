@extends('layouts.admin')
@section('title', 'My Projects')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>My Projects</h1>
    <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> My Projects</div>
  </div>
  <a href="{{ route('hire') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> Start a project</a>
</div>

@if(session('success'))
  <div class="tb-alert tb-alert-success" style="margin-bottom:18px;">{{ session('success') }}</div>
@endif

@if($proposals->isNotEmpty())
  {{-- Stage one. Shown until it becomes a project, so nobody has to wonder
       whether their proposal arrived. --}}
  <div class="tb-card" style="margin-bottom:22px;">
    <div class="tb-card-header"><h2 class="tb-card-title">With Muhindo</h2></div>
    <div class="tb-card-body">
      @foreach($proposals as $proposal)
        <div class="prop">
          <div class="prop-head">
            <div>
              <h3>{{ $proposal->title ?: 'Your project' }}</h3>
              <p class="mine-meta">
                Sent {{ ($proposal->submitted_at ?? $proposal->created_at)->format('d M Y') }}
                · {{ $proposal->budgetLabel() }}
                · {{ $proposal->timelineLabel() }}
              </p>
            </div>
            <span class="badge-tb {{ $proposal->status->badge() }}">{{ $proposal->status->label() }}</span>
          </div>

          <ol class="prop-flow">
            <li class="done"><b>Proposal sent</b><span>{{ ($proposal->submitted_at ?? $proposal->created_at)->diffForHumans() }}</span></li>
            <li class="{{ $proposal->status->value === 'new' ? 'now' : 'done' }}">
              <b>Muhindo reads it</b><span>Within one working day</span></li>
            <li class="{{ $proposal->status->value === 'contacted' ? 'now' : '' }}">
              <b>A call, then a written scope</b><span>What is in, what it costs, when it lands</span></li>
            <li class="{{ $proposal->status->value === 'converted' ? 'now' : '' }}">
              <b>It becomes a project here</b><span>Progress, documents and invoices</span></li>
          </ol>
        </div>
      @endforeach
    </div>
  </div>
@endif

<div class="mine-grid">
  @forelse($projects as $project)
    @php
      $latest = $project->updates->first();
      $total = $project->tasks_count ?? 0;
      $done = $project->done_tasks_count ?? 0;
    @endphp
    <article class="mine-card">
      <div class="mine-card-body">
        <div class="mine-card-top">
          <div style="min-width:0;">
            <h2 class="mine-title">{{ $project->title }}</h2>
            <p class="mine-meta">
              {{ $project->project_number }}@if($project->due_date) · due {{ $project->due_date->format('d M Y') }}@endif
            </p>
          </div>
          <span class="badge-tb badge-neutral" style="flex-shrink:0;">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
        </div>

        @if($total > 0)
          <div class="resume-bar" style="margin:11px 0 5px;" role="img"
               aria-label="{{ $done }} of {{ $total }} tasks done">
            <i style="width:{{ (int) round($done / $total * 100) }}%"></i>
          </div>
          <p class="mine-meta" aria-hidden="true">{{ $done }} of {{ $total }} tasks done</p>
        @else
          <p class="mine-meta" style="margin-top:11px;">Scope being agreed</p>
        @endif

        @if($latest)
          <p class="mine-meta" style="margin-top:10px;">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            {{ Str::limit($latest->update_text, 110) }}
          </p>
        @endif

        <div style="margin-top:12px;">
          <a href="{{ route('portal.project', $project) }}" class="btn-tb btn-tb-primary btn-tb-sm">
            View progress <span class="sr-only">for {{ $project->title }}</span>
          </a>
        </div>
      </div>
    </article>
  @empty
    @if($proposals->isEmpty())
      <div class="tb-card" style="grid-column:1/-1;">
        <div class="tb-empty">
          <p>Nothing here yet, tell me what you would like built and I will take it from there.</p>
          <a href="{{ route('hire') }}" class="btn-tb btn-tb-primary" style="margin-top:12px;">
            <i class="fas fa-plus"></i> Tell me about your project
          </a>
        </div>
      </div>
    @endif
  @endforelse
</div>

@push('styles')
<style>
  .prop + .prop{margin-top:20px;padding-top:20px;border-top:1px solid var(--line);}
  .prop-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;}
  .prop-head h3{font-size:16px;font-weight:600;margin:0 0 3px;}
  .prop-flow{list-style:none;margin:16px 0 0;padding:0;display:grid;
    grid-template-columns:repeat(4,1fr);gap:0;}
  .prop-flow li{position:relative;padding:22px 12px 0 0;}
  .prop-flow li::before{content:'';position:absolute;left:0;top:6px;width:12px;height:12px;
    border-radius:50%;background:var(--line);}
  .prop-flow li::after{content:'';position:absolute;left:12px;right:0;top:11px;height:2px;
    background:var(--line);}
  .prop-flow li:last-child::after{display:none;}
  .prop-flow li.done::before{background:var(--ok,#15803D);}
  .prop-flow li.now::before{background:var(--gold,#B8933F);
    box-shadow:0 0 0 4px color-mix(in srgb, var(--gold,#B8933F) 26%, transparent);}
  .prop-flow b{display:block;font-size:12.5px;font-weight:600;color:var(--tx);}
  .prop-flow span{font-size:11px;color:var(--tx3);line-height:1.45;}
  @media(max-width:760px){
    .prop-flow{grid-template-columns:1fr;gap:12px;}
    .prop-flow li{padding:0 0 0 24px;}
    .prop-flow li::before{top:2px;}
    .prop-flow li::after{left:5px;top:14px;bottom:-12px;right:auto;width:2px;height:auto;}
  }
</style>
@endpush

@endsection
