@extends('layouts.marketing')
@section('title', ($identity['name'] ?? 'Muhindo Mubaraka').' — '.($identity['title'] ?? 'Information Systems'))
@section('desc', $identity['tagline'] ?? '')

@section('content')

<section class="hero">
  <div class="wrap">
    <div class="eyebrow">{{ $identity['title'] ?? '' }} · {{ $identity['location'] ?? '' }}</div>
    <h1>Hi, I'm <b>{{ $identity['name'] ?? 'Muhindo Mubaraka' }}</b>.<br>{{ $identity['tagline'] ?? '' }}</h1>
    <p class="lead">{{ $about['lead'] ?? '' }}</p>
    <div class="ctas">
      <a href="#work" class="btn gold lg">See my work</a>
      <a href="#contact" class="btn ghost lg">Get in touch</a>
    </div>
    @if(count($stats))
    <div class="stat-row">
      @foreach($stats as $s)
        <div class="stat"><div class="v">{{ $s['value'] }}</div><div class="l">{{ $s['label'] }}</div></div>
      @endforeach
    </div>
    @endif
  </div>
</section>

@if(count($clients))
<section class="band-surface" style="padding:34px 0;">
  <div class="wrap">
    <div class="clients-strip">
      @foreach($clients as $c)<span>{{ $c }}</span>@endforeach
    </div>
  </div>
</section>
@endif

<section id="about">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">About</div>
      <h2>Systems that hold up in the real world</h2>
    </div>
    <div style="max-width:760px;margin:0 auto;display:flex;flex-direction:column;gap:16px;">
      @foreach($about['paragraphs'] ?? [] as $p)
        <p class="lead" style="font-size:14.5px;">{{ $p }}</p>
      @endforeach
    </div>
  </div>
</section>

@if($services->count())
<section class="band-surface">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">What I do</div>
      <h2>Services</h2>
    </div>
    <div class="grid">
      @foreach($services as $s)
        <div class="card">
          <div class="ic"><i class="fas {{ $s->icon }}"></i></div>
          <h3>{{ $s->title }}</h3>
          <p>{{ $s->description }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
@endif

@if($projects->count())
<section id="work">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">Selected work</div>
      <h2>Projects</h2>
      <p>Enterprise systems delivered for government ministries, NGOs and private organisations.</p>
    </div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr));">
      @foreach($projects as $p)
        <a href="{{ route('portfolio.project', $p) }}" class="proj-card">
          <div class="tag-row">
            @foreach(array_slice($p->tags ?? [], 0, 3) as $t)<span class="tag">{{ $t }}</span>@endforeach
          </div>
          <h3>{{ $p->title }}</h3>
          <p>{{ $p->description }}</p>
          <span class="link">View case study <i class="fas fa-arrow-right"></i></span>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

@if($skills->count())
<section class="band-surface" id="skills">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">Toolbox</div>
      <h2>Skills</h2>
    </div>
    <div class="skill-groups">
      @foreach($skills as $category => $items)
        <div class="skill-group">
          <h4>{{ $category }}</h4>
          <ul>@foreach($items as $i)<li>{{ $i->name }}</li>@endforeach</ul>
        </div>
      @endforeach
    </div>
  </div>
</section>
@endif

