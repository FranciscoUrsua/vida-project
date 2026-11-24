<?php

namespace Tests\Feature\Inicio;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Menú único y supervisión sola (pasos 6 de la ronda 1 de staging, R2 y R4): TF-STG-19 a 21
 * y TF-STG-30.
 */
class MenuYSupervisionTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del circuito.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();
    }

    /**
     * Claves de las entradas del menú lateral en el HTML de una pantalla.
     *
     * @param string $html
     *
     * @return list<string>
     */
    private function entradasMenu(string $html): array
    {
        $this->assertSame(1, preg_match('/<aside[^>]*class="op-sidebar".*?<\/aside>/s', $html, $menu));
        preg_match_all('/data-menu="([^"]+)"/', $menu[0], $claves);

        return $claves[1];
    }

    /** TF-STG-19 — La directora (intervención + supervisión) tiene el buscador y la Agenda en el menú. */
    #[Test]
    public function tf_stg_19_menu_con_varios_roles(): void
    {
        $this->directoraVallecas->assignRole('intervencion');
        $this->actingAs($this->directoraVallecas->fresh());

        foreach ([route('supervision.inicio'), route('intervencion.agenda.index')] as $pantalla) {
            $entradas = $this->entradasMenu($this->get($pantalla)->assertOk()->getContent());

            $this->assertContains('buscador', $entradas);
            $this->assertContains('agenda', $entradas);
            $this->assertContains('supervision.inicio', $entradas);
            $this->assertContains('supervision.equipo', $entradas);
        }
    }

    /** TF-STG-20 — Supervisión sola: ficha en lectura con los bloques de R2, sin edición ni documentos. */
    #[Test]
    public function tf_stg_20_ficha_para_supervision(): void
    {
        [$persona, $historia] = $this->personaEnCentro($this->ciamVallecas, protegida: false, referencia: $this->psicologaVallecas);
        $this->actingAs($this->coordIgualdad);

        $this->get(route('ciudadania.ciudadano.ficha', $persona->id))->assertOk();

        $ficha = Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $persona->id]);
        $html = $ficha->html();

        // Bloques de R2: identificación, contacto, estado de la Historia Social y citas
        $this->assertStringContainsString($persona->nombre, $html);
        $this->assertTrue($ficha->get('puedeVerEstadoHistoria'));
        $this->assertTrue($ficha->get('puedeVerHistorialCitas'));

        // Sin edición, sin documentos, sin Historia Social
        $this->assertFalse($ficha->get('puedeEditar'));
        $this->assertFalse($ficha->get('puedeEditarRelaciones'));
        $this->assertFalse($ficha->get('puedeVerDocumentos'));
        $this->assertFalse($ficha->get('puedeVerHistoria'));
        $this->assertStringNotContainsString('documentos-ciudadano', $html);
        $this->assertStringNotContainsString('Ir a HS', $html);
        $this->assertStringNotContainsString(route('intervencion.ciudadano.show', $historia->id), $html);

        // El buscador le lleva a la ficha, no a la Historia Social
        $persona->update(['alias' => 'aliasr2']);
        $busqueda = Livewire::test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'alias')->set('query', 'aliasr2')->call('buscar');
        $this->assertSame('ficha', collect($busqueda->get('resultados'))->firstWhere('ciudadano_id', $persona->id)['accion']);
    }

    /** TF-STG-21 — Supervisión sola, con la persona en su ámbito: Historia Social, plan y valoración → 403 propio. [negativo] */
    #[Test]
    public function tf_stg_21_supervision_sin_historia_ni_planes(): void
    {
        // La coordinación de Primaria (solo supervisión) tiene el CSS Norte en su ámbito
        $historia = $this->historiaAna;
        $plan = $this->pisoAna;
        $this->actingAs($this->coordPrimaria);

        foreach ([
            route('intervencion.ciudadano.show', $historia->id),
            route('intervencion.plan.show', $plan->id),
            route('intervencion.valoracion.nueva', $historia->id),
        ] as $ruta) {
            $this->get($ruta)
                ->assertForbidden()
                ->assertSee('No tienes permiso para ver esta página.')
                ->assertDontSee('User does not have the right roles');
        }

        // Y por permiso: ni Historia Social, ni apuntes, ni carpeta de documentos (R2)
        $coord = $this->coordPrimaria->fresh();
        foreach (['historia.leer', 'apunte.leer', 'apunte.leer_ajeno', 'documento.ver_carpeta', 'plan.leer'] as $permiso) {
            $this->assertFalse($coord->can($permiso), "supervision no debe tener {$permiso}");
        }
        $this->assertFalse($coord->can('view', $historia));
    }

    /** TF-STG-30 — Intervención + consulta básica: el mismo menú en Agenda, citas e Historia Social. [negativo] */
    #[Test]
    public function tf_stg_30_menu_unico(): void
    {
        $usuario = $this->usuario('ts.ventanilla', 'intervencion', $this->uoCssNorte);
        $usuario->assignRole('consulta_basica');
        $this->actingAs($usuario->fresh());

        $esperado = ['agenda', 'casos', 'bandeja.alertas', 'bandeja.avisos', 'bandeja.mensajes', 'buscador', 'alta', 'citacion'];

        foreach ([
            route('intervencion.agenda.index'),
            route('agenda.citas.bandeja'),
            route('intervencion.ciudadano.show', $this->historiaAna->id),
        ] as $pantalla) {
            $entradas = $this->entradasMenu($this->get($pantalla)->assertOk()->getContent());

            $this->assertSame($esperado, $entradas, "Menú en {$pantalla}");
            $this->assertSame(1, array_count_values($entradas)['buscador']);
            $this->assertEmpty(array_filter($entradas, fn (string $e) => str_starts_with($e, 'supervision.') || $e === 'tramitacion'));
        }
    }
}
