@php $sr = fn($n) => request()->routeIs($n) ? 'on' : ''; @endphp
<div class="subnav">
  <a href="{{ route('portfolio.about') }}" wire:navigate class="{{ $sr('portfolio.about') }}">About</a>
  <a href="{{ route('portfolio.services') }}" wire:navigate class="{{ $sr('portfolio.services') }}">Services</a>
  <a href="{{ route('portfolio.skills') }}" wire:navigate class="{{ $sr('portfolio.skills') }}">Skills</a>
  <a href="{{ route('portfolio.experience') }}" wire:navigate class="{{ $sr('portfolio.experience') }}">Experience</a>
  <a href="{{ route('portfolio.education') }}" wire:navigate class="{{ $sr('portfolio.education') }}">Education</a>
  <a href="{{ route('portfolio.research') }}" wire:navigate class="{{ $sr('portfolio.research') }}">Research</a>
  <a href="{{ route('portfolio.products') }}" wire:navigate class="{{ $sr('portfolio.products') }}">Products</a>
</div>
