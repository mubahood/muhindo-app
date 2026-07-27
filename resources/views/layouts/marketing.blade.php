<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>@yield('title', 'Muhindo Mubaraka — Information Systems & Software Engineering')</title>
  <meta name="description" content="@yield('desc', 'Portfolio of Muhindo Mubaraka — enterprise information systems, database administration and digital solutions for government, NGOs and private organisations across Uganda.')">
  <link rel="canonical" href="{{ url()->current() }}">
  <meta name="theme-color" content="#0b1f3a">
  <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon.png') }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Muhindo Mubaraka">
  <meta property="og:title" content="@yield('title', 'Muhindo Mubaraka — Information Systems & Software Engineering')">
  <meta property="og:description" content="@yield('desc', 'Enterprise information systems, database administration and digital solutions for government, NGOs and private organisations across Uganda.')">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="stylesheet" href="{{ asset('vendor/fonts/inter/inter.css') }}">
  <link rel="stylesheet" href="{{ asset('vendor/fa/css/all.min.css') }}">
  @livewireStyles
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{
      --bg:#f7f6f2; --surface:#fff; --surface-2:#f0eee7; --line:#e7e3d8; --line-2:#d8d2c0;
      --tx:#141a26; --tx2:#5b6270; --tx3:#706f5c; --pri:#0b1f3a; --pri-d:#060f1f; --pri-soft:#eef1f6;
      --gold:#b8933f; --gold-d:#7d6228; --gold-soft:#f7f0df;
      --ok:#0f6b30; --ok-soft:#e6f4ea; --hd:52px;
      --font:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    }
    html{scroll-behavior:smooth;-webkit-text-size-adjust:100%;}
    body{font-family:var(--font);color:var(--tx);background:var(--bg);font-size:14px;line-height:1.6;
      -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;letter-spacing:.002em;}
    a{color:inherit;text-decoration:none;} img{max-width:100%;display:block;}
    ::selection{background:var(--gold-soft);}
    .wrap{max-width:1080px;margin:0 auto;padding:0 24px;}

    /* ── Fixed header ── */
    header.site{position:fixed;top:0;left:0;right:0;z-index:60;height:var(--hd);
      background:rgba(247,246,242,.88);backdrop-filter:saturate(180%) blur(10px);border-bottom:1px solid var(--line);}
    header.site .bar{display:flex;align-items:center;gap:18px;height:var(--hd);}
    .brand{display:flex;align-items:center;gap:8px;font-weight:600;font-size:13.5px;letter-spacing:.01em;}
    .brand .badge{width:25px;height:25px;background:var(--pri);color:var(--gold);display:flex;align-items:center;
      justify-content:center;font-size:10.5px;font-weight:700;letter-spacing:.02em;}
    .nav{display:flex;align-items:center;gap:19px;font-size:13px;font-weight:400;}
    .nav a{color:var(--tx2);transition:color .15s;}
    .nav a:hover,.nav a.on{color:var(--tx);}
    .nav a.on{color:var(--gold-d);}
    .nav .dot{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--gold);margin-left:4px;vertical-align:middle;}
    .hd-r{margin-left:auto;display:flex;align-items:center;gap:10px;}
    .burger{display:none;width:32px;height:32px;border:1px solid var(--line-2);background:var(--surface);
      color:var(--tx2);align-items:center;justify-content:center;cursor:pointer;font-size:13.5px;}
    main{padding-top:var(--hd);}

    /* ── Buttons ── */
    .btn{display:inline-flex;align-items:center;gap:7px;font-weight:500;font-size:12.5px;padding:8px 14px;
      border:1px solid var(--pri);background:var(--pri);color:#fff;transition:background .15s,border-color .15s,color .15s;white-space:nowrap;}
    .btn:hover{background:var(--pri-d);border-color:var(--pri-d);}
    .btn.gold{border-color:var(--gold);background:var(--gold);color:var(--pri-d);}
    .btn.gold:hover{background:var(--gold-d);border-color:var(--gold-d);color:#fff;}
    .btn.ghost{background:transparent;color:var(--pri);}
    .btn.ghost:hover{background:var(--pri-soft);}
    .btn.sm{padding:7px 12px;font-size:12px;}
    .btn.lg{padding:10px 18px;font-size:13px;}

    /* ── Sections ── */
    section{padding:52px 0;}
    .eyebrow{font-size:11px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--gold-d);}
    h1{font-size:38px;font-weight:200;letter-spacing:-.025em;line-height:1.12;}
    h1 b{font-weight:600;color:var(--pri);}
    h2{font-size:24px;font-weight:300;letter-spacing:-.02em;line-height:1.2;}
    .lead{font-size:15px;font-weight:300;color:var(--tx2);line-height:1.6;}

    /* hero (home only) */
    .hero{padding:48px 0 44px;text-align:center;position:relative;overflow:hidden;}
    .hero .eyebrow{margin-bottom:12px;}
    .hero h1{margin-bottom:14px;}
    .hero p{max-width:560px;margin:0 auto 24px;}
    .ctas{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}
    .stat-row{display:flex;gap:30px;justify-content:center;flex-wrap:wrap;margin-top:36px;}
    .stat-row .stat{text-align:center;}
    .stat-row .v{font-size:23px;font-weight:600;color:var(--pri);}
    .stat-row .l{font-size:11px;color:var(--tx3);text-transform:uppercase;letter-spacing:.06em;margin-top:2px;}

    /* compact "what I do" teaser row (home only — the full grid lives on /services) */
    .icon-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;}
    .icon-row a{display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center;padding:20px 12px;
      border:1px solid var(--line);background:var(--surface);transition:border-color .15s,transform .15s;}
    .icon-row a:hover{border-color:var(--gold);transform:translateY(-2px);}
    .icon-row .ic{width:38px;height:38px;background:var(--gold-soft);color:var(--gold-d);display:flex;align-items:center;justify-content:center;font-size:15px;}
    .icon-row span{font-size:12.5px;font-weight:600;color:var(--tx);}

    /* page hero (every sub-page) — shorter, left-aligned, no stat row */
    .page-hero{padding:34px 0 30px;border-bottom:1px solid var(--line);background:var(--surface);}
    .page-hero .eyebrow{margin-bottom:8px;}
    .page-hero h1{font-size:28px;font-weight:400;margin-bottom:6px;}
    .page-hero p{color:var(--tx2);font-size:13.5px;max-width:560px;}

    /* subnav — cross-links between the about-family pages */
    .subnav{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;}
    .subnav a{font-size:12px;font-weight:500;color:var(--tx2);padding:5px 12px;border:1px solid var(--line-2);background:var(--bg);transition:all .15s;}
    .subnav a:hover{border-color:var(--gold);color:var(--gold-d);}
    .subnav a.on{background:var(--pri);border-color:var(--pri);color:#fff;}

    /* module / feature grid */
    .band-surface{background:var(--surface);border-top:1px solid var(--line);border-bottom:1px solid var(--line);}
    .sec-head{text-align:center;max-width:620px;margin:0 auto 40px;}
    .sec-head h2{margin-bottom:10px;}
    .sec-head p{color:var(--tx2);font-size:14px;}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;}
    .card{padding:22px;border:1px solid var(--line);background:var(--bg);transition:border-color .15s;}
    .card:hover{border-color:var(--line-2);}
    .card .ic{width:40px;height:40px;background:var(--gold-soft);color:var(--gold-d);display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:14px;}
    .card h3{font-size:14.5px;font-weight:600;margin-bottom:6px;}
    .card p{font-size:13px;color:var(--tx2);line-height:1.55;}

    /* clients strip */
    .clients-strip{display:flex;flex-wrap:wrap;justify-content:center;gap:12px 28px;font-size:12.5px;color:var(--tx3);
      font-weight:500;letter-spacing:.02em;text-transform:uppercase;}

    /* projects grid */
    .proj-card{border:1px solid var(--line);background:var(--surface);padding:24px;display:flex;flex-direction:column;gap:10px;transition:border-color .15s,transform .15s;}
    .proj-card:hover{border-color:var(--gold);transform:translateY(-2px);}
    .proj-card .tag-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;}
    .tag{font-size:10.5px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;padding:3px 8px;background:var(--pri-soft);color:var(--pri);}
    .proj-card h3{font-size:16px;font-weight:600;}
    .proj-card .client{font-size:12px;color:var(--gold-d);font-weight:600;text-transform:uppercase;letter-spacing:.04em;}
    .proj-card p{font-size:13px;color:var(--tx2);line-height:1.55;}
    .proj-card .link{margin-top:auto;font-size:12.5px;font-weight:600;color:var(--pri);}

    /* skills */
    .skill-groups{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:22px;}
    .skill-group h4{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gold-d);margin-bottom:10px;}
    .skill-group ul{list-style:none;display:flex;flex-wrap:wrap;gap:7px;}
    .skill-group li{font-size:12px;color:var(--tx2);background:var(--surface-2);padding:5px 10px;border:1px solid var(--line);}

    /* timeline (experience/education) */
    .timeline{display:flex;flex-direction:column;gap:26px;}
    .tl-item{padding-left:22px;border-left:2px solid var(--line);position:relative;}
    .tl-item::before{content:'';position:absolute;left:-6px;top:4px;width:10px;height:10px;background:var(--gold);}
    .tl-item .period{font-size:11.5px;color:var(--tx3);text-transform:uppercase;letter-spacing:.05em;font-weight:600;}
    .tl-item h3{font-size:15px;font-weight:600;margin:4px 0 2px;}
    .tl-item .org{font-size:13px;color:var(--pri);font-weight:500;margin-bottom:6px;}
    .tl-item p{font-size:13px;color:var(--tx2);line-height:1.55;}

    /* research / products */
    .feature-box{border:1px solid var(--line);background:var(--surface);padding:30px;}
    .feature-box h3{font-size:18px;font-weight:600;margin-bottom:6px;}
    .feature-box .sub{font-size:12.5px;color:var(--gold-d);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:14px;}
    .feature-box p{font-size:13.5px;color:var(--tx2);line-height:1.65;margin-bottom:14px;}
    .pill-row{display:flex;flex-wrap:wrap;gap:8px;}
    .pill{font-size:11.5px;padding:5px 11px;border:1px solid var(--line-2);color:var(--tx2);}

    /* contact */
    .contact-grid{display:grid;grid-template-columns:1fr 1.3fr;gap:40px;}
    .contact-info .item{margin-bottom:20px;}
    .contact-info .item h4{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--tx3);margin-bottom:5px;}
    .contact-info .item a,.contact-info .item span{font-size:14px;color:var(--tx);}
    form.contact-form{display:flex;flex-direction:column;gap:14px;}
    form.contact-form input,form.contact-form textarea{
      width:100%;border:1px solid var(--line-2);background:var(--surface);padding:12px 14px;font-family:var(--font);
      font-size:13.5px;color:var(--tx);}
    form.contact-form input:focus,form.contact-form textarea:focus{outline:2px solid var(--gold);outline-offset:-1px;}
    form.contact-form textarea{min-height:120px;resize:vertical;}
    form.contact-form label{font-size:12px;font-weight:600;color:var(--tx2);margin-bottom:4px;display:block;}
    .field-error{font-size:12px;color:#b91c1c;margin-top:4px;}
    .alert-success{background:var(--ok-soft);color:var(--ok);border:1px solid var(--ok);padding:12px 16px;font-size:13px;margin-bottom:16px;}
    /* honeypot — hidden from real visitors, catches simple bots */
    .hp-field{position:absolute;left:-9999px;top:-9999px;}

    /* e-learning catalogue */
    .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:26px;}
    .filter-bar select,.filter-bar input[type=text]{border:1px solid var(--line-2);background:var(--surface);
      padding:9px 12px;font-family:var(--font);font-size:13px;color:var(--tx);}
    .filter-bar input[type=text]{flex:1;min-width:180px;}
    .filter-bar select:focus,.filter-bar input[type=text]:focus{outline:2px solid var(--gold);outline-offset:-1px;}
    .trust-chips{display:flex;flex-wrap:wrap;gap:8px 20px;justify-content:center;margin-top:16px;font-size:12.5px;color:var(--tx2);}
    .trust-chips span{white-space:nowrap;}
    .course-cover{width:calc(100% + 48px);aspect-ratio:16/9;background:linear-gradient(135deg,var(--pri),var(--pri-d));
      display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:26px;margin:-24px -24px 14px;}
    .course-cover img{width:100%;height:100%;object-fit:cover;}
    .course-meta{display:flex;flex-wrap:wrap;gap:8px 12px;font-size:12px;color:var(--tx3);margin:6px 0;}
    .course-price{font-weight:700;color:var(--pri);font-size:14px;}
    .course-price .free{color:var(--gold-d);}
    .course-price .was{font-weight:400;color:var(--tx3);text-decoration:line-through;margin-right:6px;}
    .pagination{display:flex;gap:10px;align-items:center;justify-content:center;margin-top:34px;font-size:13px;}
    .pagination a,.pagination span{color:var(--tx2);}
    .pagination a:hover{color:var(--gold-d);}

    /* course detail (sales page) */
    .course-layout{display:grid;grid-template-columns:1fr 340px;gap:40px;align-items:start;}
    .course-layout .main{min-width:0;}
    .buy-box{border:1px solid var(--line);background:var(--surface);padding:22px;position:sticky;top:calc(var(--hd) + 16px);}
    .buy-box .thumb{width:100%;aspect-ratio:16/9;background:linear-gradient(135deg,var(--pri),var(--pri-d));
      display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:30px;margin-bottom:16px;}
    .buy-box .thumb img{width:100%;height:100%;object-fit:cover;}
    .buy-box .price{font-size:26px;font-weight:700;color:var(--pri);margin-bottom:14px;}
    .buy-box .price.free{color:var(--gold-d);}
    .buy-box ul.includes{list-style:none;margin:16px 0;}
    .buy-box ul.includes li{font-size:12.5px;color:var(--tx2);padding:5px 0 5px 22px;position:relative;}
    .buy-box ul.includes li::before{content:'\2713';position:absolute;left:0;color:var(--gold-d);font-weight:700;}
    .pay-icons{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0;font-size:11px;color:var(--tx3);}
    .pay-icons span{border:1px solid var(--line-2);padding:4px 8px;}
    .buy-box .coupon-field{width:100%;border:1px solid var(--line-2);padding:9px 12px;font-family:var(--font);font-size:13px;margin-bottom:10px;}
    .buy-box .money-comfort{font-size:11px;color:var(--tx3);margin-top:12px;text-align:center;}
    .accordion-mod{border:1px solid var(--line);margin-bottom:10px;}
    .accordion-mod summary{padding:14px 18px;font-weight:600;font-size:14px;cursor:pointer;list-style:none;
      display:flex;justify-content:space-between;align-items:center;background:var(--surface);}
    .accordion-mod summary::-webkit-details-marker{display:none;}
    .accordion-mod summary .n{font-size:11.5px;color:var(--tx3);font-weight:400;}
    .lesson-row{display:flex;justify-content:space-between;align-items:center;padding:11px 18px;border-top:1px solid var(--line);font-size:13px;color:var(--tx2);}
    .outcomes-list{list-style:none;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px 20px;}
    .outcomes-list li{padding-left:24px;position:relative;font-size:13.5px;color:var(--tx2);}
    .outcomes-list li::before{content:'\2713';position:absolute;left:0;color:var(--gold-d);font-weight:700;}
    .instructor-card{display:flex;gap:16px;align-items:flex-start;}
    .instructor-card .ph{width:56px;height:56px;border-radius:50%;background:var(--gold-soft);color:var(--gold-d);
      display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;flex-shrink:0;}
    .faq-item{border-bottom:1px solid var(--line);padding:14px 0;}
    .faq-item h4{font-size:14px;font-weight:600;margin-bottom:6px;}
    .faq-item p{font-size:13px;color:var(--tx2);}
    @media(max-width:1024px){.course-layout{grid-template-columns:1fr;} .buy-box{position:static;order:-1;margin-bottom:30px;}}

    /* content/legal prose */
    .page{max-width:760px;margin:0 auto;}
    .page h1{font-size:34px;margin-bottom:8px;}
    .page .updated{font-size:12.5px;color:var(--tx3);margin-bottom:28px;}
    .page h2{font-size:18px;font-weight:500;margin:28px 0 8px;}
    .page p{color:var(--tx2);margin:10px 0;}
    .page ul{margin:10px 0;padding-left:0;list-style:none;}
    .page li{position:relative;padding-left:22px;color:var(--tx2);margin:7px 0;}
    .page li::before{content:'';position:absolute;left:4px;top:9px;width:6px;height:6px;background:var(--gold);}
    .page a.link{color:var(--pri);font-weight:500;}

    /* footer */
    footer{border-top:1px solid var(--line);background:var(--surface);padding:44px 0 30px;}
    .foot{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:26px;}
    .foot .blurb{font-size:13px;color:var(--tx3);max-width:280px;margin-top:12px;line-height:1.6;}
    .foot h4{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--tx3);margin-bottom:12px;}
    .foot a{display:block;font-size:13px;color:var(--tx2);margin:8px 0;}
    .foot a:hover{color:var(--gold-d);}
    .foot-bar{border-top:1px solid var(--line);margin-top:34px;padding-top:20px;font-size:12px;color:var(--tx3);
      display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;}

    /* mobile menu */
    .mmenu{position:fixed;inset:var(--hd) 0 0 0;z-index:55;background:var(--bg);padding:22px 24px;display:none;flex-direction:column;gap:4px;}
    .mmenu.open{display:flex;}
    .mmenu a{font-size:18px;font-weight:300;color:var(--tx);padding:12px 0;border-bottom:1px solid var(--line);}
    .mmenu .btn{margin-top:16px;justify-content:center;}

    @media(max-width:820px){
      .nav{display:none;} .burger{display:inline-flex;} .hd-r .btn.desk{display:none;}
      h1,.hero h1{font-size:30px;} h2{font-size:20px;} .page-hero h1{font-size:25px;}
      .foot{grid-template-columns:1fr 1fr;}
      .contact-grid{grid-template-columns:1fr;}
    }
    @media(max-width:520px){ .foot{grid-template-columns:1fr;} section{padding:40px 0;} }
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto;}}
  </style>
  @stack('styles')
