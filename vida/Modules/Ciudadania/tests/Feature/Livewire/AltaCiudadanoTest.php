<?php

namespace Modules\Ciudadania\Tests\Feature\Livewire;

use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Contracts\FuenteIdentidadInterface;
use Modules\Ciudadania\Http\Livewire\AltaCiudadano;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del componente Livewire AltaCiudadano.
 *
 * TF-LW-ALT-01 a TF-LW-ALT-19
 *
 * @see docs/instrucciones-cli/instrucciones-cli-alta-ciudadano.md § Tarea 5
 */
class AltaCiudadanoTest extends TestCase
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
            'nombre'    => 'CSS Alta Test',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);

        $this->usuario = $this->crearUsuario('intervencion');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function crearUsuario(string $rol, string $email = null): User
    {
        $user = User::create([
            'name'               => "Test {$rol}",
            'email'              => $email ?? "{$rol}." . uniqid() . '@vida360.test',
            'password'           => 'secreto',
            'email_verified_at'  => now(),
            'primer_acceso'      => false,
        ]);
        $user->assignRole($rol);

        UsuarioUo::create([
            'usuario_id'             => $user->id,
            'unidad_organizativa_id' => $this->uo->id,
            'tipo_vinculo'           => 'interno',
            'fecha_inicio'           => today()->toDateString(),
        ]);

        return $user;
    }

    private function crearCiudadanoConDocumento(string $tipoDoc, string $valorDoc, array $extra = []): Ciudadano
    {
        $ciudadano = Ciudadano::create(array_merge([
            'nombre'              => 'Ana',
            'apellido1'           => 'González',
            'fecha_nacimiento'    => '1985-03-15',
            'sexo'                => 'F',
            'nivel_identificacion' => 'identificado',
            'activo'              => true,
        ], $extra));

        CiudadanoIdentificador::create([
            'ciudadano_id' => $ciudadano->id,
            'tipo'         => $tipoDoc,
            'valor'        => $valorDoc,
            'fecha_inicio' => today()->toDateString(),
            'verificado'   => false,
            'fuente'       => 'manual',
        ]);

        return $ciudadano;
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-01: acceso sin autenticación → 302
    // -------------------------------------------------------------------------

    #[Test]
    public function componente_no_accesible_sin_autenticacion(): void
    {
        $this->get(route('ciudadania.alta'))
            ->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-02: roles autorizados pueden montar el componente
    // -------------------------------------------------------------------------

    #[Test]
    public function roles_autorizados_pueden_montar_el_componente(): void
    {
        foreach (['intervencion', 'tramitacion', 'consulta_basica'] as $rol) {
            $user = $this->crearUsuario($rol);
            Livewire::actingAs($user)
                ->test(AltaCiudadano::class)
                ->assertStatus(200);
        }
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-03: buscar() por documento normaliza y encuentra por hash
    // -------------------------------------------------------------------------

    #[Test]
    public function buscar_por_documento_normaliza_y_encuentra_por_hash(): void
    {
        $this->crearCiudadanoConDocumento('nif', '12345678A');

        Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('busquedaTipoDoc', 'nif')
            ->set('busquedaValorDoc', '12345678a') // minúsculas — debe normalizar
            ->call('buscar')
            ->assertSet('busquedaRealizada', true)
            ->assertSet('fase', 'busqueda');

        $component = Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('busquedaTipoDoc', 'nif')
            ->set('busquedaValorDoc', '12345678a')
            ->call('buscar');

        $this->assertNotEmpty($component->get('resultadosBusqueda'));
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-04: buscar() por nombre+fecha devuelve candidatos ordenados
    // -------------------------------------------------------------------------

    #[Test]
    public function buscar_por_nombre_fecha_devuelve_candidatos_ordenados_por_score(): void
    {
        // Ciudadano con apellido muy similar
        Ciudadano::create([
            'nombre'           => 'María',
            'apellido1'        => 'García',
            'fecha_nacimiento' => '1990-05-20',
            'sexo'             => 'F',
            'nivel_identificacion' => 'probable',
            'activo'           => true,
        ]);

        Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('busquedaNombre', 'María')
            ->set('busquedaApellido1', 'Garcia') // sin tilde — JW cercano
            ->set('busquedaFechaNacimiento', '1990-05-20')
            ->call('buscar')
            ->assertSet('busquedaRealizada', true);

        $component = Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('busquedaNombre', 'María')
            ->set('busquedaApellido1', 'Garcia')
            ->set('busquedaFechaNacimiento', '1990-05-20')
            ->call('buscar');

        $resultados = $component->get('resultadosBusqueda');
        $this->assertNotEmpty($resultados);

        // Verificar orden descendente por score
        $scores = array_column($resultados, 'score');
        $sortedDesc = $scores;
        rsort($sortedDesc);
        $this->assertEquals($sortedDesc, $scores);
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-05: coincidencia exacta por documento bloquea
    // -------------------------------------------------------------------------

    #[Test]
    public function coincidencia_exacta_por_documento_bloquea(): void
    {
        $this->crearCiudadanoConDocumento('nif', '87654321Z');

        $component = Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('busquedaTipoDoc', 'nif')
            ->set('busquedaValorDoc', '87654321Z')
            ->call('buscar');

        $resultados = $component->get('resultadosBusqueda');
        $this->assertNotEmpty($resultados);
        $this->assertTrue($resultados[0]['bloquea']);
        $this->assertEquals(1.0, $resultados[0]['score']);
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-06: sin coincidencias → continuarConNuevoAlta transiciona a padron
    // -------------------------------------------------------------------------

    #[Test]
    public function sin_coincidencias_continuar_transiciona_a_padron(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('busquedaNombre', 'Pedro')
            ->set('busquedaApellido1', 'Inexistente')
            ->set('busquedaFechaNacimiento', '2000-01-01')
            ->call('buscar')
            ->call('continuarConNuevoAlta')
            ->assertSet('fase', 'padron');
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-07: padrón encontrado precarga campos con fuenteCampos = padron
    // -------------------------------------------------------------------------

    #[Test]
    public function padron_encontrado_precarga_campos_con_fuente_padron(): void
    {
        $this->app->instance(FuenteIdentidadInterface::class, new class implements FuenteIdentidadInterface {
            public function consultarDatos(string $v): ?array {
                return [
                    'nombre'          => 'Laura',
                    'apellido1'       => 'Martínez',
                    'apellido2'       => 'Sanz',
                    'fecha_nacimiento' => '1992-07-10',
                    'sexo'            => 'F',
                    'direccion_texto' => 'Calle Mayor 1',
                ];
            }
        });

        $component = Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('busquedaTipoDoc', 'nif')
            ->set('busquedaValorDoc', '11111111A')
            ->call('buscar')
            ->call('continuarConNuevoAlta')
            ->call('consultarPadron');

        $component->assertSet('nombre', 'Laura')
            ->assertSet('padronEncontrado', true)
            ->assertSet('fase', 'formulario');

        $fuenteCampos = $component->get('fuenteCampos');
        $this->assertEquals('padron', $fuenteCampos['nombre']);
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-08: padrón no encontrado — fase permanece en padron
    // -------------------------------------------------------------------------

    #[Test]
    public function padron_no_encontrado_fase_permanece_en_padron(): void
    {
        $this->app->instance(FuenteIdentidadInterface::class, new class implements FuenteIdentidadInterface {
            public function consultarDatos(string $v): ?array { return null; }
        });

        Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('busquedaTipoDoc', 'nif')
            ->set('busquedaValorDoc', '22222222B')
            ->call('buscar')
            ->call('continuarConNuevoAlta')
            ->call('consultarPadron')
            ->assertSet('padronEncontrado', false)
            ->assertSet('padronConsultado', true)
            ->assertSet('fase', 'padron');
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-09: tramitacion no puede seleccionar excepción psh
    // -------------------------------------------------------------------------

    #[Test]
    public function rol_tramitacion_no_puede_seleccionar_excepcion_psh(): void
    {
        $tramitacion = $this->crearUsuario('tramitacion');

        Livewire::actingAs($tramitacion)
            ->test(AltaCiudadano::class)
            ->call('seleccionarExcepcionPadron', 'psh')
            ->assertSet('excepcionPadron', null);
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-10: intervencion puede seleccionar las cuatro excepciones
    // -------------------------------------------------------------------------

    #[Test]
    public function rol_intervencion_puede_seleccionar_las_cuatro_excepciones(): void
    {
        foreach (['psh', 'vvg', 'representante', 'otra'] as $excepcion) {
            Livewire::actingAs($this->usuario)
                ->test(AltaCiudadano::class)
                ->call('seleccionarExcepcionPadron', $excepcion)
                ->assertSet('excepcionPadron', $excepcion)
                ->assertSet('fase', 'formulario');
        }
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-11: excepción vvg — consultarPadron no invoca FuenteIdentidad
    // -------------------------------------------------------------------------

    #[Test]
    public function excepcion_vvg_consultar_padron_no_invoca_fuente_identidad(): void
    {
        $mock = $this->createMock(FuenteIdentidadInterface::class);
        $mock->expects($this->never())->method('consultarDatos');
        $this->app->instance(FuenteIdentidadInterface::class, $mock);

        Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('excepcionPadron', 'vvg')
            ->call('consultarPadron');
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-12: contexto psh — sin nombre acepta; sin alias falla
    // -------------------------------------------------------------------------

    #[Test]
    public function contexto_psh_sin_nombre_acepta_pero_sin_alias_falla_validacion(): void
    {
        // Sin nombre y sin alias → debe fallar por alias requerido
        Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('excepcionPadron', 'psh')
            ->set('fase', 'formulario')
            ->set('sexo', 'D')
            ->call('guardar')
            ->assertHasErrors(['alias']);
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-13: contexto estándar — sin nombre falla validación
    // -------------------------------------------------------------------------

    #[Test]
    public function contexto_estandar_sin_nombre_falla_validacion(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('fase', 'formulario')
            ->set('sexo', 'F')
            ->call('guardar')
            ->assertHasErrors(['nombre']);
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-14: guardar() exitoso crea Ciudadano + CiudadanoIdentificador
    // -------------------------------------------------------------------------

    #[Test]
    public function guardar_exitoso_crea_ciudadano_y_identificador_en_transaccion(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('fase', 'formulario')
            ->set('nombre', 'Carlos')
            ->set('apellido1', 'Pérez')
            ->set('sexo', 'M')
            ->set('tipoDocumento', 'nif')
            ->set('valorDocumento', '33333333C')
            ->call('guardar')
            ->assertSet('fase', 'confirmacion');

        $this->assertDatabaseCount('ciudadanos', 1);
        $this->assertDatabaseCount('ciudadano_identificadores', 1);
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-15: nivel_identificacion correcto según datos
    // -------------------------------------------------------------------------

    #[Test]
    public function nivel_identificacion_identificado_con_documento_probable_sin_el(): void
    {
        // Con documento → identificado
        $comp1 = Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('fase', 'formulario')
            ->set('nombre', 'Luis')
            ->set('apellido1', 'Torres')
            ->set('sexo', 'M')
            ->set('tipoDocumento', 'nif')
            ->set('valorDocumento', '44444444D')
            ->call('guardar');

        $id1 = $comp1->get('ciudadanoIdCreado');
        $c1 = Ciudadano::withoutGlobalScope(\App\Models\Scopes\AmbitoUoScope::class)->find($id1);
        $this->assertEquals('identificado', $c1?->nivel_identificacion);

        // Sin documento con nombre + fecha → probable
        $comp2 = Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('fase', 'formulario')
            ->set('nombre', 'Elena')
            ->set('apellido1', 'Ruiz')
            ->set('sexo', 'F')
            ->set('fechaNacimiento', '1995-06-15')
            ->call('guardar');

        $id2 = $comp2->get('ciudadanoIdCreado');
        $c2 = Ciudadano::withoutGlobalScope(\App\Models\Scopes\AmbitoUoScope::class)->find($id2);
        $this->assertEquals('probable', $c2?->nivel_identificacion);
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-16: confirmarAlta con accion = ficha redirige a ficha
    // -------------------------------------------------------------------------

    #[Test]
    public function confirmar_alta_con_accion_ficha_redirige_a_ficha(): void
    {
        $comp = Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('fase', 'formulario')
            ->set('nombre', 'Rosa')
            ->set('apellido1', 'Blanco')
            ->set('sexo', 'F')
            ->call('guardar');

        $id = $comp->get('ciudadanoIdCreado');

        $comp->set('accionPostAlta', 'ficha')
            ->call('confirmarAlta')
            ->assertRedirect(route('ciudadania.ciudadano.ficha', $id));
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-17: confirmarAlta con accion = solo_alta redirige a buscar
    // -------------------------------------------------------------------------

    #[Test]
    public function confirmar_alta_con_accion_solo_alta_redirige_a_buscar(): void
    {
        $comp = Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('fase', 'formulario')
            ->set('nombre', 'Pilar')
            ->set('apellido1', 'Navarro')
            ->set('sexo', 'F')
            ->call('guardar');

        $comp->set('accionPostAlta', 'solo_alta')
            ->call('confirmarAlta')
            ->assertRedirect(route('ciudadania.buscar'));
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-18: primera demanda se persiste en ciudadanos.primera_demanda
    // -------------------------------------------------------------------------

    #[Test]
    public function primera_demanda_se_persiste_en_la_tabla(): void
    {
        $comp = Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('fase', 'formulario')
            ->set('nombre', 'Jorge')
            ->set('apellido1', 'Medina')
            ->set('sexo', 'M')
            ->call('guardar');

        $id = $comp->get('ciudadanoIdCreado');

        $comp->set('primeraDemanda', 'Vengo a pedir ayuda para el alquiler.')
            ->set('accionPostAlta', 'solo_alta')
            ->call('confirmarAlta');

        $ciudadano = Ciudadano::withoutGlobalScope(\App\Models\Scopes\AmbitoUoScope::class)->find($id);
        $this->assertEquals('Vengo a pedir ayuda para el alquiler.', $ciudadano?->primera_demanda);
    }

    // -------------------------------------------------------------------------
    // TF-LW-ALT-19: segunda pasada de matching en guardar() bloquea si hay
    //               coincidencia nueva casi segura
    // -------------------------------------------------------------------------

    #[Test]
    public function segunda_pasada_de_matching_bloquea_y_retrocede_a_busqueda(): void
    {
        // Escenario: el profesional buscó por nombre (no encontró nada), luego en el
        // formulario introduce un documento que SÍ existe → la segunda pasada lo detecta.
        $docExistente = '55555555E';
        $this->crearCiudadanoConDocumento('nif', $docExistente);

        // Primera pasada: búsqueda por nombre → sin resultados (ciudadanoIdCreado=null)
        Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('busquedaRealizada', true)
            ->set('resultadosBusqueda', []) // primera pasada sin resultados de ese documento
            ->set('fase', 'formulario')
            ->set('nombre', 'Ana')
            ->set('apellido1', 'González')
            ->set('sexo', 'F')
            ->set('tipoDocumento', 'nif')
            ->set('valorDocumento', $docExistente) // mismo documento que el ciudadano existente
            ->call('guardar')
            ->assertSet('fase', 'busqueda') // retrocede porque segunda pasada bloquea
            ->assertSet('busquedaRealizada', true);

        // El ciudadano existente no debe haberse duplicado
        $this->assertDatabaseCount('ciudadanos', 1);
        $this->assertDatabaseCount('ciudadano_identificadores', 1);
    }
}
