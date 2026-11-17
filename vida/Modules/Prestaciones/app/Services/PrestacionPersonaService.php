<?php

namespace Modules\Prestaciones\Services;

use App\Models\Ciudadano;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Prestaciones\Enums\EstadoPrestacionPersona;
use Modules\Prestaciones\Enums\ViaAcceso;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Models\PrestacionPersona;
use Modules\Prestaciones\Models\PrestacionPersonaEvento;
use Modules\Prestaciones\Models\ResolucionLinea;

/**
 * Único punto de escritura de las prestaciones de la persona (§2.6 del diseño, RN-08).
 *
 * Nacen activas (prestación directa, plaza asignada) o concedidas (resolución). Concedida no
 * es activa hasta registrar el inicio efectivo. Suspendida se reanuda; finalizada es final.
 * Cada cambio deja un evento inmutable con quién, cuándo y por qué.
 */
class PrestacionPersonaService
{
    /**
     * Registra una prestación directa: activa desde hoy, sin concesión ni origen.
     *
     * @param Ciudadano $ciudadano
     * @param Prestacion $prestacion Debe ser de vía directa y ofrecerse en el centro.
     * @param User $actor Quien la presta (con `solicitud.crear`).
     * @param Centro|null $centro Centro en que se presta (comprueba el catálogo efectivo).
     * @param PlanDeIntervencion|null $plan
     * @param PlanObjetivo|null $objetivo
     *
     * @return PrestacionPersona
     *
     * @throws AuthorizationException|DomainException
     */
    public function registrarDirecta(Ciudadano $ciudadano, Prestacion $prestacion, User $actor, ?Centro $centro = null, ?PlanDeIntervencion $plan = null, ?PlanObjetivo $objetivo = null): PrestacionPersona
    {
        if (! $actor->can('solicitud.crear')) {
            throw new AuthorizationException('No puedes registrar prestaciones.');
        }

        if ($prestacion->via_acceso !== ViaAcceso::Directa) {
            throw new DomainException('Esa prestación no es de vía directa.');
        }

        if ($centro !== null && ! app(ConfiguracionEfectivaCentro::class)->prestaciones($centro)->contains('id', $prestacion->id)) {
            throw new DomainException("{$centro->nombre} no ofrece esa prestación.");
        }

        return $this->crear($ciudadano->id, $prestacion->id, EstadoPrestacionPersona::Activa, $actor, [
            'plan_id' => $plan?->id,
            'plan_objetivo_id' => $objetivo?->id,
            'fecha_inicio' => today(),
        ]);
    }

    /**
     * Crea la prestación concedida de una línea de resolución (la llama ResolucionService).
     *
     * @param ResolucionLinea $linea Línea concedida.
     * @param int $ciudadanoId
     * @param PlanDeIntervencion|null $plan Plan de la solicitud, si lo tenía.
     * @param int|null $objetivoId
     * @param User|null $actor Quien registró la resolución (null si llegó del gestor).
     * @param int $registradaPorId Usuario al que se atribuye el registro.
     *
     * @return PrestacionPersona
     */
    public function desdeResolucion(ResolucionLinea $linea, int $ciudadanoId, ?PlanDeIntervencion $plan, ?int $objetivoId, ?User $actor, int $registradaPorId): PrestacionPersona
    {
        return $this->crear($ciudadanoId, $linea->prestacion_id, EstadoPrestacionPersona::Concedida, $actor, [
            'origen_type' => $linea->getMorphClass(),
            'origen_id' => $linea->id,
            'plan_id' => $plan?->id,
            'plan_objetivo_id' => $objetivoId,
            'fecha_concesion' => $linea->resolucion->fecha,
            'condiciones' => array_filter([
                'importe' => $linea->importe,
                'intensidad' => $linea->intensidad,
                'desde' => $linea->fecha_desde?->toDateString(),
                'hasta' => $linea->fecha_hasta?->toDateString(),
            ], fn ($valor) => $valor !== null),
        ], $registradaPorId);
    }

