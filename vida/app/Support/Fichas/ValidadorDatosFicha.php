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

    /**
     * Comprueba que cada valor tiene la forma de su tipo de campo (número, fecha, sí/no,
     * opción de la lista, texto). La usan las fichas de la persona, que entran por un servicio
     * propio (`FichaPersonaService`); los catálogos los comprueba `validar()`.
     *
     * @param array<int, array<string, mixed>> $campos Campos del schema con el que se guarda.
     * @param array<string, mixed> $datos Valores por id de campo.
     * @param string $prefijo Prefijo de las claves de error.
     *
     * @return void
     *
     * @throws ValidationException Si algún valor no tiene la forma de su tipo.
     */
    public static function validarTipos(array $campos, array $datos, string $prefijo = 'datos'): void
    {
        $errores = [];

        foreach ($campos as $campo) {
            $id = $campo['id'] ?? null;
            if (! is_string($id) || ! ValorCampo::tieneValor($datos[$id] ?? null)) {
                continue;
            }

            $error = self::errorDeTipo($campo, $datos[$id]);
            if ($error !== null) {
                $errores["{$prefijo}.{$id}"] = $error;
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * Mensaje de error si el valor no tiene la forma de su tipo; null si es válido.
     *
     * @param array<string, mixed> $campo Definición del campo.
     * @param mixed $valor Valor (no vacío).
     *
     * @return string|null
     */
    private static function errorDeTipo(array $campo, mixed $valor): ?string
    {
        return match ($campo['tipo'] ?? 'texto') {
            'numero', 'escala' => is_numeric($valor) ? null : 'Escribe un número.',
            'fecha' => is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) && checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))
                ? null : 'Escribe una fecha válida.',
            'booleano' => in_array($valor, [true, false, 1, 0, '1', '0'], true) ? null : 'Elige sí o no.',
            'select' => in_array($valor, $campo['opciones'] ?? [], true) ? null : 'Elige una opción de la lista.',
            'catalogo' => is_string($valor) ? null : 'Elige un valor de la lista.',
            default => is_scalar($valor) ? null : 'Valor no válido.',
        };
    }
}
