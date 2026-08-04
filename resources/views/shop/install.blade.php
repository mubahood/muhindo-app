@extends('layouts.marketing')
@section('title', 'Installing '.$product->name)
@section('desc', 'Step-by-step setup for '.$product->name.'.')

@push('styles')
<style>
  /* A zip of somebody else's codebase is where most source-code sales turn
     into support requests: it runs on the author's machine and nowhere else.
     This page is the difference between a sale and a refund. */

  .in-bar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;border:1px solid var(--line);
    background:var(--surface);padding:14px 16px;margin-bottom:26px;}
  .in-bar .in-t{flex:1;min-width:180px;}
  .in-bar strong{display:block;font-size:14px;color:var(--tx);}
  .in-bar span{font-size:12px;color:var(--tx3);}
  .in-bar .in-acts{display:flex;gap:8px;flex-wrap:wrap;}

  /* The guide is authored markdown, so it is styled here rather than inline. */
  .in-guide{font-size:14.5px;line-height:1.75;color:var(--tx2);}
  .in-guide h2{font-size:17px;font-weight:600;color:var(--pri);margin:34px 0 12px;
    padding-bottom:8px;border-bottom:1px solid var(--line);}
  .in-guide h2:first-child{margin-top:0;}
  .in-guide h3{font-size:14.5px;font-weight:600;color:var(--tx);margin:22px 0 8px;}
  .in-guide p{margin-bottom:13px;}
  .in-guide ul,.in-guide ol{margin:0 0 14px;padding-left:20px;}
  .in-guide li{margin:6px 0;line-height:1.7;}
  .in-guide a{color:var(--pri);font-weight:600;text-decoration:underline;text-underline-offset:2px;}
  .in-guide a:hover{color:var(--gold-d);}
  .in-guide strong{color:var(--tx);font-weight:600;}

  /* Commands are meant to be copied, so they are set as commands. */
  .in-guide pre{background:var(--pri);color:#E6EDF3;padding:14px 16px;overflow-x:auto;
    margin:0 0 16px;font-size:12.5px;line-height:1.7;}
  .in-guide pre code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
    background:none;color:inherit;padding:0;font-size:inherit;}
  .in-guide :not(pre) > code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
    font-size:12.5px;background:var(--gold-soft);color:var(--pri);padding:2px 6px;}

  .in-help{margin-top:34px;border:1px solid var(--line);border-left:3px solid var(--gold);
    background:var(--surface);padding:17px 19px;}
  .in-help h2{font-size:15px;font-weight:600;margin:0 0 6px;}
  .in-help p{font-size:13px;line-height:1.7;color:var(--tx3);margin:0 0 14px;}
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">SETUP</span>
  <div class="wrap">
    <div class="eyebrow">
      <a href="{{ route('shop.downloads') }}" wire:navigate style="color:var(--gold-d);">&larr; My library</a>
    </div>
    <h1>Installing {{ $product->name }}</h1>
    <p>Follow these in order and it will run. Every step is written for a machine that has
       nothing installed yet.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap page">

    <div class="in-bar">
      <div class="in-t">
        <strong>{{ $product->name }}@if($product->version) · v{{ $product->version }}@endif</strong>
        <span>
          Bought {{ $license->granted_at?->format('d M Y') }}
          @if($license->download_count) · downloaded {{ $license->download_count }}× @endif
        </span>
      </div>
      <div class="in-acts">
        @if($product->file_path)
          {{-- A file response is a real navigation, never an SPA swap. --}}
          <a href="{{ route('shop.download', $product) }}" data-no-navigate class="btn gold sm">
            <i class="fas fa-download" aria-hidden="true"></i>
            Download{{ $product->fileSize() ? ' ('.$product->fileSize().')' : '' }}
          </a>
        @elseif($product->external_url)
          <a href="{{ $product->external_url }}" target="_blank" rel="noopener" data-no-navigate class="btn gold sm">
            <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Open the repository
          </a>
        @endif
        @if($product->demo_url)
          <a href="{{ $product->demo_url }}" target="_blank" rel="noopener" class="btn ghost sm">
            See it running
          </a>
        @endif
      </div>
    </div>

    <div class="in-guide">{!! $guide !!}</div>

    <div class="in-help">
      <h2>Still stuck?</h2>
      <p>Send me what you tried and the exact error — the message matters more than the description
         of it. I answer setup questions on things I have sold.</p>
      <a href="{{ route('contact', ['about' => $product->slug]) }}" wire:navigate class="btn ghost sm">
        Ask about this install
      </a>
    </div>

  </div>
</section>

{{-- Phone only. Somebody following an install guide on a laptop with the phone
     beside them still wants the file one tap away. --}}
<x-action-bar>
  @if($product->file_path)
    <a href="{{ route('shop.download', $product) }}" data-no-navigate class="btn gold">
      <i class="fas fa-download" aria-hidden="true"></i> Download
    </a>
  @elseif($product->external_url)
    <a href="{{ $product->external_url }}" target="_blank" rel="noopener" data-no-navigate class="btn gold">
      Open repository
    </a>
  @endif
  <a href="{{ route('shop.downloads') }}" wire:navigate class="btn ghost">
    My library <i class="fas fa-arrow-right" aria-hidden="true"></i>
  </a>
</x-action-bar>

@endsection
