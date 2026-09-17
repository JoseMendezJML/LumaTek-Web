<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGreenhouseRequest extends FormRequest
{
    /**
     * Determina si el usuario puede realizar esta solicitud.
     *
     * La autorización por empresa/rol se controlará posteriormente
     * desde el controlador y middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación del perfil del invernadero.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'crop_type' => [
                'required',
                'string',
                'max:120',
            ],

            'area' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999.99',
            ],

            'location' => [
                'required',
                'string',
                'max:255',
            ],

            'planting_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],

            'nominal_flow' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999.99',
            ],
        ];
    }

    /**
     * Mensajes personalizados en español.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del invernadero es obligatorio.',
            'name.string' => 'El nombre del invernadero debe ser texto.',
            'name.max' => 'El nombre del invernadero no puede superar los 150 caracteres.',

            'crop_type.required' => 'El tipo de cultivo es obligatorio.',
            'crop_type.string' => 'El tipo de cultivo debe ser texto.',
            'crop_type.max' => 'El tipo de cultivo no puede superar los 120 caracteres.',

            'area.required' => 'El área del invernadero es obligatoria.',
            'area.numeric' => 'El área debe ser un valor numérico.',
            'area.gt' => 'El área debe ser mayor que 0.',
            'area.max' => 'El área ingresada es demasiado grande.',

            'location.required' => 'La ubicación es obligatoria.',
            'location.string' => 'La ubicación debe ser texto.',
            'location.max' => 'La ubicación no puede superar los 255 caracteres.',

            'planting_date.required' => 'La fecha de siembra es obligatoria.',
            'planting_date.date' => 'La fecha de siembra no es válida.',
            'planting_date.before_or_equal' => 'La fecha de siembra no puede ser posterior a hoy.',

            'nominal_flow.required' => 'El caudal nominal es obligatorio.',
            'nominal_flow.numeric' => 'El caudal nominal debe ser un valor numérico.',
            'nominal_flow.gt' => 'El caudal nominal debe ser mayor que 0.',
            'nominal_flow.max' => 'El caudal nominal ingresado es demasiado grande.',
        ];
    }

    /**
     * Normaliza algunos valores antes de validarlos.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name)
                ? trim($this->name)
                : $this->name,

            'crop_type' => is_string($this->crop_type)
                ? trim($this->crop_type)
                : $this->crop_type,

            'location' => is_string($this->location)
                ? trim($this->location)
                : $this->location,
        ]);
    }
}