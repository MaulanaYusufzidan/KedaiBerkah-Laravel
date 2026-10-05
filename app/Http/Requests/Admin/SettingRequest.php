<?php

namespace App\Http\Requests\Admin;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Akses dijaga middleware auth + admin pada route.
    }

    /** Bersihkan format @username / URL Instagram sebelum divalidasi. */
    protected function prepareForValidation(): void
    {
        $instagram = trim((string) $this->input('instagram'));
        $instagram = preg_replace('#^(https?://)?(www\.)?instagram\.com/#i', '', $instagram);
        $instagram = ltrim((string) preg_replace('#[/?].*$#', '', $instagram), '@');

        $this->merge(['instagram' => $instagram]);
    }

    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:100'],
            'shop_tagline' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'string', 'max:500'],
            'whatsapp_number' => [
                'nullable', 'string', 'max:30',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && $value !== '' && Phone::normalize($value) === null) {
                        $fail('Nomor WhatsApp tidak valid. Gunakan nomor seluler Indonesia, contoh 0878-7462-7555.');
                    }
                },
            ],
            'instagram' => ['nullable', 'string', 'regex:/^[A-Za-z0-9._]{1,30}$/'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'regex:/^[0-9][0-9 \-]{3,28}[0-9]$/'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'payment_instructions' => ['nullable', 'string', 'max:500'],
            'qris' => [
                'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000',
            ],
            'remove_qris' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'shop_name.required' => 'Nama toko wajib diisi.',
            'shop_name.max' => 'Nama toko maksimal 100 karakter.',
            'shop_tagline.max' => 'Deskripsi singkat maksimal 150 karakter.',
            'address.max' => 'Alamat maksimal 500 karakter.',
            'opening_hours.max' => 'Jam operasional maksimal 500 karakter.',
            'whatsapp_number.max' => 'Nomor WhatsApp terlalu panjang.',
            'instagram.regex' => 'Username Instagram hanya boleh berisi huruf, angka, titik, dan garis bawah (maksimal 30 karakter).',
            'bank_name.max' => 'Nama bank maksimal 50 karakter.',
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka (5–30 digit), boleh dengan spasi atau strip.',
            'bank_account_name.max' => 'Nama pemilik rekening maksimal 100 karakter.',
            'payment_instructions.max' => 'Instruksi pembayaran maksimal 500 karakter.',
            'qris.uploaded' => 'Gambar QRIS gagal diunggah. Ukuran berkas mungkin melebihi batas server.',
            'qris.file' => 'QRIS harus berupa berkas yang diunggah.',
            'qris.image' => 'QRIS harus berupa gambar JPG, PNG, atau WebP.',
            'qris.mimes' => 'QRIS harus berupa gambar JPG, PNG, atau WebP.',
            'qris.mimetypes' => 'QRIS harus berupa gambar JPG, PNG, atau WebP.',
            'qris.max' => 'Ukuran gambar QRIS maksimal 2 MB.',
            'qris.dimensions' => 'Dimensi gambar QRIS harus antara 200×200 dan 6000×6000 piksel.',
            'remove_qris.boolean' => 'Pilihan hapus QRIS tidak valid.',
        ];
    }
}
