<?php

namespace Modules\Intervencion\Services;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Enums\ModoAsignacionReferenciaCentro;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\AsignacionCentroService;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\Asignacion\ResolucionCentroService;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;

/**
 * Apertura de la Historia Social de un ciudadano por un profesional.
 *
 * Abrir la historia es un acto profesional explícito (docs/modulo-ciudadania.md).
 * La referencia ya no es siempre quien la abre: depende del modo del centro
 * (docs/modulo-asignacion.md §4.4). La apertura:
 * 1. abre la historia en la UO activa del profesional;
 * 2. toma como centro de la apertura el de esa UO;
 * 3. asegura el centro de la persona del mismo tipo (por domicilio, o el propio
 *    centro si es de libre elección y la persona acude a él);
 * 4. asigna la referencia en el centro de la persona (AsignacionReferenciaService).
 *
 * Si la UO del profesional no tiene centro, o la persona es una persona sin
 * hogar (fuera de esta fase), se mantiene el comportamiento anterior: quien
 * abre queda de referencia. En un centro «quien abre», también.
 * En los demás, si la persona no tiene centro, la historia se abre sin
 * referencia y queda en la bandeja del supervisor. Lo usan el botón de la
 * ficha y la confirmación del alta.
 */
class AperturaHistoriaService
{
    /**
     * @param CentroDeUsuario $centros
     * @param ResolucionCentroService $resolucion
     * @param AsignacionCentroService $asignacionCentro
     * @param AsignacionReferenciaService $asignacionReferencia
     */
    public function __construct(
        private readonly CentroDeUsuario $centros,
        private readonly ResolucionCentroService $resolucion,
        private readonly AsignacionCentroService $asignacionCentro,
        private readonly AsignacionReferenciaService $asignacionReferencia,
    ) {}

    /**
     * Abre la historia del ciudadano en la UO activa del profesional y le asigna
     * profesional de referencia según el modo del centro. Si el ciudadano ya
     * tiene historia, la devuelve sin cambiar nada (la historia social es única).
     *
     * @param int $ciudadanoId Ciudadano cuya historia se abre.
     * @param User $profesional Profesional que la abre.
     * @param User|null $elegido Profesional elegido por la persona (centros de libre elección).
     * @return HistoriaSocial
     *
     * @throws AuthorizationException Si el profesional no puede crear historias.
     */
    public function abrir(int $ciudadanoId, User $profesional, ?User $elegido = null): HistoriaSocial
    {
        Gate::forUser($profesional)->authorize('create', HistoriaSocial::class);

        return DB::transaction(function () use ($ciudadanoId, $profesional, $elegido) {
            // Sin scopes: la historia puede estar en otra UO y sigue siendo la única del ciudadano
            $existente = HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $ciudadanoId)->first();
            if ($existente !== null) {
                return $existente;
            }

            $historia = HistoriaSocial::create([
                'ciudadano_id' => $ciudadanoId,
                'unidad_organizativa_id' => $profesional->uosActivas()->first()?->id,
                'estado' => 'abierta',
            ]);

            $this->asignarReferencia($historia, Ciudadano::findOrFail($ciudadanoId), $profesional, $elegido);

            return $historia;
        });
    }

    /**
     * Modo de asignación de referencia del centro en el que abriría la historia
     * el profesional, para adaptar la interfaz. Null si su UO no tiene centro
     * (se comporta como «quien abre»).
     *
     * @param User $profesional
     * @return ModoAsignacionReferenciaCentro|null
     */
    public function modoDelCentro(User $profesional): ?ModoAsignacionReferenciaCentro
    {
        return $this->centros->centroActivo($profesional)?->modo_asignacion_referencia;
    }

    /**
     * Asigna la referencia de la historia recién abierta.
     *
     * @param HistoriaSocial $historia
     * @param Ciudadano $ciudadano
     * @param User $profesional
     * @param User|null $elegido
     * @return AsignacionProfesional|null
     */
    private function asignarReferencia(HistoriaSocial $historia, Ciudadano $ciudadano, User $profesional, ?User $elegido): ?AsignacionProfesional
    {
        $centroApertura = $this->centros->centroActivo($profesional);

        // Sin centro no hay reparto posible, y las personas sin hogar quedan fuera de
        // esta fase (se asignarán por el servicio de calle y sus zonas): comportamiento anterior
        if ($centroApertura === null || $ciudadano->es_psh) {
            return AsignacionProfesional::create([
                'historia_id' => $historia->id,
                'profesional_id' => $profesional->id,
                'centro_id' => $centroApertura?->id,
                'origen' => OrigenAsignacionReferencia::QuienAbre,
                'fecha_inicio' => today(),
            ]);
        }

        $centroPersona = $this->centroDeLaPersona($ciudadano, $centroApertura, $profesional);

        if ($centroPersona === null) {
            // En «quien abre» la referencia no depende de la geografía
            if ($centroApertura->modo_asignacion_referencia === ModoAsignacionReferenciaCentro::QuienAbre) {
                return $this->asignacionReferencia->asignarInicial($historia, $centroApertura, $profesional);
            }

            $this->asignacionReferencia->registrarSinReferencia($historia, $centroApertura, null);

            return null;
        }

        return $this->asignacionReferencia->asignarInicial($historia, $centroPersona, $profesional, $elegido);
    }

    /**
     * Centro de la persona del mismo tipo que el de la apertura, asignándolo si
     * aún no lo tiene. Si el centro de la apertura no tiene tipo, o su tipo no
     * se asigna ni por domicilio ni por elección, la persona se atiende en él.
     *
     * @param Ciudadano $ciudadano
     * @param Centro $centroApertura
     * @param User $profesional
     * @return Centro|null Null si no se puede asignar (queda en la bandeja).
     */
    private function centroDeLaPersona(Ciudadano $ciudadano, Centro $centroApertura, User $profesional): ?Centro
    {
        $tipo = $centroApertura->tipo_centro;

        if ($tipo === null) {
            return $centroApertura;
        }

        if ($vigente = $this->asignacionCentro->vigente($ciudadano, $tipo)) {
            return $vigente->centro;
        }

        // Acudir a un centro de libre elección y abrir allí la historia es elegirlo
        if ($centroApertura->inscripcion_libre) {
            return $this->asignacionCentro->asignarPorEleccion($ciudadano, $centroApertura, $profesional)->centro;
        }

        if (! in_array($tipo, $this->resolucion->tiposPorDomicilio(), true)) {
            return $centroApertura;
        }

        return $this->asignacionCentro->asignarPorDireccion($ciudadano, $tipo, $profesional)?->centro;
    }
}
