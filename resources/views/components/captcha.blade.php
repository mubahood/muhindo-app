{{--
  The reCAPTCHA widget, on every public form.

  Renders nothing at all until the keys are configured, so the site is never
  showing a broken or unverifiable box. See App\Support\Spam\Captcha.

  Hiding is done with `.c-shell:empty` rather than an @if around the wrapper,
  so the surrounding form never inherits a stray gap when the widget is off.
--}}
@php $captchaOn = \App\Support\Spam\Captcha::enabled(); @endphp

@if($captchaOn)
  <div class="c-shell">
    {!! NoCaptcha::display(['data-theme' => 'light']) !!}
    @error(\App\Support\Spam\Captcha::FIELD)
      <p class="c-err"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>
    @enderror
  </div>

  @once
    @push('scripts')
      {!! NoCaptcha::renderJs() !!}
    @endpush

    @push('styles')
    <style>
      .c-shell{margin:0 0 16px;}
      /* Google's iframe is a fixed 304px wide and will not reflow. Below that
         the page would scroll sideways, so the widget is scaled down instead
         and its box shrunk to match — otherwise scaling leaves dead space. */
      .c-shell .g-recaptcha{transform-origin:0 0;}
      @media(max-width:360px){
        .c-shell .g-recaptcha{transform:scale(.86);}
        .c-shell{height:68px;}
      }
      .c-err{display:flex;align-items:center;gap:7px;margin-top:7px;
        font-size:12px;font-weight:500;color:#b91c1c;}
    </style>
    @endpush
  @endonce
@endif
