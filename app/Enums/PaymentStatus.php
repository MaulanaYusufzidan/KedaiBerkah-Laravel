<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case WaitingVerification = 'waiting_verification';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum Dibayar',
            self::WaitingVerification => 'Menunggu Verifikasi',
            self::Paid => 'Lunas',
            self::Rejected => 'Ditolak',
            self::Expired => 'Kedaluwarsa',
        };
    }
}
