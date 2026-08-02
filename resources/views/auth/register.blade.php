@extends('layouts.auth')
@section('title', 'Create your account')
@section('card_width', 'wide')

@push('styles')
<style>
  .acct-types{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:6px;}
  .acct-type{position:relative;display:flex;flex-direction:column;gap:4px;padding:11px 12px;cursor:pointer;
    border:1px solid var(--line-2);background:var(--surface);transition:border-color .15s,background .15s;}
  .acct-type:hover{border-color:var(--pri);}
  .acct-type input{position:absolute;opacity:0;pointer-events:none;}
  .acct-type .t{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:var(--tx);}
  .acct-type .t i{color:var(--tx3);font-size:12px;}
  .acct-type .d{font-size:11px;line-height:1.4;color:var(--tx3);}
  .acct-type:has(input:checked){border-color:var(--pri);background:var(--pri-soft);box-shadow:inset 0 0 0 1px var(--pri);}
  .acct-type:has(input:checked) .t i{color:var(--pri);}
  /* The radio itself is visually hidden but still focusable, so the card has to
     carry its focus ring — without this the keyboard focus is invisible here. */
  .acct-type:has(input:focus-visible){outline:2px solid var(--pri);outline-offset:2px;}
  @media(max-width:560px){.acct-types{grid-template-columns:1fr;}}
</style>
@endpush

@section('form')
<div class="af-eyebrow">Create an account</div>
<h2 class="af-title">Join Muhindo Mubaraka</h2>
<p class="af-sub">One account for both sides. Pick what brings you here — you can add the other later.</p>

@include('auth.partials.course-context')

@if($errors->any())
<div class="a-alert err"><i class="fas fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
@endif

<form method="POST" action="{{ route('register') }}">
  @csrf
  <x-form-shield id="register" />
  @if($intendedCourse)
    <input type="hidden" name="intended_course" value="{{ $intendedCourse->slug }}">
    @if(request('coupon_code'))<input type="hidden" name="coupon_code" value="{{ request('coupon_code') }}">@endif
  @endif
  @php $selected = old('account_type', $defaultAccountType); @endphp
  {{-- No Alpine here on purpose. The auth layout ships no JavaScript framework,
       and a sign-up form should not be the reason to load one. The selection is
       rendered by the server and styled with :has(input:checked), so it is
       correct on first paint and keeps working with scripting off. --}}
  <div class="a-field">
    <label class="a-label">I'm here to</label>
    <div class="acct-types">
      @foreach([
        ['v' => 'student', 'i' => 'fa-graduation-cap', 't' => 'Learn from Muhindo',
         'd' => 'Take his courses, track your progress and earn a verifiable certificate.'],
        ['v' => 'client',  'i' => 'fa-handshake', 't' => 'Hire Muhindo for a project',
         'd' => 'Send him a brief, then follow the build in your own client portal.'],
        ['v' => 'both',    'i' => 'fa-layer-group', 't' => 'Both',
         'd' => 'One account for learning and for the work you commission. Switch any time.'],
      ] as $opt)
        <label class="acct-type">
          <input type="radio" name="account_type" value="{{ $opt['v'] }}"
                 @checked($selected === $opt['v']) required>
          <span class="t"><i class="fas {{ $opt['i'] }}"></i> {{ $opt['t'] }}</span>
          <span class="d">{{ $opt['d'] }}</span>
        </label>
      @endforeach
    </div>
    <p class="muted" style="font-size:11px;">You can change this later from your profile.</p>
    @error('account_type')<div class="a-alert err" style="margin-top:8px;">{{ $message }}</div>@enderror
  </div>

  <div class="a-row2">
    <div class="a-field">
      <label class="a-label" for="name">Full name</label>
      <div class="a-inwrap">
        <i class="fas fa-user"></i>
        <input class="a-input" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
      </div>
    </div>

    <div class="a-field">
      <label class="a-label" for="email">Email address</label>
      <div class="a-inwrap">
        <i class="fas fa-envelope"></i>
        <input class="a-input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
      </div>
    </div>
  </div>

  <div class="a-row2">
    <div class="a-field">
      <label class="a-label" for="password">Password</label>
      <div class="a-inwrap">
        <i class="fas fa-lock"></i>
        <input class="a-input" id="password" type="password" name="password" required autocomplete="new-password" style="padding-right:44px;">
        <button type="button" class="a-eye" data-eye="password"><i class="fas fa-eye"></i></button>
      </div>
    </div>

    <div class="a-field">
      <label class="a-label" for="password_confirmation">Confirm password</label>
      <div class="a-inwrap">
        <i class="fas fa-lock"></i>
        <input class="a-input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" style="padding-right:44px;">
        <button type="button" class="a-eye" data-eye="password_confirmation"><i class="fas fa-eye"></i></button>
      </div>
    </div>
  </div>

  <label class="a-check"><input type="checkbox" name="terms" value="1" required> I agree to the <a href="{{ route('terms') }}" target="_blank">Terms</a> and <a href="{{ route('privacy') }}" target="_blank">Privacy Policy</a></label>

  <x-captcha />
  <button type="submit" class="a-btn gold"><i class="fas fa-user-plus"></i> Create account</button>
</form>

<div class="a-alt">Already have an account? <a href="{{ route('login', $intendedCourse ? array_filter(['intended_course' => $intendedCourse->slug, 'coupon_code' => request('coupon_code')]) : []) }}">Sign in</a></div>
<div class="a-alt">Just want to describe a project first? <a href="{{ route('start-a-project') }}">Start a project →</a></div>
<div style="text-align:center;"><a href="{{ route('home') }}" class="a-back"><i class="fas fa-arrow-left"></i> Back to home</a></div>
@endsection