@if($experience->count())
<section id="experience">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">Career</div>
      <h2>Experience</h2>
    </div>
    <div class="timeline" style="max-width:760px;margin:0 auto;">
      @foreach($experience as $e)
        <div class="tl-item">
          <div class="period">{{ $e->start_date?->format('Y') }} – {{ $e->end_date?->format('Y') ?? 'Present' }}</div>
          <h3>{{ $e->role }}</h3>
          <div class="org">{{ $e->company }}</div>
          <p>{{ $e->description }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
@endif

@if($research)
<section class="band-surface" id="research">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">Research</div>
      <h2>Graduate research</h2>
    </div>
    <div class="feature-box" style="max-width:760px;margin:0 auto;">
      <div class="sub">{{ $research['institution'] ?? '' }}</div>
      <h3>{{ $research['title'] ?? '' }}</h3>
      <p style="font-size:12.5px;color:var(--tx3);margin-bottom:14px;">{{ $research['supervisor'] ?? '' }}</p>
      <p>{{ $research['body'] ?? '' }}</p>
      <div class="pill-row">
        @foreach($research['areas'] ?? [] as $a)<span class="pill">{{ $a }}</span>@endforeach
      </div>
    </div>
  </div>
</section>
@endif

@if(count($products))
<section id="products">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">Products</div>
      <h2>What I've built for myself</h2>
    </div>
    <div class="grid">
      @foreach($products as $p)
        <div class="card">
          <div class="ic"><i class="fas {{ $p['icon'] ?? 'fa-star' }}"></i></div>
          <h3>{{ $p['name'] }}</h3>
          <p style="margin-bottom:6px;">{{ $p['tagline'] }}</p>
          <p>{{ $p['body'] }}</p>
          @if(!empty($p['link']))<a href="{{ $p['link'] }}" target="_blank" rel="noopener" class="link" style="font-size:12.5px;font-weight:600;color:var(--pri);display:block;margin-top:10px;">Visit <i class="fas fa-arrow-up-right-from-square"></i></a>@endif
        </div>
      @endforeach
    </div>
  </div>
</section>
@endif

@if($education->count())
<section class="band-surface" id="education">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">Education</div>
      <h2>Academic background</h2>
    </div>
    <div class="timeline" style="max-width:760px;margin:0 auto;">
      @foreach($education as $ed)
        <div class="tl-item">
          <div class="period">{{ $ed->start_date?->format('Y') }} – {{ $ed->end_date?->format('Y') ?? 'Present' }}</div>
          <h3>{{ $ed->degree }}</h3>
          <div class="org">{{ $ed->institution }}</div>
          <p>{{ $ed->description }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
@endif

@if(count($languages))
<section>
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">Languages</div>
      <h2>Spoken languages</h2>
    </div>
    <div class="pill-row" style="justify-content:center;">
      @foreach($languages as $l)<span class="pill">{{ $l['name'] }} — {{ $l['level'] }}</span>@endforeach
    </div>
  </div>
</section>
@endif

<section class="band-surface" id="contact">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">Contact</div>
      <h2>Let's build something together</h2>
    </div>

    @if(session('success'))
      <div class="alert-success" style="max-width:600px;margin:0 auto 24px;">{{ session('success') }}</div>
    @endif

    <div class="contact-grid">
      <div class="contact-info">
        @foreach(($contact['emails'] ?? []) as $email)
          <div class="item"><h4>Email</h4><a href="mailto:{{ $email }}">{{ $email }}</a></div>
        @endforeach
        @foreach(($contact['phones'] ?? []) as $phone)
          <div class="item"><h4>Phone</h4><a href="tel:{{ $phone }}">{{ $phone }}</a></div>
        @endforeach
        @if(!empty($contact['github']))
          <div class="item"><h4>GitHub</h4><a href="{{ $contact['github'] }}" target="_blank" rel="noopener">{{ $contact['github_label'] }}</a></div>
        @endif
        @if(!empty($contact['youtube']))
          <div class="item"><h4>YouTube</h4><a href="{{ $contact['youtube'] }}" target="_blank" rel="noopener">{{ $contact['youtube_label'] }}</a></div>
        @endif
      </div>

      <form class="contact-form" method="POST" action="{{ route('contact.store') }}">
        @csrf
        <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off">
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
        <div>
          <label for="subject">Subject</label>
          <input type="text" id="subject" name="subject" value="{{ old('subject') }}">
        </div>
        <div>
          <label for="message">Message</label>
          <textarea id="message" name="message" required>{{ old('message') }}</textarea>
          @error('message')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn gold lg">Send message</button>
      </form>
    </div>
  </div>
</section>

@endsection
