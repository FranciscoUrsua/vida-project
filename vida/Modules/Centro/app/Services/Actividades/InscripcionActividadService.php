<?php

namespace Modules\Centro\Services\Actividades;

use App\Models\Ciudadano;
use App\Models\User;
use App\Services\PresentacionPersona;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Enums\EstadoInscripcionSesion;
use Modules\Centro\Enums\ViaInscripcion;
use Modules\Centro\Models\InscripcionCentro;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Services\PrescripcionService;

/**
 * Único punto de escritura de las inscripciones de personas en sesiones de actividad
 * (fase 6, docs/modulo-centros.md §2.6).
 *
 * La inscripción es sesión a sesión (F6-1). Por acceso libre la hace el personal adscrito
 * al centro de la actividad (RN-15); por prescripción nace de una prescripción desde un plan
 * (PrescripcionService) y consume el cupo de prescripción. Con el aforo completo se rechaza
 * (F6-3). La comprobación de plazas y la escritura van en una transacción con un bloqueo
 * por sesión, para que dos inscripciones simultáneas no superen el aforo. Nada se borra:
 * la baja es lógica, con motivo (F6-5).
 */
class InscripcionActividadService
{
    /**
     * Clave de los bloqueos consultivos de PostgreSQL de esta tabla («fase 6»); la segunda
     * clave es el id de la sesión.
     */
    public const CLAVE_BLOQUEO = 61001;

    /**
     * Qué hacer al crear una inscripción (fase 3 bis: la prestación de la persona, F6-10).
     * Lo configura PrestacionesServiceProvider.
     *
     * @var callable(InscripcionSesion): void
     */
    private $alInscribir;

    /**
     * Arranca sin acción al inscribir hasta que se configure.
     */
    public function __construct()
    {
        $this->alInscribir = fn (InscripcionSesion $inscripcion): null => null;
    }

    /**
     * Inyecta lo que se hace al crear una inscripción.
     *
     * @param callable(InscripcionSesion): void $accion
     *
     * @return void
     */
    public function setAlInscribir(callable $accion): void
    {
        $this->alInscribir = $accion;
    }

    /**
     * Inscribe a una persona en una sesión por acceso libre.
     *
     * @param SesionActividad $sesion
     * @param Ciudadano $ciudadano
     * @param User $actor Personal adscrito al centro de la actividad, o su supervisión.
     * @param bool $inscribirEnCentro Consentimiento para inscribirla antes en el centro si la actividad lo exige (F6-4).
     *
     * @return InscripcionSesion
     *
     * @throws AuthorizationException Si el actor no puede inscribir en el centro o no puede ver a la persona.
     * @throws InscripcionCentroRequerida Si hace falta la inscripción en el centro y no se ha consentido.
     * @throws DomainException Si la actividad o la sesión no admiten la inscripción o no hay plaza.
     */
    public function inscribir(SesionActividad $sesion, Ciudadano $ciudadano, User $actor, bool $inscribirEnCentro = false): InscripcionSesion
    {
        if (! Gate::forUser($actor)->allows('inscribir', [InscripcionSesion::class, $sesion])) {
            throw new AuthorizationException('Para inscribir en esta actividad tienes que estar adscrito a su centro.');
        }

        $this->exigirVisible($ciudadano, $actor);

        if (! ViaInscripcion::Libre->admitidaEn($sesion->actividad->modo_acceso)) {
            throw new DomainException('Esta actividad es solo de prescripción: se accede desde un plan.');
        }

        return $this->registrar($sesion, $ciudadano->id, ViaInscripcion::Libre, $actor, null, $inscribirEnCentro);
    }

    /**
     * Inscribe por la vía de prescripción a la persona de una prescripción a una sesión.
     * Lo llama PrescripcionService dentro de su transacción: si no hay plaza, la excepción
     * deshace también la prescripción.
     *
     * @param Prescripcion $prescripcion Prescripción a una sesión, ya guardada.
     * @param User $actor Quien prescribe.
     * @param bool $inscribirEnCentro Consentimiento para la inscripción en el centro (F6-4).
     *
     * @return InscripcionSesion
     *
     * @throws InscripcionCentroRequerida|DomainException
     */
    public function inscribirPorPrescripcion(Prescripcion $prescripcion, User $actor, bool $inscribirEnCentro = false): InscripcionSesion
    {
        $sesion = SesionActividad::findOrFail($prescripcion->destino_id);

        if (! ViaInscripcion::Prescripcion->admitidaEn($sesion->actividad->modo_acceso)) {
            throw new DomainException('Esta actividad es de acceso libre: no admite prescripción.');
        }

        return $this->registrar($sesion, $prescripcion->ciudadano_id, ViaInscripcion::Prescripcion, $actor, $prescripcion, $inscribirEnCentro);
    }

