<?php

namespace Modules\Centro\Services\TipoCentro;

use App\Models\User;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Centro\Models\HorarioTipoCentro;
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
     * @param array<string, mixed> $datos codigo, nombre, siglas, unidad_organizativa_id, configuración opcional y
     *     `horario` opcional (dias_laborables, hora_apertura, hora_cierre, hora_inicio_atencion, hora_fin_atencion).
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

            if (! empty($datos['horario'])) {
                HorarioTipoCentro::create(array_merge(
                    ['nombre' => 'Horario general', 'vigente_desde' => $alta->toDateString()],
                    $datos['horario'],
                    ['tipo_centro_id' => $tipo->id],
                ));
            }

            return $tipo;
        });
    }

    /**
     * Programa un horario nuevo del tipo desde una fecha: cierra el vigente el día
     * anterior. Se aplica de inmediato a los centros que heredan (RN-07).
     *
     * @param TipoCentro $tipo Tipo de centro.
     * @param array{dias_laborables: list<int>, hora_apertura: string, hora_cierre: string, hora_inicio_atencion: string, hora_fin_atencion: string, nombre?: string} $horario
     * @param Carbon $desde Fecha de entrada en vigor.
     * @param User|null $actor Usuario que lo programa (queda en la versión del horario cerrado).
     *
     * @throws DomainException Si empieza antes que el vigente o se solapa con otro.
     *
     * @return HorarioTipoCentro
     */
    public function programarHorario(TipoCentro $tipo, array $horario, Carbon $desde, ?User $actor): HorarioTipoCentro
    {
        return DB::transaction(function () use ($tipo, $horario, $desde): HorarioTipoCentro {
            $abierto = HorarioTipoCentro::where('tipo_centro_id', $tipo->id)->whereNull('vigente_hasta')->lockForUpdate()->first();

            if ($abierto !== null) {
                if ($desde->lte($abierto->vigente_desde)) {
                    throw new DomainException('El nuevo horario debe empezar después del vigente.');
                }
                $abierto->update(['vigente_hasta' => $desde->copy()->subDay()->toDateString()]);
            }

            return HorarioTipoCentro::create(array_merge(
                ['nombre' => 'Horario general'],
                $horario,
                ['tipo_centro_id' => $tipo->id, 'vigente_desde' => $desde->toDateString(), 'vigente_hasta' => null],
            ));
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
