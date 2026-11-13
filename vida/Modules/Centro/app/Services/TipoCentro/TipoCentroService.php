<?php

namespace Modules\Centro\Services\TipoCentro;

use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Modules\Centro\Models\Denominacion;
use Modules\Centro\Models\HorarioTipoCentro;
use Modules\Centro\Models\TipoCentro;

/**
 * Alta, cambios, horario, renombrado y baja de tipos de centro (docs/modulo-tipo-centro.md §8, §9 y RN-11).
 *
 * Crear un tipo deja su primera denominación vigente desde el alta; los
 * renombrados van por `renombrar()`. No hay borrado. Con actor, todo pasa por
 * TipoCentroPolicy (solo administrador); sin actor son procesos del sistema.
 */
class TipoCentroService
{
    /**
     * Crea un tipo de centro con su denominación inicial.
     *
     * @param array<string, mixed> $datos codigo, nombre, siglas, unidad_organizativa_id, configuración opcional y
     *                                    `horario` opcional (dias_laborables, hora_apertura, hora_cierre, hora_inicio_atencion, hora_fin_atencion).
     * @param User|null $actor Usuario que lo crea.
     *
     * @return TipoCentro
     *
     * @throws DomainException Si falta un dato obligatorio.
     * @throws AuthorizationException Si el actor no es administrador.
     */
    public function crear(array $datos, ?User $actor): TipoCentro
    {
        $this->autorizar($actor, 'create', TipoCentro::class);
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
     * @return HorarioTipoCentro
     *
     * @throws DomainException Si empieza antes que el vigente o se solapa con otro.
     * @throws AuthorizationException Si el actor no es administrador.
     */
    public function programarHorario(TipoCentro $tipo, array $horario, Carbon $desde, ?User $actor): HorarioTipoCentro
    {
        $this->autorizar($actor, 'update', $tipo);

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
     * @return TipoCentro
     *
     * @throws LogicException Si tiene centros activos.
     * @throws AuthorizationException Si el actor no es administrador.
     */
    public function darDeBaja(TipoCentro $tipo, Carbon $fecha, ?User $actor): TipoCentro
    {
        $this->autorizar($actor, 'darDeBaja', $tipo);

        $tipo->update(['activo' => false, 'fecha_baja' => $fecha->toDateString()]);

        return $tipo;
    }

    /**
     * Cambia la configuración del tipo (UO funcional, asignación, restricción, protocolo…).
     * El código es inmutable (lo rechaza el modelo) y la denominación va por renombrar().
     *
     * @param TipoCentro $tipo Tipo.
     * @param array<string, mixed> $datos Campos a cambiar.
     * @param User|null $actor Usuario que lo cambia.
     *
     * @return TipoCentro
     *
     * @throws DomainException Si incluye la denominación.
     * @throws AuthorizationException Si el actor no es administrador.
     */
    public function actualizar(TipoCentro $tipo, array $datos, ?User $actor): TipoCentro
    {
        $this->autorizar($actor, 'update', $tipo);

        $denominacion = array_intersect(array_keys($datos), ['nombre', 'siglas']);
        if ($denominacion !== []) {
            throw new DomainException('La denominación del tipo se cambia programando una nueva (renombrar).');
        }

        $tipo->update($datos);

        return $tipo;
    }

    /**
     * Programa una denominación nueva del tipo.
     *
     * @param TipoCentro $tipo Tipo.
     * @param string $nombre Denominación completa.
     * @param string $siglas Siglas.
     * @param Carbon $desde Fecha de entrada en vigor.
     * @param User|null $actor Usuario que la programa.
     *
     * @return Denominacion
     *
     * @throws AuthorizationException Si el actor no es administrador.
     */
    public function renombrar(TipoCentro $tipo, string $nombre, string $siglas, Carbon $desde, ?User $actor): Denominacion
    {
        $this->autorizar($actor, 'renombrar', $tipo);

        return $tipo->programarDenominacion($nombre, $siglas, $desde, $actor);
    }

    /**
     * Comprueba TipoCentroPolicy si hay actor; sin actor es un proceso del sistema.
     *
     * @param User|null $actor
     * @param string $accion
     * @param TipoCentro|class-string<TipoCentro> $objetivo
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    private function autorizar(?User $actor, string $accion, TipoCentro|string $objetivo): void
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize($accion, $objetivo);
        }
    }
}
