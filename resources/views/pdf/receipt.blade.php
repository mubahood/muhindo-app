<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><style>
  body { font-family: DejaVu Sans, sans-serif; color: #141a26; font-size: 12px; margin: 32px; }
  .brand { font-size: 16px; font-weight: bold; color: #0b1f3a; }
  .muted { color: #5b6270; font-size: 10px; }
  h2 { font-size: 18px; letter-spacing: 1px; margin: 14px 0 4px; color: #0b1f3a; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th { text-align: left; color: #5b6270; width: 40%; padding: 6px 0; font-weight: normal; }
  td { padding: 6px 0; }
  .amt { font-size: 22px; font-weight: bold; color: #0b1f3a; margin-top: 16px; }
  .foot { margin-top: 40px; font-size: 10px; color: #93927e; }
</style></head><body>
  @php
    $money = fn ($v) => $payment->invoice->currency.' '.number_format((float) $v, 2);
    $billTo = $payment->invoice->billable?->name ?? 'Customer';
  @endphp
  <div class="brand">Muhindo Mubaraka</div>
  <div class="muted">Kampala, Uganda</div>
  <h2>PAYMENT RECEIPT</h2>
  <div class="muted">{{ $payment->created_at?->format('d M Y H:i') }}</div>

  <table><tbody>
    <tr><th>Invoice</th><td>{{ $payment->invoice->invoice_no }}</td></tr>
    <tr><th>Paid by</th><td>{{ $billTo }}</td></tr>
    <tr><th>Method</th><td>{{ $payment->method->label() }}</td></tr>
    @if($payment->reference)<tr><th>Reference</th><td>{{ $payment->reference }}</td></tr>@endif
    <tr><th>Received by</th><td>{{ $payment->receivedBy?->name ?? '—' }}</td></tr>
    <tr><th>Balance after</th><td>{{ $money($payment->balance_after) }}</td></tr>
  </tbody></table>

  <div class="amt">{{ $money($payment->amount) }}</div>
  <div class="foot">This is a computer-generated receipt.</div>
</body></html>
