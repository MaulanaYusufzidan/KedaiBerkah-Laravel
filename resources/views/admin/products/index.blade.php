@extends('layouts.admin')

@section('title', 'Produk')

@section('content')
    <x-admin.page-header title="Produk">
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Tambah produk</a>
    </x-admin.page-header>

    <form method="GET" action="{{ route('admin.products.index') }}" class="mb-5 grid gap-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
        <div>
            <label for="q" class="field-label">Cari nama produk</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="100" class="field-input">
        </div>
        <div>
            <label for="category" class="field-label">Kategori</label>
            <select id="category" name="category" class="field-input">
                <option value="">Semua</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="field-label">Status</label>
            <select id="status" name="status" class="field-input">
                <option value="">Semua</option>
                <option value="aktif" @selected($filters['status'] === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected($filters['status'] === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-quiet">Cari</button>
            @if ($filtered)
                <a href="{{ route('admin.products.index') }}" class="btn btn-quiet">Reset</a>
            @endif
        </div>
    </form>

    @if ($products->isEmpty())
        <div class="rounded-control border border-line bg-surface px-4 py-8 text-center">
            @if ($filtered)
                <p class="text-muted">Tidak ada produk yang cocok dengan pencarian.</p>
                <a href="{{ route('admin.products.index') }}" class="btn btn-quiet mt-4">Reset pencarian</a>
            @else
                <p class="text-muted">Belum ada produk.</p>
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary mt-4">Tambah produk</a>
            @endif
        </div>
    @else
        <ul class="divide-y divide-line rounded-control border border-line bg-surface">
            @foreach ($products as $product)
                <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-product-thumb :product="$product" />
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{ $product->name }}
                                <x-badge :tone="$product->is_active ? 'success' : 'neutral'" class="ml-1 align-middle">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                            </p>
                            <p class="text-sm text-muted">{{ $product->category->name }} · {{ \App\Support\Rupiah::format($product->base_price) }}</p>
                        </div>
                    </div>

                    <x-admin.row-actions
                        :edit="route('admin.products.edit', $product)"
                        :toggle="route('admin.products.toggle', $product)"
                        :active="$product->is_active"
                        :destroy="route('admin.products.destroy', $product)"
                        :confirm="'Hapus produk '.$product->name.'?'" />
                </li>
            @endforeach
        </ul>

        {{ $products->links('vendor.pagination.admin') }}
    @endif
@endsection
