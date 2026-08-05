@extends('layouts.marketing')
@section('title', 'Source code for sale | Muhindo Mubaraka')
@section('desc', 'Complete, working source code from systems I have delivered, with an install guide for each one.')

@push('styles')
<style>
  /* A source-code listing has to answer three questions on the card: what is
     it built in, what is in the archive, and can I actually run it. The old
     card answered none of them, a cover image, a name and a price. */

  .sc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:18px;}
  .sc{display:flex;flex-direction:column;border:1px solid var(--line);background:var(--surface);
    transition:border-color .16s,transform .16s,box-shadow .16s;}
  .sc:hover{border-color:var(--gold);transform:translateY(-2px);
    box-shadow:0 10px 26px -18px rgba(11,31,58,.45);}

  .sc-top{position:relative;display:block;aspect-ratio:16/10;overflow:hidden;background:var(--bg);
    border-bottom:1px solid var(--line);}
  .sc-top img{width:100%;height:100%;object-fit:cover;display:block;}
  .sc-top .ph{height:100%;border:none;}
  .sc-type{position:absolute;top:0;left:0;background:var(--pri);color:var(--gold);
    font-size:9.5px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;padding:5px 9px;}
  .sc-ver{position:absolute;top:0;right:0;background:rgba(255,255,255,.94);color:var(--tx3);
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:10px;font-weight:600;padding:5px 9px;}

  .sc-body{flex:1;display:flex;flex-direction:column;gap:9px;padding:15px 16px 14px;}
  .sc-body h3{font-size:15.5px;font-weight:600;line-height:1.32;margin:0;}
  .sc-body h3 a{color:var(--tx);}
  .sc-body h3 a:hover{color:var(--gold-d);}
  .sc-body p{font-size:12.5px;line-height:1.6;color:var(--tx3);margin:0;}

  .sc-stack{display:flex;flex-wrap:wrap;gap:5px;}
  .sc-stack span{font-size:9.5px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;
    color:var(--tx2);background:var(--bg);border:1px solid var(--line);padding:3px 7px;}

  .sc-inc{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:4px;}
  .sc-inc li{position:relative;padding-left:16px;font-size:12px;line-height:1.5;color:var(--tx2);}
  .sc-inc li::before{content:'';position:absolute;left:0;top:8px;width:6px;height:1px;background:var(--gold);}
  .sc-more{font-size:11.5px;color:var(--tx3);font-style:italic;}

  .sc-foot{margin-top:auto;padding:13px 16px;border-top:1px solid var(--line);
    display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
  .sc-price{display:flex;align-items:baseline;gap:7px;}
  .sc-price b{font-size:17px;font-weight:700;color:var(--pri);}
  .sc-price b.free{color:var(--gold-d);}
  .sc-price s{font-size:12px;color:var(--tx3);}
  .sc-acts{display:flex;gap:6px;margin:0;}

  /* Described, priced, and not yet released. Listed on purpose. The page is
     worth reading, but with no way to pay for it, because there is nothing
     behind it to hand over. */
  .sc-soon{display:flex;align-items:center;gap:7px;font-size:11.5px;font-weight:600;
    color:var(--tx3);background:var(--bg);border:1px solid var(--line);padding:7px 11px;}
  .sc-soon i{color:var(--gold-d);font-size:10px;}

  .sc-note{grid-column:1/-1;display:flex;gap:13px;align-items:flex-start;
    border:1px solid var(--line);border-left:3px solid var(--gold);background:var(--surface);
    padding:15px 17px;}
  .sc-note i{color:var(--gold-d);margin-top:2px;}
  .sc-note strong{display:block;font-size:13.5px;color:var(--tx);margin-bottom:3px;}
  .sc-note p{font-size:12.5px;line-height:1.65;color:var(--tx3);margin:0;}

  @media(max-width:560px){
    .sc-grid{grid-template-columns:1fr;}
    .sc-foot{flex-direction:column;align-items:stretch;}
    .sc-acts{width:100%;}
    .sc-acts .btn{flex:1;justify-content:center;}
  }
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">SOURCE</span>
  <div class="wrap">
    <div class="eyebrow">Buy it and build on it</div>
    <h1>Source code for sale</h1>
    <p>Complete, working systems I have actually delivered, not demos. Every archive comes with a
       step-by-step install guide, so it runs on your machine and not only on mine.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">

    <form method="GET" action="{{ route('shop.index') }}" class="shop-filters" role="search">
      <label class="sr-only" for="q">Search source code</label>
      <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Search..." class="tb-input">

      <label class="sr-only" for="category">Category</label>
      <select id="category" name="category" class="tb-input">
        <option value="">All categories</option>
        @foreach($categories as $c)<option value="{{ $c }}" @selected($filters['category'] === $c)>{{ $c }}</option>@endforeach
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

    @if(session('error'))<div class="field-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif

    @if($products->isEmpty())
      <div class="tb-empty" style="text-align:center;padding:40px 0;">
        <p class="lead">Nothing matches that search.</p>
        <a href="{{ route('shop.index') }}" wire:navigate class="btn ghost" style="margin-top:12px;">See everything</a>
      </div>
    @else
      <div class="sc-grid">

        <div class="sc-note">
          <i class="fas fa-shield-halved" aria-hidden="true"></i>
          <div>
            <strong>Every purchase is a download, not a promise</strong>
            <p>The file is in your library the moment payment clears, with its install guide beside it.
               Anything still being packaged says so on its card and cannot be bought until it is ready.</p>
          </div>
        </div>

        @foreach($products as $i => $product)
          @php $ready = $product->isDeliverable(); @endphp
          <article class="sc" data-rise style="--d:{{ min($i, 6) * 50 }}ms;">
            <a href="{{ route('shop.show', $product) }}" wire:navigate class="sc-top" aria-label="{{ $product->name }}">
              @if($product->coverUrl())
                <img src="{{ $product->coverUrl() }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
              @else
                <x-ph :src="'images/products/'.$product->slug.'.png'" :alt="$product->name"
                      label="Product cover" size="1600 × 1000px" ratio="16 / 10" icon="fa-code" />
              @endif
              <span class="sc-type">{{ $product->typeLabel() }}</span>
              @if($product->version)<span class="sc-ver">v{{ $product->version }}</span>@endif
            </a>

            <div class="sc-body">
              <h3><a href="{{ route('shop.show', $product) }}" wire:navigate>{{ $product->name }}</a></h3>
              <p>{{ $product->summary }}</p>

              @if($product->stack)
                <div class="sc-stack">
                  @foreach(array_slice($product->stack, 0, 4) as $tool)<span>{{ $tool }}</span>@endforeach
                  @if(count($product->stack) > 4)<span>+{{ count($product->stack) - 4 }}</span>@endif
                </div>
              @endif

              @if($product->whats_inside)
                <ul class="sc-inc">
                  @foreach(array_slice($product->whats_inside, 0, 3) as $item)
                    <li>{{ \Illuminate\Support\Str::limit($item, 74) }}</li>
                  @endforeach
                </ul>
                @if(count($product->whats_inside) > 3)
                  <span class="sc-more">and {{ count($product->whats_inside) - 3 }} more inside</span>
                @endif
              @endif
            </div>

            <div class="sc-foot">
              <span class="sc-price">
                @if($product->isFree())
                  <b class="free">Free</b>
                @else
                  @if($product->isDiscounted())<s>{{ $product->currency }} {{ number_format((float) $product->compare_at_price) }}</s>@endif
                  <b>{{ $product->currency }} {{ number_format((float) $product->price) }}</b>
                @endif
              </span>

              @if($ready)
                <form method="POST" action="{{ route('cart.add') }}" class="sc-acts">
                  @csrf
                  <input type="hidden" name="type" value="product">
                  <input type="hidden" name="id" value="{{ $product->id }}">
                  <button type="submit" class="btn ghost sm" aria-label="Add {{ $product->name }} to basket">
                    <i class="fas fa-cart-plus" aria-hidden="true"></i> Add
                  </button>
                  <button type="submit" name="buy_now" value="1" class="btn gold sm">Buy now</button>
                </form>
              @else
                <span class="sc-soon">
                  <i class="fas fa-hourglass-half" aria-hidden="true"></i> Being packaged
                </span>
              @endif
            </div>
          </article>
        @endforeach
      </div>

      <div style="margin-top:26px;">{{ $products->links() }}</div>
    @endif
  </div>
</section>

@endsection
