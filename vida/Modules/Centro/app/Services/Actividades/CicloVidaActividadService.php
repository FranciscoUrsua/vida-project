<?php

namespace Modules\Centro\Services\Actividades;

use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Services\PrescripcionService;

/**
 * Ciclo de vida de actividades y sesiones (fase 6, paso 3, F6-5): nada con personas se borra.
 *
 * Una actividad se da de baja (fecha y motivo) y sus sesiones futuras se cancelan. Una
 * sesión con inscripciones o prescripciones se cancela, nunca se borra; solo una sesión sin
 * ninguna se puede eliminar. Al cancelar, las inscripciones se dan de baja con «Sesión
 * cancelada» y se avisa a quien inscribió o prescribió a cada persona. Marcar una sesión
 * como celebrada no registra asistencia (F6-6). Todo lo hace la supervisión del centro.
 */
class CicloVidaActividadService
{
    /** Motivo de baja de las inscripciones de una sesión cancelada. */
    public const MOTIVO_SESION_CANCELADA = 'Sesión cancelada';

    /**
     * Aviso a quien inscribió o prescribió a personas en una sesión cancelada. Lo configura
     * IntervencionServiceProvider con AvisoActividadesService.
     *
     * @var callable(int, SesionActividad, int): void Usuario, sesión y número de personas.
     */
    private $avisoCancelacion;

    /**
     * Arranca con las dependencias y sin aviso hasta que se configure.
     *
     * @param InscripcionActividadService $inscripciones
     * @param PrescripcionService $prescripciones
     */
    public function __construct(
        private readonly InscripcionActividadService $inscripciones,
        private readonly PrescripcionService $prescripciones,
    ) {
        $this->avisoCancelacion = fn (int $usuarioId, SesionActividad $sesion, int $personas): null => null;
    }

    /**
     * Inyecta el aviso de sesión cancelada.
     *
     * @param callable(int, SesionActividad, int): void $aviso
     *
     * @return void
     */
    public function setAvisoCancelacion(callable $aviso): void
    {
        $this->avisoCancelacion = $aviso;
    }

