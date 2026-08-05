@extends('layouts.admin')
@section('title', 'Content performance')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>Content</h1>
    <div class="tb-breadcrumb">
      <a href="{{ route('admin.analytics.index') }}" wire:navigate>Analytics</a> <span>/</span>
      What is being read, for how long, and how far down
    </div>
  </div>
  <div class="an-header-actions">
    <a href="{{ route('admin.analytics.sources') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-signs-post"></i> Sources</a>
    <a href="{{ route('admin.analytics.index') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-chart-line"></i> Overview</a>
  </div>
</div>

@include('admin.analytics.partials.period', ['route' => 'admin.analytics.content'])

{{-- Grouped by what the page is ABOUT rather than by URL, so a course reached
     through its catalogue card, its sales page and a shared link is one row. --}}
@foreach($sets as $label => $rows)
  <div class="dash-grid">
    <x-dash.section :title="$label" icon="fa-layer-group" :count="$rows->count()">
      <div class="tb-table-wrap">
        <table class="tb-table">
          <thead>
            <tr><th>{{ \Illuminate\Support\Str::singular($label) }}</th><th>Views</th><th>People</th>
                <th>Read for</th><th>Scrolled</th><th>Attention</th></tr>
          </thead>
          <tbody>
            @foreach($rows as $row)
              @php
                $title = $row->subject->title ?? $row->subject->name ?? 'Removed';
                $seconds = (int) $row->avg_seconds;
                $scroll = (int) $row->avg_scroll;
                // One score out of the two halves of "did they actually read
                // it": time spent, capped at three minutes, and depth reached.
                $score = min(100, round((min($seconds, 180) / 180 * 60) + ($scroll / 100 * 40)));
              @endphp
              <tr>
                <td><b>{{ \Illuminate\Support\Str::limit($title, 58) }}</b></td>
                <td>{{ number_format($row->views) }}</td>
                <td>{{ number_format($row->visitors) }}</td>
                <td class="muted">{{ $seconds ? gmdate('i:s', $seconds) : 'not measured' }}</td>
                <td class="muted">{{ $scroll ? $scroll.'%' : '-' }}</td>
                <td>
                  <span class="an-score" title="Time spent and depth reached, combined">
                    <span class="an-score-track"><span class="an-score-fill" style="width:{{ $score }}%"></span></span>
                    <em>{{ $score }}</em>
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </x-dash.section>
  </div>
@endforeach

<div class="dash-grid">
  <x-dash.section title="Every page" icon="fa-list" :count="$topPages->count()">
    <div class="tb-table-wrap">
      <table class="tb-table">
        <thead><tr><th>Page</th><th>Views</th><th>People</th><th>Read for</th><th>Scrolled</th></tr></thead>
        <tbody>
          @forelse($topPages as $page)
            <tr>
              <td>
                <a href="{{ url($page->path) }}" target="_blank" rel="noopener">{{ $page->title ?: $page->path }}</a>
                @if($page->title)<div class="an-sub">{{ $page->path }}</div>@endif
              </td>
              <td>{{ number_format($page->views) }}</td>
              <td>{{ number_format($page->visitors) }}</td>
              <td class="muted">{{ $page->avg_seconds ? gmdate('i:s', (int) $page->avg_seconds) : '-' }}</td>
              <td class="muted">{{ $page->avg_scroll ? round($page->avg_scroll).'%' : '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="5"><x-dash.empty text="No page views in this window" /></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </x-dash.section>
</div>

@if($broken->isNotEmpty())
  <div class="dash-grid">
    <x-dash.section title="Requested and not found" icon="fa-link-slash">
      <div class="tb-table-wrap">
        <table class="tb-table">
          <thead><tr><th>Path</th><th>Status</th><th>Hits</th><th></th></tr></thead>
          <tbody>
            @foreach($broken as $row)
              <tr>
                <td class="an-mono">{{ $row->path }}</td>
                <td><span class="badge-tb badge-danger">{{ $row->status }}</span></td>
                <td>{{ number_format($row->hits) }}</td>
                <td class="muted">Somebody has published a link to this. Worth redirecting.</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </x-dash.section>
  </div>
@endif

@endsection
