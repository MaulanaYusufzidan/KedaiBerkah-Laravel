@extends('layouts.admin')

@section('title', 'Pesanan '.$order->order_number)

@section('content')
    <p class="mb-2 text-sm"><a href="{{ route('admin.orders.index') }}" class="underline">← Semua pesanan</a></p>

    <x-admin.page-header :title="$order->order_number">
        <div class="flex flex-wrap gap-1">
            <x-badge :tone="$order->status->tone()">{{ $order->status->label() }}</x-badge>
            <x-badge :tone="$payment?->status->tone() ?? 'neutral'">Bayar: {{ $payment?->status->label() ?? 'Belum ada' }}</x-badge>
        </div>
    </x-admin.page-header>
    <p class="-mt-3 mb-5 text-sm text-muted">Dibuat {{ $order->created_at->locale('id')->isoFormat('dddd, D MMMM Y, HH.mm') }} WIB</p>

    {{-- Aksi: hanya yang valid untuk status saat ini. Server memeriksa ulang saat dikirim. --}}
    <section class="mb-8" aria-labelledby="aksi">
        <h2 id="aksi" class="mb-2 text-lg font-semibold">Tindakan</h2>

        @if ($order->status === \App\Enums\OrderStatus::PendingPayment)
            <p class="mb-3 text-sm text-muted">Pelanggan mengirim bukti pembayaran lewat WhatsApp. Pesanan baru berpindah status setelah Anda menandainya di sini.</p>
        @elseif ($order->status === \App\Enums\OrderStatus::WaitingVerification)
            <p class="mb-3 text-sm text-muted">Periksa bukti pembayaran, lalu verifikasi atau tolak. Pesanan baru diproses setelah pembayaran diverifikasi.</p>
        @endif

        @if (! $actions)
            <p class="text-muted">Tidak ada tindakan untuk pesanan {{ strtolower($order->status->label()) }}.</p>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($actions as $action)
                    @if (in_array($action, $noteRequired, true))
                        <details class="rounded-control border border-line bg-surface" @if (old('action') === $action) open @endif>
                            <summary class="btn {{ $action === 'cancel' ? 'btn-danger' : 'btn-quiet' }} w-full justify-start sm:w-auto">{{ $actionLabels[$action] }}</summary>
                            <form method="POST" action="{{ route('admin.orders.transition', $order) }}" class="p-4" novalidate
                                  data-confirm="{{ $actionLabels[$action] }}?">
                                @csrf
                                <input type="hidden" name="action" value="{{ $action }}">
                                <label for="note-{{ $action }}" class="field-label">Alasan (wajib)</label>
                                <textarea id="note-{{ $action }}" name="note" rows="2" maxlength="255" required class="field-input py-2"
                                          @if (old('action') === $action) aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}" @endif>{{ old('action') === $action ? old('note') : '' }}</textarea>
                                @if (old('action') === $action)
                                    @error('note') <p class="field-error">{{ $message }}</p> @enderror
                                @endif
                                <button type="submit" class="btn {{ $action === 'cancel' ? 'btn-danger' : 'btn-primary' }} mt-3">{{ $actionLabels[$action] }}</button>
                            </form>
                        </details>
                    @else
                        <form method="POST" action="{{ route('admin.orders.transition', $order) }}">
                            @csrf
                            <input type="hidden" name="action" value="{{ $action }}">
                            <button type="submit" class="btn btn-primary w-full sm:w-auto">{{ $actionLabels[$action] }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
        @endif
    </section>

    <section class="mb-8" aria-labelledby="pelanggan">
        <h2 id="pelanggan" class="mb-2 text-lg font-semibold">Pelanggan dan pengambilan</h2>
        <dl class="divide-y divide-line rounded-control border border-line bg-surface">
            <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-muted">Nama</dt>
                <dd class="sm:col-span-2">{{ $order->customer_name }}</dd>
            </div>
            <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-muted">Nomor WhatsApp</dt>
                <dd class="sm:col-span-2">
                    {{ $order->customer_phone }}
                    @if ($customerWhatsapp)
                        <a href="{{ $customerWhatsapp }}" target="_blank" rel="noopener noreferrer" class="ml-2 inline-block py-2 text-sm underline">Hubungi via WhatsApp</a>
                    @endif
                </dd>
            </div>
            <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-muted">Pengambilan</dt>
                <dd class="sm:col-span-2">{{ $order->fulfillment_type->label() }}</dd>
            </div>
            @unless ($order->isPickup())
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm text-muted">Area pengiriman</dt>
                    <dd class="sm:col-span-2">{{ $order->areaName() ?? 'Tidak tercatat' }}</dd>
                </div>
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm text-muted">Alamat</dt>
                    <dd class="whitespace-pre-line sm:col-span-2">{{ $order->address ?: 'Tidak tercatat' }}</dd>
                </div>
            @endunless
            @if ($order->notes)
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm text-muted">Catatan pelanggan</dt>
                    <dd class="whitespace-pre-line sm:col-span-2">{{ $order->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="mb-8" aria-labelledby="item">
        <h2 id="item" class="mb-2 text-lg font-semibold">Item pesanan</h2>
        <ul class="divide-y divide-line rounded-control border border-line bg-surface">
            @foreach ($order->items as $item)
                <li class="flex items-baseline justify-between gap-3 px-4 py-3">
                    <span>{{ $item->product_name }}
                        <span class="text-sm text-muted">· {{ $item->quantity }} × {{ \App\Support\Rupiah::format($item->unit_price) }}</span></span>
                    <span class="shrink-0">{{ \App\Support\Rupiah::format($item->line_total) }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="mt-2 space-y-1 px-1 text-sm">
            <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd>{{ \App\Support\Rupiah::format($order->subtotal) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Ongkos kirim</dt><dd>{{ \App\Support\Rupiah::format($order->delivery_fee) }}</dd></div>
            <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd>{{ \App\Support\Rupiah::format($order->total) }}</dd></div>
        </dl>
        <p class="mt-1 px-1 text-sm text-muted">Nama dan harga item adalah data saat pesanan dibuat, tidak ikut berubah jika menu diedit.</p>
    </section>

    <section class="mb-8" aria-labelledby="pembayaran">
        <h2 id="pembayaran" class="mb-2 text-lg font-semibold">Pembayaran</h2>
        @if ($order->payments->isEmpty())
            <p class="rounded-control border border-line bg-surface px-4 py-3 text-muted">Belum ada pembayaran tercatat.</p>
        @else
            <ul class="divide-y divide-line rounded-control border border-line bg-surface">
                @foreach ($order->payments->sortByDesc('id') as $row)
                    <li class="px-4 py-3">
                        <p class="font-medium">
                            {{ \App\Support\Rupiah::format($row->amount) }} · {{ ucfirst($row->payment_method) }}
                            <x-badge :tone="$row->status->tone()" class="ml-1 align-middle">{{ $row->status->label() }}</x-badge>
                        </p>
                        @if ($row->verified_at)
                            <p class="text-sm text-muted">
                                {{ $row->status === \App\Enums\PaymentStatus::Paid ? 'Diverifikasi' : 'Diperiksa' }}
                                {{ $row->verified_at->locale('id')->isoFormat('D MMM Y, HH.mm') }} oleh {{ $row->verifier?->name ?? 'admin (akun sudah dihapus)' }}
                            </p>
                        @endif
                        @if ($row->rejection_reason)
                            <p class="mt-1 text-sm">Alasan penolakan: {{ $row->rejection_reason }}</p>
                        @endif
                        @if ($row->proof && $loop->first)
                            <p class="mt-1 text-sm"><a href="{{ route('admin.orders.proof', $order) }}" target="_blank" rel="noopener noreferrer" class="underline">Lihat bukti pembayaran</a></p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="riwayat">
        <h2 id="riwayat" class="mb-2 text-lg font-semibold">Riwayat status</h2>
        @if ($order->statusHistories->isEmpty())
            <p class="rounded-control border border-line bg-surface px-4 py-3 text-muted">Belum ada perubahan status tercatat.</p>
        @else
            <ol class="divide-y divide-line rounded-control border border-line bg-surface">
                @foreach ($order->statusHistories as $history)
                    <li class="px-4 py-3">
                        <p class="font-medium">
                            @if ($history->from_status) {{ $history->from_status->label() }} → @endif{{ $history->to_status->label() }}
                        </p>
                        <p class="text-sm text-muted">
                            {{ $history->created_at->locale('id')->isoFormat('D MMM Y, HH.mm') }} WIB
                            · {{ $history->changedBy?->name ?? 'Sistem' }}
                        </p>
                        @if ($history->note)
                            <p class="mt-1 text-sm">{{ $history->note }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@endsection
