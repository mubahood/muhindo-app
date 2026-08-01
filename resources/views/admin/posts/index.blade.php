@extends('layouts.admin')
@section('title', 'Insights')

@section('content')

<div class="tb-page-header">
  <div><h1>Insights</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Insights</div></div>
  <a href="{{ route('admin.posts.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New post</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <caption class="sr-only">All articles, newest first</caption>
      <thead><tr>
        <th scope="col">Title</th><th scope="col">Category</th><th scope="col">Status</th>
        <th scope="col">Published</th><th scope="col">Read</th><th scope="col"><span class="sr-only">Actions</span></th>
      </tr></thead>
      <tbody>
        @forelse($items as $item)
          <tr>
            <th scope="row" style="font-weight:500;">
              <a href="{{ route('admin.posts.edit', $item) }}">{{ $item->title }}</a>
              <div class="muted" style="font-size:11px;">/insights/{{ $item->slug }}</div>
            </th>
            <td>{{ $item->category ?? '—' }}</td>
            <td>
              @if($item->is_published)
                <span class="badge-tb badge-success">Published</span>
              @else
                <span class="badge-tb badge-neutral">Draft</span>
              @endif
            </td>
            <td>{{ $item->published_at?->format('d M Y') ?? '—' }}</td>
            <td>{{ $item->read_minutes }} min</td>
            <td>
              <div class="tb-table-actions">
                @if($item->is_published)
                  <a href="{{ route('insights.show', $item) }}" target="_blank" rel="noopener" data-no-navigate
                     class="btn-tb btn-tb-ghost btn-tb-icon" title="View"><i class="fas fa-eye"></i></a>
                @endif
                <a href="{{ route('admin.posts.edit', $item) }}" class="btn-tb btn-tb-ghost btn-tb-icon" title="Edit"><i class="fas fa-pen"></i></a>
                <form method="POST" action="{{ route('admin.posts.destroy', $item) }}" onsubmit="return confirm('Delete this post?');">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="6"><div class="tb-empty"><p>No posts yet.</p>
            <a href="{{ route('admin.posts.create') }}" class="btn-tb btn-tb-primary" style="margin-top:12px;">
              <i class="fas fa-plus"></i> Write the first one</a></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div style="margin-top:16px;">{{ $items->links() }}</div>

@endsection
