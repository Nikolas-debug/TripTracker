<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnticipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'valor' => ['required', 'integer', 'min:1'],
            'observacion' => ['nullable', 'string', 'max:150'],
        ];
    }
}
