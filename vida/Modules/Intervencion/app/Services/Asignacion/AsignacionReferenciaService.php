<?php

namespace Modules\Intervencion\Services\Asignacion;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Services\AmbitoUsuario;
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
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;

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
 *
 * Cambio manual (fase 3, bloque E): solo la supervisión adscrita al centro afectado,
 * no la de UO superiores (TF-CIR-37; con persona protegida lo exige además RN-17/RN-19). El nuevo profesional debe poder ser referencia en el centro y se
 * avisa al saliente y al entrante.
 */
class AsignacionReferenciaService
{
    /**
     * @param SorteoReferenciaService $sorteo
     * @param PoolReferenciaService $pool
     * @param CentroDeUsuario $centrosUsuario
     * @param AlertaService $alertas
     */
    public function __construct(
        private readonly SorteoReferenciaService $sorteo,
        private readonly PoolReferenciaService $pool,
        private readonly CentroDeUsuario $centrosUsuario,
        private readonly AlertaService $alertas,
    ) {}

    /**
     * Asignación vigente de una historia.
     *
     * @param HistoriaSocial $historia
     *
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
     *
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

        $modo = app(ConfiguracionEfectivaCentro::class)->modoAsignacionReferencia($centro);

        if ($modo === ModoAsignacionReferenciaCentro::QuienAbre) {
            return $this->crear($historia, $centro, $actor->id, OrigenAsignacionReferencia::QuienAbre);
        }

        if ($modo === ModoAsignacionReferenciaCentro::LibreEleccion && $elegido !== null) {
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
     *
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
     *
     * @return AsignacionProfesional
     *
     * @throws AuthorizationException Si el usuario no supervisa el centro (o no está adscrito, con persona protegida).
     * @throws InvalidArgumentException Si falta el motivo o el nuevo profesional no puede ser referencia en el centro.
     */
    public function cambiarManual(HistoriaSocial $historia, User $nuevo, string $motivo, User $supervisor, ?Centro $centro = null): AsignacionProfesional
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('El cambio de referencia exige un motivo.');
        }

        $vigenteAntes = $this->vigente($historia);
        $centro ??= $vigenteAntes?->centro_id !== null ? Centro::find($vigenteAntes->centro_id) : $historia->centro;
        $this->exigirSupervisionDelCentro($supervisor, $centro, $historia);

        if (! $this->pool->profesionalesReferencia($centro, today())->contains('id', $nuevo->id)) {
            throw new InvalidArgumentException("{$nuevo->nombreVisible()} no puede ser profesional de referencia en {$centro->nombre}.");
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

            $this->avisarCambio($asignacion, $vigente?->profesional_id, $centro);

            return $asignacion;
        });
    }

    /**
     * Exige rol `supervision` y adscripción directa al centro.
     *
     * @param User $usuario
     * @param Centro|null $centro
     * @param HistoriaSocial $historia
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    private function exigirSupervisionDelCentro(User $usuario, ?Centro $centro, HistoriaSocial $historia): void
    {
        // La supervisión adscrita al centro, no la de UO superiores (TF-CIR-37); igual con
        // persona protegida (RN-17/RN-19), donde además lo exige la regla de protegidos.
        if ($centro === null || ! $usuario->hasRole('supervision') || ! $this->centrosUsuario->adscritoA($usuario, $centro)) {
            throw new AuthorizationException(app(AmbitoUsuario::class)->historiaProtegida($historia)
                ? 'La referencia de una persona con protección especial la cambia la supervisión del propio centro.'
                : 'Solo la supervisión del centro puede cambiar el profesional de referencia.');
        }
    }

    /**
     * Avisa del cambio al profesional saliente y al entrante (sin nombrar a la persona).
     *
     * @param AsignacionProfesional $asignacion Nueva asignación.
     * @param int|null $salienteId Profesional que deja de ser referencia.
     * @param Centro $centro
     *
     * @return void
     */
    private function avisarCambio(AsignacionProfesional $asignacion, ?int $salienteId, Centro $centro): void
    {
        $avisos = [
            $asignacion->profesional_id => ['Nuevo caso de referencia', "La supervisión de {$centro->nombre} te ha asignado un caso como profesional de referencia."],
        ];

        if ($salienteId !== null && $salienteId !== $asignacion->profesional_id) {
            $avisos[$salienteId] = ['Cambio de profesional de referencia', "La supervisión de {$centro->nombre} ha asignado a otro profesional de referencia un caso tuyo."];
        }

        foreach ($avisos as $usuarioId => [$titulo, $cuerpo]) {
            $this->alertas->crear([
                'tipo' => TipoAlerta::Aviso,
                'origen_type' => AsignacionProfesional::class,
                'origen_id' => $asignacion->id,
                'titulo' => $titulo,
                'cuerpo' => $cuerpo,
                'destinatario_type' => DestinatarioType::Usuario,
                'destinatario_usuario_id' => $usuarioId,
            ]);
        }
    }

    /**
     * Referencia vigente en el centro de algún miembro activo de la unidad de
     * convivencia de la persona (la más reciente, si hubiera varias).
     *
     * @param HistoriaSocial $historia
     * @param Centro $centro
     *
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
     * @param Centro $centro Centro del caso (RN-23).
     * @param int $profesionalId
     * @param OrigenAsignacionReferencia $origen
     * @param array<string, mixed> $extra
     *
     * @return AsignacionProfesional
     */
    private function crear(HistoriaSocial $historia, Centro $centro, int $profesionalId, OrigenAsignacionReferencia $origen, array $extra = []): AsignacionProfesional
    {
        return AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $profesionalId,
            'centro_id' => $centro->id,
            'origen' => $origen,
            'cuenta_en_reparto' => $origen->cuentaEnReparto(),
            'fecha_inicio' => today(),
        ] + $extra);
    }
}
