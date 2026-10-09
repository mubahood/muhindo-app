@extends('layouts.marketing')
@section('title', 'Tell me what you want built | Muhindo Mubaraka')
@section('desc', 'Send a project enquiry in under a minute. No account needed. Say what you want built and I reply within one working day.')

@push('styles')
<style>
  .enq{max-width:720px;margin:0 auto;}
  .enq-head{text-align:center;margin-bottom:8px;}
  .enq-head h1{font-size:34px;font-weight:300;letter-spacing:-.02em;margin-bottom:10px;}
  .enq-head p{color:var(--tx2);font-size:15px;line-height:1.65;max-width:520px;margin:0 auto;}
  .enq-trust{display:flex;flex-wrap:wrap;justify-content:center;gap:8px 18px;margin:20px 0 30px;
    font-size:12.5px;color:var(--tx3);font-weight:500;}
  .enq-trust span{display:inline-flex;align-items:center;gap:6px;}
  .enq-trust i{color:var(--gold-d);font-size:11px;}
  .enq-card{background:var(--surface);border:1px solid var(--line);padding:28px;}
  .fld{margin-bottom:18px;}
  .fld label{display:block;font-size:13px;font-weight:600;margin-bottom:7px;}
  .fld .opt{font-weight:400;color:var(--tx3);}
  .inp{width:100%;padding:11px 13px;border:1px solid var(--line-2);background:var(--bg);
    font:inherit;font-size:14.5px;color:var(--tx);border-radius:3px;}
  .inp:focus{outline:2px solid var(--pri);outline-offset:1px;border-color:var(--pri);}
  .inp.is-bad{border-color:#b3261e;}
  textarea.inp{min-height:132px;resize:vertical;line-height:1.6;}
  .err{color:#b3261e;font-size:12.5px;margin-top:5px;}
  .two{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  .enq-send{width:100%;padding:14px;font-size:15px;font-weight:600;background:var(--pri);color:#fff;
    border:0;cursor:pointer;border-radius:3px;}
  .enq-send:hover{background:var(--pri-d);}
  .enq-alt{text-align:center;margin-top:22px;font-size:13.5px;color:var(--tx2);}
  .enq-alt a{color:var(--pri);font-weight:600;}
  .enq-done{background:var(--ok-soft);border:1px solid #cfe6d7;padding:30px;text-align:center;}
  .enq-done h2{font-size:22px;font-weight:500;margin-bottom:10px;color:var(--ok);}
  .enq-done p{color:var(--tx2);line-height:1.7;margin-bottom:8px;}
  @media(max-width:620px){ .two{grid-template-columns:1fr;} .enq-card{padding:20px;} .enq-head h1{font-size:27px;} }
</style>
@endpush

@section('content')
<section>
  <div class="wrap page enq" style="padding-block:46px;">

    @if(session('enquiry_sent'))
      {{-- The reply promise is the whole point of this screen. A thank you
           that does not say when somebody will hear back is just a full stop. --}}
      <div class="enq-done">
        <h2>Got it. It is in my inbox.</h2>
        <p>I read every enquiry myself and reply within one working day, usually sooner.</p>
        <p>If it is urgent, message me on WhatsApp and say you have just sent an enquiry.</p>
        <p style="margin-top:20px;">
          <a class="btn gold" target="_blank" rel="noopener"
             href="https://wa.me/{{ preg_replace('/\D+/', '', config('company.phone_e164')) }}?text={{ rawurlencode('Hello Muhindo, I have just sent a project enquiry through your site.') }}">
            <i class="fab fa-whatsapp"></i> Open WhatsApp
          </a>
        </p>
      </div>
    @else
      <div class="enq-head">
        <h1>Tell me what you want built</h1>
        <p>No account, no password, no forms to fill twice. Five questions, under a minute,
           and I reply within one working day.</p>
      </div>

      <div class="enq-trust">
        <span><i class="fas fa-check"></i> Nine years building systems people run on</span>
        <span><i class="fas fa-check"></i> I read every one myself</span>
        <span><i class="fas fa-check"></i> No obligation, no sales call</span>
      </div>

      <div class="enq-card">
        <form method="POST" action="{{ route('enquire.store') }}" novalidate>
          @csrf
          <x-form-shield id="public-enquiry" />

          <div class="two">
            <div class="fld">
              <label for="name">Your name</label>
              <input id="name" name="name" class="inp @error('name') is-bad @enderror"
                     value="{{ old('name') }}" required autocomplete="name">
              @error('name')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="fld">
              <label for="organisation">Organisation <span class="opt">(optional)</span></label>
              <input id="organisation" name="organisation" class="inp"
                     value="{{ old('organisation') }}" autocomplete="organization">
            </div>
          </div>

          <div class="two">
            <div class="fld">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" class="inp @error('email') is-bad @enderror"
                     value="{{ old('email') }}" required autocomplete="email">
              @error('email')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="fld">
              <label for="phone">WhatsApp number</label>
              <input id="phone" name="phone" type="tel" class="inp @error('phone') is-bad @enderror"
                     value="{{ old('phone') }}" required autocomplete="tel" placeholder="+256 ...">
              @error('phone')<p class="err">{{ $message }}</p>@enderror
            </div>
          </div>

          <div class="two">
            <div class="fld">
              <label for="category">What kind of work is it?</label>
              <select id="category" name="category" class="inp @error('category') is-bad @enderror" required>
                <option value="">Choose one</option>
                @foreach($categories as $value => $label)
                  <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                @endforeach
              </select>
              @error('category')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="fld">
              <label for="timeline">When would you want it?</label>
              <select id="timeline" name="timeline" class="inp @error('timeline') is-bad @enderror" required>
                <option value="">Choose one</option>
                @foreach($timelines as $value => $label)
                  <option value="{{ $value }}" @selected(old('timeline') === $value)>{{ $label }}</option>
                @endforeach
              </select>
              @error('timeline')<p class="err">{{ $message }}</p>@enderror
            </div>
          </div>

          <div class="fld">
            <label for="description">What do you want it to do?</label>
            <textarea id="description" name="description" class="inp @error('description') is-bad @enderror"
                      required placeholder="Who will use it, and what should it let them do? A few sentences is plenty.">{{ old('description') }}</textarea>
            @error('description')<p class="err">{{ $message }}</p>@enderror
          </div>

          <x-captcha-field />

          <button type="submit" class="enq-send">Send it</button>
        </form>
      </div>

      <p class="enq-alt">
        Would rather talk? <a href="https://wa.me/{{ preg_replace('/\D+/', '', config('company.phone_e164')) }}?text={{ rawurlencode('Hello Muhindo, I have a project I would like built.') }}" target="_blank" rel="noopener">Message me on WhatsApp</a>
        &middot; Already have an account? <a href="{{ route('propose') }}">Use the full proposal form</a>
      </p>
    @endif
  </div>
</section>
@endsection
