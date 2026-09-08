@extends('layouts.marketing')
@section('title', 'Refund and Cancellation Policy | SOLAVIA GROUP LIMITED')
@section('desc', 'How refunds and cancellations work for courses, e-books, templates, software tools and school licences sold by SOLAVIA GROUP LIMITED. What we refund, how to ask, and how long it takes.')

@push('styles')
<style>
  /* The company block. A policy a payment provider will read has to say who
     is bound by it, before it says anything else. */
  .legal-id{border:1px solid var(--line);background:var(--surface);padding:18px 20px;margin:0 0 26px;}
  .legal-id b{display:block;font-size:14px;font-weight:600;color:var(--tx);letter-spacing:.01em;}
  .legal-id span{display:block;font-size:12.5px;color:var(--tx2);line-height:1.65;margin-top:4px;}

  .legal-lede{font-size:14.5px;line-height:1.7;}

  /* The refund table is the part of this page anyone actually reads, so it is
     a real table: a customer scans it, a screen reader announces the pairing,
     and a payment provider can quote a row. */
  .refund-table{width:100%;border-collapse:collapse;margin:14px 0 8px;font-size:13.5px;}
  .refund-table caption{text-align:left;font-size:12.5px;color:var(--tx3);padding-bottom:9px;}
  .refund-table th{text-align:left;font-weight:600;font-size:11.5px;letter-spacing:.06em;
    text-transform:uppercase;color:var(--tx3);border-bottom:1px solid var(--line-2);padding:0 14px 9px 0;}
  .refund-table td{border-bottom:1px solid var(--line);padding:13px 14px 13px 0;
    color:var(--tx2);vertical-align:top;line-height:1.6;}
  .refund-table td:last-child,.refund-table th:last-child{padding-right:0;width:38%;}
  .refund-table tr:last-child td{border-bottom:none;}

  /* Green for money coming back, plain grey for money staying put. "No refund"
     is a normal outcome, not a warning, so it is not painted red. */
  .r-tag{display:inline-block;font-size:11.5px;font-weight:600;letter-spacing:.02em;
    padding:3px 9px;border:1px solid transparent;white-space:nowrap;}
  .r-yes{color:var(--ok);background:var(--ok-soft);border-color:#cfe6d7;}
  .r-no{color:var(--tx3);background:var(--surface-2);border-color:var(--line);}
  .r-note{display:block;font-size:12px;color:var(--tx3);margin-top:5px;line-height:1.55;}

  /* Under 640px a two-column table of sentences is unreadable, so each row
     becomes its own block with the header carried in as a label. */
  @media (max-width:640px){
    .refund-table,.refund-table tbody,.refund-table tr,.refund-table td{display:block;width:100%;}
    .refund-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);}
    .refund-table tr{border-bottom:1px solid var(--line);padding:14px 0;}
    .refund-table tr:last-child{border-bottom:none;}
    .refund-table td{border:none;padding:0;}
    .refund-table td:last-child{width:auto;margin-top:8px;}
    .refund-table td:first-child{color:var(--tx);font-weight:500;}
  }

  /* The three-step timeline in "How to request a refund". Numbered because the
     order matters and because a customer mid-problem is skimming. */
  .steps{counter-reset:step;margin:14px 0 0;padding:0;list-style:none;}
  .steps li{position:relative;padding-left:38px;margin:0 0 16px;color:var(--tx2);line-height:1.65;}
  .steps li::before{counter-increment:step;content:counter(step);position:absolute;left:0;top:-1px;
    width:25px;height:25px;border:1px solid var(--line-2);background:var(--surface);
    display:flex;align-items:center;justify-content:center;
    font-size:12px;font-weight:600;color:var(--pri);}
  .steps li strong{color:var(--tx);font-weight:600;}

  .legal-sign{border-top:1px solid var(--line);margin-top:34px;padding-top:22px;}
  .legal-sign .sig{font-size:15px;font-weight:600;color:var(--tx);margin:10px 0 2px;letter-spacing:.01em;}
  .legal-sign .role{font-size:12.5px;color:var(--tx3);}
</style>
@endpush

