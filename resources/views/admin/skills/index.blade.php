@extends('layouts.admin')
@section('title', 'Skills')

@section('content')

<div class="tb-page-header">
  <div><h1>Skills</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Skills</div></div>
  <a href="{{ route('admin.skills.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New Skill</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Name</th><th>Category</th><th>Proficiency</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($skills as $skill)
        <tr>
          <td style="font-weight:500;">{{ $skill->name }}</td>
          <td>{{ $skill->category ?? '—' }}</td>
          <td>{{ $skill->proficiency !== null ? $skill->proficiency.'%' : '—' }}</td>
          <td>
            <div class="tb-table-actions">
              <a href="{{ route('admin.skills.edit', $skill) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
              <form method="POST" action="{{ route('admin.skills.destroy', $skill) }}" onsubmit="return confirm('Remove this skill?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="tb-empty"><p>No skills yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
