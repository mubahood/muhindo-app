<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><style>
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; color: #141a26; margin: 0; padding: 60px; }
  .frame { border: 3px solid #0b1f3a; padding: 50px; text-align: center; }
  .badge { width: 60px; height: 60px; background: #0b1f3a; color: #b8933f; font-weight: bold; font-size: 20px;
    line-height: 60px; margin: 0 auto 20px; }
  .eyebrow { font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: #b8933f; font-weight: bold; }
  h1 { font-size: 30px; font-weight: normal; margin: 10px 0 30px; color: #0b1f3a; }
  .lead { font-size: 13px; color: #5b6270; margin-bottom: 6px; }
  .name { font-size: 26px; font-weight: bold; margin: 14px 0; color: #0b1f3a; }
  .course { font-size: 18px; margin: 14px 0 30px; color: #141a26; }
  .meta { display: flex; justify-content: space-between; margin-top: 50px; font-size: 11px; color: #5b6270; }
  .sig { border-top: 1px solid #d8d2c0; padding-top: 8px; width: 220px; }
</style></head><body>
  <div class="frame">
    <div class="badge">MM</div>
    <div class="eyebrow">Certificate of Completion</div>
    <h1>Muhindo Mubaraka</h1>
    <p class="lead">This certifies that</p>
    <div class="name">{{ $certificate->enrollment->user->name }}</div>
    <p class="lead">has successfully completed</p>
    <div class="course">{{ $certificate->enrollment->course->title }}</div>

    <div class="meta">
      <div class="sig">Certificate No.<br>{{ $certificate->certificate_no }}</div>
      <div class="sig">Issued<br>{{ $certificate->issued_at->format('d F Y') }}</div>
      @isset($qrDataUri)
        <div style="text-align:center;">
          <img src="{{ $qrDataUri }}" style="width:70px;height:70px;">
          <div style="font-size:9px;color:#5b6270;margin-top:4px;">Scan to verify</div>
        </div>
      @endisset
    </div>
  </div>
</body></html>
