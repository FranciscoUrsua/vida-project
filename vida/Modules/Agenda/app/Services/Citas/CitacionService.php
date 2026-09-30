<?php

namespace Modules\Agenda\Services\Citas;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\ActorTipoCitaEvento;
use Modules\Agenda\Enums\CanalCitaEvento;
use Modules\Agenda\Enums\CanalSolicitudCita;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\EstadoSolicitudCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Enums\OrigenPermitidoSlot;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Models\TipoCita;
use Modules\Agenda\Services\Citas\CitaPrevia\AdaptadorCitaPrevia;
use Modules\Agenda\Services\Citas\CitaPrevia\CitaExternaRecibida;
use Modules\Ciudadania\Models\CiudadanoIdentificador;

/**
 * Dar, reprogramar y cancelar citas (docs/modulo-citas.md §3.2 y §3.4) y recibir
 * las del canal externo (§4).
 *
 * Único punto de creación de citas: ningún otro camino escribe en `citas` salvo
 * los servicios de Agenda. Cada operación comprueba la política, bloquea el
 * slot, cambia estados y escribe sus eventos en una transacción. El sistema
 * nunca elige el hueco: lo elige la persona entre las propuestas.
 */
class CitacionService
{
    /**
     * @param SolicitudCitaService $solicitudes
     * @param RegistroEventosCita $eventos
     * @param AvisosCitas $avisos
     * @param AdaptadorCitaPrevia $citaPrevia
     */
    public function __construct(
        private readonly SolicitudCitaService $solicitudes,
        private readonly RegistroEventosCita $eventos,
        private readonly AvisosCitas $avisos,
        private readonly AdaptadorCitaPrevia $citaPrevia,
    ) {}

    /**
     * Da la cita de una solicitud en el slot elegido.
     *
     * @param SolicitudCita $solicitud Pendiente o en gestión (por quien cita).
     * @param Slot $slot
     * @param ModoAsignacionCita $modo El de la propuesta elegida.
     * @param User $usuario
     * @return Cita
     *
     * @throws AuthorizationException
     * @throws LogicException Si la solicitud ya no admite cita o el slot no es válido o ya está ocupado.
     */
    public function citar(SolicitudCita $solicitud, Slot $slot, ModoAsignacionCita $modo, User $usuario): Cita
    {
        Gate::forUser($usuario)->authorize('gestionar', $solicitud);
        Gate::forUser($usuario)->authorize('citar', [Cita::class, $slot]);

        return DB::transaction(function () use ($solicitud, $slot, $modo, $usuario) {
            $solicitud = SolicitudCita::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();

            if ($solicitud->estado === EstadoSolicitudCita::EnGestion && $solicitud->gestionada_por_id !== $usuario->id && ! $usuario->can('citas.supervisar')) {
                throw new LogicException('Esta solicitud la está gestionando otra persona.');
            }

            if (! in_array($solicitud->estado, [EstadoSolicitudCita::Pendiente, EstadoSolicitudCita::EnGestion], true)) {
                throw new LogicException('La solicitud ya no admite cita.');
            }

            $slot = $this->bloquearSlot($slot, $solicitud->urgencia->admiteSlotsUrgencia());
            $this->exigirCompatible($solicitud->tipoCita, $slot, $solicitud->centro_id);
            $eraUrgencia = $slot->estado === EstadoSlot::BloqueadoUrgencia;

            $cita = $this->crearCita($slot, $solicitud->tipoCita, $modo, [
                'solicitud_cita_id' => $solicitud->id,
                'ciudadano_id' => $solicitud->ciudadano_id,
                'origen' => OrigenCita::Interno,
                'creado_por_id' => $usuario->id,
            ]);

            $antes = $solicitud->estado;
            $solicitud->update(['estado' => EstadoSolicitudCita::Citada, 'resuelta_en' => now(), 'gestionada_por_id' => $usuario->id]);

            $this->eventos->registrar(
                AccionCitaEvento::CitaCreada,
                cita: $cita,
                solicitud: $solicitud,
                actor: $usuario,
                canal: CanalCitaEvento::Interno,
                estadoAntes: $antes->value,
                estadoDespues: EstadoSolicitudCita::Citada->value,
                datos: RegistroEventosCita::datosSlot($cita) + ['modo_asignacion' => $modo->value, 'slot_urgencia' => $eraUrgencia],
            );

            if ($eraUrgencia) {
                $this->avisos->slotUrgenciaConsumido($cita);
            }

            return $cita;
        });
    }

