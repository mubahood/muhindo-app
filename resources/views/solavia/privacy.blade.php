@extends('layouts.marketing')
@section('title', 'Privacy Policy | SOLAVIA GROUP LIMITED')
@section('desc', 'What SOLAVIA GROUP LIMITED collects, why, who we share it with and how long we keep it. The data controller is SOLAVIA GROUP LIMITED, Reg. No. 80048169153974, Wakiso District, Uganda.')
@section('og_image', asset('images/og-solavia.png'))

@include('solavia.partials.styles')

@section('content')
<div class="sv">
  <section class="sv-hero">
    <div class="wrap">
      <h1>Privacy Policy</h1>
      <p class="lede">{{ config('company.name') }} &middot; Reg. No. {{ config('company.registration_number') }}</p>
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap page sv-legal" style="max-width:820px;margin:0;">
      <div class="updated">Effective 12 September 2026</div>

      <h2>1. Who controls your data</h2>
      <p>The data controller is {{ config('company.name') }}, {{ config('company.address.street') }}, {{ config('company.address.trading_centre') }}, {{ config('company.address.division') }}, {{ config('company.address.locality') }}, {{ config('company.address.region') }}, {{ config('company.address.country') }}. Reach us at <a class="link" href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a> or {{ config('company.phone') }}.</p>
      <p>This policy covers this website and every product listed on our <a class="link" href="{{ route('solavia.products') }}" wire:navigate>products page</a>.</p>

      <h2>2. What we collect</h2>
      <ul>
        <li>Your name, phone number and email address, when you create an account or contact us.</li>
        <li>A device identifier, for our mobile apps, so a subscription works on the device that bought it.</li>
        <li>A payment reference and the result of the payment, returned to us by the payment provider.</li>
        <li>What you used: courses opened, lessons completed, titles watched, pages visited.</li>
        <li>Technical data your browser or app sends: IP address, device type, approximate location from that IP.</li>
      </ul>
      <p><strong>We never collect or store card numbers, CVV codes or mobile money PINs.</strong> Those are entered with the payment provider and never reach our systems.</p>

      <h2>3. Why we use it</h2>
      <ul>
        <li>To deliver what you bought and keep your access working across devices.</li>
        <li>To take payment, and to match a payment to an order when something goes wrong.</li>
        <li>To answer support requests and process refunds.</li>
        <li>To detect and prevent fraud and account sharing.</li>
        <li>To understand which courses and titles are used, so we make more of what works.</li>
        <li>To meet our legal and tax obligations in Uganda.</li>
      </ul>

      <h2>4. Who we share it with</h2>
      <p>We do not sell your data. We share only what is needed, with:</p>
      <ul>
        <li><strong>Payment providers</strong>, who need your name, contact details and order reference to take payment and to settle a dispute.</li>
        <li><strong>Hosting and infrastructure providers</strong>, who store our data on our behalf under contract.</li>
        <li><strong>Schools</strong>, where you use School Dynamics as a parent, student or member of staff: the school is the controller of its own records.</li>
        <li><strong>Authorities</strong>, where the law requires it.</li>
      </ul>

      <h2>5. How long we keep it</h2>
      <ul>
        <li>Account and order records: for as long as you have an account, and for seven years after your last payment, which is what Ugandan tax and accounting rules require.</li>
        <li>Support messages: three years.</li>
        <li>Technical logs: twelve months.</li>
      </ul>
      <p>When a period ends, we delete the data or anonymise it so it can no longer identify you.</p>

      <h2>6. Your rights</h2>
      <ul>
        <li>Ask for a copy of the data we hold about you.</li>
        <li>Ask us to correct anything wrong.</li>
        <li>Ask us to delete your account and data, where we are not required to keep it.</li>
        <li>Object to a use of your data, or ask us to restrict it.</li>
        <li>Withdraw consent at any time, where we relied on consent.</li>
      </ul>
      <p>Write to <a class="link" href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a> and we will answer within 30 days.</p>

      <h2>7. Security</h2>
      <p>Data travels over encrypted connections and is stored on servers we control, with access limited to staff who need it. No system is perfect; if a breach ever affects you, we will tell you.</p>

      <h2>8. Children</h2>
      <p>Our consumer products are not aimed at children under 13. School Dynamics holds records about pupils, but only on behalf of the school, which is responsible for the consent behind them.</p>

      <h2>9. Changes</h2>
      <p>We may update this policy. The effective date above changes when we do.</p>

      <h2>10. Contact</h2>
      <p>
        {{ config('company.name') }}, {{ config('company.address.street') }}, {{ config('company.address.locality') }}, {{ config('company.address.region') }}, {{ config('company.address.country') }}.
        Email <a class="link" href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>.
        Phone <a class="link" href="tel:{{ config('company.phone_e164') }}">{{ config('company.phone') }}</a>.
      </p>
    </div>
  </section>
</div>
@endsection
