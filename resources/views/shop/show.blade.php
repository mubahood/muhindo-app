@extends('layouts.marketing')
@section('title', $product->name.' | Source code for sale')
@section('desc', $product->summary ?? '')
@section('og_image', $product->coverUrl() ?? '')

@push('styles')
<style>
  /* Everything somebody needs before deciding, in the order they need it:
     what is it, what is in the box, what will it run on, how do I get it
     going, and what am I allowed to do with it. The old page had a paragraph
     and a price. */

  .pr-lead{display:grid;grid-template-columns:1fr;gap:0;border:1px solid var(--line);
    background:var(--surface);margin-bottom:26px;}
  .pr-lead img{width:100%;display:block;border-bottom:1px solid var(--line);}

  .pr-facts{display:flex;flex-wrap:wrap;}
  .pr-facts div{flex:1 1 130px;padding:12px 15px;border-right:1px solid var(--line);}
  .pr-facts div:last-child{border-right:0;}
  .pr-facts dt{font-size:9.5px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:var(--tx3);}
  .pr-facts dd{font-size:13px;font-weight:600;color:var(--tx);margin:3px 0 0;}

  .page .pr-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;}
  .page .pr-list li{position:relative;margin:0;padding-left:24px;font-size:13.5px;line-height:1.6;color:var(--tx2);}
  .page .pr-list li::before{content:'\2713';position:absolute;left:0;top:0;color:var(--gold-d);
    font-weight:700;font-size:12px;background:none;width:auto;height:auto;}

  .pr-stack{display:flex;flex-wrap:wrap;gap:6px;}
  .pr-stack span{font-size:11.5px;font-weight:600;color:var(--tx2);background:var(--surface);
    border:1px solid var(--line);padding:6px 11px;}

  /* A preview of the install guide, so "will I be able to run this" is
     answered before payment rather than discovered after it. */
  .pr-steps{counter-reset:s;list-style:none;margin:0;padding:0;}
  .page .pr-step{position:relative;margin:0;padding:0 0 16px 46px;font-size:13.5px;line-height:1.65;color:var(--tx2);}
  .page .pr-step::before{counter-increment:s;content:counter(s,decimal-leading-zero);
    position:absolute;left:0;top:0;width:30px;height:30px;background:var(--surface);
    border:1px solid var(--line);display:flex;align-items:center;justify-content:center;
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;font-weight:700;
    color:var(--gold-d);}
  .page .pr-step:last-child{padding-bottom:0;}

  .pr-guide{display:flex;gap:13px;align-items:flex-start;border:1px solid var(--line);
    border-left:3px solid var(--gold);background:var(--surface);padding:15px 17px;margin-top:14px;}
  .pr-guide i{color:var(--gold-d);margin-top:2px;}
  .pr-guide strong{display:block;font-size:13.5px;color:var(--tx);margin-bottom:3px;}
  .pr-guide p{font-size:12.5px;line-height:1.65;color:var(--tx3);margin:0;}

  .pr-soon{border:1px solid var(--line);background:var(--bg);padding:16px 18px;text-align:center;}
  .pr-soon i{color:var(--gold-d);font-size:18px;}
  .pr-soon strong{display:block;font-size:14px;color:var(--tx);margin:8px 0 4px;}
  .pr-soon p{font-size:12.5px;line-height:1.6;color:var(--tx3);margin:0 0 12px;}

  .pr-owned{border:1px solid var(--gold);background:var(--gold-soft);padding:14px 16px;
    display:flex;gap:11px;align-items:flex-start;margin-bottom:12px;}
  .pr-owned i{color:var(--gold-d);margin-top:2px;}
  .pr-owned strong{display:block;font-size:13.5px;color:var(--pri);}
  .pr-owned span{font-size:12px;color:var(--tx2);}

  .pr-more{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;}
  .pr-more a{border:1px solid var(--line);background:var(--surface);padding:14px 16px;display:block;}
  .pr-more a:hover{border-color:var(--gold);}
  .pr-more h3{font-size:13.5px;font-weight:600;color:var(--tx);margin:0 0 4px;line-height:1.35;}
  .pr-more span{font-size:11.5px;font-weight:600;color:var(--gold-d);}
