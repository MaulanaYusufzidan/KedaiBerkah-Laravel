<?php

namespace App\Http\Requests\Admin;

use App\Support\OrderWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Akses dijaga middleware auth + admin; aturan alur dijaga OrderWorkflow.
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(array_keys(OrderWorkflow::ACTIONS))],
            'note' => [
                Rule::requiredIf(fn () => in_array($this->input('action'), OrderWorkflow::NOTE_REQUIRED, true)),
                'nullable', 'string', 'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Pilih aksi.',
            'action.in' => 'Aksi tidak dikenal.',
            'note.required' => 'Alasan wajib diisi.',
            'note.max' => 'Alasan maksimal 255 karakter.',
        ];
    }
}
