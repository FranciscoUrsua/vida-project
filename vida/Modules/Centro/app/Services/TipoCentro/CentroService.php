<?php

namespace Modules\Centro\Services\TipoCentro;

use App\Models\User;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoCentro;

/**
 * Alta y baja de centros (docs/modulo-tipo-centro.md §8 y RN-01).
 *
 * El tipo se fija al alta y no se cambia: para «cambiar de tipo» se da de baja
 * el centro y se da de alta otro con este como antecesor (§8.3). No hay borrado.
 */
class CentroService
{
    /**
     * Da de alta un centro de un tipo activo, con su denominación inicial.
     *
     * @param array<string, mixed> $datos Datos del centro (nombre, nombre_corto, tipo_gestion, UO, contacto…).
     * @param TipoCentro $tipo Tipo del centro (debe estar activo).
     * @param Centro|null $antecesor Centro al que sustituye.
     * @param User|null $actor Usuario que lo crea.
     *
     * @throws DomainException Si el tipo está de baja o falta el nombre.
     *
     * @return Centro
     */
    public function darDeAlta(array $datos, TipoCentro $tipo, ?Centro $antecesor, ?User $actor): Centro
    {
        if (blank($datos['nombre'] ?? null)) {
            throw new DomainException('Falta el nombre del centro.');
        }

        $alta = Carbon::parse($datos['fecha_alta'] ?? today());

        return DB::transaction(function () use ($datos, $tipo, $antecesor, $actor, $alta): Centro {
            $centro = Centro::create(array_merge(
                Arr::except($datos, ['tipo_centro_id', 'centro_antecesor_id', 'activo', 'fecha_baja']),
                [
                    'tipo_centro_id' => $tipo->id,
                    'centro_antecesor_id' => $antecesor?->id,
                    'activo' => true,
                    'fecha_alta' => $alta->toDateString(),
                ],
            ));

            $centro->denominaciones()->create([
                'nombre' => $centro->nombre,
                'nombre_corto' => mb_substr($centro->nombre_corto ?? $centro->nombre, 0, 50),
                'vigente_desde' => $alta->toDateString(),
                'creado_por_id' => $actor?->id,
            ]);

            return $centro;
        });
    }

    /**
     * Da de baja un centro (baja lógica): sigue existiendo con sus historias,
     * asignaciones y prescripciones.
     *
     * @param Centro $centro Centro que se da de baja.
     * @param Carbon $fecha Fecha de baja.
     * @param User|null $actor Usuario que la ejecuta (queda en la versión).
     *
     * @return Centro
     */
    public function darDeBaja(Centro $centro, Carbon $fecha, ?User $actor): Centro
    {
        $centro->update(['activo' => false, 'fecha_baja' => $fecha->toDateString()]);

        return $centro;
    }
}
