<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de ajustes v2 sobre la pantalla de login.
 * Saludo dinámico por hora y texto de acceso restringido.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_saludo_de_la_pantalla_de_login_no_contiene_genero(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('Bienvenido');
        $response->assertDontSee('Bienvenida');
    }

    #[Test]
    public function el_saludo_muestra_buenos_dias_por_la_manana(): void
    {
        $this->travelTo(now()->setTime(9, 0));

        $response = $this->get('/login');

        $response->assertSee('Buenos días');
    }

    #[Test]
    public function el_saludo_muestra_buenas_tardes_por_la_tarde(): void
    {
        $this->travelTo(now()->setTime(16, 0));

        $response = $this->get('/login');

        $response->assertSee('Buenas tardes');
    }

    #[Test]
    public function el_saludo_muestra_buenas_noches_por_la_noche(): void
    {
        $this->travelTo(now()->setTime(23, 0));

        $response = $this->get('/login');

        $response->assertSee('Buenas noches');
    }

    #[Test]
    public function la_pantalla_de_login_muestra_el_texto_de_acceso_restringido_correcto(): void
    {
        $response = $this->get('/login');

        $response->assertSee('Acceso restringido a personal autorizado');
        $response->assertSee('contacta con tu responsable de unidad');
    }
}
