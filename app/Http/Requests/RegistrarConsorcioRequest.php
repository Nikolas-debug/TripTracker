<?php

namespace App\Http\Requests;

use App\Enums\TipoTarifa;
use App\Enums\TipoViaje;
use App\Enums\UnidadTarifa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El alta guiada de un contrato: consorcio + obras + tarifas, todo junto.
 */
class RegistrarConsorcioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'max:150'],
            'nit' => ['required', 'string', 'max:20', Rule::unique('consorcios', 'nit')],
            'departamento' => ['nullable', 'string', 'max:60'],
            'municipio' => ['nullable', 'string', 'max:60'],
            'direccion_principal' => ['nullable', 'string', 'max:150'],
            'correo' => ['nullable', 'email', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:20'],

            'obras' => ['required', 'array', 'min:1', 'max:20'],
            'obras.*.nombre' => ['required', 'string', 'max:120', 'distinct'],
            'obras.*.direccion' => ['nullable', 'string', 'max:150'],
            'obras.*.municipio' => ['nullable', 'string', 'max:60'],

            // Se puede crear una obra sin tarifas, pero se avisa: hasta que
            // no exista al menos una de cobro, no se pueden registrar viajes.
            'obras.*.tarifas' => ['array'],
            'obras.*.tarifas.*.tipo' => ['required', Rule::enum(TipoTarifa::class)],
            'obras.*.tarifas.*.tipo_viaje' => ['required', Rule::enum(TipoViaje::class)],
            'obras.*.tarifas.*.unidad' => ['required', Rule::enum(UnidadTarifa::class)],
            'obras.*.tarifas.*.cantera_id' => ['nullable', 'exists:canteras,id'],
            'obras.*.tarifas.*.botadero_id' => ['nullable', 'exists:botaderos,id'],
            'obras.*.tarifas.*.material_id' => ['nullable', 'exists:materiales,id'],
            'obras.*.tarifas.*.tercero_id' => ['nullable', 'exists:terceros,id'],
            'obras.*.tarifas.*.valor' => ['nullable', 'integer', 'min:0'],
            'obras.*.tarifas.*.valor_base' => ['nullable', 'integer', 'min:0'],
            'obras.*.tarifas.*.porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'obras.*.tarifas.*.fecha_inicio' => ['required', 'date'],
        ];
    }

    /**
     * Las combinaciones que la base rechazaría de todos modos, explicadas
     * en el idioma del negocio en vez de con un error de constraint.
     */
    public function withValidator($validador): void
    {
        $validador->after(function ($v) {
            foreach ($this->input('obras', []) as $i => $obra) {
                foreach ($obra['tarifas'] ?? [] as $j => $tarifa) {
                    $campo = "obras.{$i}.tarifas.{$j}";
                    $esRetiro = ($tarifa['tipo_viaje'] ?? null) === TipoViaje::Retiro->value;

                    if ($esRetiro && empty($tarifa['botadero_id'])) {
                        $v->errors()->add($campo, 'Una tarifa de retiro necesita el botadero: el precio depende de a dónde se lleve el escombro.');
                    }

                    if (! $esRetiro && empty($tarifa['cantera_id'])) {
                        $v->errors()->add($campo, 'Una tarifa de suministro necesita la cantera de origen.');
                    }

                    if (($tarifa['tipo'] ?? null) !== TipoTarifa::Cobro->value && empty($tarifa['tercero_id'])) {
                        $v->errors()->add($campo, 'Una tarifa de pago se pacta con un dueño o un conductor.');
                    }

                    if (($tarifa['tipo'] ?? null) === TipoTarifa::PagoConductor->value
                        && (empty($tarifa['valor_base']) || ! isset($tarifa['porcentaje']))) {
                        $v->errors()->add($campo, 'El pago al conductor es un porcentaje sobre una base fija.');
                    }
                }
            }
        });
    }
}