</head>
<body>

@php $r = fn($n) => request()->routeIs($n) ? 'on' : ''; @endphp

<header class="site">
  <div class="wrap bar">
    <a href="{{ route('home') }}" wire:navigate class="brand"><span class="badge">MM</span> Muhindo Mubaraka</a>
    <nav class="nav">
      <a href="{{ route('courses.index') }}" wire:navigate class="{{ $r('courses.*') }}">e&#8209;Learning<span class="dot"></span></a>
      <a href="{{ route('portfolio.work') }}" wire:navigate class="{{ $r('portfolio.work') }} {{ $r('portfolio.project') }}">Work</a>
      <a href="{{ route('portfolio.about') }}" wire:navigate class="{{ $r('portfolio.about') }}">About</a>
      <a href="{{ route('portfolio.skills') }}" wire:navigate class="{{ $r('portfolio.skills') }}">Skills</a>
      <a href="{{ route('contact') }}" wire:navigate class="{{ $r('contact') }}">Contact</a>
    </nav>
    <div class="hd-r">
      <a href="{{ route('login') }}" wire:navigate class="btn ghost desk sm">Sign in</a>
      <a href="{{ route('contact') }}" wire:navigate class="btn gold desk sm">Get in touch</a>
      <button class="burger" id="burger" aria-label="Menu" aria-expanded="false"><i class="fas fa-bars"></i></button>
    </div>
  </div>
