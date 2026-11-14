<?php

namespace Modules\Intervencion\Services;

use App\Models\HistoriaSocial;
use App\Models\User;
use Modules\Centro\Models\ListaEspera;
use Modules\Centro\Models\Prescripcion;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;

/**
 * Avisos de plazas al profesional de referencia vigente (fase 3, F3-8, bloque D).
 *
 * Resuelve el TSR de una persona por `AsignacionReferenciaService::vigente()` sobre su
 * historia y avisa por `AlertaService`; ninguna alerta de plazas queda solo en el log.
 * Sin referencia vigente, el aviso de asignación va a la supervisión del centro de la
 * historia. Las alertas no nombran a la persona (pueden leerlas usuarios que no la ven).
 */
class AvisoPlazasService
{
    /**
     * Inyecta las dependencias.
     *
     * @param AsignacionReferenciaService $referencias
     * @param AlertaService $alertas
     */
    public function __construct(
        private readonly AsignacionReferenciaService $referencias,
        private readonly AlertaService $alertas,
    ) {}

    /**
     * Usuario profesional de referencia vigente de la persona, si lo tiene.
     *
     * @param int $ciudadanoId
     *
     * @return User|null
     */
    public function referenciaDe(int $ciudadanoId): ?User
    {
        $historia = $this->historiaDe($ciudadanoId);
        $asignacion = $historia !== null ? $this->referencias->vigente($historia) : null;

        return $asignacion !== null ? User::find($asignacion->profesional_id) : null;
    }

    /**
     * Ficha de profesional del TSR vigente (lo que guarda `lista_espera.profesional_alerta_id`).
     * Es el resolver que usa PrescripcionService al liberar plazas.
     *
     * @param int $ciudadanoId
     *
     * @return int|null
     */
    public function profesionalReferenciaDe(int $ciudadanoId): ?int
    {
        return $this->referenciaDe($ciudadanoId)?->profesional_id;
    }

    /**
     * Avisa de que se ha asignado plaza a una prescripción: al TSR vigente o, sin él, a la
     * supervisión del centro de la historia.
     *
     * @param Prescripcion $prescripcion
     *
     * @return void
     */
    public function avisarAsignacion(Prescripcion $prescripcion): void
    {
        $datos = [
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => Prescripcion::class,
            'origen_id' => $prescripcion->id,
            'titulo' => 'Plaza asignada',
            'cuerpo' => 'Se ha asignado plaza a una prescripción de un caso tuyo. Revísala en la Historia Social.',
        ];

        $tsr = $this->referenciaDe($prescripcion->ciudadano_id);

        if ($tsr !== null) {
            $this->alertas->crear($datos + ['destinatario_type' => DestinatarioType::Usuario, 'destinatario_usuario_id' => $tsr->id]);

            return;
        }

        $uoId = $this->historiaDe($prescripcion->ciudadano_id)?->unidad_organizativa_id;

        $this->alertas->crear([
            'cuerpo' => 'Se ha asignado plaza a una prescripción de una persona sin profesional de referencia. Asígnale referencia y revisa la prescripción.',
            'destinatario_type' => DestinatarioType::RolUo,
            'destinatario_rol' => 'supervision',
            'destinatario_uo_id' => $uoId,
        ] + $datos);
    }

    /**
     * Avisa al profesional de alerta de una entrada de lista de espera de que hay plaza libre.
     *
     * @param ListaEspera $entrada
     *
     * @return void
     */
    public function avisarPlazaLibre(ListaEspera $entrada): void
    {
        $usuario = $entrada->profesional_alerta_id !== null
            ? User::where('profesional_id', $entrada->profesional_alerta_id)->first()
            : null;

        if ($usuario === null) {
            $this->avisarAsignacionPendiente($entrada);

            return;
        }

        $this->alertas->crear([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => ListaEspera::class,
            'origen_id' => $entrada->id,
            'titulo' => 'Plaza libre en lista de espera',
            'cuerpo' => 'Se ha liberado una plaza en una colección donde un caso tuyo está en lista de espera. La asignación no es automática: revísala.',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuario->id,
        ]);
    }

    /**
     * Sin profesional al que avisar de la plaza libre: avisa a la supervisión del centro de la historia.
     *
     * @param ListaEspera $entrada
     *
     * @return void
     */
    private function avisarAsignacionPendiente(ListaEspera $entrada): void
    {
        $ciudadanoId = $entrada->prescripcion?->ciudadano_id;

        $this->alertas->crear([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => ListaEspera::class,
            'origen_id' => $entrada->id,
            'titulo' => 'Plaza libre en lista de espera',
            'cuerpo' => 'Se ha liberado una plaza para una persona en lista de espera sin profesional de referencia.',
            'destinatario_type' => DestinatarioType::RolUo,
            'destinatario_rol' => 'supervision',
            'destinatario_uo_id' => $ciudadanoId !== null ? $this->historiaDe($ciudadanoId)?->unidad_organizativa_id : null,
        ]);
    }

    /**
     * Historia abierta más reciente de la persona, sin scope.
     *
     * @param int $ciudadanoId
     *
     * @return HistoriaSocial|null
     */
    private function historiaDe(int $ciudadanoId): ?HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScopes()
            ->where('ciudadano_id', $ciudadanoId)
            ->orderByRaw("CASE WHEN estado IN ('abierta', 'en_seguimiento') THEN 0 ELSE 1 END")
            ->latest()
            ->first();
    }
}
