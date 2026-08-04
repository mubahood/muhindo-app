@extends('layouts.marketing')
@section('title', 'Tell me about your project — Muhindo Mubaraka')
@section('desc', 'The eight questions I need answered before I can tell you honestly whether I am the right fit.')

@push('styles')
<style>
  /* One screen, in the order a conversation actually goes: what is it, who
     is it for, when, and what can you spend. Nothing is asked that does not
     change the answer, because every extra field is somebody deciding to do
     this another day. */

  .pp{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:34px;align-items:start;}
  @media(max-width:940px){.pp{grid-template-columns:1fr;}}

  .pp-step{margin-bottom:30px;}
  .pp-step > h2{display:flex;align-items:center;gap:11px;font-size:15px;font-weight:700;
    color:var(--pri);margin:0 0 4px;}
  .pp-n{flex-shrink:0;width:26px;height:26px;background:var(--pri);color:#fff;
    display:flex;align-items:center;justify-content:center;
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;font-weight:700;}
  .pp-hint{font-size:12.5px;line-height:1.6;color:var(--tx3);margin:0 0 13px 37px;}

  .pp-f{margin-bottom:15px;}
  .pp-f label{display:block;font-size:12.5px;font-weight:600;color:var(--tx2);margin-bottom:5px;}
  .pp-f label .opt{font-weight:400;color:var(--tx3);}
  .pp-f input[type=text],.pp-f input[type=tel],.pp-f input[type=number],
  .pp-f textarea,.pp-f select{
    width:100%;padding:11px 13px;border:1px solid var(--line-2);background:var(--surface);
    font-family:var(--font);font-size:14px;color:var(--tx);border-radius:0;}
  .pp-f textarea{resize:vertical;min-height:104px;line-height:1.65;}
  .pp-f input:focus,.pp-f textarea:focus,.pp-f select:focus{
    outline:2px solid var(--gold);outline-offset:-1px;border-color:var(--gold);}
  .pp-err{font-size:12px;color:#B4483C;margin-top:5px;font-weight:500;}

  /* Categories as things you press, not a dropdown you have to open and
     read. The description is the point — it tells somebody which one they
     are without making them guess at a label. */
  .pp-cats{display:grid;gap:8px;}
  .pp-cat{display:flex;gap:11px;align-items:flex-start;padding:12px 14px;cursor:pointer;
    border:1px solid var(--line);background:var(--surface);transition:.14s;}
  .pp-cat:hover{border-color:var(--gold);}
  .pp-cat input{margin:2px 0 0;accent-color:var(--gold-d);flex-shrink:0;}
  .pp-cat span{font-size:13.5px;line-height:1.5;color:var(--tx2);}
  .pp-cat:has(input:checked){border-color:var(--gold);background:var(--gold-soft);}
  .pp-cat:has(input:checked) span{color:var(--pri);font-weight:500;}

  /* Money. The currency is a real choice — most of my clients think in
     shillings and some think in dollars, and converting for them is a way to
     get the number wrong. */
  .pp-money{display:flex;gap:0;}
  .pp-cur{display:flex;flex-shrink:0;}
  .pp-cur label{margin:0;}
  .pp-cur input{position:absolute;opacity:0;pointer-events:none;}
  .pp-cur span{display:flex;align-items:center;justify-content:center;min-width:62px;height:100%;
    padding:0 14px;border:1px solid var(--line-2);border-right:0;background:var(--bg);
    font-size:13px;font-weight:700;color:var(--tx3);cursor:pointer;transition:.14s;}
  .pp-cur input:checked + span{background:var(--pri);border-color:var(--pri);color:#fff;}
  .pp-money input[type=number]{border-radius:0;}

  .pp-submit{border-top:2px solid var(--pri);padding-top:20px;margin-top:6px;}
  .pp-submit .btn{width:100%;justify-content:center;}
  .pp-under{font-size:12px;line-height:1.65;color:var(--tx3);margin:11px 0 0;}

  /* The rail: what happens after they press it. The single most common
     reason somebody abandons a form like this is not knowing. */
  .pp-side{position:sticky;top:calc(var(--hd) + 18px);display:flex;flex-direction:column;gap:16px;}
  .pp-card{border:1px solid var(--line);background:var(--surface);padding:17px 18px;}
  .pp-card h3{font-size:11px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;
    color:var(--tx3);margin:0 0 13px;}

  .pp-flow{list-style:none;margin:0;padding:0;counter-reset:f;}
  .pp-flow li{position:relative;padding:0 0 17px 30px;}
  .pp-flow li:last-child{padding-bottom:0;}
  .pp-flow li::before{counter-increment:f;content:counter(f);position:absolute;left:0;top:0;
    width:20px;height:20px;border-radius:50%;background:var(--gold-soft);color:var(--gold-d);
    display:flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:700;}
  .pp-flow li::after{content:'';position:absolute;left:9.5px;top:22px;bottom:2px;width:1px;
    background:var(--line);}
  .pp-flow li:last-child::after{display:none;}
  .pp-flow li.now::before{background:var(--pri);color:#fff;}
  .pp-flow b{display:block;font-size:13px;font-weight:600;color:var(--tx);}
  .pp-flow span{font-size:11.5px;line-height:1.55;color:var(--tx3);}

  .pp-you{display:flex;gap:11px;align-items:center;}
  .pp-you .av{width:38px;height:38px;border-radius:50%;background:var(--pri);color:var(--gold);
    display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;}
  .pp-you b{display:block;font-size:13px;color:var(--tx);}
  .pp-you span{font-size:11.5px;color:var(--tx3);}

  .pp-fit{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;}
  .pp-fit li{position:relative;padding-left:18px;font-size:12.5px;line-height:1.55;color:var(--tx2);}
  .pp-fit li::before{content:'';position:absolute;left:0;top:8px;width:7px;height:1px;background:var(--gold);}

  .pp-demo{display:flex;gap:12px;align-items:flex-start;border:1px solid var(--line);
    border-left:3px solid var(--gold);background:var(--surface);padding:14px 16px;margin-bottom:24px;}
  .pp-demo i{color:var(--gold-d);margin-top:2px;}
  .pp-demo b{display:block;font-size:13.5px;color:var(--tx);}
  .pp-demo span{font-size:12.5px;color:var(--tx3);}
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">BRIEF</span>
  <div class="wrap">
    <div class="eyebrow">Step 2 of 2</div>
    <h1>Tell me about your project</h1>
    <p>Eight questions. They are the ones I need answered before I can tell you honestly
       whether I am the right person for this — and roughly what it would cost.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    @if($errors->any())
      <div class="field-error" style="margin-bottom:20px;">
        <strong>A few things need fixing:</strong>
        <ul style="margin:6px 0 0;padding-left:18px;">
          @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
      </div>
    @endif

    @if($demo)
      <div class="pp-demo">
        <i class="fas fa-desktop" aria-hidden="true"></i>
        <div>
          <b>You came from {{ $demo->title }}</b>
          <span>Say below if you want something like it — I will show you the real thing on a call.</span>
        </div>
      </div>
    @endif

    <form method="POST" action="{{ route('propose.store') }}" class="pp">
      @csrf

      <div>
        {{-- 1 ─────────────────────────────────────────────────────────── --}}
        <div class="pp-step">
          <h2><span class="pp-n">01</span> What are you building?</h2>
          <p class="pp-hint">A name and a few sentences. Plain language is perfect — I would rather
             read how you describe it than how you think a developer would.</p>

          <div class="pp-f">
            <label for="title">Give it a name</label>
            <input type="text" id="title" name="title" value="{{ old('title', $demo ? 'Something like '.$demo->title : '') }}"
                   placeholder="Stock system for our three branches" required>
            @error('title')<div class="pp-err">{{ $message }}</div>@enderror
          </div>

          <div class="pp-f">
            <label for="description">What does it need to do?</label>
            <textarea id="description" name="description" rows="6" required
              placeholder="What happens today, what goes wrong, and what you want instead. If you already have something that is not working, say that too.">{{ old('description') }}</textarea>
            @error('description')<div class="pp-err">{{ $message }}</div>@enderror
          </div>
        </div>

        {{-- 2 ─────────────────────────────────────────────────────────── --}}
        <div class="pp-step">
          <h2><span class="pp-n">02</span> What kind of thing is it?</h2>
          <p class="pp-hint">The closest one. Nothing here locks you in.</p>

          <div class="pp-cats">
            @foreach($categories as $value => $label)
              <label class="pp-cat">
                <input type="radio" name="category" value="{{ $value }}"
                       @checked(old('category') === $value) required>
                <span>{{ $label }}</span>
              </label>
            @endforeach
          </div>
          @error('category')<div class="pp-err">{{ $message }}</div>@enderror
        </div>

        {{-- 3 ─────────────────────────────────────────────────────────── --}}
        <div class="pp-step">
          <h2><span class="pp-n">03</span> Who will actually use it?</h2>
          <p class="pp-hint">This changes the design more than anything else on this page. A system
             for four people in an office and a system for four hundred field officers are not the
             same system.</p>

          <div class="pp-f">
            <label for="who_uses_it">Who, and roughly how many <span class="opt">— optional</span></label>
            <textarea id="who_uses_it" name="who_uses_it" rows="3"
              placeholder="Shop attendants at three branches, plus me. Maybe 12 people. Some of them are not confident with computers.">{{ old('who_uses_it') }}</textarea>
          </div>

          <div class="pp-f">
            <label for="success_looks_like">How would you know it worked? <span class="opt">— optional</span></label>
            <textarea id="success_looks_like" name="success_looks_like" rows="3"
              placeholder="I can see stock across all three branches without phoning anybody.">{{ old('success_looks_like') }}</textarea>
          </div>
        </div>

        {{-- 4 ─────────────────────────────────────────────────────────── --}}
        <div class="pp-step">
          <h2><span class="pp-n">04</span> When, and what can you spend?</h2>
          <p class="pp-hint">Both honestly. A budget you name is a budget I can design to; a budget
             I have to guess at is how projects end badly for everybody.</p>

          <div class="pp-f">
            <label for="timeline">When would you want this working?</label>
            <select id="timeline" name="timeline" required>
              <option value="">Choose one…</option>
              @foreach($timelines as $value => $label)
                <option value="{{ $value }}" @selected(old('timeline') === $value)>{{ $label }}</option>
              @endforeach
            </select>
            @error('timeline')<div class="pp-err">{{ $message }}</div>@enderror
          </div>

          <div class="pp-f">
            <label for="budget_amount">Budget you have in mind <span class="opt">— optional, and not held against you</span></label>
            <div class="pp-money">
              <span class="pp-cur">
                <label><input type="radio" name="budget_currency" value="UGX"
                       @checked(old('budget_currency', 'UGX') === 'UGX')><span>UGX</span></label>
                <label><input type="radio" name="budget_currency" value="USD"
                       @checked(old('budget_currency') === 'USD')><span>USD</span></label>
              </span>
              <input type="number" id="budget_amount" name="budget_amount" min="0" step="1000"
                     value="{{ old('budget_amount') }}" placeholder="e.g. 12,000,000">
            </div>
            @error('budget_amount')<div class="pp-err">{{ $message }}</div>@enderror
          </div>
        </div>

        {{-- 5 ─────────────────────────────────────────────────────────── --}}
        <div class="pp-step">
          <h2><span class="pp-n">05</span> How do I reach you?</h2>
          <p class="pp-hint">I already have your name and email from your account. A phone number
             means I can call instead of writing, which is usually faster for both of us.</p>

          <div class="pp-f">
            <label for="phone">Phone number</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone', auth()->user()->phone) }}"
                   placeholder="+256 700 000 000" required>
            @error('phone')<div class="pp-err">{{ $message }}</div>@enderror
          </div>

          <div class="pp-f">
            <label for="organisation">Organisation <span class="opt">— optional, individuals welcome</span></label>
            <input type="text" id="organisation" name="organisation" value="{{ old('organisation') }}">
          </div>
        </div>

        <div class="pp-submit">
          <button type="submit" class="btn gold lg">
            Send this to Muhindo <i class="fas fa-arrow-right" aria-hidden="true"></i>
          </button>
          <p class="pp-under">
            No obligation, no cost, and nobody else sees it. If I am not the right person for this
            I will say so and tell you who might be.
          </p>
        </div>
      </div>

      {{-- The rail ─────────────────────────────────────────────────────── --}}
      <aside class="pp-side">
        <div class="pp-card">
          <h3>What happens next</h3>
          <ol class="pp-flow">
            <li><b>You send this</b><span>It lands with me, not a form inbox.</span></li>
            <li class="now"><b>I read it and reply</b><span>Within one working day. Honestly, including if it is a no.</span></li>
            <li><b>We talk it through</b><span>A call, free, to find out what it really needs.</span></li>
            <li><b>A written scope and a fixed price</b><span>What is in, what is out, what it costs, when it lands.</span></li>
            <li><b>It becomes a project here</b><span>Progress, documents and invoices, in your portal.</span></li>
          </ol>
        </div>

        <div class="pp-card">
          <h3>Who reads this</h3>
          <div class="pp-you">
            <span class="av">MM</span>
            <div>
              <b>Muhindo Mubaraka</b>
              <span>Every proposal, personally</span>
            </div>
          </div>
        </div>

        <div class="pp-card">
          <h3>Worth saying now</h3>
          <ul class="pp-fit">
            <li>I take on a few projects at a time, so I may say no.</li>
            <li>I do not do logos, branding or purely visual design.</li>
            <li>I need access to the people who will use it.</li>
            <li>Your team should be able to run it after I leave — that is the point.</li>
          </ul>
        </div>
      </aside>
    </form>
  </div>
</section>

{{-- Phone only. --}}
<x-action-bar>
  <span class="act-note"><strong>Step 2 of 2</strong><span>Nearly there</span></span>
  <button type="submit" form="" class="btn gold"
          onclick="document.querySelector('.pp').requestSubmit();return false;">
    Send it <i class="fas fa-arrow-right" aria-hidden="true"></i>
  </button>
</x-action-bar>

@endsection
