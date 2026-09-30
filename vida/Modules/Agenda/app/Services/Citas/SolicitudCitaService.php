<?php

namespace Modules\Agenda\Services\Citas;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\CanalCitaEvento;
use Modules\Agenda\Enums\CanalSolicitudCita;
use Modules\Agenda\Enums\DestinoCita;
use Modules\Agenda\Enums\EstadoSolicitudCita;
use Modules\Agenda\Enums\UrgenciaCita;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Models\TipoCita;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use Modules\Usuarios\Models\Cargo;

/**
 * Ciclo de vida de las solicitudes de cita (docs/modulo-citas.md §2.2 y §3.1).
 *
 * Único punto de creación y cambio de estado de `solicitudes_cita`. Cada método
 * comprueba la política, abre transacción y escribe su evento. VIDA no gestiona
 * el contacto con la persona: no hay límite ni registro de intentos (RN-10).
 */
class SolicitudCitaService
{
    /**
     * @param RegistroEventosCita $eventos
     * @param AvisosCitas $avisos
     * @param AsignacionReferenciaService $referencias
     */
    public function __construct(
        private readonly RegistroEventosCita $eventos,
        private readonly AvisosCitas $avisos,
        private readonly AsignacionReferenciaService $referencias,
    ) {}

    /**
     * Crea una solicitud pendiente en la bandeja del centro y avisa a quienes dan citas.
     *
     * Sin `no_despues_de`, se calcula con el plazo de la urgencia en días
     * laborables del horario del centro.
     *
     * @param array<string, mixed> $datos ciudadano_id, centro_id, tipo_cita_id, urgencia, destino,
     *        profesional_destino_id, servicio_destino, no_antes_de, no_despues_de, motivo,
     *        observaciones_citacion, canal, contexto_type, contexto_id.
     * @param User $solicitante
     * @param bool $avisar False cuando la solicitud se cita en el mismo momento (ventanilla).
     * @return SolicitudCita
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function crear(array $datos, User $solicitante, bool $avisar = true): SolicitudCita
    {
        Gate::forUser($solicitante)->authorize('create', SolicitudCita::class);

        $datos = $this->validar($datos);

        return DB::transaction(function () use ($datos, $solicitante, $avisar) {
            $solicitud = SolicitudCita::create($datos + [
                'solicitante_id' => $solicitante->id,
                'estado' => EstadoSolicitudCita::Pendiente,
            ]);

            $this->eventos->registrar(
                AccionCitaEvento::SolicitudCreada,
                solicitud: $solicitud,
                actor: $solicitante,
                canal: $this->canalEvento($solicitud->canal),
                estadoDespues: EstadoSolicitudCita::Pendiente->value,
                datos: ['urgencia' => $solicitud->urgencia->value, 'destino' => $solicitud->destino->value, 'no_despues_de' => $solicitud->no_despues_de->toDateString()],
            );

            if ($avisar) {
                $this->avisos->solicitudCreada($solicitud);
            }

            return $solicitud;
        });
    }

    /**
     * Toma la solicitud para gestionarla: evita que dos personas llamen a la misma persona.
     *
     * @param SolicitudCita $solicitud
     * @param User $usuario
     * @return SolicitudCita
     *
     * @throws AuthorizationException
     * @throws LogicException Si ya no está pendiente (otro la tomó antes).
     */
    public function tomar(SolicitudCita $solicitud, User $usuario): SolicitudCita
    {
        Gate::forUser($usuario)->authorize('gestionar', $solicitud);

        return DB::transaction(function () use ($solicitud, $usuario) {
            // Bloqueo: de dos tomas simultáneas, la segunda ve el estado ya cambiado
            $solicitud = SolicitudCita::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();

            if ($solicitud->estado !== EstadoSolicitudCita::Pendiente) {
                throw new LogicException('Otra persona ya ha tomado esta solicitud.');
            }

            return $this->cambiarEstado($solicitud, EstadoSolicitudCita::EnGestion, AccionCitaEvento::SolicitudTomada, $usuario, [
                'gestionada_por_id' => $usuario->id,
            ]);
        });
    }

