<?php

namespace Modules\Centro\Services;

use App\Models\UnidadOrganizativa;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Centro\Data\DiaCalendario;
use Modules\Centro\Data\HorarioEfectivo;
use Modules\Centro\Enums\ElementoCatalogo;
use Modules\Centro\Enums\ModoAsignacionReferenciaCentro;
use Modules\Centro\Enums\TipoCierre;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\CierreCalendario;
use Modules\Centro\Models\ExclusionCatalogo;
use Modules\Centro\Models\HorarioTipoCentro;
use Modules\Centro\Models\SegmentoPoblacion;
use Modules\Centro\Models\TipoCentro;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Único punto de lectura de la configuración heredable de un centro (RN-05).
 *
 * La aplicación nunca lee la configuración del tipo o la del centro por separado:
 * pide aquí el valor efectivo. Las propiedades «solo tipo» salen del tipo; las
 * sobrescribibles, del centro si las tiene y, si no, del tipo. El tipo se carga
 * una vez por instancia de centro (relación `tipoCentro`), así que un cambio del
 * tipo hecho en la misma petición sobre esa instancia se ve de inmediato.
 *
 * @see docs/modulo-tipo-centro.md §4
 */
class ConfiguracionEfectivaCentro
{
    /**
     * Tipo del centro.
     */
    public function tipo(Centro $centro): TipoCentro
    {
        $tipo = $centro->tipoCentro;
        if ($tipo === null) {
            throw new \LogicException("El centro {$centro->id} no tiene tipo (RN-01).");
        }

        return $tipo;
    }

    /**
     * UO funcional de la que depende el centro (solo tipo, RN-03).
     */
    public function uoFuncional(Centro $centro): UnidadOrganizativa
    {
        return $this->tipo($centro)->unidadOrganizativa()->firstOrFail();
    }

    /**
     * Público destinatario (solo tipo).
     *
     *
     * @return EloquentCollection<int, SegmentoPoblacion>
     */
    public function segmentos(Centro $centro): EloquentCollection
    {
        return $this->tipo($centro)->segmentos()->get();
    }

    /**
     * Si el centro se elige libremente en lugar de asignarse por domicilio (solo tipo, RN-09).
     */
    public function inscripcionLibre(Centro $centro): bool
    {
        return $this->tipo($centro)->inscripcion_libre;
    }

    /**
     * Cómo se asigna el profesional de referencia al abrir una historia (solo tipo, RN-09).
     */
    public function modoAsignacionReferencia(Centro $centro): ModoAsignacionReferenciaCentro
    {
        return $this->tipo($centro)->modo_asignacion_referencia;
    }

    /**
     * Meses hacia atrás que cuenta el reparto de referencias (solo tipo).
     */
    public function ventanaRepartoMeses(Centro $centro): int
    {
        return $this->tipo($centro)->ventana_reparto_meses;
    }

    /**
     * Meses sin apuntes para considerar dormido un caso (solo tipo).
     */
    public function mesesInactividadCaso(Centro $centro): int
    {
        return $this->tipo($centro)->meses_inactividad_caso;
    }

    /**
     * Horario semanal efectivo en una fecha (sobrescribible, sustitución completa RN-06).
     *
     * Toma el HorarioCentro vigente del centro; si hereda (o el centro no tiene
     * horario propio), el del tipo vigente en esa fecha. Null si no hay ninguno.
     *
     * @param CarbonInterface $fecha Fecha de referencia.
     *
     * @throws \RuntimeException Si el centro hereda y el tipo no tiene horario vigente.
     */
    public function horario(Centro $centro, CarbonInterface $fecha): ?HorarioEfectivo
    {
        $fecha = Carbon::instance($fecha);
        $propio = HorarioCentro::vigenteDelCentro($centro->id, $fecha);

        if ($propio !== null && ! $propio->hereda_horario_tipo) {
            return new HorarioEfectivo(
                array_map('intval', $propio->dias_laborables ?? []),
                substr((string) $propio->hora_apertura, 0, 5),
                substr((string) $propio->hora_cierre, 0, 5),
                substr((string) $propio->hora_inicio_atencion, 0, 5),
                substr((string) $propio->hora_fin_atencion, 0, 5),
                'centro',
            );
        }

        $delTipo = HorarioTipoCentro::where('tipo_centro_id', $centro->tipo_centro_id)->vigenteEn($fecha)->first();
        if ($delTipo === null) {
            if ($propio !== null) {
                throw new \RuntimeException("El centro {$centro->nombre} hereda el horario de su tipo, pero el tipo no tiene horario vigente el {$fecha->toDateString()}.");
            }

            return null;
        }

        return new HorarioEfectivo(
            array_map('intval', $delTipo->dias_laborables),
            substr($delTipo->hora_apertura, 0, 5),
            substr($delTipo->hora_cierre, 0, 5),
            substr($delTipo->hora_inicio_atencion, 0, 5),
            substr($delTipo->hora_fin_atencion, 0, 5),
            'tipo',
        );
    }

