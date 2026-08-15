@props([
    'title' => 'Muhindo Mubaraka, Software Engineer & Programming Teacher',
    'description' => "I teach computer programming and computer-related courses, and I build software for anyone with a real problem: individuals, startups, schools, clinics, NGOs and enterprises across Uganda.",
    'image' => null,
    'type' => 'website',
    'canonical' => null,
])
@php
    $seoTitle = \Illuminate\Support\Str::limit($title, 60, '');
    $seoDescription = \Illuminate\Support\Str::limit($description, 155, '');
    $seoCanonical = $canonical ?? url()->current();
    $seoImage = $image ?? asset('images/og.png');
@endphp
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $seoCanonical }}">
<meta name="theme-color" content="#0b1f3a">
<link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon.png') }}">
{{-- Linked here rather than per layout, because a manifest nothing points at
     is a file the browser never asks for: it shipped correct-looking and
     completely inert, so no install prompt was ever offered. apple-touch-icon
     is the same story on iOS, which ignores the manifest and looks only for
     this tag before falling back to a screenshot of the page. --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo-192.png') }}">
<meta name="apple-mobile-web-app-title" content="Muhindo">
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="Muhindo Mubaraka">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">
{{-- Ownership tokens. Search engines look for these on the page they were
     given, so they belong in the layout every public page shares rather than
     on the home page alone: a property verified against /e-learning fails if
     the tag only exists at the root.

     Anything that is not a non-empty string is skipped rather than printed.
     Two of these provider names contain a dot, and config() reads a dot as a
     level of nesting, so one config(['seo.verifications.msvalidate.01' => ...])
     turns the value into an array. Rendering that would put a PHP error on
     every public page over a line nobody would think to test. --}}
@foreach((array) config('seo.verifications', []) as $provider => $token)
@if(is_string($token) && trim($token) !== '')
<meta name="{{ $provider }}" content="{{ trim($token) }}">
@endif
@endforeach
{!! $slot ?? '' !!}
