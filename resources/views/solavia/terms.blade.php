@extends('layouts.marketing')
@section('title', 'Terms of Service | SOLAVIA GROUP LIMITED')
@section('desc', 'The terms you agree to when you buy or use any product operated by SOLAVIA GROUP LIMITED: accounts, payment in UGX, digital delivery, acceptable use, intellectual property and governing law.')
@section('og_image', asset('images/og-solavia.png'))

@include('solavia.partials.styles')

@section('content')
<div class="sv">
  <section class="sv-hero">
    <div class="wrap">
      <h1>Terms of Service</h1>
      <p class="lede">{{ config('company.name') }} &middot; Reg. No. {{ config('company.registration_number') }}</p>
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap page sv-legal" style="max-width:820px;margin:0;">
      <div class="updated">Effective 12 September 2026</div>

      <h2>1. Who we are</h2>
      <p>{{ config('company.name') }} is a company registered in Uganda with the {{ config('company.registrar') }} under number {{ config('company.registration_number') }}, incorporated on {{ config('company.incorporated_on') }}, with its registered office at {{ config('company.address.street') }}, {{ config('company.address.trading_centre') }}, {{ config('company.address.division') }}, {{ config('company.address.locality') }}, {{ config('company.address.region') }}, {{ config('company.address.country') }}. In these terms, "we" and "us" mean that company.</p>

      <h2>2. What these terms cover</h2>
      <p>These terms apply to this website and to every product we operate, including our online courses and source code, School Dynamics, our hospital and livestock systems, and our consumer apps. Our <a class="link" href="{{ route('solavia.products') }}" wire:navigate>products page</a> lists them. Using any of them means you accept these terms.</p>
      <p>An individual product may add its own terms where it needs them. Where a product's own terms conflict with these, the product's terms apply to that product only.</p>

      <h2>3. Your account</h2>
      <ul>
        <li>You are responsible for keeping your login details secure, and for what is done with your account.</li>
        <li>Give accurate details. We may need to reach you about a payment or an order.</li>
        <li>Do not access another person's account or data.</li>
        <li>One account is for one person. Course access and subscriptions are not transferable.</li>
      </ul>

      <h2>4. Payment and pricing</h2>
      <p>Prices are shown in Ugandan Shillings (UGX) and include any tax we are required to charge. The price you see before you confirm is the price you pay.</p>
      <p>Payment is taken by licensed payment providers using MTN Mobile Money, Airtel Money, Visa or Mastercard. We never see or store your full card number. Payments are processed for {{ config('company.name') }} and will appear on your statement as such.</p>
      <p>Subscriptions renew for the period you chose until you cancel. You can cancel at any time and keep access to the end of the period you have paid for.</p>

      <h2>5. Delivery</h2>
      <p>Everything we sell is digital and is delivered automatically once payment is confirmed, usually within seconds. We ship no physical goods. If access has not reached you within 24 hours, that is a fault on our side and our <a class="link" href="{{ route('solavia.refund-policy') }}" wire:navigate>refund policy</a> says what happens next.</p>

      <h2>6. Refunds</h2>
      <p>Refunds and cancellations are governed by our <a class="link" href="{{ route('solavia.refund-policy') }}" wire:navigate>Refund and Cancellation Policy</a>, which sets out exactly what is refunded and how to ask.</p>

      <h2>7. Acceptable use</h2>
      <ul>
        <li>Do not resell, redistribute or publish our courses, videos or source code without written permission.</li>
        <li>Do not share your account so that others can use what you paid for.</li>
        <li>Do not scrape, overload, probe or disrupt our services.</li>
        <li>Do not upload anything unlawful, or anything you do not have the right to upload.</li>
      </ul>
      <p>We may suspend an account that breaks these rules. Where we can, we will tell you why first.</p>

      <h2>8. Intellectual property</h2>
      <p>All content and software we provide belongs to {{ config('company.name') }} or to its licensors. Buying a course, a subscription or a source-code package gives you a licence to use it, not ownership of it.</p>
      <p>Source-code packages may be used in your own projects, including commercial ones. They may not be resold or republished as a product of their own, whether changed or not.</p>
      <p>Some video content is licensed to us by its rights holders. Those rights stay with them.</p>

      <h2>9. Availability</h2>
      <p>We work to keep our services available, but we do not promise they will never be interrupted. We may change or withdraw a product. Where a withdrawal affects something you have paid for, the refund policy applies.</p>

      <h2>10. Limitation of liability</h2>
      <p>We are liable for losses we cause by failing to provide what you paid for. We are not liable for indirect or consequential loss, for loss of profit or data, or for anything outside our reasonable control. Where liability cannot be excluded by law, it is not excluded here.</p>
      <p>Our total liability for any claim is limited to the amount you paid us for the product the claim concerns in the twelve months before the claim.</p>

      <h2>11. Governing law</h2>
      <p>These terms are governed by the laws of the Republic of Uganda, and the courts of Uganda have jurisdiction over any dispute.</p>

      <h2>12. Changes</h2>
      <p>We may update these terms. The effective date above changes when we do. Continuing to use our products after that date means you accept the update.</p>

      <h2>13. Contact</h2>
      <p>
        {{ config('company.name') }}, {{ config('company.address.street') }}, {{ config('company.address.locality') }}, {{ config('company.address.region') }}, {{ config('company.address.country') }}.
        Email <a class="link" href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>.
        Phone <a class="link" href="tel:{{ config('company.phone_e164') }}">{{ config('company.phone') }}</a>.
      </p>
    </div>
  </section>
</div>
@endsection
