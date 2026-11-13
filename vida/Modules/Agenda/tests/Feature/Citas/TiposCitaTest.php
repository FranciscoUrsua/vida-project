<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use App\Filament\Resources\TipoCitaResource\Pages\CreateTipoCita;
use App\Models\Audit;
use App\Models\Version;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Models\TipoCita;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo A: tipos de cita (TF-CIT-01 a 03).
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md
 */
class TiposCitaTest extends TestCase
{
    use CitasTestSetup;
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioCitas();
    }

    /**
     * TF-CIT-01 — Solo administración gestiona tipos de cita (con auditoría y versión).
     *
     * @return void
     */
    #[Test]
    public function solo_administracion_gestiona_tipos_de_cita(): void
    {
        $datos = [
            'codigo' => 'visita_domicilio',
            'nombre' => 'Visita domiciliaria',
            'etiqueta_publica' => 'Visita',
            'herramienta' => 'entrevista_seguimiento',
            'modalidad_defecto' => 'domicilio',
            'activo' => true,
        ];

        Livewire::actingAs($this->admin)->test(CreateTipoCita::class)
            ->fillForm($datos)
            ->call('create')
            ->assertHasNoFormErrors();

        $tipo = TipoCita::where('codigo', 'visita_domicilio')->sole();
        $this->assertTrue(Audit::where('auditable_type', $tipo->getMorphClass())->where('auditable_id', $tipo->id)->exists());

        $tipo->update(['etiqueta_publica' => 'Visita a domicilio']);
        $this->assertSame(1, Version::where('versionable_type', $tipo->getMorphClass())->where('versionable_id', $tipo->id)->count());

        // Intervención no entra al panel (redirige) ni puede usar la página de creación
        $this->actingAs($this->tsr)->get('/admin/tipo-citas/create')->assertRedirect();
        Livewire::actingAs($this->tsr)->test(CreateTipoCita::class)->assertForbidden();
        $this->assertSame(1, TipoCita::where('codigo', 'like', 'visita%')->count());
    }

    /**
     * TF-CIT-02 — El código es inmutable si hay citas del tipo; sin citas, se puede cambiar.
     *
     * @return void
     */
    #[Test]
    public function el_codigo_es_inmutable_con_citas(): void
    {
        $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07');

        try {
            $this->tipoSeguimiento->update(['codigo' => 'otro_codigo']);
            $this->fail('Con citas, el código no cambia');
        } catch (DomainException) {
        }
        $this->assertSame('seguimiento_pia_vg', $this->tipoSeguimiento->fresh()->codigo);

        $this->tipoInformacion->update(['codigo' => 'informacion_general']);
        $this->assertSame('informacion_general', $this->tipoInformacion->fresh()->codigo);
    }

    /**
     * TF-CIT-03 — Nombre interno para quien accede a la Historia Social; etiqueta
     * pública para el resto. [negativo]
     *
     * @return void
     */
    #[Test]
    public function nombre_interno_y_etiqueta_publica_segun_rol(): void
    {
        $this->assertSame('Entrevista', $this->tipoSeguimiento->nombreParaUsuario($this->consulta));
        $this->assertSame('Seguimiento PIA violencia de género', $this->tipoSeguimiento->nombreParaUsuario($this->tsr));
        $this->assertSame('Seguimiento PIA violencia de género', $this->tipoSeguimiento->nombreParaUsuario($this->supervisor));
        $this->assertSame('Entrevista', $this->tipoSeguimiento->nombreParaUsuario(null));
    }
}
