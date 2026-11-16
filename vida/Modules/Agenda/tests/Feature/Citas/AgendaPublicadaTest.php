<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use App\Filament\Resources\CuadranteMesResource\Pages\ListCuadrantesMes;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Livewire\Citas\CitaDirectaPage;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoCita;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Agenda publicada y huecos para citar (incidencia del 2026-11-16: en staging la
 * búsqueda nunca proponía huecos porque no había slots).
 *
 * - Publicar un cuadrante desde Filament materializa sus slots (antes solo cambiaba el estado).
 * - El tipo genérico «cita» con tipos de slot enlazados se limita a ellos.
 * - Sin agenda publicada, la cita directa lo dice en lugar de «no hay huecos».
 */
class AgendaPublicadaTest extends TestCase
{
    use CitasTestSetup;
    use RefreshDatabase;

    /**
     * Prepara el escenario de citas.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioCitas();
    }

    #[Test]
    public function publicar_desde_filament_materializa_los_slots(): void
    {
        $cuadrante = CuadranteMes::create([
            'centro_id' => $this->centro->id, 'anyo' => 2026, 'mes' => 12,
            'estado' => EstadoCuadrante::Borrador->value, 'generado_con_ia' => false, 'generado_automaticamente' => false,
        ]);
        LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id, 'usuario_id' => $this->tsr->id, 'centro_id' => $this->centro->id,
            'fecha' => '2026-12-02', 'franjas' => [['inicio' => '09:00', 'fin' => '14:00']], 'anulada' => false,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListCuadrantesMes::class)
            ->callAction(TestAction::make('publicar')->table($cuadrante));

        $this->assertSame(EstadoCuadrante::Publicado, $cuadrante->fresh()->estado);
        $this->assertGreaterThan(0, Slot::where('linea_cuadrante_id', LineaCuadrante::where('cuadrante_mes_id', $cuadrante->id)->value('id'))->count());
    }

    #[Test]
    public function el_tipo_generico_con_enlaces_solo_admite_esos_tipos_de_slot(): void
    {
        $generico = TipoCita::generico();

        // Sin enlaces: cualquiera (modo básico)
        $this->assertContains($this->slotGrupal->id, $generico->idsTiposSlotCompatibles());
        $this->assertTrue($generico->admiteTipoSlot($this->slotGrupal->id));

        $generico->tiposSlot()->attach($this->slotEntrevista);
        $generico->refresh();

        $this->assertSame([$this->slotEntrevista->id], $generico->idsTiposSlotCompatibles());
        $this->assertFalse($generico->admiteTipoSlot($this->slotGrupal->id));
        $this->assertTrue($generico->admiteTipoSlot($this->slotEntrevista->id));
    }

    #[Test]
    public function sin_agenda_publicada_la_cita_directa_lo_dice(): void
    {
        // El escenario trae slots: se quita la agenda para reproducir el centro sin cuadrante publicado
        Slot::query()->delete();

        $pagina = Livewire::withQueryParams(['ciudadano' => $this->juan->id])
            ->actingAs($this->consulta)
            ->test(CitaDirectaPage::class)
            ->set('formSolicitud.tipo_cita_id', (string) $this->tipoInformacion->id)
            ->set('formSolicitud.destino', 'profesional_concreto')
            ->set('formSolicitud.profesional_destino_id', (string) $this->tsr->id)
            ->call('buscarHuecos')
            ->assertSet('propuestas', [])
            ->assertSet('sinAgenda', true)
            ->assertSee('El centro no tiene agenda publicada para estas fechas');

        // Con agenda pero sin huecos libres para ese destino: el mensaje de siempre
        $this->materializarSlots($this->tsr2);
        $pagina->call('buscarHuecos')
            ->assertSet('propuestas', [])
            ->assertSet('sinAgenda', false)
            ->assertSee('No hay huecos compatibles');
    }
}
