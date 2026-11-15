<?php

namespace Modules\Prestaciones\Tests\Feature\Solicitudes;

use App\Filament\Resources\PrestacionResource\Pages\CreatePrestacion;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Centro\Models\Servicio;
use Modules\Intervencion\Http\Livewire\RegistrarValoracionPage;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Prestaciones\Enums\ViaAcceso;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Tests\Concerns\SolicitudesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo A — Catálogo (TF-SOL-01 a 03): vía de acceso coherente, formularios de solicitud
 * separados de las fichas de valoración y copia de la configuración en cada solicitud.
 */
class CatalogoAccesoTest extends TestCase
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

    /** TF-SOL-01 — Vía obligatoria y coherente. [negativo] */
    #[Test]
    public function tf_sol_01_via_obligatoria_y_coherente(): void
    {
        Livewire::actingAs($this->admin)->test(CreatePrestacion::class)
            ->fillForm(['codigo' => 'X1', 'nombre' => 'Sin vía', 'tipo_prestacion' => 'servicio', 'nivel_garantia' => 'garantizada', 'activa' => true])
            ->call('create')
            ->assertHasFormErrors(['via_acceso' => 'required']);
        $this->assertFalse(Prestacion::where('codigo', 'X1')->exists());

        $base = ['tipo_prestacion' => 'servicio', 'nivel_garantia' => 'garantizada', 'activa' => true];
        foreach ([
            ['codigo' => 'X2', 'nombre' => 'Externa sin unidad', 'via_acceso' => ViaAcceso::ExpedienteExterno],
            ['codigo' => 'X3', 'nombre' => 'Directa con código', 'via_acceso' => ViaAcceso::Directa, 'codigo_gestor' => 'PROC-1'],
            ['codigo' => 'X4', 'nombre' => 'Directa con unidad', 'via_acceso' => ViaAcceso::Directa, 'unidad_tramitadora_tipo' => 'centro_solicitud'],
            ['codigo' => 'X5', 'nombre' => 'Interna con UO vacía', 'via_acceso' => ViaAcceso::TramitacionInterna, 'unidad_tramitadora_tipo' => 'uo'],
        ] as $datos) {
            try {
                Prestacion::create($datos + $base);
                $this->fail("Debía rechazarse: {$datos['nombre']}");
            } catch (DomainException) {
                $this->assertFalse(Prestacion::where('codigo', $datos['codigo'])->exists());
            }
        }
    }

    /** TF-SOL-02 — Los formularios de solicitud no se ofrecen como fichas de valoración, ni al revés. */
    #[Test]
    public function tf_sol_02_formularios_de_solicitud_separados(): void
    {
        $valoracion = TipoFicha::create(['nombre' => 'Ficha de vivienda', 'activo' => true, 'schema' => ['campos' => []]]);

        $this->actingAs($this->tsNorte);
        $fichas = Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historiaAna])->get('fichasDisponibles');
        $this->assertArrayHasKey($valoracion->id, $fichas);
        $this->assertArrayNotHasKey($this->formularioSad->id, $fichas);

        $this->assertTrue(TipoFicha::deSolicitud()->whereKey($this->formularioSad->id)->exists());
        $this->assertFalse(TipoFicha::deSolicitud()->whereKey($valoracion->id)->exists());
    }

    /** TF-SOL-03 — La solicitud conserva la configuración de su creación. */
    #[Test]
    public function tf_sol_03_snapshot_de_configuracion(): void
    {
        $solicitud = $this->solicitudSad();

        $this->pSad->documentosExigidos()->delete();
        $this->pSad->update(['codigo_gestor' => 'PROC-NUEVO']);

        $config = $solicitud->fresh()->configuracion_snapshot;
        $this->assertSame(['Documento de identidad', 'Informe médico'], array_column($config['documentos'], 'nombre'));
        $this->assertSame('PROC-SAD', $config['codigo_gestor']);
        $this->assertSame($this->uoTramitacionSad->id, $config['unidad_tramitadora_id']);
    }

    /** TF-SOL-44 — Sin `SolicitudServicio`; `Servicio` sigue y su UO puede tramitar una prestación. */
    #[Test]
    public function tf_sol_44_sin_solicitud_servicio(): void
    {
        $this->assertFalse(Schema::hasTable('solicitudes_servicio'));
        $this->assertFalse(class_exists('Modules\\Centro\\Models\\SolicitudServicio'));

        $uoServicio = $this->uo('Servicio de teleasistencia', 'departamento', $this->uoArea);
        $servicio = Servicio::create(['nombre' => 'Teleasistencia', 'nombre_corto' => 'Teleasistencia', 'cargo_nombre' => 'Jefatura de servicio', 'unidad_organizativa_id' => $uoServicio->id, 'activo' => true, 'fecha_alta' => '2026-01-01']);
        $prestacion = $this->prestacionDe('TEL', 'Teleasistencia', ViaAcceso::TramitacionInterna, ['unidad_tramitadora_tipo' => 'uo', 'unidad_tramitadora_id' => $servicio->unidad_organizativa_id]);

        $solicitud = $this->servicio()->crear($this->ana, $prestacion, $this->tsNorte, null, null, $this->cssNorte);
        $this->servicio()->pasarABandeja($solicitud, $this->tsNorte);
        $this->assertSame($uoServicio->id, $solicitud->fresh()->unidad_tramitadora_id);
    }
}
