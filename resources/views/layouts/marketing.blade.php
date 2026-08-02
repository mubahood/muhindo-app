<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <x-seo
    :title="trim($__env->yieldContent('title', 'Muhindo Mubaraka — Software Engineer & Programming Teacher'))"
    :description="trim($__env->yieldContent('desc', 'I teach computer programming and computer-related courses, and I build software for anyone with a real problem — individuals, startups, schools, clinics, NGOs and enterprises across Uganda.'))"
    :image="trim($__env->yieldContent('og_image', '')) ?: null"
  >@stack('jsonld')</x-seo>
  <link rel="stylesheet" href="{{ asset('vendor/fonts/inter/inter.css') }}">
  {{-- §6.6 — FontAwesome (74KB) isn't needed for first paint (icons are secondary to text/
       layout); loaded at print-media priority then swapped to all, so it never blocks
       render. noscript fallback covers JS-disabled visitors (§7's "works without JS"). --}}
  <link rel="stylesheet" href="{{ asset('vendor/fa/css/all.min.css') }}" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="{{ asset('vendor/fa/css/all.min.css') }}"></noscript>
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
    .nav{display:flex;align-items:center;gap:18px;font-size:13px;font-weight:600;}
    /* The nav is now set at the same weight and ink as the body text it sits
       above, so hover needs its own colour or it reads as dead. Active keeps
       that colour plus a standing rule, which hover never draws — the two
       states have to be told apart at a glance. */
    .nav a{position:relative;color:var(--tx);transition:color .15s;padding:2px 0;}
    .nav a:hover{color:var(--gold-d);}
    .nav a.on{color:var(--gold-d);}
    .nav a.on::after{content:'';position:absolute;left:0;right:0;bottom:-3px;height:2px;background:var(--gold);}
    .nav .dot{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--gold);margin-left:4px;vertical-align:middle;}
    .hd-r{margin-left:auto;display:flex;align-items:center;gap:10px;}
    .burger{display:none;width:32px;height:32px;border:1px solid var(--line-2);background:var(--surface);
      color:var(--tx2);align-items:center;justify-content:center;cursor:pointer;font-size:13.5px;}
    main{padding-top:var(--hd);}

    /* ── Buttons ── */
    /* §7 — "tap targets >= 44px": base/.lg padding+line-height sized to clear 44px total
       height (a real gap the W7 walkthrough caught — the original 36px/41px heights
       looked fine on desktop but fail the plan's own mobile tap-target requirement). */
    .btn{display:inline-flex;align-items:center;gap:7px;font-weight:500;font-size:12.5px;padding:12px 16px;
      min-height:44px;border:1px solid var(--pri);background:var(--pri);color:#fff;
      transition:background .15s,border-color .15s,color .15s;white-space:nowrap;}
    .btn:hover{background:var(--pri-d);border-color:var(--pri-d);}
    .btn.gold{border-color:var(--gold);background:var(--gold);color:var(--pri-d);}
    .btn.gold:hover{background:var(--gold-d);border-color:var(--gold-d);color:#fff;}
    .btn.ghost{background:transparent;color:var(--pri);}
    .btn.ghost:hover{background:var(--pri-soft);}
    .btn.sm{padding:9px 12px;font-size:12px;min-height:38px;} /* header/inline use only — never a primary conversion CTA */
    .btn.lg{padding:14px 20px;font-size:13px;min-height:48px;}

    /* ── Sections ── */
    section{padding:52px 0;}
    .eyebrow{font-size:11px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--gold-d);}
    h1{font-size:38px;font-weight:200;letter-spacing:-.025em;line-height:1.12;}
    h1 b{font-weight:600;color:var(--pri);}
    h2{font-size:24px;font-weight:300;letter-spacing:-.02em;line-height:1.2;}
    .lead{font-size:15px;font-weight:450;color:var(--tx2);line-height:1.6;}

    /* hero (home only) */
    .hero{padding:48px 0 44px;text-align:center;position:relative;overflow:hidden;}
    .hero .eyebrow{margin-bottom:12px;}
    .hero h1{margin-bottom:14px;}
    .hero p{max-width:560px;margin:0 auto 24px;}
    .ctas{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}
    .stat-row{display:flex;gap:30px;justify-content:center;flex-wrap:wrap;margin-top:36px;}
    .stat-row .stat{text-align:center;}
    .stat-row .v{font-size:23px;font-weight:600;color:var(--pri);}
    .stat-row .l{font-size:11px;font-weight:600;color:var(--tx2);text-transform:uppercase;letter-spacing:.06em;margin-top:2px;}

    /* compact "what I do" teaser row (home only — the full grid lives on /services) */
    .icon-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;}
    .icon-row a{display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center;padding:20px 12px;
      border:1px solid var(--line);background:var(--surface);transition:border-color .15s,transform .15s;}
    .icon-row a:hover{border-color:var(--gold);transform:translateY(-2px);}
    .icon-row .ic{width:38px;height:38px;background:var(--gold-soft);color:var(--gold-d);display:flex;align-items:center;justify-content:center;font-size:15px;}
    .icon-row span{font-size:13px;font-weight:600;color:var(--tx);}

    /* page hero (every sub-page) — shorter, left-aligned, no stat row */
    .page-hero{padding:34px 0 30px;border-bottom:1px solid var(--line);background:var(--surface);}
    .page-hero .eyebrow{margin-bottom:8px;}
    .page-hero h1{font-size:28px;font-weight:400;margin-bottom:6px;}
    .page-hero p{color:var(--tx2);font-size:13.5px;font-weight:450;max-width:560px;}

    /* subnav — cross-links between the about-family pages */
    .subnav{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;}
    .subnav a{font-size:12px;font-weight:500;color:var(--tx2);padding:5px 12px;border:1px solid var(--line-2);background:var(--bg);transition:all .15s;}
    .subnav a:hover{border-color:var(--gold);color:var(--gold-d);}
    .subnav a.on{background:var(--pri);border-color:var(--pri);color:#fff;}

    /* module / feature grid */
    .band-surface{background:var(--surface);border-top:1px solid var(--line);border-bottom:1px solid var(--line);}
    .sec-head{text-align:center;max-width:620px;margin:0 auto 40px;}
    .sec-head h2{margin-bottom:10px;}
    .sec-head p{color:var(--tx2);font-size:14px;font-weight:450;}
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
    .proj-card p{font-size:13px;font-weight:450;color:var(--tx2);line-height:1.55;}
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
    form.contact-form input,form.contact-form textarea,form.contact-form select{
      width:100%;border:1px solid var(--line-2);background:var(--surface);padding:12px 14px;font-family:var(--font);
      font-size:13.5px;color:var(--tx);}
    form.contact-form input:focus,form.contact-form textarea:focus,form.contact-form select:focus{outline:2px solid var(--gold);outline-offset:-1px;}
    form.contact-form textarea{min-height:120px;resize:vertical;}
    form.contact-form label{font-size:12px;font-weight:600;color:var(--tx2);margin-bottom:4px;display:block;}
    form.contact-form .row2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    @media(max-width:520px){form.contact-form .row2{grid-template-columns:1fr;}}
    .field-error{font-size:12px;color:#b91c1c;margin-top:4px;}
    .steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin:26px 0 0;}
    .steps .step{border:1px solid var(--line);background:var(--surface);padding:20px;}
    .steps .step .n{width:26px;height:26px;background:var(--pri);color:var(--gold);font-size:12px;font-weight:700;
      display:flex;align-items:center;justify-content:center;margin-bottom:10px;}
    .steps .step h4{font-size:13.5px;font-weight:600;margin-bottom:5px;}
    .steps .step p{font-size:12.5px;color:var(--tx2);line-height:1.5;}
    .alert-success{background:var(--ok-soft);color:var(--ok);border:1px solid var(--ok);padding:12px 16px;font-size:13px;margin-bottom:16px;}
    /* honeypot — hidden from real visitors, catches simple bots */
    .hp-field{position:absolute;left:-9999px;top:-9999px;}

    /* e-learning catalogue */
    .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;
      clip:rect(0,0,0,0);white-space:nowrap;border:0;}
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
    .faq-item h3{font-size:14px;font-weight:600;margin-bottom:6px;}
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
    .foot .blurb{font-size:13px;font-weight:450;color:var(--tx2);max-width:280px;margin-top:12px;line-height:1.6;}
    .foot .foot-h{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--tx3);margin-bottom:12px;}
    .foot a{display:block;font-size:13px;font-weight:500;color:var(--tx2);margin:7px 0;}
    .foot a:hover{color:var(--gold-d);}
    .foot-bar{border-top:1px solid var(--line);margin-top:34px;padding-top:20px;font-size:12px;color:var(--tx3);
      display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;}

    /* mobile menu */
    .mmenu{position:fixed;inset:var(--hd) 0 0 0;z-index:55;background:var(--bg);padding:22px 24px;display:none;flex-direction:column;gap:4px;}
    .mmenu.open{display:flex;}
    .mmenu a{font-size:18px;font-weight:300;color:var(--tx);padding:12px 0;border-bottom:1px solid var(--line);}
    .mmenu .btn{margin-top:16px;justify-content:center;}

    /* ══════════════════════════════════════════════════════════════════════
       Navigation

       Four items, one of which opens a panel. Opening is driven by :hover and
       :focus-within, so the panel works with a mouse, with a keyboard, and
       with JavaScript switched off. Script only adds what CSS cannot express:
       Escape to close, and keeping aria-expanded truthful.
       ══════════════════════════════════════════════════════════════════════ */
    .nav{position:relative;}
    .nav-item{position:relative;display:flex;align-items:center;}
    .nav-link{position:relative;display:inline-flex;align-items:center;gap:6px;color:var(--tx);
      font-weight:600;font-size:13px;padding:2px 0;background:none;border:none;font-family:inherit;
      cursor:pointer;transition:color .15s;}
    .nav-link:hover{color:var(--gold-d);}
    .nav-link.on{color:var(--gold-d);}
    .nav-link.on::after{content:'';position:absolute;left:0;right:0;bottom:-3px;height:2px;background:var(--gold);}
    .nav-link .caret{font-size:9px;transition:transform .22s;}
    .nav-item:hover .caret,.nav-item:focus-within .caret{transform:rotate(180deg);}
    .nav-link .dot{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--gold);vertical-align:middle;}

    /* The panel is anchored to the header, not the item, so a six-entry menu
       can be wide enough to explain itself without being dragged off-screen by
       whichever nav item happens to open it. */
    .mega{position:absolute;top:calc(100% + 10px);left:0;z-index:70;width:min(560px,calc(100vw - 32px));
      background:var(--surface);border:1px solid var(--line);box-shadow:0 22px 48px -20px rgba(11,31,58,.28);
      padding:10px;opacity:0;visibility:hidden;transform:translateY(-6px);
      transition:opacity .18s,transform .18s,visibility .18s;}
    .nav-item:hover > .mega,.nav-item:focus-within > .mega{opacity:1;visibility:visible;transform:none;}
    /* Bridges the 10px gap so the pointer can travel from trigger to panel
       without passing over dead space and closing it. */
    .nav-item.has-menu::after{content:'';position:absolute;top:100%;left:0;right:0;height:12px;}
    .mega-grid{display:grid;grid-template-columns:1fr 1fr;gap:2px;}
    .mega-link{display:flex;gap:10px;padding:9px 11px;align-items:flex-start;transition:background .14s;min-width:0;}
    .mega-link:hover{background:var(--gold-soft);}
    .mega-link.on{background:var(--pri-soft);}
    .mega-link .mi{width:26px;height:26px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
      background:var(--gold-soft);color:var(--gold-d);font-size:12px;}
    .mega-link.on .mi{background:var(--pri);color:var(--gold);}
    .mega-link .mt{display:block;font-size:12.5px;font-weight:600;color:var(--tx);line-height:1.3;}
    .mega-link .md{display:block;font-size:11px;font-weight:450;color:var(--tx2);line-height:1.4;margin-top:2px;}
    .mega-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:8px;
      padding:9px 11px;background:var(--bg);border-top:1px solid var(--line);font-size:11.5px;
      font-weight:500;color:var(--tx2);}

    /* ── Calls to action that say what they do ─────────────────────────────
       The resting label is short enough to scan; the hover label names the
       actual outcome. Both live in the same grid cell so the button is sized
       by the longer of the two and never changes width mid-interaction —
       a button that resizes under the pointer is a button people miss. */
    .cta{display:inline-grid;align-items:center;justify-items:center;overflow:hidden;}
    .cta > span{grid-area:1/1;display:inline-flex;align-items:center;gap:7px;white-space:nowrap;
      transition:transform .3s cubic-bezier(.22,.61,.36,1),opacity .22s;}
    .cta .cta-b{transform:translateY(115%);opacity:0;}
    .cta:hover .cta-a,.cta:focus-visible .cta-a{transform:translateY(-115%);opacity:0;}
    .cta:hover .cta-b,.cta:focus-visible .cta-b{transform:none;opacity:1;}
    @media(prefers-reduced-motion:reduce){
      .cta > span{transition:none;}
      .cta .cta-b{display:none;}
      .cta:hover .cta-a{transform:none;opacity:1;}
    }

    /* ── Account control ──────────────────────────────────────────────────
       Signing in used to displace the two calls to action with account links.
       That is backwards: the actions are what the header is for, and they
       still apply to someone who already has an account — a student can hire,
       a client can enrol. Account navigation is secondary, so it collapses to
       an avatar and gets out of the way.

       (Phrased without quoting the button labels: this comment ships inside
       the inline <style> block, so any label named here would appear in the
       page source for every visitor — including logged-out ones.) */
    .acct{position:relative;display:flex;align-items:center;}
    .acct-trigger{display:inline-flex;align-items:center;gap:6px;background:none;border:none;cursor:pointer;
      font-family:inherit;padding:3px;color:var(--tx2);transition:color .15s;}
    .acct-trigger:hover{color:var(--tx);}
    .acct-av{width:28px;height:28px;flex-shrink:0;background:var(--pri);color:var(--gold);
      display:flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:700;letter-spacing:.02em;}
    .acct-trigger .caret{font-size:9px;transition:transform .2s;}
    .acct:hover .caret,.acct:focus-within .caret{transform:rotate(180deg);}
    .acct-menu{position:absolute;top:calc(100% + 9px);right:0;z-index:70;min-width:210px;
      background:var(--surface);border:1px solid var(--line);box-shadow:0 20px 44px -18px rgba(11,31,58,.28);
      padding:5px;opacity:0;visibility:hidden;transform:translateY(-5px);
      transition:opacity .16s,transform .16s,visibility .16s;}
    .acct:hover > .acct-menu,.acct:focus-within > .acct-menu{opacity:1;visibility:visible;transform:none;}
    /* Bridges the gap so the pointer can reach the menu without closing it. */
    .acct::after{content:'';position:absolute;top:100%;right:0;width:100%;height:11px;}
    .acct-menu a,.acct-menu button{display:flex;align-items:center;gap:9px;width:100%;padding:8px 10px;
      font-family:inherit;font-size:12.5px;font-weight:500;color:var(--tx);background:none;border:none;
      text-align:left;cursor:pointer;transition:background .14s;}
    .acct-menu a:hover,.acct-menu button:hover{background:var(--gold-soft);}
    .acct-menu i{width:14px;color:var(--tx3);font-size:11px;}
    .acct-menu hr{border:none;border-top:1px solid var(--line);margin:4px 0;}
    .acct-menu .danger:hover{color:var(--bad,#b91c1c);}
    .acct-who{padding:8px 10px 6px;border-bottom:1px solid var(--line);margin-bottom:4px;}
    .acct-who .nm{display:block;font-size:12.5px;font-weight:600;color:var(--tx);}
    .acct-who .rl{display:block;font-size:11px;font-weight:450;color:var(--tx2);margin-top:1px;}
    .signin{font-size:12.5px;font-weight:600;color:var(--tx2);white-space:nowrap;padding:6px 2px;}
    .signin:hover{color:var(--gold-d);}

    /* ── Mobile sheet ────────────────────────────────────────────────────── */
    .mm-group{border-bottom:1px solid var(--line);}
    .mm-group > summary{display:flex;align-items:center;justify-content:space-between;gap:10px;
      font-size:17px;font-weight:600;color:var(--tx);padding:13px 0;cursor:pointer;list-style:none;}
    .mm-group > summary::-webkit-details-marker{display:none;}
    .mm-group > summary .chev{font-size:12px;color:var(--tx3);transition:transform .2s;}
    .mm-group[open] > summary .chev{transform:rotate(180deg);}
    .mm-sub{padding:0 0 10px 2px;display:flex;flex-direction:column;}
    .mm-sub a{display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;color:var(--tx2);
      padding:9px 0;border:none;}
    .mm-sub a .mi{width:24px;height:24px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
      background:var(--gold-soft);color:var(--gold-d);font-size:11px;}
    .mm-sub a.on{color:var(--pri);font-weight:600;}
    .mmenu .mm-actions{display:flex;flex-direction:column;gap:8px;margin-top:18px;}
    .mmenu .mm-actions .btn{width:100%;justify-content:center;}

    /* ══════════════════════════════════════════════════════════════════════
       Density

       Deliberately tight. Space is spent where it separates ideas and taken
       back everywhere else, so a page carries more without feeling fuller.
       ══════════════════════════════════════════════════════════════════════ */
    section{padding:40px 0;}
    .sec-head{margin-bottom:26px;}
    .grid{gap:12px;}
    .card{padding:16px 18px;}
    .card .ic{width:32px;height:32px;font-size:14px;margin-bottom:10px;}
    .card h3{font-size:14px;margin-bottom:4px;}
    .card p{font-size:13px;font-weight:450;color:var(--tx2);line-height:1.55;}
    .proj-card{padding:18px;gap:8px;}
    .page-hero{padding:26px 0 0;}

    /* ── Blended bands ───────────────────────────────────────────────────
       Sections used to meet at a hard 1px rule, which chops the page into
       stacked boxes. They now bleed into one another: the border is replaced
       by a short gradient in the neighbouring surface colour, so the eye
       reads one continuous page with changes of light in it. */
    .band-surface{border-top:none;border-bottom:none;position:relative;}
    .band-surface::before,.band-surface::after{content:'';position:absolute;left:0;right:0;height:26px;pointer-events:none;}
    .band-surface::before{top:-26px;background:linear-gradient(to bottom,transparent,var(--surface));}
    .band-surface::after{bottom:-26px;background:linear-gradient(to top,transparent,var(--surface));}
    .band-deep{margin-top:26px;}
    .band-deep::before{}
    /* The dark band still needs a hard edge — a gradient into navy would read
       as a printing fault rather than a transition. It gets a gold hairline
       instead, which announces the change deliberately. */
    .band-deep{border-top:2px solid var(--gold);}

    /* ── Links that reward pointing at them ─────────────────────────────── */
    .link,a.link{position:relative;display:inline-flex;align-items:center;gap:6px;}
    .link::after{content:'';position:absolute;left:0;right:0;bottom:-2px;height:1px;background:currentColor;
      transform:scaleX(0);transform-origin:left;transition:transform .26s cubic-bezier(.22,.61,.36,1);}
    a.link:hover::after,*:hover > .link::after{transform:scaleX(1);}
    .link i{transition:transform .26s cubic-bezier(.22,.61,.36,1);}
    a.link:hover i,*:hover > .link i{transform:translateX(4px);}

    /* Buttons get the same offset plate the cards use, so a call to action is
       visibly the same family of object as everything else on the page.

       Drawn with box-shadow, not a pseudo-element. A ::before at z-index:-1
       paints *above* its own element's background — that is the CSS painting
       order, negative-z-index descendants come after the background box — so
       on a transparent ghost button the plate covered the button face and the
       navy label vanished into it. A shadow is painted entirely outside the
       border box and can never touch the text.

       Each plate is the contrasting ink for the button it sits under, so the
       offset stays visible whichever variant and whichever band it is on. */
    .btn{transition:background .15s,border-color .15s,color .15s,box-shadow .2s cubic-bezier(.22,.61,.36,1);}
    .btn:hover{box-shadow:5px 5px 0 var(--gold);}
    .btn.gold:hover{box-shadow:5px 5px 0 var(--pri);}
    .btn.ghost:hover{box-shadow:5px 5px 0 var(--pri);}
    /* On the navy band a navy plate would be invisible. */
    .band-deep .btn.gold:hover{box-shadow:5px 5px 0 rgba(255,255,255,.32);}
    .btn i{transition:transform .22s;}
    .btn:hover i{transform:translateX(3px);}

    /* ── Segmented sub-navigation ────────────────────────────────────────
       Seven detached grey pills were the main wayfinding for the whole
       about-family and the least designed thing on the site. One connected
       rail instead, sitting on the section rule so it belongs to the content
       below rather than floating above it. */
    .subnav{display:flex;flex-wrap:wrap;gap:0;margin:14px 0 0;border-bottom:1px solid var(--line);}
    .subnav a{position:relative;font-size:13px;font-weight:600;color:var(--tx2);padding:9px 14px;
      border:none;background:none;transition:color .16s;}
    .subnav a::after{content:'';position:absolute;left:0;right:0;bottom:-1px;height:2px;background:var(--gold);
      transform:scaleX(0);transform-origin:center;transition:transform .24s cubic-bezier(.22,.61,.36,1);}
    .subnav a:hover{color:var(--tx);}
    .subnav a:hover::after{transform:scaleX(.4);}
    .subnav a.on{color:var(--pri);font-weight:600;background:none;}
    .subnav a.on::after{transform:scaleX(1);}

    /* ── Page hero: asymmetric, with the section word set as a watermark ─── */
    .page-hero{position:relative;overflow:hidden;}
    .page-hero .wrap{position:relative;z-index:1;}
    .page-hero h1{font-size:30px;font-weight:300;margin-bottom:4px;}
    .page-hero p{max-width:520px;}
    /* An oversized ghost of the page name, cropped by the section. Gives the
       thin repeated header a piece of typography to hang on without adding
       height, which is what a banner image would have cost. */
    .hero-mark{position:absolute;right:-10px;top:50%;transform:translateY(-50%);z-index:0;pointer-events:none;
      font-size:104px;font-weight:700;letter-spacing:-.05em;line-height:1;
      color:var(--pri);opacity:.045;white-space:nowrap;user-select:none;}
    @media(max-width:760px){.hero-mark{display:none;}}

    /* ── Skills: a capability matrix, not a wall of chips ────────────────
       Laid out in CSS columns so the groups pack tight and fill the whole
       measure — the old grid stopped three quarters across and left the right
       edge dead. */
    .skill-cols{columns:3;column-gap:26px;}
    .skill-group{break-inside:avoid;margin:0 0 20px;display:block;}
    .skill-group h4{display:flex;align-items:baseline;gap:8px;font-size:10.5px;font-weight:700;
      text-transform:uppercase;letter-spacing:.07em;color:var(--gold-d);margin-bottom:8px;}
    .skill-group h4 .n{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:10px;color:var(--tx3);font-weight:500;}
    .skill-group h4::after{content:'';flex:1;height:1px;background:var(--line-2);}
    .skill-group ul{list-style:none;display:flex;flex-wrap:wrap;gap:4px;}
    .skill-group li{font-size:12px;font-weight:500;line-height:1.2;color:var(--tx);background:var(--surface);
      padding:5px 8px;border:1px solid var(--line);transition:border-color .15s,color .15s,background .15s;}
    .skill-group li:hover{border-color:var(--gold);color:var(--pri);background:var(--gold-soft);}
    /* The few marked Expert in the owner's own data lead their group and carry
       the accent — a skills page whose items all look identical tells a reader
       nothing about what this person is actually best at. */
    .skill-group li.core{background:var(--pri);border-color:var(--pri);color:#fff;font-weight:600;}
    .skill-group li.core .lv{color:var(--gold);font-size:9.5px;font-weight:700;letter-spacing:.06em;
      text-transform:uppercase;margin-left:5px;}
    .skill-group li.core:hover{background:var(--pri-d);border-color:var(--pri-d);color:#fff;}
    @media(max-width:900px){.skill-cols{columns:2;}}
    @media(max-width:600px){.skill-cols{columns:1;}}

    /* ── Numbered service cards ─────────────────────────────────────────── */
    .svc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;}
    .svc{position:relative;isolation:isolate;border:1px solid var(--line);background:var(--surface);
      padding:16px 18px 18px;display:flex;flex-direction:column;gap:7px;transition:border-color .18s,transform .18s;}
    .svc:hover{border-color:var(--gold);transform:translateY(-2px);}
    .svc::before{content:'';position:absolute;z-index:-1;inset:0;background:var(--gold-soft);border:1px solid var(--line);
      opacity:0;transition:opacity .2s,transform .2s;}
    .svc:hover::before{opacity:1;transform:translate(6px,6px);}
    .svc-top{display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .svc .ic{width:30px;height:30px;background:var(--gold-soft);color:var(--gold-d);display:flex;
      align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
    .svc .no{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:10.5px;font-weight:700;
      color:var(--line-2);letter-spacing:.06em;}
    .svc h3{font-size:14px;font-weight:600;line-height:1.3;}
    .svc p{font-size:13px;font-weight:450;color:var(--tx2);line-height:1.55;}

    /* ── Timeline with a rail you can actually see ──────────────────────── */
    /* The rail position is derived from the same tokens the grid uses. Written
       as a literal it drifts the moment either column changes — which it did:
       the dots sat 17px off the line because the rail was placed at the column
       edge and the dots at the content edge, a gap away. */
    .tl{--tl-w:118px;--tl-gap:22px;
      position:relative;display:grid;grid-template-columns:var(--tl-w) 1fr;gap:0 var(--tl-gap);}
    /* Drawn once for the whole column rather than per row, so it reads as one
       continuous line instead of a stack of separate borders. */
    .tl::before{content:'';position:absolute;left:calc(var(--tl-w) + var(--tl-gap));top:6px;bottom:6px;
      width:1px;background:var(--line-2);}
    .tl-row{display:contents;}
    .tl-when{grid-column:1;text-align:right;padding:0 0 22px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
      font-size:10.5px;font-weight:600;letter-spacing:.04em;color:var(--tx3);white-space:nowrap;padding-top:1px;}
    .tl-what{grid-column:2;position:relative;padding:0 0 22px 20px;}
    .tl-what::before{content:'';position:absolute;left:-4.5px;top:4px;width:9px;height:9px;background:var(--gold);
      box-shadow:0 0 0 3px var(--bg);}
    .tl-row:hover .tl-what::before{background:var(--pri);}
    .tl-what h3{font-size:14.5px;font-weight:600;line-height:1.3;}
    .tl-what .org{font-size:12.5px;color:var(--pri);font-weight:500;margin:2px 0 5px;}
    .tl-what p{font-size:13px;font-weight:450;color:var(--tx2);line-height:1.6;}
    @media(max-width:640px){
      .tl{grid-template-columns:1fr;gap:0;}
      .tl::before{left:4px;}
      .tl-when{grid-column:1;text-align:left;padding:0 0 4px 20px;}
      .tl-what{grid-column:1;padding-left:20px;}
      .tl-what::before{left:0;}
    }

    /* ══════════════════════════════════════════════════════════════════════
       Surface system

       The site was five identical stacks of centred heading + white boxes on
       flat cream. The fix isn't ornament — it's giving each band its own
       surface, and drawing them the way an engineer draws: ruled grids,
       hairlines, indexed sections. All of it is generated in CSS, so the whole
       system costs no image requests and stays crisp at any density.
       ══════════════════════════════════════════════════════════════════════ */

    /* Ruled paper. Two hairline grids at different scales, so the texture reads
       as drafting rather than as a repeating tile. */
    .tex-grid{position:relative;isolation:isolate;}
    .tex-grid::before{content:'';position:absolute;inset:0;z-index:-1;pointer-events:none;
      background-image:
        linear-gradient(var(--line) 1px,transparent 1px),
        linear-gradient(90deg,var(--line) 1px,transparent 1px),
        linear-gradient(rgba(231,227,216,.5) 1px,transparent 1px),
        linear-gradient(90deg,rgba(231,227,216,.5) 1px,transparent 1px);
      background-size:96px 96px,96px 96px,24px 24px,24px 24px;
      background-position:-1px -1px;
      /* Fades out at the edges so the grid never collides with the section rule. */
      -webkit-mask-image:radial-gradient(ellipse 80% 70% at 50% 45%,#000 30%,transparent 100%);
      mask-image:radial-gradient(ellipse 80% 70% at 50% 45%,#000 30%,transparent 100%);
      opacity:.75;}

    /* A single warm light source, low and off-centre. Gives a flat band depth
       without a gradient that announces itself. */
    .tex-glow{position:relative;isolation:isolate;}
    .tex-glow::after{content:'';position:absolute;inset:0;z-index:-1;pointer-events:none;
      background:
        radial-gradient(760px 320px at 78% 0%,rgba(184,147,63,.13),transparent 62%),
        radial-gradient(620px 280px at 8% 100%,rgba(11,31,58,.07),transparent 60%);}

    /* Deep band — used once or twice per page to break the cream monotony. */
    .band-deep{background:var(--pri);color:#fff;border-top:none;border-bottom:none;position:relative;isolation:isolate;}
    .band-deep::before{content:'';position:absolute;inset:0;z-index:-1;pointer-events:none;
      background-image:
        linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);
      background-size:56px 56px,56px 56px;
      -webkit-mask-image:radial-gradient(ellipse 75% 80% at 50% 50%,#000 20%,transparent 100%);
      mask-image:radial-gradient(ellipse 75% 80% at 50% 50%,#000 20%,transparent 100%);}
    .band-deep::after{content:'';position:absolute;inset:0;z-index:-1;pointer-events:none;
      background:radial-gradient(700px 300px at 82% 10%,rgba(184,147,63,.20),transparent 60%);}
    .band-deep h2,.band-deep h3{color:#fff;}
    .band-deep .lead,.band-deep p{color:rgba(255,255,255,.68);}
    .band-deep .eyebrow{color:var(--gold);}
    .band-deep .sec-idx{color:rgba(255,255,255,.35);}
    .band-deep .sec-idx::after{background:rgba(255,255,255,.18);}

    /* ── Indexed section headers ──────────────────────────────────────────
       "01 —— SELECTED WORK". Numbering the sections is how a schematic or a
       syllabus is laid out, which is exactly what this person does for a
       living, and it gives the eye an anchor other than another centred
       heading. */
    .sec-idx{display:flex;align-items:center;gap:10px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
      font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--gold-d);margin-bottom:12px;}
    .sec-idx::after{content:'';flex:1;height:1px;background:var(--line-2);max-width:120px;}
    .sec-head.left{text-align:left;margin-left:0;max-width:640px;}
    .sec-head.left .sec-idx{justify-content:flex-start;}
    .sec-head:not(.left) .sec-idx{justify-content:center;}
    .sec-head:not(.left) .sec-idx::after{display:none;}
    .sec-head:not(.left) .sec-idx::before{content:'';flex:0 0 26px;height:1px;background:var(--line-2);}

    /* ── Cards: the offset-plate motif from the hero, made interactive ──── */
    .proj-card{position:relative;isolation:isolate;}
    .proj-card::before{content:'';position:absolute;z-index:-1;inset:0;background:var(--gold-soft);
      border:1px solid var(--line);opacity:0;transition:opacity .22s,transform .22s;}
    .proj-card:hover::before{opacity:1;transform:translate(7px,7px);}
    /* The arrow leans into the direction it points on hover — a small reward
       for pointing at the thing, and the only motion on the card that isn't
       the plate. */
    .proj-card .link i{transition:transform .22s;}
    .proj-card:hover .link i{transform:translateX(4px);}

    /* ── Duotone course covers ───────────────────────────────────────────
       The uploaded cover art is loud stock rendering in reds that fight the
       navy-and-gold palette and pull every eye on the page. Rather than
       discard the owner's images, they're desaturated and re-tinted into the
       brand's own light, and the full original colour returns on hover — so
       the artwork still does its job when someone is actually looking at it. */
    .course-cover{position:relative;overflow:hidden;}
    .course-cover img{transition:filter .35s ease,transform .5s ease;filter:grayscale(1) contrast(1.04) brightness(1.04);}
    .course-cover::after{content:'';position:absolute;inset:0;pointer-events:none;
      background:linear-gradient(150deg,var(--pri) 8%,rgba(11,31,58,.5) 58%,rgba(184,147,63,.6) 100%);
      mix-blend-mode:color;transition:opacity .35s ease;}
    .course-cover::before{content:'';position:absolute;inset:0;z-index:1;pointer-events:none;
      background:linear-gradient(to top,rgba(6,15,31,.28),transparent 58%);}
    .proj-card:hover .course-cover img{filter:none;transform:scale(1.03);}
    .proj-card:hover .course-cover::after{opacity:0;}
    /* The catalogue reuses a handful of stock renders, so an identical tint on
       every card turns the grid into one repeated rectangle. Rotating the light
       across the row gives each card its own value without touching the files. */
    .grid > .proj-card:nth-child(3n+2) .course-cover::after{
      background:linear-gradient(150deg,rgba(184,147,63,.55) 0%,rgba(11,31,58,.5) 45%,var(--pri) 100%);}
    .grid > .proj-card:nth-child(3n+3) .course-cover::after{
      background:linear-gradient(115deg,var(--pri) 20%,rgba(125,98,40,.6) 100%);}

    /* ── Work cards with a visual, not just a text block ────────────────── */
    .work-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:20px;}
    .work-card{position:relative;isolation:isolate;border:1px solid var(--line);background:var(--surface);
      display:flex;flex-direction:column;transition:border-color .2s,transform .2s;}
    .work-card:hover{border-color:var(--gold);transform:translateY(-3px);}
    .work-card::before{content:'';position:absolute;z-index:-1;inset:0;background:var(--gold-soft);
      border:1px solid var(--line);opacity:0;transition:opacity .22s,transform .22s;}
    .work-card:hover::before{opacity:1;transform:translate(8px,8px);}
    .work-shot{position:relative;aspect-ratio:16/10;overflow:hidden;border-bottom:1px solid var(--line);background:var(--surface-2);}
    .work-shot .ph{height:100%;border:none;}
    .work-shot img{width:100%;height:100%;object-fit:cover;}
    /* The project number sits on the artwork like a plate on a machine. */
    .work-no{position:absolute;z-index:2;top:0;left:0;background:var(--pri);color:var(--gold);
      font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:10.5px;font-weight:700;
      letter-spacing:.08em;padding:5px 9px;}
    .work-body{padding:18px 20px 20px;display:flex;flex-direction:column;gap:9px;flex:1;}
    .work-body h3{font-size:15.5px;font-weight:600;line-height:1.35;}
    .work-body p{font-size:13px;font-weight:450;color:var(--tx2);line-height:1.6;}
    .work-body .link{margin-top:auto;font-size:12.5px;font-weight:600;color:var(--pri);padding-top:4px;}
    .work-body .link i{transition:transform .22s;}
    .work-card:hover .work-body .link i{transform:translateX(4px);}

    /* ── Vertical section rail (the about-family) ─────────────────────────
       The horizontal tab strip only showed where you were; every other section
       was a word you had to read and click to find out about. As a rail down
       the side, all of them stay on screen beside the content — you can see
       what is next without going there, which is the whole point of a
       sub-navigation. It sticks, so it is still there after scrolling. */
    .rail-layout{display:grid;grid-template-columns:210px minmax(0,1fr);gap:34px;align-items:start;}
    .rail{position:sticky;top:calc(var(--hd) + 14px);display:flex;flex-direction:column;}
    .rail-h{font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--tx3);
      padding:0 0 8px;border-bottom:1px solid var(--line);margin-bottom:4px;}
    .rail a{position:relative;display:flex;align-items:center;gap:10px;padding:8px 10px;
      font-size:13px;font-weight:500;color:var(--tx2);border-left:2px solid transparent;transition:.15s;}
    .rail a .ri{width:16px;text-align:center;font-size:11.5px;color:var(--tx3);transition:color .15s;}
    .rail a:hover{background:var(--surface);color:var(--tx);}
    .rail a:hover .ri{color:var(--gold-d);}
    .rail a.on{background:var(--surface);border-left-color:var(--gold);color:var(--pri);font-weight:600;}
    .rail a.on .ri{color:var(--gold-d);}
    /* A one-line description of each destination, so the rail answers "what is
       in there" rather than only "what is it called". Hidden on the active
       item, where the page itself is already the answer. */
    .rail a .rd{display:block;font-size:10.5px;font-weight:450;color:var(--tx3);line-height:1.35;margin-top:1px;}
    .rail a.on .rd{display:none;}
    .rail-foot{margin-top:12px;padding-top:10px;border-top:1px solid var(--line);}

    @media(max-width:900px){
      /* Below the rail's width it becomes a scrolling strip, still showing the
         neighbouring sections rather than hiding them behind a menu. */
      .rail-layout{grid-template-columns:1fr;gap:16px;}
      .rail{position:static;flex-direction:row;overflow-x:auto;gap:2px;padding-bottom:2px;
        border-bottom:1px solid var(--line);-webkit-overflow-scrolling:touch;}
      .rail-h,.rail a .rd,.rail-foot{display:none;}
      .rail a{border-left:none;border-bottom:2px solid transparent;white-space:nowrap;padding:9px 12px;}
      .rail a.on{border-left-color:transparent;border-bottom-color:var(--gold);}
    }

    /* ── Source code: a terminal, answering the browser frame above ───────
       The work section frames screenshots in browser chrome to say "this is
       running". This frames the catalogue in a terminal to say "this is what
       is behind it". Each row is a real product and a real link — the window
       is the listing itself, not a picture of one. */
    .code-band{padding:44px 0;}
    .term{border:1px solid rgba(255,255,255,.14);background:rgba(6,15,31,.55);
      font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;overflow:hidden;}
    .term-bar{display:flex;align-items:center;gap:6px;padding:9px 12px;
      background:rgba(255,255,255,.05);border-bottom:1px solid rgba(255,255,255,.12);}
    .term-bar i{width:9px;height:9px;border-radius:50%;background:rgba(255,255,255,.22);display:block;}
    .term-bar .path{margin-left:10px;font-size:11px;color:rgba(255,255,255,.42);letter-spacing:.02em;}
    .term-body{padding:14px 6px 12px;}
    .term-line{font-size:12px;color:rgba(255,255,255,.5);padding:2px 12px;}
    .term-line .p{color:var(--gold);font-weight:700;margin-right:6px;}
    .caret{display:inline-block;width:7px;height:13px;background:var(--gold);vertical-align:-2px;}
    @media(prefers-reduced-motion:no-preference){
      .caret{animation:blink 1.1s steps(1) infinite;}
      @keyframes blink{0%,50%{opacity:1;}51%,100%{opacity:0;}}
    }
    .term-list{list-style:none;margin:6px 0;}
    .term-row{display:grid;grid-template-columns:96px 62px minmax(0,1fr) auto;align-items:center;gap:14px;
      padding:8px 12px;font-size:12.5px;color:rgba(255,255,255,.82);transition:background .14s,color .14s;}
    .term-row:hover{background:rgba(184,147,63,.14);color:#fff;}
    .term-row .perm{color:rgba(255,255,255,.3);font-size:11.5px;}
    .term-row .size{color:rgba(255,255,255,.5);font-size:11.5px;text-align:right;}
    .term-row .name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .term-row:hover .name{text-decoration:underline;text-underline-offset:3px;}
    .term-row .price{color:var(--gold);font-weight:700;white-space:nowrap;}
    .code-actions{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:20px;}
    .code-note{font-size:11.5px;font-weight:450;color:rgba(255,255,255,.5);}
    @media(max-width:640px){
      .term-row{grid-template-columns:minmax(0,1fr) auto;gap:8px;}
      .term-row .perm,.term-row .size{display:none;}   /* detail nobody reads on a phone */
    }

    /* ── Logo marquee ─────────────────────────────────────────────────────
       Thirteen names in a static grid is a wall; moving slowly, it reads as a
       list that continues past the edge of the screen. Two identical tracks
       scroll as one and the animation resets after exactly one track width, so
       the loop is seamless with no script and no cloned-node bookkeeping. */
    .logos-band{padding:30px 0;overflow:hidden;}
    .marquee{position:relative;display:flex;width:max-content;
      -webkit-mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent);
      mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent);}
    .marquee-track{display:flex;align-items:center;list-style:none;flex-shrink:0;}
    .marquee-item{display:flex;align-items:center;justify-content:center;
      padding:0 26px;height:56px;flex-shrink:0;}
    .marquee-item img{max-height:34px;width:auto;object-fit:contain;
      filter:grayscale(1);opacity:.62;transition:filter .2s,opacity .2s;}
    .marquee-item:hover img{filter:none;opacity:1;}
    .marquee-item .wordmark{font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
      color:var(--tx3);white-space:nowrap;transition:color .2s;}
    .marquee-item:hover .wordmark{color:var(--pri);}
    @media(prefers-reduced-motion:no-preference){
      .marquee-track{animation:marquee 46s linear infinite;}
      /* Pausing on hover is what makes it usable rather than decorative —
         a name you want to read stops moving when you point at it. */
      .marquee:hover .marquee-track{animation-play-state:paused;}
    }
    @keyframes marquee{from{transform:translateX(0);}to{transform:translateX(-100%);}}

    /* ── Photo mosaic ─────────────────────────────────────────────────────
       One frame given real size so the section has a subject, the rest packed
       around it. Six equal thumbnails read as filler. */
    .mosaic{display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:repeat(2,138px);gap:8px;}
    .mosaic-cell{position:relative;overflow:hidden;border:1px solid var(--line);
      background:var(--surface-2);display:block;}
    .mosaic-cell.lead{grid-column:span 2;grid-row:span 2;}
    .mosaic-cell img{width:100%;height:100%;object-fit:cover;transition:transform .55s ease;}
    .mosaic-cell:hover img{transform:scale(1.05);}
    .mosaic-cap{position:absolute;left:0;right:0;bottom:0;padding:20px 11px 9px;
      background:linear-gradient(to top,rgba(6,15,31,.88),transparent);
      opacity:0;transform:translateY(6px);transition:opacity .22s,transform .22s;}
    .mosaic-cell:hover .mosaic-cap,.mosaic-cell:focus-visible .mosaic-cap{opacity:1;transform:none;}
    .mosaic-cap .t{display:block;font-size:12px;font-weight:600;color:#fff;line-height:1.3;}
    .mosaic-cap .c{display:none;font-size:10.5px;font-weight:450;color:rgba(255,255,255,.72);line-height:1.4;margin-top:2px;}
    .mosaic-cell.lead .mosaic-cap .c{display:block;}
    .mosaic-cell.lead .mosaic-cap .t{font-size:14px;}
    /* The closing tile is a door, not a photograph — it says how much more
       there is and where to go, which a seventh thumbnail would not. */
    .mosaic-more{display:flex;flex-direction:column;align-items:flex-start;
      justify-content:center;gap:2px;padding:16px 18px;border:1px solid var(--line);
      background:var(--surface);transition:border-color .18s,background .18s;}
    .mosaic-more:hover{border-color:var(--gold);background:var(--gold-soft);}
    .mosaic-more .n{font-size:26px;font-weight:200;color:var(--pri);line-height:1;}
    .mosaic-more .l{font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--tx3);}
    .mosaic-more .link{margin-top:8px;font-size:12.5px;font-weight:600;color:var(--pri);}
    @media(max-width:820px){
      .mosaic{grid-template-columns:repeat(2,1fr);grid-template-rows:repeat(3,120px);}
      .mosaic-cell.lead{grid-column:span 2;grid-row:span 2;}
      .mosaic-more{grid-column:span 2;}
    }

    /* ── About, on the home page ──────────────────────────────────────────
       Two columns of prose at a comfortable measure. Long-form lives on
       /about; this is the ten-second version. */
    .about-lead{max-width:900px;}
    .about-cols{columns:2;column-gap:34px;}
    .about-cols p{font-size:14px;font-weight:450;color:var(--tx2);line-height:1.7;margin-bottom:1em;
      break-inside:avoid;}
    .about-cols p:last-child{margin-bottom:0;}
    .about-actions{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:18px;}
    @media(max-width:720px){.about-cols{columns:1;}}

    /* ── References ───────────────────────────────────────────────────────
       Named people with links out, rather than anonymous praise. A card works
       with or without a quote: until someone's own words are in hand, who they
       are and where to check is the substance. */
    .ref-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;}
    .ref{border:1px solid var(--line);background:var(--surface);padding:18px;margin:0;
      display:flex;flex-direction:column;gap:12px;transition:border-color .18s,transform .18s;}
    .ref:hover{border-color:var(--gold);transform:translateY(-2px);}
    .ref blockquote{font-size:13.5px;font-weight:450;line-height:1.65;color:var(--tx);}
    .ref blockquote::before{content:'\201C';display:block;font-size:32px;line-height:.7;color:var(--gold);font-weight:600;margin-bottom:4px;}
    .ref figcaption{display:flex;align-items:center;gap:11px;margin-top:auto;}
    /* Two classes deep on purpose: the .ph slot sets width:100% and is defined
       later in this sheet, so a single-class rule here loses and the avatar
       stretches the whole card. */
    .ref figcaption .ref-avatar{width:42px;height:42px;flex:0 0 42px;padding:6px;gap:0;}
    /* At 42px there is no room for the slot's guidance text — the icon alone
       reads as "a photo goes here", the words just become noise. */
    .ref figcaption .ref-avatar .ph-label,
    .ref figcaption .ref-avatar .ph-size,
    .ref figcaption .ref-avatar .ph-path{display:none;}
    .ref figcaption .ref-avatar i{font-size:14px;}
    .ref-who{min-width:0;flex:1;}
    .ref-who .nm{display:block;font-size:13px;font-weight:600;color:var(--tx);line-height:1.3;}
    .ref-who .rl{display:block;font-size:11.5px;font-weight:450;color:var(--tx2);line-height:1.4;margin-top:1px;}
    .ref-who .og{display:block;font-size:11px;font-weight:500;color:var(--gold-d);line-height:1.35;margin-top:1px;}
    .ref-link{font-size:12px;font-weight:600;color:var(--pri);align-self:flex-start;}

    /* ── Shop ─────────────────────────────────────────────────────────────── */
    .shop-filters{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:18px;}
    .shop-filters .tb-input{width:auto;min-width:150px;border:1px solid var(--line-2);background:var(--surface);
      padding:9px 11px;font-family:var(--font);font-size:13px;color:var(--tx);}
    .shop-filters input[type=search]{flex:1;min-width:190px;}
    .shop-filters .tb-input:focus{outline:2px solid var(--gold);outline-offset:-1px;}

    .price-row{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;margin-top:2px;}
    .price{font-size:16px;font-weight:700;color:var(--pri);}
    .price.free{color:var(--gold-d);}
    .price-row .was{font-size:12.5px;color:var(--tx3);text-decoration:line-through;}
    .price-row .meta{font-size:11px;color:var(--tx3);margin-left:auto;}
    .buy-row{display:flex;gap:6px;margin-top:auto;padding-top:10px;}
    .buy-row .btn{flex:1;justify-content:center;}

    .product-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:34px;align-items:start;}
    .cart-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:24px;align-items:start;}
    .buy-box{border:1px solid var(--line);background:var(--surface);padding:20px;position:sticky;top:calc(var(--hd) + 14px);}
    .buy-box .includes{list-style:none;margin:0 0 16px;}
    .buy-box .includes li{position:relative;font-size:12.5px;font-weight:450;color:var(--tx2);padding:5px 0 5px 20px;}
    .buy-box .includes li::before{content:'\2713';position:absolute;left:0;color:var(--gold-d);font-weight:700;}
    .pay-icons{display:flex;flex-wrap:wrap;gap:6px;margin:14px 0 8px;}
    .pay-icons span{font-size:10.5px;color:var(--tx3);border:1px solid var(--line-2);padding:4px 8px;}
    .money-comfort{font-size:11px;color:var(--tx3);text-align:center;line-height:1.5;}
    @media(max-width:900px){
      .product-layout,.cart-layout{grid-template-columns:1fr;}
      .buy-box{position:static;order:-1;}
    }

    /* Basket indicator — only shown when there is something in it, so an empty
       basket never adds noise to the header. */
    .cart-link{position:relative;display:inline-flex;align-items:center;justify-content:center;
      width:34px;height:34px;color:var(--tx2);transition:color .15s;}
    .cart-link:hover{color:var(--gold-d);}
    .cart-count{position:absolute;top:-2px;right:-3px;min-width:16px;height:16px;padding:0 4px;
      background:var(--gold);color:var(--pri-d);font-size:9.5px;font-weight:700;
      display:flex;align-items:center;justify-content:center;}

    /* ── Gallery ──────────────────────────────────────────────────────────
       A column masonry rather than a grid of equal boxes: these photographs
       are a mix of portrait, landscape and square, and forcing them all into
       one crop throws away the framing of every shot that is not that shape. */
    .gal-filters{display:flex;flex-wrap:wrap;gap:0;border-bottom:1px solid var(--line);margin-bottom:16px;}
    .gal-filters a{position:relative;font-size:12.5px;font-weight:600;color:var(--tx2);padding:9px 14px;}
    .gal-filters a::after{content:'';position:absolute;left:0;right:0;bottom:-1px;height:2px;background:var(--gold);
      transform:scaleX(0);transition:transform .24s cubic-bezier(.22,.61,.36,1);}
    .gal-filters a:hover{color:var(--tx);} .gal-filters a:hover::after{transform:scaleX(.4);}
    .gal-filters a.on{color:var(--pri);} .gal-filters a.on::after{transform:scaleX(1);}
    .gal-filters .n{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:10.5px;color:var(--tx3);margin-left:4px;}

    .gal-grid{columns:4;column-gap:10px;}
    .gal-item{break-inside:avoid;position:relative;display:block;width:100%;margin:0 0 10px;padding:0;
      border:1px solid var(--line);background:var(--surface-2);cursor:zoom-in;overflow:hidden;font-family:inherit;
      transition:border-color .18s,transform .18s;}
    .gal-item:hover{border-color:var(--gold);transform:translateY(-2px);}
    .gal-item img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s ease,filter .3s;}
    .gal-item:hover img{transform:scale(1.04);}
    .gal-cap{position:absolute;left:0;right:0;bottom:0;padding:22px 12px 10px;text-align:left;
      background:linear-gradient(to top,rgba(6,15,31,.86),transparent);
      opacity:0;transform:translateY(6px);transition:opacity .22s,transform .22s;}
    .gal-item:hover .gal-cap,.gal-item:focus-visible .gal-cap{opacity:1;transform:none;}
    .gal-t{display:block;font-size:12.5px;font-weight:600;color:#fff;line-height:1.3;}
    .gal-c{display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
      color:var(--gold);margin-top:2px;}
    .gal-zoom{position:absolute;top:8px;right:8px;width:26px;height:26px;display:flex;align-items:center;
      justify-content:center;background:rgba(255,255,255,.92);color:var(--pri);font-size:10px;
      opacity:0;transform:scale(.85);transition:opacity .2s,transform .2s;}
    .gal-item:hover .gal-zoom,.gal-item:focus-visible .gal-zoom{opacity:1;transform:none;}
    @media(max-width:1000px){.gal-grid{columns:3;}}
    @media(max-width:700px){.gal-grid{columns:2;}}
    @media(max-width:420px){.gal-grid{columns:1;}}

    /* ── Lightbox ─────────────────────────────────────────────────────────── */
    .lb{position:fixed;inset:0;z-index:200;background:rgba(6,15,31,.94);
      display:flex;align-items:center;justify-content:center;padding:40px 56px;}
    .lb[hidden]{display:none;}
    .lb-stage{max-width:min(1100px,100%);max-height:100%;display:flex;flex-direction:column;
      align-items:center;gap:12px;margin:0;}
    .lb-img{max-width:100%;max-height:calc(100vh - 150px);width:auto;height:auto;object-fit:contain;
      border:1px solid rgba(255,255,255,.12);background:#0b1f3a;}
    .lb-meta{text-align:center;max-width:620px;}
    .lb-count{display:block;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:10.5px;
      letter-spacing:.1em;color:var(--gold);margin-bottom:4px;}
    .lb-title{display:block;font-size:15px;font-weight:600;color:#fff;}
    .lb-caption{display:block;font-size:12.5px;font-weight:450;color:rgba(255,255,255,.66);line-height:1.55;margin-top:3px;}
    .lb button{position:absolute;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);
      color:#fff;cursor:pointer;width:42px;height:42px;display:flex;align-items:center;justify-content:center;
      font-size:15px;transition:background .16s,border-color .16s;}
    .lb button:hover{background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.4);}
    .lb button:focus-visible{outline:2px solid var(--gold);outline-offset:2px;}
    .lb-close{top:18px;right:18px;}
    .lb-nav.prev{left:12px;top:50%;transform:translateY(-50%);}
    .lb-nav.next{right:12px;top:50%;transform:translateY(-50%);}
    @media(max-width:640px){
      .lb{padding:52px 10px 20px;}
      .lb-nav.prev{left:6px;} .lb-nav.next{right:6px;}
      .lb button{width:38px;height:38px;}
      .lb-img{max-height:calc(100vh - 200px);}
    }

    /* ── Photo strips embedded in other pages ─────────────────────────────── */
    .photo-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;}
    .photo-strip a{position:relative;display:block;overflow:hidden;border:1px solid var(--line);aspect-ratio:1;}
    .photo-strip img{width:100%;height:100%;object-fit:cover;transition:transform .5s ease;}
    .photo-strip a:hover img{transform:scale(1.05);}
    .photo-strip a::after{content:'';position:absolute;inset:0;background:var(--pri);opacity:0;transition:opacity .2s;}
    .photo-strip a:hover::after{opacity:.12;}

    /* ══════════════════════════════════════════════════════════════════════
       Artwork slots
       A slot renders the real image once the file exists and a labelled
       drop-target until then, so the page can be reviewed and shipped before
       a single photo has been taken.
       ══════════════════════════════════════════════════════════════════════ */
    .ph{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;text-align:center;
      padding:16px;background:
        repeating-linear-gradient(-45deg,var(--surface-2) 0 10px,transparent 10px 20px),var(--surface);
      border:1px dashed var(--line-2);color:var(--tx3);width:100%;}
    .ph i{font-size:20px;opacity:.5;}
    .ph-label{font-size:11.5px;font-weight:600;color:var(--tx2);line-height:1.35;}
    .ph-size{font-size:10.5px;letter-spacing:.04em;opacity:.8;}
    .ph-path{font-size:9.5px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;opacity:.65;word-break:break-all;}
    .ph.round{border-radius:50%;}
    .ph-img{width:100%;height:100%;object-fit:cover;}
    .ph-img.round{border-radius:50%;}
    .ph-img.contain{object-fit:contain;}

    /* ── Hero: portrait beside the claim ── */
    .hero-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:48px;align-items:center;text-align:left;}
    .hero-grid .ctas{justify-content:flex-start;}
    .hero-grid .hero-copy p{margin-left:0;margin-right:0;}
    .hero-portrait{position:relative;max-width:340px;margin-left:auto;width:100%;}
    .hero-portrait .ph,.hero-portrait .ph-img{position:relative;z-index:1;}
    /* A soft gold plate offset behind the portrait — depth without a drop shadow,
       which would read as a different design language from the flat squares. */
    .hero-portrait::after{content:'';position:absolute;inset:14px -14px -14px 14px;background:var(--gold-soft);
      border:1px solid var(--line);z-index:0;}
    .hero-badge{position:absolute;z-index:2;left:-14px;bottom:26px;background:var(--surface);border:1px solid var(--line);
      padding:9px 13px;display:flex;align-items:center;gap:9px;max-width:210px;}
    .hero-badge .n{font-size:16px;font-weight:700;color:var(--pri);line-height:1;}
    .hero-badge .t{font-size:10.5px;color:var(--tx3);text-transform:uppercase;letter-spacing:.05em;line-height:1.3;}

    /* ── Trusted-by logo strip ── */
    /* Each cell draws its own hairline instead of the grid showing a background
       through its gaps: an odd number of logos leaves a blank cell on narrow
       screens, and a background-based grid renders that blank as a tinted block
       that reads like a broken image. */
    .logo-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1px;}
    .logo-strip .cell{background:var(--surface);display:flex;align-items:center;justify-content:center;
      padding:18px 14px;min-height:78px;box-shadow:0 0 0 1px var(--line);}
    .logo-strip .cell .ph{border:none;background:none;padding:0;gap:2px;}
    .logo-strip .cell .ph i{font-size:14px;}
    /* Until a logo file lands, the organisation's name IS the mark — a wordmark
       reads as deliberate, where an empty grey box reads as broken. */
    .logo-strip .wordmark{font-size:11.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;
      color:var(--tx2);text-align:center;line-height:1.35;}
    .logo-strip img{max-height:40px;width:auto;object-fit:contain;filter:grayscale(1);opacity:.72;transition:filter .2s,opacity .2s;}
    .logo-strip .cell:hover img{filter:none;opacity:1;}

    /* ── Systems showcase ── */
    .shot-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;}
    .shot{border:1px solid var(--line);background:var(--surface);display:flex;flex-direction:column;
      transition:border-color .18s,transform .18s;}
    .shot:hover{border-color:var(--gold);transform:translateY(-3px);}
    /* A browser chrome around each screenshot: it frames a raw screengrab as a
       product shot, and hides the fact that the shots differ in edge and crop. */
    .shot-frame{border-bottom:1px solid var(--line);background:var(--surface-2);}
    .shot-bar{display:flex;align-items:center;gap:5px;padding:8px 10px;border-bottom:1px solid var(--line);}
    .shot-bar i{width:8px;height:8px;border-radius:50%;background:var(--line-2);display:block;}
    .shot-bar .u{flex:1;margin-left:6px;height:14px;background:var(--bg);border:1px solid var(--line);}
    .shot-shot{aspect-ratio:16/10;overflow:hidden;background:var(--bg);}
    .shot-shot .ph{height:100%;border:none;}
    .shot-body{padding:16px 18px 18px;display:flex;flex-direction:column;gap:7px;flex:1;}
    .shot-body h3{font-size:15px;font-weight:600;}
    .shot-body p{font-size:13px;font-weight:450;color:var(--tx2);line-height:1.55;}
    .shot-body .link{font-size:12.5px;font-weight:600;color:var(--pri);}
    /* The address bar shows the project's real domain rather than an empty
       grey pill — the frame stops being decoration and starts carrying a fact. */
    .shot-bar .u{display:flex;align-items:center;padding:0 8px;height:16px;background:var(--bg);
      border:1px solid var(--line);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
      font-size:9px;color:var(--tx3);letter-spacing:.02em;overflow:hidden;white-space:nowrap;}
    .shot-shot{display:block;}
    .shot-points{list-style:none;margin:2px 0 0;display:flex;flex-direction:column;gap:3px;}
    .shot-points li{position:relative;padding-left:14px;font-size:11.5px;font-weight:450;
      color:var(--tx2);line-height:1.45;}
    .shot-points li::before{content:'';position:absolute;left:2px;top:6px;width:4px;height:4px;background:var(--gold);}
    .shot-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;
      flex-wrap:wrap;margin-top:auto;padding-top:10px;border-top:1px solid var(--line);}
    .shot-live{font-size:11.5px;font-weight:600;color:var(--gold-d);white-space:nowrap;}
    .shot-live:hover{color:var(--pri);}
    .shot-body h3 a:hover{color:var(--gold-d);}

    /* ── Testimonials ── */
    .quote-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;}
    .quote{border:1px solid var(--line);background:var(--surface);padding:24px;display:flex;flex-direction:column;gap:14px;}
    .quote blockquote{font-size:14px;line-height:1.65;color:var(--tx);font-weight:300;}
    .quote blockquote::before{content:'\201C';display:block;font-size:38px;line-height:.7;color:var(--gold);
      font-weight:600;margin-bottom:6px;}
    .quote figcaption{display:flex;align-items:center;gap:11px;margin-top:auto;}
    .quote .avatar{width:40px;height:40px;flex-shrink:0;}
    .quote .who{min-width:0;}
    .quote .who .nm{font-size:12.5px;font-weight:600;}
    .quote .who .rl{font-size:11.5px;color:var(--tx3);line-height:1.35;}

    /* ══════════════════════════════════════════════════════════════════════
       Motion
       Content is visible by default and animation only ever *removes* an
       offset, so a failed script or a blocked observer can never leave the
       page blank. Anyone who asked their system for less motion gets none.
       ══════════════════════════════════════════════════════════════════════ */
    @media(prefers-reduced-motion:no-preference){
      .js [data-rise]{opacity:0;transform:translateY(14px);
        transition:opacity .6s cubic-bezier(.22,.61,.36,1),transform .6s cubic-bezier(.22,.61,.36,1);
        transition-delay:var(--d,0ms);}
      .js [data-rise].in{opacity:1;transform:none;}
      .hero-portrait::after{transition:transform .8s cubic-bezier(.22,.61,.36,1);}
      .js .hero-portrait:hover::after{transform:translate(4px,4px);}
    }
    /* Without JS nothing is ever hidden, so no fallback rule is needed. */

    @media(max-width:860px){
      .hero-grid{grid-template-columns:1fr;gap:32px;text-align:center;}
      .hero-grid .ctas{justify-content:center;}
      .hero-grid .hero-copy p{margin-left:auto;margin-right:auto;}
      .hero-portrait{margin:0 auto;max-width:300px;}
      .hero-badge{left:auto;right:-8px;}
    }
    @media(max-width:560px){
      .hero-portrait::after{inset:10px -10px -10px 10px;}
      .hero-badge{position:static;margin-top:16px;max-width:none;justify-content:center;}
    }

    @media(max-width:820px){
      .nav{display:none;} .burger{display:inline-flex;}
      .hd-r .btn.desk,.hd-r .acct.desk,.hd-r .signin.desk{display:none;}
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

@php
  $r = fn($n) => request()->routeIs($n) ? 'on' : '';
  $nav = \App\Support\SiteNav::items();
  $isOn = fn (array $item) => request()->routeIs(...($item['match'] ?? []));
  /* Role-aware account entry point: every signed-in visitor gets a direct door to
     THEIR side of the platform, from every public page. */
  $u = auth()->user();
  if ($u?->isAdmin()) {
      [$accountLabel, $accountUrl, $accountIcon] = ['Dashboard', route('dashboard'), 'fa-gauge'];
  } elseif ($u?->isClient()) {
      [$accountLabel, $accountUrl, $accountIcon] = ['My Projects', route('portal.index'), 'fa-diagram-project'];
  } else {
      [$accountLabel, $accountUrl, $accountIcon] = ['My Courses', route('learn.index'), 'fa-graduation-cap'];
  }
@endphp

<header class="site">
  <div class="wrap bar">
    <a href="{{ route('home') }}" wire:navigate class="brand"><span class="badge">MM</span> Muhindo Mubaraka</a>

    <nav class="nav" aria-label="Main">
      @foreach($nav as $item)
        @php $on = $isOn($item); @endphp
        @if(empty($item['children']))
          <div class="nav-item">
            <a href="{{ $item['url'] }}" wire:navigate class="nav-link {{ $on ? 'on' : '' }}"
               @if($on) aria-current="page" @endif>
              {{ $item['label'] }}@if(!empty($item['flag']))<span class="dot" aria-hidden="true"></span>@endif
            </a>
          </div>
        @else
          <div class="nav-item has-menu">
            <button type="button" class="nav-link {{ $on ? 'on' : '' }}"
                    aria-expanded="false" aria-controls="mega-{{ Str::slug($item['label']) }}">
              {{ $item['label'] }} <i class="fas fa-chevron-down caret" aria-hidden="true"></i>
            </button>
            <div class="mega" id="mega-{{ Str::slug($item['label']) }}">
              <div class="mega-grid">
                @foreach($item['children'] as $child)
                  <a href="{{ $child['url'] }}" wire:navigate class="mega-link {{ $isOn($child) ? 'on' : '' }}">
                    <span class="mi"><i class="fas {{ $child['icon'] }}" aria-hidden="true"></i></span>
                    <span>
                      <span class="mt">{{ $child['label'] }}</span>
                      <span class="md">{{ $child['desc'] }}</span>
                    </span>
                  </a>
                @endforeach
              </div>
              @if(!empty($item['blurb']))
                <div class="mega-foot">
                  <span>{{ $item['blurb'] }}</span>
                  <a href="{{ route('contact') }}" wire:navigate class="link" style="color:var(--pri);font-weight:600;white-space:nowrap;">
                    Hire me <i class="fas fa-arrow-right"></i>
                  </a>
                </div>
              @endif
            </div>
          </div>
        @endif
      @endforeach
    </nav>

    <div class="hd-r">
      {{-- The two calls to action are permanent. Signing in does not remove
           them: a student can still hire, and a client can still enrol. --}}
      <a href="{{ route('start-a-project') }}" wire:navigate class="btn ghost desk sm cta">
        <span class="cta-a">Hire Me</span>
        <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
      </a>
      <a href="{{ route('courses.index') }}" wire:navigate class="btn gold desk sm cta">
        <span class="cta-a">Learn</span>
        <span class="cta-b" aria-hidden="true">Start Learning <i class="fas fa-arrow-right"></i></span>
      </a>

      @if(app(\App\Services\Shop\Cart::class)->count() > 0)
        <a href="{{ route('cart.show') }}" wire:navigate class="cart-link" aria-label="Basket, {{ app(\App\Services\Shop\Cart::class)->count() }} items">
          <i class="fas fa-basket-shopping" aria-hidden="true"></i>
          <span class="cart-count">{{ app(\App\Services\Shop\Cart::class)->count() }}</span>
        </a>
      @endif

      @auth
        <div class="acct desk">
          <button type="button" class="acct-trigger" aria-expanded="false" aria-controls="acct-menu">
            <span class="acct-av" aria-hidden="true">{{ $u->initials }}</span>
            <span class="sr-only">Account menu for {{ $u->name }}</span>
            <i class="fas fa-chevron-down caret" aria-hidden="true"></i>
          </button>
          <div class="acct-menu" id="acct-menu">
            <div class="acct-who">
              <span class="nm">{{ $u->name }}</span>
              <span class="rl">{{ $u->accountTypeLabel() }}</span>
            </div>
            <a href="{{ $accountUrl }}"><i class="fas {{ $accountIcon }}"></i> {{ $accountLabel }}</a>
            <a href="{{ route('account.edit') }}"><i class="fas fa-user-pen"></i> Your account</a>
            <hr>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="danger"><i class="fas fa-right-from-bracket"></i> Sign out</button>
            </form>
          </div>
        </div>
      @else
        <a href="{{ route('login') }}" wire:navigate class="signin desk">Sign in</a>
      @endauth

      <button class="burger" id="burger" aria-label="Menu" aria-expanded="false" aria-controls="mmenu"><i class="fas fa-bars"></i></button>
    </div>
  </div>
</header>

<div class="mmenu" id="mmenu">
  @foreach($nav as $item)
    @if(empty($item['children']))
      <a href="{{ $item['url'] }}" wire:navigate class="{{ $isOn($item) ? 'on' : '' }}">{{ $item['label'] }}</a>
    @else
      {{-- <details> gives an accessible, keyboard-operable disclosure with no
           script at all, and keeps the section open on the page it belongs to. --}}
      <details class="mm-group" @if($isOn($item)) open @endif>
        <summary>{{ $item['label'] }} <i class="fas fa-chevron-down chev" aria-hidden="true"></i></summary>
        <div class="mm-sub">
          @foreach($item['children'] as $child)
            <a href="{{ $child['url'] }}" wire:navigate class="{{ $isOn($child) ? 'on' : '' }}">
              <span class="mi"><i class="fas {{ $child['icon'] }}" aria-hidden="true"></i></span>
              {{ $child['label'] }}
            </a>
          @endforeach
        </div>
      </details>
    @endif
  @endforeach

  <div class="mm-actions">
    {{-- Same order as the desktop header: the actions, then the account. --}}
    <a href="{{ route('start-a-project') }}" wire:navigate class="btn ghost"><i class="fas fa-handshake"></i> Hire Muhindo</a>
    <a href="{{ route('courses.index') }}" wire:navigate class="btn gold"><i class="fas fa-graduation-cap"></i> Start Learning</a>

    @auth
      <a href="{{ $accountUrl }}" class="btn ghost"><i class="fas {{ $accountIcon }}"></i> {{ $accountLabel }}</a>
      <a href="{{ route('account.edit') }}" wire:navigate class="btn ghost"><i class="fas fa-user-pen"></i> Your account</a>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn ghost" style="width:100%;justify-content:center;">Sign out</button>
      </form>
    @else
      <a href="{{ route('login') }}" wire:navigate class="btn ghost">Sign in</a>
    @endauth
  </div>
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
        <p class="foot-h">Site</p>
        <a href="{{ route('courses.index') }}" wire:navigate>e&#8209;Learning</a>
        <a href="{{ route('start-a-project') }}" wire:navigate>Start a project</a>
        <a href="{{ route('portfolio.work') }}" wire:navigate>Work</a>
        <a href="{{ route('portfolio.about') }}" wire:navigate>About</a>
        <a href="{{ route('portfolio.skills') }}" wire:navigate>Skills</a>
        <a href="{{ route('contact') }}" wire:navigate>Contact</a>
      </div>
      <div>
        <p class="foot-h">More</p>
        <a href="{{ route('portfolio.services') }}" wire:navigate>Services</a>
        <a href="{{ route('portfolio.experience') }}" wire:navigate>Experience</a>
        <a href="{{ route('portfolio.education') }}" wire:navigate>Education</a>
        <a href="{{ route('portfolio.research') }}" wire:navigate>Research</a>
        <a href="{{ route('portfolio.products') }}" wire:navigate>Products</a>
      </div>
      <div>
        <p class="foot-h">Legal</p>
        <a href="{{ route('privacy') }}" wire:navigate>Privacy</a>
        <a href="{{ route('terms') }}" wire:navigate>Terms</a>
        @auth
          <a href="{{ $accountUrl }}">{{ $accountLabel }}</a>
        @else
          <a href="{{ route('login') }}" wire:navigate>Sign in</a>
        @endauth
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
  /* CSS already opens the mega panel on hover and on focus-within, which
     covers mouse, keyboard and no-JS. Script adds only what CSS cannot say:
     Escape closes, and aria-expanded tells the truth about the panel's state
     for anyone listening rather than looking. */
  function initMegaMenus(){
    document.querySelectorAll('.nav-item.has-menu').forEach(function(item){
      if (item.dataset.wired) return;
      item.dataset.wired = '1';
      var trigger = item.querySelector('.nav-link');
      var sync = function(open){ if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false'); };

      item.addEventListener('mouseenter', function(){ sync(true); });
      item.addEventListener('mouseleave', function(){ sync(false); });
      item.addEventListener('focusin',   function(){ sync(true); });
      item.addEventListener('focusout',  function(){
        // focusout fires before focus lands; check on the next tick.
        setTimeout(function(){ if (!item.contains(document.activeElement)) sync(false); }, 0);
      });
      item.addEventListener('keydown', function(e){
        if (e.key !== 'Escape') return;
        sync(false);
        if (trigger) trigger.blur();
      });
    });
  }
  initMegaMenus();

  document.addEventListener('livewire:navigated', function(){
    initBurgerMenu();
    initMegaMenus();
    var m=document.getElementById('mmenu'), b=document.getElementById('burger');
    if(m && m.classList.contains('open')){ m.classList.remove('open'); document.body.style.overflow=''; if(b){ b.setAttribute('aria-expanded','false'); b.querySelector('i').className='fas fa-bars'; } }
  });

  /* ── Motion ────────────────────────────────────────────────────────────
     Sections rise into place as they're reached, and the hero's numbers
     count up once.

     The hiding is applied by JS (the .js class), never by the stylesheet, so
     the content is visible to anyone whose script fails, whose observer is
     unsupported, or who asked their system for reduced motion. There is no
     state in which this can leave the page blank. */
  (function(){
    var still = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (still.matches || !('IntersectionObserver' in window)) return;

    document.documentElement.classList.add('js');

    var seen = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (!e.isIntersecting) return;
        e.target.classList.add('in');
        seen.unobserve(e.target);                 // rise once, not on every pass
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    /* Counts up to whatever the number already says, so the markup stays the
       single source of truth.

       These numbers are someone's credentials, so the animation is built to be
       incapable of leaving a wrong one on screen. requestAnimationFrame stops
       being delivered in a background tab, in low-power mode, and under some
       automation — and a count-up that stalls mid-flight leaves "9+ years"
       reading "1+ years" permanently. So the true text is restored by a timer
       that does not depend on frames arriving, and again if the tab is hidden
       mid-count. Worst case the number simply appears without counting. */
    function countUp(el){
      // The real value is copied out of the node before anything writes to it,
      // and every restore reads from there. Reading it back off the element
      // would be reading whatever frame the animation happens to be on — which
      // is how a second run once captured "1+" as the true value of "9+" and
      // left it there.
      if (el.dataset.trueValue === undefined) el.dataset.trueValue = el.textContent.trim();
      if (el.dataset.counted) return;             // never animate the same node twice
      el.dataset.counted = '1';

      var text = el.dataset.trueValue;
      var target = parseFloat(text.replace(/[^0-9.]/g, ''));
      if (!isFinite(target) || target === 0) return;

      var suffix = text.replace(/[0-9.,]/g, '');
      var ms = 900, start = null, done = false;

      function settle(){
        if (done) return;
        done = true;
        el.textContent = el.dataset.trueValue;    // the exact original, always
        document.removeEventListener('visibilitychange', onHide);
      }
      function onHide(){ if (document.hidden) settle(); }

      document.addEventListener('visibilitychange', onHide);
      setTimeout(settle, ms + 120);               // independent of rAF delivery

      requestAnimationFrame(function frame(now){
        if (done) return;
        if (start === null) start = now;
        var p = Math.min(1, (now - start) / ms);
        el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString() + suffix;
        if (p < 1) requestAnimationFrame(frame);
        else settle();
      });
    }

    /* Anything still hidden becomes visible, unconditionally. The reveal is
       decoration; the content is the point. Printing, find-in-page and
       deep-link anchors all reach content the observer hasn't got to yet, and
       any failure inside wire() would otherwise leave a page of invisible
       text. */
    function revealAll(){
      document.documentElement.classList.remove('js');
      document.querySelectorAll('[data-rise]').forEach(function(el){ el.classList.add('in'); });
    }
    window.addEventListener('beforeprint', revealAll);

    function wire(){
      document.querySelectorAll('[data-rise]').forEach(function(el, i){
        // Stagger within a group, capped so a long list never crawls.
        if (!el.style.getPropertyValue('--d')) {
          el.style.setProperty('--d', Math.min(i, 6) * 60 + 'ms');
        }
        seen.observe(el);
      });

      /* Anything already on screen is revealed straight away rather than left
         to the observer. The observer's negative bottom margin creates a dead
         band at the foot of the viewport, and on a screen tall enough to show
         the whole page there is no scroll to move anything out of it — so that
         content would stay invisible for good. */
      requestAnimationFrame(function () {
        document.querySelectorAll('[data-rise]:not(.in)').forEach(function (el) {
          if (el.getBoundingClientRect().top < window.innerHeight) el.classList.add('in');
        });
      });

      var stats = document.querySelector('[data-count]');
      if (stats) {
        var once = new IntersectionObserver(function(entries){
          entries.forEach(function(e){
            if (!e.isIntersecting) return;
            e.target.querySelectorAll('.v').forEach(countUp);
            once.disconnect();
          });
        }, { threshold: 0.4 });
        once.observe(stats);
      }
    }

    function safeWire(){
      /* Arriving on a deep link jumps straight past everything above the
         target, and those sections never come back into view for the observer
         to notice — so the visitor lands on a page of invisible text. Anyone
         who followed a link to a specific section gets the whole page revealed
         at once instead. */
      if (location.hash && document.getElementById(location.hash.slice(1))) {
        revealAll();

        return;
      }

      try { wire(); } catch (e) { revealAll(); }   // never trade content for an effect
    }

    safeWire();
    document.addEventListener('livewire:navigated', safeWire);
  })();
</script>
@stack('scripts')
@livewireScripts
</body>
</html>
