<?php

namespace Modules\Centro\Services\Asignacion;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Modules\Centro\Enums\EstadoAsignacionPendiente;
use Modules\Centro\Enums\ModoAsignacionCentro;
use Modules\Centro\Enums\TipoAsignacionPendiente;
use Modules\Centro\Models\AsignacionCentro;
use Modules\Centro\Models\AsignacionPendiente;
use Modules\Centro\Models\Centro;

/**
 * Único punto de escritura de las asignaciones de centro y de sus entradas en
 * la bandeja del supervisor.
 *
 * Reglas que protege (docs/modulo-asignacion.md):
 * - Solo se asigna solo lo inequívoco; el resto va a la bandeja (RN-09).
 * - Un cambio de domicilio no traslada a nadie: genera una propuesta (RN-10).
 * - La persona no cambia de centro a petición propia; solo el supervisor, con
 *   motivo (RN-04).
 * - Historial aditivo: se cierra la vigente y se crea otra; nunca se cambia el
 *   centro de una asignación existente (RN-11, principio 4.3).
 *
 * Las personas sin hogar quedan fuera de la v1: ni se asignan por dirección ni
 * van a la bandeja (se modelarán con el servicio de calle y sus zonas).
 */
class AsignacionCentroService
{
    /**
     * @param ResolucionCentroService $resolucion
     * @param CentroDeUsuario $centros
     */
    public function __construct(
        private readonly ResolucionCentroService $resolucion,
        private readonly CentroDeUsuario $centros,
    ) {}

    /**
     * Asignación vigente de la persona para un tipo de centro.
     *
     * @param Ciudadano $ciudadano
     * @param string $tipoCentro
     * @return AsignacionCentro|null
     */
    public function vigente(Ciudadano $ciudadano, string $tipoCentro): ?AsignacionCentro
    {
        return AsignacionCentro::vigentes()
            ->where('ciudadano_id', $ciudadano->id)
            ->where('tipo_centro', $tipoCentro)
            ->first();
    }

    /**
     * Asigna el centro del tipo que corresponde a la dirección, si la persona aún
     * no tiene uno y la resolución es inequívoca. Si no lo es, abre o actualiza
     * su entrada en la bandeja. Nunca cambia una asignación vigente.
     *
     * @param Ciudadano $ciudadano
     * @param string $tipoCentro
     * @param User|null $actor Usuario que provoca la evaluación; su centro es la bandeja.
     * @return AsignacionCentro|null La asignación vigente, o null si queda pendiente.
     */
    public function asignarPorDireccion(Ciudadano $ciudadano, string $tipoCentro, ?User $actor = null): ?AsignacionCentro
    {
        if ($ciudadano->es_psh) {
            return null;
        }

        if ($vigente = $this->vigente($ciudadano, $tipoCentro)) {
            return $vigente;
        }

        $resultado = $this->resolucion->resolver($ciudadano, $tipoCentro);

        if ($resultado->centro === null) {
            $this->registrarSinCentro($ciudadano, $tipoCentro, $resultado, $actor);

            return null;
        }

        return DB::transaction(function () use ($ciudadano, $tipoCentro, $resultado) {
            $asignacion = AsignacionCentro::create([
                'ciudadano_id' => $ciudadano->id,
                'tipo_centro' => $tipoCentro,
                'centro_id' => $resultado->centro->id,
                'modo' => ModoAsignacionCentro::Geografico,
                'seccion_censal_codigo' => $resultado->seccionCensal,
                'fecha_inicio' => today(),
            ]);

            $this->cerrarPendientes($ciudadano, $tipoCentro, [TipoAsignacionPendiente::SinCentro], null, 'Asignado por domicilio.');

            return $asignacion;
        });
    }

    /**
     * Asigna un centro de libre elección elegido por la persona (CIAM).
     *
     * @param Ciudadano $ciudadano
     * @param Centro $centro Centro elegido; debe ser de libre elección.
     * @param User $actor Profesional que registra la elección.
     * @return AsignacionCentro
     *
     * @throws LogicException Si el centro no es de libre elección o la persona ya tiene otro del tipo.
     */
    public function asignarPorEleccion(Ciudadano $ciudadano, Centro $centro, User $actor): AsignacionCentro
    {
        if (! $centro->inscripcion_libre || $centro->tipo_centro === null) {
            throw new LogicException("{$centro->nombre} no es un centro de libre elección: se asigna por domicilio.");
        }

        $vigente = $this->vigente($ciudadano, $centro->tipo_centro);

        if ($vigente !== null) {
            if ($vigente->centro_id === $centro->id) {
                return $vigente;
            }

            // RN-04: sin cambio a petición de la persona; solo el supervisor, con motivo
            throw new LogicException('La persona ya tiene asignado otro centro de este tipo. Solo supervisión puede cambiarlo.');
        }

        return AsignacionCentro::create([
            'ciudadano_id' => $ciudadano->id,
            'tipo_centro' => $centro->tipo_centro,
            'centro_id' => $centro->id,
            'modo' => ModoAsignacionCentro::Eleccion,
            'asignado_por_id' => $actor->id,
            'fecha_inicio' => today(),
        ]);
    }

