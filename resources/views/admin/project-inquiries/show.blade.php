@extends('layouts.admin')
@section('title', 'Project Inquiry | '.$inquiry->name)

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $inquiry->name }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> <a href="{{ route('admin.project-inquiries.index') }}">Project Inquiries</a> <span>/</span> {{ $inquiry->name }}</div>
  </div>
  <a href="{{ route('admin.clients.create', ['from_inquiry' => $inquiry->id]) }}" class="btn-tb btn-tb-primary"><i class="fas fa-user-plus"></i> Convert to client</a>
</div>

<div class="tb-card" style="margin-bottom:20px;">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div><div class="muted" style="font-size:.75rem;">Email</div><a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a></div>
      <div><div class="muted" style="font-size:.75rem;">Phone / WhatsApp</div>{{ $inquiry->phone ?: '-' }}</div>
      <div><div class="muted" style="font-size:.75rem;">Organisation</div>{{ $inquiry->organisation ?: 'Individual' }}</div>
      <div><div class="muted" style="font-size:.75rem;">Project type</div>{{ str_replace('_', ' ', ucfirst($inquiry->project_type)) }}</div>
      <div><div class="muted" style="font-size:.75rem;">Budget</div>{{ $inquiry->budget_range ? str_replace('_', ' ', $inquiry->budget_range) : 'Not specified' }}</div>
      <div><div class="muted" style="font-size:.75rem;">Timeline</div>{{ $inquiry->timeline ? str_replace('_', ' ', $inquiry->timeline) : 'Not specified' }}</div>
      <div><div class="muted" style="font-size:.75rem;">Received</div>{{ $inquiry->created_at->format('d M Y, H:i') }}</div>
    </div>
    <div style="margin-top:18px;">
      <div class="muted" style="font-size:.75rem;margin-bottom:6px;">Description</div>
      <p>{{ $inquiry->description }}</p>
    </div>
  </div>
</div>

<div class="tb-card">
  <div class="tb-card-body">
    <div class="muted" style="font-size:.75rem;margin-bottom:8px;">Status</div>
    <form method="POST" action="{{ route('admin.project-inquiries.status', $inquiry) }}" style="display:flex;gap:10px;align-items:center;">
      @csrf @method('PATCH')
      <select name="status" class="tb-select" style="max-width:220px;">
        @foreach(\App\Enums\ProjectInquiryStatus::options() as $value => $label)
          <option value="{{ $value }}" {{ $inquiry->status->value === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm">Update status</button>
    </form>

    <form method="POST" action="{{ route('admin.project-inquiries.destroy', $inquiry) }}"
          style="margin-top:12px;"
          onsubmit="return confirm('Delete this inquiry? This cannot be undone.');">
      @csrf @method('DELETE')
      <button type="submit" class="btn-tb btn-tb-danger btn-tb-sm"><i class="fas fa-trash"></i> Delete inquiry</button>
    </form>
  </div>
</div>

@endsection
