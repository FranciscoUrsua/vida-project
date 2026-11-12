<?php

namespace Modules\Centro\Services\TipoCentro;

use App\Models\User;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Centro\Models\TipoCentro;

/**
 * Alta y baja de tipos de centro (docs/modulo-tipo-centro.md §8 y RN-11).
 *
 * Crear un tipo deja su primera denominación vigente desde el alta; los
 * renombrados van por `programarDenominacion()`. No hay borrado.
 */
class TipoCentroService
{
    /**
     * Crea un tipo de centro con su denominación inicial.
     *
     * @param array<string, mixed> $datos codigo, nombre, siglas, unidad_organizativa_id y configuración opcional.
     * @param User|null $actor Usuario que lo crea.
     *
     * @throws DomainException Si falta un dato obligatorio.
     *
     * @return TipoCentro
     */
    public function crear(array $datos, ?User $actor): TipoCentro
    {
        foreach (['codigo', 'nombre', 'siglas', 'unidad_organizativa_id'] as $obligatorio) {
            if (blank($datos[$obligatorio] ?? null)) {
                throw new DomainException("Falta el dato obligatorio «{$obligatorio}» del tipo de centro.");
            }
        }

        $alta = Carbon::parse($datos['fecha_alta'] ?? today());

        return DB::transaction(function () use ($datos, $alta, $actor): TipoCentro {
            $tipo = TipoCentro::create(array_merge(
                Arr::only($datos, (new TipoCentro)->getFillable()),
                ['fecha_alta' => $alta->toDateString(), 'activo' => true, 'fecha_baja' => null],
            ));

            $tipo->denominaciones()->create([
                'nombre' => $datos['nombre'],
                'nombre_corto' => $datos['siglas'],
                'vigente_desde' => $alta->toDateString(),
                'creado_por_id' => $actor?->id,
            ]);

            return $tipo;
        });
    }

    /**
     * Da de baja un tipo sin centros activos (RN-11). El tipo sigue existiendo.
     *
     * @param TipoCentro $tipo Tipo que se da de baja.
     * @param Carbon $fecha Fecha de baja.
     * @param User|null $actor Usuario que la ejecuta (queda en la versión).
     *
     * @throws LogicException Si tiene centros activos.
     *
     * @return TipoCentro
     */
    public function darDeBaja(TipoCentro $tipo, Carbon $fecha, ?User $actor): TipoCentro
    {
        $tipo->update(['activo' => false, 'fecha_baja' => $fecha->toDateString()]);

        return $tipo;
    }
}
