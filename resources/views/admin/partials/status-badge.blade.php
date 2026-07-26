@php
  // Colour-coded registration status badge (square, matches td-admin.css).
  $map = [
    'draft'     => ['badge-neutral', 'Draft', 'fa-pen'],
    'pending'   => ['badge-info', 'Pending', 'fa-clock'],
    'verified'  => ['badge-active', 'Verified', 'fa-circle-check'],
    'suspended' => ['badge-warn', 'Suspended', 'fa-pause'],
    'revoked'   => ['badge-danger', 'Revoked', 'fa-ban'],
    'expired'   => ['badge-neutral', 'Expired', 'fa-hourglass-end'],
  ];
  [$cls, $label, $icon] = $map[$status] ?? ['badge-neutral', ucfirst($status), 'fa-circle'];
@endphp
<span class="badge-tb {{ $cls }}"><i class="fas {{ $icon }}" style="font-size:9px;"></i> {{ $label }}</span>
