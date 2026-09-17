@extends('layouts.marketing')
@section('title', 'Contact | SOLAVIA GROUP LIMITED')
@section('desc', 'Reach SOLAVIA GROUP LIMITED by phone, WhatsApp or email, or send a message from this page. Registered office in Nansana Municipality, Wakiso District, Uganda.')
@section('og_image', asset('images/og-solavia.png'))

@include('solavia.partials.styles')

@push('styles')
<style>
  .sv-form{border:1px solid var(--line);background:var(--surface);padding:24px;}
  .sv-form .row2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  .sv-form label{display:block;font-size:12.5px;font-weight:600;color:var(--tx2);margin-bottom:5px;}
  .sv-form input,.sv-form textarea{width:100%;padding:10px 12px;border:1px solid var(--line-2);
    background:var(--bg);font:inherit;font-size:14px;color:var(--tx);}
  .sv-form input:focus,.sv-form textarea:focus{outline:none;border-color:var(--sv-green);}
  .sv-form textarea{min-height:132px;resize:vertical;}
  .sv-field{margin-bottom:14px;}
  .sv-err{color:#b3261e;font-size:12.5px;margin-top:4px;}
  .sv-ok{background:#e6f4ea;border:1px solid #b7dfc4;color:#0f6b30;padding:13px 16px;margin-bottom:18px;font-size:14px;}
  @media(max-width:760px){ .sv-form .row2{grid-template-columns:1fr;} }
</style>
@endpush

@section('content')
<div class="sv">
  <section class="sv-hero">
    <div class="wrap">
      <h1>Contact us</h1>
      <p class="lede">{{ config('company.name') }} &middot; Reg. No. {{ config('company.registration_number') }}. We reply within 2 business days.</p>
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap">
      @include('solavia.partials.contact-strip')
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap" style="max-width:720px;margin:0;">
      <div class="sv-eyebrow">Send a message</div>
      <h2 id="contact-form">Write to us</h2>
      <p>For an order or a payment, please include the payment reference and the phone number or email used on the order, so we can find it straight away.</p>

      @if(session('success'))
        <div class="sv-ok" role="status">{{ session('success') }}</div>
      @endif

      <form class="sv-form" method="POST" action="{{ route('solavia.contact.submit') }}">
        @csrf
        <x-form-shield id="solavia-contact" />

        <div class="row2">
          <div class="sv-field">
            <label for="sv-name">Your name</label>
            <input id="sv-name" type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
            @error('name')<div class="sv-err">{{ $message }}</div>@enderror
          </div>
          <div class="sv-field">
            <label for="sv-email">Email address</label>
            <input id="sv-email" type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email">
            @error('email')<div class="sv-err">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="sv-field">
          <label for="sv-phone">Phone number <span style="font-weight:400;color:var(--tx3);">(optional)</span></label>
          <input id="sv-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="32" autocomplete="tel">
          @error('phone')<div class="sv-err">{{ $message }}</div>@enderror
        </div>

        <div class="sv-field">
          <label for="sv-message">Message</label>
          <textarea id="sv-message" name="message" required minlength="10" maxlength="4000">{{ old('message') }}</textarea>
          @error('message')<div class="sv-err">{{ $message }}</div>@enderror
        </div>

        <x-captcha />

        <button type="submit" class="sv-btn sv-btn-solid" style="border:0;cursor:pointer;">
          <i class="fas fa-paper-plane" aria-hidden="true"></i> Send message
        </button>
      </form>
    </div>
  </section>
</div>
@endsection
