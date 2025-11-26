<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

class ValidarIdentificacionEspanola implements ValidationRule
{
    public ?string $tipo = null;  // <-- Cambiado a ?string para permitir null

    public function __construct(?string $tipo = null)  // <-- Nullable en param
    {
        $this->tipo = $tipo;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;  // Nullable, salta
        }

        $tipo = $this->tipo ?? request('tipo_documento');  // Fallback a request si null

        if (empty($tipo)) {
            $fail('Tipo de documento requerido para validar el número.');
            return;
        }

        switch ($tipo) {
            case 'dni':
                if (!preg_match('/^\d{8}[TRWAGMYFPDXBNJZSQVHLCKE]$/', $value)) {
                    $fail('El DNI debe tener 8 dígitos + letra válida.');
                    return;
                }
                $numero = substr($value, 0, 8);
                $letra = substr($value, 8, 1);
                $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
                $pos = $numero % 23;
                if ($letras[$pos] !== $letra) {
                    $fail('La letra del DNI no coincide con el cálculo.');
                }
                break;

            case 'nie':
                if (!preg_match('/^[XYZ]\d{7}[TRWAGMYFPDXBNJZSQVHLCKE]$/', $value)) {
                    $fail('El NIE debe tener X/Y/Z + 7 dígitos + letra válida.');
                    return;
                }
                $numero = str_replace(['X', 'Y', 'Z'], [0, 1, 2], $value);
                $numero = substr($numero, 0, 8);  # Convierte a 8 dígitos para cálculo
                $letra = substr($value, 8, 1);
                $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
                $pos = $numero % 23;
                if ($letras[$pos] !== $letra) {
                    $fail('La letra del NIE no coincide con el cálculo.');
                }
                break;

            case 'pasaporte':
                if (!preg_match('/^[A-Z]{3}\d{6,7}$/', $value)) {  # Formato aproximado español
                    $fail('El pasaporte debe tener 3 letras + 6-7 dígitos.');
                }
                break;

            case 'otro':
                // Libre, solo formato básico
                if (!preg_match('/^[A-Z0-9]{1,20}$/', $value)) {
                    $fail('El ID "otro" debe ser alfanumérico, máx 20 chars.');
                }
                break;

            default:
                $fail('Tipo de documento inválido.');
        }
    }
}
