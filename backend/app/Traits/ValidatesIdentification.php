<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait ValidatesIdentification
{
    private static $letrasDni = 'TRWAGMYFPDXBNJZSQVHLCKE'; // Confirmado 23 chars

    protected static function bootValidatesIdentification()
    {
        static::saving(function (Model $model) {
            if ($model->identificacion_desconocida ?? false) {
                $model->identificacion_validada = false;
                return;
            }

            $tipo = $model->tipo_documento ?? $model->tipo_id ?? null;
            $numero = $model->numero_id ?? null;

            Log::debug('Validación ID: tipo=' . ($tipo ?? 'null') . ', numero=' . ($numero ?? 'null') . ' para ' . $model->getTable()); // Debug campos

            if (!$tipo || !$numero) {
                Log::warning('Campos ID obligatorios faltantes en ' . $model->getTable());
                throw ValidationException::withMessages([
                    'numero_id' => 'Número de ID y tipo son obligatorios.',
                ]);
            }

            $validation = $model->validateIdentification($tipo, $numero);

            if (!$validation['success']) {
                Log::warning('ID inválido bloqueado: ' . $validation['error'] . ' para ' . $model->getTable() . ' ID ' . ($model->id ?? 'new'));
                throw ValidationException::withMessages([
                    'numero_id' => $validation['error'],
                ]);
            }

            $model->identificacion_validada = true;

            // Hash para RGPD en historial
            $historialRaw = $model->identificacion_historial;
            $historial = [];
            if (is_string($historialRaw)) {
                $decoded = json_decode($historialRaw, true);
                $historial = is_array($decoded) ? $decoded : [];
            } elseif (is_array($historialRaw)) {
                $historial = $historialRaw;
            }

            $historial[now()->format('Y-m')] = Hash::make($numero);
            $model->identificacion_historial = $historial;
        });
    }

    public function validateIdentification(string $tipo, string $numero): array
    {
        $numero = strtoupper(trim($numero));

        switch (strtoupper($tipo)) {
            case 'DNI':
                return $this->validateDni($numero);

            case 'NIE':
                return $this->validateNie($numero);

            case 'PASAPORTE':
                return $this->validatePasaporte($numero);

            case 'OTRO':
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
        if (!is_numeric($numero)) {
            return ['success' => false, 'error' => 'Los 8 primeros caracteres deben ser dígitos.'];
        }

        $letra = substr($dni, 8, 1);

        $checksum = (int) $numero % 23;
        $letraCalculada = self::$letrasDni[$checksum];

        Log::debug('DNI calc: numero=' . $numero . ', mod=' . $checksum . ', letra_calculada=' . $letraCalculada . ', letra_real=' . $letra); // DEBUG: Para ver cálculo

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
        $numero = substr($numero, 0, -1);
        if (!is_numeric($numero)) {
            return ['success' => false, 'error' => 'Los dígitos del NIE deben ser numéricos.'];
        }

        $letra = substr($nie, -1);

        $checksum = (int) $numero % 23;
        $letraCalculada = self::$letrasDni[$checksum];

        Log::debug('NIE calc: numero=' . $numero . ', mod=' . $checksum . ', letra_calculada=' . $letraCalculada . ', letra_real=' . $letra); // DEBUG

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
