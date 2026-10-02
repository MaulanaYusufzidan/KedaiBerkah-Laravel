@extends('layouts.admin')

@section('title', 'Menu Hari Ini')

@section('content')
    <x-admin.page-header :title="$isToday ? 'Menu Hari Ini' : 'Menu Harian'">
        <a href="{{ route('admin.daily-menus.create', ['date' => $date->toDateString()]) }}" class="btn btn-primary">Tambah ke menu</a>
    </x-admin.page-header>

    <form method="GET" action="{{ route('admin.daily-menus.index') }}" class="mb-5 flex flex-wrap items-end gap-2">
        <div>
            <label for="date" class="field-label">Tanggal</label>
            <input id="date" name="date" type="date" value="{{ $date->toDateString() }}" class="field-input">
        </div>
        <button type="submit" class="btn btn-quiet">Tampilkan</button>
        @unless ($isToday)
            <a href="{{ route('admin.daily-menus.index') }}" class="btn btn-quiet">Hari ini</a>
        @endunless
    </form>

    <h2 class="mb-3 text-lg font-semibold">{{ $date->locale('id')->isoFormat('dddd, D MMMM Y') }}</h2>

    @if ($menus->isEmpty())
        <div class="rounded-control border border-line bg-surface px-4 py-8 text-center">
            <p class="text-muted">Belum ada menu pada tanggal ini.</p>
            <a href="{{ route('admin.daily-menus.create', ['date' => $date->toDateString()]) }}" class="btn btn-primary mt-4">Tambah ke menu</a>
        </div>
    @else
        <ul class="divide-y divide-line rounded-control border border-line bg-surface">
            @foreach ($menus as $menu)
                <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold">
                            {{ $menu->product->name }}
                            <x-badge :tone="match ($menu->status) {
                                \App\Enums\DailyMenuStatus::Available => 'success',
                                \App\Enums\DailyMenuStatus::SoldOut => 'danger',
                                default => 'neutral',
                            }" class="ml-1 align-middle">{{ $menu->status->label() }}</x-badge>
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