</header>

<div class="mmenu" id="mmenu">
  <a href="{{ route('courses.index') }}" wire:navigate>e&#8209;Learning</a>
  <a href="{{ route('portfolio.work') }}" wire:navigate>Work</a>
  <a href="{{ route('portfolio.about') }}" wire:navigate>About</a>
  <a href="{{ route('portfolio.skills') }}" wire:navigate>Skills</a>
  <a href="{{ route('contact') }}" wire:navigate>Contact</a>
  <a href="{{ route('login') }}" wire:navigate class="btn ghost">Sign in</a>
  <a href="{{ route('contact') }}" wire:navigate class="btn gold">Get in touch</a>
</div>

<main>
  @yield('content')
</main>

<footer>
  <div class="wrap">
    <div class="foot">
      <div>
        <a href="{{ route('home') }}" wire:navigate class="brand"><span class="badge">MM</span> Muhindo Mubaraka</a>
        <p class="blurb">Software engineer and programming teacher based in Kampala, Uganda. I teach computer programming courses online, and I build software for anyone with a real problem — individuals, startups, schools, clinics, NGOs and enterprises.</p>
      </div>
      <div>
        <h4>Site</h4>
        <a href="{{ route('courses.index') }}" wire:navigate>e&#8209;Learning</a>
        <a href="{{ route('portfolio.work') }}" wire:navigate>Work</a>
        <a href="{{ route('portfolio.about') }}" wire:navigate>About</a>
        <a href="{{ route('portfolio.skills') }}" wire:navigate>Skills</a>
        <a href="{{ route('contact') }}" wire:navigate>Contact</a>
      </div>
      <div>
        <h4>More</h4>
        <a href="{{ route('portfolio.services') }}" wire:navigate>Services</a>
        <a href="{{ route('portfolio.experience') }}" wire:navigate>Experience</a>
        <a href="{{ route('portfolio.education') }}" wire:navigate>Education</a>
        <a href="{{ route('portfolio.research') }}" wire:navigate>Research</a>
        <a href="{{ route('portfolio.products') }}" wire:navigate>Products</a>
      </div>
      <div>
        <h4>Legal</h4>
        <a href="{{ route('privacy') }}" wire:navigate>Privacy</a>
        <a href="{{ route('terms') }}" wire:navigate>Terms</a>
        <a href="{{ route('login') }}" wire:navigate>Sign in</a>
      </div>
    </div>
    <div class="foot-bar">
      <span>&copy; {{ date('Y') }} Muhindo Mubaraka. All rights reserved.</span>
      <span>Kampala, Uganda.</span>
    </div>
  </div>
</footer>

<script>
  function initBurgerMenu(){
    var b=document.getElementById('burger'), m=document.getElementById('mmenu');
    if(!b || b.dataset.wired) return;
    b.dataset.wired = '1';
    b.addEventListener('click',function(){ var o=m.classList.toggle('open'); b.setAttribute('aria-expanded',o); b.querySelector('i').className=o?'fas fa-xmark':'fas fa-bars'; document.body.style.overflow=o?'hidden':''; });
  }
  initBurgerMenu();
  // wire:navigate swaps <body> for internal links (pjax-style — no full reload, every
  // page is still a real server-rendered URL, so SEO is unaffected); re-wire the burger
  // button and close any menu left open after each swap.
  document.addEventListener('livewire:navigated', function(){
    initBurgerMenu();
    var m=document.getElementById('mmenu'), b=document.getElementById('burger');
    if(m && m.classList.contains('open')){ m.classList.remove('open'); document.body.style.overflow=''; if(b){ b.setAttribute('aria-expanded','false'); b.querySelector('i').className='fas fa-bars'; } }
  });
</script>
@stack('scripts')
@livewireScripts
</body>
</html>
