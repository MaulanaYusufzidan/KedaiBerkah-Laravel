@extends('layouts.admin')

@section('title', 'Menu Hari Ini')

@section('content')
    <x-admin.page-header :title="$isToday ? 'Menu Hari Ini' : 'Menu Harian'">
        <a href="{{ route('admin.daily-menus.create', ['date' => $date->toDateString()]) }}" class="btn btn-primary">Tambah ke menu</a>
    </x-admin.page-header>

    <form method="GET" action="{{ route('admin.daily-menus.index') }}" class="mb-5 grid gap-3 sm:grid-cols-[auto_1fr_auto_auto] sm:items-end">
        <div>
            <label for="date" class="field-label">Tanggal</label>
            <input id="date" name="date" type="date" value="{{ $date->toDateString() }}" class="field-input">
        </div>
        <div>
            <label for="q" class="field-label">Cari produk</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="100" class="field-input">
        </div>
        <div>
            <label for="status" class="field-label">Status</label>
            <select id="status" name="status" class="field-input">
                <option value="">Semua</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="btn btn-quiet">Tampilkan</button>
            @if (! $isToday || $filtered)
                <a href="{{ route('admin.daily-menus.index') }}" class="btn btn-quiet">Reset</a>
            @endif
        </div>
    </form>

    <h2 class="mb-3 text-lg font-semibold">{{ $date->locale('id')->isoFormat('dddd, D MMMM Y') }}</h2>

    @if ($menus->isEmpty())
        <div class="rounded-control border border-line bg-surface px-4 py-8 text-center">
            @if ($filtered)
                <p class="text-muted">Tidak ada menu yang cocok dengan pencarian pada tanggal ini.</p>
                <a href="{{ route('admin.daily-menus.index', ['date' => $date->toDateString()]) }}" class="btn btn-quiet mt-4">Reset pencarian</a>
            @else
                <p class="text-muted">Belum ada menu pada tanggal ini.</p>
                <a href="{{ route('admin.daily-menus.create', ['date' => $date->toDateString()]) }}" class="btn btn-primary mt-4">Tambah ke menu</a>
            @endif
        </div>
    @else
        <ul class="divide-y divide-line rounded-control border border-line bg-surface">
            @foreach ($menus as $menu)
                <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-product-thumb :product="$menu->product" />
                        <div class="min-w-0">
                        <p class="font-semibold">
                            {{ $menu->product->name }}
                            <x-badge :tone="match ($menu->status) {
                                \App\Enums\DailyMenuStatus::Available => 'success',
                                \App\Enums\DailyMenuStatus::SoldOut => 'danger',
                                default => 'neutral',
                            }" class="ml-1 align-middle">{{ $menu->status->label() }}</x-badge>
                            @unless ($menu->product->is_active)
                                <x-badge tone="danger" class="ml-1 align-middle">Produk nonaktif</x-badge>
                            @endunless
                        </p>
                        <p class="text-sm text-muted">
                            {{ $menu->product->category->name }} · {{ \App\Support\Rupiah::format($menu->price) }} · stok {{ $menu->stock }}
                            @if ($menu->available_from || $menu->available_until)
                                · {{ $menu->available_from ? substr($menu->available_from, 0, 5) : '…' }}–{{ $menu->available_until ? substr($menu->available_until, 0, 5) : '…' }}
                            @endif
                        </p>
                        @if ($menu->notes)
                            <p class="mt-1 text-sm">{{ $menu->notes }}</p>
                        @endif
                        </div>
                    </div>

                    <x-admin.row-actions
                        :edit="route('admin.daily-menus.edit', $menu)"
                        :destroy="route('admin.daily-menus.destroy', $menu)"
                        :confirm="'Hapus '.$menu->product->name.' dari menu tanggal ini?'" />
                </li>
            @endforeach
        </ul>

        {{ $menus->links('vendor.pagination.admin') }}
    @endif
@endsection
