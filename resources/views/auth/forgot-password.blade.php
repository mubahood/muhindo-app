@extends('layouts.auth')
@section('title', 'Reset password')

@section('form')
<div class="af-eyebrow">Account recovery</div>
<h2 class="af-title">Forgot your password?</h2>
<p class="af-sub">No problem. Enter your email and we'll send a secure link to set a new one.</p>

@if(session('status'))
<div class="a-alert ok"><i class="fas fa-paper-plane"></i><span>{{ session('status') }}</span></div>
@endif
@if($errors->any())
<div class="a-alert err"><i class="fas fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
@endif

<form method="POST" action="{{ route('password.email') }}">
  @csrf
  <div class="a-field">
    <label class="a-label" for="email">Email address</label>
    <div class="a-inwrap">
      <i class="fas fa-envelope"></i>
      <input class="a-input" id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus autocomplete="username">
    </div>
  </div>
  <button type="submit" class="a-btn"><i class="fas fa-paper-plane"></i> Email me a reset link</button>
</form>

<div style="text-align:center;"><a href="{{ url('/admin/login') }}" class="a-back"><i class="fas fa-arrow-left"></i> Back to sign in</a></div>
@endsection
