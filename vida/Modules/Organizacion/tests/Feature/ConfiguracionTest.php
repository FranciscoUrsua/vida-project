<?php

namespace Modules\Organizacion\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Organizacion\Models\Configuracion;
use Modules\Organizacion\Services\ConfiguracionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del modelo Configuracion — logotipo único de organización.
 *
 * @see docs/decisiones-tecnicas.md Sección 12
 */
class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function logo_path_absoluto_devuelve_ruta_en_disco_cuando_el_logo_existe(): void
    {
        Storage::fake('public');

        $ruta = UploadedFile::fake()->image('logo.png')->store('branding', 'public');
        app(ConfiguracionService::class)->set('logo_path', $ruta);

        $resultado = Configuracion::logoPathAbsoluto();

        $this->assertNotNull($resultado);
        $this->assertFileExists($resultado);
    }

    #[Test]
    public function logo_path_absoluto_devuelve_null_si_no_hay_logo_configurado(): void
    {
        Storage::fake('public');

        $this->assertNull(Configuracion::logoPathAbsoluto());
    }

    #[Test]
    public function logo_path_absoluto_devuelve_null_si_el_fichero_configurado_ya_no_existe_en_disco(): void
    {
        Storage::fake('public');

        // La clave apunta a un fichero que nunca se subió (o se borró del disco)
        app(ConfiguracionService::class)->set('logo_path', 'branding/logo-inexistente.png');

        $this->assertNull(Configuracion::logoPathAbsoluto());
    }
}
