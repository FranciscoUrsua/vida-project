<?php

namespace Modules\Ciudadania\Tests\Feature\ConsultaBasica;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Tests\Concerns\ConsultaBasicaTestSetup;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Personas protegidas y consulta básica (fase 4, F4-1 confirmada el 15 nov 2026).
 *
 * Consulta básica ve los datos objetivos de una persona protegida solo desde el centro
 * de su historia o de un plan en curso (RN-17, `gestionaProtegido()`), y nunca el
 * banner de protegido. Desde otro centro, «Persona con protección especial».
 *
 * TF-CBA-08, 09 y 10.
 *
 * @see docs/instrucciones-cli/2026-11-fase4-consulta-basica.md
 */
class ProtegidosConsultaBasicaTest extends TestCase
{
    use ConsultaBasicaTestSetup;
    use RefreshDatabase;

    /**
     * Prepara el escenario, con una cita y una unidad de convivencia de Marta.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarConsultaBasica();

        $this->cita($this->marta, $this->tsNorte, $this->cssNorte, '2026-11-09', '09:00', EstadoCita::Confirmada);
        $uc = UnidadConvivencia::create(['domicilio' => 'Calle Reservada 2', 'fecha_constitucion' => '2026-01-01']);
        $uc->agregarMiembro($this->marta->id);
        $uc->agregarMiembro($this->pedro->id);
    }

    /** TF-CBA-08 — Sin banner de protegido [negativo]. */
    #[Test]
    public function tf_cba_08_sin_banner_de_protegido(): void
    {
        $this->actingAs($this->ventanillaNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->marta->id))
            ->assertOk()
            ->assertDontSee('protección especial')
            ->assertDontSee('Protección especial')
            ->assertDontSee('protegid')
            ->assertDontSee('Protegid')
            ->assertDontSee('colectivo')
            ->assertDontSee('Colectivo');
    }

    /** TF-CBA-09 — Protegida, desde su centro: datos objetivos (F4-1). */
    #[Test]
    public function tf_cba_09_protegida_desde_su_centro(): void
    {
        $this->actingAs($this->ventanillaNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->marta->id))
            ->assertOk()
            ->assertSee($this->marta->nombre)
            ->assertSee('Identificación y contacto')
            ->assertSee('Unidad de convivencia')
            ->assertSee('Relaciones')
            ->assertSee('id="ficha-citas"', false)
            ->assertSee('09/11/2026');
    }

    /** TF-CBA-10 — Protegida, desde otro centro: sin datos (F4-1) [negativo]. */
    #[Test]
    public function tf_cba_10_protegida_desde_otro_centro(): void
    {
        $busqueda = Livewire::actingAs($this->ventanillaSur)->test(BuscarCiudadanoPage::class)
            ->set('query', $this->marta->nombre.' '.$this->marta->apellido1)
            ->call('buscar')
            ->assertSee('Persona con protección especial')
            ->assertDontSee($this->marta->apellido1);

        $resultado = collect($busqueda->get('resultados'))->firstWhere('ciudadano_id', $this->marta->id);
        $this->assertNotNull($resultado);
        $this->assertNull($resultado['nombre']);
        $this->assertSame(3, $resultado['nivel']);

        // La ficha no se abre con datos
        $this->actingAs($this->ventanillaSur)
            ->get(route('ciudadania.ciudadano.ficha', $this->marta->id))
            ->assertForbidden();
    }
}
