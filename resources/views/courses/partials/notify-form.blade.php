{{--
  The waitlist form.

  Three fields and no account. The person this catches read the whole sales
  page and decided yes; asking them to register first would lose exactly them,
  and a name plus a WhatsApp number is worth more here than an account is.

  WhatsApp before email deliberately: this audience answers WhatsApp.
--}}
<div class="notify" id="notify">
  @if(session('success'))
    <p class="notify-done"><i class="fas fa-circle-check"></i> {{ session('success') }}</p>
  @else
    <p class="notify-lead">
      This course is being finished now. Leave your name and I will message you
      the day it opens, before it is announced anywhere else.
    </p>

    <form method="POST" action="{{ route('courses.notify', $course) }}" class="notify-form">
      @csrf
      <x-form-shield :id="'notify-'.$course->id" />

      <label for="nf-name" class="sr-only">Your name</label>
      <input type="text" id="nf-name" name="name" required maxlength="120"
             value="{{ old('name', auth()->user()?->name) }}"
             placeholder="Your name" autocomplete="name"
             @error('name') aria-invalid="true" @enderror>
      @error('name')<span class="notify-err">{{ $message }}</span>@enderror

      <label for="nf-whatsapp" class="sr-only">WhatsApp number</label>
      <input type="tel" id="nf-whatsapp" name="whatsapp" required maxlength="32"
             value="{{ old('whatsapp', auth()->user()?->phone) }}"
             placeholder="WhatsApp number, e.g. 0783 204 665" autocomplete="tel"
             inputmode="tel"
             @error('whatsapp') aria-invalid="true" @enderror>
      @error('whatsapp')<span class="notify-err">{{ $message }}</span>@enderror

      <label for="nf-email" class="sr-only">Email address</label>
      <input type="email" id="nf-email" name="email" required maxlength="150"
             value="{{ old('email', auth()->user()?->email) }}"
             placeholder="Email address" autocomplete="email"
             @error('email') aria-invalid="true" @enderror>
      @error('email')<span class="notify-err">{{ $message }}</span>@enderror

      <x-captcha />
      @error(\App\Support\Spam\FormShield::TIMESTAMP)<span class="notify-err">{{ $message }}</span>@enderror

      <button type="submit" class="btn gold lg" style="width:100%;justify-content:center;">
        <i class="fas fa-bell"></i> Notify me when it opens
      </button>

      <p class="notify-fine">One message when it opens. Nothing else, ever.</p>
    </form>
  @endif
</div>
