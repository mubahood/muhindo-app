@php $sr = fn($n) => request()->routeIs($n) ? 'on' : ''; @endphp
{{-- Mirrors the "About Me" mega-menu, so someone who arrived from the menu
     finds the same set of pages in the same order once they are here. --}}
<div class="subnav">
  <a href="{{ route('portfolio.about') }}" wire:navigate class="{{ $sr('portfolio.about') }}">About me</a>
  <a href="{{ route('portfolio.work') }}" wire:navigate class="{{ $sr('portfolio.work') }}">My work</a>
  <a href="{{ route('portfolio.cv') }}" wire:navigate class="{{ $sr('portfolio.cv') }}">My CV</a>
  <a href="{{ route('portfolio.education') }}" wire:navigate class="{{ $sr('portfolio.education') }}">Qualifications</a>
  <a href="{{ route('portfolio.skills') }}" wire:navigate class="{{ $sr('portfolio.skills') }}">Skills</a>
  <a href="{{ route('portfolio.experience') }}" wire:navigate class="{{ $sr('portfolio.experience') }}">Experience</a>
  <a href="{{ route('portfolio.research') }}" wire:navigate class="{{ $sr('portfolio.research') }}">Research</a>
  <a href="{{ route('portfolio.products') }}" wire:navigate class="{{ $sr('portfolio.products') }}">Products</a>
</div>
