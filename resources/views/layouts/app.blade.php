<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Dashboard') · Muhindo Mubaraka</title>
  <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon.png') }}">
  <link rel="stylesheet" href="{{ asset('vendor/fonts/inter/inter.css') }}">
  <link rel="stylesheet" href="{{ asset('vendor/fa/css/all.min.css') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{
      --bg:#f7f6f2; --surface:#fff; --surface-2:#f0eee7; --line:#e7e3d8; --line-2:#d8d2c0;
      --tx:#141a26; --tx2:#5b6270; --tx3:#706f5c; --pri:#0b1f3a; --pri-d:#060f1f; --pri-soft:#eef1f6;
      --gold:#b8933f; --gold-d:#7d6228; --gold-soft:#f7f0df; --ok:#0f6b30; --ok-soft:#e6f4ea;
      --hd:60px; --font:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    }
    body{font-family:var(--font);color:var(--tx);background:var(--bg);font-size:14px;line-height:1.6;}
    a{color:inherit;text-decoration:none;}
    a:focus-visible,button:focus-visible,input:focus-visible,textarea:focus-visible,select:focus-visible,[tabindex]:focus-visible{
      outline:2px solid var(--gold);outline-offset:2px;}
    .wrap{max-width:960px;margin:0 auto;padding:0 24px;}
    .wrap.wide{max-width:1440px;}
    header.app{position:sticky;top:0;z-index:40;height:var(--hd);background:rgba(247,246,242,.92);
      backdrop-filter:blur(10px);border-bottom:1px solid var(--line);}
    header.app .bar{display:flex;align-items:center;gap:20px;height:var(--hd);max-width:960px;margin:0 auto;padding:0 24px;}
    .brand{display:flex;align-items:center;gap:9px;font-weight:600;font-size:14px;}
    .brand .badge{width:28px;height:28px;background:var(--pri);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;}
    .nav{display:flex;gap:20px;font-size:13px;color:var(--tx2);}
    .nav a:hover,.nav a.on{color:var(--pri);}
    .hd-r{margin-left:auto;display:flex;align-items:center;gap:14px;font-size:13px;}
    .hd-r form button{background:none;border:none;color:var(--tx2);cursor:pointer;font-size:13px;font-family:var(--font);min-height:44px;padding:0 4px;}
    .hd-r form button:hover{color:var(--pri);}
    main{padding:36px 0 60px;}
    h1{font-size:24px;font-weight:300;margin-bottom:20px;}
    .card{background:var(--surface);border:1px solid var(--line);padding:24px;}
    .grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;font-size:13px;font-weight:500;
      min-height:44px;padding:9px 16px;border:1px solid var(--pri);background:var(--pri);color:#fff;}
    .btn:hover{background:var(--pri-d);}
    .btn.gold{border-color:var(--gold);background:var(--gold);color:var(--pri-d);}
    .alert-success{background:var(--ok-soft);color:var(--ok);border:1px solid var(--ok);padding:10px 14px;margin-bottom:18px;font-size:13px;}
    .muted{color:var(--tx3);}
    .badge-pill{font-size:10.5px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;padding:3px 8px;background:var(--pri-soft);color:var(--pri);}

    /* §7.6 — at 360px the header row (brand + nav + name + sign out) no longer fits on one
       line; drop the brand wordmark and the visible username first, they're the least essential. */
    @media(max-width:480px){
      .brand-name{display:none;}
      .hd-r .muted{display:none;}
      header.app .bar,.wrap{padding:0 14px;}
      .nav{gap:14px;}
    }
  </style>
  @stack('styles')
</head>
<body>
@php $u = auth()->user(); @endphp
@if(trim($__env->yieldContent('layout_mode', '')) !== 'full')
<header class="app">
  <div class="bar">
    <a href="{{ route('dashboard') }}" class="brand"><span class="badge">MM</span> <span class="brand-name">Muhindo Mubaraka</span></a>
    <nav class="nav">
      @if($u?->isStudent())
        <a href="{{ route('learn.index') }}" class="{{ request()->routeIs('learn.*') ? 'on' : '' }}">My Courses</a>
        <a href="{{ route('courses.index') }}">Browse Courses</a>
      @elseif($u?->isClient())
        <a href="{{ route('portal.index') }}" class="{{ request()->routeIs('portal.index') || request()->routeIs('portal.project') ? 'on' : '' }}">My Projects</a>
        <a href="{{ route('portal.invoices') }}" class="{{ request()->routeIs('portal.invoices') ? 'on' : '' }}">Invoices</a>
      @endif
    </nav>
    <div class="hd-r">
      <span class="muted">{{ $u?->name }}</span>
      <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
    </div>
  </div>
</header>
@endif

@include('partials.toast-host')

<main>
  @if(trim($__env->yieldContent('layout_mode', '')) === 'full')
    {{-- Full-bleed pages (the lesson player shell) own their entire viewport region,
         including where flash messages render — no centered .wrap around them. --}}
    @yield('content')
  @else
  <div class="wrap {{ trim($__env->yieldContent('wrap_width', '')) }}">
    @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert-success" style="background:#fbe9e9;color:#b91c1c;border-color:#b91c1c;">{{ session('error') }}</div>@endif
    @yield('content')
  </div>
  @endif
</main>
@stack('scripts')
</body>
</html>
