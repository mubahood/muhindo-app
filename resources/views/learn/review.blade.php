@extends('layouts.learn')
@section('title', 'Review | ' . $course->title)
@section('page_title', 'Course review')

@section('learn_content')
<h1>{{ $review ? 'Edit your review' : 'Rate this course' }}</h1>

<div class="card" style="max-width:520px;">
  <form method="POST" action="{{ route('learn.review.store', $course) }}">
    @csrf
    <label class="muted" style="display:block;margin-bottom:6px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">Rating</label>
    <div style="display:flex;gap:16px;margin-bottom:18px;">
      @foreach([1, 2, 3, 4, 5] as $value)
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
          <input type="radio" name="rating" value="{{ $value }}" {{ old('rating', $review?->rating) == $value ? 'checked' : '' }} required>
          {{ $value }} <i class="fas fa-star" style="color:var(--gold);"></i>
        </label>
      @endforeach
    </div>
    <label class="muted" style="display:block;margin-bottom:6px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">Your review (optional)</label>
    <textarea name="body" style="width:100%;padding:10px 12px;border:1px solid var(--line);min-height:120px;font-family:var(--font);">{{ old('body', $review?->body) }}</textarea>
    <button type="submit" class="btn gold" style="margin-top:16px;"><i class="fas fa-star"></i> {{ $review ? 'Update review' : 'Submit review' }}</button>
  </form>
</div>
@endsection
