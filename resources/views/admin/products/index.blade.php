@extends('layouts.admin')
@section('title', 'Shop products')

@section('content')

<div class="tb-page-header">
  <div><h1>Shop products</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Products</div></div>
  <a href="{{ route('admin.products.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New product</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <caption class="sr-only">Digital products for sale</caption>
      <thead><tr>
        <th scope="col">Product</th><th scope="col">Type</th><th scope="col">Price</th>
        <th scope="col">Status</th><th scope="col">Sold</th><th scope="col">File</th>
        <th scope="col"><span class="sr-only">Actions</span></th>
      </tr></thead>
      <tbody>
        @forelse($items as $item)
          <tr>
            <th scope="row" style="font-weight:500;">
              <a href="{{ route('admin.products.edit', $item) }}">{{ $item->name }}</a>
              <div class="muted" style="font-size:11px;">/shop/{{ $item->slug }}</div>
            </th>
            <td>{{ $item->typeLabel() }}</td>
            <td>
              @if($item->isFree())<span class="badge-tb badge-success">Free</span>
              @else {{ $item->currency }} {{ number_format((float) $item->price) }}@endif
            </td>
            <td>
              @if($item->is_published)<span class="badge-tb badge-success">Published</span>
              @else<span class="badge-tb badge-neutral">Draft</span>@endif
            </td>
            <td>{{ $item->licenses_count }}</td>
            <td class="muted">{{ $item->fileSize() ?? ($item->external_url ? 'Link' : '—') }}</td>
            <td>
              <div class="tb-table-actions">
                @if($item->is_published)
                  <a href="{{ route('shop.show', $item) }}" target="_blank" rel="noopener" data-no-navigate
                     class="btn-tb btn-tb-ghost btn-tb-icon" title="View"><i class="fas fa-eye"></i></a>
                @endif
                <a href="{{ route('admin.products.edit', $item) }}" class="btn-tb btn-tb-ghost btn-tb-icon" title="Edit"><i class="fas fa-pen"></i></a>
                <form method="POST" action="{{ route('admin.products.destroy', $item) }}" onsubmit="return confirm('Delete this product?');">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7"><div class="tb-empty"><p>No products yet.</p>
            <a href="{{ route('admin.products.create') }}" class="btn-tb btn-tb-primary" style="margin-top:12px;">
              <i class="fas fa-plus"></i> Add the first one</a></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div style="margin-top:16px;">{{ $items->links() }}</div>

@endsection
