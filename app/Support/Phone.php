<?php

namespace App\Support;

class Phone
{
    /**
     * Normalisasi nomor seluler Indonesia ke format internasional tanpa tanda (628...).
     * Contoh: "0878-7462-7555", "+62 878 7462 7555", "62878...", "878..." -> "628787462 7555" (tanpa spasi).
     * Mengembalikan null jika bukan nomor seluler Indonesia yang masuk akal.
     */
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);          // 0062... -> 62...
        }

        if (str_starts_with($digits, '620')) {
            $digits = '62'.substr($digits, 3);     // 62 0878... (nol berlebih) -> 62878...
        } elseif (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);     // 0878... -> 62878...
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;                // 878... -> 62878...
        }

        return preg_match('/^628\d{8,12}$/', $digits) ? $digits : null;
    }

    /** Tampilan lokal yang mudah dibaca: 6287874627555 -> 0878-7462-7555. */
    public static function display(?string $normalized): ?string
    {
        if (! $normalized) {
            return null;
        }

        $local = str_starts_with($normalized, '62') ? '0'.substr($normalized, 2) : $normalized;

        return preg_replace('/^(\d{4})(\d{4})(\d+)$/', '$1-$2-$3', $local) ?? $local;
    }

    /** Tautan Click-to-Chat WhatsApp dengan teks yang di-encode dengan benar. */
    public static function whatsappUrl(?string $normalized, ?string $text = null): ?string
    {
        if (! $normalized) {
            return null;
        }

        return 'https://wa.me/'.$normalized.($text !== null && $text !== '' ? '?text='.rawurlencode($text) : '');
    }
}