    /**
     * Suelta una solicitud en gestión: vuelve a pendiente, con una nota opcional.
     *
     * @param SolicitudCita $solicitud
     * @param User $usuario Quien la tiene en gestión (o supervisión).
     * @param string|null $nota
     * @return SolicitudCita
     *
     * @throws AuthorizationException
     * @throws LogicException
     */
    public function liberar(SolicitudCita $solicitud, User $usuario, ?string $nota = null): SolicitudCita
    {
        Gate::forUser($usuario)->authorize('gestionar', $solicitud);

        if ($solicitud->gestionada_por_id !== $usuario->id && ! $usuario->can('citas.supervisar')) {
            throw new AuthorizationException('Solo quien tiene la solicitud en gestión puede soltarla.');
        }

        return DB::transaction(fn () => $this->cambiarEstado(
            SolicitudCita::whereKey($solicitud->id)->lockForUpdate()->firstOrFail(),
            EstadoSolicitudCita::Pendiente,
            AccionCitaEvento::SolicitudSoltada,
            $usuario,
            ['gestionada_por_id' => null],
            filled($nota) ? trim($nota) : null,
        ));
    }

    /**
     * La persona no quiere cita o no se la localiza. Final.
     *
     * @param SolicitudCita $solicitud
     * @param User $usuario
     * @param string $motivo Obligatorio.
     * @return SolicitudCita
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function desistir(SolicitudCita $solicitud, User $usuario, string $motivo): SolicitudCita
    {
        Gate::forUser($usuario)->authorize('gestionar', $solicitud);

        return $this->cerrar($solicitud, $usuario, $motivo, EstadoSolicitudCita::Desistida, AccionCitaEvento::SolicitudDesistida);
    }

    /**
     * Quien la pidió (o quien gestiona) la retira. Final.
     *
     * @param SolicitudCita $solicitud
     * @param User $usuario
     * @param string $motivo Obligatorio.
     * @return SolicitudCita
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function anular(SolicitudCita $solicitud, User $usuario, string $motivo): SolicitudCita
    {
        Gate::forUser($usuario)->authorize('anular', $solicitud);

        return $this->cerrar($solicitud, $usuario, $motivo, EstadoSolicitudCita::Anulada, AccionCitaEvento::SolicitudAnulada);
    }

    /**
     * Solicitud sin guardar con los datos validados, para proponer huecos antes
     * de citar en ventanilla (la solicitud real la crea citarDirecto() al confirmar).
     *
     * @param array<string, mixed> $datos Los de crear().
     * @param User $usuario
     * @return SolicitudCita No persistida.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function borrador(array $datos, User $usuario): SolicitudCita
    {
        Gate::forUser($usuario)->authorize('create', SolicitudCita::class);

        return new SolicitudCita($this->validar($datos) + ['solicitante_id' => $usuario->id, 'estado' => EstadoSolicitudCita::Pendiente]);
    }

    /**
     * Fecha límite por defecto: plazo de la urgencia en días laborables del centro.
     *
     * @param int $centroId
     * @param UrgenciaCita $urgencia
     * @param Carbon|null $desde Por defecto, hoy.
     * @return Carbon
     */
    public function fechaLimite(int $centroId, UrgenciaCita $urgencia, ?Carbon $desde = null): Carbon
    {
        $desde ??= today();
        $horario = HorarioCentro::vigenteDelCentro($centroId, $desde) ?? new HorarioCentro(['dias_laborables' => [1, 2, 3, 4, 5]]);

        return $horario->sumarDiasLaborables($desde, $horario->plazoUrgencia($urgencia));
    }

