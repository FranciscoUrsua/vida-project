<?php

namespace Modules\Ciudadania\Tests\Feature\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Http\Livewire\AltaCiudadano;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Ciudadania\Models\CiudadanoPrestacionResumen;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales de la Ficha del Ciudadano.
 *
 * TF-LW-FIC-01 a TF-LW-FIC-16
 *
 * @see docs/instrucciones-cli/instrucciones-cli-ficha-ciudadano.md §Tarea 5
 */
class FichaCiudadanoPageTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private UnidadOrganizativa $uo;

    private Ciudadano $ciudadano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Test Ficha',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->usuario = $this->crearUsuarioConRol('intervencion');

        $this->ciudadano = Ciudadano::create([
            'nombre' => 'Ana',
            'apellido1' => 'Martínez',
            'apellido2' => 'López',
            'sexo' => 'M',
            'activo' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Crea un usuario con el rol indicado y lo adscribe a la UO de test.
     */
    private function crearUsuarioConRol(string $rol): User
    {
        $user = User::create([
            'name' => "Test {$rol} ".uniqid(),
            'email' => "{$rol}-".uniqid().'@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $user->assignRole($rol);
        UsuarioUo::create([
            'usuario_id' => $user->id,
            'unidad_organizativa_id' => $this->uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        return $user;
    }

    // -------------------------------------------------------------------------
    // Acceso — TF-LW-FIC-01 a TF-LW-FIC-03
    // -------------------------------------------------------------------------

    /**
     * TF-LW-FIC-01 — Componente no accesible sin autenticación → 302.
     */
    #[Test]
    public function componente_no_accesible_sin_autenticacion(): void
    {
        $this->get('/ciudadania/ciudadano/'.$this->ciudadano->id)
            ->assertRedirect();
    }

    /**
     * TF-LW-FIC-02 — Los cuatro roles autorizados pueden montar el componente.
     */
    #[Test]
    public function roles_autorizados_pueden_montar_el_componente(): void
    {
        foreach (['intervencion', 'tramitacion', 'consulta_basica', 'supervision'] as $rol) {
            $user = $this->crearUsuarioConRol($rol);
            Livewire::actingAs($user)
                ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
                ->assertOk()
                ->assertHasNoErrors();
        }
    }

    /**
     * TF-LW-FIC-03 — Usuario sin ninguno de los cuatro roles → 403.
     */
    #[Test]
    public function usuario_sin_rol_autorizado_recibe_403(): void
    {
        $userSinRol = User::create([
            'name' => 'Sin Rol Test',
            'email' => 'sinrol@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);

        Livewire::actingAs($userSinRol)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Permisos de edición — TF-LW-FIC-04 a TF-LW-FIC-05
    // -------------------------------------------------------------------------

    /**
     * TF-LW-FIC-04 — puedeEditar() devuelve true para tramitacion, false para supervision.
     * Se verifica mediante la presencia o ausencia del botón "Editar datos" en la vista.
     */
    #[Test]
    public function puede_editar_es_true_para_tramitacion_false_para_supervision(): void
    {
        $tramitacion = $this->crearUsuarioConRol('tramitacion');
        $supervision = $this->crearUsuarioConRol('supervision');

        Livewire::actingAs($tramitacion)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->assertSee('Editar datos');

        Livewire::actingAs($supervision)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->assertDontSee('Editar datos');
    }

    /**
     * TF-LW-FIC-05 — activarEdicion() con rol supervision no cambia modoEdicion.
     */
    #[Test]
    public function activar_edicion_con_supervision_no_cambia_modo_edicion(): void
    {
        $supervision = $this->crearUsuarioConRol('supervision');

        Livewire::actingAs($supervision)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->assertSet('modoEdicion', false)
            ->call('activarEdicion')
            ->assertSet('modoEdicion', false);
    }

    // -------------------------------------------------------------------------
    // Guardar datos — TF-LW-FIC-06 a TF-LW-FIC-08
    // -------------------------------------------------------------------------

    /**
     * TF-LW-FIC-06 — guardar() actualiza los campos del ciudadano y modoEdicion vuelve a false.
     */
    #[Test]
    public function guardar_actualiza_ciudadano_y_desactiva_modo_edicion(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->call('activarEdicion')
            ->assertSet('modoEdicion', true)
            ->set('sexo', 'H')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('modoEdicion', false);

        $this->assertDatabaseHas('ciudadanos', [
            'id' => $this->ciudadano->id,
            'sexo' => 'H',
        ]);
    }

    /**
     * TF-LW-FIC-07 — guardar() con rol supervision no actualiza el ciudadano.
     */
    #[Test]
    public function guardar_con_supervision_no_actualiza_el_ciudadano(): void
    {
        $supervision = $this->crearUsuarioConRol('supervision');

        Livewire::actingAs($supervision)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->set('sexo', 'H')
            ->call('guardar')
            ->assertSet('modoEdicion', false);

        // El campo sexo original era 'M' — no debe haber cambiado
        $this->assertDatabaseHas('ciudadanos', [
            'id' => $this->ciudadano->id,
            'sexo' => 'M',
        ]);
    }

    /**
     * TF-LW-FIC-08 — guardar() sin nombre falla validación.
     */
    #[Test]
    public function guardar_sin_nombre_falla_validacion(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->call('activarEdicion')
            ->set('nombre', '')
            ->call('guardar')
            ->assertHasErrors(['nombre']);
    }

    // -------------------------------------------------------------------------
    // Documentos — TF-LW-FIC-09 a TF-LW-FIC-10
    // -------------------------------------------------------------------------

    /**
     * TF-LW-FIC-09 — guardarDocumento() crea un nuevo CiudadanoIdentificador y cierra el anterior.
     */
    #[Test]
    public function guardar_documento_crea_nuevo_y_cierra_anterior(): void
    {
        // Crear documento activo previo
        CiudadanoIdentificador::create([
            'ciudadano_id' => $this->ciudadano->id,
            'tipo' => 'nif',
            'valor' => '12345678A',
            'fecha_inicio' => today()->subYear()->toDateString(),
            'verificado' => false,
            'fuente' => 'manual',
        ]);

        Livewire::actingAs($this->usuario)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->call('abrirModalDocumento')
            ->assertSet('modalDocumento', true)
            ->set('nuevoTipoDocumento', 'nie')
            ->set('nuevoValorDocumento', 'X1234567L')
            ->call('guardarDocumento')
            ->assertHasNoErrors()
            ->assertSet('modalDocumento', false);

        // El nuevo documento debe existir
        $this->assertDatabaseHas('ciudadano_identificadores', [
            'ciudadano_id' => $this->ciudadano->id,
            'tipo' => 'nie',
            'fecha_fin' => null,
        ]);

        // El anterior debe tener fecha_fin
        $this->assertDatabaseMissing('ciudadano_identificadores', [
            'ciudadano_id' => $this->ciudadano->id,
            'tipo' => 'nif',
            'fecha_fin' => null,
        ]);
    }

    /**
     * TF-LW-FIC-10 — guardarDocumento() con rol supervision no crea documento.
     */
    #[Test]
    public function guardar_documento_con_supervision_no_crea_documento(): void
    {
        $supervision = $this->crearUsuarioConRol('supervision');

        Livewire::actingAs($supervision)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->set('nuevoTipoDocumento', 'nif')
            ->set('nuevoValorDocumento', '87654321B')
            ->call('guardarDocumento');

        $this->assertDatabaseMissing('ciudadano_identificadores', [
            'ciudadano_id' => $this->ciudadano->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Historia social — TF-LW-FIC-11 a TF-LW-FIC-13
    // -------------------------------------------------------------------------

    /**
     * TF-LW-FIC-11 — Vista renderiza banner de historia social si existe.
     */
    #[Test]
    public function vista_renderiza_banner_si_existe_historia_social(): void
    {
        HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => $this->ciudadano->id,
            'unidad_organizativa_id' => $this->uo->id,
            'estado' => 'abierta',
        ]);

        Livewire::actingAs($this->usuario)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->assertSee('Historia social activa');
    }

    /**
     * TF-LW-FIC-12 — Vista no renderiza banner si no existe historia social.
     */
    #[Test]
    public function vista_no_renderiza_banner_si_no_existe_historia_social(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->assertDontSee('Historia social activa');
    }

    /**
     * TF-LW-FIC-13 — Enlace "Ir a HS" es navegable para intervencion, no navegable para tramitacion.
     */
    #[Test]
    public function enlace_ir_a_hs_navegable_solo_para_intervencion(): void
    {
        $hs = HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => $this->ciudadano->id,
            'unidad_organizativa_id' => $this->uo->id,
            'estado' => 'abierta',
        ]);

        $urlHs = route('intervencion.ciudadano.show', $hs);

        // intervencion: el enlace debe aparecer como <a> con href
        Livewire::actingAs($this->usuario)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->assertSeeHtml($urlHs);

        // tramitacion: el enlace NO debe aparecer como href (es un <span> no navegable)
        $tramitacion = $this->crearUsuarioConRol('tramitacion');
        Livewire::actingAs($tramitacion)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->assertDontSeeHtml($urlHs);
    }

    // -------------------------------------------------------------------------
    // Prestaciones — TF-LW-FIC-14 a TF-LW-FIC-15
    // -------------------------------------------------------------------------

    /**
     * TF-LW-FIC-14 — Widget de prestaciones no aparece si la tabla está vacía.
     */
    #[Test]
    public function widget_prestaciones_no_aparece_si_no_hay_registros(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->assertDontSee('Otras prestaciones');
    }

    /**
     * TF-LW-FIC-15 — Widget de prestaciones muestra máximo 4 registros aunque haya más.
     */
    #[Test]
    public function widget_prestaciones_muestra_maximo_4_registros(): void
    {
        foreach (range(1, 5) as $i) {
            CiudadanoPrestacionResumen::create([
                'ciudadano_id' => $this->ciudadano->id,
                'modulo_origen' => 'centros',
                'origen_id' => $i,
                'tipo' => 'actividad_centro',
                'descripcion' => "Taller número {$i}",
                'estado' => 'activo',
                'fecha_inicio' => today()->subDays($i)->toDateString(),
            ]);
        }

        $html = Livewire::actingAs($this->usuario)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->assertSee('Otras prestaciones')
            ->html();

        // Debe aparecer el quinto taller (el de mayor fecha_inicio - $5) en un máximo de 4.
        // El scope recientes(4) devuelve los 4 más relevantes por estado y fecha.
        // Al menos deben aparecer Taller 1, 2, 3, 4 y NO Taller 5 (el más antiguo).
        $this->assertStringContainsString('Taller número 1', $html);
        $this->assertStringContainsString('Taller número 4', $html);
        $this->assertStringNotContainsString('Taller número 5', $html);
    }

    // -------------------------------------------------------------------------
    // Integración con AltaCiudadano — TF-LW-FIC-16
    // -------------------------------------------------------------------------

    /**
     * TF-LW-FIC-16 — confirmarAlta() con acción 'ficha' en AltaCiudadano redirige a ciudadania.ciudadano.ficha.
     */
    #[Test]
    public function confirmar_alta_con_accion_ficha_redirige_a_ficha_ciudadano(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('fase', 'confirmacion')
            ->set('ciudadanoIdCreado', $this->ciudadano->id)
            ->set('accionPostAlta', 'ficha')
            ->call('confirmarAlta')
            ->assertRedirect(route('ciudadania.ciudadano.ficha', $this->ciudadano->id));
    }
}
