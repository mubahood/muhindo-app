@extends('layouts.marketing')
@section('title', 'Contact — Muhindo Mubaraka')
@section('desc', "Let's build something together.")

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">CONTACT</span>
  <div class="wrap">
    <div class="eyebrow">Contact</div>
    <h1>Let's build something together</h1>
    <p>Reach out about a project, a role, or just to say hi. Have a specific project brief already? <a href="{{ route('start-a-project') }}" wire:navigate style="color:var(--gold-d);font-weight:600;">Start a project →</a></p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">

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

        @if(count($languages))
          <div class="item">
            <h4>Languages</h4>
            <div class="pill-row">
              @foreach($languages as $l)<span class="pill">{{ $l['name'] }}</span>@endforeach
            </div>
          </div>
        @endif
      </div>

      <form class="contact-form" method="POST" action="{{ route('contact.store') }}">
        @csrf
      <x-form-shield />
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
