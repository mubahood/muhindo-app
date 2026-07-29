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
  .acct-type.on{border-color:var(--pri);background:var(--pri-soft);box-shadow:inset 0 0 0 1px var(--pri);}
  .acct-type.on .t i{color:var(--pri);}
  @media(max-width:560px){.acct-types{grid-template-columns:1fr;}}
</style>
@endpush

@section('form')
<div class="af-eyebrow">Create an account</div>
<h2 class="af-title">Join Muhindo Mubaraka</h2>
<p class="af-sub">One account for learning and for building — pick what you're here for.</p>

@include('auth.partials.course-context')

@if($errors->any())
<div class="a-alert err"><i class="fas fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
@endif

<form method="POST" action="{{ route('register') }}">
  @csrf
  @if($intendedCourse)
    <input type="hidden" name="intended_course" value="{{ $intendedCourse->slug }}">
    @if(request('coupon_code'))<input type="hidden" name="coupon_code" value="{{ request('coupon_code') }}">@endif
  @endif
  @php $selected = old('account_type', $defaultAccountType); @endphp
  <div class="a-field" x-data="{ type: @js($selected) }">
    <label class="a-label">I'm here to</label>
    <div class="acct-types">
      @foreach([
        ['v' => 'student', 'i' => 'fa-graduation-cap', 't' => 'Learn', 'd' => 'Take courses, track progress and earn certificates.'],
        ['v' => 'client',  'i' => 'fa-diagram-project', 't' => 'Hire me', 'd' => 'Request a project and follow it in your client portal.'],
        ['v' => 'both',    'i' => 'fa-layer-group', 't' => 'Both', 'd' => 'Learn and hire from the same account — switch any time.'],
      ] as $opt)
        <label class="acct-type" :class="{ on: type === @js($opt['v']) }">
          <input type="radio" name="account_type" value="{{ $opt['v'] }}" x-model="type" required>
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

  <button type="submit" class="a-btn"><i class="fas fa-user-plus"></i> Create account</button>
</form>

<div class="a-alt">Already have an account? <a href="{{ route('login', $intendedCourse ? array_filter(['intended_course' => $intendedCourse->slug, 'coupon_code' => request('coupon_code')]) : []) }}">Sign in</a></div>
<div class="a-alt">Just want to describe a project first? <a href="{{ route('start-a-project') }}">Start a project →</a></div>
<div style="text-align:center;"><a href="{{ route('home') }}" class="a-back"><i class="fas fa-arrow-left"></i> Back to home</a></div>
@endsection
