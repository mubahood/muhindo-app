<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Sign in') | Muhindo Mubaraka</title>
  <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon.png') }}">
  <meta name="theme-color" content="#ffffff">
  <link rel="stylesheet" href="{{ asset('vendor/fonts/inter/inter.css') }}">
  <link rel="stylesheet" href="{{ asset('vendor/fa/css/all.min.css') }}" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="{{ asset('vendor/fa/css/all.min.css') }}"></noscript>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{
      --bg:#f7f6f2; --surface:#fff; --surface-2:#f0eee7; --line:#e7e3d8; --line-2:#d8d2c0;
      --tx:#141a26; --tx2:#5b6270; --tx3:#706f5c; --pri:#0b1f3a; --pri-d:#060f1f; --pri-soft:#eef1f6;
      --ok:#0f6b30; --ok-soft:#e6f4ea; --bad:#b91c1c; --bad-soft:#fbe9e9;
      --gold:#b8933f; --gold-d:#7d6228; --gold-soft:#f7f0df;
      --font:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    }
    body{font-family:var(--font);font-size:13px;font-weight:400;color:var(--tx);
      background:var(--bg);position:relative;overflow-x:hidden;
      -webkit-font-smoothing:antialiased;letter-spacing:.002em;min-height:100vh;
      display:flex;align-items:center;justify-content:center;padding:24px;}
    /* Positive z-index on purpose: a negative one would paint above body's own
       background but below nothing else, and the card would sit under it. */
    body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
      background:
        radial-gradient(circle at 50% 0%,rgba(184,147,63,.10) 0%,transparent 46%),
        radial-gradient(circle at 12% 92%,rgba(11,31,58,.07) 0%,transparent 44%),
        linear-gradient(rgba(11,31,58,.032) 1px,transparent 1px) 0 0/34px 34px,
        linear-gradient(90deg,rgba(11,31,58,.032) 1px,transparent 1px) 0 0/34px 34px;
      -webkit-mask-image:radial-gradient(ellipse 90% 70% at 50% 38%,#000 30%,transparent 78%);
              mask-image:radial-gradient(ellipse 90% 70% at 50% 38%,#000 30%,transparent 78%);}
    a{color:inherit;text-decoration:none;}
    .ctx-sub{display:block;font-size:11.5px;font-weight:450;color:var(--tx3);margin-top:2px;line-height:1.45;}
    .a-course-ctx{display:flex;align-items:center;gap:12px;background:var(--pri-soft);border:1px solid var(--line);
      padding:12px 14px;margin-bottom:20px;}
    .a-course-ctx .thumb{width:44px;height:44px;flex-shrink:0;background:var(--pri);color:#b8933f;
      display:flex;align-items:center;justify-content:center;font-size:16px;}
    .a-course-ctx .thumb img{width:100%;height:100%;object-fit:cover;}
    .a-course-ctx p{font-size:12.5px;color:var(--tx2);line-height:1.45;}
    .a-course-ctx p b{color:var(--tx);}
    .auth-card{width:100%;max-width:392px;position:relative;z-index:1;}
    .auth-card.wide{max-width:600px;}
    .a-brand{display:flex;align-items:center;gap:10px;justify-content:center;margin-bottom:22px;}
    .a-brand .mk{width:32px;height:32px;object-fit:contain;display:block;}
    .a-brand b{font-weight:500;font-size:15px;}
    .card{background:var(--surface);border:1px solid var(--line);
      box-shadow:0 1px 0 var(--line),0 14px 36px -22px rgba(17,28,46,.3);padding:32px 30px;}
    .auth-card.wide .card{padding:38px 44px;}
    .a-row2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    @media(max-width:560px){.auth-card.wide .card{padding:28px 22px;} .a-row2{grid-template-columns:1fr;}}
    .af-eyebrow{font-size:11px;font-weight:500;letter-spacing:.16em;text-transform:uppercase;color:var(--pri);}
    .af-title{font-size:24px;font-weight:200;letter-spacing:-.02em;margin:6px 0 4px;}
    .af-sub{font-size:13px;font-weight:300;color:var(--tx2);margin-bottom:24px;}
    .a-alert{display:flex;align-items:center;gap:9px;font-size:13px;padding:11px 13px;margin-bottom:16px;}
    .a-alert.ok{background:var(--ok-soft);color:var(--ok);}
    .a-alert.err{background:var(--bad-soft);color:var(--bad);}
    .a-field{margin-bottom:16px;}
    .a-label{display:flex;justify-content:space-between;align-items:center;font-size:11px;font-weight:500;
      letter-spacing:.04em;text-transform:uppercase;color:var(--tx3);margin-bottom:7px;}
    .a-label a{color:var(--pri);font-weight:500;font-size:11px;text-transform:none;letter-spacing:0;}
    .a-inwrap{position:relative;display:flex;align-items:center;}
    .a-inwrap > i{position:absolute;left:13px;color:var(--tx3);font-size:13px;pointer-events:none;}
    .a-input{width:100%;font-family:var(--font);font-size:14px;font-weight:400;color:var(--tx);background:var(--surface);
      border:1px solid var(--line-2);padding:12px 13px 12px 38px;transition:border-color .15s,box-shadow .15s;}
    .a-input:focus{outline:none;border-color:var(--pri);box-shadow:0 0 0 3px var(--pri-soft);}
    .a-input::placeholder{color:var(--tx3);font-weight:300;}
    .a-input.bad{border-color:var(--bad);}
    .a-input.bad:focus{border-color:var(--bad);box-shadow:0 0 0 3px var(--bad-soft);}
    .a-eye{position:absolute;right:6px;width:34px;height:34px;border:none;background:none;color:var(--tx3);cursor:pointer;}
    .a-check{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:300;color:var(--tx2);margin:4px 0 18px;cursor:pointer;}
    .a-btn{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;
      background:var(--pri);color:#fff;border:1px solid var(--pri);font-family:var(--font);font-weight:500;
      font-size:13px;letter-spacing:.03em;padding:13px;cursor:pointer;transition:background .15s;}
    .a-btn:hover{background:var(--pri-d);border-color:var(--pri-d);}
    /* Gold is the primary action everywhere else on the site; a navy submit
       here made sign-in look like a secondary step in its own flow. */
    .a-btn.gold{background:var(--gold);border-color:var(--gold);color:#231a05;font-weight:600;}
    .a-btn.gold:hover{background:var(--gold-d);border-color:var(--gold-d);color:#fff;}
    .a-alt{text-align:center;font-size:12px;font-weight:300;color:var(--tx3);margin-top:18px;}
    /* a{color:inherit} left these the same grey as the sentence around them,
       so the only way to find the link was to hover and hope. */
    .a-alt a{color:var(--pri);font-weight:600;text-underline-offset:3px;}
    .a-alt a:hover{text-decoration:underline;}
    .a-back{display:inline-flex;align-items:center;gap:6px;color:var(--tx2);font-weight:400;font-size:12px;}
    .a-back:hover{color:var(--pri);}
    .a-secure{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;
      margin-top:20px;font-size:11px;font-weight:300;color:var(--tx3);}
    .a-secure .dot{opacity:.5;}
    /* Two genuinely different next steps, not two afterthought text links. */
    .a-sep{display:flex;align-items:center;gap:10px;margin:22px 0 12px;color:var(--tx3);
      font-size:10.5px;font-weight:500;letter-spacing:.14em;text-transform:uppercase;}
    .a-sep::before,.a-sep::after{content:'';flex:1;height:1px;background:var(--line);}
    .a-ways{display:grid;gap:8px;}
    .a-way{display:flex;align-items:center;gap:11px;padding:11px 13px;border:1px solid var(--line-2);
      background:var(--surface);transition:border-color .15s,background .15s,transform .15s;}
    .a-way:hover{border-color:var(--pri);background:var(--pri-soft);transform:translateX(2px);}
    .a-way > i{width:16px;text-align:center;color:var(--tx3);font-size:13px;flex-shrink:0;}
    .a-way:hover > i{color:var(--pri);}
    .a-way span{display:flex;flex-direction:column;gap:2px;font-size:11px;font-weight:400;
      color:var(--tx3);line-height:1.4;}
    .a-way span b{font-size:12.5px;font-weight:600;color:var(--tx);}
  </style>
  @stack('styles')
</head>
<body>
  <div class="auth-card {{ trim($__env->yieldContent('card_width', '')) }}">
    <div class="a-brand"><span style="width:32px;height:32px;background:var(--pri);color:#b8933f;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;">MM</span> <b>Muhindo Mubaraka</b></div>
    <div class="card">
      @yield('form')
    </div>
  </div>

  <script>
    document.querySelectorAll('.a-eye').forEach(function(btn){
      btn.addEventListener('click', function(){
        var el = document.getElementById(btn.getAttribute('data-eye'));
        if(!el) return;
        var show = el.type === 'password';
        el.type = show ? 'text' : 'password';
        btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
      });
    });
  </script>
  @stack('scripts')
</body>
</html>
