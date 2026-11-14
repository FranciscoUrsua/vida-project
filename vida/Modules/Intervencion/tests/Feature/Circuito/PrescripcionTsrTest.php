<?php

namespace Modules\Intervencion\Tests\Feature\Circuito;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\Espacio;
use Modules\Centro\Models\ListaEspera;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\TipoEspacio;
use Modules\Centro\Services\PrescripcionService;
use Modules\Intervencion\Http\Livewire\AsignarPlazaModal;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use Modules\Mensajes\Models\Alerta;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo E — Plazas y profesional de referencia (TF-CIR-34 y 35; fase 3, F3-8).
 *
 * Ninguna alerta de plazas queda solo en el log: va al TSR vigente o, sin él, a la
 * supervisión del centro de la historia.
 */
class PrescripcionTsrTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    private ColeccionPlazas $coleccion;

    /**
     * Escenario del circuito con una colección de plazas en el CSS Norte.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();

        $this->coleccion = ColeccionPlazas::create([
            'centro_id' => $this->cssNorte->id, 'nombre' => 'Plazas de acogida', 'tipo_plaza' => 'pernocta',
            'modo_acceso' => 'prescripcion_lista_espera', 'capacidad' => 1, 'activa' => true, 'fecha_alta' => '2026-01-01',
        ]);
        $espacio = Espacio::create([
            'coleccion_plazas_id' => $this->coleccion->id, 'tipo_espacio_id' => TipoEspacio::create(['nombre' => 'Habitación', 'activo' => true])->id,
            'nombre' => 'Habitación 1', 'capacidad' => 1, 'accesible' => false,
        ]);
        Plaza::create(['espacio_id' => $espacio->id, 'nombre' => 'Cama 1', 'estado' => 'ocupada', 'activa' => true]);
    }

    /** TF-CIR-34 — Alerta real al asignar plaza: al TSR; sin referencia, a la supervisión. Nunca solo log. [negativo] */
    #[Test]
    public function tf_cir_34_alerta_real_al_asignar_plaza(): void
    {
        Log::spy();
        $prescripcion = $this->prescripcionEnEspera($this->ana->id);

        $this->asignarPlazaDesdeElModal($prescripcion);
        $this->assertTrue(Alerta::pendientesPara($this->tsNorte)->where('origen_type', Prescripcion::class)->where('origen_id', $prescripcion->id)->exists());

        // Sin referencia vigente: a la supervisión del centro de la historia.
        AsignacionProfesional::where('historia_id', $this->historiaAna->id)->update(['fecha_fin' => '2026-11-01']);
        Plaza::query()->update(['estado' => 'libre']);
        $otra = $this->prescripcionEnEspera($this->ana->id);
        $this->asignarPlazaDesdeElModal($otra);
        $this->assertTrue(Alerta::pendientesPara($this->supNorte)->where('origen_type', Prescripcion::class)->where('origen_id', $otra->id)->exists());

        Log::shouldNotHaveReceived('info');
    }

    /** TF-CIR-35 — La lista de espera usa el TSR vigente al liberarse una plaza. */
    #[Test]
    public function tf_cir_35_lista_de_espera_usa_el_tsr_vigente(): void
    {
        $prescripcion = $this->prescripcionEnEspera($this->ana->id);
        $entrada = ListaEspera::where('prescripcion_id', $prescripcion->id)->sole();

        PerfilHorarioProfesional::factory()->create(['usuario_id' => $this->tsNorte2->id, 'centro_id' => $this->cssNorte->id, 'vigente_desde' => '2026-01-01']);
        $this->tsNorte2->profesional->cargo->update(['puede_ser_referencia' => true]);
        app(AsignacionReferenciaService::class)->cambiarManual($this->historiaAna, $this->tsNorte2, 'Corrección', $this->supNorte, $this->cssNorte);

        app(PrescripcionService::class)->liberarPlaza(Plaza::firstOrFail());

        $this->assertSame($this->tsNorte2->profesional_id, $entrada->fresh()->profesional_alerta_id);
        $this->assertTrue(Alerta::pendientesPara($this->tsNorte2)->where('origen_type', ListaEspera::class)->where('origen_id', $entrada->id)->exists());
    }

    /**
     * Prescripción de la persona a la colección, en lista de espera (no hay plaza libre).
     *
     * @param int $ciudadanoId
     *
     * @return Prescripcion
     */
    private function prescripcionEnEspera(int $ciudadanoId): Prescripcion
    {
        return app(PrescripcionService::class)->crear([
            'profesional_id' => $this->tsNorte->profesional_id,
            'ciudadano_id' => $ciudadanoId,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $this->coleccion->id,
            'fecha_prescripcion' => '2026-11-02',
        ]);
    }

    /**
     * Libera la plaza y la asigna desde el modal como profesional del CSS Norte.
     *
     * @param Prescripcion $prescripcion
     *
     * @return void
     */
    private function asignarPlazaDesdeElModal(Prescripcion $prescripcion): void
    {
        $plaza = Plaza::firstOrFail();
        $plaza->update(['estado' => 'libre']);
        $this->tsNorte->profesional->update(['unidad_organizativa_id' => $this->cssNorte->unidad_organizativa_id]);

        Livewire::actingAs(User::find($this->tsNorte->id))
            ->test(AsignarPlazaModal::class)
            ->call('abrir', $prescripcion->id)
            ->call('asignar', $plaza->id)
            ->assertHasNoErrors();

        $this->assertSame('asignada', $prescripcion->fresh()->estado);
    }
}