    /**
     * Da de baja una inscripción y libera su plaza. Si nació de una prescripción, la
     * prescripción se cancela con el mismo motivo.
     *
     * @param InscripcionSesion $inscripcion
     * @param string $motivo Obligatorio.
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function darDeBaja(InscripcionSesion $inscripcion, string $motivo, User $actor): void
    {
        if (! Gate::forUser($actor)->allows('darDeBaja', $inscripcion)) {
            throw new AuthorizationException('No puedes dar de baja esta inscripción.');
        }

        $this->registrarBaja($inscripcion, $motivo, $actor);
    }

    /**
     * Da de baja una inscripción sin comprobar permisos: para la cancelación de una sesión
     * (ya autorizada) y la cancelación de su prescripción.
     *
     * @param InscripcionSesion $inscripcion
     * @param string $motivo
     * @param User|null $actor Null si la baja la provoca un proceso sin usuario.
     *
     * @return void
     *
     * @throws DomainException Si no hay motivo o la inscripción ya está de baja.
     */
    public function registrarBaja(InscripcionSesion $inscripcion, string $motivo, ?User $actor): void
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new DomainException('Indica el motivo de la baja.');
        }

        if (! $inscripcion->vigente()) {
            throw new DomainException('La inscripción ya está dada de baja.');
        }

        DB::transaction(function () use ($inscripcion, $motivo, $actor): void {
            $inscripcion->update([
                'estado' => EstadoInscripcionSesion::Baja,
                'fecha_baja' => now(),
                'motivo_baja' => $motivo,
                'baja_por_id' => $actor?->id,
            ]);

            // La plaza de una prescripción es su inscripción: sin ella, la prescripción se cancela
            if ($inscripcion->prescripcion !== null && $inscripcion->prescripcion->estado !== 'cancelada') {
                app(PrescripcionService::class)->cancelar($inscripcion->prescripcion, $motivo);
            }
        });
    }

    /**
     * Comprueba y escribe la inscripción con la sesión bloqueada.
     *
     * @param SesionActividad $sesion
     * @param int $ciudadanoId
     * @param ViaInscripcion $via
     * @param User $actor
     * @param Prescripcion|null $prescripcion
     * @param bool $inscribirEnCentro
     *
     * @return InscripcionSesion
     *
     * @throws InscripcionCentroRequerida|DomainException
     */
    private function registrar(SesionActividad $sesion, int $ciudadanoId, ViaInscripcion $via, User $actor, ?Prescripcion $prescripcion, bool $inscribirEnCentro): InscripcionSesion
    {
        $inscripcion = DB::transaction(function () use ($sesion, $ciudadanoId, $via, $actor, $prescripcion, $inscribirEnCentro): InscripcionSesion {
            // Bloqueo consultivo por sesión hasta el fin de la transacción: serializa las
            // inscripciones de la misma sesión, así el recuento de plazas no queda obsoleto.
            // Es consultivo y no de fila para que bloquee aunque la fila no sea visible aún
            // para la otra transacción.
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [self::CLAVE_BLOQUEO, $sesion->id]);

            $sesion->refresh();
            $actividad = $sesion->actividad;

            if (! $actividad->admiteInscripciones()) {
                throw new DomainException('La actividad está dada de baja: no admite inscripciones.');
            }

            if (! $sesion->admiteInscripciones()) {
                throw new DomainException('Solo se inscribe en sesiones programadas que aún no han empezado.');
            }

            $yaInscrita = InscripcionSesion::where('sesion_actividad_id', $sesion->id)
                ->where('ciudadano_id', $ciudadanoId)
                ->vigentes()
                ->exists();

            if ($yaInscrita) {
                throw new DomainException('La persona ya está inscrita en esta sesión.');
            }

            $plazas = $sesion->plazasLibres($via);

            if ($plazas !== null && $plazas < 1) {
                throw new DomainException($via === ViaInscripcion::Prescripcion
                    ? 'No quedan plazas de prescripción en esta sesión.'
                    : 'No quedan plazas de acceso libre en esta sesión.');
            }

            if ($actividad->requiere_inscripcion_centro && ! $actividad->ciudadanoInscritoEnCentro($ciudadanoId)) {
                if (! $inscribirEnCentro) {
                    throw new InscripcionCentroRequerida($actividad->centro);
                }

                InscripcionCentro::create([
                    'centro_id' => $actividad->centro_id,
                    'ciudadano_id' => $ciudadanoId,
                    'fecha_alta' => today(),
                    'activa' => true,
                ]);
            }

            $inscripcion = InscripcionSesion::create([
                'sesion_actividad_id' => $sesion->id,
                'ciudadano_id' => $ciudadanoId,
                'via' => $via,
                'prescripcion_id' => $prescripcion?->id,
                'estado' => EstadoInscripcionSesion::Inscrita,
                'inscrita_por_id' => $actor->id,
            ]);

            ($this->alInscribir)($inscripcion);

            return $inscripcion;
        });

        return $inscripcion;
    }

    /**
     * Rechaza inscribir a una persona protegida que el actor no puede ver (RN-17).
     *
     * @param Ciudadano $ciudadano
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    private function exigirVisible(Ciudadano $ciudadano, User $actor): void
    {
        if (! PresentacionPersona::visible($ciudadano, $actor)) {
            throw new AuthorizationException('No tienes acceso a esta persona.');
        }
    }
}
