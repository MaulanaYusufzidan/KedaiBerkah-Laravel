<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur resmi untuk mengubah status pesanan dan pembayaran dari admin.
 *
 * Setiap aksi: dijalankan dalam transaksi, mengunci baris pesanan (lockForUpdate), memeriksa
 * ulang status TERKINI (bukan status di halaman admin yang mungkin sudah usang), lalu mencatat
 * riwayat. Permintaan ganda atau tombol yang ditekan dua kali ditolak karena statusnya sudah berubah.
 *
 * Aturan inti: pesanan hanya bisa masuk "processing" lewat verifikasi yang menetapkan pembayaran "paid".
 */
class OrderWorkflow
{
    public const ACTIONS = [
        'mark_proof_received' => 'Bukti diterima (via WhatsApp)',
        'verify_payment' => 'Verifikasi pembayaran & mulai proses',
        'reject_payment' => 'Tolak bukti pembayaran',
        'mark_ready' => 'Tandai siap diambil',
        'start_delivery' => 'Mulai pengiriman',
        'complete' => 'Tandai selesai',
        'cancel' => 'Batalkan pesanan',
    ];

    /** Aksi yang wajib disertai alasan. */
    public const NOTE_REQUIRED = ['reject_payment', 'cancel'];

    /** @return list<string> aksi yang valid untuk status pesanan saat ini */
    public function availableActions(Order $order): array
    {
        return $this->actionsFor($order, $this->latestPayment($order));
    }

    /**
     * @throws OrderTransitionException jika aksi tidak diizinkan pada status terkini
     */
    public function apply(Order $order, string $action, User $by, ?string $note = null): Order
    {
        if (! array_key_exists($action, self::ACTIONS)) {
            throw new OrderTransitionException('Aksi tidak dikenal.');
        }

        $note = $note !== null ? trim($note) : null;
        $note = $note === '' ? null : $note;

        if (in_array($action, self::NOTE_REQUIRED, true) && $note === null) {
            throw new OrderTransitionException('Alasan wajib diisi.');
        }

        return DB::transaction(function () use ($order, $action, $by, $note) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = $locked->payments()->latest('id')->lockForUpdate()->first();

            $this->guard($locked, $payment, $action);

            $from = $locked->status;
            [$to, $defaultNote] = $this->perform($locked, $payment, $action, $by, $note);

            $locked->forceFill(['status' => $to])->save();

            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $by->id,
                'note' => $note ?? $defaultNote,
            ]);

            return $locked->refresh();
        });
    }

    private function guard(Order $order, ?Payment $payment, string $action): void
    {
        if ($action === 'start_delivery' && $order->isPickup()) {
            throw new OrderTransitionException('Pesanan ambil sendiri tidak melalui pengiriman.');
        }
        if ($action === 'mark_ready' && ! $order->isPickup()) {
            throw new OrderTransitionException('Pesanan antar tidak memakai status siap diambil. Langsung mulai pengiriman.');
        }

        if (! in_array($action, $this->actionsFor($order, $payment), true)) {
            throw new OrderTransitionException('Aksi tidak dapat dilakukan karena status pesanan sudah berubah. Muat ulang halaman.');
        }

        if ($action === 'verify_payment' && (int) $payment->amount !== (int) $order->total) {
            throw new OrderTransitionException(sprintf(
                'Nominal pembayaran (%s) tidak sama dengan total pesanan (%s). Periksa kembali.',
                Rupiah::format($payment->amount),
                Rupiah::format($order->total),
            ));
        }
    }

    /** @return array{0: OrderStatus, 1: string} status baru dan catatan bawaan */
    private function perform(Order $order, ?Payment $payment, string $action, User $by, ?string $note): array
    {
        switch ($action) {
            case 'mark_proof_received':
                if ($payment && $payment->status === PaymentStatus::Pending) {
                    $payment->forceFill(['status' => PaymentStatus::WaitingVerification])->save();
                } else {
                    // Percobaan baru (belum ada pembayaran, atau yang lama ditolak/kedaluwarsa): riwayat lama dipertahankan.
                    Payment::create(['order_id' => $order->id, 'payment_method' => 'transfer', 'amount' => $order->total])
                        ->forceFill(['status' => PaymentStatus::WaitingVerification])->save();
                }

                return [OrderStatus::WaitingVerification, 'Bukti pembayaran diterima via WhatsApp, menunggu verifikasi'];

            case 'verify_payment':
                $payment->forceFill([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                    'verified_at' => now(),
                    'verified_by' => $by->id,
                    'rejection_reason' => null,
                ])->save();

                return [OrderStatus::Processing, 'Pembayaran diverifikasi, pesanan mulai diproses'];

            case 'reject_payment':
                $payment->forceFill([
                    'status' => PaymentStatus::Rejected,
                    'verified_at' => now(),
                    'verified_by' => $by->id,
                    'rejection_reason' => $note,
                ])->save();

                return [OrderStatus::PendingPayment, 'Bukti pembayaran ditolak'];

            case 'mark_ready':
                return [OrderStatus::Ready, 'Pesanan siap diambil'];

            case 'start_delivery':
                return [OrderStatus::Delivering, 'Pesanan mulai diantar'];

            case 'complete':
                return [OrderStatus::Completed, 'Pesanan selesai'];

            case 'cancel':
                if ($payment && in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::WaitingVerification], true)) {
                    $payment->forceFill(['status' => PaymentStatus::Expired])->save();
                }

                return [OrderStatus::Cancelled, 'Pesanan dibatalkan'];
        }

        throw new OrderTransitionException('Aksi tidak dikenal.');
    }

    /** @return list<string> */
    private function actionsFor(Order $order, ?Payment $payment): array
    {
        $pickup = $order->isPickup();

        return match ($order->status) {
            OrderStatus::PendingPayment => ['mark_proof_received', 'cancel'],
            OrderStatus::WaitingVerification => $payment?->status === PaymentStatus::WaitingVerification
                ? ['verify_payment', 'reject_payment', 'cancel']
                : ['cancel'],
            OrderStatus::Processing => [$pickup ? 'mark_ready' : 'start_delivery', 'cancel'],
            OrderStatus::Ready => $pickup ? ['complete'] : ['start_delivery'],
            OrderStatus::Delivering => $pickup ? [] : ['complete'],
            default => [],
        };
    }

    private function latestPayment(Order $order): ?Payment
    {
        return $order->payments()->latest('id')->first();
    }
}
