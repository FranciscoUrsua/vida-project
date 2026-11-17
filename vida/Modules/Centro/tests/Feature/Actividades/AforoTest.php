<?php

namespace Modules\Centro\Tests\Feature\Actividades;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Enums\EstadoInscripcionSesion;
use Modules\Centro\Enums\ViaInscripcion;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Services\Actividades\InscripcionActividadService;
use Modules\Centro\Tests\Concerns\ActividadesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo A — Aforo de las sesiones de actividad (TF-ACT-01 a 04; fase 6, F6-3 y F6-8).
 *
 * Ninguna inscripción supera el aforo, tampoco en concurrencia: el servicio serializa
 * las inscripciones de una sesión con un bloqueo consultivo de PostgreSQL.
 */
class AforoTest extends TestCase
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
     * TF-ACT-01 — La sesión con aforo propio usa el suyo; las demás, el de la actividad.
     * El cupo de prescripción se reserva y el resto es de acceso libre.
     *
     * @return void
     */
    #[Test]
    public function tf_act_01_aforo_efectivo(): void
    {
        $this->assertSame(6, $this->sesion16->aforoEfectivo());
        $this->assertSame(10, $this->sesion9->aforoEfectivo());
        $this->assertSame(10, $this->sesion23->aforoEfectivo());

        $this->assertSame(6, $this->sesion9->plazasLibres(ViaInscripcion::Libre));
        $this->assertSame(4, $this->sesion9->plazasLibres(ViaInscripcion::Prescripcion));

        // En la del 16 (aforo 6) quedan 2 libres y las 4 de prescripción
        $this->assertSame(2, $this->sesion16->plazasLibres(ViaInscripcion::Libre));
        $this->assertSame(4, $this->sesion16->plazasLibres(ViaInscripcion::Prescripcion));

        // Una actividad libre no admite prescripción
        $this->assertSame(0, $this->sesionCharla->plazasLibres(ViaInscripcion::Prescripcion));
    }

    /**
     * F6-8 — En una mixta sin cupo de prescripción, las dos vías comparten el aforo total.
     *
     * @return void
     */
    #[Test]
    public function tf_act_01_bis_mixta_sin_cupo_es_bolsa_comun(): void
    {
        $this->tallerCiam->update(['aforo_prescripcion' => null]);
        $sesion = $this->sesion9->fresh();

        $this->assertSame(10, $sesion->plazasLibres(ViaInscripcion::Libre));
        $this->assertSame(10, $sesion->plazasLibres(ViaInscripcion::Prescripcion));

        $this->llenarLibre($sesion, 7);
        $this->prescribir($sesion, $this->persona($this->tallerCiam));

        $this->assertSame(2, $sesion->plazasLibres(ViaInscripcion::Libre));
        $this->assertSame(2, $sesion->plazasLibres(ViaInscripcion::Prescripcion));
    }

    /**
     * TF-ACT-02 — Sin aforo no hay límite.
     *
     * @return void
     */
    #[Test]
    public function tf_act_02_sin_limite(): void
    {
        $this->llenarLibre($this->sesionCharla, 50);

        $this->assertSame(50, $this->sesionCharla->ocupadas());
        $this->assertNull($this->sesionCharla->plazasLibres(ViaInscripcion::Libre));
    }

    /**
     * TF-ACT-03 — Con las plazas libres ocupadas, una más se rechaza; por prescripción
     * aún hay plaza. [negativo]
     *
     * @return void
     */
    #[Test]
    public function tf_act_03_aforo_completo(): void
    {
        $this->llenarLibre($this->sesion9, 6);

        try {
            $this->llenarLibre($this->sesion9, 1);
            $this->fail('Se ha superado el aforo de acceso libre.');
        } catch (DomainException $e) {
            $this->assertSame('No quedan plazas de acceso libre en esta sesión.', $e->getMessage());
        }

        $this->assertSame(6, $this->sesion9->ocupadas(ViaInscripcion::Libre));

        $this->prescribir($this->sesion9, $this->persona($this->tallerCiam));
        $this->assertSame(1, $this->sesion9->ocupadas(ViaInscripcion::Prescripcion));
    }

    /**
     * TF-ACT-04 — Mientras otra transacción inscribe en la sesión (tiene su bloqueo), una
     * segunda inscripción espera y no cuenta plazas obsoletas. [negativo: sin el bloqueo,
     * la segunda inscripción no espera]
     *
     * Con RefreshDatabase los datos del test no son visibles desde otra conexión, por eso
     * el bloqueo es consultivo y se comprueba desde una segunda conexión real.
     *
     * @return void
     */
    #[Test]
    public function tf_act_04_concurrencia(): void
    {
        // Las cinco primeras, escritas directamente: por el servicio, la transacción del
        // test ya tendría el bloqueo de la sesión (es de transacción, no de savepoint).
        for ($i = 0; $i < 5; $i++) {
            InscripcionSesion::create([
                'sesion_actividad_id' => $this->sesion9->id,
                'ciudadano_id' => $this->persona($this->tallerCiam)->id,
                'via' => ViaInscripcion::Libre,
                'estado' => EstadoInscripcionSesion::Inscrita,
                'inscrita_por_id' => $this->psicologaVallecas->id,
            ]);
        }
        $ultima = $this->persona($this->tallerCiam);

        $defecto = config('database.default');
        config(['database.connections.concurrente' => config("database.connections.{$defecto}")]);
        $otra = DB::connection('concurrente');
        $otra->beginTransaction();
        $otra->select('SELECT pg_advisory_xact_lock(?, ?)', [InscripcionActividadService::CLAVE_BLOQUEO, $this->sesion9->id]);

        DB::statement("SET lock_timeout = '300ms'");

        try {
            app(InscripcionActividadService::class)->inscribir($this->sesion9, $ultima, $this->psicologaVallecas);
            $this->fail('La inscripción no ha esperado al bloqueo de la otra transacción.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('lock timeout', $e->getMessage());
        } finally {
            DB::statement('SET lock_timeout = 0');
            $otra->rollBack();
            DB::purge('concurrente');
        }

        // Liberado el bloqueo, la última plaza se ocupa una sola vez
        app(InscripcionActividadService::class)->inscribir($this->sesion9, $ultima, $this->psicologaVallecas);
        $this->expectException(DomainException::class);
        $this->llenarLibre($this->sesion9, 1);
    }
}
