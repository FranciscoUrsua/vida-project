<?php

namespace Modules\Intervencion\Services\Asignacion;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Modules\Centro\Enums\EstadoAsignacionPendiente;
use Modules\Centro\Enums\ModoAsignacionReferenciaCentro;
use Modules\Centro\Enums\MotivoAsignacionPendiente;
use Modules\Centro\Enums\TipoAsignacionPendiente;
use Modules\Centro\Models\AsignacionPendiente;
use Modules\Centro\Models\Centro;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;

/**
 * Asignación del profesional de referencia al abrir una Historia Social y sus
 * cambios por el supervisor.
 *
 * Orden de decisión en la asignación inicial (docs/modulo-asignacion.md §4):
 * 1. Si alguien de su unidad de convivencia ya tiene referencia en el centro,
 *    la misma (RN-06; no cuenta como entrada).
 * 2. Centro «quien abre»: quien abre la historia (comportamiento anterior).
 * 3. Centro de libre elección con un profesional elegido del reparto: ese.
 * 4. Si no, sorteo. Sin nadie en el reparto, la historia queda sin referencia
 *    y va a la bandeja del supervisor.
 *
 * Historial aditivo: un cambio cierra la vigente y crea otra; nunca se cambia
 * el profesional de una asignación existente.
 */
class AsignacionReferenciaService
{
    /**
     * @param SorteoReferenciaService $sorteo
     * @param PoolReferenciaService $pool
     */
    public function __construct(
        private readonly SorteoReferenciaService $sorteo,
        private readonly PoolReferenciaService $pool,
    ) {}

    /**
     * Asignación vigente de una historia.
     *
     * @param HistoriaSocial $historia
     * @return AsignacionProfesional|null
     */
    public function vigente(HistoriaSocial $historia): ?AsignacionProfesional
    {
        return AsignacionProfesional::vigente()->where('historia_id', $historia->id)->first();
    }

    /**
     * Asigna la referencia inicial de una historia recién abierta en un centro.
     * Si la historia ya tiene referencia vigente, la devuelve sin cambiarla.
     *
     * @param HistoriaSocial $historia
     * @param Centro $centro Centro de la persona para el tipo de la apertura.
     * @param User $actor Profesional que abre la historia.
     * @param User|null $elegido Profesional elegido por la persona (centros de libre elección).
     * @return AsignacionProfesional|null Null si queda sin referencia (a la bandeja).
     *
     * @throws LogicException Si el profesional elegido no está en el reparto del centro.
     */
    public function asignarInicial(HistoriaSocial $historia, Centro $centro, User $actor, ?User $elegido = null): ?AsignacionProfesional
    {
        if ($vigente = $this->vigente($historia)) {
            return $vigente;
        }

        $hoy = today();

        if ($deLaUnidad = $this->referenciaDeLaUnidad($historia, $centro)) {
            return $this->crear($historia, $centro, $deLaUnidad, OrigenAsignacionReferencia::UnidadConvivencia);
        }

        if ($centro->modo_asignacion_referencia === ModoAsignacionReferenciaCentro::QuienAbre) {
            return $this->crear($historia, $centro, $actor->id, OrigenAsignacionReferencia::QuienAbre);
        }

        if ($centro->modo_asignacion_referencia === ModoAsignacionReferenciaCentro::LibreEleccion && $elegido !== null) {
            if (! $this->pool->elegibles($centro, $hoy)->contains(fn (array $e) => $e['usuario']->id === $elegido->id)) {
                throw new LogicException('El profesional elegido no está en el reparto de referencias del centro.');
            }

            return $this->crear($historia, $centro, $elegido->id, OrigenAsignacionReferencia::Eleccion, [
                'sorteo' => $this->sorteo->instantanea($centro, $hoy, $elegido->id),
                'asignado_por_id' => $actor->id,
            ]);
        }

        $resultado = $this->sorteo->sortear($centro, $hoy);

        if (! $resultado->hayElegido()) {
            $this->registrarSinReferencia($historia, $centro, MotivoAsignacionPendiente::SinElegibles);

            return null;
        }

        return $this->crear($historia, $centro, $resultado->elegido->id, OrigenAsignacionReferencia::Sorteo, [
            'sorteo' => $resultado->paraAuditoria(),
        ]);
    }

