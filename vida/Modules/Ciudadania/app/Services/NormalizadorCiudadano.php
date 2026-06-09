<?php

namespace Modules\Ciudadania\Services;

/**
 * Servicio de normalización de datos de ciudadano.
 *
 * Convierte los valores introducidos por el profesional a formato canónico
 * antes de la búsqueda de duplicados y el guardado. No lanza excepciones:
 * si el formato no es válido devuelve el valor original.
 */
class NormalizadorCiudadano
{
    /** Abreviaturas de nombre con expansión unívoca. */
    private const ABREVIATURAS_NOMBRE = [
        'Mª' => 'María',
        'M.ª' => 'María',
        'Mª.' => 'María',
    ];

    /**
     * Normaliza un número de documento a formato canónico.
     *
     * NIF: 8 dígitos + letra verificada, mayúsculas, sin espacios ni guiones.
     * NIE: X/Y/Z + 7 dígitos + letra, formato X-NNNNNNN-L.
     * Pasaporte: mayúsculas, sin espacios.
     *
     * @param string $tipo  nif | nie | pasaporte
     * @param string $valor Valor tal como lo introduce el profesional.
     */
    public static function documento(string $tipo, string $valor): string
    {
        $limpio = strtoupper(preg_replace('/[\s\-]/', '', $valor));

        return match ($tipo) {
            'nif' => $limpio,
            'nie' => preg_match('/^[XYZ]\d{7}[A-Z]$/', $limpio)
                ? substr($limpio, 0, 1) . '-' . substr($limpio, 1, 7) . '-' . substr($limpio, 8, 1)
                : $valor,
            'pasaporte' => $limpio,
            default     => $valor,
        };
    }

    /**
     * Normaliza un nombre o apellido a Title Case, eliminando espacios múltiples
     * y expandiendo abreviaturas unívocas.
     *
     * @param string $valor
     */
    public static function nombre(string $valor): string
    {
        $v = trim(preg_replace('/\s+/', ' ', $valor));

        foreach (self::ABREVIATURAS_NOMBRE as $abrev => $expansion) {
            $v = str_replace($abrev, $expansion, $v);
        }

        return mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Normaliza un número de teléfono eliminando espacios, guiones y paréntesis.
     * Añade prefijo +34 si empieza por 6, 7, 8 o 9 sin prefijo internacional.
     *
     * @param string $valor
     */
    public static function telefono(string $valor): string
    {
        $limpio = preg_replace('/[\s\-\(\)]/', '', $valor);

        if (preg_match('/^[6789]\d{8}$/', $limpio)) {
            return '+34' . $limpio;
        }

        return $limpio;
    }

    /**
     * Normaliza un email a minúsculas sin espacios.
     *
     * @param string $valor
     */
    public static function email(string $valor): string
    {
        return strtolower(trim($valor));
    }

    /**
     * Aplica todos los normalizadores sobre un array de datos de formulario.
     *
     * Claves reconocidas: nombre, apellido1, apellido2, tipo_documento,
     * valor_documento, telefono, email. Los campos no reconocidos se devuelven intactos.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public static function normalizar(array $datos): array
    {
        if (!empty($datos['nombre'])) {
            $datos['nombre'] = static::nombre($datos['nombre']);
        }
        if (!empty($datos['apellido1'])) {
            $datos['apellido1'] = static::nombre($datos['apellido1']);
        }
        if (!empty($datos['apellido2'])) {
            $datos['apellido2'] = static::nombre($datos['apellido2']);
        }
        if (!empty($datos['valor_documento']) && !empty($datos['tipo_documento'])) {
            $datos['valor_documento'] = static::documento($datos['tipo_documento'], $datos['valor_documento']);
        }
        if (!empty($datos['telefono'])) {
            $datos['telefono'] = static::telefono($datos['telefono']);
        }
        if (!empty($datos['email'])) {
            $datos['email'] = static::email($datos['email']);
        }

        return $datos;
    }
}
