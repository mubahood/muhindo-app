@if($intendedCourse)
<div class="a-course-ctx">
  <div class="thumb">
    @if($intendedCourse->cover_image)
      <img src="{{ $intendedCourse->cover_image }}" alt="{{ $intendedCourse->coverAlt() }}">
    @else
      <i class="fas fa-graduation-cap" aria-hidden="true"></i>
    @endif
  </div>
  <p>To start <b>{{ $intendedCourse->title }}</b></p>
</div>
@endif
