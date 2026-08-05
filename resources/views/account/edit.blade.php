@extends('layouts.admin')
@section('title', 'Your account')

@php
  /** Panels double as the in-page nav and the section list, so the two can't drift. */
  $panels = [
      ['id' => 'details', 'label' => 'Your details', 'icon' => 'fa-user'],
      ['id' => 'account-type', 'label' => 'Account type', 'icon' => 'fa-toggle-on', 'skip' => $user->isAdmin()],
      ['id' => 'security', 'label' => 'Security', 'icon' => 'fa-lock'],
  ];
  $panels = array_values(array_filter($panels, fn ($p) => ! ($p['skip'] ?? false)));

  $types = [
      ['value' => 'student', 'icon' => 'fa-graduation-cap', 'title' => 'Learn from Muhindo',
       'desc' => 'Take his courses, track your progress and earn a verifiable certificate.'],
      ['value' => 'client', 'icon' => 'fa-handshake', 'title' => 'Hire Muhindo for a project',
       'desc' => 'Send him a brief, then follow the build in your own client portal.'],
      ['value' => 'both', 'icon' => 'fa-layer-group', 'title' => 'Both',
       'desc' => 'One account for learning and for the work you commission. Switch any time.'],
  ];
@endphp

@section('content')

<div class="tb-page-header">
  <div>
    <h1>Your account</h1>
    <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Your account</div>
  </div>
</div>

