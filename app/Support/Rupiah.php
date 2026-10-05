<?php

namespace App\Support;

class Rupiah
{
    public static function format(int|float|null $amount): string
    {
        return 'Rp'.number_format((int) $amount, 0, ',', '.');
    }

    /** Format ringkas untuk label sumbu grafik: Rp500, Rp500 rb, Rp1,5 jt, Rp2 M. */
    public static function short(int|float|null $amount): string
    {
        $n = (int) $amount;

        return match (true) {
            $n >= 1_000_000_000 => 'Rp'.self::trim($n / 1_000_000_000).' M',
            $n >= 1_000_000 => 'Rp'.self::trim($n / 1_000_000).' jt',
            $n >= 1_000 => 'Rp'.self::trim($n / 1_000).' rb',
            default => 'Rp'.$n,
        };
    }

    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }
}
