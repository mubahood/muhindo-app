@extends('layouts.marketing')
@section('title', $product->name.' — Shop')
@section('desc', $product->summary)
@section('og_image', $product->coverUrl() ?? '')

@section('content')

<section class="page-hero tex-glow">
  <div class="wrap">
    <div class="tb-breadcrumb" style="font-size:12px;color:var(--tx3);margin-bottom:8px;">
      <a href="{{ route('shop.index') }}" wire:navigate class="link" style="color:var(--tx2);font-weight:600;">
        <i class="fas fa-arrow-left"></i> Shop
      </a>
    </div>
    <h1 style="font-size:28px;font-weight:400;">{{ $product->name }}</h1>
    <p>{{ $product->summary }}</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    <div class="product-layout">
      <div class="product-main">
        @if($product->coverUrl())
          <img src="{{ $product->coverUrl() }}" alt="{{ $product->name }}"
               style="width:100%;border:1px solid var(--line);margin-bottom:20px;">
        @endif

        @if($product->description)
          <div class="article-body" style="font-size:14px;">{!! app(\App\Services\Learning\MarkdownRenderer::class)->toHtml($product->description) !!}</div>
        @endif

        @if($product->tags)
          <div class="tag-row" style="margin-top:20px;">
            @foreach($product->tags as $tag)<span class="tag">{{ $tag }}</span>@endforeach
          </div>
        @endif
      </div>

      <aside class="buy-box">
        <div class="price-row" style="margin-bottom:12px;">
          @if($product->isFree())
            <span class="price free" style="font-size:24px;">Free</span>
          @else
            @if($product->isDiscounted())
              <span class="was">{{ $product->currency }} {{ number_format((float) $product->compare_at_price) }}</span>
            @endif
            <span class="price" style="font-size:24px;">{{ $product->currency }} {{ number_format((float) $product->price) }}</span>
          @endif
        </div>

        <ul class="includes">
          <li>{{ $product->typeLabel() }}@if($product->fileSize()) · {{ $product->fileSize() }}@endif</li>
          <li>Instant download once payment clears</li>
          <li>Yours to re-download any time</li>
        </ul>

        @if($owned)
          <a href="{{ route('shop.downloads') }}" wire:navigate class="btn gold" style="width:100%;justify-content:center;">
            <i class="fas fa-circle-check"></i> You own this — download
          </a>
        @else
          <form method="POST" action="{{ route('cart.add') }}" style="display:flex;flex-direction:column;gap:8px;">
            @csrf
            <input type="hidden" name="type" value="product">
            <input type="hidden" name="id" value="{{ $product->id }}">
            <button type="submit" name="buy_now" value="1" class="btn gold" style="width:100%;justify-content:center;">
              <i class="fas fa-bolt"></i> Buy now
            </button>
            <button type="submit" class="btn ghost" style="width:100%;justify-content:center;">
              <i class="fas fa-cart-plus"></i> Add to basket
            </button>
          </form>
        @endif

        <div class="pay-icons">
          <span>MTN MoMo</span><span>Airtel Money</span><span>Visa</span><span>Mastercard</span>
        </div>
        <p class="money-comfort">Payment is handled by Flutterwave. Your card details never touch this site.</p>
      </aside>
    </div>

    @if($related->isNotEmpty())
      <div class="sec-head left" style="margin-top:40px;"><div class="sec-idx">More <span>like this</span></div></div>
      <div class="work-grid">
        @foreach($related as $r)
          <a href="{{ route('shop.show', $r) }}" wire:navigate class="work-card">
            <div class="work-body">
              <h3>{{ $r->name }}</h3>
              <p>{{ $r->summary }}</p>
              <span class="link">View <i class="fas fa-arrow-right"></i></span>
            </div>
          </a>
        @endforeach
      </div>
    @endif
  </div>
</section>

@endsection
