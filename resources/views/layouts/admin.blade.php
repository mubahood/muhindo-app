{{-- Muhindo Mubaraka — Back-office Layout (light · square · Inter) --}}
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  @include('partials.sw-kill')
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  {{-- $title is set by Livewire full-page components via ->title(); @yield covers classic Blade pages --}}
  <title>{{ $title ?? $__env->yieldContent('title', 'Dashboard') }} · Muhindo Mubaraka</title>
  <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon.png') }}">
  <meta name="theme-color" content="#ffffff">
  <link rel="stylesheet" href="{{ asset('vendor/fonts/inter/inter.css') }}">
  <link rel="stylesheet" href="{{ asset('vendor/fa/css/all.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/td-admin.css') }}?v={{ filemtime(public_path('css/td-admin.css')) }}">
  @livewireStyles
  @stack('styles')
</head>
<body x-data="tdAdmin()" x-cloak>

<a href="#tb-content" class="tb-skip">Skip to main content</a>

@php
  $u = Auth::user();
  $unread = $u->unreadNotifications()->count();
  $orgName = $u->role_label;
  $shellPaths = \App\Support\AppShell::paths();
@endphp

<div class="tb-wrapper">

  {{-- ═══════ Sidebar ═══════ --}}
  <aside class="tb-sidebar" :class="{'open':side}" aria-label="Main navigation">
    <a wire:navigate href="{{ route('dashboard') }}" class="tb-sidebar-brand">
      <span class="bk" style="width:30px;height:30px;background:#0b1f3a;color:#b8933f;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;">MM</span>
      <span class="btext">
        <span class="bt">Muhindo Mubaraka</span>
        <span class="bs" title="{{ $orgName }}">{{ $orgName }}</span>
      </span>
      <button class="tb-sidebar-close" type="button" @click="side=false"><i class="fas fa-xmark"></i></button>
    </a>

    <nav class="tb-nav" id="tb-nav" style="padding-top:8px;">
      @include('partials.admin-nav')
    </nav>

    <div class="tb-sidebar-footer">
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="tb-logout-btn"><i class="fas fa-right-from-bracket"></i> Sign out</button>
      </form>
    </div>
  </aside>

  {{-- Mobile drawer backdrop --}}
  <div class="tb-sidebar-backdrop" x-show="side" x-cloak @click="side=false" x-transition.opacity.duration.150ms></div>

  {{-- ═══════ Main ═══════ --}}
  <div class="tb-main">
    <header class="tb-topbar">
      <div class="tb-topbar-left">
        <button class="tb-topbar-toggle" type="button" @click.stop="side = !side"
                :aria-expanded="side ? 'true' : 'false'" aria-controls="tb-nav" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
        {{-- Dynamic page title: reads the current page's <h1> (each page provides
             one — visually-hidden on list pages, visible on detail pages) and
             mirrors it into the browser tab. Re-reads after each wire:navigate. --}}
        <span class="tb-page-title"
              x-data="{
                t: @js($__env->yieldContent('title', 'Dashboard')),
                sync() {
                  const h = document.querySelector('.tb-content h1');
                  if (h && h.textContent.trim()) this.t = h.textContent.trim();
                  document.title = this.t + ' · Muhindo Mubaraka';
                }
              }"
              x-init="$nextTick(() => sync())"
              x-on:livewire:navigated.window="$nextTick(() => sync())"
              x-text="t">@yield('title', 'Dashboard')</span>
      </div>
      <div class="tb-topbar-right">
        <a wire:navigate href="{{ route('notifications.index') }}" class="tb-topbar-bell {{ request()->routeIs('notifications.*') ? 'active' : '' }}" aria-label="Notifications" title="Notifications">
          <i class="fas fa-bell"></i>
          @if($unread)<span class="tb-bell-badge">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
        </a>
        <div class="tb-user-menu" :class="{'open':userMenu}" @click.outside="userMenu=false" @keydown.escape="userMenu=false">
          <button type="button" class="tb-user-trigger" @click="userMenu=!userMenu"
                  :aria-expanded="userMenu ? 'true' : 'false'" aria-haspopup="true" aria-controls="tb-user-dropdown">
            <div class="tb-user-avatar-sm" aria-hidden="true">{{ $u->initials }}</div>
            <span class="tb-user-name">{{ $u->name }}</span>
            <span class="sr-only">Account menu</span>
            <i class="fas fa-chevron-down tb-user-caret" aria-hidden="true"></i>
          </button>
          <div class="tb-user-dropdown" id="tb-user-dropdown">
            <a wire:navigate href="{{ route('account.edit') }}"><i class="fas fa-user-pen"></i> Your account</a>
            <a wire:navigate href="{{ route('account.edit') }}#security"><i class="fas fa-lock"></i> Change password</a>
            @if($u->isSuperAdmin())<a wire:navigate href="{{ route('admin.settings.index') }}"><i class="fas fa-gear"></i> Site settings</a>@endif
            <hr>
            <form method="POST" action="{{ route('logout') }}">@csrf
              <button type="submit" class="danger"><i class="fas fa-right-from-bracket"></i> Sign out</button>
            </form>
          </div>
        </div>
      </div>
    </header>

    {{-- Flash --}}
    @if(session('success') || session('error') || session('warning') || $errors->any())
    <div class="tb-flash-wrap" x-data="{show:true}" x-show="show">
      @if(session('success'))<div class="tb-alert tb-alert-success"><i class="fas fa-circle-check"></i><span>{{ session('success') }}</span><button class="tb-alert-x" @click="show=false"><i class="fas fa-xmark"></i></button></div>@endif
      @if(session('error'))<div class="tb-alert tb-alert-error"><i class="fas fa-circle-exclamation"></i><span>{{ session('error') }}</span><button class="tb-alert-x" @click="show=false"><i class="fas fa-xmark"></i></button></div>@endif
      @if(session('warning'))<div class="tb-alert tb-alert-warning"><i class="fas fa-triangle-exclamation"></i><span>{{ session('warning') }}</span><button class="tb-alert-x" @click="show=false"><i class="fas fa-xmark"></i></button></div>@endif
      @if($errors->any())<div class="tb-alert tb-alert-error"><i class="fas fa-circle-exclamation"></i><div><strong>Please fix:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div><button class="tb-alert-x" @click="show=false"><i class="fas fa-xmark"></i></button></div>@endif
    </div>
    @endif

    @include('partials.toast-host')

    <main class="tb-content" id="tb-content" tabindex="-1">
      {{-- Dual-mode: traditional Blade pages fill @yield('content'); Livewire
           full-page components fill {{ $slot }}. Exactly one is ever populated. --}}
      @yield('content')
      {{ $slot ?? '' }}
    </main>

    <footer class="tb-footer">
      <div class="tb-footer-brand"><i class="fas fa-id-card"></i> Muhindo Mubaraka</div>
      <div class="tb-footer-meta">
        <span class="tb-foot-sm-hide"><i class="fas fa-user-shield"></i> {{ $u->name }} · {{ $u->role_label }}</span>
        <span class="sep tb-foot-sm-hide">·</span>
        <span>{{ now()->format('D, d M Y') }}</span>
        <span class="sep">·</span>
        <span x-data="{ t:'' }"
              x-init="const f = () => t = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); f(); setInterval(f, 15000)">
          <i class="fas fa-clock"></i> <span x-text="t"></span>
        </span>
      </div>
    </footer>
  </div>
</div>

<script>
  // Global body-scroll lock shared by every modal/slide-over (reference-counted
  // so overlapping modals don't unlock each other). Self-heals on SPA navigation.
  window.tbScrollLock = function(on){
    window.__tbLocks = Math.max(0, (window.__tbLocks||0) + (on ? 1 : -1));
    document.body.classList.toggle('tb-scroll-lock', window.__tbLocks > 0);
  };
  document.addEventListener('livewire:navigated', function(){
    window.__tbLocks = 0; document.body.classList.remove('tb-scroll-lock');
  });
  function tdAdmin(){
    return { side:false, userMenu:false };
  }

  // Auto-dismiss flash after 5s
  setTimeout(function(){ document.querySelectorAll('.tb-flash-wrap .tb-alert-success').forEach(function(a){a.style.display='none';}); }, 5000);
</script>
<style>[x-cloak]{display:none!important;}</style>
@include('partials.app-navigation', ['shellPaths' => $shellPaths])
{{-- Livewire 3 (bundles Alpine — the standalone Alpine include is intentionally removed to avoid a double Alpine). --}}
@livewireScripts
<script defer src="{{ asset('vendor/js/chart.min.js') }}"></script>
@stack('scripts')
</body>
</html>