    /**
     * Cita en ventanilla o por teléfono: solicitud y cita a la vez, o ninguna.
     *
     * @param array<string, mixed> $datos Datos de la solicitud (ver SolicitudCitaService::crear()).
     * @param Slot $slot
     * @param ModoAsignacionCita $modo
     * @param User $usuario
     * @return Cita
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws LogicException Si el slot ya no está libre: no queda ni la solicitud.
     */
    public function citarDirecto(array $datos, Slot $slot, ModoAsignacionCita $modo, User $usuario): Cita
    {
        Gate::forUser($usuario)->authorize('citar', [Cita::class, $slot]);

        return DB::transaction(function () use ($datos, $slot, $modo, $usuario) {
            $solicitud = $this->solicitudes->crear($datos + ['canal' => CanalSolicitudCita::Presencial->value], $usuario, avisar: false);

            return $this->citar($solicitud, $slot, $modo, $usuario);
        });
    }

    /**
     * Mueve una cita a otro slot: la original queda reprogramada y libera su slot;
     * la nueva la referencia. Una cita solo se reprograma una vez: la cadena crece
     * desde la vigente.
     *
     * @param Cita $cita Confirmada.
     * @param Slot $nuevo
     * @param PedidoPor $pedidoPor
     * @param string $motivo Obligatorio.
     * @param User $usuario
     * @return Cita La nueva cita.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function reprogramar(Cita $cita, Slot $nuevo, PedidoPor $pedidoPor, string $motivo, User $usuario): Cita
    {
        Gate::forUser($usuario)->authorize('reprogramar', $cita);
        Gate::forUser($usuario)->authorize('citar', [Cita::class, $nuevo]);
        $motivo = $this->exigirMotivo($motivo);

        return DB::transaction(function () use ($cita, $nuevo, $pedidoPor, $motivo, $usuario) {
            $cita = Cita::whereKey($cita->id)->lockForUpdate()->firstOrFail();

            if ($cita->estado !== EstadoCita::Confirmada) {
                throw new LogicException('Solo se reprograma una cita confirmada: si ya se reprogramó, se mueve la cita nueva.');
            }

            $slot = $this->bloquearSlot($nuevo, $cita->solicitud?->urgencia->admiteSlotsUrgencia() ?? false);
            $this->exigirCompatible($cita->tipoCita, $slot, $cita->centro_id);

            $this->liberarSlot($cita);
            $cita->update(['estado' => EstadoCita::Reprogramada, 'pendiente_cierre' => false]);

            $nueva = $this->crearCita($slot, $cita->tipoCita, $cita->modo_asignacion, [
                'solicitud_cita_id' => $cita->solicitud_cita_id,
                'ciudadano_id' => $cita->ciudadano_id,
                'cita_anterior_id' => $cita->id,
                'origen' => $cita->origen,
                'modalidad' => $cita->modalidad,
                'creado_por_id' => $usuario->id,
            ]);

            $datos = ['origen' => RegistroEventosCita::datosSlot($cita), 'destino' => RegistroEventosCita::datosSlot($nueva)];

            foreach ([[$cita, EstadoCita::Confirmada, EstadoCita::Reprogramada], [$nueva, null, EstadoCita::Confirmada]] as [$afectada, $antes, $despues]) {
                $this->eventos->registrar(AccionCitaEvento::CitaReprogramada, cita: $afectada, actor: $usuario, canal: CanalCitaEvento::Interno,
                    pedidoPor: $pedidoPor, estadoAntes: $antes?->value, estadoDespues: $despues->value, motivo: $motivo, datos: $datos);
            }

            $this->notificarExterna($nueva, 'reprogramada', $usuario, ['fecha' => $nueva->fecha->toDateString(), 'hora_inicio' => substr((string) $nueva->hora_inicio, 0, 5)]);

            return $nueva;
        });
    }

    /**
     * Cancela una cita con motivo y a petición de quién. Quien cancela decide si
     * se abre una solicitud nueva enlazada (no hay regla automática).
     *
     * @param Cita $cita Confirmada.
     * @param PedidoPor $pedidoPor
     * @param string $motivo Obligatorio.
     * @param User $usuario
     * @param bool $abrirSolicitud
     * @return SolicitudCita|null La solicitud nueva, si se abre.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function cancelar(Cita $cita, PedidoPor $pedidoPor, string $motivo, User $usuario, bool $abrirSolicitud = false): ?SolicitudCita
    {
        Gate::forUser($usuario)->authorize('cancelar', $cita);
        $motivo = $this->exigirMotivo($motivo);

        if ($this->yaEmpezo($cita)) {
            Gate::forUser($usuario)->authorize('cancelarRetroactiva', $cita);
        }

        return DB::transaction(function () use ($cita, $pedidoPor, $motivo, $usuario, $abrirSolicitud) {
            $cita = Cita::whereKey($cita->id)->lockForUpdate()->firstOrFail();

            if ($cita->estado !== EstadoCita::Confirmada) {
                throw new LogicException('Solo se cancela una cita confirmada.');
            }

            $cita->cancelar($usuario, $motivo);
            $cita->update(['pedido_por_cancelacion' => $pedidoPor, 'pendiente_cierre' => false]);

            $this->eventos->registrar(AccionCitaEvento::CitaCancelada, cita: $cita, actor: $usuario, canal: CanalCitaEvento::Interno,
                pedidoPor: $pedidoPor, estadoAntes: EstadoCita::Confirmada->value, estadoDespues: EstadoCita::Cancelada->value,
                motivo: $motivo, datos: RegistroEventosCita::datosSlot($cita));

            $this->notificarExterna($cita, 'cancelada', $usuario);

            return $abrirSolicitud ? $this->solicitudTrasCancelar($cita, $usuario) : null;
        });
    }

    /**
     * Recibe una cita del canal externo. Idempotente: la misma referencia devuelve
     * la cita existente sin crear otra ni escribir evento. Si la persona no se
     * identifica de forma única por su documento, la cita queda pendiente de
     * identificar con los datos recibidos cifrados. No crea ciudadanos.
     *
     * @param CitaExternaRecibida $recibida
     * @return array{cita: Cita, creada: bool}
     *
     * @throws LogicException Si el slot no existe, no está libre o su tipo no admite el canal externo.
     */
    public function recibirExterna(CitaExternaRecibida $recibida): array
    {
        $existente = Cita::withTrashed()->where('origen', OrigenCita::ApiExterna)->where('referencia_externa', $recibida->referenciaExterna)->first();

        if ($existente !== null) {
            return ['cita' => $existente, 'creada' => false];
        }

        return DB::transaction(function () use ($recibida) {
            $slot = Slot::with('tipoSlot')->whereKey($recibida->slotId)->lockForUpdate()->first()
                ?? throw new LogicException('El slot no existe.');

            // Los slots de urgencia nunca se exponen al canal externo
            if ($slot->estado !== EstadoSlot::Disponible) {
                throw new LogicException('El slot no está disponible.');
            }

            if (! in_array($slot->tipoSlot->origen_permitido, [OrigenPermitidoSlot::ApiExterna, OrigenPermitidoSlot::Ambos], true)) {
                throw new LogicException('El tipo de slot no admite citas del canal externo.');
            }

            $ciudadanoId = $this->identificar($recibida);

            $cita = $this->crearCita($slot, TipoCita::findOrFail($recibida->tipoCitaId), ModoAsignacionCita::ProfesionalConcreto, [
                'ciudadano_id' => $ciudadanoId,
                'origen' => OrigenCita::ApiExterna,
                'referencia_externa' => $recibida->referenciaExterna,
                'datos_identificacion_externos' => $ciudadanoId === null ? $recibida->identificacion : null,
            ]);

            $this->eventos->registrar(AccionCitaEvento::CitaCreada, cita: $cita, actorTipo: ActorTipoCitaEvento::ApiExterna,
                canal: CanalCitaEvento::Api, estadoDespues: EstadoCita::Confirmada->value,
                datos: RegistroEventosCita::datosSlot($cita) + ['pendiente_identificar' => $ciudadanoId === null]);

            return ['cita' => $cita, 'creada' => true];
        });
    }

