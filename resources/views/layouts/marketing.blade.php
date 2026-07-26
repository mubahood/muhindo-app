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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('vendor/fa/css/all.min.css') }}">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{
      --bg:#f7f6f2; --surface:#fff; --surface-2:#f0eee7; --line:#e7e3d8; --line-2:#d8d2c0;
      --tx:#141a26; --tx2:#5b6270; --tx3:#93927e; --pri:#0b1f3a; --pri-d:#060f1f; --pri-soft:#eef1f6;
      --gold:#b8933f; --gold-d:#93752f; --gold-soft:#f7f0df;
      --ok:#15803d; --ok-soft:#e6f4ea; --hd:60px;
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
    header.site .bar{display:flex;align-items:center;gap:22px;height:var(--hd);}
    .brand{display:flex;align-items:center;gap:10px;font-weight:600;font-size:15px;letter-spacing:.01em;}
    .brand .badge{width:30px;height:30px;background:var(--pri);color:var(--gold);display:flex;align-items:center;
      justify-content:center;font-size:12px;font-weight:700;letter-spacing:.02em;}
    .nav{display:flex;align-items:center;gap:24px;font-size:13.5px;font-weight:400;}
    .nav a{color:var(--tx2);transition:color .15s;}
    .nav a:hover,.nav a.on{color:var(--tx);}
    .nav a.on{color:var(--gold-d);}
    .hd-r{margin-left:auto;display:flex;align-items:center;gap:12px;}
    .burger{display:none;width:38px;height:38px;border:1px solid var(--line-2);background:var(--surface);
      color:var(--tx2);align-items:center;justify-content:center;cursor:pointer;font-size:15px;}
    main{padding-top:var(--hd);}

    /* ── Buttons ── */
    .btn{display:inline-flex;align-items:center;gap:8px;font-weight:500;font-size:13px;padding:10px 18px;
      border:1px solid var(--pri);background:var(--pri);color:#fff;transition:background .15s,border-color .15s,color .15s;white-space:nowrap;}
    .btn:hover{background:var(--pri-d);border-color:var(--pri-d);}
    .btn.gold{border-color:var(--gold);background:var(--gold);color:var(--pri-d);}
    .btn.gold:hover{background:var(--gold-d);border-color:var(--gold-d);color:#fff;}
    .btn.ghost{background:transparent;color:var(--pri);}
    .btn.ghost:hover{background:var(--pri-soft);}
    .btn.sm{padding:8px 14px;font-size:12.5px;}
    .btn.lg{padding:13px 24px;font-size:14px;}

    /* ── Sections ── */
    section{padding:60px 0;}
    .eyebrow{font-size:11.5px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--gold-d);}
    h1{font-size:42px;font-weight:200;letter-spacing:-.025em;line-height:1.12;}
    h1 b{font-weight:600;color:var(--pri);}
    h2{font-size:26px;font-weight:300;letter-spacing:-.02em;line-height:1.2;}
    .lead{font-size:16px;font-weight:300;color:var(--tx2);line-height:1.65;}

    /* hero */
    .hero{padding:56px 0 52px;text-align:center;position:relative;overflow:hidden;}
    .hero .eyebrow{margin-bottom:14px;}
    .hero h1{margin-bottom:18px;}
    .hero p{max-width:620px;margin:0 auto 30px;}
    .ctas{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
    .stat-row{display:flex;gap:34px;justify-content:center;flex-wrap:wrap;margin-top:44px;}
    .stat-row .stat{text-align:center;}
    .stat-row .v{font-size:26px;font-weight:600;color:var(--pri);}
    .stat-row .l{font-size:11.5px;color:var(--tx3);text-transform:uppercase;letter-spacing:.06em;margin-top:2px;}

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
    .foot{display:grid;grid-template-columns:1.6fr 1fr 1fr;gap:30px;}
    .foot .blurb{font-size:13px;color:var(--tx3);max-width:280px;margin-top:12px;line-height:1.6;}
    .foot h4{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--tx3);margin-bottom:12px;}
    .foot a{display:block;font-size:13px;color:var(--tx2);margin:8px 0;}
    .foot a:hover{color:var(--gold-d);}
    .foot-bar{border-top:1px solid var(--line);margin-top:34px;padding-top:20px;font-size:12px;color:var(--tx3);
      display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;}

    /* mobile menu */
    .mmenu{position:fixed;inset:var(--hd) 0 0 0;z-index:55;background:var(--bg);padding:22px 24px;display:none;flex-direction:column;gap:4px;}
    .mmenu.open{display:flex;}
    .mmenu a{font-size:20px;font-weight:300;color:var(--tx);padding:13px 0;border-bottom:1px solid var(--line);}
    .mmenu .btn{margin-top:16px;justify-content:center;}

    @media(max-width:820px){
      .nav{display:none;} .burger{display:inline-flex;} .hd-r .btn.desk{display:none;}
      h1,.hero h1{font-size:32px;} h2{font-size:22px;}
      .foot{grid-template-columns:1fr 1fr;}
      .contact-grid{grid-template-columns:1fr;}
    }
    @media(max-width:520px){ .foot{grid-template-columns:1fr;} section{padding:48px 0;} }
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto;}}
  </style>
  @stack('styles')
