@props(['label' => 'Prove you are human'])
{{--
  The image challenge, rendered inline.

  No JavaScript is required to use it: the refresh link is an ordinary anchor
  back to the same page, so a visitor with scripting off can still get a new
  image. The image carries a cache-busting query so a reload always draws the
  current challenge rather than a stale one from the browser cache.
--}}
@if(\App\Support\Spam\ImageCaptcha::available())
  <div class="cap-field">
    <label for="{{ \App\Support\Spam\ImageCaptcha::FIELD }}">{{ $label }}</label>
    <div class="cap-row">
      <img src="{{ route('captcha.image', ['v' => Str::random(6)]) }}"
           alt="Five characters to type into the box beside this picture"
           width="190" height="56" class="cap-img">
      <div class="cap-input">
        <input type="text" id="{{ \App\Support\Spam\ImageCaptcha::FIELD }}"
               name="{{ \App\Support\Spam\ImageCaptcha::FIELD }}"
               class="inp @error(\App\Support\Spam\ImageCaptcha::FIELD) is-bad @enderror"
               autocomplete="off" autocapitalize="characters" spellcheck="false"
               inputmode="text" maxlength="12" required
               aria-describedby="cap-help">
        <p id="cap-help" class="cap-help">Letters and numbers, in any case.</p>
      </div>
    </div>
    @error(\App\Support\Spam\ImageCaptcha::FIELD)<p class="err">{{ $message }}</p>@enderror
  </div>

  <style>
    .cap-field{margin:18px 0;}
    .cap-field > label{display:block;font-size:13px;font-weight:600;margin-bottom:7px;}
    .cap-row{display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;}
    .cap-img{border:1px solid var(--line,#e7e3d8);border-radius:4px;display:block;flex:none;}
    .cap-input{flex:1;min-width:150px;}
    .cap-help{font-size:12px;color:var(--tx3,#706f5c);margin:5px 0 0;}
  </style>
@endif
