<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmarValeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'obra_id' => ['required', 'exists:obras,id'],

            // El número es del talonario de la obra: único ahí, no en todo el sistema.
            'numero' => [
                'required', 'integer', 'min:1',
                Rule::unique('vales')->where('obra_id', $this->integer('obra_id')),
            ],

            'fecha_vale' => ['required', 'date', 'before_or_equal:today'],
            'fecha_recepcion' => ['nullable', 'date', 'after_or_equal:fecha_vale'],
            'firmado_por' => ['nullable', 'string', 'max:120'],
            'observacion' => ['nullable', 'string', 'max:1000'],

            'viajes' => ['required', 'array', 'min:1'],
            'viajes.*.viaje_id' => ['required', 'distinct', 'exists:viajes,id'],
            'viajes.*.m3_confirmado' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero.unique' => 'Ya existe un vale con ese número en esta obra.',
            'viajes.required' => 'Un vale se registra junto con los viajes que ampara.',
        ];
    }

    /** @return array<string, mixed> */
    public function datosVale(): array
    {
        return $this->safe()->only(['numero', 'obra_id', 'fecha_vale', 'fecha_recepcion', 'firmado_por', 'observacion']);
    }

    /** @return array<int, array<string, mixed>> */
    public function viajes(): array
    {
        return $this->input('viajes');
    }
}
