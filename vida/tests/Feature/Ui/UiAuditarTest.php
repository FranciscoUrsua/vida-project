<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del comando `ui:auditar`: comprobación de que las superficies operativa
 * y pública usan solo Bootstrap, sin estilos inventados ni huérfanos.
 *
 * TF-UI-01 a TF-UI-26. Cada test monta un proyecto mínimo en un directorio
 * temporal (vistas, SCSS, CSS compilado y CSS de Bootstrap) y ejecuta el
 * comando contra él.
 *
 * @see docs/instrucciones-cli/2026-09-bootstrap-unico.md §3
 */
class UiAuditarTest extends TestCase
{
    private string $base;

    /** @var array<string, array<string, string>> */
    private array $catalogo = [];

    /** @var array<string, list<string>> Clases exigidas por bundle (R7). */
    private array $exigidas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/ui-auditar-'.uniqid();
        File::makeDirectory($this->base, 0755, true);

        $this->escribir('node_modules/bootstrap/dist/css/bootstrap.css', <<<'CSS'
            :root{--bs-primary:#0d6efd;--bs-body-color:#212529}
            .btn{display:inline-block}.btn-primary{color:#fff}.btn-sm{padding:0}
            .d-flex{display:flex}.gap-2{gap:.5rem}.text-primary{color:var(--bs-primary)}
            .row{display:flex}.col-md-6{flex:0 0 auto}.modal{display:none}.show{display:block}
            .card{border:1px solid}.w-100{width:100%}
            CSS);

        $this->escribir('resources/scss/_bootstrap-overrides.scss', '$primary: #2A5B8A;');
        $this->escribir('resources/scss/_vida-sass-tokens.scss', '$vida-ink-200: #E6E1D8;');
        $this->escribir('resources/scss/app-operativo.scss', "@import 'bootstrap/scss/bootstrap';\n@import './op-demo';");
        $this->escribir('resources/scss/app-public.scss', "@import 'bootstrap/scss/bootstrap';");
        $this->escribir('resources/scss/_op-demo.scss', <<<'SCSS'
            // Componente de ejemplo con BEM anidado
            .op-demo {
                border: 1px solid $vida-ink-200;

                &__titulo {
                    color: var(--bs-primary);
                }

                &--activo {
                    font-weight: 600;
                }
            }
            SCSS);

        $this->catalogo = [
            'op-demo' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Demo'],
            'op-demo__titulo' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Demo'],
            'op-demo--activo' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Demo'],
        ];

        $this->compilar();

        // Uso de las clases del catálogo, para que R5 no salte en los tests que no la prueban.
        $this->escribir('resources/views/usos.blade.php', '<div class="op-demo op-demo--activo"><h2 class="op-demo__titulo">x</h2></div>');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function escribir(string $ruta, string $contenido): void
    {
        $absoluta = $this->base.'/'.$ruta;
        File::ensureDirectoryExists(dirname($absoluta));
        File::put($absoluta, $contenido);
    }

    /**
     * Simula `npm run build`: CSS compilado de cada entrada = Bootstrap + clases
     * propias, y manifest de Vite.
     *
     * @param string $extraOperativo CSS adicional del bundle operativo.
     * @return void
     */
    private function compilar(string $extraOperativo = ''): void
    {
        $bootstrap = File::get($this->base.'/node_modules/bootstrap/dist/css/bootstrap.css');
        $propias = '.op-demo{border:1px solid #E6E1D8}.op-demo__titulo{color:var(--bs-primary)}.op-demo--activo{font-weight:600}';

        $this->escribir('public/build/assets/app-operativo-abc.css', $bootstrap.$propias.$extraOperativo);
        $this->escribir('public/build/assets/app-public-abc.css', $bootstrap);
        $this->escribir('public/build/manifest.json', json_encode([
            'resources/scss/app-operativo.scss' => ['file' => 'assets/app-operativo-abc.css', 'isEntry' => true],
            'resources/scss/app-public.scss' => ['file' => 'assets/app-public-abc.css', 'isEntry' => true],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Configura el auditor contra el proyecto temporal.
     *
     * @return void
     */
    private function configurar(): void
    {
        config([
            'ui-auditoria.base' => $this->base,
            'ui-auditoria.excluir_uso' => [],
            'ui-auditoria.clases_exigidas' => $this->exigidas,
            'ui-catalogo.clases' => $this->catalogo,
        ]);
    }

    /**
     * Ejecuta el comando y devuelve código de salida y salida de consola.
     *
     * @param array<string, mixed> $opciones
     *
     * @return array{0: int, 1: string}
     */
    private function auditar(array $opciones = []): array
    {
        $this->configurar();
        $codigo = $this->withoutMockingConsoleOutput()->artisan('ui:auditar', $opciones);
        $salida = Artisan::output();

        return [$codigo, $salida];
    }

    // -------------------------------------------------------------------------
    // TF-UI-01: proyecto limpio → éxito
    // -------------------------------------------------------------------------

    #[Test]
    public function proyecto_limpio_pasa_la_auditoria(): void
    {
        $this->escribir('resources/views/limpia.blade.php', '<div class="d-flex gap-2"><button class="btn btn-primary btn-sm">Guardar</button></div>');

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('Sin infracciones', $salida);
    }

    // -------------------------------------------------------------------------
    // TF-UI-02 a 06: R1, clase inexistente
    // -------------------------------------------------------------------------

    #[Test]
    public function r1_clase_tailwind_inexistente_en_el_bundle_falla(): void
    {
        $this->escribir('Modules/Demo/resources/views/livewire/pantalla.blade.php', "<div>\n<span class=\"d-flex items-center\">x</span>\n</div>");

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('R1', $salida);
        $this->assertStringContainsString('items-center', $salida);
        $this->assertStringContainsString('Modules/Demo/resources/views/livewire/pantalla.blade.php:2', $salida);
    }

    #[Test]
    public function r1_clase_gancho_del_catalogo_se_acepta_aunque_no_tenga_estilos(): void
    {
        $this->catalogo['js-arrastrable'] = ['tipo' => 'gancho', 'estado' => 'aprobada', 'descripcion' => 'Selector de Sortable'];
        $this->escribir('resources/views/gancho.blade.php', '<ul class="js-arrastrable"></ul>');

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(0, $codigo, $salida);
    }

    #[Test]
    public function r1_verifica_literales_dentro_de_expresiones_blade_y_de_class(): void
    {
        $this->escribir('resources/views/expresiones.blade.php', <<<'BLADE'
            <div class="btn {{ $activo ? 'btn-primary' : 'btn-fantasma' }}">x</div>
            <div @class(['card', 'tarjeta-inventada' => $x])>y</div>
            BLADE);

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('btn-fantasma', $salida);
        $this->assertStringContainsString('tarjeta-inventada', $salida);
        $this->assertStringNotContainsString("'btn-primary'", $salida);
    }

    #[Test]
    public function r1_clase_construida_dinamicamente_falla(): void
    {
        // CLAUDE.md prohíbe concatenar nombres de clase: el resultado no se puede comprobar
        $this->escribir('resources/views/dinamica.blade.php', '<span class="badge-{{ $tipo }} d-flex">x</span>');

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo, $salida);
        $this->assertStringContainsString('no se puede comprobar', $salida);
        $this->assertStringContainsString('dinámica', $salida);
        $this->assertStringContainsString('badge-', $salida);
    }

    #[Test]
    public function r1_una_clase_del_bundle_operativo_no_vale_en_una_vista_publica(): void
    {
        $this->escribir('resources/views/auth/login.blade.php', '<div class="op-demo">x</div>');

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('resources/views/auth/login.blade.php', $salida);
        $this->assertStringContainsString('op-demo', $salida);
    }

    // -------------------------------------------------------------------------
    // TF-UI-07 y 08: R2, estilo inline
    // -------------------------------------------------------------------------

    #[Test]
    public function r2_estilo_inline_literal_falla(): void
    {
        $this->escribir('resources/views/inline.blade.php', '<div style="display: flex; gap: 1rem;">x</div>');

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('R2', $salida);
        $this->assertStringContainsString('display: flex', $salida);
    }

    #[Test]
    public function r2_estilo_inline_solo_con_valores_dinamicos_se_acepta(): void
    {
        $this->escribir('resources/views/dinamico.blade.php', '<div class="w-100" style="width: {{ $pct }}%; --op-demo-color: {{ $color }}">x</div>');

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(0, $codigo, $salida);
    }

    // -------------------------------------------------------------------------
    // TF-UI-09 a 12: R3, colores literales y variables inexistentes
    // -------------------------------------------------------------------------

    #[Test]
    public function r3_color_literal_en_blade_falla_en_style_y_en_atributos_svg(): void
    {
        $this->escribir('resources/views/colores.blade.php', <<<'BLADE'
            <div style="color: {{ $c }}; background: #fff3cd">x</div>
            <svg><circle fill="#2A5B8A" /></svg>
            <a href="#add">ancla, no es color</a>
            BLADE);

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('R3', $salida);
        $this->assertStringContainsString('#fff3cd', $salida);
        $this->assertStringContainsString('#2A5B8A', $salida);
        $this->assertStringNotContainsString('#add', $salida);
    }

    #[Test]
    public function r3_variable_css_inexistente_en_blade_falla_y_las_de_bootstrap_se_aceptan(): void
    {
        $this->escribir('resources/views/variables.blade.php', <<<'BLADE'
            <div class="text-primary" style="border-color: var(--bs-primary)">x</div>
            <div style="color: var(--color-primary)">y</div>
            BLADE);

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('variable no definida --color-primary', $salida);
        $this->assertStringNotContainsString('variable no definida --bs-primary', $salida);
    }

    #[Test]
    public function r3_color_literal_en_scss_falla_salvo_en_los_ficheros_de_tokens(): void
    {
        $this->escribir('resources/scss/_op-demo.scss', File::get($this->base.'/resources/scss/_op-demo.scss')."\n.op-demo { background: #FAF7F1; box-shadow: 0 1px 2px rgba(0, 0, 0, .1); outline-color: rgba(\$vida-ink-200, .5); }\n");

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('resources/scss/_op-demo.scss', $salida);
        $this->assertStringContainsString('#FAF7F1', $salida);
        $this->assertStringContainsString('rgba(0, 0, 0, .1)', $salida);
        $this->assertStringNotContainsString('rgba($vida-ink-200', $salida);
        $this->assertStringNotContainsString('_vida-sass-tokens.scss', $salida);
    }

    #[Test]
    public function r3_variable_css_inexistente_en_scss_falla(): void
    {
        $this->escribir('resources/scss/_op-demo.scss', File::get($this->base.'/resources/scss/_op-demo.scss')."\n.op-demo { padding: var(--space-2); }\n");

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('--space-2', $salida);
    }

    // -------------------------------------------------------------------------
    // TF-UI-13 a 16: R4, clases fuera de catálogo
    // -------------------------------------------------------------------------

    #[Test]
    public function r4_clase_propia_anidada_fuera_del_catalogo_falla(): void
    {
        unset($this->catalogo['op-demo--activo']);

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('R4', $salida);
        $this->assertStringContainsString('op-demo--activo', $salida);
    }

    #[Test]
    public function r4_reestilar_una_clase_de_bootstrap_no_exige_catalogo(): void
    {
        $this->escribir('resources/scss/_op-demo.scss', File::get($this->base.'/resources/scss/_op-demo.scss')."\n.op-demo .btn { font-weight: 600; }\n");

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(0, $codigo, $salida);
    }

    #[Test]
    public function r4_entrada_del_catalogo_sin_definir_en_scss_falla(): void
    {
        $this->catalogo['op-fantasma'] = ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'No existe'];
        $this->escribir('resources/views/fantasma.blade.php', '<div class="op-fantasma">x</div>');

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('op-fantasma', $salida);
    }

    #[Test]
    public function r4_clases_pendientes_fallan_en_modo_bloqueante_pero_no_con_informe(): void
    {
        $this->catalogo['op-demo']['estado'] = 'pendiente';

        [$codigo, $salida] = $this->auditar();
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('pendiente', $salida);

        [$codigo] = $this->auditar(['--informe' => true]);
        $this->assertSame(0, $codigo);
    }

    // -------------------------------------------------------------------------
    // TF-UI-17 y 18: R5, clases huérfanas
    // -------------------------------------------------------------------------

    #[Test]
    public function r5_clase_del_catalogo_sin_uso_falla(): void
    {
        $this->escribir('resources/views/usos.blade.php', '<div class="op-demo op-demo--activo"></div>');

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('R5', $salida);
        $this->assertStringContainsString('op-demo__titulo', $salida);
    }

    #[Test]
    public function r5_el_uso_desde_una_clase_php_cuenta(): void
    {
        $this->escribir('resources/views/usos.blade.php', '<div class="op-demo op-demo--activo"></div>');
        $this->escribir('app/Enums/Demo.php', "<?php\nreturn 'op-demo__titulo';\n");

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(0, $codigo, $salida);
    }

    // -------------------------------------------------------------------------
    // TF-UI-19: R6, Tailwind en una entrada del ámbito
    // -------------------------------------------------------------------------

    #[Test]
    public function r6_tailwind_en_una_entrada_del_ambito_falla(): void
    {
        $this->escribir('resources/scss/app-public.scss', "@import 'bootstrap/scss/bootstrap';\n@import 'tailwindcss';\n");

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('R6', $salida);
        $this->assertStringContainsString('app-public.scss', $salida);
    }

    // -------------------------------------------------------------------------
    // TF-UI-24 a 26: R1 sobre clases que se deciden en PHP
    // -------------------------------------------------------------------------

    #[Test]
    public function r1_interpolacion_de_variable_o_de_tabla_en_clase_falla(): void
    {
        $this->escribir('resources/views/opaca.blade.php', <<<'BLADE'
            @php $x = 'btn-fantasma'; $mapa = ['a' => 'bg-protectd']; @endphp
            <span class="btn {{ $x }}">x</span>
            <span class="btn {{ $mapa[$k] ?? 'btn-primary' }}">y</span>
            <span class="btn {{ $x ?: 'btn-sm' }}">z</span>
            BLADE);

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('opaca.blade.php:2', $salida);
        $this->assertStringContainsString('opaca.blade.php:3', $salida);
        // `a ?: b` devuelve la propia condición
        $this->assertStringContainsString('opaca.blade.php:4', $salida);
        $this->assertStringContainsString('{{ $x }}', $salida);
    }

    #[Test]
    public function r1_ternarios_de_literales_y_metodos_clases_se_aceptan(): void
    {
        $this->escribir('resources/views/comprobable.blade.php', <<<'BLADE'
            <span class="btn {{ $a ? 'btn-primary' : ($b ? 'btn-sm' : '') }}">x</span>
            <span class="btn {{ $tono->clasesSuave() }}">x</span>
            <span class="btn {{ \App\Tonos::estado($e)?->clasesPunto() ?? 'btn-sm' }}">x</span>
            <span class="btn {{ $estado->tono()->clasesFuerte() }}">x</span>
            BLADE);

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(0, $codigo, $salida);
    }

    #[Test]
    public function r1_fuente_de_clases_con_una_clase_inexistente_falla(): void
    {
        $nombre = 'FuentePrueba'.uniqid();
        $this->escribir("app/Ui/{$nombre}.php", <<<PHP
            <?php

            namespace Tests\\Temporal;

            use App\\Support\\Ui\\FuenteClasesCss;

            enum {$nombre}: string implements FuenteClasesCss
            {
                case A = 'a';

                public static function clasesCss(): array
                {
                    return ['btn-primary', 'bg-protectd'];
                }
            }
            PHP);

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString("app/Ui/{$nombre}.php", $salida);
        $this->assertStringContainsString('bg-protectd', $salida);
        $this->assertStringNotContainsString('btn-primary', $salida);
    }

    // -------------------------------------------------------------------------
    // TF-UI-22 y 23: R7 — clases exigidas en el CSS compilado
    // -------------------------------------------------------------------------

    #[Test]
    public function r7_clase_exigida_ausente_del_css_compilado_falla(): void
    {
        $this->exigidas = ['operativo' => ['bg-protected', 'btn-outline-protected']];
        $this->compilar('.bg-protected{background:var(--bs-primary)}');

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('R7', $salida);
        $this->assertStringContainsString('btn-outline-protected', $salida);
        $this->assertStringNotContainsString('«bg-protected»', $salida);
    }

    #[Test]
    public function r7_clase_exigida_presente_solo_en_otro_bundle_falla(): void
    {
        $this->exigidas = ['publico' => ['op-demo']];

        [$codigo, $salida] = $this->auditar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('R7', $salida);

        $this->exigidas = ['operativo' => ['op-demo']];
        [$codigo] = $this->auditar();

        $this->assertSame(0, $codigo);
    }

    // -------------------------------------------------------------------------
    // TF-UI-20 y 21: ámbito y opciones
    // -------------------------------------------------------------------------

    #[Test]
    public function vistas_de_filament_y_pdf_quedan_fuera_y_modulo_filtra_las_vistas(): void
    {
        $this->escribir('resources/views/filament/pagina.blade.php', '<div class="grid-cols-3" style="display:grid">x</div>');
        $this->escribir('Modules/Demo/resources/views/pdf/plan.blade.php', '<td style="color:#000">x</td>');
        $this->escribir('Modules/Otro/resources/views/livewire/mal.blade.php', '<div class="items-center">x</div>');

        [$codigo, $salida] = $this->auditar(['--modulo' => 'Demo']);

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringNotContainsString('grid-cols-3', $salida);
        $this->assertStringNotContainsString('items-center', $salida);

        [$codigo, $salida] = $this->auditar();
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('items-center', $salida);
        $this->assertStringNotContainsString('grid-cols-3', $salida);
    }

    #[Test]
    public function sin_css_compilado_el_comando_falla_con_un_mensaje_claro(): void
    {
        File::delete($this->base.'/public/build/manifest.json');

        [$codigo, $salida] = $this->auditar(['--informe' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('npm run build', $salida);
    }
}
