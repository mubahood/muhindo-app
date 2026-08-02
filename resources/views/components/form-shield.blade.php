@props(['id' => 'f'])
{{--
  Spam protection for a public form. Two hidden inputs, no puzzle for the
  visitor to solve, nothing loaded from a third party.

  The honeypot is positioned off-screen by .hp-field rather than hidden with
  display:none — some bots skip fields that are display:none — and it is
  aria-hidden with tabindex -1 so a screen reader or keyboard never reaches it.
--}}
<div class="hp-field" aria-hidden="true">
  <label for="shield-{{ $id }}">Leave this field empty</label>
  <input type="text" id="shield-{{ $id }}" name="{{ \App\Support\Spam\FormShield::HONEYPOT }}"
         tabindex="-1" autocomplete="off" value="">
</div>
<input type="hidden" name="{{ \App\Support\Spam\FormShield::TIMESTAMP }}"
       value="{{ \App\Support\Spam\FormShield::stamp() }}">