    /**
     * Valida y normaliza los datos de una solicitud nueva.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validar(array $datos): array
    {
        $validos = Validator::make($datos, [
            'ciudadano_id' => ['required', 'integer', 'exists:ciudadanos,id'],
            'centro_id' => ['required', 'integer', 'exists:centros,id'],
            'tipo_cita_id' => ['required', 'integer', Rule::exists('tipos_cita', 'id')->where('activo', true)->whereNull('deleted_at')],
            'urgencia' => ['required', Rule::enum(UrgenciaCita::class)],
            'destino' => ['required', Rule::enum(DestinoCita::class)],
            'profesional_destino_id' => ['nullable', 'required_if:destino,'.DestinoCita::ProfesionalConcreto->value, 'integer', 'exists:users,id'],
            'servicio_destino' => ['nullable', 'required_if:destino,'.DestinoCita::Servicio->value, 'string', Rule::exists('cargos', 'slug')],
            'no_antes_de' => ['nullable', 'date'],
            'no_despues_de' => ['nullable', 'date', 'after_or_equal:today', 'after_or_equal:no_antes_de'],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'observaciones_citacion' => ['nullable', 'string', 'max:500'],
            'canal' => ['nullable', Rule::enum(CanalSolicitudCita::class)],
            'contexto_type' => ['nullable', 'string', 'required_with:contexto_id'],
            'contexto_id' => ['nullable', 'integer', 'required_with:contexto_type'],
        ], [
            'profesional_destino_id.required_if' => 'Elige el profesional para el que se pide la cita.',
            'servicio_destino.required_if' => 'Elige el servicio o perfil.',
        ])->validate();

        $urgencia = UrgenciaCita::from($validos['urgencia']);
        $destino = DestinoCita::from($validos['destino']);
        $tipo = TipoCita::findOrFail($validos['tipo_cita_id']);
        $historia = HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $validos['ciudadano_id'])->first();

        if ($tipo->requiere_historia_social && $historia === null) {
            throw ValidationException::withMessages(['tipo_cita_id' => 'Este tipo de cita requiere que la persona tenga Historia Social.']);
        }

        if ($destino === DestinoCita::Referencia && ($historia === null || $this->referencias->vigente($historia) === null)) {
            throw ValidationException::withMessages(['destino' => 'La persona no tiene profesional de referencia: pide la cita para un servicio o el primer libre.']);
        }

        return [
            'ciudadano_id' => (int) $validos['ciudadano_id'],
            'centro_id' => (int) $validos['centro_id'],
            'tipo_cita_id' => $tipo->id,
            'urgencia' => $urgencia,
            'destino' => $destino,
            'profesional_destino_id' => $destino === DestinoCita::ProfesionalConcreto ? (int) $validos['profesional_destino_id'] : null,
            'servicio_destino' => in_array($destino, [DestinoCita::Servicio, DestinoCita::PrimerLibre], true) ? ($validos['servicio_destino'] ?? null) : null,
            'no_antes_de' => $validos['no_antes_de'] ?? null,
            'no_despues_de' => $validos['no_despues_de'] ?? $this->fechaLimite((int) $validos['centro_id'], $urgencia)->toDateString(),
            'motivo' => filled($validos['motivo'] ?? null) ? trim($validos['motivo']) : null,
            'observaciones_citacion' => filled($validos['observaciones_citacion'] ?? null) ? trim($validos['observaciones_citacion']) : null,
            'canal' => $validos['canal'] ?? CanalSolicitudCita::Interno->value,
            'contexto_type' => $validos['contexto_type'] ?? null,
            'contexto_id' => $validos['contexto_id'] ?? null,
        ];
    }

    /**
     * Cierra la solicitud (desistida o anulada) con motivo obligatorio.
     *
     * @param SolicitudCita $solicitud
     * @param User $usuario
     * @param string $motivo
     * @param EstadoSolicitudCita $estado
     * @param AccionCitaEvento $accion
     * @return SolicitudCita
     *
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    private function cerrar(SolicitudCita $solicitud, User $usuario, string $motivo, EstadoSolicitudCita $estado, AccionCitaEvento $accion): SolicitudCita
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('Indica el motivo.');
        }

        return DB::transaction(fn () => $this->cambiarEstado(
            SolicitudCita::whereKey($solicitud->id)->lockForUpdate()->firstOrFail(),
            $estado,
            $accion,
            $usuario,
            ['motivo_cierre' => $motivo, 'resuelta_en' => now(), 'gestionada_por_id' => $usuario->id],
            $motivo,
        ));
    }

    /**
     * Cambia el estado (el modelo valida la transición) y escribe el evento.
     *
     * @param SolicitudCita $solicitud Bloqueada.
     * @param EstadoSolicitudCita $nuevo
     * @param AccionCitaEvento $accion
     * @param User $usuario
     * @param array<string, mixed> $extra
     * @param string|null $motivo
     * @return SolicitudCita
     *
     * @throws LogicException Si la transición no es válida.
     */
    private function cambiarEstado(SolicitudCita $solicitud, EstadoSolicitudCita $nuevo, AccionCitaEvento $accion, User $usuario, array $extra = [], ?string $motivo = null): SolicitudCita
    {
        $antes = $solicitud->estado;
        $solicitud->update(['estado' => $nuevo] + $extra);

        $this->eventos->registrar($accion, solicitud: $solicitud, actor: $usuario, estadoAntes: $antes->value, estadoDespues: $nuevo->value, motivo: $motivo);

        return $solicitud;
    }

    /**
     * Canal del historial a partir del canal de la solicitud.
     *
     * @param CanalSolicitudCita $canal
     * @return CanalCitaEvento
     */
    private function canalEvento(CanalSolicitudCita $canal): CanalCitaEvento
    {
        return match ($canal) {
            CanalSolicitudCita::Presencial => CanalCitaEvento::Presencial,
            CanalSolicitudCita::Telefonico => CanalCitaEvento::Telefonico,
            CanalSolicitudCita::Interno, CanalSolicitudCita::Seguimiento => CanalCitaEvento::Interno,
        };
    }
}