    /**
     * Crea la prestación activa de una plaza asignada, si la prescripción nació de una
     * prestación (decisión del 15 nov 2026). Sin prestación, no hace nada. Idempotente.
     *
     * @param Prescripcion $prescripcion Prescripción con plaza asignada.
     *
     * @return PrestacionPersona|null
     */
    public function desdePrescripcion(Prescripcion $prescripcion): ?PrestacionPersona
    {
        if ($prescripcion->prestacion_id === null) {
            return null;
        }

        $existente = PrestacionPersona::where('origen_type', $prescripcion->getMorphClass())->where('origen_id', $prescripcion->id)->first();
        if ($existente !== null) {
            return $existente;
        }

        $registradaPor = auth()->id() ?? User::where('profesional_id', $prescripcion->profesional_id)->value('id');

        return $this->crear($prescripcion->ciudadano_id, $prescripcion->prestacion_id, EstadoPrestacionPersona::Activa, auth()->user(), [
            'origen_type' => $prescripcion->getMorphClass(),
            'origen_id' => $prescripcion->id,
            'plan_id' => $prescripcion->plan_intervencion_id,
            'fecha_inicio' => $prescripcion->fecha_inicio ?? $prescripcion->fecha_asignacion ?? today(),
        ], $registradaPor);
    }

    /**
     * Crea la prestación activa de la persona al inscribirla en una actividad vinculada a
     * una prestación (fase 6). Una por persona y actividad (F6-10): si ya tiene esa
     * prestación sin finalizar, nacida de otra inscripción en la misma actividad, devuelve
     * esa. Sin prestación vinculada, no hace nada.
     *
     * @param InscripcionSesion $inscripcion Inscripción recién creada.
     *
     * @return PrestacionPersona|null
     */
    public function desdeInscripcion(InscripcionSesion $inscripcion): ?PrestacionPersona
    {
        $sesion = $inscripcion->sesion;
        $actividad = $sesion->actividad;

        if ($actividad->prestacion_id === null) {
            return null;
        }

        $inscripcionesDeLaActividad = InscripcionSesion::where('ciudadano_id', $inscripcion->ciudadano_id)
            ->whereIn('sesion_actividad_id', $actividad->sesiones()->withTrashed()->select('id'))
            ->select('id');

        $existente = PrestacionPersona::where('ciudadano_id', $inscripcion->ciudadano_id)
            ->where('prestacion_id', $actividad->prestacion_id)
            ->where('origen_type', $inscripcion->getMorphClass())
            ->whereIn('origen_id', $inscripcionesDeLaActividad)
            ->where('estado', '!=', EstadoPrestacionPersona::Finalizada->value)
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        return $this->crear($inscripcion->ciudadano_id, $actividad->prestacion_id, EstadoPrestacionPersona::Activa, $inscripcion->inscritaPor, [
            'origen_type' => $inscripcion->getMorphClass(),
            'origen_id' => $inscripcion->id,
            'plan_id' => $inscripcion->prescripcion?->plan_intervencion_id,
            'fecha_inicio' => $sesion->fecha,
        ]);
    }

