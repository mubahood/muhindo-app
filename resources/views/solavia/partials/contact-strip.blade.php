@php $a = config('company.address'); @endphp
<div class="sv-contact">
  <div>
    <h3>Registered office</h3>
    <p>
      {{ config('company.name') }}<br>
      {{ $a['street'] }}<br>
      {{ $a['trading_centre'] }}, {{ $a['division'] }}<br>
      {{ $a['locality'] }}, {{ $a['region'] }}, {{ $a['country'] }}<br>
      {{ $a['po_box'] }}
    </p>
    <p>
      <a class="link" href="tel:{{ config('company.phone_e164') }}">{{ config('company.phone') }}</a><br>
      <a class="link" href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>
    </p>
    <a class="sv-wa" href="{{ config('company.whatsapp') }}" target="_blank" rel="noopener">
      <i class="fab fa-whatsapp" aria-hidden="true"></i> Message us on WhatsApp
    </a>
  </div>
  <div>
    {{-- Lazy, and with a title, because an untitled iframe is unlabelled for
         anybody using a screen reader and this one is below the fold anyway. --}}
    <iframe class="sv-map" loading="lazy" title="Map of {{ $a['trading_centre'] }}, {{ $a['locality'] }}"
      referrerpolicy="no-referrer-when-downgrade" style="border:0;"
      src="https://www.google.com/maps?q={{ urlencode($a['trading_centre'].', '.$a['locality'].', '.$a['region'].', Uganda') }}&output=embed"></iframe>
  </div>
</div>
