<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait ValidatesIdentification
{
    private static $letrasDni = 'TRWAGMYFPDXBNJZSQVHLCKE';

    protected static function bootValidatesIdentification()
    {
        static::saving(function (Model $model) {
            if ($model->identificacion_desconocida) {
                $model->identificacion_validada = false; // Skip validación, pero marca como no validada
                return;
            }

            if (!$model->numero_id || !$model->tipo_documento) {
                throw ValidationException::withMessages([
                    'numero_id' => 'Número de ID y tipo son obligatorios.',
                ]);
            }

            $validation = $model->validateIdentification($model->tipo_documento, $model->numero_id);

            if (!$validation['success']) {
                Log::warning('ID inválido bloqueado: ' . $validation['error'] . ' para ' . $model->getTable() . ' ID ' . ($model->id ?? 'new'));
                throw ValidationException::withMessages([
                    'numero_id' => $validation['error'],
                ]);
            }

            $model->identificacion_validada = true;

            // Hash para RGPD en historial (manejo robusto de string/null)
            $historialRaw = $model->identificacion_historial;
            $historial = [];
            if (is_string($historialRaw)) {
                $decoded = json_decode($historialRaw, true);
                $historial = is_array($decoded) ? $decoded : [];
            } elseif (is_array($historialRaw)) {
                $historial = $historialRaw;
            } // Null → []

            $historial[now()->format('Y-m')] = Hash::make($model->numero_id);
            $model->identificacion_historial = $historial;
        });
    }

    /**
     * Valida formato y checksum. Retorna array con success.
     * @param string $tipo
     * @param string $numero
     * @return array ['success' => bool, 'error' => ?string]
     */
    public function validateIdentification(string $tipo, string $numero): array
    {
        $numero = strtoupper(trim($numero));

        switch ($tipo) {
            case 'dni':
                return $this->validateDni($numero);

            case 'nie':
                return $this->validateNie($numero);

            case 'pasaporte':
                return $this->validatePasaporte($numero);

            case 'otro':
                return ['success' => true, 'error' => null];

            default:
                return ['success' => false, 'error' => 'Tipo no soportado.'];
        }
    }

    private function validateDni(string $dni): array
    {
        if (!preg_match('/^\d{8}[TRWAGMYFPDXBNJZSQVHLCKE]$/', $dni)) {
            return ['success' => false, 'error' => 'Formato DNI inválido (8 dígitos + letra válida).'];
        }

        $numero = substr($dni, 0, 8);
        $letra = substr($dni, 8, 1);

        $checksum = (int) $numero % 23;
        $letraCalculada = self::$letrasDni[$checksum];

        if ($letra !== $letraCalculada) {
            return ['success' => false, 'error' => 'Letra de checksum incorrecta para DNI (calculada: ' . $letraCalculada . ').'];
        }

        return ['success' => true, 'error' => null];
    }

    private function validateNie(string $nie): array
    {
        if (!preg_match('/^[XYZ]\d{7,8}[TRWAGMYFPDXBNJZSQVHLCKE]$/', $nie)) {
            return ['success' => false, 'error' => 'Formato NIE inválido (X/Y/Z + 7-8 dígitos + letra válida).'];
        }

        $numero = str_replace(['X', 'Y', 'Z'], [0, 1, 2], $nie);
        $numero = substr($numero, 0, -1); // Sin letra final
        $letra = substr($nie, -1);

        $checksum = (int) $numero % 23;
        $letraCalculada = self::$letrasDni[$checksum];

        if ($letra !== $letraCalculada) {
            return ['success' => false, 'error' => 'Letra de checksum incorrecta para NIE (calculada: ' . $letraCalculada . ').'];
        }

        return ['success' => true, 'error' => null];
    }

    private function validatePasaporte(string $pasaporte): array
    {
        if (!preg_match('/^[A-Z]{3}\d{6}$/', $pasaporte)) {
            return ['success' => false, 'error' => 'Formato Pasaporte inválido (3 letras mayúsculas + 6 dígitos).'];
        }

        return ['success' => true, 'error' => null];
    }
}
