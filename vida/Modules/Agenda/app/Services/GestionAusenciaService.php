<?php

namespace Modules\Agenda\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\ReasignacionCita;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Services\Citas\RegistroEventosCita;

/**
 * Gestiona el flujo cuando un profesional no se presenta.
 *
 * - Las citas confirmadas del día pasan a estado 'cancelada' con motivo descriptivo.
 * - En modos estandar/avanzado devuelve slots de urgencia de otros profesionales
 *   como candidatos para reasignación.
 * - En modo basico devuelve slots disponibles de otros profesionales.
 * - La reasignación siempre la confirma un supervisor (Principio 3.9).
 * - Cancelaciones y reasignaciones dejan su evento en el historial de la cita.
 */
class GestionAusenciaService
{
    /**
     * Procesa la ausencia sobrevenida de un profesional en una fecha concreta.
     *
     * Cancela las citas confirmadas del profesional y devuelve los candidatos
     * de reasignación disponibles en el centro para esa fecha.
     *
     * @param int $usuarioId ID del profesional ausente
     * @param int $centroId ID del centro
     * @param Carbon $fecha Fecha de la ausencia
     *
     * @return array{citas: Collection<int, Cita>, candidatos: Collection<int, Slot>}
     */
    public function procesarAusencia(int $usuarioId, int $centroId, Carbon $fecha): array
    {
        $fechaStr = $fecha->toDateString();

        // Cancelar las citas confirmadas del profesional en esa fecha
        $citas = Cita::where('profesional_id', $usuarioId)
            ->where('centro_id', $centroId)
            ->where('fecha', $fechaStr)
            ->where('estado', EstadoCita::Confirmada->value)
            ->get();

        foreach ($citas as $cita) {
            DB::transaction(function () use ($cita) {
                $cita->update([
                    'estado' => EstadoCita::Cancelada->value,
                    'motivo_cancelacion' => Cita::MOTIVO_CANCELACION_AUSENCIA,
                    'pedido_por_cancelacion' => PedidoPor::Centro,
                    'pendiente_cierre' => false,
                ]);

                app(RegistroEventosCita::class)->registrar(AccionCitaEvento::CitaCancelada, cita: $cita, pedidoPor: PedidoPor::Centro,
                    estadoAntes: EstadoCita::Confirmada->value, estadoDespues: EstadoCita::Cancelada->value,
                    motivo: Cita::MOTIVO_CANCELACION_AUSENCIA, datos: RegistroEventosCita::datosSlot($cita));
            });
        }

        // Determinar el modo del centro a partir del HorarioCentro vigente en la fecha
        $horario = HorarioCentro::where('centro_id', $centroId)
            ->where('activo', true)
            ->where('vigente_desde', '<=', $fechaStr)
            ->where(function ($q) use ($fechaStr) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fechaStr);
            })
            ->first();

        $modoBasico = $horario?->esModoBasico() ?? false;

        // En modo básico no hay slots de urgencia; se ofrecen slots disponibles de otros profesionales
        $estadoCandidato = $modoBasico
            ? EstadoSlot::Disponible->value
            : EstadoSlot::BloqueadoUrgencia->value;

        $candidatos = Slot::where('centro_id', $centroId)
            ->where('fecha', $fechaStr)
            ->where('usuario_id', '!=', $usuarioId)
            ->where('estado', $estadoCandidato)
            ->get();

        return compact('citas', 'candidatos');
    }

    /**
     * Reasigna una cita a un nuevo slot elegido por el supervisor.
     *
     * Crea el registro histórico de reasignación, actualiza la cita con el nuevo
     * profesional y slot, y marca el slot destino como reservado.
     * El slot original permanece en su estado; el profesional estuvo ausente.
     *
     * @param Cita $cita Cita que necesita reasignación
     * @param Slot $slotDestino Slot (urgencia u ordinario) del profesional sustituto
     * @param int $supervisorId ID del supervisor que autoriza
     * @param string $motivo Valor de MotivoReasignacion
     *
     * @return ReasignacionCita Registro creado
     */
    public function reasignar(Cita $cita, Slot $slotDestino, int $supervisorId, string $motivo): ReasignacionCita
    {
        return DB::transaction(fn () => $this->aplicarReasignacion($cita, $slotDestino, $supervisorId, $motivo));
    }

    /**
     * Aplica la reasignación y escribe su evento (dentro de la transacción de reasignar()).
     *
     * @param Cita $cita
     * @param Slot $slotDestino
     * @param int $supervisorId
     * @param string $motivo
     * @return ReasignacionCita
     */
    private function aplicarReasignacion(Cita $cita, Slot $slotDestino, int $supervisorId, string $motivo): ReasignacionCita
    {
        $origen = RegistroEventosCita::datosSlot($cita);
        $estadoAntes = $cita->estado->value;

        $reasignacion = ReasignacionCita::create([
            'cita_id' => $cita->id,
            'slot_original_id' => $cita->slot_id,
            'slot_nuevo_id' => $slotDestino->id,
            'profesional_original_id' => $cita->profesional_id,
            'profesional_nuevo_id' => $slotDestino->usuario_id,
            'motivo' => $motivo,
            'realizada_por_id' => $supervisorId,
        ]);

        // La cita queda confirmada con el nuevo profesional y slot
        $cita->update([
            'estado' => EstadoCita::Confirmada->value,
            'profesional_id' => $slotDestino->usuario_id,
            'slot_id' => $slotDestino->id,
            'fecha' => $slotDestino->fecha->toDateString(),
            'hora_inicio' => $slotDestino->hora_inicio,
            'hora_fin' => $slotDestino->hora_fin,
        ]);

        // El slot destino queda reservado
        $slotDestino->update(['estado' => EstadoSlot::Reservado->value]);

        app(RegistroEventosCita::class)->registrar(AccionCitaEvento::CitaReasignada, cita: $cita, actor: User::find($supervisorId),
            pedidoPor: PedidoPor::Centro, estadoAntes: $estadoAntes, estadoDespues: EstadoCita::Confirmada->value,
            datos: ['origen' => $origen, 'destino' => RegistroEventosCita::datosSlot($cita), 'motivo_reasignacion' => $motivo]);

        return $reasignacion;
    }

    /**
     * El supervisor descarta reasignar una cita cancelada por ausencia: deja de
     * contar como pendiente (pantalla y contadores del menú), sin cambiar su estado.
     *
     * @param Cita $cita Cancelada por ausencia del profesional.
     * @return void
     */
    public function descartarReasignacion(Cita $cita): void
    {
        $cita->update(['motivo_cancelacion' => Cita::MOTIVO_AUSENCIA_DESCARTADA]);
    }
}
