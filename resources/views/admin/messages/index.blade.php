@extends('layouts.admin')@section('title', 'Messages')@section('content')
<div class="tb-page-header"><div><h1>Contact messages</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Messages</div></div></div>
<div class="tb-card"><div class="tb-card-body" style="padding:0;">
  @forelse($messages as $m)
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:16px 18px;border-bottom:1px solid var(--bd);{{ $m->isRead() ? '' : 'background:var(--br-soft);' }}">
      <div>
        <div style="font-weight:{{ $m->isRead() ? '400' : '600' }};">{{ $m->subject ?: '(No subject)' }}</div>
        <div class="muted" style="font-size:.85rem;">{{ $m->name }} &lt;{{ $m->email }}&gt;</div>
        <p style="margin-top:8px;white-space:pre-line;">{{ $m->message }}</p>
        <div class="muted" style="font-size:.72rem;margin-top:6px;">{{ $m->created_at->diffForHumans() }}</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
        <a href="mailto:{{ $m->email }}" class="btn-tb btn-tb-sm btn-tb-ghost"><i class="fas fa-reply"></i> Reply</a>
        @unless($m->isRead())
          <form method="POST" action="{{ route('admin.messages.read', $m) }}">@csrf<button class="btn-tb btn-tb-sm btn-tb-ghost">Mark read</button></form>
        @endunless
      </div>
    </div>
  @empty
    <div class="tb-empty" style="padding:40px;"><i class="fas fa-envelope-open"></i><p>No messages yet.</p></div>
  @endforelse
</div></div>
<div style="margin-top:16px;">{{ $messages->links() }}</div>
@endsection
