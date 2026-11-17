<?php

namespace Modules\Centro\Tests\Concerns;

use App\Models\Ciudadano;
use App\Models\User;
use App\Services\AmbitoUsuario;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\InscripcionCentro;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Models\TipoActividad;
use Modules\Centro\Services\Actividades\InscripcionActividadService;
use Modules\Centro\Services\PrescripcionService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Datos de los tests de actividades grupales (fase 6,
 * docs/instrucciones-cli/2026-11-fase6-actividades.md, «Datos»).
 *
 * Sobre el escenario del circuito (fase 3, fecha 2026-11-02): el taller mixto del CIAM
 * de Vallecas (aforo 10, 4 de prescripción, exige inscripción en el centro, sesiones el
 * 9, 16 y 23 de noviembre y una pasada el 26 de octubre) con su prestación, la charla
 * libre del CSS Norte sin aforo y una auxiliar `consulta_basica` del CIAM.
 */
trait ActividadesTestSetup
{
    use CircuitoTestSetup;

    protected Actividad $tallerCiam;

    protected SesionActividad $sesion9;

    /** Sesión del 16 con aforo propio de 6. */
    protected SesionActividad $sesion16;

    protected SesionActividad $sesion23;

    /** Sesión pasada (26 de octubre). */
    protected SesionActividad $sesionPasada;

    protected Prestacion $pTaller;

    protected Actividad $charlaCss;

    protected SesionActividad $sesionCharla;

    protected User $auxVallecas;

    /**
     * Monta el escenario de actividades.
     *
     * @return void
     */
    protected function montarActividades(): void
    {
        $this->montarCircuito();

        $tipo = TipoActividad::create(['nombre' => 'Taller', 'slug' => 'taller', 'activo' => true]);
        $this->pTaller = $this->prestacion('P-TALLER', 'Taller de autonomía', $this->tipoCiam);

        $this->tallerCiam = Actividad::create([
            'centro_id' => $this->ciamVallecas->id,
            'tipo_actividad_id' => $tipo->id,
            'prestacion_id' => $this->pTaller->id,
            'nombre' => 'Taller de autonomía',
            'modo_acceso' => 'mixta',
            'aforo_total' => 10,
            'aforo_prescripcion' => 4,
            'requiere_inscripcion_centro' => true,
            'activa' => true,
            'fecha_alta' => '2026-10-01',
        ]);
        $this->sesion9 = $this->sesion($this->tallerCiam, '2026-11-09');
        $this->sesion16 = $this->sesion($this->tallerCiam, '2026-11-16', ['aforo_total' => 6]);
        $this->sesion23 = $this->sesion($this->tallerCiam, '2026-11-23');
        $this->sesionPasada = $this->sesion($this->tallerCiam, '2026-10-26');

        $this->charlaCss = Actividad::create([
            'centro_id' => $this->cssNorte->id,
            'tipo_actividad_id' => $tipo->id,
            'nombre' => 'Charla de prevención',
            'modo_acceso' => 'libre',
            'aforo_total' => null,
            'requiere_inscripcion_centro' => false,
            'activa' => true,
            'fecha_alta' => '2026-10-01',
        ]);
        $this->sesionCharla = $this->sesion($this->charlaCss, '2026-11-10');

        $this->auxVallecas = $this->usuario('aux.vallecas', 'consulta_basica', $this->uoCiamVallecas);

        app(AmbitoUsuario::class)->olvidar();
    }

    /**
     * Sesión programada de una actividad a las 10:00.
     *
     * @param Actividad $actividad
     * @param string $fecha
     * @param array<string, mixed> $extra
     *
     * @return SesionActividad
     */
    protected function sesion(Actividad $actividad, string $fecha, array $extra = []): SesionActividad
    {
        return SesionActividad::create($extra + [
            'actividad_id' => $actividad->id,
            'fecha' => $fecha,
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'estado' => 'programada',
        ]);
    }

    /**
     * Persona sin historia, inscrita en el centro de la actividad si se indica.
     *
     * @param Actividad|null $inscritaEnCentroDe
     *
     * @return Ciudadano
     */
    protected function persona(?Actividad $inscritaEnCentroDe = null): Ciudadano
    {
        $persona = Ciudadano::factory()->create();

        if ($inscritaEnCentroDe !== null) {
            InscripcionCentro::create([
                'centro_id' => $inscritaEnCentroDe->centro_id,
                'ciudadano_id' => $persona->id,
                'fecha_alta' => '2026-01-01',
                'activa' => true,
            ]);
        }

        return $persona;
    }

    /**
     * Inscribe por acceso libre a `$n` personas nuevas (inscritas en el centro) en una sesión.
     *
     * @param SesionActividad $sesion
     * @param int $n
     * @param User|null $actor Por defecto, la psicóloga del CIAM o la directora del CSS Norte.
     *
     * @return list<InscripcionSesion>
     */
    protected function llenarLibre(SesionActividad $sesion, int $n, ?User $actor = null): array
    {
        $actor ??= $sesion->actividad->centro_id === $this->ciamVallecas->id ? $this->psicologaVallecas : $this->directoraNorte;
        $inscripciones = [];

        for ($i = 0; $i < $n; $i++) {
            $inscripciones[] = app(InscripcionActividadService::class)->inscribir($sesion, $this->persona($sesion->actividad), $actor);
        }

        return $inscripciones;
    }

    /**
     * Prescribe una sesión a una persona por el servicio, como lo hace el modal.
     *
     * @param SesionActividad $sesion
     * @param Ciudadano $persona
     * @param User|null $actor Por defecto, el TS del CSS Norte.
     * @param bool $inscribirEnCentro
     *
     * @return Prescripcion
     */
    protected function prescribir(SesionActividad $sesion, Ciudadano $persona, ?User $actor = null, bool $inscribirEnCentro = true): Prescripcion
    {
        $actor ??= $this->tsNorte;

        return app(PrescripcionService::class)->crear([
            'profesional_id' => $actor->profesional_id,
            'ciudadano_id' => $persona->id,
            'plan_intervencion_id' => null,
            'tipo_destino' => 'sesion_actividad',
            'destino_id' => $sesion->id,
            'fecha_prescripcion' => today()->toDateString(),
        ], $actor, $inscribirEnCentro);
    }

    /**
     * Prescribe `$n` personas nuevas en una sesión.
     *
     * @param SesionActividad $sesion
     * @param int $n
     *
     * @return void
     */
    protected function llenarPrescripcion(SesionActividad $sesion, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $this->prescribir($sesion, $this->persona($sesion->actividad));
        }
    }
}
