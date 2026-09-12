@extends('layouts.marketing')
@section('title', 'Products | SOLAVIA GROUP LIMITED')
@section('desc', 'Every product built and operated by SOLAVIA GROUP LIMITED: online courses, source code, School Dynamics, hospital and livestock systems, and consumer apps including LugaFlix and UGNEWS24.')

@include('solavia.partials.styles')

@push('styles')
<style>
  .pr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(268px,1fr));gap:16px;}
  .pr-card{border:1px solid var(--line);background:var(--surface);display:flex;flex-direction:column;
    overflow:hidden;transition:.15s;}
  .pr-card:hover{border-color:var(--sv-green);}
  .pr-shot{height:124px;background:var(--sv-green-soft);display:flex;align-items:center;justify-content:center;
    border-bottom:1px solid var(--line);overflow:hidden;}
  .pr-shot img{max-height:78px;max-width:76%;width:auto;object-fit:contain;}
  /* A monogram when there is no artwork: better than a broken image and better
     than an empty grey box, and it never pretends to be a screenshot. */
  .pr-mono{width:60px;height:60px;display:flex;align-items:center;justify-content:center;
    background:var(--sv-green);color:#fff;font-size:21px;font-weight:600;letter-spacing:.03em;}
  .pr-body{padding:17px 18px 18px;display:flex;flex-direction:column;flex:1;}
  .pr-body h3{font-size:15.5px;font-weight:600;margin:0 0 6px;}
  .pr-body p{font-size:13.2px;color:var(--tx2);line-height:1.6;margin:0 0 12px;flex:1;}
  .pr-price{font-size:13px;font-weight:600;color:var(--tx);margin-bottom:9px;}
  .pr-plat{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:13px;}
  .pr-plat span{font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;
    color:var(--tx3);border:1px solid var(--line);padding:3px 7px;}
  .pr-go{font-size:13px;font-weight:600;color:var(--sv-green);display:inline-flex;align-items:center;gap:6px;}
  .pr-go:hover{color:var(--sv-ember);}
  .pr-note{font-size:12px;color:var(--tx3);font-style:italic;}
  .pr-group{margin-bottom:38px;}
  .pr-group > p{color:var(--tx2);margin:0 0 18px;font-size:14px;}
  .pr-owned{border-top:1px solid var(--line);padding-top:22px;margin-top:6px;font-size:14px;color:var(--tx2);}
  @media(max-width:760px){ .pr-grid{grid-template-columns:1fr;} }
</style>
@endpush

@section('content')
<div class="sv">

  <section class="sv-hero">
    <div class="wrap">
      <img src="{{ asset('images/solavia-logo-reversed.svg') }}" alt="SOLAVIA GROUP LIMITED">
      <h1>Our products</h1>
      <p class="lede">Everything SOLAVIA GROUP LIMITED builds and operates, what it costs, and where to find it.</p>
      <div class="acts">
        <a class="sv-btn sv-btn-ghost" href="{{ route('solavia.home') }}" wire:navigate>Back to the company</a>
      </div>
    </div>
  </section>

  <section class="sv-sec">
    <div class="wrap">
      @foreach($groups as $group)
        <div class="pr-group">
          <h2>{{ $group['heading'] }}</h2>
          <p>{{ $group['blurb'] }}</p>

          <div class="pr-grid">
            @foreach($group['items'] as $item)
              <div class="pr-card">
                <div class="pr-shot">
                  @if($item['image'] ?? null)
                    <img src="{{ asset($item['image']) }}" alt="{{ $item['name'] }}" loading="lazy">
                  @else
                    <span class="pr-mono" aria-hidden="true">{{ mb_substr($item['name'], 0, 2) }}</span>
                  @endif
                </div>

                <div class="pr-body">
                  <h3>{{ $item['name'] }}</h3>
                  <p>{{ $item['description'] }}</p>

                  <div class="pr-price">{{ $item['price'] ?? $item['price_note'] ?? '' }}</div>

                  <div class="pr-plat">
                    @foreach($item['platforms'] as $platform)<span>{{ $platform }}</span>@endforeach
                  </div>

                  @if($item['url'])
                    <a class="pr-go" href="{{ $item['url'] }}"
                       @if(! str_starts_with($item['url'], '/')) target="_blank" rel="noopener" @else wire:navigate @endif>
                      Visit <i class="fas fa-arrow-right" aria-hidden="true" style="font-size:11px;"></i>
                    </a>
                  @endif

                  @foreach($item['extra_links'] ?? [] as $extra)
                    <a class="pr-go" href="{{ $extra['url'] }}" target="_blank" rel="noopener" style="margin-top:6px;">
                      {{ $extra['label'] }} <i class="fas fa-arrow-up-right-from-square" aria-hidden="true" style="font-size:10px;"></i>
                    </a>
                  @endforeach

                  @if($item['link_note'] ?? null)
                    <div class="pr-note" style="margin-top:7px;">{{ $item['link_note'] }}</div>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endforeach

      <p class="pr-owned">All products listed here are owned and operated by {{ config('company.name') }}.</p>
    </div>
  </section>

</div>
@endsection
