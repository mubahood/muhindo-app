@extends('layouts.marketing')
@section('title', 'SOLAVIA GROUP LIMITED | Company')
@section('desc', 'SOLAVIA GROUP LIMITED is a Ugandan software company, Reg. No. 80048169153974, building and operating digital products for learners, schools and consumers across East Africa.')

@include('solavia.partials.styles')

@push('jsonld')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => config('company.name'),
    'legalName' => config('company.name'),
    'identifier' => config('company.registration_number'),
    'foundingDate' => '2026-07-22',
    'url' => route('solavia.home'),
    'logo' => asset('images/solavia-logo.svg'),
    'email' => config('company.email'),
    'telephone' => config('company.phone_e164'),
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => config('company.address.street'),
        'addressLocality' => config('company.address.locality'),
        'addressRegion' => config('company.address.region'),
        'addressCountry' => 'UG',
    ],
    'sameAs' => array_column(config('company.social'), 'url'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="sv">

  <section class="sv-hero">
    <div class="wrap">
      <img src="{{ asset('images/solavia-logo-reversed.svg') }}" alt="SOLAVIA GROUP LIMITED">
      <h1>SOLAVIA GROUP LIMITED</h1>
      <p class="lede">A Ugandan software company building and operating digital products for learners, schools and consumers across East Africa.</p>
      <div class="acts">
        <a class="sv-btn sv-btn-solid" href="{{ route('solavia.products') }}" wire:navigate>Our products</a>
        <a class="sv-btn sv-btn-ghost" href="{{ route('solavia.contact') }}" wire:navigate>Contact us</a>
      </div>
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap">
      <div class="sv-eyebrow">About</div>
      <h2>Who we are</h2>
      <p>SOLAVIA GROUP LIMITED is a company registered in Uganda with the Uganda Registration Services Bureau, incorporated on {{ config('company.incorporated_on') }} under registration number {{ config('company.registration_number') }}. We are based in {{ config('company.address.trading_centre') }}, {{ config('company.address.locality') }}, {{ config('company.address.region') }}.</p>
      <p>We earn from three things. We sell online courses and digital products directly on this site. We license School Dynamics, our school management software, to schools as a subscription. And we run subscription video platforms for Ugandan audiences at home and in the diaspora.</p>
      <p>Across those platforms we have over {{ config('company.scale.registered_users') }} registered users and have completed more than {{ config('company.scale.completed_payments') }} customer payments.</p>
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap">
      <div class="sv-eyebrow">Registered details</div>
      <h2>Company details</h2>
      @include('solavia.partials.facts')
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap">
      <div class="sv-eyebrow">Leadership</div>
      <h2>Who runs the company</h2>
      <div class="sv-cards">
        @foreach(config('company.leadership') as $person)
          <div class="sv-card">
            <div class="who">
              @if($person['photo'])
                <img class="sv-avatar" src="{{ asset($person['photo']) }}" alt="{{ $person['name'] }}" loading="lazy" width="46" height="46">
              @else
                {{-- Initials rather than a stock face. An invented photograph
                     of a real director would be worse than none. --}}
                <span class="sv-initials" aria-hidden="true">{{ collect(explode(' ', $person['name']))->take(2)->map(fn($w) => mb_substr($w, 0, 1))->implode('') }}</span>
              @endif
              <span>
                <b>{{ $person['name'] }}</b>
                <div class="role">{{ $person['title'] }}</div>
              </span>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap">
      <div class="sv-eyebrow">Payments</div>
      <h2>How customers pay</h2>
      <p>Customers pay online by MTN Mobile Money, Airtel Money, Visa and Mastercard through licensed payment providers. Access to every product is delivered digitally and automatically after payment. We ship no physical goods.</p>
      <div class="sv-pay">
        <img src="{{ asset('images/mtn-momo.png') }}" alt="MTN Mobile Money" loading="lazy" height="26">
        <img src="{{ asset('images/airtel-money.png') }}" alt="Airtel Money" loading="lazy" height="26">
        <img src="{{ asset('images/visa.png') }}" alt="Visa" loading="lazy" height="26">
        <span class="card-word">Mastercard</span>
      </div>
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap">
      <div class="sv-eyebrow">Policies</div>
      <h2>The terms we trade on</h2>
      <div class="sv-policies">
        <a class="sv-policy" href="{{ route('solavia.terms') }}" wire:navigate>
          <b>Terms of Service</b><span>What you agree to when you buy or use our products.</span></a>
        <a class="sv-policy" href="{{ route('solavia.privacy') }}" wire:navigate>
          <b>Privacy Policy</b><span>What we collect, why, and who we share it with.</span></a>
        <a class="sv-policy" href="{{ route('solavia.refund-policy') }}" wire:navigate>
          <b>Refund Policy</b><span>When we refund, and how to ask.</span></a>
      </div>
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap">
      <div class="sv-eyebrow">Find us</div>
      <h2>Contact</h2>
      @include('solavia.partials.contact-strip')
    </div>
  </section>

</div>
@endsection
