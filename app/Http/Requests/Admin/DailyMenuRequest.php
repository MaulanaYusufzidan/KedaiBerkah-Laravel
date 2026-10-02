<?php

namespace App\Http\Requests\Admin;

use App\Enums\DailyMenuStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Akses dijaga middleware auth + admin pada route.
    }

    public function rules(): array
    {
        $menu = $this->route('daily_menu'); // null saat membuat baru
        $productId = $menu?->product_id ?? (int) $this->input('product_id');

        return [
            // Produk hanya dipilih saat membuat, dan harus produk aktif.
            'product_id' => $menu
                ? ['prohibited']
                : ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'menu_date' => [
                'required', 'date_format:Y-m-d',
                Rule::unique('daily_menus', 'menu_date')
                    ->where('product_id', $productId)
                    ->ignore($menu?->id),
            ],
            // Saat membuat, harga boleh kosong (dipakai harga dasar produk).
            'price' => [$menu ? 'required' : 'nullable', 'integer', 'min:0', 'max:10000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'available_from' => ['nullable', 'date_format:H:i'],
            'available_until' => [
                'nullable', 'date_format:H:i',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $from = $this->input('available_from');
                    if ($from && $value && $value <= $from) {
                        $fail('Jam selesai harus setelah jam mulai.');
                    }
                },
            ],
            'status' => ['required', Rule::enum(DailyMenuStatus::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Pilih produk.',
            'product_id.exists' => 'Produk tidak ditemukan atau tidak aktif.',
            'product_id.prohibited' => 'Produk pada menu yang sudah dibuat tidak dapat diganti.',
            'menu_date.required' => 'Tanggal wajib diisi.',
            'menu_date.date_format' => 'Tanggal tidak valid.',
            'menu_date.unique' => 'Produk tersebut sudah ditambahkan ke menu pada tanggal yang dipilih.',
            'price.required' => 'Harga wajib diisi.',
            'price.integer' => 'Harga harus berupa angka bulat dalam rupiah, tanpa titik atau koma.',
            'price.min' => 'Harga tidak boleh negatif.',
            'price.max' => 'Harga maksimal Rp10.000.000.',
            'stock.required' => 'Stok wajib diisi.',
            'stock.integer' => 'Stok harus berupa angka bulat.',
            'stock.min' => 'Stok tidak boleh negatif.',
            'stock.max' => 'Stok maksimal 100.000.',
            'available_from.date_format' => 'Format jam mulai tidak valid.',
            'available_until.date_format' => 'Format jam selesai tidak valid.',
            'status.required' => 'Pilih status.',
            'status.enum' => 'Status tidak valid.',
            'notes.max' => 'Catatan maksimal 255 karakter.',
        ];
    }
}
