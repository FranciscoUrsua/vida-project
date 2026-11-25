<?php

namespace Modules\Intervencion\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Agenda\Enums\DestinoCita;
use Modules\Agenda\Enums\UrgenciaCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Services\Citas\BusquedaHuecosService;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Services\Citas\PropuestaHueco;
use Modules\Agenda\Services\Citas\SolicitudCitaService;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Models\Derivacion;

/**
 * El profesional de encargo cita a la persona de su encargo en su propia agenda (EP-9 y bloque
 * E2 de docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * Solo el profesional asignado, con el encargo en curso y en huecos suyos del centro del
 * encargo (RN-15). La solicitud lleva el encargo como contexto: «Atender» la cita vuelve a él
 * (EnlaceAtencionCita). La cita la escribe CitacionService::citarEnAgendaPropia(), el único
 * camino de creación de citas.
 */
class CitasEncargo
{
    /**
     * @param SolicitudCitaService $solicitudes
     * @param BusquedaHuecosService $huecos
     * @param CitacionService $citacion
     * @param CentroDeUsuario $centros
     */
    public function __construct(
        private readonly SolicitudCitaService $solicitudes,
        private readonly BusquedaHuecosService $huecos,
        private readonly CitacionService $citacion,
        private readonly CentroDeUsuario $centros,
    ) {}

    /**
     * Si el usuario puede citar desde el encargo: profesional asignado, encargo en curso,
     * `citas.solicitar` y adscripción al centro del encargo.
     *
     * @param Derivacion $encargo
     * @param User $usuario
     *
     * @return bool
     */
    public function puedeCitar(Derivacion $encargo, User $usuario): bool
    {
        return $encargo->esEncargo()
            && $encargo->estado === EstadoDerivacion::Aceptada
            && $encargo->profesional_responsable_id === $usuario->id
            && $usuario->can('citas.solicitar')
            && $this->centros->puedeActuar($usuario, $encargo->centroDestino);
    }

    /**
     * Huecos libres del propio profesional en el centro del encargo para el tipo de cita.
     *
     * @param Derivacion $encargo
     * @param int $tipoCitaId
     * @param User $usuario
     *
     * @return Collection<int, PropuestaHueco>
     */
    public function huecos(Derivacion $encargo, int $tipoCitaId, User $usuario): Collection
    {
        $this->exigirCitable($encargo, $usuario);

        $borrador = $this->solicitudes->borrador($this->datos($encargo, $tipoCitaId, $usuario), $usuario);

        return $this->huecos->buscar($borrador);
    }

    /**
     * Da la cita en un hueco propio del centro del encargo.
     *
     * @param Derivacion $encargo
     * @param int $tipoCitaId
     * @param Slot $slot
     * @param User $usuario
     *
     * @return Cita
     *
     * @throws DomainException Si el encargo no admite cita o el hueco no es del centro del encargo.
     */
    public function citar(Derivacion $encargo, int $tipoCitaId, Slot $slot, User $usuario): Cita
    {
        $this->exigirCitable($encargo, $usuario);

        if ($slot->centro_id !== $encargo->centro_destino_id) {
            throw new DomainException('El hueco no es del centro del encargo.');
        }

        return $this->citacion->citarEnAgendaPropia($this->datos($encargo, $tipoCitaId, $usuario), $slot, $usuario);
    }

    /**
     * Exige que el usuario pueda citar desde el encargo; RN-15 con el mensaje de siempre.
     *
     * @param Derivacion $encargo
     * @param User $usuario
     *
     * @return void
     *
     * @throws DomainException
     */
    private function exigirCitable(Derivacion $encargo, User $usuario): void
    {
        // R1: citar es una actuación en el centro del encargo
        $this->centros->exigirActuacion($usuario, $encargo->centroDestino);

        if (! $this->puedeCitar($encargo, $usuario)) {
            throw new DomainException('Solo el profesional asignado cita desde un encargo en curso.');
        }
    }

    /**
     * Datos de la solicitud: la persona del encargo, en su centro, para el propio profesional.
     *
     * @param Derivacion $encargo
     * @param int $tipoCitaId
     * @param User $usuario
     *
     * @return array<string, mixed>
     */
    private function datos(Derivacion $encargo, int $tipoCitaId, User $usuario): array
    {
        return [
            'ciudadano_id' => (int) DB::table('historias_sociales')->where('id', $encargo->historia_id)->value('ciudadano_id'),
            'centro_id' => $encargo->centro_destino_id,
            'tipo_cita_id' => $tipoCitaId,
            'urgencia' => UrgenciaCita::Ordinaria->value,
            'destino' => DestinoCita::ProfesionalConcreto->value,
            'profesional_destino_id' => $usuario->id,
            'motivo' => 'Encargo profesional',
            'contexto_type' => $encargo->getMorphClass(),
            'contexto_id' => $encargo->id,
        ];
    }
}
