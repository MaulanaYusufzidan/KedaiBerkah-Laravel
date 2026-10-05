@extends('layouts.admin')

@section('title', 'Pesanan')

@section('content')
    <x-admin.page-header title="Pesanan" />

    <form method="GET" action="{{ route('admin.orders.index') }}" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="sm:col-span-2 lg:col-span-3">
            <label for="q" class="field-label">Cari nomor pesanan atau nama pelanggan</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="100" class="field-input">
        </div>
        <div>
            <label for="from" class="field-label">Dari tanggal</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="field-input">
        </div>
        <div>
            <label for="to" class="field-label">Sampai tanggal</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] }}" class="field-input">
        </div>
        <div>
            <label for="fulfillment" class="field-label">Pengambilan</label>
            <select id="fulfillment" name="fulfillment" class="field-input">
                <option value="">Semua</option>
                @foreach ($fulfillments as $type)
                    <option value="{{ $type->value }}" @selected($filters['fulfillment'] === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="field-label">Status pesanan</label>
            <select id="status" name="status" class="field-input">
                <option value="">Semua</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="payment" class="field-label">Status pembayaran</label>
            <select id="payment" name="payment" class="field-input">
                <option value="">Semua</option>
                @foreach ($paymentStatuses as $paymentStatus)
                    <option value="{{ $paymentStatus->value }}" @selected($filters['payment'] === $paymentStatus->value)>{{ $paymentStatus->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-quiet">Cari</button>
            @if ($filtered)
                <a href="{{ route('admin.orders.index') }}" class="btn btn-quiet">Reset</a>
            @endif
        </div>
    </form>

    <nav aria-label="Ringkasan status" class="mb-5 flex flex-wrap gap-x-4 gap-y-1 text-sm">
        @php($query = request()->except('status', 'page'))
        <a href="{{ route('admin.orders.index', $query) }}"
           @class(['inline-block py-2 underline', 'font-semibold' => ! $filters['status']])>Semua ({{ $counts->sum() }})</a>
        @foreach ($statuses as $status)
            <a href="{{ route('admin.orders.index', $query + ['status' => $status->value]) }}"
               @class(['inline-block py-2 underline', 'font-semibold' => $filters['status'] === $status->value])
               @if ($filters['status'] === $status->value) aria-current="true" @endif>{{ $status->label() }} ({{ $counts[$status->value] ?? 0 }})</a>
        @endforeach
    </nav>

    @if ($orders->isEmpty())
        <div class="rounded-control border border-line bg-surface px-4 py-8 text-center">
            @if ($filtered)
                <p class="text-muted">Tidak ada pesanan yang cocok dengan pencarian.</p>
                <a href="{{ route('admin.orders.index') }}" class="btn btn-quiet mt-4">Reset pencarian</a>
            @else
                <p class="text-muted">Belum ada pesanan.</p>
                <p class="mt-1 text-sm text-muted">Pesanan akan muncul di sini setelah pelanggan melakukan checkout.</p>
            @endif
        </div>
    @else
        <ul class="divide-y divide-line rounded-control border border-line bg-surface">
            @foreach ($orders as $order)
                <li>
                    <a href="{{ route('admin.orders.show', $order) }}" class="flex flex-col gap-2 px-4 py-3 hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ink sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $order->order_number }} <span class="font-normal text-muted">· {{ $order->customer_name }}</span></p>
                            <p class="text-sm text-muted">
                                {{ $order->created_at->locale('id')->isoFormat('D MMM Y, HH.mm') }}
                                · {{ $order->fulfillment_type->label() }}
                                · {{ $order->items_count }} item
                                · {{ \App\Support\Rupiah::format($order->total) }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <x-badge :tone="$order->status->tone()">{{ $order->status->label() }}</x-badge>
                            <x-badge :tone="$order->payment?->status->tone() ?? 'neutral'">Bayar: {{ $order->payment?->status->label() ?? 'Belum ada' }}</x-badge>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        {{ $orders->links('vendor.pagination.admin') }}
    @endif
@endsection
