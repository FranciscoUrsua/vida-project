<?php

namespace Modules\Ciudadania\Tests\Feature\ConsultaBasica;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ciudadania\Tests\Concerns\ConsultaBasicaTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bloque «Historia Social» de la ficha: existencia, estado y referencia; nada más (fase 4).
 *
 * TF-CBA-02, 03 y 30.
 *
 * @see docs/instrucciones-cli/2026-11-fase4-consulta-basica.md
 */
class EstadoHistoriaTest extends TestCase
{
    use ConsultaBasicaTestSetup;
    use RefreshDatabase;

    /**
     * Prepara el escenario de la fase.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarConsultaBasica();
    }

    /** TF-CBA-02 — Estado de la historia sin contenido [negativo]. */
    #[Test]
    public function tf_cba_02_estado_de_la_historia_sin_contenido(): void
    {
        $respuesta = $this->actingAs($this->ventanillaNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertOk()
            ->assertSee('id="ficha-estado-historia"', false)
            ->assertSee('Abierta')
            ->assertSee($this->tsNorte->nombreVisible());

        // Ni enlace a la historia, ni fechas de la referencia, ni planes, ni apuntes
        $respuesta->assertDontSee(route('intervencion.ciudadano.show', $this->historiaAna->id))
            ->assertDontSee('Ir a HS')
            ->assertDontSee('01/01/2026') // inicio de la referencia
            ->assertDontSee('PISO')
            ->assertDontSee('Apunte');

        // Contraste: intervención sí ve la referencia con su fecha y el enlace
        $this->actingAs($this->tsNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertSee('01/01/2026')
            ->assertSee(route('intervencion.ciudadano.show', $this->historiaAna->id));
    }

    /** TF-CBA-03 — Persona sin historia. */
    #[Test]
    public function tf_cba_03_sin_historia(): void
    {
        $this->actingAs($this->ventanillaNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->pedro->id))
            ->assertOk()
            ->assertSee('No tiene Historia Social.');
    }

    /** TF-CBA-30 — Consulta básica y tramitación no abren la Historia Social [negativo]. */
    #[Test]
    public function tf_cba_30_consulta_basica_y_tramitacion_no_abren_la_historia(): void
    {
        $rutas = [
            route('intervencion.ciudadano.show', $this->historiaAna->id),
            route('intervencion.plan.show', $this->pisoAna->id),
            route('intervencion.valoracion.nueva', $this->historiaAna->id),
            route('intervencion.escala.nueva', $this->historiaAna->id),
        ];

        foreach ([$this->ventanillaNorte, $this->adminNorte] as $usuario) {
            foreach ($rutas as $ruta) {
                $this->actingAs($usuario)->get($ruta)->assertForbidden();
            }
        }

        // Contraste: la ficha de la historia sí la abre su trabajadora social
        $this->actingAs($this->tsNorte)->get($rutas[0])->assertOk();
    }
}
