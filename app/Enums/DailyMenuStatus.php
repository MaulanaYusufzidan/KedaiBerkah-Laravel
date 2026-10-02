<?php

namespace App\Enums;

enum DailyMenuStatus: string
{
    case Scheduled = 'scheduled';
    case Available = 'available';
    case SoldOut = 'sold_out';
    case Inactive = 'inactive';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Dijadwalkan',
            self::Available => 'Tersedia',
            self::SoldOut => 'Habis',
            self::Inactive => 'Nonaktif',
            self::Expired => 'Berakhir',
        };
    }
}
