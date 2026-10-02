<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case WaitingVerification = 'waiting_verification';
    case Processing = 'processing';
    case Ready = 'ready';
    case Delivering = 'delivering';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu Pembayaran',
            self::WaitingVerification => 'Menunggu Verifikasi',
            self::Processing => 'Sedang Diproses',
            self::Ready => 'Siap',
            self::Delivering => 'Sedang Diantar',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
