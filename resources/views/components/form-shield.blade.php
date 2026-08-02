@props(['id' => 'f'])
{{--
  Spam protection for a public form. Nothing for the visitor to solve, nothing
  loaded from a third party.

  The hiding is inline, not a class. It was `.hp-field`, which is defined in the
  marketing layout — so on the auth pages, which use a different layout, the
  honeypot rendered fully visible with the label "Leave this field empty". Any
  real person who typed in it would have been silently discarded. A component
  that can be dropped into any form has to carry its own styling.

  Positioned off-screen rather than display:none, because some bots skip fields
  that are display:none — and aria-hidden with tabindex -1 so a screen reader or
  keyboard never reaches it.
--}}
<div aria-hidden="true"
     style="position:absolute !important;left:-9999px !important;top:-9999px !important;
            width:1px !important;height:1px !important;overflow:hidden !important;">
  <label for="shield-{{ $id }}">Leave this field empty</label>
  <input type="text" id="shield-{{ $id }}" name="{{ \App\Support\Spam\FormShield::HONEYPOT }}"
         tabindex="-1" autocomplete="off" value="">
</div>
<input type="hidden" name="{{ \App\Support\Spam\FormShield::TIMESTAMP }}"
       value="{{ \App\Support\Spam\FormShield::stamp() }}">
