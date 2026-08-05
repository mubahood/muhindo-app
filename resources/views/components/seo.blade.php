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
{!! $slot ?? '' !!}
