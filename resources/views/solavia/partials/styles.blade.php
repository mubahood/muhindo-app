{{--
  The company section's own styling, pushed once per page.

  Muvule Green and Equator Ember come from config so the palette and the logo
  beside it cannot drift apart. Everything else borrows the site's existing
  typography and rhythm on purpose: this has to read as part of the same site,
  not a microsite bolted on.
--}}
@push('styles')
<style>
  .sv{--sv-green:{{ config('company.brand.green') }};--sv-ember:{{ config('company.brand.ember') }};
      --sv-green-soft:#eaf2ef;}

  .sv-hero{background:var(--sv-green);color:#fff;padding:56px 0 52px;position:relative;overflow:hidden;}
  .sv-hero::after{content:'';position:absolute;right:-90px;bottom:-140px;width:340px;height:340px;
    border-radius:50%;background:var(--sv-ember);opacity:.13;pointer-events:none;}
  .sv-hero .wrap{position:relative;z-index:1;}
  .sv-hero img{height:54px;width:auto;margin-bottom:22px;display:block;}
  .sv-hero h1{font-size:32px;font-weight:600;letter-spacing:-.01em;margin:0 0 12px;color:#fff;line-height:1.2;}
  .sv-hero p.lede{font-size:16px;line-height:1.65;color:rgba(255,255,255,.88);max-width:620px;margin:0 0 26px;}
  .sv-hero .acts{display:flex;flex-wrap:wrap;gap:10px;}
  .sv-btn{display:inline-flex;align-items:center;gap:8px;padding:11px 20px;font-size:14px;font-weight:600;
    border:1px solid transparent;transition:.15s;}
  .sv-btn-solid{background:var(--sv-ember);color:#fff;}
  .sv-btn-solid:hover{filter:brightness(1.08);color:#fff;}
  .sv-btn-ghost{border-color:rgba(255,255,255,.45);color:#fff;}
  .sv-btn-ghost:hover{background:rgba(255,255,255,.12);color:#fff;}

  .sv-sec{padding:46px 0;}
  .sv-sec + .sv-sec{border-top:1px solid var(--line);}
  .sv-eyebrow{font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
    color:var(--sv-ember);margin-bottom:9px;}
  .sv-sec h2{font-size:23px;font-weight:600;margin:0 0 16px;letter-spacing:-.01em;}
  .sv-sec h3{font-size:16px;font-weight:600;margin:0 0 7px;}
  .sv-sec p{color:var(--tx2);line-height:1.75;margin:0 0 13px;max-width:70ch;}

  /* The facts table. Plain text throughout, never an image: a reviewer has to
     be able to select and copy the registration number. */
  .sv-facts{width:100%;border-collapse:collapse;font-size:14px;}
  .sv-facts th{text-align:left;font-weight:600;color:var(--tx3);width:34%;padding:11px 16px 11px 0;
    vertical-align:top;border-bottom:1px solid var(--line);font-size:12.5px;letter-spacing:.03em;
    text-transform:uppercase;}
  .sv-facts td{padding:11px 0;vertical-align:top;border-bottom:1px solid var(--line);color:var(--tx);line-height:1.6;}
  .sv-facts tr:last-child th,.sv-facts tr:last-child td{border-bottom:none;}
  .sv-copyable{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:14px;
    background:var(--sv-green-soft);padding:2px 7px;border-radius:3px;user-select:all;}

  .sv-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;}
  .sv-card{border:1px solid var(--line);background:var(--surface);padding:22px;}
  .sv-card .who{display:flex;align-items:center;gap:13px;}
  .sv-avatar{width:46px;height:46px;border-radius:50%;object-fit:cover;flex:none;}
  .sv-initials{width:46px;height:46px;border-radius:50%;flex:none;display:flex;align-items:center;
    justify-content:center;background:var(--sv-green);color:#fff;font-weight:600;font-size:15px;letter-spacing:.02em;}
  .sv-card .role{font-size:12.5px;color:var(--tx3);margin-top:2px;}

  .sv-pay{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:16px;}
  .sv-pay img{height:26px;width:auto;opacity:.9;}
  .sv-pay .card-word{font-size:13px;font-weight:600;color:var(--tx2);border:1px solid var(--line);
    padding:5px 11px;}

  .sv-policies{display:flex;flex-wrap:wrap;gap:10px;}
  .sv-policy{flex:1 1 210px;border:1px solid var(--line);padding:17px 19px;background:var(--surface);transition:.15s;}
  .sv-policy:hover{border-color:var(--sv-green);}
  .sv-policy b{display:block;font-size:14.5px;color:var(--tx);margin-bottom:3px;}
  .sv-policy span{font-size:12.5px;color:var(--tx3);line-height:1.5;}

  .sv-contact{display:grid;grid-template-columns:1fr 1fr;gap:26px;align-items:start;}
  .sv-map{width:100%;aspect-ratio:16/10;border:1px solid var(--line);display:block;}
  .sv-wa{display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;padding:10px 17px;
    font-size:14px;font-weight:600;margin-top:13px;}
  .sv-wa:hover{color:#fff;filter:brightness(1.05);}

  /* Legal pages: numbered sections, generous line height, nothing decorative. */
  .sv-legal h2{font-size:17px;margin:30px 0 9px;}
  .sv-legal h2:first-of-type{margin-top:0;}
  .sv-legal ul{margin:10px 0;padding-left:0;list-style:none;}
  .sv-legal li{position:relative;padding-left:20px;color:var(--tx2);margin:7px 0;line-height:1.7;}
  .sv-legal li::before{content:'';position:absolute;left:3px;top:10px;width:5px;height:5px;background:var(--sv-ember);}

  @media(max-width:760px){
    .sv-hero{padding:38px 0 36px;}
    .sv-hero h1{font-size:25px;}
    .sv-hero img{height:42px;}
    .sv-hero p.lede{font-size:15px;}
    .sv-sec{padding:34px 0;}
    .sv-sec h2{font-size:20px;}
    .sv-contact{grid-template-columns:1fr;gap:20px;}
    /* A two-column facts table of long values is unreadable on a phone, so
       each row becomes a labelled block. */
    .sv-facts,.sv-facts tbody,.sv-facts tr,.sv-facts th,.sv-facts td{display:block;width:100%;}
    .sv-facts th{border-bottom:none;padding:14px 0 2px;}
    .sv-facts td{padding:0 0 12px;border-bottom:1px solid var(--line);}
    .sv-btn{flex:1 1 100%;justify-content:center;}
  }
</style>
@endpush
