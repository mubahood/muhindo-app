@extends('layouts.marketing')
@section('title', 'Projects for sale — Muhindo Mubaraka')
@section('desc', 'E-books, templates, toolkits and downloadable resources for people building software.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">FOR SALE</span>
  <div class="wrap">
    <div class="eyebrow">Digital products</div>
    <h1>Projects for sale</h1>
    <p>Projects, templates and toolkits I have built, packaged so you can use them too. Instant download after payment.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">

    <form method="GET" action="{{ route('shop.index') }}" class="shop-filters" role="search">
      <label class="sr-only" for="q">Search products</label>
      <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Search products…" class="tb-input">

      <label class="sr-only" for="category">Category</label>
      <select id="category" name="category" class="tb-input">
        <option value="">All categories</option>
        @foreach($categories as $c)<option value="{{ $c }}" @selected($filters['category'] === $c)>{{ $c }}</option>@endforeach
      </select>

      <label class="sr-only" for="type">Type</label>
      <select id="type" name="type" class="tb-input">
        <option value="">All types</option>
        @foreach($types as $t)<option value="{{ $t }}" @selected($filters['type'] === $t)>{{ \App\Models\Product::TYPES[$t] ?? $t }}</option>@endforeach
      </select>

      <label class="sr-only" for="sort">Sort</label>
      <select id="sort" name="sort" class="tb-input">
        <option value="">Featured first</option>
        <option value="price" @selected($filters['sort'] === 'price')>Price, low to high</option>
        <option value="popular" @selected($filters['sort'] === 'popular')>Most bought</option>
      </select>

      <button type="submit" class="btn gold sm">Search</button>
      @if(array_filter($filters))
        <a href="{{ route('shop.index') }}" wire:navigate class="btn ghost sm">Clear</a>
      @endif
    </form>

    @if($products->isEmpty())
      <div class="tb-empty" style="text-align:center;padding:40px 0;">
        <p class="lead">Nothing matches that search yet.</p>
        <a href="{{ route('shop.index') }}" wire:navigate class="btn ghost" style="margin-top:12px;">See everything</a>
      </div>
    @else
      <div class="work-grid">
        @foreach($products as $i => $product)
          <article class="work-card" data-rise style="--d:{{ min($i, 6) * 50 }}ms;">
            <a href="{{ route('shop.show', $product) }}" wire:navigate class="work-shot" aria-label="{{ $product->name }}">
              @if($product->coverUrl())
                <img src="{{ $product->coverUrl() }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
              @else
                <x-ph :src="'images/products/'.$product->slug.'.png'" :alt="$product->name"
                      label="Product cover" size="1600 × 1000px" ratio="16 / 10" icon="fa-box-open" />
              @endif
              <span class="work-no">{{ strtoupper($product->typeLabel()) }}</span>
            </a>
            <div class="work-body">
              <h3><a href="{{ route('shop.show', $product) }}" wire:navigate>{{ $product->name }}</a></h3>
              <p>{{ $product->summary }}</p>

              <div class="price-row">
                @if($product->isFree())
                  <span class="price free">Free</span>
                @else
                  @if($product->isDiscounted())
                    <span class="was">{{ $product->currency }} {{ number_format((float) $product->compare_at_price) }}</span>
                  @endif
                  <span class="price">{{ $product->currency }} {{ number_format((float) $product->price) }}</span>
                @endif
                @if($product->fileSize())<span class="meta">{{ $product->fileSize() }}</span>@endif
              </div>

              <form method="POST" action="{{ route('cart.add') }}" class="buy-row">
                @csrf
                <input type="hidden" name="type" value="product">
                <input type="hidden" name="id" value="{{ $product->id }}">
                <button type="submit" class="btn ghost sm"><i class="fas fa-cart-plus"></i> Add</button>
                <button type="submit" name="buy_now" value="1" class="btn gold sm">Buy now</button>
              </form>
            </div>
          </article>
        @endforeach
      </div>

      <div style="margin-top:24px;">{{ $products->links() }}</div>
    @endif
  </div>
</section>

@endsection
