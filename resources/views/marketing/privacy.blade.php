@extends('layouts.marketing')
@section('title', 'Privacy Policy — Muhindo Mubaraka')

@section('content')
<section>
  <div class="wrap page">
    <h1>Privacy Policy</h1>
    <div class="updated">Last updated {{ date('F Y') }}</div>

    <p>This site is operated by Muhindo Mubaraka. This policy explains what information is collected through the portfolio site, the course platform, and the client project portal, and how it's used.</p>

    <h2>Contact form</h2>
    <p>When you send a message through the contact form, your name, email, subject and message are stored so I can reply and keep a record of the conversation.</p>

    <h2>Student accounts</h2>
    <p>Creating an account to enrol in a course stores your name, email and course progress (lessons completed, certificates earned). This data is used to run the course and is never sold or shared with third parties.</p>

    <h2>Client accounts</h2>
    <p>If you're a client, your project details, documents, invoices and communications are stored to deliver and bill for the work. This data is visible only to you and to me — never to other clients.</p>

    <h2>How data is protected</h2>
    <ul>
      <li>Passwords are hashed, never stored in plain text.</li>
      <li>Client and student data is scoped so each account only ever sees its own records.</li>
      <li>Uploaded documents are stored on a private disk, never publicly accessible by URL.</li>
    </ul>

    <h2>Your rights</h2>
    <p>You can request a copy or deletion of the data held about you at any time by getting in touch.</p>

    <h2>Contact</h2>
    <p>Questions about this policy? Email <a class="link" href="mailto:mubahood360@gmail.com">mubahood360@gmail.com</a>.</p>
  </div>
</section>
@endsection
