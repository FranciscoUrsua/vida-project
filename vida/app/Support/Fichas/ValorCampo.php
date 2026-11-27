<?php

namespace App\Support\Fichas;

use App\Models\CatalogoSistema;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Formato único del valor guardado de un campo de ficha para pantalla y PDF (SD-5).
 *
 * Todas las vistas de fichas guardadas pasan por aquí, para que un tipo de campo se muestre
 * igual en todas. Para `catalogo` devuelve la etiqueta actual de la clave aunque el valor ya
 * no esté activo ni vigente (`CatalogoSistema::etiquetaDe()`): la ficha guarda la clave, nunca
 * la etiqueta. La unidad de los números no se incluye: cada vista la pinta a su manera.
 */
final class ValorCampo
{
    /**
     * Texto para mostrar el valor de un campo; cadena vacía si no hay respuesta.
     *
     * @param array<string, mixed> $campo Definición del campo (del `schema_snapshot`).
     * @param mixed $valor Valor guardado en `datos`.
     * @param string $formatoFecha Formato de Carbon para las fechas (el PDF usa `d/m/Y`).
     *
     * @return string
     */
    public static function mostrar(array $campo, mixed $valor, string $formatoFecha = 'j M Y'): string
    {
        if (! self::tieneValor($valor)) {
            return '';
        }

        return match ($campo['tipo'] ?? 'texto') {
            'booleano' => in_array($valor, [true, 1, '1', 'true', 'si', 'sí'], true) ? 'Sí' : 'No',
            'fecha' => self::fecha($valor, $formatoFecha),
            'catalogo' => self::catalogo($campo, $valor),
            default => is_array($valor) ? implode(', ', array_map('strval', $valor)) : (string) $valor,
        };
    }

    /**
     * Si un valor guardado cuenta como respuesta (nulo, cadena vacía o lista vacía no cuentan).
     *
     * @param mixed $valor Valor guardado.
     *
     * @return bool
     */
    public static function tieneValor(mixed $valor): bool
    {
        return $valor !== null && $valor !== '' && $valor !== [];
    }

    /**
     * Fecha formateada; si no se puede interpretar, el texto tal cual.
     *
     * @param mixed $valor Fecha guardada.
     * @param string $formato Formato de Carbon.
     *
     * @return string
     */
    private static function fecha(mixed $valor, string $formato): string
    {
        try {
            return Carbon::parse((string) $valor)->translatedFormat($formato);
        } catch (Throwable) {
            return (string) $valor;
        }
    }

    /**
     * Etiqueta actual de la clave en el catálogo del campo; la clave si ya no existe.
     *
     * @param array<string, mixed> $campo Definición del campo.
     * @param mixed $valor Clave guardada.
     *
     * @return string
     */
    private static function catalogo(array $campo, mixed $valor): string
    {
        $grupo = $campo['catalogo'] ?? null;

        return (is_string($grupo) ? CatalogoSistema::etiquetaDe($grupo, (string) $valor) : null) ?? (string) $valor;
    }
}
