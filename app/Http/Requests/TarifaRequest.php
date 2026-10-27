<?php

namespace App\Http\Requests;

use App\Enums\TipoTarifa;
use App\Enums\TipoViaje;
use App\Enums\UnidadTarifa;
use App\Models\Tarifa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TarifaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $esRetiro = $this->input('tipo_viaje') === TipoViaje::Retiro->value;
        $esConductor = $this->input('tipo') === TipoTarifa::PagoConductor->value;
        $esCobro = $this->input('tipo') === TipoTarifa::Cobro->value;

        return [
            'tipo' => ['required', Rule::enum(TipoTarifa::class)],
            'tipo_viaje' => ['required', Rule::enum(TipoViaje::class)],
            'obra_id' => ['required', 'exists:obras,id'],

            'cantera_id' => [Rule::requiredIf(! $esRetiro), Rule::prohibitedIf($esRetiro), 'nullable', 'exists:canteras,id'],
            'botadero_id' => [Rule::requiredIf($esRetiro), Rule::prohibitedIf(! $esRetiro), 'nullable', 'exists:botaderos,id'],

            // Solo para una excepción de precio en un material puntual de esa ruta.
            'material_id' => [Rule::prohibitedIf($esRetiro), 'nullable', 'exists:materiales,id'],

            // Las tarifas de pago siempre son con alguien.
            'tercero_id' => [Rule::requiredIf(! $esCobro), Rule::prohibitedIf($esCobro), 'nullable', 'exists:terceros,id'],

            'unidad' => ['required', Rule::enum(UnidadTarifa::class)],
            'valor' => [Rule::requiredIf(! $esConductor), 'nullable', 'integer', 'min:0'],
            'valor_base' => [Rule::requiredIf($esConductor), 'nullable', 'integer', 'min:0'],
            'porcentaje' => [Rule::requiredIf($esConductor), 'nullable', 'numeric', 'min:0', 'max:100'],

            'fecha_inicio' => ['required', 'date'],
            'motivo_cambio' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * Dos tarifas abiertas para la misma ruta harían ambigua la resolución.
     * Para un precio nuevo en una ruta que ya tiene tarifa está "Cambiar".
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $existe = Tarifa::abiertas()
                ->where('tipo', $this->input('tipo'))
                ->where('tipo_viaje', $this->input('tipo_viaje'))
                ->where('obra_id', $this->input('obra_id'))
                ->where('cantera_id', $this->input('cantera_id'))
                ->where('botadero_id', $this->input('botadero_id'))
                ->where('material_id', $this->input('material_id'))
                ->where('tercero_id', $this->input('tercero_id'))
                ->exists();

            if ($existe) {
                $validator->errors()->add('obra_id', 'Esa ruta ya tiene una tarifa vigente de este tipo. Usa "Cambiar" en la lista.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'tercero_id.required' => 'Una tarifa de pago se pacta con un dueño o un conductor.',
            'tercero_id.prohibited' => 'La tarifa de cobro es con el consorcio de la obra, no con un tercero.',
            'valor_base.required' => 'El pago al conductor es un porcentaje sobre una base fija.',
        ];
    }
}