    /**
     * Asignación manual por el supervisor: cierra la vigente del tipo y crea la
     * nueva. Resuelve las entradas abiertas de la persona para ese tipo.
     *
     * @param Ciudadano $ciudadano
     * @param Centro $centro
     * @param string $motivo Obligatorio.
     * @param User $supervisor
     * @return AsignacionCentro
     *
     * @throws AuthorizationException Si el usuario no tiene rol de supervisión.
     * @throws InvalidArgumentException Si falta el motivo.
     * @throws LogicException Si el centro no tiene tipo.
     */
    public function asignarManual(Ciudadano $ciudadano, Centro $centro, string $motivo, User $supervisor): AsignacionCentro
    {
        $this->exigirSupervision($supervisor);
        $motivo = $this->exigirMotivo($motivo);

        if ($centro->tipo_centro === null) {
            throw new LogicException("{$centro->nombre} no tiene tipo de centro configurado.");
        }

        return DB::transaction(function () use ($ciudadano, $centro, $motivo, $supervisor) {
            // Bloquea la vigente para que dos asignaciones simultáneas no se pisen
            $vigente = AsignacionCentro::vigentes()
                ->where('ciudadano_id', $ciudadano->id)
                ->where('tipo_centro', $centro->tipo_centro)
                ->lockForUpdate()
                ->first();

            $vigente?->update(['fecha_fin' => today()]);

            $asignacion = AsignacionCentro::create([
                'ciudadano_id' => $ciudadano->id,
                'tipo_centro' => $centro->tipo_centro,
                'centro_id' => $centro->id,
                'modo' => ModoAsignacionCentro::Manual,
                'motivo' => $motivo,
                'asignado_por_id' => $supervisor->id,
                'fecha_inicio' => today(),
            ]);

            $this->cerrarPendientes(
                $ciudadano,
                $centro->tipo_centro,
                [TipoAsignacionPendiente::SinCentro, TipoAsignacionPendiente::CambioDomicilio],
                $supervisor,
                $motivo,
            );

            return $asignacion;
        });
    }

    /**
     * Tras un cambio de domicilio, si la persona tiene una asignación por
     * domicilio vigente y la nueva dirección corresponde a otro centro, abre una
     * propuesta de traslado en la bandeja del centro actual. No cambia nada.
     *
     * @param Ciudadano $ciudadano
     * @param string $tipoCentro
     * @return AsignacionPendiente|null La propuesta abierta, si la hay.
     */
    public function evaluarCambioDomicilio(Ciudadano $ciudadano, string $tipoCentro): ?AsignacionPendiente
    {
        $vigente = $this->vigente($ciudadano, $tipoCentro);

        if ($vigente === null || $vigente->modo !== ModoAsignacionCentro::Geografico) {
            return null;
        }

        $resultado = $this->resolucion->resolver($ciudadano, $tipoCentro);
        $abierta = $this->pendienteAbierta($ciudadano, TipoAsignacionPendiente::CambioDomicilio, $tipoCentro);

        // Sin un centro distinto e inequívoco no hay nada que proponer
        if ($resultado->centro === null || $resultado->centro->id === $vigente->centro_id) {
            if ($abierta !== null && $resultado->centro?->id === $vigente->centro_id) {
                $abierta->update([
                    'estado' => EstadoAsignacionPendiente::Descartada,
                    'resuelta_en' => now(),
                    'motivo_resolucion' => 'El domicilio vuelve a corresponder al centro actual.',
                ]);
            }

            return null;
        }

        $datos = [
            'centro_id' => $vigente->centro_id,
            'centro_propuesto_id' => $resultado->centro->id,
        ];

        if ($abierta !== null) {
            $abierta->update($datos);

            return $abierta;
        }

        return AsignacionPendiente::create($datos + [
            'ciudadano_id' => $ciudadano->id,
            'tipo' => TipoAsignacionPendiente::CambioDomicilio,
            'tipo_centro' => $tipoCentro,
            'estado' => EstadoAsignacionPendiente::Pendiente,
        ]);
    }

    /**
     * Confirma una propuesta de cambio de centro por domicilio: asignación
     * manual al centro propuesto, con el motivo del supervisor.
     *
     * @param AsignacionPendiente $propuesta
     * @param string $motivo
     * @param User $supervisor
     * @return AsignacionCentro
     *
     * @throws LogicException Si la entrada no es una propuesta abierta.
     */
    public function confirmarCambioDomicilio(AsignacionPendiente $propuesta, string $motivo, User $supervisor): AsignacionCentro
    {
        if ($propuesta->tipo !== TipoAsignacionPendiente::CambioDomicilio
            || $propuesta->estado !== EstadoAsignacionPendiente::Pendiente
            || $propuesta->centroPropuesto === null) {
            throw new LogicException('Solo se puede confirmar una propuesta de cambio de domicilio abierta.');
        }

        return $this->asignarManual($propuesta->ciudadano, $propuesta->centroPropuesto, $motivo, $supervisor);
    }

