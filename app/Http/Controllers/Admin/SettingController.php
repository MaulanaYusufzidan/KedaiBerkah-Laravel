<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Models\Setting;
use App\Support\ImageProcessingException;
use App\Support\ImageStorage;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class SettingController extends Controller
{
    private const QRIS_DIR = 'qris';

    /** Key teks yang disimpan apa adanya (kosong -> null). */
    private const TEXT_KEYS = [
        'shop_name', 'shop_tagline', 'address', 'opening_hours', 'instagram',
        'bank_name', 'bank_account_name', 'payment_instructions',
    ];

    public function edit(): View
    {
        $values = [];
        foreach ([...self::TEXT_KEYS, 'whatsapp_number', 'bank_account_number', 'qris_image'] as $key) {
            $values[$key] = Setting::get($key);
        }

        return view('admin.settings.edit', [
            'values' => $values,
            'whatsappDisplay' => Phone::display($values['whatsapp_number']),
            'whatsappTestUrl' => Setting::whatsappUrl('Halo, ini uji tautan WhatsApp dari pengaturan toko.'),
            'qrisUrl' => ImageStorage::url($values['qris_image']),
        ]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $oldQris = Setting::get('qris_image');
        $remove = $request->boolean('remove_qris');

        try {
            $newQris = $request->hasFile('qris')
                ? ImageStorage::store($request->file('qris'), self::QRIS_DIR, 1600, 92)
                : null;
        } catch (ImageProcessingException $e) {
            // Tidak ada setting yang berubah dan QRIS lama tidak disentuh.
            return back()->withInput()->withErrors(['qris' => $e->getMessage()]);
        }

        try {
            DB::transaction(function () use ($data, $newQris, $remove) {
                foreach (self::TEXT_KEYS as $key) {
                    Setting::set($key, $this->clean($data[$key] ?? null));
                }

                Setting::set('whatsapp_number', Phone::normalize($data['whatsapp_number'] ?? null));
                Setting::set('bank_account_number', $this->digitsOnly($data['bank_account_number'] ?? null));

                if ($newQris) {
                    Setting::set('qris_image', $newQris);
                } elseif ($remove) {
                    Setting::set('qris_image', null);
                }
            });
        } catch (Throwable $e) {
            ImageStorage::delete($newQris, self::QRIS_DIR);
            throw $e;
        }

        if (($newQris || $remove) && $oldQris && $oldQris !== Setting::get('qris_image')) {
            ImageStorage::delete($oldQris, self::QRIS_DIR);
        }

        return redirect()->route('admin.settings.edit')->with('status', 'Pengaturan berhasil disimpan.');
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function digitsOnly(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }
}
