<?php

namespace Modules\Intervencion\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Database\Seeders\TipoPlanSeeder;
use Modules\Intervencion\Models\ObjetivoCatalogo;
use Modules\Intervencion\Models\PlanActuacionAyuntamiento;
use Modules\Intervencion\Models\PlanActuacionCiudadano;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Models\PlanParticipante;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Intervencion\Services\PlanPdfService;
use Modules\Prestaciones\Models\Prestacion;
use Tests\TestCase;

/**
 * Tests funcionales del contenido del Plan de Intervención.
 * Nomenclatura: TF-PLAN-01 a TF-PLAN-17
 */
class PlanContenidoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un plan de test con un TipoPlan factory.
     */
    private function crearPlan(): PlanDeIntervencion
    {
        $tipoPlan = TipoPlan::factory()->create();

        return PlanDeIntervencion::factory()->create([
            'tipo_plan_id' => $tipoPlan->id,
        ]);
    }

    // --- TipoPlan ---

    /** TF-PLAN-01: El seeder carga exactamente los 5 tipos iniciales del sistema. */
    public function test_seeder_tipos_plan(): void
    {
        $this->seed(TipoPlanSeeder::class);

        $this->assertEquals(5, TipoPlan::count());
    }

    /** TF-PLAN-02: El seeder es idempotente: ejecutarlo dos veces no duplica tipos. */
    public function test_seeder_tipos_plan_idempotente(): void
    {
        $this->seed(TipoPlanSeeder::class);
        $this->seed(TipoPlanSeeder::class);

        $this->assertEquals(5, TipoPlan::count());
    }

    /** TF-PLAN-03: Intentar eliminar un tipo del sistema lanza LogicException. */
    public function test_no_elimina_tipo_plan_sistema(): void
    {
        $this->seed(TipoPlanSeeder::class);
        $tipo = TipoPlan::where('eliminable', false)->first();

        $this->expectException(\LogicException::class);
        $tipo->delete();
    }

    // --- ObjetivoCatalogo ---

    /** TF-PLAN-04: Un objetivo específico se vincula correctamente a su objetivo general. */
    public function test_objetivo_especifico_vinculado_a_general(): void
    {
        $tipo = TipoPlan::factory()->create();

        $general = ObjetivoCatalogo::create([
            'tipo_plan_id' => $tipo->id,
            'nivel' => 'general',
            'texto' => 'Favorecer la inclusión social',
            'orden' => 1,
        ]);

        $especifico = ObjetivoCatalogo::create([
            'tipo_plan_id' => $tipo->id,
            'nivel' => 'especifico',
            'objetivo_general_id' => $general->id,
            'texto' => 'Reducir el aislamiento social',
            'orden' => 1,
        ]);

        $this->assertEquals($general->id, $especifico->objetivoGeneral->id);
        $this->assertEquals($especifico->id, $general->objetivosEspecificos->first()->id);
    }

    // --- PlanObjetivo ---

    /** TF-PLAN-05: Se puede añadir un objetivo a un plan y aparece en su relación objetivos(). */
    public function test_anadir_objetivo_al_plan(): void
    {
        $plan = $this->crearPlan();

        PlanObjetivo::create([
            'plan_id' => $plan->id,
            'nivel' => 'general',
            'texto' => 'Mejorar las condiciones económicas del hogar',
            'estado' => 'pendiente',
            'orden' => 1,
        ]);

        $this->assertDatabaseHas('plan_objetivos', ['plan_id' => $plan->id]);
        $this->assertEquals(1, $plan->objetivos()->count());
    }

    /** TF-PLAN-06: Los objetivos específicos se anidan bajo su objetivo general en el plan. */
    public function test_objetivos_anidados(): void
    {
        $plan = $this->crearPlan();

        $general = PlanObjetivo::create([
            'plan_id' => $plan->id,
            'nivel' => 'general',
            'texto' => 'Objetivo general',
            'estado' => 'pendiente',
            'orden' => 1,
        ]);

        PlanObjetivo::create([
            'plan_id' => $plan->id,
            'nivel' => 'especifico',
            'objetivo_general_id' => $general->id,
            'texto' => 'Objetivo específico',
            'estado' => 'pendiente',
            'orden' => 1,
        ]);

        $this->assertCount(1, $general->objetivosEspecificos);
    }

    // --- PlanActuacionAyuntamiento ---

    /** TF-PLAN-07: Crear una actuación del Ayuntamiento sin prestación_id lanza LogicException. */
    public function test_actuacion_ayuntamiento_requiere_prestacion(): void
    {
        $plan = $this->crearPlan();

        $this->expectException(\LogicException::class);

        PlanActuacionAyuntamiento::create([
            'plan_id' => $plan->id,
            'prestacion_id' => null,
            'estado' => 'pendiente',
            'orden' => 1,
        ]);
    }

    /** TF-PLAN-08: Una actuación del Ayuntamiento con prestación se guarda y relaciona correctamente. */
    public function test_actuacion_ayuntamiento_con_prestacion(): void
    {
        $plan = $this->crearPlan();
        $prestacion = Prestacion::factory()->create();

        $actuacion = PlanActuacionAyuntamiento::create([
            'plan_id' => $plan->id,
            'prestacion_id' => $prestacion->id,
            'descripcion_especifica' => 'Asistirá a 4 sesiones del taller de empleo',
            'estado' => 'pendiente',
            'orden' => 1,
        ]);

        $this->assertEquals($prestacion->id, $actuacion->prestacion->id);
        $this->assertEquals('Asistirá a 4 sesiones del taller de empleo', $actuacion->descripcion_especifica);
    }

    // --- PlanActuacionCiudadano ---

    /** TF-PLAN-09: Una actuación del ciudadano se puede guardar sin prestación (texto libre). */
    public function test_actuacion_ciudadano_sin_prestacion(): void
    {
        $plan = $this->crearPlan();

        $actuacion = PlanActuacionCiudadano::create([
            'plan_id' => $plan->id,
            'descripcion' => 'Mantendrá a sus hijos escolarizados',
            'estado' => 'pendiente',
            'orden' => 1,
        ]);

        $this->assertNull($actuacion->prestacion_id);
        $this->assertDatabaseHas('plan_actuaciones_ciudadano', ['plan_id' => $plan->id]);
    }

    /** TF-PLAN-10: Una actuación del ciudadano puede vincularse opcionalmente a una prestación. */
    public function test_actuacion_ciudadano_con_prestacion(): void
    {
        $plan = $this->crearPlan();
        $prestacion = Prestacion::factory()->create();

        $actuacion = PlanActuacionCiudadano::create([
            'plan_id' => $plan->id,
            'descripcion' => 'Asistirá al taller de resolución de conflictos',
            'prestacion_id' => $prestacion->id,
            'estado' => 'pendiente',
            'orden' => 1,
        ]);

        $this->assertEquals($prestacion->id, $actuacion->prestacion_id);
    }

    // --- PlanParticipante ---

    /** TF-PLAN-11: Se puede añadir un participante activo al plan. */
    public function test_anadir_participante(): void
    {
        $plan = $this->crearPlan();
        $profesional = User::factory()->create();

        $participante = PlanParticipante::create([
            'plan_id' => $plan->id,
            'user_id' => $profesional->id,
            'rol_en_plan' => 'Educador/a social',
            'fecha_inicio' => now()->toDateString(),
        ]);

        $this->assertTrue($participante->estaActivo());
        $this->assertCount(1, $plan->participantesActivos);
    }

    /** TF-PLAN-12: Un participante con fecha_fin pasada no aparece en participantesActivos(). */
    public function test_participante_inactivo_excluido(): void
    {
        $plan = $this->crearPlan();
        $profesional = User::factory()->create();

        PlanParticipante::create([
            'plan_id' => $plan->id,
            'user_id' => $profesional->id,
            'rol_en_plan' => 'Psicólogo/a',
            'fecha_inicio' => '2024-01-01',
            'fecha_fin' => '2024-06-01',
        ]);

        $this->assertCount(0, $plan->participantesActivos);
    }

    // --- Historial de cambios ---

    /** TF-PLAN-13: registrarCambio() crea un snapshot con el estado actual del plan. */
    public function test_registrar_cambio_crea_snapshot(): void
    {
        $plan = $this->crearPlan();
        $profesional = User::factory()->create();
        $plan->update(['diagnostico_social' => 'Situación de vulnerabilidad económica']);

        $cambio = $plan->registrarCambio($profesional->id, 'Actualización de diagnóstico');

        $this->assertNotNull($cambio->snapshot);
        $this->assertEquals(
            'Situación de vulnerabilidad económica',
            $cambio->snapshot['diagnostico_social']
        );
    }

    /** TF-PLAN-14: El origen del cambio se distingue entre discrecional y de seguimiento. */
    public function test_origen_cambio(): void
    {
        $plan = $this->crearPlan();
        $profesional = User::factory()->create();

        $cambio = $plan->registrarCambio($profesional->id, 'Ajuste menor', 'discrecional');

        $this->assertEquals('discrecional', $cambio->origen);
    }

    /** TF-PLAN-15: Los cambios del plan se devuelven ordenados de más reciente a más antiguo. */
    public function test_cambios_orden_desc(): void
    {
        $plan = $this->crearPlan();
        $profesional = User::factory()->create();

        $plan->registrarCambio($profesional->id, 'Primer cambio');
        sleep(1);
        $plan->registrarCambio($profesional->id, 'Segundo cambio');

        $cambios = $plan->cambios()->get();
        $this->assertEquals('Segundo cambio', $cambios->first()->motivo);
        $this->assertEquals('Primer cambio', $cambios->last()->motivo);
    }

    // --- PDF ---

    /** TF-PLAN-16: PlanPdfService es instanciable desde el contenedor de Laravel. */
    public function test_plan_pdf_service_instanciable(): void
    {
        $service = app(PlanPdfService::class);

        $this->assertInstanceOf(PlanPdfService::class, $service);
    }

    /** TF-PLAN-17: nombre() devuelve un string con extensión .pdf y número de versión. */
    public function test_pdf_nombre_correcto(): void
    {
        $plan = $this->crearPlan();
        $service = app(PlanPdfService::class);

        $nombre = $service->nombre($plan);

        $this->assertStringEndsWith('.pdf', $nombre);
        $this->assertStringContainsString("v{$plan->version}", $nombre);
    }
}
