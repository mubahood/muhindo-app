{{--
  Tawk.to live-chat widget. Included before </body> on customer-facing layouts.
  Disabled when services.tawk.enabled is false, when no id is set, or during tests.
  Optionally pass $tawkOffsetY to lift the bubble above fixed bottom UI (e.g. the
  trade screen's BUY/SELL bar).
--}}
@php($tawkId = config('services.tawk.id'))
@if(config('services.tawk.enabled') && $tawkId && ! app()->environment('testing'))
{{-- Start of Tawk.to Script --}}
<script type="text/javascript">
var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
@isset($tawkOffsetY)
Tawk_API.customStyle = {
  visibility: {
    desktop: { position: 'br', xOffset: 24, yOffset: {{ (int) $tawkOffsetY }} },
    mobile:  { position: 'br', xOffset: 14, yOffset: {{ (int) $tawkOffsetY }} }
  }
};
@endisset
(function () {
  var s1 = document.createElement("script"), s0 = document.getElementsByTagName("script")[0];
  s1.async = true;
  s1.src = 'https://embed.tawk.to/{{ $tawkId }}';
  s1.charset = 'UTF-8';
  s1.setAttribute('crossorigin', '*');
  s0.parentNode.insertBefore(s1, s0);
})();
</script>
{{-- End of Tawk.to Script --}}
@endif
