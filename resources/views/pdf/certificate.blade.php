<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Certificate {{ $certificate->certificate_no }}</title>
<style>
  /*
    DomPDF, not a browser. No flexbox, no grid — the previous version laid the
    footer out with `display:flex`, which DomPDF silently ignored, stacking the
    certificate number, the issue date and the QR into a narrow column down the
    left instead of spreading them across the foot. Anything that has to sit
    side by side here is a table.
  */
  @page { margin: 0; }
  body { font-family: DejaVu Sans, sans-serif; color: #141a26; margin: 0; padding: 0; }

  .sheet { padding: 24px; }
  .frame { border: 2px solid #0b1f3a; padding: 4px; }
  .inner { border: 1px solid #c9b071; padding: 0; }
  .layout { width: 100%; border-collapse: collapse; }
  .layout td { padding: 0 40px; }
  .body-cell { height: 560px; vertical-align: middle; }
  .foot-cell { vertical-align: bottom; padding-bottom: 22px !important; }

  .crest { margin: 0 auto; border-collapse: collapse; }
  /* A line-height equal to the box clipped the mark's descenders. The cell
     centres it vertically instead. */
  .crest td { width: 50px; height: 50px; background: #0b1f3a; color: #b8933f;
    font-size: 16px; font-weight: bold; text-align: center; vertical-align: middle; }

  .eyebrow { font-size: 10px; letter-spacing: 4px; text-transform: uppercase;
    color: #9b7d33; font-weight: bold; margin: 13px 0 0; text-align: center; }
  .rule { width: 58px; border-top: 2px solid #c9b071; margin: 8px auto 0; }

  .lead { font-size: 11px; color: #5b6270; margin: 15px 0 0; text-align: center; }
  /* The recipient is the subject of the document, so their name is the largest
     thing on it. It used to be smaller than the issuer's. */
  .name { font-size: 29px; font-weight: bold; color: #0b1f3a; margin: 6px 0 0; text-align: center; }
  .name-rule { width: 290px; border-top: 1px solid #e2ddcd; margin: 9px auto 0; }
  .course { font-size: 16px; color: #141a26; margin: 11px 0 0; text-align: center; }
  .detail { font-size: 10.5px; color: #5b6270; margin: 8px 0 0; text-align: center; }

  .foot { width: 100%; margin-top: 24px; border-collapse: collapse; }
  .foot td { vertical-align: bottom; font-size: 9.5px; color: #5b6270; }
  .lbl { text-transform: uppercase; letter-spacing: 1.2px; font-size: 8px; color: #8a8f99; }
  .val { font-size: 10.5px; color: #141a26; font-weight: bold; }
  .sigline { border-top: 1px solid #0b1f3a; padding-top: 5px; width: 190px; margin: 0 auto; }
  .signame { font-size: 11px; font-weight: bold; color: #0b1f3a; }

  .qrcell { text-align: right; }
  .qrcell img { width: 72px; height: 72px; }
  /* The address is printed as well as encoded. A photocopy on a desk with no
     phone to hand still has to be checkable. */
  .qrnote { font-size: 7.5px; color: #8a8f99; margin-top: 3px; line-height: 1.45; }
</style>
</head>
<body>
<div class="sheet">
  <div class="frame">
    <div class="inner">
      <table class="layout">
      <tr><td class="body-cell">

      <table class="crest"><tr><td>MM</td></tr></table>

      <p class="eyebrow">Certificate of Completion</p>
      <div class="rule"></div>

      <p class="lead">This is to certify that</p>
      <div class="name">{{ $certificate->enrollment->user->name }}</div>
      <div class="name-rule"></div>

      <p class="lead">has successfully completed the course</p>
      <div class="course">{{ $certificate->enrollment->course->title }}</div>

      @if($certificate->enrollment->completed_at)
        <p class="detail">Completed on {{ $certificate->enrollment->completed_at->format('j F Y') }}</p>
      @endif

      </td></tr>
      <tr><td class="foot-cell">

      <table class="foot">
        <tr>
          <td style="width:34%;">
            <div class="lbl">Certificate number</div>
            <div class="val">{{ $certificate->certificate_no }}</div>
            <div class="lbl" style="margin-top:9px;">Date of issue</div>
            <div class="val">{{ $certificate->issued_at->format('j F Y') }}</div>
          </td>

          <td style="width:33%; text-align:center;">
            <div class="sigline">
              <div class="signame">Muhindo Mubaraka</div>
              <div>Software engineer &amp; instructor</div>
            </div>
          </td>

          <td style="width:33%;" class="qrcell">
            @isset($qrDataUri)
              <img src="{{ $qrDataUri }}" alt="">
            @endisset
            <div class="qrnote">
              Scan to verify, or check number<br>
              <b>{{ $certificate->certificate_no }}</b> at<br>{{ $lookupUrl }}
            </div>
          </td>
        </tr>
      </table>

      </td></tr>
      </table>
    </div>
  </div>
</div>
</body>
</html>
