<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment {{ $ok ? 'received' : 'not completed' }} | Muhindo Mubaraka</title>
<link rel="stylesheet" href="{{ asset('vendor/fonts/inter/inter.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fa/css/all.min.css') }}">
<style>
  *{box-sizing:border-box;}
  body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    background:#f7f6f2;color:#141a26;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:20px;}
  .card{background:#fff;border:1px solid #e7e3d8;padding:40px 34px;max-width:420px;width:100%;text-align:center;
    box-shadow:0 14px 36px -22px rgba(17,28,46,.3);}
  .icon{width:52px;height:52px;margin:0 auto 16px;background:{{ $ok ? '#e6f4ea' : '#f7f0df' }};color:{{ $ok ? '#0f6b30' : '#7d6228' }};
    display:flex;align-items:center;justify-content:center;font-size:22px;border-radius:50%;}
  h1{font-size:19px;font-weight:600;margin:0 0 8px;}
  p{color:#5b6270;margin:0 0 24px;font-size:13.5px;line-height:1.6;}
  a.btn{display:inline-block;background:#0b1f3a;color:#fff;text-decoration:none;padding:11px 22px;font-size:13.5px;font-weight:500;}
  a.btn:hover{background:#060f1f;}
  a.link{display:block;margin-top:14px;color:#5b6270;font-size:12.5px;text-decoration:underline;}
</style></head>
<body><div class="card">
  <div class="icon"><i class="fas {{ $ok ? 'fa-check' : 'fa-triangle-exclamation' }}"></i></div>
  <h1>{{ $ok ? 'Payment received' : 'Payment not completed' }}</h1>
  <p>
    @if($ok)
      Thank you. Your payment has been recorded and your enrollment is now active.
    @else
      The payment wasn't completed, so nothing was charged. If money did leave your
      account, it will reconcile automatically. Nothing else to do. Your course is
      still reserved; you can pick up right where you left off.
    @endif
  </p>
  @if($ok)
    <a href="{{ route('dashboard') }}" class="btn">Go to my dashboard</a>
  @else
    <a href="{{ $retryUrl }}" class="btn">Try again</a>
    <a href="{{ route('dashboard') }}" class="link">Return to my dashboard</a>
  @endif
</div>
</body></html>
