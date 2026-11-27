<?php

namespace Modules\Intervencion\Tests\Feature\Usabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nombre del profesional y barra de estadísticas en la Historia Social (U4, U5; TF-USA-15 y 16).
 *
 * `User::booted()` rellena `name` con el correo, así que mostrar `name` enseña el correo del
 * profesional: en la interfaz se usa siempre `User::nombreVisible()`.
 *
 * @see docs/instrucciones-cli/2026-11-usabilidad-historia-social.md, pasos 4 y 5
 */
class NombreYEstadisticasTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    /**
     * Circuito de intervención; actúa el TS del CSS Norte.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();
        $this->actingAs($this->tsNorte);
    }

    /**
     * TF-USA-15 [negativo] — El autor de una anotación aparece con su nombre completo en el
     * timeline y en la modal de detalle, nunca con su correo.
     *
     * @return void
     */
    #[Test]
    public function el_autor_de_una_anotacion_aparece_con_su_nombre_y_no_con_su_correo(): void
    {
        $apunte = Apunte::create([
            'historia_id' => $this->historiaAna->id,
            'autor_id' => $this->tsNorte2->id,
            'fecha' => today(),
            'tipo' => TipoApunte::Anotacion,
            'contenido' => 'Llamada de seguimiento.',
            'visibilidad' => VisibilidadApunte::Profesionales,
        ]);
        $nombre = $this->tsNorte2->profesional->nombre_completo;
        $correo = $this->tsNorte2->email;
        $this->assertSame($correo, $this->tsNorte2->name);

        $pagina = Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna]);
        $this->assertStringContainsString($nombre, $this->timeline($pagina->html()));
        $this->assertStringNotContainsString($correo, $this->timeline($pagina->html()));

        $pagina->call('verApunte', $apunte->id)
            ->assertSet('modalApunteDatos.autor', $nombre);
    }

    /**
     * TF-USA-15 (paso 4.3) — Ninguna vista de los módulos muestra `->name` de una persona.
     *
     * @return void
     */
    #[Test]
    public function ninguna_vista_muestra_el_name_de_una_persona(): void
    {
        $infracciones = [];

        foreach (File::allFiles(base_path('Modules')) as $fichero) {
            if (str_ends_with($fichero->getFilename(), '.blade.php') && str_contains($fichero->getPathname(), '/resources/views/')) {
                $this->buscar($fichero->getPathname(), $infracciones);
            }
        }

        $this->assertSame([], $infracciones, 'Usa nombreVisible() para mostrar personas.');
    }

    /**
     * TF-USA-16 — La Historia Social no tiene la barra de estadísticas.
     *
     * @return void
     */
    #[Test]
    public function la_historia_social_no_tiene_barra_de_estadisticas(): void
    {
        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertDontSee('Prestaciones activas')
            ->assertDontSee('Último contacto');
    }

    /**
     * Anota las líneas de una vista que muestran `->name }}` de autor, usuario, creador,
     * responsable o profesional.
     *
     * @param string $vista
     * @param list<string> $infracciones
     *
     * @return void
     */
    private function buscar(string $vista, array &$infracciones): void
    {
        foreach (file($vista) ?: [] as $n => $linea) {
            if (preg_match('/(autor|user|usuario|creador|responsable|profesional)\??->name\b[^}]*}}/i', $linea)) {
                $infracciones[] = str_replace(base_path().'/', '', $vista).':'.($n + 1);
            }
        }
    }

    /**
     * Fragmento del HTML con la línea de tiempo de la Historia Social (sin el widget de accesos
     * ni el resto de la página).
     *
     * @param string $html
     *
     * @return string
     */
    private function timeline(string $html): string
    {
        $inicio = strpos($html, 'Llamada de seguimiento.');
        $this->assertNotFalse($inicio, 'La anotación tiene que estar en la línea de tiempo.');

        return substr($html, max(0, $inicio - 1500), 3000);
    }
}