    /**
     * Cómo abre el centro un día: cierre del centro > cierre del tipo > horario efectivo (§5.2).
     *
     * @param CarbonInterface $fecha Día.
     */
    public function dia(Centro $centro, CarbonInterface $fecha): DiaCalendario
    {
        $fecha = Carbon::instance($fecha);
        $cierreCentro = CierreCalendario::where('titular_type', $centro->getMorphClass())
            ->where('titular_id', $centro->id)->enFecha($fecha)->latest('id')->first();
        if ($cierreCentro !== null) {
            return $this->aplicarCierre($cierreCentro, 'cierre_centro');
        }

        $cierreTipo = CierreCalendario::where('titular_type', (new TipoCentro)->getMorphClass())
            ->where('titular_id', $centro->tipo_centro_id)->enFecha($fecha)->latest('id')->first();
        if ($cierreTipo !== null) {
            return $this->aplicarCierre($cierreTipo, 'cierre_tipo');
        }

        $horario = $this->horario($centro, $fecha);
        if ($horario === null) {
            return DiaCalendario::cerrado('sin_horario');
        }
        if (! $horario->abreEl($fecha)) {
            return DiaCalendario::cerrado('horario');
        }

        return new DiaCalendario(true, $horario->apertura, $horario->cierre, $horario->inicioAtencion, $horario->finAtencion, 'horario');
    }

    /**
     * Fecha que resulta de sumar días en que el centro abre (con cierres aplicados).
     *
     * @param CarbonInterface $desde Fecha de partida (no cuenta).
     * @param int $dias Días a sumar.
     */
    public function sumarDiasLaborables(Centro $centro, CarbonInterface $desde, int $dias): Carbon
    {
        $fecha = Carbon::instance($desde)->startOfDay();
        // Tope de seguridad: un centro sin horario no abre nunca
        for ($vueltas = 0; $dias > 0 && $vueltas < 730; $vueltas++) {
            $fecha->addDay();
            if ($this->dia($centro, $fecha)->abierto) {
                $dias--;
            }
        }

        return $fecha;
    }

    /**
     * Días en que el centro abre entre dos fechas (sin contar la inicial).
     */
    public function diasLaborablesEntre(Centro $centro, CarbonInterface $desde, CarbonInterface $hasta): int
    {
        $dias = 0;
        $fecha = Carbon::instance($desde)->startOfDay();
        $fin = Carbon::instance($hasta)->startOfDay();
        while ($fecha->lt($fin)) {
            $fecha->addDay();
            if ($this->dia($centro, $fecha)->abierto) {
                $dias++;
            }
        }

        return $dias;
    }

    /**
     * Protocolo del centro, o el del tipo si no tiene propio (sobrescribible).
     */
    public function protocolo(Centro $centro): ?string
    {
        return $centro->protocolo ?? $this->tipo($centro)->protocolo;
    }

    /**
     * Prestaciones activas del catálogo del tipo (restringible, RN-08).
     *
     *
     * @return EloquentCollection<int, Prestacion>
     */
    public function prestaciones(Centro $centro): EloquentCollection
    {
        return $this->tipo($centro)->prestaciones()->where('prestaciones.activa', true)
            ->whereNotIn('prestaciones.id', $this->excluidos($centro, ElementoCatalogo::Prestacion))
            ->orderBy('prestaciones.nombre')->get();
    }

