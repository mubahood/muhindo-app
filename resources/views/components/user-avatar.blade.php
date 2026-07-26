{{--
  Reusable user avatar component.
  Props: $user (User model), $size (sm|md|lg), $class (extra CSS classes)
--}}
@props(['user', 'size' => 'sm', 'class' => ''])

@php
  $sizes = [
    'xs' => 'width:22px;height:22px;font-size:.5rem;',
    'sm' => 'width:30px;height:30px;font-size:.6875rem;',
    'md' => 'width:44px;height:44px;font-size:.875rem;',
    'lg' => 'width:72px;height:72px;font-size:1.5rem;',
    'xl' => 'width:96px;height:96px;font-size:2rem;',
  ];
  $sizeStyle = $sizes[$size] ?? $sizes['sm'];
  $avatarUrl = $user->avatar_url ?? null;
  $initials  = $user->initials ?? strtoupper(substr($user->name ?? 'U', 0, 2));
@endphp

@if($avatarUrl)
  <img src="{{ $avatarUrl }}"
       alt="{{ $user->name }}"
       style="{{ $sizeStyle }} border-radius:50%;object-fit:cover;flex-shrink:0;display:block;"
       class="{{ $class }}"
       onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
  <div style="{{ $sizeStyle }} border-radius:50%;background:linear-gradient(135deg,var(--br),var(--ac));
               display:none;align-items:center;justify-content:center;
               font-weight:700;color:#fff;flex-shrink:0;"
       class="{{ $class }}">
    {{ $initials }}
  </div>
@else
  <div style="{{ $sizeStyle }} border-radius:50%;background:linear-gradient(135deg,var(--br),var(--ac));
               display:flex;align-items:center;justify-content:center;
               font-weight:700;color:#fff;flex-shrink:0;"
       class="{{ $class }}">
    {{ $initials }}
  </div>
@endif