    /**
     * Da de baja una actividad: deja de admitir inscripciones y sus sesiones programadas
     * futuras se cancelan con el mismo motivo. La actividad sigue existiendo.
     *
     * @param Actividad $actividad
     * @param string $motivo Obligatorio.
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function darDeBajaActividad(Actividad $actividad, string $motivo, User $actor): void
    {
        $this->exigirGestion($actividad, $actor);
        $motivo = $this->exigirMotivo($motivo, 'Indica el motivo de la baja.');

        if (! $actividad->admiteInscripciones()) {
            throw new DomainException('La actividad ya está dada de baja.');
        }

        DB::transaction(function () use ($actividad, $motivo, $actor): void {
            $actividad->update(['activa' => false, 'fecha_baja' => today(), 'motivo_baja' => $motivo]);

            $futuras = $actividad->sesiones()->where('estado', 'programada')->get()
                ->filter(fn (SesionActividad $sesion) => $sesion->inicio()->isFuture());

            foreach ($futuras as $sesion) {
                $this->cancelarSinAutorizar($sesion, "Actividad dada de baja: {$motivo}", $actor);
            }
        });
    }

    /**
     * Cancela una sesión programada: da de baja sus inscripciones, cancela sus
     * prescripciones en curso y avisa a quien las hizo.
     *
     * @param SesionActividad $sesion
     * @param string $motivo Obligatorio.
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function cancelarSesion(SesionActividad $sesion, string $motivo, User $actor): void
    {
        $this->exigirGestion($sesion->actividad, $actor);
        $motivo = $this->exigirMotivo($motivo, 'Indica el motivo de la cancelación.');

        if ($sesion->estado !== 'programada') {
            throw new DomainException('Solo se cancela una sesión programada.');
        }

        DB::transaction(fn () => $this->cancelarSinAutorizar($sesion, $motivo, $actor));
    }

    /**
     * Elimina una sesión que no tiene ninguna inscripción ni prescripción (F6-5).
     *
     * @param SesionActividad $sesion
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function eliminarSesion(SesionActividad $sesion, User $actor): void
    {
        $this->exigirGestion($sesion->actividad, $actor);

        if ($sesion->tieneParticipantes()) {
            throw new DomainException('La sesión tiene inscripciones o prescripciones: no se elimina, se cancela.');
        }

        $sesion->delete();
    }

    /**
     * Marca como celebrada una sesión programada que ya ha empezado. No registra asistencia (F6-6).
     *
     * @param SesionActividad $sesion
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function marcarCelebrada(SesionActividad $sesion, User $actor): void
    {
        $this->exigirGestion($sesion->actividad, $actor);

        if ($sesion->estado !== 'programada') {
            throw new DomainException('Solo se marca como celebrada una sesión programada.');
        }

        if ($sesion->inicio()->isFuture()) {
            throw new DomainException('La sesión aún no se ha celebrado.');
        }

        $sesion->update(['estado' => 'celebrada']);
    }

    /**
     * Cancela la sesión dentro de una transacción ya abierta y avisa a quien inscribió o
     * prescribió (un aviso por usuario, con el número de personas).
     *
     * @param SesionActividad $sesion
     * @param string $motivo
     * @param User $actor
     *
     * @return void
     */
    private function cancelarSinAutorizar(SesionActividad $sesion, string $motivo, User $actor): void
    {
        $sesion->update([
            'estado' => 'cancelada',
            'motivo_cancelacion' => $motivo,
            'fecha_cancelacion' => now(),
            'cancelada_por_id' => $actor->id,
        ]);

        /** @var array<int, int> $personasPorUsuario Usuario => personas afectadas. */
        $personasPorUsuario = [];

        $vigentes = $sesion->inscripciones()->vigentes()->with('prescripcion')->get();

        foreach ($vigentes as $inscripcion) {
            /** @var InscripcionSesion $inscripcion */
            $this->inscripciones->registrarBaja($inscripcion, self::MOTIVO_SESION_CANCELADA, $actor);
            $personasPorUsuario[$inscripcion->inscrita_por_id] = ($personasPorUsuario[$inscripcion->inscrita_por_id] ?? 0) + 1;
        }

        // Prescripciones en curso que no llegaron a inscripción (anteriores a la fase 6)
        $sueltas = $sesion->prescripciones()->enCurso()->get();

        foreach ($sueltas as $prescripcion) {
            /** @var Prescripcion $prescripcion */
            $this->prescripciones->cancelar($prescripcion, self::MOTIVO_SESION_CANCELADA);
            $usuarioId = User::where('profesional_id', $prescripcion->profesional_id)->value('id');

            if ($usuarioId !== null) {
                $personasPorUsuario[$usuarioId] = ($personasPorUsuario[$usuarioId] ?? 0) + 1;
            }
        }

        foreach ($personasPorUsuario as $usuarioId => $personas) {
            ($this->avisoCancelacion)($usuarioId, $sesion, $personas);
        }
    }

    /**
     * Rechaza la gestión de la actividad a quien no supervisa su centro.
     *
     * @param Actividad $actividad
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    private function exigirGestion(Actividad $actividad, User $actor): void
    {
        if (! Gate::forUser($actor)->allows('gestionarActividad', [InscripcionSesion::class, $actividad])) {
            throw new AuthorizationException('Solo la supervisión del centro gestiona sus actividades.');
        }
    }

    /**
     * Motivo sin espacios sobrantes, o rechazo si está vacío.
     *
     * @param string $motivo
     * @param string $mensaje
     *
     * @return string
     *
     * @throws DomainException
     */
    private function exigirMotivo(string $motivo, string $mensaje): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new DomainException($mensaje);
        }

        return $motivo;
    }
}