    /**
     * Registra el inicio efectivo de una prestación concedida: pasa a activa (RN-08).
     *
     * @param PrestacionPersona $prestacion
     * @param Carbon $fecha Fecha en que la persona empieza a percibirla.
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function registrarInicio(PrestacionPersona $prestacion, Carbon $fecha, User $actor): void
    {
        $this->exigirPermiso($prestacion, $actor);
        $this->exigirEstado($prestacion, EstadoPrestacionPersona::Concedida, 'Solo se registra el inicio de una prestación concedida.');

        $this->cambiar($prestacion, EstadoPrestacionPersona::Activa, $actor, null, ['fecha_inicio' => $fecha]);
    }

    /**
     * Suspende una prestación activa, con motivo.
     *
     * @param PrestacionPersona $prestacion
     * @param string $motivo
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function suspender(PrestacionPersona $prestacion, string $motivo, User $actor): void
    {
        $this->exigirPermiso($prestacion, $actor);
        $this->exigirEstado($prestacion, EstadoPrestacionPersona::Activa, 'Solo se suspende una prestación activa.');
        $motivo = $this->exigirMotivo($motivo);

        $this->cambiar($prestacion, EstadoPrestacionPersona::Suspendida, $actor, $motivo, ['motivo_suspension' => $motivo]);
    }

    /**
     * Reanuda una prestación suspendida.
     *
     * @param PrestacionPersona $prestacion
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function reanudar(PrestacionPersona $prestacion, User $actor): void
    {
        $this->exigirPermiso($prestacion, $actor);
        $this->exigirEstado($prestacion, EstadoPrestacionPersona::Suspendida, 'Solo se reanuda una prestación suspendida.');

        $this->cambiar($prestacion, EstadoPrestacionPersona::Activa, $actor, null, ['motivo_suspension' => null]);
    }

    /**
     * Finaliza una prestación (desde concedida, activa o suspendida), con fecha y motivo. Es final.
     *
     * @param PrestacionPersona $prestacion
     * @param Carbon $fecha
     * @param string $motivo
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function finalizar(PrestacionPersona $prestacion, Carbon $fecha, string $motivo, User $actor): void
    {
        $this->exigirPermiso($prestacion, $actor);

        if ($prestacion->estado === EstadoPrestacionPersona::Finalizada) {
            throw new DomainException('La prestación ya está finalizada.');
        }

        $motivo = $this->exigirMotivo($motivo);
        $this->cambiar($prestacion, EstadoPrestacionPersona::Finalizada, $actor, $motivo, ['fecha_fin' => $fecha, 'motivo_fin' => $motivo]);
    }

    /**
     * Crea la prestación y su primer evento.
     *
     * @param int $ciudadanoId
     * @param int $prestacionId
     * @param EstadoPrestacionPersona $estado
     * @param User|null $actor
     * @param array<string, mixed> $datos
     * @param int|null $registradaPorId Por defecto, el actor.
     *
     * @return PrestacionPersona
     */
    private function crear(int $ciudadanoId, int $prestacionId, EstadoPrestacionPersona $estado, ?User $actor, array $datos, ?int $registradaPorId = null): PrestacionPersona
    {
        return DB::transaction(function () use ($ciudadanoId, $prestacionId, $estado, $actor, $datos, $registradaPorId): PrestacionPersona {
            $prestacion = PrestacionPersona::create($datos + [
                'ciudadano_id' => $ciudadanoId,
                'prestacion_id' => $prestacionId,
                'estado' => $estado,
                'registrada_por_id' => $registradaPorId ?? $actor?->id,
            ]);

            PrestacionPersonaEvento::create([
                'prestacion_persona_id' => $prestacion->id,
                'usuario_id' => $actor?->id,
                'estado_antes' => null,
                'estado_despues' => $estado,
            ]);

            return $prestacion;
        });
    }

    /**
     * Cambia el estado con sus datos y deja el evento.
     *
     * @param PrestacionPersona $prestacion
     * @param EstadoPrestacionPersona $nuevo
     * @param User $actor
     * @param string|null $motivo
     * @param array<string, mixed> $datos
     *
     * @return void
     */
    private function cambiar(PrestacionPersona $prestacion, EstadoPrestacionPersona $nuevo, User $actor, ?string $motivo, array $datos): void
    {
        DB::transaction(function () use ($prestacion, $nuevo, $actor, $motivo, $datos): void {
            $antes = $prestacion->estado;
            $prestacion->update($datos + ['estado' => $nuevo]);

            PrestacionPersonaEvento::create([
                'prestacion_persona_id' => $prestacion->id,
                'usuario_id' => $actor->id,
                'estado_antes' => $antes,
                'estado_despues' => $nuevo,
                'motivo' => $motivo,
            ]);
        });
    }

    /**
     * Exige poder gestionar esta prestación (PrestacionPersonaPolicy::gestionar).
     *
     * @param PrestacionPersona $prestacion
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    private function exigirPermiso(PrestacionPersona $prestacion, User $actor): void
    {
        if (! Gate::forUser($actor)->allows('gestionar', $prestacion)) {
            throw new AuthorizationException('No puedes cambiar el estado de las prestaciones.');
        }
    }

    /**
     * Rechaza el cambio si la prestación no está en el estado esperado.
     *
     * @param PrestacionPersona $prestacion
     * @param EstadoPrestacionPersona $esperado
     * @param string $mensaje
     *
     * @return void
     *
     * @throws DomainException
     */
    private function exigirEstado(PrestacionPersona $prestacion, EstadoPrestacionPersona $esperado, string $mensaje): void
    {
        if ($prestacion->estado !== $esperado) {
            throw new DomainException($mensaje);
        }
    }

    /**
     * Devuelve el motivo recortado o rechaza uno vacío.
     *
     * @param string $motivo
     *
     * @return string
     *
     * @throws DomainException
     */
    private function exigirMotivo(string $motivo): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new DomainException('Indica el motivo.');
        }

        return $motivo;
    }
}
