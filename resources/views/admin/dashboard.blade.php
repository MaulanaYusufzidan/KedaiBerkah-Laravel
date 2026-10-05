@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <x-admin.page-header title="Dashboard" />
    <p class="-mt-3 mb-5 text-muted">Halo, {{ auth()->user()->name }}.</p>

    @if ($missingSettings)
        <p class="mb-5 rounded-control border border-line bg-surface px-4 py-3 text-sm">
            Pengaturan toko belum lengkap: {{ implode(', ', $missingSettings) }}.
            <a href="{{ route('admin.settings.edit') }}" class="underline">Lengkapi di Pengaturan</a>
        </p>
    @endif

    <form method="GET" action="{{ route('admin.dashboard') }}" class="mb-2 flex flex-wrap items-end gap-3">
        <div>
            <label for="from" class="field-label">Dari tanggal</label>
            <input id="from" name="from" type="date" value="{{ $from->toDateString() }}" class="field-input">
        </div>
        <div>
            <label for="to" class="field-label">Sampai tanggal</label>
            <input id="to" name="to" type="date" value="{{ $to->toDateString() }}" class="field-input">
        </div>
        <div>
            <label for="group" class="field-label">Grafik</label>
            <select id="group" name="group" class="field-input">
                @foreach (['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'] as $value => $label)
                    <option value="{{ $value }}" @selected($granularity === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Terapkan</button>
    </form>

    <p class="mb-5 text-sm">
        @foreach ($presets as $label => [$presetFrom, $presetTo])
            <a href="{{ route('admin.dashboard', ['from' => $presetFrom->toDateString(), 'to' => $presetTo->toDateString()]) }}"
               class="mr-3 inline-block py-2 underline">{{ $label }}</a>
        @endforeach
    </p>

    @if ($errors)
        <div role="alert" class="mb-5 rounded-control border border-danger/30 bg-danger-soft px-4 py-3 text-danger">
            @foreach ($errors as $message)
                <p>{{ $message }}</p>
            @endforeach
            <p class="mt-1 text-sm">Menampilkan 30 hari terakhir.</p>
        </div>
    @endif

    @if ($granularityAdjusted)
        <p class="mb-5 text-sm text-muted">Rentang lebih dari {{ \App\Support\SalesReport::DAILY_MAX_DAYS }} hari, grafik ditampilkan per minggu.</p>
    @endif

    <section aria-labelledby="ringkasan">
        <h2 id="ringkasan" class="sr-only">Ringkasan penjualan</h2>
        <dl class="grid divide-y divide-line rounded-control border border-line bg-surface sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="px-4 py-3">
                <dt class="text-sm text-muted">Omzet</dt>
                <dd class="mt-1 text-xl font-semibold">{{ \App\Support\Rupiah::format($summary['revenue']) }}</dd>
            </div>
            <div class="px-4 py-3">
                <dt class="text-sm text-muted">Pesanan</dt>
                <dd class="mt-1 text-xl font-semibold">{{ $summary['orders'] }}</dd>
            </div>
            <div class="px-4 py-3">
                <dt class="text-sm text-muted">Rata-rata per pesanan</dt>
                <dd class="mt-1 text-xl font-semibold">{{ \App\Support\Rupiah::format($summary['average']) }}</dd>
            </div>
        </dl>
        <p class="mt-2 text-sm text-muted">
            {{ $from->locale('id')->isoFormat('D MMM Y') }} – {{ $to->locale('id')->isoFormat('D MMM Y') }} ({{ $days }} hari).
            Omzet dihitung dari pesanan yang pembayarannya sudah dinyatakan lunas dan tidak dibatalkan.
        </p>
    </section>

    @if ($summary['orders'] === 0)
        <div class="mt-6 rounded-control border border-line bg-surface px-4 py-8 text-center">
            <p class="font-medium">Belum ada penjualan pada periode ini.</p>
            <p class="mt-1 text-sm text-muted">Grafik dan produk terlaris muncul setelah ada pesanan yang pembayarannya dikonfirmasi.</p>
        </div>
    @else
        <section class="mt-8" aria-labelledby="grafik">
            <h2 id="grafik" class="mb-3 text-lg font-semibold">
                Omzet {{ ['daily' => 'harian', 'weekly' => 'mingguan', 'monthly' => 'bulanan'][$granularity] }}
            </h2>
            <div class="rounded-control border border-line bg-surface p-4">
                <x-admin.sales-chart :series="$series"
                    :label="'Grafik omzet '.['daily' => 'harian', 'weekly' => 'mingguan', 'monthly' => 'bulanan'][$granularity].', total '.\App\Support\Rupiah::format($summary['revenue'])" />
            </div>
        </section>

        <section class="mt-8" aria-labelledby="terlaris">
            <h2 id="terlaris" class="mb-3 text-lg font-semibold">Produk terlaris</h2>
            <ol class="divide-y divide-line rounded-control border border-line bg-surface">
                @foreach ($topProducts as $i => $product)
                    <li class="flex items-baseline justify-between gap-3 px-4 py-3">
                        <span><span class="text-muted">{{ $i + 1 }}.</span> {{ $product->product_name }}</span>
                        <span class="shrink-0 text-right text-sm text-muted">
                            {{ $product->qty }} terjual · {{ \App\Support\Rupiah::format($product->revenue) }}
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if (array_sum($statusCounts) > 0)
        <section class="mt-8" aria-labelledby="status-pesanan">
            <h2 id="status-pesanan" class="mb-1 text-lg font-semibold">Status pesanan</h2>
            <p class="mb-3 text-sm text-muted">Semua pesanan yang dibuat pada periode ini.</p>
            <dl class="divide-y divide-line rounded-control border border-line bg-surface">
                @foreach ($statusCounts as $status => $total)
                    <div class="flex justify-between px-4 py-2">
                        <dt>{{ $statusLabels[$status] }}</dt>
                        <dd class="font-medium">{{ $total }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif
@endsection
