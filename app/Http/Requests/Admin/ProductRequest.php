<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Akses dijaga middleware auth + admin pada route.
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('products', 'name')->ignore($this->route('product')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Pilih kategori.',
            'category_id.integer' => 'Kategori tidak valid.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
            'name.required' => 'Nama produk wajib diisi.',
            'name.max' => 'Nama produk maksimal 150 karakter.',
            'name.unique' => 'Nama produk ini sudah dipakai.',
            'description.max' => 'Deskripsi maksimal 1000 karakter.',
            'base_price.required' => 'Harga wajib diisi.',
            'base_price.integer' => 'Harga harus berupa angka bulat dalam rupiah, tanpa titik atau koma.',
            'base_price.min' => 'Harga tidak boleh negatif.',
            'base_price.max' => 'Harga maksimal Rp10.000.000.',
            'is_active.*' => 'Status tidak valid.',
        ];
    }
}
