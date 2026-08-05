{{--
  Why the visitor is on this page.

  Someone bounced to sign-in mid-purchase needs to see that what they chose is
  still waiting, otherwise signing in feels like starting over, which is where
  people abandon. Covers both routes in: a single course they clicked "enrol"
  on, and a basket they were about to check out.
--}}
@php
  $cart = app(\App\Services\Shop\Cart::class);
  $cartCount = $cart->count();
@endphp

@if($intendedCourse ?? null)
<div class="a-course-ctx">
  <div class="thumb">
    @if($intendedCourse->coverUrl())
      <img src="{{ $intendedCourse->coverUrl() }}" alt="">
    @else
      <i class="fas fa-graduation-cap" aria-hidden="true"></i>
    @endif
  </div>
  <p>
    Continuing to <b>{{ $intendedCourse->title }}</b>
    <span class="ctx-sub">
      @if($intendedCourse->isFree())
        Free · you will be taken straight into the first lesson.
      @else
        {{ $intendedCourse->currency }} {{ number_format((float) $intendedCourse->price) }} · payment comes after this step.
      @endif
    </span>
  </p>
</div>
@elseif($cartCount > 0)
<div class="a-course-ctx">
  <div class="thumb"><i class="fas fa-basket-shopping" aria-hidden="true"></i></div>
  <p>
    Your basket is waiting, <b>{{ $cartCount }} {{ \Illuminate\Support\Str::plural('item', $cartCount) }}</b>
    <span class="ctx-sub">Nothing is lost. You will land back on checkout once you are in.</span>
  </p>
</div>
@endif
