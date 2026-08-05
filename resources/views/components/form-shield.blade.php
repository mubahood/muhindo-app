@props(['id' => 'f'])
{{--
  Spam protection for a public form. Nothing for the visitor to solve, nothing
  loaded from a third party.

  The hiding is inline, not a class. It was `.hp-field`, which is defined in the
  marketing layout, so on the auth pages, which use a different layout, the
  honeypot rendered fully visible with the label "Leave this field empty". Any
  real person who typed in it would have been silently discarded. A component
  that can be dropped into any form has to carry its own styling.

  Positioned off-screen rather than display:none, because some bots skip fields
  that are display:none, and aria-hidden with tabindex -1 so a screen reader or
  keyboard never reaches it.

  Off-screen is also why the field's NAME and these data attributes matter. A
  browser will happily autofill a field it cannot see, and the field used to be
  called `website`, which is exactly what Chrome, Safari and every password
  manager fill from a saved address card. People with password managers had the
  trap filled for them and were silently refused an account, a sign-in and a
  password reset. autocomplete="off" does not stop that on its own and is
  documented as ignored for address autofill, so each manager's own opt-out is
  set explicitly, and the name now matches nothing any of them look for.

  The label is deliberately plausible rather than "Leave this field empty": a
  bot that reads labels skips a field that announces itself as a trap.
--}}
<div aria-hidden="true"
     style="position:absolute !important;left:-9999px !important;top:-9999px !important;
            width:1px !important;height:1px !important;overflow:hidden !important;">
  <label for="shield-{{ $id }}">Referral note</label>
  <input type="text" id="shield-{{ $id }}" name="{{ \App\Support\Spam\FormShield::HONEYPOT }}"
         tabindex="-1" value=""
         autocomplete="off"
         data-1p-ignore
         data-lpignore="true"
         data-bwignore
         data-form-type="other"
         data-protonpass-ignore="true">
</div>
<input type="hidden" name="{{ \App\Support\Spam\FormShield::TIMESTAMP }}"
       value="{{ \App\Support\Spam\FormShield::stamp() }}">
