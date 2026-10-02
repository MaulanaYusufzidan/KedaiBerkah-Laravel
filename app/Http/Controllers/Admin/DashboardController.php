<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $whatsapp = Setting::get('whatsapp_number');

        // Hanya data yang memang ada di tabel settings. Yang kosong ditampilkan "Belum diisi".
        $shop = [
            'Nama kedai' => Setting::get('shop_name'),
            'Alamat' => Setting::get('address'),
            'WhatsApp' => $whatsapp ? $this->formatPhone($whatsapp) : null,
            'Jam operasional' => Setting::get('opening_hours'),
            'Rekening' => Setting::get('bank_account_number'),
            'Instagram' => Setting::get('instagram'),
        ];

        return view('admin.dashboard', ['shop' => $shop]);
    }

    private function formatPhone(string $number): string
    {
        $local = str_starts_with($number, '62') ? '0'.substr($number, 2) : $number;

        return preg_replace('/^(\d{4})(\d{4})(\d+)$/', '$1-$2-$3', $local) ?? $local;
    }
}