    /**
     * Descarta una entrada de la bandeja sin asignar nada, con motivo.
     *
     * @param AsignacionPendiente $pendiente
     * @param string $motivo
     * @param User $supervisor
     * @return void
     *
     * @throws AuthorizationException Si el usuario no tiene rol de supervisión.
     * @throws InvalidArgumentException Si falta el motivo.
     */
    public function descartar(AsignacionPendiente $pendiente, string $motivo, User $supervisor): void
    {
        $this->exigirSupervision($supervisor);

        $pendiente->update([
            'estado' => EstadoAsignacionPendiente::Descartada,
            'resuelta_por_id' => $supervisor->id,
            'resuelta_en' => now(),
            'motivo_resolucion' => $this->exigirMotivo($motivo),
        ]);
    }

    /**
     * Abre o actualiza la entrada «sin centro» de la persona. La bandeja es el
     * centro de quien actúa; sin usuario (reintento en cola), se conserva la de
     * la entrada ya abierta; si nunca hubo, queda sin bandeja (la ven todos los
     * supervisores del tipo).
     *
     * @param Ciudadano $ciudadano
     * @param string $tipoCentro
     * @param ResultadoResolucionCentro $resultado
     * @param User|null $actor
     * @return AsignacionPendiente
     */
    private function registrarSinCentro(Ciudadano $ciudadano, string $tipoCentro, ResultadoResolucionCentro $resultado, ?User $actor): AsignacionPendiente
    {
        $abierta = $this->pendienteAbierta($ciudadano, TipoAsignacionPendiente::SinCentro, $tipoCentro);

        $datos = [
            'motivo' => $resultado->motivoPendiente,
            'centros_candidatos' => $resultado->candidatos->isEmpty() ? null : $resultado->candidatos->pluck('id')->all(),
            'centro_id' => $this->centros->centroActivo($actor)?->id ?? $abierta?->centro_id,
        ];

        if ($abierta !== null) {
            $abierta->update($datos);

            return $abierta;
        }

        return AsignacionPendiente::create($datos + [
            'ciudadano_id' => $ciudadano->id,
            'tipo' => TipoAsignacionPendiente::SinCentro,
            'tipo_centro' => $tipoCentro,
            'estado' => EstadoAsignacionPendiente::Pendiente,
        ]);
    }

    /**
     * Entrada abierta de un tipo para la persona y el tipo de centro.
     *
     * @param Ciudadano $ciudadano
     * @param TipoAsignacionPendiente $tipo
     * @param string $tipoCentro
     * @return AsignacionPendiente|null
     */
    private function pendienteAbierta(Ciudadano $ciudadano, TipoAsignacionPendiente $tipo, string $tipoCentro): ?AsignacionPendiente
    {
        return AsignacionPendiente::abiertas()
            ->where('ciudadano_id', $ciudadano->id)
            ->where('tipo', $tipo)
            ->where('tipo_centro', $tipoCentro)
            ->first();
    }

    /**
     * Marca como resueltas las entradas abiertas de la persona de los tipos dados.
     *
     * @param Ciudadano $ciudadano
     * @param string $tipoCentro
     * @param list<TipoAsignacionPendiente> $tipos
     * @param User|null $resueltaPor Null si la resolvió el sistema.
     * @param string $motivo
     * @return void
     */
    private function cerrarPendientes(Ciudadano $ciudadano, string $tipoCentro, array $tipos, ?User $resueltaPor, string $motivo): void
    {
        AsignacionPendiente::abiertas()
            ->where('ciudadano_id', $ciudadano->id)
            ->where('tipo_centro', $tipoCentro)
            ->whereIn('tipo', $tipos)
            ->get()
            ->each(fn (AsignacionPendiente $p) => $p->update([
                'estado' => EstadoAsignacionPendiente::Resuelta,
                'resuelta_por_id' => $resueltaPor?->id,
                'resuelta_en' => now(),
                'motivo_resolucion' => $motivo,
            ]));
    }

    /**
     * Exige rol de supervisión (RN-04: solo el supervisor cambia asignaciones).
     *
     * @param User $usuario
     * @return void
     *
     * @throws AuthorizationException
     */
    private function exigirSupervision(User $usuario): void
    {
        if (! $usuario->hasRole('supervision')) {
            throw new AuthorizationException('Solo supervisión puede asignar o descartar manualmente.');
        }
    }

    /**
     * Exige un motivo no vacío y lo devuelve recortado.
     *
     * @param string $motivo
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private function exigirMotivo(string $motivo): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('La asignación manual exige un motivo.');
        }

        return $motivo;
    }
}
