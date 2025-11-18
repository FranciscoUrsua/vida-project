<?php

namespace Modules\Intervencion\Services;

use Modules\Intervencion\Models\TipoFicha;
use Illuminate\Validation\ValidationException;

class FichaService
{
    /**
     * Valida datos JSON contra TipoFicha (por ahora: existe nombre y JSON válido; futuro: contra schema).
     * Devuelve true si OK.
     */
    public function validarDatos(string $nombreTipo, array $datos): bool
    {
        // Chequeo básico: TipoFicha existe
        $tipoFicha = TipoFicha::porNombre($nombreTipo);
        if (!$tipoFicha) {
            throw new ValidationException('Tipo de ficha no encontrado: ' . $nombreTipo);
        }

        // Chequeo JSON: debe ser array no vacío (futuro: validar keys contra $tipoFicha->schema)
        if (!is_array($datos) || empty($datos)) {
            throw new ValidationException('Datos deben ser un JSON/array no vacío.');
        }

        // Por ahora, siempre OK si pasa lo anterior
        return true;
    }

    /**
     * Método helper para crear Ficha con validación.
     */
    public function crearFicha(array $data): Ficha
    {
        $this->validarDatos($data['nombre_tipo'], $data['datos']);
        // Extrae tipo_ficha_id del nombre
        $tipoFicha = TipoFicha::porNombre($data['nombre_tipo']);
        $data['tipo_ficha_id'] = $tipoFicha->id;

        return Ficha::create($data);
    }
}
