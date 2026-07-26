@extends('layouts.admin')@section('title', 'Notifications')@section('content')
<div class="tb-page-header"><div><h1>Notifications</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Notifications</div></div>
  @if(auth()->user()->unreadNotifications->count())
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-tb"><i class="fas fa-check-double"></i> Mark all read</button></form>
  @endif
</div>
<div class="tb-card"><div class="tb-card-body" style="padding:0;">
  @forelse($notifications as $n)
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--bd);{{ $n->read_at ? '' : 'background:var(--br-soft);' }}">
      <div>
        <div style="font-weight:{{ $n->read_at ? '400' : '600' }};">{{ $n->data['title'] ?? 'Notification' }}</div>
        <div class="muted" style="font-size:.85rem;">{{ $n->data['message'] ?? '' }}</div>
        <div class="muted" style="font-size:.72rem;">{{ $n->created_at->diffForHumans() }}</div>
      </div>
      @unless($n->read_at)
        <form method="POST" action="{{ route('notifications.read', $n->id) }}">@csrf<button class="btn-tb btn-tb-sm btn-tb-ghost">Mark read</button></form>
      @endunless
    </div>
  @empty
    <div class="tb-empty" style="padding:40px;"><i class="fas fa-bell-slash"></i><p>No notifications.</p></div>
  @endforelse
</div></div>
<div style="margin-top:16px;">{{ $notifications->links() }}</div>
@endsection
