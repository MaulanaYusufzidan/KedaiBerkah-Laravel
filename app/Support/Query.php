<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Pembaca parameter query yang tahan input aneh (misalnya ?q[]=x), supaya halaman
 * daftar tidak berubah menjadi error 500 hanya karena URL diutak-atik.
 */
class Query
{
    public static function text(Request $request, string $key, int $max = 100): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    public static function int(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        return is_string($value) && ctype_digit($value) && $value !== '' ? (int) $value : null;
    }
}
