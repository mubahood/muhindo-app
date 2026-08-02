@extends('layouts.admin')
@section('title', 'Gallery')

@section('content')

<div class="tb-page-header">
  <div><h1>Gallery</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Gallery</div></div>
  <a href="{{ route('admin.gallery.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> Add photograph</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <caption class="sr-only">Gallery photographs in display order</caption>
      <thead><tr>
        <th scope="col"><span class="sr-only">Preview</span></th>
        <th scope="col">Title</th><th scope="col">Category</th><th scope="col">Status</th>
        <th scope="col">Size</th><th scope="col">Order</th>
        <th scope="col"><span class="sr-only">Actions</span></th>
      </tr></thead>
      <tbody>
        @forelse($items as $item)
          <tr>
            <td style="width:64px;">
              <img src="{{ $item->thumbUrl() }}" alt="" style="width:48px;height:48px;object-fit:cover;border:1px solid var(--line);">
            </td>
            <th scope="row" style="font-weight:500;">
              <a href="{{ route('admin.gallery.edit', $item) }}">{{ $item->title }}</a>
              @if($item->caption)<div class="muted" style="font-size:11px;">{{ Str::limit($item->caption, 60) }}</div>@endif
            </th>
            <td>{{ $item->category ?? '—' }}</td>
            <td>
              @if($item->is_published)<span class="badge-tb badge-success">Published</span>
              @else<span class="badge-tb badge-neutral">Hidden</span>@endif
              @if($item->is_featured)<span class="badge-tb badge-warn">Featured</span>@endif
            </td>
            <td class="muted">{{ $item->width }}×{{ $item->height }} · {{ $item->bytes ? round($item->bytes / 1024).'KB' : '—' }}</td>
            <td class="mono">{{ $item->sort_order }}</td>
            <td>
              <div class="tb-table-actions">
                <a href="{{ route('admin.gallery.edit', $item) }}" class="btn-tb btn-tb-ghost btn-tb-icon" title="Edit"><i class="fas fa-pen"></i></a>
                <form method="POST" action="{{ route('admin.gallery.destroy', $item) }}" onsubmit="return confirm('Delete this photograph and its files?');">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7"><div class="tb-empty"><p>No photographs yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<p class="muted" style="font-size:11.5px;margin-top:12px;">
  Bulk import a folder from the command line:
  <code>php artisan gallery:import /path/to/folder</code> — each file is auto-oriented, stripped of metadata, resized and written as JPEG + WebP with a thumbnail.
</p>

@endsection
