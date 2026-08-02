{{--
  Muhindo's signature over a ruled line, for documents he issues.

  Lives in resources/, NOT public/. DomPDF loads it off the filesystem, so it
  never needs to be web-reachable — and a signature at a guessable public URL is
  one anyone can download and paste onto a document of their own. dompdf's
  chroot is base_path(), so resources/ is readable.

  resources/ rather than storage/: storage/app/.gitignore ignores everything in
  it, so the asset would not have shipped and a fresh deployment would have
  produced unsigned documents.

  A path rather than a base64 data URI: inlining a 17KB PNG is ~23KB of string
  in every certificate and invoice for no gain.

  Renders the line and the name even when the image is missing, so a deployment
  that has not shipped the asset produces a document with an unsigned line
  rather than a broken image or a fatal.

  @param string|null $role   Line under the name. Defaults to his title.
  @param int|null    $width  Signature width in points.
--}}
@php
  $signatureFile = resource_path('brand/signature.png');
  $hasSignature = is_file($signatureFile);
  $signatureWidth = $width ?? 150;
@endphp

<div class="sig-block">
  @if($hasSignature)
    <img class="sig-ink" src="{{ $signatureFile }}" alt="" style="width:{{ $signatureWidth }}px;">
  @else
    <div class="sig-gap"></div>
  @endif

  <div class="sig-rule"></div>
  <div class="sig-name">Muhindo Mubaraka</div>
  <div class="sig-role">{{ $role ?? 'Software engineer & instructor' }}</div>
</div>
