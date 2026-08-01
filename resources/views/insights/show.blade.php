@extends('layouts.marketing')
@section('title', $post->title.' — Muhindo Mubaraka')
@section('desc', $post->excerpt)
@section('og_image', $post->cover_image ? asset('storage/'.$post->cover_image) : '')

@push('jsonld')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $post->title,
    'description' => $post->excerpt,
    'datePublished' => $post->published_at?->toIso8601String(),
    'dateModified' => $post->updated_at?->toIso8601String(),
    'author' => ['@type' => 'Person', 'name' => $post->author?->name ?? 'Muhindo Mubaraka'],
    'mainEntityOfPage' => route('insights.show', $post),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@push('styles')
<style>
  .article{max-width:720px;margin:0 auto;}
  .article-body{font-size:15px;font-weight:450;color:var(--tx);line-height:1.75;}
  .article-body > *{margin-bottom:1em;}
  .article-body h2{font-size:20px;font-weight:600;margin:1.6em 0 .5em;color:var(--pri);}
  .article-body h3{font-size:16px;font-weight:600;margin:1.3em 0 .4em;}
  .article-body ul,.article-body ol{margin-left:1.3em;}
  .article-body li{margin-bottom:.35em;}
  .article-body a{color:var(--pri);text-decoration:underline;text-underline-offset:2px;}
  .article-body blockquote{border-left:3px solid var(--gold);padding-left:16px;color:var(--tx2);font-style:italic;}
  .article-body code{background:var(--surface-2);padding:2px 5px;font-size:.9em;
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}
  .article-body pre{background:#0d2237;color:#eef1f6;padding:14px 16px;overflow-x:auto;}
  .article-body pre code{background:none;padding:0;color:inherit;}
  .article-body img{margin:1.2em 0;border:1px solid var(--line);}
  .article-meta{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;font-size:12px;
    font-weight:500;color:var(--tx2);margin-top:10px;}
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <div class="wrap">
    <div class="article">
      <div class="tb-breadcrumb" style="font-size:12px;color:var(--tx3);margin-bottom:8px;">
        <a href="{{ route('insights.index') }}" wire:navigate class="link" style="color:var(--tx2);font-weight:600;">
          <i class="fas fa-arrow-left"></i> Insights
        </a>
      </div>
      <h1 style="font-size:30px;font-weight:400;line-height:1.2;">{{ $post->title }}</h1>
      <div class="article-meta">
        @if($post->category)<span class="tag">{{ $post->category }}</span>@endif
        <span><i class="fas fa-calendar" aria-hidden="true"></i> {{ $post->published_at?->format('d M Y') }}</span>
        <span><i class="fas fa-clock" aria-hidden="true"></i> {{ $post->read_minutes }} min read</span>
        <span><i class="fas fa-user" aria-hidden="true"></i> {{ $post->author?->name ?? 'Muhindo Mubaraka' }}</span>
      </div>
    </div>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    <article class="article">
      @if($post->cover_image)
        <img src="{{ asset('storage/'.$post->cover_image) }}" alt="{{ $post->title }}"
             style="width:100%;border:1px solid var(--line);margin-bottom:24px;" loading="lazy">
      @endif

      {{-- Rendered by MarkdownRenderer, which escapes raw HTML and blocks
           javascript: links, so a post can never inject script into the page. --}}
      <div class="article-body">{!! $html !!}</div>

      @if($post->tags)
        <div class="tag-row" style="margin-top:26px;">
          @foreach($post->tags as $tag)<span class="tag">{{ $tag }}</span>@endforeach
        </div>
      @endif
    </article>
  </div>
</section>

@if($more->isNotEmpty())
<section class="band-surface">
  <div class="wrap">
    <div class="sec-head left" data-rise>
      <div class="sec-idx">More <span>reading</span></div>
      <h2>Other articles</h2>
    </div>
    <div class="work-grid">
      @foreach($more as $m)
        <a href="{{ route('insights.show', $m) }}" wire:navigate class="work-card" data-rise>
          <div class="work-body">
            <div class="tag-row">
              @if($m->category)<span class="tag">{{ $m->category }}</span>@endif
            </div>
            <h3>{{ $m->title }}</h3>
            <p>{{ $m->excerpt }}</p>
            <span class="link">Read article <i class="fas fa-arrow-right"></i></span>
          </div>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

@endsection
