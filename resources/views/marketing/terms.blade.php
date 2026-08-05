@extends('layouts.marketing')
@section('title', 'Terms of Service | Muhindo Mubaraka')

@section('content')
<section>
  <div class="wrap page">
    <h1>Terms of Service</h1>
    <div class="updated">Last updated {{ date('F Y') }}</div>

    <p>These terms govern use of this site: the portfolio, the course platform, and the client project portal. By creating an account, you agree to them.</p>

    <h2>Courses</h2>
    <p>Free courses are available to any signed-in student. Paid courses are billed as a one-off purchase per course; access is granted once payment is confirmed. Course content may be improved or expanded over time.</p>

    <h2>Client projects</h2>
    <p>Client accounts are created individually to give you visibility into project progress, documents and invoices. Access is scoped to your own projects only.</p>

    <h2>Accounts &amp; access</h2>
    <p>You're responsible for keeping your account credentials secure and must only access data belonging to your own account.</p>

    <h2>Acceptable use</h2>
    <ul>
      <li>Do not attempt to access another user's account or data.</li>
      <li>Do not scrape, overload, or disrupt the service.</li>
      <li>Do not redistribute paid course content without permission.</li>
    </ul>

    <h2>Payments</h2>
    <p>Paid courses and client project invoices are processed via Flutterwave. Refunds are handled on a case-by-case basis, so get in touch if something's wrong with a payment.</p>

    <h2>Contact</h2>
    <p>Questions about these terms? Email <a class="link" href="mailto:mubahood360@gmail.com">mubahood360@gmail.com</a>.</p>
  </div>
</section>
@endsection
