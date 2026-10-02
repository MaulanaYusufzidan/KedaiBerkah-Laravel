<?php

namespace App\Enums;

enum FulfillmentType: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Diantar',
            self::Pickup => 'Ambil Sendiri',
        };
    }
}
