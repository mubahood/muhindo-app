@props(['show', 'title' => '', 'width' => '560px'])
{{--
  Reusable AJAX slide-over for Livewire forms.
  Usage: <x-ui.slideover show="showForm" title="New department">…form…</x-ui.slideover>
  `show` is the name of a boolean Livewire property; the panel entangles to it so
  opening/closing is driven by the server component and animated by Alpine. No
  x-teleport — the content stays inside the component root so wire: bindings are
  never severed; position:fixed handles the overlay (no transformed ancestors).

  STABILITY: the panel only closes via the ✕ button, a Cancel action, or a
  successful save (server sets the property false). It deliberately does NOT
  close on backdrop click or the Escape key, so a half-filled form is never lost
  by an accidental tap outside. Body scroll is locked while open.
--}}
<div x-data="{ open: $wire.entangle('{{ $show }}') }"
     x-init="$watch('open', v => window.tbScrollLock && window.tbScrollLock(v))"
     x-show="open" x-cloak
     class="tb-slideover-backdrop"
     x-transition.opacity.duration.200ms
     style="display:none;">
  <div class="tb-slideover" style="width:min({{ $width }},100%);"
       role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif
       x-show="open"
       x-transition:enter="tb-slide-enter" x-transition:enter-start="tb-slide-start" x-transition:enter-end="tb-slide-end">
    <div class="tb-slideover-head">
      <h2>{{ $title }}</h2>
      <button type="button" class="tb-slideover-x" @click="open = false" aria-label="Close"><i class="fas fa-xmark"></i></button>
    </div>
    {{ $slot }}
  </div>
</div>
