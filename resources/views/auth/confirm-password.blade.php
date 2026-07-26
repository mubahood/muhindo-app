@extends('layouts.auth')
@section('title', 'Confirm password')

@section('form')
<div class="af-eyebrow">Secure area</div>
<h2 class="af-title">Confirm your password</h2>
<p class="af-sub">For your security, please re-enter your password to continue.</p>

@if($errors->any())
<div class="a-alert err"><i class="fas fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
@endif

<form method="POST" action="{{ route('password.confirm') }}">
  @csrf
  <div class="a-field">
    <label class="a-label" for="password">Password</label>
    <div class="a-inwrap">
      <i class="fas fa-lock"></i>
      <input class="a-input" id="password" type="password" name="password" placeholder="••••••••" required autofocus autocomplete="current-password" style="padding-right:44px;">
      <button type="button" class="a-eye" data-eye="password"><i class="fas fa-eye"></i></button>
    </div>
  </div>
  <button type="submit" class="a-btn"><i class="fas fa-shield-halved"></i> Confirm</button>
</form>
@endsection
