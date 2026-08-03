{{--
  The phone-only bar pinned to the bottom of the window.

  Put the page's one real action in it — buy, enrol, check out, hire — with an
  optional <span class="act-note"> on the left for what it costs. Anything in
  the slot is laid out by the layout's .act-bar rules: links and forms stretch
  to equal width, .btn.sq stays a square.

  It is never a second copy of a control already on screen; it is the same
  control kept in reach through a scroll that would otherwise leave it behind.

      <x-action-bar>
        <span class="act-note"><strong>UGX 140,000</strong><span>One payment</span></span>
        <a href="..." class="btn gold">Enrol now</a>
      </x-action-bar>
--}}
<div {{ $attributes->merge(['class' => 'act-bar']) }}>
  {{ $slot }}
</div>
