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
            'image' => [
                'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048', 'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000',
            ],
            'remove_image' => ['nullable', 'boolean'],
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
            'image.uploaded' => 'Gambar gagal diunggah. Ukuran berkas mungkin melebihi batas server.',
            'image.file' => 'Gambar harus berupa berkas yang diunggah.',
            'image.image' => 'Berkas harus berupa gambar JPG, PNG, atau WebP.',
            'image.mimes' => 'Berkas harus berupa gambar JPG, PNG, atau WebP.',
            'image.mimetypes' => 'Berkas harus berupa gambar JPG, PNG, atau WebP.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
            'image.dimensions' => 'Dimensi gambar harus antara 100×100 dan 6000×6000 piksel.',
            'remove_image.boolean' => 'Pilihan hapus gambar tidak valid.',
        ];
    }
}
