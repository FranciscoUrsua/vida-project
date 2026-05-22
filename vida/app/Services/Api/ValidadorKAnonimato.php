<?php

namespace App\Services\Api;

use App\Exceptions\Anonimizacion\KAnonimatoValidacionException;
use Illuminate\Support\Collection;

/**
 * Validador y aplicador de k-anonimato sobre un conjunto de registros.
 *
 * Garantiza que cada combinación de cuasi-identificadores aparece al menos
 * K veces en el resultado. Cuando no se cumple, aplica una cascada de
 * generalización en orden estricto hasta alcanzar el umbral o suprimir el registro.
 *
 * Los cuasi-identificadores evaluados son: sexo, rango_edad, calle_generalizada,
 * colectivo_principal. Ver docs/anonimizacion.md § 6.5.
 *
 * Solo se usa en jobs asíncronos de extracción — nunca en tiempo real.
 */
class ValidadorKAnonimato
{
    /**
     * Aplica la cascada de generalización y valida k-anonimato sobre la colección.
     *
     * Los registros deben haber pasado ya por AnonimizadorService (supresión y
     * generalización de Nivel 2). Este validador opera sobre el conjunto resultante.
     *
     * Orden de la cascada (sin saltar pasos):
     * 1. rango_edad: anio (integer) → decada (string "AAAA-AAAA+9")
     * 2. calle_generalizada: nombre_via → distrito_proxy
     * 3. colectivo_principal: suprimir
     * 4. registro completo: suprimir
     *
     * Casos especiales (aplicados antes de la cascada):
     * - es_vvg = true: suprimir todos los campos de dirección
     * - es_psh = true: usar zona_intervencion como cuasi-identificador de calle
     * - colectivo_extra_protegido = true: suprimir colectivo_principal desde el inicio
     *
     * @param Collection<int, array<string, mixed>> $registros
     * @param int                                   $k         Umbral mínimo de apariciones
     * @return Collection<int, array<string, mixed>>
     *
     * @throws KAnonimatoValidacionException Si tras la cascada completa aún hay combinaciones < K
     */
    public function aplicar(Collection $registros, int $k): Collection
    {
        $dados = $registros->map(fn ($r) => (array) $r)->values();

        // Casos especiales — aplicar antes de la cascada
        $dados = $dados->map(fn (array $r) => $this->preprocesarEspeciales($r));

        // Cascada paso 1: rango_edad de anio a decada
        $dados = $this->aplicarPasoEnCascada($dados, $k, fn (array $r) => $this->generalizarRangoEdad($r));

        // Cascada paso 2: calle de nombre_via a distrito_proxy
        $dados = $this->aplicarPasoEnCascada($dados, $k, fn (array $r) => $this->generalizarCalle($r));

        // Cascada paso 3: suprimir colectivo_principal
        $dados = $this->aplicarPasoEnCascada($dados, $k, function (array $r): array {
            unset($r['colectivo_principal']);
            return $r;
        });

        // Cascada paso 4: suprimir el registro completo
        $llavesFiltrar = $this->obtenerLlavesCombinacionesProblematicas($dados, $k);
        $dados = $dados->filter(
            fn (array $r) => !in_array($this->obtenerLlaveCombinacion($r), $llavesFiltrar, true)
        )->values();

        // Validación final: si aún hay combinaciones < K, el job no puede continuar
        $llavesFinal = $this->obtenerLlavesCombinacionesProblematicas($dados, $k);
        if (!empty($llavesFinal)) {
            throw new KAnonimatoValidacionException($llavesFinal);
        }

        return $dados;
    }

    /**
     * Aplica una transformación solo a los registros pertenecientes a combinaciones < K,
     * luego reevalúa el conjunto completo.
     *
     * @param Collection<int, array<string, mixed>>                  $registros
     * @param callable(array<string, mixed>): array<string, mixed>   $transformacion
     * @return Collection<int, array<string, mixed>>
     */
    private function aplicarPasoEnCascada(Collection $registros, int $k, callable $transformacion): Collection
    {
        $llavesproblematicas = $this->obtenerLlavesCombinacionesProblematicas($registros, $k);

        if (empty($llavesproblematicas)) {
            return $registros;
        }

        return $registros->map(function (array $r) use ($llavesproblematicas, $transformacion): array {
            if (in_array($this->obtenerLlaveCombinacion($r), $llavesproblematicas, true)) {
                return $transformacion($r);
            }
            return $r;
        });
    }

