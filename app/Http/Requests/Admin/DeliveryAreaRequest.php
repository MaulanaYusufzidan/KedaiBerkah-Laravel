<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Akses dijaga middleware auth + admin pada route.
    }

    public function rules(): array
    {
        return [
            'district' => [
                'required', 'string', 'max:100',
                Rule::unique('delivery_areas', 'district')->ignore($this->route('delivery_area')),
            ],
            'delivery_fee' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'district.required' => 'Nama area wajib diisi.',
            'district.max' => 'Nama area maksimal 100 karakter.',
            'district.unique' => 'Nama area ini sudah ada.',
            'delivery_fee.required' => 'Biaya kirim wajib diisi.',
            'delivery_fee.integer' => 'Biaya kirim harus berupa angka bulat dalam rupiah, tanpa titik atau koma.',
            'delivery_fee.min' => 'Biaya kirim tidak boleh negatif.',
            'delivery_fee.max' => 'Biaya kirim maksimal Rp1.000.000.',
            'is_active.*' => 'Status tidak valid.',
        ];
    }
}
