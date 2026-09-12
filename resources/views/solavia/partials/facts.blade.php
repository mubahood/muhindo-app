{{-- The registered facts. Every value is selectable text, never an image,
     because a reviewer's next action is to copy the registration number into
     a form. --}}
@php $a = config('company.address'); @endphp
<table class="sv-facts">
  <tbody>
    <tr><th scope="row">Legal name</th><td>{{ config('company.name') }}</td></tr>
    <tr>
      <th scope="row">Registration number</th>
      <td><span class="sv-copyable">{{ config('company.registration_number') }}</span>
        <div class="muted" style="font-size:12.5px;margin-top:4px;">{{ config('company.registrar') }}</div></td>
    </tr>
    <tr><th scope="row">Incorporated</th><td>{{ config('company.incorporated_on') }}</td></tr>
    <tr><th scope="row">Business</th><td>{{ config('company.business') }} (ISIC {{ config('company.isic') }})</td></tr>
    <tr>
      <th scope="row">Registered address</th>
      <td>{{ $a['street'] }}<br>{{ $a['trading_centre'] }}, {{ $a['division'] }}<br>
          {{ $a['locality'] }}, {{ $a['region'] }}, {{ $a['country'] }}</td>
    </tr>
    <tr><th scope="row">Postal</th><td>{{ $a['po_box'] }}</td></tr>
    <tr>
      <th scope="row">Phone / WhatsApp</th>
      <td><a class="link" href="tel:{{ config('company.phone_e164') }}">{{ config('company.phone') }}</a></td>
    </tr>
    <tr>
      <th scope="row">Email</th>
      <td><a class="link" href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a></td>
    </tr>
    <tr><th scope="row">Managing Director</th><td>{{ config('company.leadership.0.name') }}</td></tr>
  </tbody>
</table>
