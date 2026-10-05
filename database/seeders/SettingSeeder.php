<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Hanya mengisi nilai yang sudah diberikan pemilik. Setting yang sudah diubah
     * admin tidak ditimpa; yang belum diketahui dibiarkan kosong, bukan dikarang.
     */
    public function run(): void
    {
        $defaults = [
            'shop_name' => 'Kedai Berkah',
            'shop_tagline' => 'Spesialisasi Olahan Ayam',
            'address' => 'Jl. Kp. Kukupu Gg. Jarum, RT.02/RW.08, Cibadak, Tanah Sareal, Kota Bogor, Jawa Barat 16166',
            'whatsapp_number' => '6287874627555',
            'opening_hours' => null,
            'bank_name' => null,
            'bank_account_number' => null,
            'bank_account_name' => null,
            'qris_image' => null,
            'payment_instructions' => null,
            'instagram' => null,
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