</head>
<body>

@php $r = fn($n) => request()->routeIs($n) ? 'on' : ''; @endphp

<header class="site">
  <div class="wrap bar">
    <a href="{{ route('home') }}" class="brand"><span class="badge">MM</span> Muhindo Mubaraka</a>
    <nav class="nav">
      <a href="{{ route('home') }}#work" class="{{ $r('portfolio.project') }}">Work</a>
      <a href="{{ route('courses.index') }}" class="{{ $r('courses.*') }}">Courses</a>
      <a href="{{ route('home') }}#contact">Contact</a>
    </nav>
    <div class="hd-r">
      <a href="{{ route('login') }}" class="btn ghost desk sm">Sign in</a>
      <a href="{{ route('home') }}#contact" class="btn gold desk sm">Get in touch</a>
      <button class="burger" id="burger" aria-label="Menu" aria-expanded="false"><i class="fas fa-bars"></i></button>
    </div>
  </div>
</header>

<div class="mmenu" id="mmenu">
  <a href="{{ route('home') }}#work">Work</a>
  <a href="{{ route('courses.index') }}">Courses</a>
  <a href="{{ route('home') }}#contact">Contact</a>
  <a href="{{ route('login') }}" class="btn ghost">Sign in</a>
  <a href="{{ route('home') }}#contact" class="btn gold">Get in touch</a>
</div>

<main>
  @yield('content')
</main>

<footer>
  <div class="wrap">
    <div class="foot">
      <div>
        <a href="{{ route('home') }}" class="brand"><span class="badge">MM</span> Muhindo Mubaraka</a>
        <p class="blurb">Manager, Information Systems — enterprise information systems, database administration and digital solutions delivery for government, NGOs and private organisations across Uganda.</p>
      </div>
      <div>
        <h4>Site</h4>
        <a href="{{ route('home') }}#work">Work</a>
        <a href="{{ route('courses.index') }}">Courses</a>
        <a href="{{ route('home') }}#contact">Contact</a>
      </div>
      <div>
        <h4>Legal</h4>
        <a href="{{ route('privacy') }}">Privacy</a>
        <a href="{{ route('terms') }}">Terms</a>
        <a href="{{ route('login') }}">Sign in</a>
      </div>
    </div>
    <div class="foot-bar">
      <span>&copy; {{ date('Y') }} Muhindo Mubaraka. All rights reserved.</span>
      <span>Kampala, Uganda.</span>
    </div>
  </div>
</footer>

<script>
  (function(){
    var b=document.getElementById('burger'), m=document.getElementById('mmenu');
    if(b){ b.addEventListener('click',function(){ var o=m.classList.toggle('open'); b.setAttribute('aria-expanded',o); b.querySelector('i').className=o?'fas fa-xmark':'fas fa-bars'; document.body.style.overflow=o?'hidden':''; }); }
  })();
</script>
@stack('scripts')
</body>
</html>