@section('content')
<section>
  <div class="wrap page">
    <h1>Refund and Cancellation Policy</h1>
    {{-- A fixed date, not date('F Y'). A policy whose "last updated" line moves
         on its own every month is telling a customer, and a payment provider,
         something that is not true. --}}
    <div class="updated">Effective 8 September 2026</div>

    <div class="legal-id">
      <b>SOLAVIA GROUP LIMITED</b>
      <span>
        Registration No. 80048169153974<br>
        Plot 2335, Buwambo-Katadde-Najjo Road, Nansana Municipality, Wakiso District, Uganda<br>
        P.O. Box 214231, Kampala &middot;
        <a class="link" href="tel:+256783204665">+256 783 204 665</a> &middot;
        <a class="link" href="mailto:solaviaug@gmail.com">solaviaug@gmail.com</a>
      </span>
    </div>

    <p class="legal-lede">This policy covers every product and service sold by SOLAVIA GROUP LIMITED through <a class="link" href="{{ url('/') }}">muhindomubaraka.com</a>, our mobile applications and our school management system. It is shown to customers before payment and published here on our website.</p>

    <h2>1. What we sell</h2>
    <p>All our products are digital: online courses, e-books, templates, software tools, video subscriptions and school software licences.</p>
    <p>Delivery is instant. Access starts the moment payment is confirmed, and nothing is shipped physically. This matters for the section below, because a digital product that has already been opened cannot be returned in the way a physical one can.</p>

    <h2>2. When we refund</h2>

    <table class="refund-table">
      <caption>Every situation below is decided the same way for every customer.</caption>
      <thead>
        <tr><th scope="col">Situation</th><th scope="col">What happens</th></tr>
      </thead>
      <tbody>
        <tr>
          <td>You paid but had no access within 24 hours</td>
          <td><span class="r-tag r-yes">Full refund</span></td>
        </tr>
        <tr>
          <td>You were charged twice for the same order</td>
          <td><span class="r-tag r-yes">Full refund</span><span class="r-note">The duplicate charge is returned in full.</span></td>
        </tr>
        <tr>
          <td>The product is materially different from its description</td>
          <td><span class="r-tag r-yes">Full refund</span></td>
        </tr>
        <tr>
          <td>Money was deducted but no order was created</td>
          <td><span class="r-tag r-yes">Full refund</span></td>
        </tr>
        <tr>
          <td>You changed your mind after the product was accessed or downloaded</td>
          <td><span class="r-tag r-no">No refund</span></td>
        </tr>
        <tr>
          <td>The unused part of a subscription period that has already started</td>
          <td><span class="r-tag r-no">No refund</span><span class="r-note">Your access continues to the end of the period you paid for.</span></td>
        </tr>
      </tbody>
    </table>

    <h2>3. How to request a refund</h2>
    <ol class="steps">
      <li><strong>Get in touch within 7 days of payment.</strong> Email <a class="link" href="mailto:solaviaug@gmail.com">solaviaug@gmail.com</a> or call <a class="link" href="tel:+256783204665">+256 783 204 665</a>.</li>
      <li><strong>Include your payment reference</strong>, and the phone number or email address used on the order. Without these we cannot find the payment.</li>
      <li><strong>We reply within 2 business days.</strong> If the refund is approved, the money goes back to the original payment method within 7 business days.</li>
    </ol>

    <h2>4. Cancellation</h2>
    <p>Subscriptions can be cancelled at any time from account settings in the app or on the website, or by contacting us. Cancelling stops future renewals; your access continues until the end of the period you have already paid for.</p>
    <p>School software licences may be cancelled by written notice given 30 days before the next billing term.</p>

    <h2>5. Disputes</h2>
    <p>Please contact us before raising a dispute with your bank or payment provider. Most problems are a delivery or access issue we can fix the same day, and doing it directly is faster for you than a chargeback.</p>
    <p>We keep payment, delivery and access logs for every order, and we provide them to the payment provider whenever a dispute is raised.</p>

    <h2>6. Contact</h2>
    <p>
      SOLAVIA GROUP LIMITED<br>
      Plot 2335, Buwambo-Katadde-Najjo Road, Nansana Municipality, Wakiso District, Uganda<br>
      P.O. Box 214231, Kampala<br>
      Email <a class="link" href="mailto:solaviaug@gmail.com">solaviaug@gmail.com</a> &middot;
      Phone <a class="link" href="tel:+256783204665">+256 783 204 665</a>
    </p>

    <div class="legal-sign">
      <p>Signed for and on behalf of SOLAVIA GROUP LIMITED:</p>
      <p class="sig">MUHINDO MUBARAKA</p>
      <p class="role">Director / Managing Director</p>
    </div>
  </div>
</section>
@endsection