    /**
     * Deja constancia en la bandeja de una historia que se abre sin referencia.
     *
     * @param HistoriaSocial $historia
     * @param Centro|null $centro Bandeja en la que aparece.
     * @param MotivoAsignacionPendiente|null $motivo Null si la causa es que la persona no tiene centro.
     * @return AsignacionPendiente
     */
    public function registrarSinReferencia(HistoriaSocial $historia, ?Centro $centro, ?MotivoAsignacionPendiente $motivo): AsignacionPendiente
    {
        return AsignacionPendiente::abiertas()
            ->where('ciudadano_id', $historia->ciudadano_id)
            ->where('tipo', TipoAsignacionPendiente::SinReferencia)
            ->first()
            ?? AsignacionPendiente::create([
                'ciudadano_id' => $historia->ciudadano_id,
                'historia_id' => $historia->id,
                'tipo' => TipoAsignacionPendiente::SinReferencia,
                'tipo_centro' => null,
                'motivo' => $motivo,
                'centro_id' => $centro?->id,
                'estado' => EstadoAsignacionPendiente::Pendiente,
            ]);
    }

    /**
     * Cambio de referencia por el supervisor, con motivo (RN-04). Cierra la
     * vigente sin tocar su profesional y crea una manual, que no cuenta en el reparto.
     *
     * @param HistoriaSocial $historia
     * @param User $nuevo Nuevo profesional de referencia.
     * @param string $motivo Obligatorio.
     * @param User $supervisor
     * @param Centro|null $centro Centro de la nueva asignación; por defecto, el de la vigente.
     * @return AsignacionProfesional
     *
     * @throws AuthorizationException Si el usuario no tiene rol de supervisión.
     * @throws InvalidArgumentException Si falta el motivo.
     */
    public function cambiarManual(HistoriaSocial $historia, User $nuevo, string $motivo, User $supervisor, ?Centro $centro = null): AsignacionProfesional
    {
        if (! $supervisor->hasRole('supervision')) {
            throw new AuthorizationException('Solo supervisión puede cambiar el profesional de referencia.');
        }

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('El cambio de referencia exige un motivo.');
        }

        return DB::transaction(function () use ($historia, $nuevo, $motivo, $supervisor, $centro) {
            $vigente = AsignacionProfesional::vigente()
                ->where('historia_id', $historia->id)
                ->lockForUpdate()
                ->first();

            $vigente?->update(['fecha_fin' => today()]);

            $asignacion = AsignacionProfesional::create([
                'historia_id' => $historia->id,
                'profesional_id' => $nuevo->id,
                'centro_id' => $centro?->id ?? $vigente?->centro_id,
                'origen' => OrigenAsignacionReferencia::Manual,
                'cuenta_en_reparto' => false,
                'motivo' => $motivo,
                'asignado_por_id' => $supervisor->id,
                'fecha_inicio' => today(),
            ]);

            AsignacionPendiente::abiertas()
                ->where('ciudadano_id', $historia->ciudadano_id)
                ->where('tipo', TipoAsignacionPendiente::SinReferencia)
                ->update([
                    'estado' => EstadoAsignacionPendiente::Resuelta,
                    'resuelta_por_id' => $supervisor->id,
                    'resuelta_en' => now(),
                ]);

            return $asignacion;
        });
    }

    /**
     * Referencia vigente en el centro de algún miembro activo de la unidad de
     * convivencia de la persona (la más reciente, si hubiera varias).
     *
     * @param HistoriaSocial $historia
     * @param Centro $centro
     * @return int|null Id del profesional.
     */
    private function referenciaDeLaUnidad(HistoriaSocial $historia, Centro $centro): ?int
    {
        $unidades = UnidadConvivenciaMiembro::query()
            ->where('ciudadano_id', $historia->ciudadano_id)
            ->whereNull('fecha_fin')
            ->whereHas('unidadConvivencia', fn ($q) => $q->whereNull('fecha_disolucion'))
            ->pluck('unidad_convivencia_id');

        if ($unidades->isEmpty()) {
            return null;
        }

        $companeros = UnidadConvivenciaMiembro::query()
            ->whereIn('unidad_convivencia_id', $unidades)
            ->whereNull('fecha_fin')
            ->where('ciudadano_id', '!=', $historia->ciudadano_id)
            ->pluck('ciudadano_id');

        return AsignacionProfesional::vigente()
            ->where('centro_id', $centro->id)
            ->whereIn('historia_id', HistoriaSocial::withoutGlobalScopes()->whereIn('ciudadano_id', $companeros)->select('id'))
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->value('profesional_id');
    }

    /**
     * Crea la asignación con su origen; solo sorteo y elección cuentan en el reparto.
     *
     * @param HistoriaSocial $historia
     * @param Centro|null $centro
     * @param int $profesionalId
     * @param OrigenAsignacionReferencia $origen
     * @param array<string, mixed> $extra
     * @return AsignacionProfesional
     */
    private function crear(HistoriaSocial $historia, ?Centro $centro, int $profesionalId, OrigenAsignacionReferencia $origen, array $extra = []): AsignacionProfesional
    {
        return AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $profesionalId,
            'centro_id' => $centro?->id,
            'origen' => $origen,
            'cuenta_en_reparto' => $origen->cuentaEnReparto(),
            'fecha_inicio' => today(),
        ] + $extra);
    }
}
