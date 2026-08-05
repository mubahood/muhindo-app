@props([
    'src' => null,          // path under public/, e.g. 'images/portrait.jpg'
    'alt' => '',
    'label' => 'Image',     // what belongs here, shown while the file is missing
    'size' => null,         // recommended pixel size, e.g. '900 × 1100'
    'ratio' => '4 / 5',
    'icon' => 'fa-image',
    'round' => false,
    'contain' => false,     // logos sit inside their box; photos fill it
])

@php
    /**
     * A slot for artwork that hasn't been supplied yet.
     *
     * While the file is missing this renders a labelled drop-target naming the
     * exact path and pixel size to use, so the page is reviewable before any
     * asset exists. The moment the file lands at that path it renders as the
     * real image, no template edit, nothing to remember to switch over.
     */
    $path = $src ? ltrim($src, '/') : null;
    $exists = $path !== null && is_file(public_path($path));
@endphp

@if($exists)
  <img src="{{ asset($path) }}" alt="{{ $alt }}" loading="lazy" decoding="async"
       {{ $attributes->merge(['class' => 'ph-img'.($round ? ' round' : '').($contain ? ' contain' : '')]) }}
       style="aspect-ratio:{{ $ratio }};{{ $attributes->get('style') }}">
@else
  {{-- Decorative to assistive tech: it carries no content yet, only instructions
       for whoever is filling the page in. --}}
  <div {{ $attributes->merge(['class' => 'ph'.($round ? ' round' : '')]) }}
       style="aspect-ratio:{{ $ratio }};{{ $attributes->get('style') }}"
       role="img" aria-label="{{ $alt !== '' ? $alt : $label.' image not added yet' }}">
    <i class="fas {{ $icon }}" aria-hidden="true"></i>
    <span class="ph-label">{{ $label }}</span>
    @if($size)<span class="ph-size">{{ $size }}</span>@endif
    @if($path)<code class="ph-path">public/{{ $path }}</code>@endif
  </div>
@endif
