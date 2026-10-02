@extends('layouts.admin')

@section('title', 'Kategori')

@section('content')
    <x-admin.page-header title="Kategori">
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">Tambah kategori</a>
    </x-admin.page-header>

    @if ($categories->isEmpty())
        <div class="rounded-control border border-line bg-surface px-4 py-8 text-center">
            <p class="text-muted">Belum ada kategori.</p>
            <a href="{{ route('admin.categories.create') }}" class="btn btn-primary mt-4">Tambah kategori</a>
        </div>
    @else
        <ul class="divide-y divide-line rounded-control border border-line bg-surface">
            @foreach ($categories as $category)
                <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold">
                            {{ $category->name }}
                            <x-badge :tone="$category->is_active ? 'success' : 'neutral'" class="ml-1 align-middle">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                        </p>
                        <p class="text-sm text-muted">{{ $category->slug }} · urutan {{ $category->sort_order }} · {{ $category->products_count }} produk</p>
                    </div>

                    <x-admin.row-actions
                        :edit="route('admin.categories.edit', $category)"
                        :toggle="route('admin.categories.toggle', $category)"
                        :active="$category->is_active"
                        :destroy="route('admin.categories.destroy', $category)"
                        :confirm="'Hapus kategori '.$category->name.'?'" />
                </li>
            @endforeach
        </ul>

        {{ $categories->links('vendor.pagination.admin') }}
    @endif
@endsection
