<?php

namespace App\Support\Fichas;

use App\Models\CatalogoSistema;
use Illuminate\Validation\ValidationException;

/**
 * Validación en el servidor de los datos de una ficha frente a su schema, común a todos los
 * servicios que guardan fichas (valoración, solicitud de prestación, ficha de la persona).
 *
 * Existe porque la vista no basta: una petición forjada puede mandar cualquier valor. Hoy
 * comprueba los campos `catalogo` (SD-4): el valor debe ser una clave vigente del grupo del
 * campo. Una clave de otro grupo, inexistente o que ya no está vigente se rechaza.
 */
final class ValidadorDatosFicha
{
    /**
     * Comprueba los datos y lanza una excepción de validación con el primer error de cada campo.
     *
     * @param array<int, array<string, mixed>> $campos Campos del schema con el que se guarda.
     * @param array<string, mixed> $datos Valores por id de campo.
     * @param string $prefijo Prefijo de las claves de error (la ruta `wire:model` de los datos).
     *
     * @return void
     *
     * @throws ValidationException Si algún valor no es válido.
     */
    public static function validar(array $campos, array $datos, string $prefijo = 'datos'): void
    {
        $errores = [];

        foreach ($campos as $campo) {
            $id = $campo['id'] ?? null;
            if (! is_string($id) || ($campo['tipo'] ?? null) !== 'catalogo' || ! ValorCampo::tieneValor($datos[$id] ?? null)) {
                continue;
            }

            $valor = $datos[$id];
            $vigente = is_string($valor)
                && is_string($campo['catalogo'] ?? null)
                && CatalogoSistema::where('grupo', $campo['catalogo'])->where('clave', $valor)->vigentes()->exists();

            if (! $vigente) {
                $errores["{$prefijo}.{$id}"] = 'Elige un valor de la lista.';
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }
}
