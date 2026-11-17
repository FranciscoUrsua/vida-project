<?php

namespace Modules\Intervencion\Services;

use Modules\Centro\Models\SesionActividad;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;

/**
 * Avisos de las actividades grupales a los profesionales (fase 6, F6-5).
 *
 * El aviso no nombra a las personas: puede haber personas protegidas entre ellas y el
 * aviso se lee fuera de su ficha. Dice cuántas y dónde verlas.
 */
class AvisoActividadesService
{
    /**
     * Arranca con el servicio de alertas.
     *
     * @param AlertaService $alertas
     */
    public function __construct(private readonly AlertaService $alertas) {}

    /**
     * Avisa a quien inscribió o prescribió a personas en una sesión cancelada.
     *
     * @param int $usuarioId Destinatario.
     * @param SesionActividad $sesion Sesión cancelada.
     * @param int $personas Personas suyas afectadas.
     *
     * @return void
     */
    public function avisarCancelacion(int $usuarioId, SesionActividad $sesion, int $personas): void
    {
        $actividad = $sesion->actividad;
        $cuando = $sesion->fecha->format('d/m/Y').' a las '.substr((string) $sesion->hora_inicio, 0, 5);
        $quienes = $personas === 1 ? 'una persona que inscribiste o prescribiste' : "{$personas} personas que inscribiste o prescribiste";

        $this->alertas->crear([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => SesionActividad::class,
            'origen_id' => $sesion->id,
            'titulo' => 'Sesión de actividad cancelada',
            'cuerpo' => "Se ha cancelado la sesión del {$cuando} de «{$actividad->nombre}» ({$actividad->centro?->nombre}). "
                ."Afecta a {$quienes}: su inscripción se ha dado de baja. Motivo: {$sesion->motivo_cancelacion}",
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuarioId,
        ]);
    }
}
