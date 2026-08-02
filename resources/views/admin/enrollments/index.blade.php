@extends('layouts.admin')
@section('title', 'Enrollments')

@push('styles')
<style>
  /* Counts double as filters — the number and the way to act on it are the
     same control, rather than a statistic you then have to go and find. */
  .en-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
  .en-tab{display:flex;align-items:baseline;gap:7px;padding:9px 13px;border:1px solid var(--line);
    background:var(--surface);font-size:12px;font-weight:500;color:var(--tx2);transition:.15s;}
  .en-tab:hover{border-color:var(--pri);color:var(--tx);}
  .en-tab.on{border-color:var(--pri);background:var(--pri-soft);color:var(--pri);font-weight:600;
    box-shadow:inset 0 0 0 1px var(--pri);}
  .en-tab b{font-size:14px;font-weight:700;}
  .en-tab.warn.on{border-color:#b45309;background:#fdf6e3;color:#8a5a06;box-shadow:inset 0 0 0 1px #b45309;}

  .en-filters{display:grid;grid-template-columns:minmax(180px,1.6fr) repeat(3,minmax(120px,1fr)) auto;
    gap:9px;align-items:end;}
  @media(max-width:900px){.en-filters{grid-template-columns:1fr 1fr;}}
  @media(max-width:560px){.en-filters{grid-template-columns:1fr;}}

  .en-who b{display:block;font-size:13px;font-weight:600;line-height:1.3;}
  .en-who span{font-size:11px;color:var(--tx3);}
  .en-course{font-size:12.5px;line-height:1.35;}
  .en-course span{display:block;font-size:11px;color:var(--tx3);}

  /* Billing is the column that decides what to do next, so it states the
     amount rather than only a colour. */
  .en-bill{font-size:11.5px;line-height:1.45;white-space:nowrap;}
  .en-bill b{display:block;font-size:12px;font-weight:600;}
  .en-bill .due{color:#b45309;}
  .en-bill .ok{color:var(--ok);}
  .en-bill .none{color:var(--tx3);}

  .en-acts{display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap;}
  .en-acts form{margin:0;}

  /* One panel of controls per enrollment, revealed rather than stacked, so the
     table still scans at thirty rows. */
  .en-panel{background:var(--surface-2,#f6f7f9);border-top:1px solid var(--line);}
  .en-panel td{padding:14px 16px !important;}
  .en-panel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;}
  .en-box{background:var(--surface);border:1px solid var(--line);padding:12px 13px;}
  .en-box h4{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:600;letter-spacing:.08em;
    text-transform:uppercase;color:var(--tx3);margin:0 0 9px;}
  .en-box form{display:grid;gap:8px;margin:0;}
  .en-box .row{display:flex;gap:7px;align-items:center;}
  .en-box .row > *{flex:1;min-width:0;}
  .en-hint{font-size:11px;color:var(--tx3);line-height:1.45;margin:0;}
  .en-link{display:flex;gap:6px;align-items:center;margin-bottom:6px;}
  .en-link input{flex:1;min-width:0;font-size:11px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
    padding:7px 8px;border:1px solid var(--line-2);background:var(--surface-2,#f6f7f9);color:var(--tx2);}
</style>
@endpush

@section('content')

<div class="tb-page-header">
  <div>
    <h1>Enrollments</h1>
    <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Enrollments</div>
  </div>
</div>

@php $f = $filters; @endphp

{{-- Counts that are also filters ----------------------------------------- --}}
<div class="en-tabs">
  <a href="{{ route('admin.enrollments.index') }}" class="en-tab {{ $f['status'] === '' && $f['billing'] === '' ? 'on' : '' }}">
    <b>{{ $counts['all'] }}</b> All
  </a>
  <a href="{{ route('admin.enrollments.index', ['status' => 'active']) }}" class="en-tab {{ $f['status'] === 'active' ? 'on' : '' }}">
    <b>{{ $counts['active'] }}</b> Active
  </a>
  <a href="{{ route('admin.enrollments.index', ['status' => 'pending']) }}" class="en-tab {{ $f['status'] === 'pending' ? 'on' : '' }}">
    <b>{{ $counts['pending'] }}</b> Pending
  </a>
  <a href="{{ route('admin.enrollments.index', ['billing' => 'unpaid']) }}" class="en-tab warn {{ $f['billing'] === 'unpaid' ? 'on' : '' }}">
    <b>{{ $counts['unpaid'] }}</b> Unpaid
  </a>
  <a href="{{ route('admin.enrollments.index', ['billing' => 'direct']) }}" class="en-tab {{ $f['billing'] === 'direct' ? 'on' : '' }}">
    Paying Muhindo directly
  </a>
  <a href="{{ route('admin.enrollments.index', ['billing' => 'uninvoiced']) }}" class="en-tab {{ $f['billing'] === 'uninvoiced' ? 'on' : '' }}">
    Never invoiced
  </a>
</div>

{{-- Search + filters ------------------------------------------------------ --}}
<div class="tb-card" style="margin-bottom:14px;">
  <div class="tb-card-body">
    <form method="GET" action="{{ route('admin.enrollments.index') }}" class="en-filters">
      <div class="tb-form-group" style="margin:0;">
        <label class="tb-label" for="q">Student</label>
        <input class="tb-input" id="q" type="search" name="q" value="{{ $f['q'] }}" placeholder="Name or email…">
      </div>
      <div class="tb-form-group" style="margin:0;">
        <label class="tb-label" for="course_id">Course</label>
        <select class="tb-select" id="course_id" name="course_id">
          <option value="">All courses</option>
          @foreach($courses as $c)
            <option value="{{ $c->id }}" @selected((string) $f['course_id'] === (string) $c->id)>{{ $c->title }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group" style="margin:0;">
        <label class="tb-label" for="status">Status</label>
        <select class="tb-select" id="status" name="status">
          <option value="">Any status</option>
          @foreach($statuses as $value => $label)
            <option value="{{ $value }}" @selected($f['status'] === $value)>{{ ucfirst($value) }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group" style="margin:0;">
        <label class="tb-label" for="billing">Billing</label>
        <select class="tb-select" id="billing" name="billing">
          <option value="">Any</option>
          <option value="unpaid" @selected($f['billing'] === 'unpaid')>Unpaid invoice</option>
          <option value="direct" @selected($f['billing'] === 'direct')>Paying Muhindo directly</option>
          <option value="uninvoiced" @selected($f['billing'] === 'uninvoiced')>No invoice</option>
        </select>
      </div>
      <div style="display:flex;gap:7px;">
        <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-magnifying-glass"></i> Filter</button>
        <a href="{{ route('admin.enrollments.index') }}" class="btn-tb btn-tb-ghost">Clear</a>
      </div>
    </form>
  </div>
</div>

{{-- Enrol by hand --------------------------------------------------------- --}}
<div class="tb-card" style="margin-bottom:14px;">
  <div class="tb-card-header"><span class="tb-card-title">Enroll a student</span></div>
  <div class="tb-card-body">
    {{-- The action depends on the chosen course, so it is built in the script
         below rather than in an inline handler. --}}
    <form method="POST" action="" id="enroll-form" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      @csrf
      <div class="tb-form-group" style="margin:0;">
        <label class="tb-label" for="enroll-course">Course</label>
        <select class="tb-select" id="enroll-course" name="course_select" required
                data-base="{{ url('/admin/courses') }}">
          <option value="">Select a course…</option>
          @foreach($courses as $c)
            <option value="{{ $c->id }}">{{ $c->title }} — {{ $c->isFree() ? 'Free' : $c->currency.' '.number_format((float) $c->price) }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group" style="margin:0;">
        <label class="tb-label" for="enroll-student">Student</label>
        <select class="tb-select" id="enroll-student" name="user_id" required>
          @foreach($students as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->email }})</option>@endforeach
        </select>
      </div>
      <div class="tb-form-group" style="margin:0;">
        <label class="tb-label" for="enroll-status">Access</label>
        <select class="tb-select" id="enroll-status" name="status">
          <option value="active">Grant access now</option>
          <option value="pending">Pending — bill them first</option>
        </select>
      </div>
      <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> Enroll</button>
    </form>
  </div>
</div>

{{-- The list -------------------------------------------------------------- --}}
<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead>
        <tr>
          <th>Student</th><th>Course</th><th>Status</th><th>Billing</th>
          <th>Source</th><th>Enrolled</th><th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($enrollments as $e)
          @php
            $inv = $e->invoice;
            $badge = match($e->status) {
                'active' => 'badge-active',
                'completed' => 'badge-success',
                'cancelled' => 'badge-danger',
                default => 'badge-pending',
            };
          @endphp
          <tr>
            <td>
              <div class="en-who">
                <b>{{ $e->user->name ?? '—' }}</b>
                <span>{{ $e->user->email ?? '' }}</span>
              </div>
            </td>
            <td>
              <div class="en-course">
                {{ $e->course->title ?? '—' }}
                <span>{{ $e->course && $e->course->isFree() ? 'Free' : $e->course?->currency.' '.number_format((float) ($e->course?->price ?? 0)) }}</span>
              </div>
            </td>
            <td><span class="badge-tb {{ $badge }}">{{ ucfirst($e->status) }}</span></td>
            <td>
              <div class="en-bill">
                @if($inv === null)
                  <span class="none">{{ $e->course && $e->course->isFree() ? 'Free course' : 'No invoice' }}</span>
                @elseif($inv->isOutstanding())
                  <b class="due">{{ $inv->currency }} {{ number_format((float) $inv->balance, 2) }} due</b>
                  <span>{{ $inv->invoice_no }}{{ $inv->direct_payment_at ? ' · paying directly' : '' }}</span>
                @else
                  <b class="ok">{{ $inv->status->label() }}</b>
                  <span>{{ $inv->invoice_no }}</span>
                @endif
              </div>
            </td>
            <td>{{ ucfirst($e->source) }}</td>
            <td>{{ $e->enrolled_at?->format('d M Y') ?? '—' }}</td>
            <td>
              <div class="en-acts">
                <a href="{{ route('admin.enrollments.show', $e) }}" class="btn-tb btn-tb-ghost btn-tb-sm" title="Open the full record">
                  <i class="fas fa-up-right-from-square"></i>
                </a>
                <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm"
                        data-toggle-row="row-{{ $e->id }}" aria-expanded="false" aria-controls="row-{{ $e->id }}">
                  <i class="fas fa-sliders"></i> Manage
                </button>
              </div>
            </td>
          </tr>

          <tr class="en-panel" id="row-{{ $e->id }}" hidden>
            <td colspan="7">
              <div class="en-panel-grid">

                {{-- Status + access window --}}
                <div class="en-box">
                  <h4><i class="fas fa-toggle-on"></i> Status &amp; access</h4>
                  <form method="POST" action="{{ route('admin.enrollments.update', $e) }}">
                    @csrf @method('PATCH')
                    <select class="tb-select" name="status" aria-label="Status">
                      @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($e->status === $value)>{{ $label }}</option>
                      @endforeach
                    </select>
                    <input class="tb-input" type="date" name="expires_at"
                           value="{{ $e->expires_at?->format('Y-m-d') }}" aria-label="Access expires">
                    <label class="en-hint" style="display:flex;gap:6px;align-items:center;">
                      <input type="checkbox" name="clear_expiry" value="1"> Lifetime access (no expiry)
                    </label>
                    <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm">Save changes</button>
                    @if($e->expires_at)
                      <p class="en-hint">Expires {{ $e->expires_at->format('d M Y') }} ({{ $e->expires_at->diffForHumans() }}).</p>
                    @else
                      <p class="en-hint">No expiry — access does not lapse.</p>
                    @endif
                  </form>
                </div>

                {{-- Money --}}
                <div class="en-box">
                  <h4><i class="fas fa-file-invoice-dollar"></i> Payment</h4>
                  @if($inv === null)
                    @if($e->course && $e->course->isFree())
                      <p class="en-hint">This course is free, so there is nothing to bill.</p>
                    @else
                      <form method="POST" action="{{ route('admin.enrollments.invoice', $e) }}">
                        @csrf
                        <input class="tb-input" type="text" name="coupon_code" placeholder="Coupon code (optional)">
                        <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm">
                          <i class="fas fa-receipt"></i> Raise an invoice
                        </button>
                        <p class="en-hint">Creates the invoice and a payment link you can send.</p>
                      </form>
                    @endif
                  @else
                    <p class="en-hint" style="margin-bottom:9px;">
                      <b>{{ $inv->invoice_no }}</b> — {{ $inv->status->label() }},
                      {{ $inv->currency }} {{ number_format((float) $inv->balance, 2) }} outstanding
                      of {{ number_format((float) $inv->total, 2) }}.
                      @if($inv->direct_payment_at)
                        <br>Said they would pay Muhindo directly on {{ $inv->direct_payment_at->format('j M Y') }}.
                      @endif
                    </p>
                    @if($inv->isOutstanding())
                      <div class="en-link">
                        <input type="text" readonly value="{{ route('payments.show', $inv) }}"
                               aria-label="Payment link" onfocus="this.select();">
                        <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm"
                                data-copy="{{ route('payments.show', $inv) }}">Copy</button>
                      </div>
                      <p class="en-hint" style="margin-bottom:9px;">Send this to the student — they sign in and pay.</p>
                    @endif
                    <a href="{{ route('admin.invoices.show', $inv) }}" class="btn-tb btn-tb-ghost btn-tb-sm">
                      <i class="fas fa-money-bill-wave"></i> Open invoice / record a payment
                    </a>
                  @endif
                </div>

                {{-- Remove --}}
                <div class="en-box">
                  <h4><i class="fas fa-trash"></i> Remove</h4>
                  <p class="en-hint" style="margin-bottom:9px;">
                    Deletes the enrollment. Any invoice stays in Billing — removing access must not erase a debt.
                  </p>
                  <form method="POST" action="{{ route('admin.enrollments.destroy', $e) }}"
                        onsubmit="return confirm('Remove this enrollment?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-tb btn-tb-danger btn-tb-sm">Remove enrollment</button>
                  </form>
                </div>

              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7"><div class="tb-empty"><p>No enrollments match these filters.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div style="margin-top:16px;">{{ $enrollments->links() }}</div>

@push('scripts')
<script>
(function () {
  // The enroll form's target depends on the chosen course, so it is built here
  // rather than inline, and synced on load so a restored selection still works.
  var courseSelect = document.getElementById('enroll-course');
  var enrollForm = document.getElementById('enroll-form');
  if (courseSelect && enrollForm) {
    var sync = function () {
      enrollForm.action = courseSelect.value
        ? courseSelect.dataset.base + '/' + courseSelect.value + '/enrollments'
        : '';
    };
    courseSelect.addEventListener('change', sync);
    sync();
  }

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-toggle-row]');
    if (toggle) {
      var row = document.getElementById(toggle.getAttribute('data-toggle-row'));
      if (row) {
        row.hidden = !row.hidden;
        toggle.setAttribute('aria-expanded', String(!row.hidden));
      }
      return;
    }

    var copy = event.target.closest('[data-copy]');
    if (copy) {
      var value = copy.getAttribute('data-copy');
      var done = function () {
        var was = copy.textContent;
        copy.textContent = 'Copied';
        setTimeout(function () { copy.textContent = was; }, 1400);
      };
      // navigator.clipboard needs a secure context. http://localhost counts,
      // a LAN address does not — hence the fallback.
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(value).then(done).catch(function () {});
      } else {
        var scratch = document.createElement('textarea');
        scratch.value = value;
        scratch.style.position = 'fixed';
        scratch.style.left = '-9999px';
        document.body.appendChild(scratch);
        scratch.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(scratch);
      }
    }
  });
})();
</script>
@endpush

@endsection
