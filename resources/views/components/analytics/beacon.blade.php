{{--
  Emits the browser half of analytics, and only when there is something for it
  to report against. No visit resolved (tracking off, a crawler, an ignored
  path) means no script tag at all rather than a script that no-ops: the
  cheapest request is the one never made.
--}}
@php
  $beaconToken = config('analytics.enabled', true) && config('analytics.beacon.enabled', true)
      ? app(\App\Services\Analytics\Tracker::class)->beaconToken(request())
      : null;
@endphp
@if($beaconToken)
  <script src="{{ asset('js/analytics.js') }}"
          data-endpoint="{{ route('analytics.beacon') }}"
          data-view="{{ $beaconToken }}" defer></script>
@endif
