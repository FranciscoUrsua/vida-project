<?php

namespace Modules\Agenda\Services\Citas;

use App\Models\User;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\ActorTipoCitaEvento;
use Modules\Agenda\Enums\CanalCitaEvento;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CitaEvento;
use Modules\Agenda\Models\SolicitudCita;

/**
 * Único punto de escritura del historial de solicitudes y citas (`cita_eventos`).
 *
 * Lo llaman los servicios de Agenda dentro de su transacción, de modo que todo
 * cambio de estado y su evento se guardan juntos o no se guarda ninguno. En
 * `datos` no van datos personales en claro: solo identificadores, fechas y horas.
 */
class RegistroEventosCita
{
    /**
     * Inserta un evento del historial.
     *
     * @param AccionCitaEvento $accion
     * @param Cita|null $cita
     * @param SolicitudCita|null $solicitud Por defecto, la de la cita.
     * @param User|null $actor Null si la acción la hace el canal externo o el sistema.
     * @param ActorTipoCitaEvento|null $actorTipo Por defecto, usuario si hay actor y sistema si no.
     * @param CanalCitaEvento|null $canal
     * @param PedidoPor|null $pedidoPor
     * @param string|null $estadoAntes
     * @param string|null $estadoDespues
     * @param string|null $motivo Se guarda cifrado.
     * @param array<string, mixed> $datos
     * @return CitaEvento
     */
    public function registrar(
        AccionCitaEvento $accion,
        ?Cita $cita = null,
        ?SolicitudCita $solicitud = null,
        ?User $actor = null,
        ?ActorTipoCitaEvento $actorTipo = null,
        ?CanalCitaEvento $canal = null,
        ?PedidoPor $pedidoPor = null,
        ?string $estadoAntes = null,
        ?string $estadoDespues = null,
        ?string $motivo = null,
        array $datos = [],
    ): CitaEvento {
        return CitaEvento::create([
            'cita_id' => $cita?->id,
            'solicitud_cita_id' => $solicitud?->id ?? $cita?->solicitud_cita_id,
            'accion' => $accion,
            'actor_id' => $actor?->id,
            'actor_tipo' => $actorTipo ?? ($actor !== null ? ActorTipoCitaEvento::Usuario : ActorTipoCitaEvento::Sistema),
            'canal' => $canal,
            'pedido_por' => $pedidoPor,
            'estado_antes' => $estadoAntes,
            'estado_despues' => $estadoDespues,
            'motivo' => $motivo,
            'datos' => $datos === [] ? null : $datos,
        ]);
    }

    /**
     * Datos de un slot para el historial (sin datos personales).
     *
     * @param Cita $cita
     * @return array{slot_id: int, profesional_id: int, fecha: string, hora_inicio: string}
     */
    public static function datosSlot(Cita $cita): array
    {
        return [
            'slot_id' => $cita->slot_id,
            'profesional_id' => $cita->profesional_id,
            'fecha' => $cita->fecha->toDateString(),
            'hora_inicio' => substr((string) $cita->hora_inicio, 0, 5),
        ];
    }
}
