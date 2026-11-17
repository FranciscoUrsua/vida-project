<?php

namespace Modules\Centro\Tests\Feature\Actividades;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Enums\EstadoInscripcionSesion;
use Modules\Centro\Enums\ViaInscripcion;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Services\Actividades\InscripcionActividadService;
use Modules\Centro\Services\PrescripcionService;
use Modules\Centro\Tests\Concerns\ActividadesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo C — Prescripción a una sesión (TF-ACT-13 a 16; fase 6, F6-2, F6-3 y F6-7).
 *
 * La prescripción nace activa con su inscripción por vía de prescripción, o no nace si
 * no queda cupo. Puede hacerse desde otro centro; la inscripción libre, no (RN-15).
 */
class InscripcionPrescripcionTest extends TestCase
{
    use ActividadesTestSetup;
    use RefreshDatabase;

    /**
     * Escenario de actividades.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarActividades();
    }

    /**
     * TF-ACT-13 — Prescripción desde el PISO: activa, con inscripción enlazada que consume
     * plaza de prescripción.
     *
     * @return void
     */
    #[Test]
    public function tf_act_13_prescribir_desde_el_plan(): void
    {
        $prescripcion = app(PrescripcionService::class)->crear([
            'profesional_id' => $this->tsNorte->profesional_id,
            'ciudadano_id' => $this->ana->id,
            'plan_intervencion_id' => $this->pisoAna->id,
            'tipo_destino' => 'sesion_actividad',
            'destino_id' => $this->sesion9->id,
            'fecha_prescripcion' => today()->toDateString(),
        ], $this->tsNorte, inscribirEnCentro: true);

        $this->assertSame('activa', $prescripcion->estado);

        $inscripcion = InscripcionSesion::where('prescripcion_id', $prescripcion->id)->sole();
        $this->assertSame(ViaInscripcion::Prescripcion, $inscripcion->via);
        $this->assertSame($this->ana->id, $inscripcion->ciudadano_id);
        $this->assertSame($this->tsNorte->id, $inscripcion->inscrita_por_id);
        $this->assertSame(3, $this->sesion9->plazasLibres(ViaInscripcion::Prescripcion));
        $this->assertSame(6, $this->sesion9->plazasLibres(ViaInscripcion::Libre));
    }

    /**
     * TF-ACT-14 — Con el cupo de prescripción lleno, la quinta se rechaza aunque queden
     * plazas libres, y no queda prescripción a medias. [negativo]
     *
     * @return void
     */
    #[Test]
    public function tf_act_14_aforo_de_prescripcion_completo(): void
    {
        $this->llenarPrescripcion($this->sesion9, 4);
        $quinta = $this->persona($this->tallerCiam);

        try {
            $this->prescribir($this->sesion9, $quinta);
            $this->fail('Se ha superado el cupo de prescripción.');
        } catch (DomainException $e) {
            $this->assertSame('No quedan plazas de prescripción en esta sesión.', $e->getMessage());
        }

        $this->assertSame(0, Prescripcion::where('ciudadano_id', $quinta->id)->count());
        $this->assertSame(6, $this->sesion9->plazasLibres(ViaInscripcion::Libre));
    }

    /**
     * TF-ACT-15 — Dar de baja la inscripción cancela la prescripción con el mismo motivo; y
     * cancelar la prescripción da de baja la inscripción.
     *
     * @return void
     */
    #[Test]
    public function tf_act_15_baja_de_una_inscripcion_por_prescripcion(): void
    {
        $prescripcion = $this->prescribir($this->sesion9, $this->ana);
        $inscripcion = InscripcionSesion::where('prescripcion_id', $prescripcion->id)->sole();

        app(InscripcionActividadService::class)->darDeBaja($inscripcion, 'Ha encontrado empleo', $this->tsNorte);

        $prescripcion->refresh();
        $this->assertSame('cancelada', $prescripcion->estado);
        $this->assertSame('Ha encontrado empleo', $prescripcion->motivo_cancelacion);

        $otra = $this->prescribir($this->sesion16, $this->ana);
        app(PrescripcionService::class)->cancelar($otra, 'Revisión del plan');

        $this->assertSame(EstadoInscripcionSesion::Baja, InscripcionSesion::where('prescripcion_id', $otra->id)->sole()->estado);
        $this->assertSame(4, $this->sesion16->plazasLibres(ViaInscripcion::Prescripcion));
    }

    /**
     * TF-ACT-16 — El TS de otro centro prescribe la sesión del CIAM, pero no puede inscribir
     * por acceso libre en ella (RN-15).
     *
     * @return void
     */
    #[Test]
    public function tf_act_16_prescripcion_desde_otro_centro(): void
    {
        $this->prescribir($this->sesion9, $this->ana, $this->tsNorte);
        $this->assertSame(1, $this->sesion9->ocupadas(ViaInscripcion::Prescripcion));

        $this->expectException(AuthorizationException::class);
        app(InscripcionActividadService::class)->inscribir($this->sesion16, $this->ana, $this->tsNorte);
    }

    /**
     * Una actividad de acceso libre no admite prescripción.
     *
     * @return void
     */
    #[Test]
    public function actividad_libre_no_admite_prescripcion(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no admite prescripción');

        $this->prescribir($this->sesionCharla, $this->ana);
    }
}