<div class="acct-wrap">

  {{-- Plain anchors, so the section list works with the keyboard, with JS off,
       and with the browser's own find-in-page. --}}
  <nav class="acct-nav" aria-label="Account sections">
    @foreach($panels as $i => $p)
      <a href="#{{ $p['id'] }}" class="{{ $i === 0 ? 'on' : '' }}"><i class="fas {{ $p['icon'] }}"></i> {{ $p['label'] }}</a>
    @endforeach
  </nav>

  <div>

    {{-- Your details --}}
    <section class="acct-panel tb-card" id="details" aria-labelledby="details-h">
      <div class="tb-card-header">
        <div>
          <h2 class="tb-card-title" id="details-h">Your details</h2>
          <p>Your name is what shows on certificates and on anything I send you.</p>
        </div>
      </div>

      <div class="tb-card-body">
        <div class="acct-id" style="margin-bottom:18px;">
          <div class="acct-avatar" aria-hidden="true">
            @if($user->avatar_url)
              <img src="{{ $user->avatar_url }}" alt="">
            @else
              {{ $user->initials }}
            @endif
          </div>
          <div class="acct-id-meta">
            <div class="acct-id-name">{{ $user->name }}</div>
            <div class="acct-id-sub">{{ $user->email }} · {{ $user->accountTypeLabel() }}</div>
          </div>
          <div class="acct-id-actions">
            <form method="POST" action="{{ route('account.avatar') }}" enctype="multipart/form-data"
                  id="avatar-form" style="display:contents;">
              @csrf
              {{-- The input keeps its native focus and keyboard behaviour; the label is
                   only its visible surface, and submits on choose so there's no
                   orphan "now press upload" step. --}}
              <input type="file" name="avatar" id="avatar" class="acct-file"
                     accept="image/jpeg,image/png,image/webp,image/gif"
                     aria-describedby="avatar-help"
                     onchange="this.form.requestSubmit()">
              <label for="avatar" class="btn-tb btn-tb-ghost btn-tb-sm acct-file-label">
                <i class="fas fa-camera"></i> {{ $user->avatar ? 'Change photo' : 'Add photo' }}
              </label>
            </form>
            @if($user->avatar)
              <form method="POST" action="{{ route('account.avatar.remove') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm"><i class="fas fa-trash"></i> Remove</button>
              </form>
            @endif
          </div>
        </div>
        <p id="avatar-help" class="acct-hint" style="margin:-12px 0 18px;">JPG, PNG, WEBP or GIF, up to 10&nbsp;MB. Saved as soon as you choose a file.</p>
        @error('avatar', 'avatar')<p class="tb-field-error" role="alert" style="margin:-12px 0 16px;">{{ $message }}</p>@enderror

        <form method="POST" action="{{ route('account.update') }}" id="details-form">
          @csrf

          @if($errors->profile->any())
            <div class="acct-summary" role="alert">
              <strong>Your details weren't saved:</strong>
              <ul>@foreach($errors->profile->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
          @endif

          <div class="tb-form-grid">
            <div class="tb-form-group">
              <label class="tb-label" for="name">Full name</label>
              <input class="tb-input" type="text" id="name" name="name" autocomplete="name" required
                     value="{{ old('name', $user->name) }}"
                     @error('name', 'profile') aria-invalid="true" aria-describedby="name-error" @enderror>
              @error('name', 'profile')<p class="tb-field-error" id="name-error">{{ $message }}</p>@enderror
            </div>

            <div class="tb-form-group">
              <label class="tb-label" for="email">Email address</label>
              <input class="tb-input" type="email" id="email" name="email" autocomplete="email" required
                     value="{{ old('email', $user->email) }}"
                     aria-describedby="email-help @error('email', 'profile') email-error @enderror"
                     @error('email', 'profile') aria-invalid="true" @enderror>
              @error('email', 'profile')<p class="tb-field-error" id="email-error">{{ $message }}</p>@enderror
              <p class="acct-hint" id="email-help">You sign in with this address.</p>
            </div>

            <div class="tb-form-group">
              <label class="tb-label" for="phone">Phone <span style="text-transform:none;letter-spacing:0;">(optional)</span></label>
              <input class="tb-input" type="tel" id="phone" name="phone" autocomplete="tel"
                     value="{{ old('phone', $user->phone) }}"
                     aria-describedby="phone-help @error('phone', 'profile') phone-error @enderror"
                     @error('phone', 'profile') aria-invalid="true" @enderror>
              @error('phone', 'profile')<p class="tb-field-error" id="phone-error">{{ $message }}</p>@enderror
              <p class="acct-hint" id="phone-help">Used only if I need to reach you about your courses or projects.</p>
            </div>

            <div class="tb-form-group">
              <label class="tb-label" for="bio">About you <span style="text-transform:none;letter-spacing:0;">(optional)</span></label>
              <textarea class="tb-textarea" id="bio" name="bio" rows="3" maxlength="500"
                        aria-describedby="bio-help @error('bio', 'profile') bio-error @enderror"
                        @error('bio', 'profile') aria-invalid="true" @enderror>{{ old('bio', $user->bio) }}</textarea>
              @error('bio', 'profile')<p class="tb-field-error" id="bio-error">{{ $message }}</p>@enderror
              <p class="acct-hint" id="bio-help">A short line about yourself, up to 500 characters.</p>
            </div>
          </div>
        </form>
      </div>

      <div class="tb-card-footer" style="display:flex;justify-content:flex-end;">
        {{-- Outside the <form> element but bound to it, so the footer can sit in the
             card's own footer band without nesting forms. --}}
        <button type="submit" form="details-form" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save details</button>
      </div>
    </section>

    {{-- Account type --}}
    @unless($user->isAdmin())
    <section class="acct-panel tb-card" id="account-type" aria-labelledby="type-h">
      <div class="tb-card-header">
        <div>
          <h2 class="tb-card-title" id="type-h">Account type</h2>
          <p>What you use this account for. Change it any time, adding an option unlocks the matching menu straight away.</p>
        </div>
      </div>

      <form method="POST" action="{{ route('account.type') }}">
        @csrf
        <div class="tb-card-body">
          @if($errors->accountType->any())
            <div class="acct-summary" role="alert">
              <ul>@foreach($errors->accountType->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
          @endif

          <fieldset style="border:none;">
            <legend class="sr-only">Choose what you use this account for</legend>
            <div class="acct-types">
              @foreach($types as $t)
                <label class="acct-type">
                  <input type="radio" name="account_type" value="{{ $t['value'] }}"
                         @checked(old('account_type', $currentType) === $t['value'])>
                  <span class="t"><i class="fas {{ $t['icon'] }}" aria-hidden="true"></i> {{ $t['title'] }}</span>
                  <span class="d">{{ $t['desc'] }}</span>
                </label>
              @endforeach
            </div>
          </fieldset>

          @if($enrollmentCount > 0 || $projectCount > 0)
            <p class="acct-hint" style="margin-top:12px;">
              <i class="fas fa-circle-info" aria-hidden="true"></i>
              Access you're already using is never removed:
              @if($enrollmentCount > 0)
                you're enrolled in {{ $enrollmentCount }} {{ Str::plural('course', $enrollmentCount) }}@if($projectCount > 0), and @endif
              @endif
              @if($projectCount > 0)
                you have {{ $projectCount }} {{ Str::plural('project', $projectCount) }} on your account
              @endif,
              so that side stays available whatever you pick here.
            </p>
          @endif
        </div>

        <div class="tb-card-footer" style="display:flex;justify-content:flex-end;">
          <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save account type</button>
        </div>
      </form>
    </section>
    @endunless

    {{-- Security --}}
    <section class="acct-panel tb-card" id="security" aria-labelledby="security-h">
      <div class="tb-card-header">
        <div>
          <h2 class="tb-card-title" id="security-h">Security</h2>
          <p>Use a password you don't use anywhere else. You stay signed in on this device until you sign out.</p>
        </div>
      </div>

      <form method="POST" action="{{ route('password.update') }}">
        @csrf @method('PUT')
        <div class="tb-card-body">
          @if(session('status') === 'password-updated')
            <div class="tb-alert tb-alert-success" role="status" style="margin-bottom:14px;">
              <i class="fas fa-circle-check" aria-hidden="true"></i><span>Your password has been changed.</span>
            </div>
          @endif

          @if($errors->updatePassword->any())
            <div class="acct-summary" role="alert">
              <strong>Your password wasn't changed:</strong>
              <ul>@foreach($errors->updatePassword->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
          @endif

          <div class="tb-form-grid">
            <div class="tb-form-group full">
              <label class="tb-label" for="current_password">Current password</label>
              <input class="tb-input" type="password" id="current_password" name="current_password"
                     autocomplete="current-password" required
                     @error('current_password', 'updatePassword') aria-invalid="true" aria-describedby="current-password-error" @enderror>
              @error('current_password', 'updatePassword')<p class="tb-field-error" id="current-password-error">{{ $message }}</p>@enderror
            </div>

            <div class="tb-form-group">
              <label class="tb-label" for="password">New password</label>
              <input class="tb-input" type="password" id="password" name="password"
                     autocomplete="new-password" required minlength="8"
                     aria-describedby="password-help @error('password', 'updatePassword') password-error @enderror"
                     @error('password', 'updatePassword') aria-invalid="true" @enderror>
              @error('password', 'updatePassword')<p class="tb-field-error" id="password-error">{{ $message }}</p>@enderror
              <p class="acct-hint" id="password-help">At least 8 characters.</p>
            </div>

            <div class="tb-form-group">
              <label class="tb-label" for="password_confirmation">Confirm new password</label>
              <input class="tb-input" type="password" id="password_confirmation" name="password_confirmation"
                     autocomplete="new-password" required minlength="8">
            </div>
          </div>
        </div>

        <div class="tb-card-footer" style="display:flex;justify-content:flex-end;">
          <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-key"></i> Change password</button>
        </div>
      </form>
    </section>

  </div>
</div>

@endsection

@push('scripts')
<script>
  /* Highlights the section you're reading in the side nav. Progressive
     enhancement only. The anchors already work without it. */
  (function () {
    const nav = document.querySelector('.acct-nav');
    if (!nav || !('IntersectionObserver' in window)) return;

    const links = new Map([...nav.querySelectorAll('a[href^="#"]')].map(a => [a.getAttribute('href').slice(1), a]));
    const observer = new IntersectionObserver((entries) => {
      const visible = entries.filter(e => e.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
      if (!visible) return;
      links.forEach(a => a.classList.remove('on'));
      links.get(visible.target.id)?.classList.add('on');
    }, { rootMargin: '-64px 0px -60% 0px' });

    links.forEach((_, id) => {
      const section = document.getElementById(id);
      if (section) observer.observe(section);
    });

    // wire:navigate keeps this JS context alive across body swaps, so the
    // observer must be torn down or it keeps pointing at detached nodes.
    document.addEventListener('livewire:navigating', () => observer.disconnect(), { once: true });
  })();
</script>
@endpush
