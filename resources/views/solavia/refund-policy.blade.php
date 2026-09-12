@extends('layouts.marketing')
@section('title', 'Refund Policy | SOLAVIA GROUP LIMITED')
@section('desc', 'How SOLAVIA GROUP LIMITED handles refunds and cancellations for courses, digital products, video subscriptions and school software licences. What we refund, how to ask, and how long it takes.')

@include('solavia.partials.styles')

@section('content')
<div class="sv">
  <section class="sv-hero">
    <div class="wrap">
      <h1>Refund and Cancellation Policy</h1>
      <p class="lede">{{ config('company.name') }} &middot; Reg. No. {{ config('company.registration_number') }}</p>
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap page sv-legal" style="max-width:820px;margin:0;">
      {{-- A fixed date, not date('F Y'). A policy whose "last updated" line
           moves by itself every month, while the document does not change, is
           telling a customer and a payment provider something untrue. --}}
      <div class="updated">Effective 8 September 2026</div>

      <p>This policy covers every product and service sold by {{ config('company.name') }} through <a class="link" href="{{ url('/') }}">https://muhindomubaraka.com</a>, our mobile applications and our school management system.</p>

      <h2>1. What we sell</h2>
      <p>All our products are digital: online courses, e-books, templates, software tools, video subscriptions and school software licences. Delivery is instant. Access starts the moment payment is confirmed. Nothing is shipped physically.</p>

      <h2>2. When we refund</h2>
      <p><strong>Full refund</strong> if you paid but did not receive access within 24 hours, if you were charged twice for the same order, if the product is materially different from its description, or if money was deducted but no order was created.</p>
      <p><strong>No refund</strong> for change of mind after the product was accessed or downloaded, or for the unused part of a subscription period that has already started (access continues to the end of the period).</p>

      <h2>3. How to request a refund</h2>
      <p>Email <a class="link" href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a> or call <a class="link" href="tel:{{ config('company.phone_e164') }}">{{ config('company.phone') }}</a> within 7 days of payment with the payment reference and the phone number or email used on the order. We reply within 2 business days. Approved refunds go back to the original payment method within 7 business days.</p>

      <h2>4. Cancellation</h2>
      <p>Subscriptions can be cancelled at any time from account settings in the app or website, or by contacting us. Cancellation stops future renewals and access continues until the end of the period already paid for. School software licences may be cancelled by written notice of 30 days before the next billing term.</p>

      <h2>5. Disputes</h2>
      <p>Please contact us before raising a dispute with your bank or payment provider. We keep payment, delivery and access logs for every order and provide them to the payment provider whenever a dispute is raised.</p>

      <h2>6. Contact</h2>
      <p>
        {{ config('company.name') }}, {{ config('company.address.street') }}, {{ config('company.address.locality') }}, {{ config('company.address.region') }}, {{ config('company.address.country') }}.
        Email <a class="link" href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>.
        Phone <a class="link" href="tel:{{ config('company.phone_e164') }}">{{ config('company.phone') }}</a>.
      </p>

      <div class="sv-policies" style="margin-top:34px;">
        <a class="sv-policy" href="{{ route('solavia.terms') }}" wire:navigate><b>Terms of Service</b><span>What you agree to when you buy.</span></a>
        <a class="sv-policy" href="{{ route('solavia.privacy') }}" wire:navigate><b>Privacy Policy</b><span>What we collect and why.</span></a>
        <a class="sv-policy" href="{{ route('solavia.contact') }}" wire:navigate><b>Contact us</b><span>Ask a question about an order.</span></a>
      </div>
    </div>
  </section>
</div>
@endsection
