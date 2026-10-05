@extends('layouts.admin')

@section('title', 'Area Pengiriman')

@section('content')
    <x-admin.page-header title="Area pengiriman">
        <a href="{{ route('admin.delivery-areas.create') }}" class="btn btn-primary">Tambah area</a>
    </x-admin.page-header>

    <form method="GET" action="{{ route('admin.delivery-areas.index') }}" class="mb-5 grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
        <div>
            <label for="q" class="field-label">Cari nama area</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="100" class="field-input">
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
                <a href="{{ route('admin.delivery-areas.index') }}" class="btn btn-quiet">Reset</a>
            @endif
        </div>
    </form>

    @if ($areas->isEmpty())
        <div class="rounded-control border border-line bg-surface px-4 py-8 text-center">
            @if ($filtered)
                <p class="text-muted">Tidak ada area yang cocok dengan pencarian.</p>
                <a href="{{ route('admin.delivery-areas.index') }}" class="btn btn-quiet mt-4">Reset pencarian</a>
            @else
                <p class="text-muted">Belum ada area pengiriman.</p>
                <a href="{{ route('admin.delivery-areas.create') }}" class="btn btn-primary mt-4">Tambah area</a>
            @endif
        </div>
    @else
        <ul class="divide-y divide-line rounded-control border border-line bg-surface">
            @foreach ($areas as $area)
                <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold">
                            {{ $area->district }}
                            <x-badge :tone="$area->is_active ? 'success' : 'neutral'" class="ml-1 align-middle">{{ $area->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                        </p>
                        <p class="text-sm text-muted">
                            Ongkir {{ \App\Support\Rupiah::format($area->delivery_fee) }}
                            @if ($area->orders_count > 0)
                                · dipakai {{ $area->orders_count }} pesanan
                            @endif
                        </p>
                    </div>

                    <x-admin.row-actions
                        :edit="route('admin.delivery-areas.edit', $area)"
                        :toggle="route('admin.delivery-areas.toggle', $area)"
                        :active="$area->is_active"
                        :destroy="route('admin.delivery-areas.destroy', $area)"
                        :confirm="'Hapus area '.$area->district.'?'" />
                </li>
            @endforeach
        </ul>

        {{ $areas->links('vendor.pagination.admin') }}
    @endif
@endsection
