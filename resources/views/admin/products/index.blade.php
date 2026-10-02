@extends('layouts.admin')

@section('title', 'Produk')

@section('content')
    <x-admin.page-header title="Produk">
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Tambah produk</a>
    </x-admin.page-header>

    @if ($products->isEmpty())
        <div class="rounded-control border border-line bg-surface px-4 py-8 text-center">
            <p class="text-muted">Belum ada produk.</p>
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary mt-4">Tambah produk</a>
        </div>
    @else
        <ul class="divide-y divide-line rounded-control border border-line bg-surface">
            @foreach ($products as $product)
                <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold">
                            {{ $product->name }}
                            <x-badge :tone="$product->is_active ? 'success' : 'neutral'" class="ml-1 align-middle">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                        </p>
                        <p class="text-sm text-muted">{{ $product->category->name }} · {{ \App\Support\Rupiah::format($product->base_price) }}</p>
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
