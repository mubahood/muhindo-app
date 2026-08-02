@extends('layouts.marketing')
@section('title', 'Start a project — Muhindo Mubaraka')
@section('desc', "Have an idea? Let's build it. Tell me about your project and I'll reply within 24 hours.")

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">PROJECT</span>
  <div class="wrap">
    <div class="eyebrow">Build with me</div>
    <h1>Have an idea? Let's build it.</h1>
    <p>I design and build software for anyone with a real problem — individuals, startups, schools, clinics, NGOs, government teams and enterprises. Tell me about it in plain language; I'll take it from there.</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap">
    <div class="steps">
      <div class="step"><div class="n">1</div><h4>Tell me about it</h4><p>Fill in the form below — plain language is perfect.</p></div>
      <div class="step"><div class="n">2</div><h4>Free consultation call</h4><p>We talk it through and I ask the right questions.</p></div>
      <div class="step"><div class="n">3</div><h4>Proposal & quote</h4><p>You get a clear scope and price before anything starts.</p></div>
      <div class="step"><div class="n">4</div><h4>Build, with weekly updates</h4><p>Track progress in your own client portal as we go.</p></div>
    </div>
  </div>
</section>

@if($projects->isNotEmpty())
<section class="band-surface">
  <div class="wrap">
    <div class="sec-head"><div class="eyebrow">Proof</div><h2>Some of what I've built</h2></div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
      @foreach($projects as $p)
        <a href="{{ route('portfolio.project', $p) }}" wire:navigate class="proj-card">
          <h3>{{ $p->title }}</h3>
          <p>{{ $p->description }}</p>
          <span class="link">View case study <i class="fas fa-arrow-right"></i></span>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

<section class="tex-grid">
  <div class="wrap page">
    @if(session('success'))
      <div class="alert-success">{{ session('success') }}</div>
    @endif

    <h2 style="margin-top:0;">Tell me about your project</h2>

    <form class="contact-form" method="POST" action="{{ route('start-a-project.store') }}" style="max-width:640px;">
      @csrf
      <x-form-shield />

      <div class="row2">
        <div>
          <label for="name">Name</label>
          <input type="text" id="name" name="name" value="{{ old('name') }}" required>
          @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="{{ old('email') }}" required>
          @error('email')<div class="field-error">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="row2">
        <div>
          <label for="phone">Phone / WhatsApp</label>
          <input type="text" id="phone" name="phone" value="{{ old('phone') }}">
        </div>
        <div>
          <label for="organisation">Organisation <span style="font-weight:400;color:var(--tx3);">(optional — individuals welcome)</span></label>
          <input type="text" id="organisation" name="organisation" value="{{ old('organisation') }}">
        </div>
      </div>

      <div>
        <label for="project_type">Project type</label>
        <select id="project_type" name="project_type" required>
          <option value="">Choose one…</option>
          @foreach(['website' => 'Website', 'web_system' => 'Web system', 'mobile_app' => 'Mobile app', 'ecommerce' => 'E-commerce', 'school_clinic_system' => 'School / clinic system', 'other' => 'Other'] as $v => $label)
            <option value="{{ $v }}" {{ old('project_type') === $v ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
        @error('project_type')<div class="field-error">{{ $message }}</div>@enderror
      </div>

      <div class="row2">
        <div>
          <label for="budget_range">Budget <span style="font-weight:400;color:var(--tx3);">(optional)</span></label>
          <select id="budget_range" name="budget_range">
            <option value="">Not sure yet</option>
            @foreach(['under_2m' => 'Under UGX 2M', '2m_5m' => 'UGX 2M – 5M', '5m_10m' => 'UGX 5M – 10M', 'over_10m' => 'Over UGX 10M'] as $v => $label)
              <option value="{{ $v }}" {{ old('budget_range') === $v ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label for="timeline">Timeline <span style="font-weight:400;color:var(--tx3);">(optional)</span></label>
          <select id="timeline" name="timeline">
            <option value="">Not sure yet</option>
            @foreach(['asap' => 'As soon as possible', '1_3_months' => '1–3 months', '3_6_months' => '3–6 months'] as $v => $label)
              <option value="{{ $v }}" {{ old('timeline') === $v ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div>
        <label for="description">Describe it in your own words</label>
        <textarea id="description" name="description" required placeholder="Plain language is perfect — what problem are you solving, and for who?">{{ old('description') }}</textarea>
        @error('description')<div class="field-error">{{ $message }}</div>@enderror
      </div>

      <button type="submit" class="btn gold lg">Send project details</button>
    </form>
  </div>
</section>

@endsection
