<?php

namespace Modules\Prestaciones\Tests\Feature\Solicitudes;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\Espacio;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\TipoEspacio;
use Modules\Centro\Services\PrescripcionService;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Prestaciones\Data\ActualizacionExpediente;
use Modules\Prestaciones\Data\LineaResolucionDto;
use Modules\Prestaciones\Data\ResolucionDto;
use Modules\Prestaciones\Enums\EstadoPrestacionPersona;
use Modules\Prestaciones\Enums\SentidoResolucion;
use Modules\Prestaciones\Http\Livewire\PrestacionesPersona;
use Modules\Prestaciones\Models\PrestacionPersona;
use Modules\Prestaciones\Services\PrestacionPersonaService;
use Modules\Prestaciones\Tests\Concerns\SolicitudesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo E — Prestaciones de la persona (TF-SOL-32 a 35 y 37; RN-08): concedida no es activa,
 * transiciones con historial, prestación directa, plaza asignada y bloque de la ficha.
 */
class PrestacionPersonaTest extends TestCase
{
    use RefreshDatabase;
    use SolicitudesTestSetup;

    /**
     * Escenario de solicitudes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarSolicitudes();
    }

    /** TF-SOL-32 — Concedida no es activa hasta registrar el inicio. [negativo] */
    #[Test]
    public function tf_sol_32_concedida_no_es_activa(): void
    {
        $pp = $this->concedida();

        $this->actingAs($this->tsNorte);
        $this->assertSame('1 concedida sin empezar', Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])->get('statPrestaciones'));
        $this->assertSame(0, PrestacionPersona::enEstado(EstadoPrestacionPersona::Activa)->count());

        app(PrestacionPersonaService::class)->registrarInicio($pp, Carbon::parse('2026-11-30'), $this->tsNorte);

        $pp->refresh();
        $this->assertSame([EstadoPrestacionPersona::Activa, '2026-11-30'], [$pp->estado, $pp->fecha_inicio->toDateString()]);
        $this->assertSame(['concedida', 'activa'], $pp->eventos()->get()->map(fn ($e) => $e->estado_despues->value)->all());
        $this->assertSame('1 activa', Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])->get('statPrestaciones'));
    }

    /** TF-SOL-33 — Suspender, reanudar y finalizar con motivo; desde finalizada no se vuelve. */
    #[Test]
    public function tf_sol_33_suspender_reanudar_finalizar(): void
    {
        $servicio = app(PrestacionPersonaService::class);
        $pp = $servicio->registrarDirecta($this->ana, $this->pDirecta, $this->tsNorte, $this->cssNorte);

        $servicio->suspender($pp, 'Ingreso hospitalario', $this->tsNorte);
        $servicio->reanudar($pp->fresh(), $this->tsNorte);
        $servicio->finalizar($pp->fresh(), Carbon::parse('2026-12-31'), 'Fin del periodo', $this->tsNorte);

        foreach ([fn () => $servicio->reanudar($pp->fresh(), $this->tsNorte), fn () => $servicio->registrarInicio($pp->fresh(), Carbon::today(), $this->tsNorte), fn () => $servicio->finalizar($pp->fresh(), Carbon::today(), 'Otra vez', $this->tsNorte)] as $intento) {
            try {
                $intento();
                $this->fail('Desde finalizada no se vuelve');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(['activa', 'suspendida', 'activa', 'finalizada'], $pp->eventos()->get()->map(fn ($e) => $e->estado_despues->value)->all());
        $this->assertSame('Ingreso hospitalario', $pp->eventos()->get()[1]->motivo);
    }

    /** TF-SOL-34 — Prestación directa: activa desde hoy, sin concesión ni origen. */
    #[Test]
    public function tf_sol_34_prestacion_directa(): void
    {
        $pp = app(PrestacionPersonaService::class)->registrarDirecta($this->ana, $this->pDirecta, $this->tsNorte, $this->cssNorte);

        $this->assertSame([EstadoPrestacionPersona::Activa, today()->toDateString(), null, null], [$pp->estado, $pp->fecha_inicio->toDateString(), $pp->fecha_concesion, $pp->origen_type]);
    }

    /** TF-SOL-35 — Plaza asignada: prestación activa con origen la prescripción. */
    #[Test]
    public function tf_sol_35_plaza_asignada(): void
    {
        $coleccion = ColeccionPlazas::create([
            'centro_id' => $this->cssNorte->id, 'nombre' => 'Centro de día', 'tipo_plaza' => 'diurna', 'modo_acceso' => 'prescripcion_directa',
            'capacidad' => 1, 'activa' => true, 'fecha_alta' => '2026-01-01',
        ]);
        $espacio = Espacio::create(['coleccion_plazas_id' => $coleccion->id, 'tipo_espacio_id' => TipoEspacio::create(['nombre' => 'Sala', 'activo' => true])->id, 'nombre' => 'Sala 1', 'capacidad' => 1, 'accesible' => true]);
        Plaza::create(['espacio_id' => $espacio->id, 'nombre' => 'Plaza 1', 'estado' => 'libre', 'activa' => true]);

        $this->actingAs($this->tsNorte);
        $prescripcion = app(PrescripcionService::class)->crear([
            'profesional_id' => $this->tsNorte->profesional_id, 'ciudadano_id' => $this->ana->id, 'prestacion_id' => $this->pPlaza->id,
            'tipo_destino' => 'coleccion_plazas', 'destino_id' => $coleccion->id, 'fecha_prescripcion' => '2026-11-02',
        ]);

        $pp = PrestacionPersona::sole();
        $this->assertSame([EstadoPrestacionPersona::Activa, $this->pPlaza->id, $prescripcion->getMorphClass(), $prescripcion->id],
            [$pp->estado, $pp->prestacion_id, $pp->origen_type, $pp->origen_id]);
    }

    /** TF-SOL-37 — Bloque «Prestaciones» de la ficha y resumen de la cabecera. */
    #[Test]
    public function tf_sol_37_bloque_prestaciones_de_la_ficha(): void
    {
        $servicio = app(PrestacionPersonaService::class);
        $activa = $servicio->registrarDirecta($this->ana, $this->pDirecta, $this->tsNorte, $this->cssNorte);
        $this->concedida();
        $suspendida = $servicio->registrarDirecta($this->ana, $this->pDirecta, $this->tsNorte, $this->cssNorte);
        $servicio->suspender($suspendida, 'Ausencia temporal', $this->tsNorte);
        $enCurso = $this->solicitudSad();

        $this->actingAs($this->tsNorte);
        $bloque = Livewire::test(PrestacionesPersona::class, ['ciudadanoId' => $this->ana->id])
            ->assertSee('Concedida el 25/11/2026')
            ->assertSee('días sin empezar')
            ->assertSee('Ausencia temporal')
            ->assertSee('Solicitudes en curso');
        $this->assertSame(['activa', 'concedida', 'suspendida'], $bloque->get('prestaciones')->map(fn ($p) => $p->estado->value)->all());
        $this->assertSame([$enCurso->id], $bloque->get('solicitudes')->pluck('id')->all());
        $this->assertNotNull($activa);

        // La suspendida no cuenta como activa en el resumen
        $this->assertSame('1 activa · 1 concedida sin empezar', Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])->get('statPrestaciones'));
    }

    /**
     * Prestación concedida por una resolución favorable de SAD.
     *
     * @return PrestacionPersona
     */
    private function concedida(): PrestacionPersona
    {
        $solicitud = $this->sadEnviada();
        $fecha = Carbon::parse('2026-11-25');
        $this->gestor->simularAviso($solicitud, new ActualizacionExpediente('RESUELTO', 'Resuelto', $fecha,
            resolucion: new ResolucionDto($fecha, SentidoResolucion::Favorable, [new LineaResolucionDto($this->pSad->id, true, intensidad: '20 h/mes')])));

        return PrestacionPersona::where('estado', EstadoPrestacionPersona::Concedida->value)->sole();
    }
}
