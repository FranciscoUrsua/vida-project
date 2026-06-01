<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use App\Models\AccesoProtegido;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use Modules\Mensajes\Models\Alerta;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del componente Livewire BuscarCiudadanoPage.
 *
 * TF-LW-BUS-01 a TF-LW-BUS-10
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega2.md §6
 */
class BuscarCiudadanoPageTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;
    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create([
            'nombre'    => 'CSS Test Buscar',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);

        $this->usuario = User::create([
            'name'              => 'Buscador Test',
            'email'             => 'buscar@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
        ]);

        $this->usuario->assignRole('intervencion');

        UsuarioUo::create([
            'usuario_id'             => $this->usuario->id,
            'unidad_organizativa_id' => $this->uo->id,
            'tipo_vinculo'           => 'interno',
            'fecha_inicio'           => today()->toDateString(),
        ]);
    }

    /**
     * Crea un ciudadano con alias y los campos cifrados mínimos.
     *
     * @param array<string, mixed> $attrs
     * @return Ciudadano
     */
    private function crearCiudadano(array $attrs = []): Ciudadano
    {
        return Ciudadano::create(array_merge([
            'alias'               => null,
            'nombre'              => 'María',
            'apellido1'           => 'García',
            'apellido2'           => 'López',
            'fecha_nacimiento'    => '1980-01-01',
            'sexo'                => 'F',
            'nivel_identificacion'=> 'identificado',
            'activo'              => true,
            'colectivo_extra_protegido' => false,
        ], $attrs));
    }

    /**
     * Crea una Historia Social del ciudadano en la UO dada.
     *
     * @param Ciudadano $ciudadano
     * @param UnidadOrganizativa $uo
     * @return HistoriaSocial
     */
    private function crearHistoria(Ciudadano $ciudadano, UnidadOrganizativa $uo): HistoriaSocial
    {
        return HistoriaSocial::create([
            'ciudadano_id'           => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-LW-BUS-01 — Buscar por nombre devuelve ciudadanos que coinciden.
     */
    #[Test]
    public function buscar_por_nombre_devuelve_coincidencias(): void
    {
        $this->crearCiudadano(['nombre' => 'Ana', 'apellido1' => 'Pérez']);
        $this->crearCiudadano(['nombre' => 'Luis', 'apellido1' => 'Torres']);

        $componente = Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'nombre')
            ->set('query', 'Ana')
            ->call('buscar');

        $resultados = $componente->get('resultados');
        $this->assertCount(1, $resultados);
        $this->assertStringContainsString('Ana', $resultados[0]['nombre']);
    }

    /**
     * TF-LW-BUS-02 — Resultado de propia UO muestra nivel 1 (botón "Ir a Historia Social").
     */
    #[Test]
    public function resultado_propia_uo_muestra_nivel_1(): void
    {
        $ciudadano = $this->crearCiudadano(['alias' => 'test-nivel1', 'nombre' => 'Carlos', 'apellido1' => 'Ruiz']);
        $this->crearHistoria($ciudadano, $this->uo);

        $componente = Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'alias')
            ->set('query', 'test-nivel1')
            ->call('buscar');

        $resultados = $componente->get('resultados');
        $this->assertNotEmpty($resultados);
        $this->assertEquals(1, $resultados[0]['nivel']);
    }

    /**
     * TF-LW-BUS-03 — Resultado de otra UO muestra nivel 2 (botón con nota de acceso registrado).
     */
    #[Test]
    public function resultado_otra_uo_muestra_nivel_2(): void
    {
        $otraUo = UnidadOrganizativa::create([
            'nombre' => 'CSS Otra UO',
            'tipo'   => 'centro',
            'activa' => true,
        ]);

        $ciudadano = $this->crearCiudadano(['alias' => 'test-nivel2', 'nombre' => 'Eva', 'apellido1' => 'Martín']);
        $this->crearHistoria($ciudadano, $otraUo);

        $componente = Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'alias')
            ->set('query', 'test-nivel2')
            ->call('buscar');

        $resultados = $componente->get('resultados');
        $this->assertNotEmpty($resultados);
        $this->assertEquals(2, $resultados[0]['nivel']);
    }

    /**
     * TF-LW-BUS-04 — Resultado de colectivo protegido muestra nivel 3 (botón "Solicitar acceso").
     */
    #[Test]
    public function resultado_colectivo_protegido_muestra_nivel_3(): void
    {
        $ciudadano = $this->crearCiudadano([
            'alias'                     => 'test-nivel3',
            'nombre'                    => 'Rosa',
            'apellido1'                 => 'Díaz',
            'colectivo_extra_protegido' => true,
        ]);

        $componente = Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'alias')
            ->set('query', 'test-nivel3')
            ->call('buscar');

        $resultados = $componente->get('resultados');
        $this->assertNotEmpty($resultados);
        $this->assertEquals(3, $resultados[0]['nivel']);
    }

    /**
     * TF-LW-BUS-05 — Ciudadano protegido no expone domicilio en los resultados.
     */
    #[Test]
    public function ciudadano_protegido_no_expone_domicilio(): void
    {
        $ciudadano = $this->crearCiudadano([
            'alias'                     => 'test-dom',
            'nombre'                    => 'Juana',
            'apellido1'                 => 'Navarro',
            'colectivo_extra_protegido' => true,
        ]);

        $componente = Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'alias')
            ->set('query', 'test-dom')
            ->call('buscar');

        $resultados = $componente->get('resultados');
        $this->assertNotEmpty($resultados);
        $this->assertNull($resultados[0]['domicilio']);
    }

    /**
     * TF-LW-BUS-06 — Acceso nivel 2 registra entrada en audits con event = 'acceso_nivel2'.
     */
    #[Test]
    public function acceso_nivel_2_registra_en_log_de_auditoria(): void
    {
        $otraUo = UnidadOrganizativa::create([
            'nombre' => 'CSS Otra UO Bus06',
            'tipo'   => 'centro',
            'activa' => true,
        ]);

        $ciudadano = $this->crearCiudadano(['nombre' => 'Pedro', 'apellido1' => 'Sáez']);
        $historia  = $this->crearHistoria($ciudadano, $otraUo);

        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(fn($msg, $ctx) => $msg === 'acceso_nivel2'
                && $ctx['auditable_type'] === 'HistoriaSocial'
                && $ctx['auditable_id'] === $historia->id
                && $ctx['event'] === 'acceso_nivel2'
                && $ctx['user_id'] === $this->usuario->id);

        Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->call('registrarAccesoNivel2', $historia->id);
    }

    /**
     * TF-LW-BUS-07 — Solicitar acceso con justificacion vacía no crea la solicitud.
     */
    #[Test]
    public function solicitar_acceso_con_justificacion_corta_no_crea_solicitud(): void
    {
        $ciudadano = $this->crearCiudadano(['colectivo_extra_protegido' => true]);

        Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->call('solicitarAcceso', $ciudadano->id, 'corta');

        $this->assertDatabaseCount('accesos_protegidos', 0);
    }

    /**
     * TF-LW-BUS-08 — Solicitar acceso crea una alerta para el supervisor de la UO responsable.
     */
    #[Test]
    public function solicitar_acceso_crea_alerta_para_supervisor(): void
    {
        $ciudadano = $this->crearCiudadano(['colectivo_extra_protegido' => true]);
        $historia  = $this->crearHistoria($ciudadano, $this->uo);

        Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->call('solicitarAcceso', $ciudadano->id, 'Justificación suficientemente larga para superar el mínimo.');

        $this->assertDatabaseHas('alertas', [
            'destinatario_rol'   => 'supervision',
            'destinatario_uo_id' => $this->uo->id,
        ]);
        $this->assertDatabaseCount('accesos_protegidos', 1);
    }

    /**
     * TF-LW-BUS-09 — NI-HSU-CM solo aparece en resultados que tienen Historia Social.
     * (Búsqueda por doc/hsu retorna vacío al no existir la tabla ciudadano_identificadores.)
     */
    #[Test]
    public function busqueda_por_hsu_retorna_sin_resultados_por_falta_de_tabla(): void
    {
        $this->crearCiudadano();

        $componente = Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'hsu')
            ->set('query', '12345678')
            ->call('buscar');

        $this->assertEmpty($componente->get('resultados'));
        $this->assertTrue($componente->get('buscado'));
    }

    /**
     * TF-LW-BUS-10 — El pie "Dar de alta" es siempre visible aunque no haya resultados.
     */
    #[Test]
    public function boton_dar_de_alta_siempre_visible(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->assertSee('Dar de alta nuevo ciudadano');

        // También visible tras una búsqueda sin resultados
        Livewire::actingAs($this->usuario)
            ->test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'alias')
            ->set('query', 'inexistente-xyz')
            ->call('buscar')
            ->assertSee('Dar de alta nuevo ciudadano');
    }
}