    /**
     * Ciudadano con ese documento vigente, si hay exactamente uno.
     *
     * @param CitaExternaRecibida $recibida
     * @return int|null
     */
    private function identificar(CitaExternaRecibida $recibida): ?int
    {
        if ($recibida->numeroDocumento === null) {
            return null;
        }

        $ids = CiudadanoIdentificador::query()
            ->where('valor_hash', hash('sha256', strtolower($recibida->numeroDocumento)))
            ->when($recibida->tipoDocumento !== null, fn ($q) => $q->where('tipo', $recibida->tipoDocumento))
            ->whereNull('fecha_fin')
            ->distinct()
            ->pluck('ciudadano_id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /**
     * Crea la cita en el slot. El observer de Cita pasa el slot a reservado.
     *
     * @param Slot $slot Bloqueado.
     * @param TipoCita $tipo
     * @param ModoAsignacionCita $modo
     * @param array<string, mixed> $datos
     * @return Cita
     */
    private function crearCita(Slot $slot, TipoCita $tipo, ModoAsignacionCita $modo, array $datos): Cita
    {
        return Cita::create($datos + [
            'slot_id' => $slot->id,
            'profesional_id' => $slot->usuario_id,
            'tipo_slot_id' => $slot->tipo_slot_id,
            'tipo_cita_id' => $tipo->id,
            'centro_id' => $slot->centro_id,
            'fecha' => $slot->fecha->toDateString(),
            'hora_inicio' => $slot->hora_inicio,
            'hora_fin' => $slot->hora_fin,
            'estado' => EstadoCita::Confirmada,
            'modalidad' => $tipo->modalidad_defecto,
            'modo_asignacion' => $modo,
        ]);
    }

    /**
     * Bloquea el slot y comprueba que sigue libre.
     *
     * @param Slot $slot
     * @param bool $admiteUrgencia
     * @return Slot Bloqueado y recargado.
     *
     * @throws LogicException
     */
    private function bloquearSlot(Slot $slot, bool $admiteUrgencia): Slot
    {
        $slot = Slot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
        $libres = $admiteUrgencia ? [EstadoSlot::Disponible, EstadoSlot::BloqueadoUrgencia] : [EstadoSlot::Disponible];

        if (! in_array($slot->estado, $libres, true)) {
            throw new LogicException('Ese hueco ya no está libre: busca otro.');
        }

        return $slot;
    }

    /**
     * El slot debe ser del centro y de un tipo compatible con el tipo de cita.
     *
     * @param TipoCita $tipo
     * @param Slot $slot
     * @param int $centroId
     * @return void
     *
     * @throws LogicException
     */
    private function exigirCompatible(TipoCita $tipo, Slot $slot, int $centroId): void
    {
        if ($slot->centro_id !== $centroId) {
            throw new LogicException('El hueco es de otro centro.');
        }

        if (! $tipo->admiteTipoSlot($slot->tipo_slot_id)) {
            throw new LogicException('El tipo de cita no se puede dar en ese tipo de hueco.');
        }
    }

    /**
     * Libera el slot de una cita con la misma regla que Cita::cancelar().
     *
     * @param Cita $cita
     * @return void
     */
    private function liberarSlot(Cita $cita): void
    {
        Slot::whereKey($cita->slot_id)->update([
            'estado' => $this->yaEmpezo($cita) ? EstadoSlot::NoOcupado->value : EstadoSlot::Disponible->value,
        ]);
    }

    /**
     * Si la hora de la cita ya pasó.
     *
     * @param Cita $cita
     * @return bool
     */
    private function yaEmpezo(Cita $cita): bool
    {
        return now()->isAfter(Carbon::parse($cita->fecha->toDateString().' '.$cita->hora_inicio));
    }

    /**
     * Solicitud nueva tras cancelar: mismo tipo, urgencia y destino, enlazada a la anterior.
     *
     * @param Cita $cita
     * @param User $usuario
     * @return SolicitudCita|null Null si la cita no tenía persona identificada.
     */
    private function solicitudTrasCancelar(Cita $cita, User $usuario): ?SolicitudCita
    {
        if ($cita->ciudadano_id === null) {
            return null;
        }

        $anterior = $cita->solicitud;

        $datos = [
            'ciudadano_id' => $cita->ciudadano_id,
            'centro_id' => $cita->centro_id,
            'tipo_cita_id' => $cita->tipo_cita_id,
            'urgencia' => $anterior?->urgencia->value ?? 'ordinaria',
            'destino' => $anterior?->destino->value ?? 'profesional_concreto',
            'profesional_destino_id' => $anterior?->profesional_destino_id ?? $cita->profesional_id,
            'servicio_destino' => $anterior?->servicio_destino,
            'observaciones_citacion' => $anterior?->observaciones_citacion,
            'motivo' => $anterior?->motivo,
        ];

        $solicitud = $this->solicitudes->crear($datos, $usuario);
        $solicitud->update(['solicitud_anterior_id' => $anterior?->id]);

        return $solicitud;
    }

    /**
     * Si la cita viene del canal externo, notifica el cambio al sistema externo y lo deja en el historial.
     *
     * @param Cita $cita
     * @param string $accion
     * @param User $usuario
     * @param array<string, mixed> $datos
     * @return void
     */
    private function notificarExterna(Cita $cita, string $accion, User $usuario, array $datos = []): void
    {
        if ($cita->origen !== OrigenCita::ApiExterna) {
            return;
        }

        // La referencia externa es la de la primera cita de la cadena de reprogramaciones
        $original = $cita->cadenaReprogramaciones()->first();
        $this->citaPrevia->notificar($original, $accion, $datos);

        $this->eventos->registrar(AccionCitaEvento::NotificacionExternaEnviada, cita: $cita, actor: $usuario, canal: CanalCitaEvento::Api,
            datos: ['accion' => $accion] + $datos);
    }

    /**
     * @param string $motivo
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private function exigirMotivo(string $motivo): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('Indica el motivo.');
        }

        return $motivo;
    }
}
