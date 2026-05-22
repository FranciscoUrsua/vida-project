<?php

namespace App\Services\Api;

use App\Exceptions\Anonimizacion\PerfilAnonimizacionInactivoException;
use App\Exceptions\Anonimizacion\PerfilAnonimizacionNotFoundException;
use App\Models\Api\PerfilAnonimizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Servicio de anonimización de registros.
 *
 * Capa de transformación que actúa después del descifrado de campos sensibles
 * y antes de serializar la respuesta o el fichero de extracción. Recibe una
 * colección de registros (modelos Eloquent o arrays) y un perfil, y devuelve
 * la colección transformada como arrays — nunca modelos Eloquent.
 *
 * No tiene dependencias con ningún módulo funcional.
 * Ver docs/anonimizacion.md §§ 3 y 6.1.
 */
class AnonimizadorService
{
    /**
     * Aplica el perfil de anonimización a la colección de registros.
     *
     * @param Collection<int, Model|array<string, mixed>> $registros
     * @param string                                      $perfilId  Nombre del perfil
     * @return Collection<int, array<string, mixed>>
     *
     * @throws PerfilAnonimizacionNotFoundException Si el perfil no existe
     * @throws PerfilAnonimizacionInactivoException Si el perfil existe pero está inactivo
     */
    public function anonimizar(Collection $registros, string $perfilId): Collection
    {
        $perfil = PerfilAnonimizacion::where('nombre', $perfilId)->first();

        if ($perfil === null) {
            throw new PerfilAnonimizacionNotFoundException($perfilId);
        }

        if ($perfil->estado !== 'activo') {
            throw new PerfilAnonimizacionInactivoException($perfilId);
        }

        return $registros->map(function (Model|array $registro) use ($perfil): array {
            $datos = $registro instanceof Model ? $registro->toArray() : (array) $registro;

            foreach ($perfil->campos as $definicion) {
                $campo   = $definicion['campo'];
                $tecnica = $definicion['tecnica'];

                $datos = match ($tecnica) {
                    'suprimir'    => $this->aplicarSupresion($datos, $campo),
                    'seudonimizar' => $this->aplicarSeudonimizacion($datos, $campo),
                    'generalizar' => $this->aplicarGeneralizacion($datos, $campo, $definicion),
                    'mantener'    => $this->aplicarMantener($datos, $campo),
                    default       => $datos,
                };
            }

            return $datos;
        });
    }

    /**
     * Elimina el campo del array resultado.
     *
     * @param array<string, mixed> $registro
     * @return array<string, mixed>
     */
    private function aplicarSupresion(array $registro, string $campo): array
    {
        unset($registro[$campo]);
        return $registro;
    }

    /**
     * Sustituye el valor por un alias opaco y consistente basado en el id del registro.
     *
     * El alias es CIU- + primeros 8 caracteres de HMAC-SHA256($id, APP_PSEUDONYM_KEY).
     * Si el campo es 'id', añade 'alias_ciudadano' y elimina 'id'.
     * Si el campo es cualquier otro, reemplaza su valor con el alias.
     *
     * @param array<string, mixed> $registro
     * @return array<string, mixed>
     */
    private function aplicarSeudonimizacion(array $registro, string $campo): array
    {
        $id    = $registro['id'] ?? null;
        $alias = 'CIU-' . substr(hash_hmac('sha256', (string) $id, config('app.pseudonym_key')), 0, 8);

        if ($campo === 'id') {
            $registro['alias_ciudadano'] = $alias;
            unset($registro['id']);
        } else {
            $registro[$campo] = $alias;
        }

        return $registro;
    }

    /**
     * Reduce la precisión del campo según la técnica de generalización configurada.
     *
     * @param array<string, mixed>  $registro
     * @param array<string, string> $definicion Configuración del campo del perfil
     * @return array<string, mixed>
     */
    private function aplicarGeneralizacion(array $registro, string $campo, array $definicion): array
    {
        $precision = $definicion['precision'] ?? '';

        return match ($precision) {
            'anio'            => $this->generalizarAnio($registro, $campo),
            'decada'          => $this->generalizarDecada($registro, $campo),
            'calle_sin_numero' => $this->generalizarCalleSinNumero($registro, $campo, $definicion),
            'distrito_proxy'  => $this->generalizarDistritoProxy($registro, $campo),
            default           => $registro,
        };
    }

    /**
     * Extrae el año de una fecha. Resultado: integer en clave 'anio_nacimiento'.
     *
     * @param array<string, mixed> $registro
     * @return array<string, mixed>
     */
    private function generalizarAnio(array $registro, string $campo): array
    {
        if (!isset($registro[$campo])) {
            return $registro;
        }

        $registro['anio_nacimiento'] = (int) substr((string) $registro[$campo], 0, 4);
        unset($registro[$campo]);
        return $registro;
    }

    /**
     * Extrae la década de una fecha. Resultado: string "1940-1949".
     *
     * @param array<string, mixed> $registro
     * @return array<string, mixed>
     */
    private function generalizarDecada(array $registro, string $campo): array
    {
        if (!isset($registro[$campo])) {
            return $registro;
        }

        $anio         = (int) substr((string) $registro[$campo], 0, 4);
        $decadaInicio = (int) floor($anio / 10) * 10;
        $registro['rango_edad'] = "{$decadaInicio}-" . ($decadaInicio + 9);
        unset($registro[$campo]);
        return $registro;
    }

    /**
     * Generaliza la dirección a nivel de calle sin número.
     *
     * Requiere que direccion_normalizada sea true. Si no lo es, aplica supresión
     * completa del campo (fallback según la especificación del perfil).
     * Cuando aplica, conserva tipo_via y nombre_via y elimina los campos de detalle.
     *
     * @param array<string, mixed>  $registro
     * @param array<string, string> $definicion
     * @return array<string, mixed>
     */
    private function generalizarCalleSinNumero(array $registro, string $campo, array $definicion): array
    {
        if (empty($registro['direccion_normalizada'])) {
            // Sin normalización geográfica no se puede garantizar la extracción fiable del nombre de vía
            unset($registro[$campo]);
            return $registro;
        }

        // Eliminar campos de detalle: queda tipo_via + nombre_via
        unset($registro['numero'], $registro['portal'], $registro['escalera'],
              $registro['piso'], $registro['puerta']);
        return $registro;
    }

    /**
     * Generaliza el código postal a los primeros 3 dígitos (distrito proxy).
     *
     * @param array<string, mixed> $registro
     * @return array<string, mixed>
     */
    private function generalizarDistritoProxy(array $registro, string $campo): array
    {
        if (!isset($registro[$campo])) {
            return $registro;
        }

        $registro['distrito_proxy'] = substr((string) $registro[$campo], 0, 3);
        unset($registro[$campo]);
        return $registro;
    }

    /**
     * No modifica el campo.
     *
     * @param array<string, mixed> $registro
     * @return array<string, mixed>
     */
    private function aplicarMantener(array $registro, string $campo): array
    {
        return $registro;
    }
}
