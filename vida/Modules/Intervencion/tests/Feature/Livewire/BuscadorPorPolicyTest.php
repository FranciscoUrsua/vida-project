<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Buscador: la acción de cada resultado la deciden las policies, no el rol (R-02 de la ronda 1
 * de staging, grupo B: TF-STG-08 a 11), y la identidad de una persona protegida, la misma
 * policy que las pantallas, con la salvedad de RN-17 (R-05, grupo D: TF-STG-17 y 18).
 */
class BuscadorPorPolicyTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    /** Ventanilla del CSS Norte: solo `consulta_basica`. */
    private User $ventanillaNorte;

    /** Tramitación del CSS Norte: solo `tramitacion`. */
    private User $tramitacionNorte;

    /**
     * Escenario del circuito con ventanilla y tramitación en el CSS Norte.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();

        $this->ventanillaNorte = $this->usuario('ventanilla.norte', 'consulta_basica', $this->uoCssNorte);
        $this->tramitacionNorte = $this->usuario('tramitacion.norte', 'tramitacion', $this->uoCssNorte);
        $this->ana->update(['alias' => 'aliasana']);
    }

    /**
     * Busca por alias y devuelve el resultado de la persona y el HTML de la pantalla.
     *
     * @param User $usuario Quien busca.
     * @param Ciudadano $persona Persona buscada (por su alias).
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function buscar(User $usuario, Ciudadano $persona): array
    {
        $this->actingAs($usuario);
        $prueba = Livewire::test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'alias')
            ->set('query', $persona->alias)
            ->call('buscar');

        $resultado = collect($prueba->get('resultados'))->firstWhere('ciudadano_id', $persona->id);
        $this->assertNotNull($resultado, 'La persona debe aparecer en la búsqueda');

        return [$resultado, $prueba->html()];
    }

    /** TF-STG-08 — `consulta_basica` va a la ficha de la persona. */
    #[Test]
    public function tf_stg_08_consulta_basica_va_a_la_ficha(): void
    {
        [$resultado, $html] = $this->buscar($this->ventanillaNorte, $this->ana);

        $this->assertSame('ficha', $resultado['accion']);
        $this->assertStringContainsString(route('ciudadania.ciudadano.ficha', $this->ana->id), $html);
        $this->assertStringNotContainsString(route('intervencion.ciudadano.show', $this->historiaAna->id), $html);
        $this->assertStringContainsString('Ir a la ficha de la persona', $html);

        $this->get(route('ciudadania.ciudadano.ficha', $this->ana->id))->assertOk();
    }

    /** TF-STG-09 — `tramitacion` va a la ficha de la persona. */
    #[Test]
    public function tf_stg_09_tramitacion_va_a_la_ficha(): void
    {
        [$resultado, $html] = $this->buscar($this->tramitacionNorte, $this->ana);

        $this->assertSame('ficha', $resultado['accion']);
        $this->assertStringContainsString(route('ciudadania.ciudadano.ficha', $this->ana->id), $html);
        $this->assertStringNotContainsString(route('intervencion.ciudadano.show', $this->historiaAna->id), $html);

        $this->get(route('ciudadania.ciudadano.ficha', $this->ana->id))->assertOk();
    }

    /** TF-STG-10 — `intervencion` sigue yendo a la Historia Social. */
    #[Test]
    public function tf_stg_10_intervencion_va_a_la_historia_social(): void
    {
        [$resultado, $html] = $this->buscar($this->tsNorte, $this->ana);

        $this->assertSame('historia', $resultado['accion']);
        $this->assertStringContainsString(route('intervencion.ciudadano.show', $this->historiaAna->id), $html);
        $this->assertStringContainsString('Ir a Historia Social', $html);
    }

    /** TF-STG-11 — `consulta_basica` en una ruta de la Historia Social: 403 propio. [negativo] */
    #[Test]
    public function tf_stg_11_sin_historia_social_403_propio(): void
    {
        $this->actingAs($this->ventanillaNorte)
            ->get(route('intervencion.ciudadano.show', $this->historiaAna->id))
            ->assertForbidden()
            ->assertSee('No tienes permiso para ver esta página.')
            ->assertDontSee('User does not have the right roles');
    }

    /** TF-STG-17 — Salvedad de RN-17: intervención de la UO funcional del CIAM ve la identidad. */
    #[Test]
    public function tf_stg_17_salvedad_de_rn17_en_el_buscador(): void
    {
        [$protegida] = $this->personaEnCentro($this->ciamVallecas, protegida: true, referencia: $this->psicologaVallecas);
        $protegida->update(['alias' => 'aliasprotegida']);

        [$resultado, $html] = $this->buscar($this->tecnicaIgualdad, $protegida);

        $this->assertNotNull($resultado['nombre']);
        $this->assertNotSame(3, $resultado['nivel']);
        $this->assertStringNotContainsString('Persona con protección especial', $html);
    }

    /** TF-STG-18 — Solo supervisión: la persona protegida aparece enmascarada, con «Solicitar acceso». [negativo] */
    #[Test]
    public function tf_stg_18_solo_supervision_enmascarada(): void
    {
        [$protegida] = $this->personaEnCentro($this->ciamVallecas, protegida: true, referencia: $this->psicologaVallecas);
        $protegida->update(['alias' => 'aliasprotegida']);

        [$resultado, $html] = $this->buscar($this->coordIgualdad, $protegida);

        $this->assertNull($resultado['nombre']);
        $this->assertSame('solicitar_acceso', $resultado['accion']);
        $this->assertStringContainsString('Persona con protección especial', $html);
        $this->assertStringContainsString('Solicitar acceso', $html);
    }
}