</style>
@endpush

@section('content')

@php
  $ready = $product->isDeliverable();
  $steps = $product->requirements ?? [];
@endphp

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">CODE</span>
  <div class="wrap">
    <div class="eyebrow">
      <a href="{{ route('shop.index') }}" wire:navigate style="color:var(--gold-d);">&larr; Source code</a>
      @if($product->category) / {{ $product->category }} @endif
    </div>
    <h1>{{ $product->name }}</h1>
    <p>{{ $product->summary }}</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    @if(session('error'))<div class="field-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif

    <div class="course-layout">
      <div class="main page">

        <div class="pr-lead">
          @if($product->coverUrl())
            <img src="{{ $product->coverUrl() }}" alt="{{ $product->name }}">
          @endif
          <dl class="pr-facts">
            <div><dt>Type</dt><dd>{{ $product->typeLabel() }}</dd></div>
            @if($product->version)<div><dt>Version</dt><dd>v{{ $product->version }}</dd></div>@endif
            @if($product->fileSize())<div><dt>Archive</dt><dd>{{ $product->fileSize() }}</dd></div>@endif
            @if($product->stack)<div><dt>Built in</dt><dd>{{ $product->stack[0] }}</dd></div>@endif
            <div><dt>Updated</dt><dd>{{ $product->updated_at->format('M Y') }}</dd></div>
          </dl>
        </div>

        @if($product->description)
          <div class="ch-sec">
            <h2 class="ch-h">What this is</h2>
            <div class="article-body" style="font-size:14.5px;">
              {!! app(\App\Services\Learning\MarkdownRenderer::class)->toHtml($product->description) !!}
            </div>
          </div>
        @endif

        @if($product->whats_inside)
          <div class="ch-sec">
            <h2 class="ch-h">What is in the archive</h2>
            <ul class="pr-list">
              @foreach($product->whats_inside as $item)<li>{{ $item }}</li>@endforeach
            </ul>
          </div>
        @endif

        @if($product->stack)
          <div class="ch-sec">
            <h2 class="ch-h">Built with</h2>
            <div class="pr-stack">
              @foreach($product->stack as $tool)<span>{{ $tool }}</span>@endforeach
            </div>
          </div>
        @endif

        @if($steps)
          <div class="ch-sec">
            <h2 class="ch-h">What you need to run it</h2>
            <ol class="pr-steps">
              @foreach($steps as $requirement)<li class="pr-step">{{ $requirement }}</li>@endforeach
            </ol>

            @if($product->hasInstallGuide())
              <div class="pr-guide">
                <i class="fas fa-book-open" aria-hidden="true"></i>
                <div>
                  <strong>A full install guide comes with it</strong>
                  <p>Unzip, dependencies, database, .env, first run, putting it on a real server, and a
                     troubleshooting list for the things that actually go wrong. It is in your library
                     beside the download, the moment you own it.</p>
                </div>
              </div>
            @endif
          </div>
        @endif

        @if($product->license_terms)
          <div class="ch-sec">
            <h2 class="ch-h">What you may do with it</h2>
            <p style="font-size:13.5px;line-height:1.7;color:var(--tx2);">{{ $product->license_terms }}</p>
          </div>
        @endif

        @if($related->isNotEmpty())
          <div class="ch-sec">
            <h2 class="ch-h">Also in {{ $product->category }}</h2>
            <div class="pr-more">
              @foreach($related as $other)
                <a href="{{ route('shop.show', $other) }}" wire:navigate>
                  <h3>{{ $other->name }}</h3>
                  <span>
                    {{ $other->isFree() ? 'Free' : $other->currency.' '.number_format((float) $other->price) }}
                    <i class="fas fa-arrow-right"></i>
                  </span>
                </a>
              @endforeach
            </div>
          </div>
        @endif

      </div>

      <aside class="buy-box" id="buy">
        @if($owned)
          <div class="pr-owned">
            <i class="fas fa-circle-check" aria-hidden="true"></i>
            <div>
              <strong>You own this</strong>
              <span>It is in your library, with its install guide.</span>
            </div>
          </div>
          <a href="{{ route('shop.downloads') }}" wire:navigate class="btn gold" style="width:100%;justify-content:center;">
            <i class="fas fa-download" aria-hidden="true"></i> Go to my library
          </a>
          @if($product->hasInstallGuide())
            <a href="{{ route('shop.install', $product) }}" wire:navigate class="btn ghost"
               style="width:100%;justify-content:center;margin-top:8px;">
              <i class="fas fa-book-open" aria-hidden="true"></i> Install guide
            </a>
          @endif
        @else
          <div class="price {{ $product->isFree() ? 'free' : '' }}">
            @if($product->isFree())
              Free
            @else
              @if($product->isDiscounted())
                <span class="was">{{ $product->currency }} {{ number_format((float) $product->compare_at_price) }}</span>
              @endif
              {{ $product->currency }} {{ number_format((float) $product->price) }}
            @endif
          </div>

          @if($ready)
            <form method="POST" action="{{ route('cart.add') }}" style="display:flex;flex-direction:column;gap:8px;">
              @csrf
              <input type="hidden" name="type" value="product">
              <input type="hidden" name="id" value="{{ $product->id }}">
              <button type="submit" name="buy_now" value="1" class="btn gold" style="width:100%;justify-content:center;">
                <i class="fas fa-bolt" aria-hidden="true"></i> Buy now
              </button>
              <button type="submit" class="btn ghost" style="width:100%;justify-content:center;">
                <i class="fas fa-cart-plus" aria-hidden="true"></i> Add to basket
              </button>
            </form>

            <ul class="includes">
              <li>Instant download once payment clears</li>
              <li>Step-by-step install guide included</li>
              <li>Yours to re-download any time</li>
              @if($product->demo_url)<li>Live demo available</li>@endif
            </ul>
          @else
            {{-- Nothing behind it to hand over, so there is no way to pay for
                 it. Said plainly rather than dressed up as a launch date
                 nobody has committed to. --}}
            <div class="pr-soon">
              <i class="fas fa-box-open" aria-hidden="true"></i>
              <strong>Still being packaged</strong>
              <p>This one is written up but the archive is not ready to hand over yet, so it cannot be
                 bought. Ask me to send it the moment it is.</p>
              <a href="{{ route('hire') }}" wire:navigate
                 class="btn ghost sm" style="width:100%;justify-content:center;">
                Tell me when it is ready
              </a>
            </div>
          @endif
        @endif

        @if($product->demo_url)
          <a href="{{ $product->demo_url }}" target="_blank" rel="noopener" class="btn ghost"
             style="width:100%;justify-content:center;margin-top:8px;">
            See it running <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
          </a>
        @endif

        @if(! $owned && $ready && ! $product->isFree())
          <div class="pay-icons">
            <span>MTN MoMo</span><span>Airtel Money</span><span>Visa</span><span>Mastercard</span>
          </div>
          <p class="money-comfort">Payment is handled by Flutterwave. Your card details never touch this site.</p>
        @endif
      </aside>
    </div>
  </div>
</section>

{{-- Phone only. No coupon field on this page, so the bar can do the thing
     itself rather than jumping to a box that does it. --}}
<x-action-bar>
  <span class="act-note">
    <strong @class(['free' => $product->isFree()])>
      {{ $product->isFree() ? 'Free' : $product->currency.' '.number_format((float) $product->price) }}
    </strong>
    <span>{{ $product->typeLabel() }}</span>
  </span>

  @if($owned)
    <a href="{{ route('shop.downloads') }}" wire:navigate class="btn gold">
      <i class="fas fa-download" aria-hidden="true"></i> My library
    </a>
  @elseif($ready)
    <form method="POST" action="{{ route('cart.add') }}">
      @csrf
      <input type="hidden" name="type" value="product">
      <input type="hidden" name="id" value="{{ $product->id }}">
      <button type="submit" name="buy_now" value="1" class="btn gold">
        <i class="fas fa-bolt" aria-hidden="true"></i> Buy now
      </button>
    </form>
  @else
    <a href="{{ route('hire') }}" wire:navigate class="btn ghost">
      Tell me when it is ready
    </a>
  @endif
</x-action-bar>

@endsection
