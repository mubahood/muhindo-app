<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><style>
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; color: #141a26; font-size: 12px; margin: 32px; }
  .head { display: flex; justify-content: space-between; border-bottom: 2px solid #0b1f3a; padding-bottom: 10px; }
  .brand { font-size: 18px; font-weight: bold; color: #0b1f3a; }
  .muted { color: #5b6270; font-size: 10px; }
  h2 { font-size: 20px; margin: 0; letter-spacing: 1px; color: #0b1f3a; }
  table { width: 100%; border-collapse: collapse; margin-top: 18px; }
  th, td { text-align: left; padding: 7px 6px; border-bottom: 1px solid #e7e3d8; }
  td.r, th.r { text-align: right; }
  tfoot td { border: none; padding: 3px 6px; }
  tfoot .tot { font-weight: bold; font-size: 14px; color: #0b1f3a; border-top: 2px solid #0b1f3a; }
  .foot { margin-top: 40px; font-size: 10px; color: #93927e; border-top: 1px solid #e7e3d8; padding-top: 10px; }
  .badge { display:inline-block; font-size:10px; font-weight:bold; text-transform:uppercase; padding:3px 8px; background:#eef1f6; color:#0b1f3a; }
  .sig-block { width: 190px; margin-left: auto; text-align: center; }
  .sig-ink { display: block; margin: 0 auto -6px; }
  .sig-gap { height: 34px; }
  .sig-rule { border-top: 1px solid #0b1f3a; padding-top: 5px; }
  .sig-name { font-size: 10.5px; font-weight: bold; color: #0b1f3a; }
  .sig-role { font-size: 9px; color: #5b6270; }
</style></head><body>
  @php
    $money = fn ($v) => $invoice->currency.' '.number_format((float) $v, 2);
    $billToName = $invoice->billable instanceof \App\Models\Client ? $invoice->billable->name : ($invoice->billable->name ?? 'Customer');
    $billToSub = $invoice->billable instanceof \App\Models\Client ? $invoice->billable->company : $invoice->billable->email ?? null;
  @endphp
  <div class="head">
    <div><div class="brand">Muhindo Mubaraka</div>
      <div class="muted">Kampala, Uganda</div></div>
    <div style="text-align:right;"><h2>INVOICE</h2>
      <div class="muted">{{ $invoice->invoice_no }}</div>
      <div class="muted">{{ $invoice->issued_at?->format('d M Y') }}</div>
      <div class="badge">{{ $invoice->status->label() }}</div></div>
  </div>

  <div style="margin-top:16px;"><strong>Bill to:</strong> {{ $billToName }}
    @if($billToSub)<span class="muted">({{ $billToSub }})</span>@endif</div>

  <table>
    <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Unit</th><th class="r">Amount</th></tr></thead>
    <tbody>
      @foreach($invoice->items as $it)
        <tr><td>{{ $it->description }}</td><td class="r">{{ $it->quantity }}</td>
          <td class="r">{{ $money($it->unit_price) }}</td><td class="r">{{ $money($it->line_total) }}</td></tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="3" class="r">Subtotal</td><td class="r">{{ $money($invoice->subtotal) }}</td></tr>
      @if(bccomp((string) $invoice->tax_total, '0', 2) > 0)<tr><td colspan="3" class="r">Tax</td><td class="r">{{ $money($invoice->tax_total) }}</td></tr>@endif
      @if(bccomp((string) $invoice->discount, '0', 2) > 0)<tr><td colspan="3" class="r">Discount</td><td class="r">- {{ $money($invoice->discount) }}</td></tr>@endif
      <tr><td colspan="3" class="r tot">Total</td><td class="r tot">{{ $money($invoice->total) }}</td></tr>
      <tr><td colspan="3" class="r">Paid</td><td class="r">{{ $money($invoice->amount_paid) }}</td></tr>
      <tr><td colspan="3" class="r">Balance due</td><td class="r">{{ $money($invoice->balance) }}</td></tr>
    </tfoot>
  </table>

  {{-- An invoice is issued by the business, so it is signed for the business.
       A receipt is deliberately NOT signed this way: it names a specific
       "Received by" person who may not be Muhindo, and stamping his signature
       on somebody else's act of receiving money would misstate what happened. --}}
  <table style="width:100%; margin-top:34px; border:0;">
    <tr>
      <td style="border:0; padding:0; vertical-align:bottom; font-size:10px; color:#5b6270;">
        Thank you for your business.
      </td>
      <td style="border:0; padding:0; width:210px;">
        @include('pdf.partials.signature', ['role' => 'For and on behalf of the business'])
      </td>
    </tr>
  </table>
</body></html>