    /**
     * Aplica los casos especiales de dominio antes de la cascada.
     *
     * @param array<string, mixed> $registro
     * @return array<string, mixed>
     */
    private function preprocesarEspeciales(array $registro): array
    {
        // VVG: suprimir todos los campos de dirección desde el inicio
        if (!empty($registro['es_vvg'])) {
            unset(
                $registro['nombre_via'],
                $registro['tipo_via'],
                $registro['numero'],
                $registro['portal'],
                $registro['escalera'],
                $registro['piso'],
                $registro['puerta'],
                $registro['codigo_postal'],
                $registro['distrito_proxy'],
                $registro['direccion_texto'],
                $registro['direccion_normalizada']
            );
        }

        // Colectivo extra-protegido: suprimir colectivo_principal en paso 0
        if (!empty($registro['colectivo_extra_protegido'])) {
            unset($registro['colectivo_principal']);
        }

        return $registro;
    }

    /**
     * Convierte rango_edad de precisión anio (integer de 4 dígitos) a decada (string).
     *
     * @param array<string, mixed> $registro
     * @return array<string, mixed>
     */
    private function generalizarRangoEdad(array $registro): array
    {
        // Solo actúa si rango_edad es un entero de 4 dígitos (precisión año)
        if (!isset($registro['anio_nacimiento']) || !is_int($registro['anio_nacimiento'])) {
            return $registro;
        }

        $anio         = $registro['anio_nacimiento'];
        $decadaInicio = (int) floor($anio / 10) * 10;
        $registro['rango_edad'] = "{$decadaInicio}-" . ($decadaInicio + 9);
        unset($registro['anio_nacimiento']);
        return $registro;
    }

    /**
     * Generaliza calle de nombre_via a distrito_proxy (primeros 3 dígitos del CP).
     *
     * @param array<string, mixed> $registro
     * @return array<string, mixed>
     */
    private function generalizarCalle(array $registro): array
    {
        if (!isset($registro['nombre_via'])) {
            return $registro;
        }

        // Intentar construir distrito_proxy desde codigo_postal si aún no existe
        if (!isset($registro['distrito_proxy']) && isset($registro['codigo_postal'])) {
            $registro['distrito_proxy'] = substr((string) $registro['codigo_postal'], 0, 3);
        }

        unset($registro['nombre_via'], $registro['tipo_via']);
        return $registro;
    }

    /**
     * Computa la clave de combinación de cuasi-identificadores para un registro.
     *
     * Para PSH, usa zona_intervencion en lugar de calle_generalizada.
     *
     * @param array<string, mixed> $registro
     */
    private function obtenerLlaveCombinacion(array $registro): string
    {
        if (!empty($registro['es_psh'])) {
            $calle = $registro['zona_intervencion'] ?? null;
        } else {
            $calle = $registro['nombre_via'] ?? $registro['distrito_proxy'] ?? null;
        }

        return serialize([
            'sexo'               => $registro['sexo'] ?? null,
            'rango_edad'         => $registro['anio_nacimiento'] ?? $registro['rango_edad'] ?? null,
            'calle_generalizada' => $calle,
            'colectivo_principal' => $registro['colectivo_principal'] ?? null,
        ]);
    }

    /**
     * Devuelve las claves serializadas de combinaciones que aparecen < K veces.
     *
     * @param Collection<int, array<string, mixed>> $registros
     * @return list<string>
     */
    private function obtenerLlavesCombinacionesProblematicas(Collection $registros, int $k): array
    {
        return $registros
            ->groupBy(fn (array $r) => $this->obtenerLlaveCombinacion($r))
            ->filter(fn (Collection $grupo) => $grupo->count() < $k)
            ->keys()
            ->all();
    }
}
