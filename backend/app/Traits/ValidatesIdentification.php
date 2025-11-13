<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash; // Para hashing si se necesita privacidad

trait ValidatesIdentification
{
    // Letras válidas para checksum DNI/NIE
    private static $letrasDni = 'TRWAGMYFPDXBNJZSQVHLCKE';

    // Boot para auto-validación en saving
    protected static function bootValidatesIdentification()
    {
        static::saving(function (Model $model) {
            if ($model->identificacion_desconocida) {
                return; // Skip si desconocida
            }

            if (!$model->numero_id || !$model->tipo_documento) {
                return; // Skip si no proporcionado
            }

            $isValid = $model->validateIdentification($model->tipo_documento, $model->numero_id);

            if (!$isValid) {
                throw ValidationException::withMessages([
                    'numero_id' => 'Número de ID inválido para el tipo ' . $model->tipo_documento . '.',
                ]);
            }

            // Opcional: Hash para privacidad RGPD (guarda hashed en DB si no ya)
            if (!$model->identificacion_historial || empty($model->identificacion_historial)) {
                $model->identificacion_historial = json_encode([
                    now()->format('Y-m') => Hash::make($model->numero_id),
                ]);
            }
        });
    }

    /**
     * Valida formato y checksum según tipo.
     * @param string $tipo 'dni', 'nie', 'pasaporte', 'otro'
     * @param string $numero
     * @return bool
     */
    public function validateIdentification(string $tipo, string $numero): bool
    {
        $numero = strtoupper(trim($numero)); // Normaliza

        switch ($tipo) {
            case 'dni':
                return $this->validateDni($numero);

            case 'nie':
                return $this->validateNie($numero);

            case 'pasaporte':
                return $this->validatePasaporte($numero);

            case 'otro':
                return true; // Skip validación para otros

            default:
                throw ValidationException::withMessages(['tipo_documento' => 'Tipo no soportado.']);
        }
    }

    private function validateDni(string $dni): bool
    {
        // Formato: 8 dígitos + 1 letra
        if (!preg_match('/^\d{8}[TRWAGMYFPDXBNJZSQVHLCKE]$/', $dni)) {
            return false;
        }

        $numero = substr($dni, 0, 8);
        $letra = substr($dni, 8, 1);

        $checksum = (int) $numero % 23;
        $letraCalculada = self::$letrasDni[$checksum];

        return $letra === $letraCalculada;
    }

    private function validateNie(string $nie): bool
    {
        // Formato: X/Y/Z + 7/8 dígitos + letra
        if (!preg_match('/^[XYZ]\d{7,8}[TRWAGMYFPDXBNJZSQVHLCKE]$/', $nie)) {
            return false;
        }

        // Extrae número (reemplaza X=0, Y=1, Z=2)
        $numero = str_replace(['X', 'Y', 'Z'], [0, 1, 2], $nie);
        $numero = substr($numero, 0, -1); // Sin letra final
        $letra = substr($nie, -1);

        $checksum = (int) $numero % 23;
        $letraCalculada = self::$letrasDni[$checksum];

        return $letra === $letraCalculada;
    }

    private function validatePasaporte(string $pasaporte): bool
    {
        // Formato español: 3 letras + 6 dígitos (sin checksum)
        return preg_match('/^[A-Z]{3}\d{6}$/', $pasaporte);
    }
}