    /**
     * Tipos de plan que se pueden abrir en el centro (restringible, RN-08).
     *
     *
     * @return EloquentCollection<int, TipoPlan>
     */
    public function tiposPlan(Centro $centro): EloquentCollection
    {
        return $this->tipo($centro)->tiposPlan()->where('tipos_plan.activo', true)
            ->whereNotIn('tipos_plan.id', $this->excluidos($centro, ElementoCatalogo::TipoPlan))
            ->orderBy('tipos_plan.nombre')->get();
    }

    /**
     * Fichas de valoración disponibles en el centro; sin filas en el tipo, todas las activas (restringible).
     *
     *
     * @return EloquentCollection<int, TipoFicha>
     */
    public function tiposFicha(Centro $centro): EloquentCollection
    {
        $delTipo = $this->tipo($centro)->tiposFicha()->where('tipo_fichas.activo', true)->get();
        $fichas = $delTipo->isNotEmpty() ? $delTipo : TipoFicha::where('activo', true)->get();
        $excluidas = $this->excluidos($centro, ElementoCatalogo::TipoFicha);

        return $fichas->reject(fn (TipoFicha $ficha) => in_array($ficha->id, $excluidas, true))->values();
    }

    /**
     * De dónde sale el valor efectivo de una propiedad, para la interfaz.
     *
     * @param string $propiedad `protocolo`, `horario` u otra; las «solo tipo» siempre son del tipo.
     *
     * @return string 'tipo' | 'centro'
     */
    public function origen(Centro $centro, string $propiedad): string
    {
        return match ($propiedad) {
            'protocolo' => $centro->protocolo !== null ? 'centro' : 'tipo',
            'horario' => $this->horario($centro, today())?->origen ?? 'tipo',
            default => 'tipo',
        };
    }

    /**
     * Ids que el centro excluye del catálogo del tipo; solo si el tipo lo permite (RN-08).
     * Las exclusiones se conservan aunque el tipo desactive la restricción.
     *
     *
     * @return list<int>
     */
    private function excluidos(Centro $centro, ElementoCatalogo $tipo): array
    {
        if (! $this->tipo($centro)->permite_restriccion_en_centro) {
            return [];
        }

        return ExclusionCatalogo::where('centro_id', $centro->id)->where('elemento_tipo', $tipo)
            ->pluck('elemento_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Día abierto o cerrado según un cierre de calendario.
     *
     * @param string $origen 'cierre_centro' | 'cierre_tipo'.
     */
    private function aplicarCierre(CierreCalendario $cierre, string $origen): DiaCalendario
    {
        if ($cierre->tipo === TipoCierre::Cierre) {
            return DiaCalendario::cerrado($origen);
        }

        $inicio = substr((string) $cierre->hora_inicio, 0, 5);
        $fin = substr((string) $cierre->hora_fin, 0, 5);

        return new DiaCalendario(true, $inicio, $fin, $inicio, $fin, $origen);
    }

    /**
     * Centros activos con adscripción por domicilio, opcionalmente de un tipo.
     *
     * Consulta por la configuración del tipo sin sacarla de este servicio.
     *
     * @param string|null $codigoTipo Código del tipo, o null para todos.
     *
     * @return Builder<Centro>
     */
    public function centrosPorDomicilio(?string $codigoTipo = null): Builder
    {
        return Centro::query()
            ->where('activo', true)
            ->whereHas('tipoCentro', fn (Builder $tipo) => $tipo
                ->where('inscripcion_libre', false)
                ->when($codigoTipo !== null, fn (Builder $q) => $q->where('codigo', $codigoTipo)));
    }

    /**
     * Códigos de los tipos con adscripción por domicilio que tienen algún centro activo.
     *
     * @return list<string>
     */
    public function tiposConDomicilio(): array
    {
        return TipoCentro::query()
            ->where('inscripcion_libre', false)
            ->whereHas('centros', fn (Builder $c) => $c->where('activo', true))
            ->orderBy('codigo')
            ->pluck('codigo')
            ->all();
    }
}
