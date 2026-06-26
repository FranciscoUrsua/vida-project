<?php

namespace Modules\Ciudadania\Tests\Feature\Livewire;

use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Ciudadania\Models\UnidadConvivencia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales de relaciones entre ciudadanos y UC solo lectura en FichaCiudadanoPage.
 *
 * TF-LW-REL-01 a TF-LW-REL-20 — panel y modal de relaciones.
 * TF-LW-UC-01  a TF-LW-UC-04  — unidad de convivencia en modo solo lectura.
 *
 * @see docs/instrucciones-cli/instrucciones-cli-relaciones.md
 * @see docs/instrucciones-cli/tests-relaciones-uc.md
 */
class RelacionesCiudadanoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private UnidadOrganizativa $uo;

    private Ciudadano $ciudadano;

    /** Slug del tipo padre/madre y su recíproco hijo/a, tal como los crea TipoRelacionSeeder. */
    private string $slugPadre;

    private string $slugHijo;

    private string $slugConyuge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->seed(TipoRelacionSeeder::class);

        $this->uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Test Relaciones',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->usuario = $this->crearUsuarioConRol('intervencion');

        $this->ciudadano = $this->crearCiudadano('Ana', 'Martínez', 'López');

        // Slugs del seeder
        $this->slugPadre = TipoRelacion::where('etiqueta', 'LIKE', '%adre%')->value('slug') ?? 'padre-madre';
        $this->slugHijo = TipoRelacion::where('slug', 'hijo-a')->value('slug') ?? 'hijo-a';
        $this->slugConyuge = TipoRelacion::where('slug', 'conyuge')->value('slug') ?? 'conyuge';
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
            'email' => "{$rol}-".uniqid().'@test.local',
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

    /**
     * Crea un ciudadano con datos mínimos.
     */
    private function crearCiudadano(string $nombre = 'Juan', string $apellido1 = 'García', string $apellido2 = 'López'): Ciudadano
    {
        return Ciudadano::create([
            'nombre' => $nombre,
            'apellido1' => $apellido1,
            'apellido2' => $apellido2,
            'sexo' => 'H',
            'activo' => true,
        ]);
    }

    /**
     * Crea una relación entre dos ciudadanos usando el slug indicado.
     */
    private function crearRelacion(Ciudadano $de, Ciudadano $a, string $tipo, ?string $fechaFin = null): CiudadanoRelacion
    {
        // Desactiva la reciprocidad automática del modelo para crear datos de test controlados
        return CiudadanoRelacion::withoutEvents(fn () => CiudadanoRelacion::create([
            'ciudadano_id' => $de->id,
            'ciudadano_relacionado_id' => $a->id,
            'tipo_relacion' => $tipo,
            'fecha_inicio' => today()->subMonths(3)->toDateString(),
            'fecha_fin' => $fechaFin,
        ]));
    }

    /**
     * Monta la ficha con usuario y ciudadano indicados.
     *
     * @return Testable<FichaCiudadanoPage>
     */
    private function montarFicha(?User $usuario = null, ?Ciudadano $ciudadano = null): Testable
    {
        $this->actingAs($usuario ?? $this->usuario);

        return Livewire::test(FichaCiudadanoPage::class, [
            'ciudadano' => ($ciudadano ?? $this->ciudadano)->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // GRUPO REL — Panel de relaciones
    // -------------------------------------------------------------------------

    /**
     * TF-LW-REL-01 — Panel visible con relaciones vigentes.
     */
    #[Test]
    public function panel_relaciones_visible_con_relaciones_vigentes(): void
    {
        $otroCiudadano = $this->crearCiudadano('Luis', 'García');
        $this->crearRelacion($this->ciudadano, $otroCiudadano, $this->slugConyuge);

        $this->montarFicha($this->usuario)
            ->assertSee('Relaciones')
            ->assertSee($otroCiudadano->nombre_completo);
    }

    /**
     * TF-LW-REL-02 — Panel visible con estado vacío cuando no hay relaciones.
     */
    #[Test]
    public function panel_relaciones_muestra_estado_vacio_sin_relaciones(): void
    {
        $this->montarFicha($this->usuario)
            ->assertSee('Relaciones')
            ->assertSee('Sin relaciones registradas');
    }

    /**
     * TF-LW-REL-03 — Relaciones cerradas no aparecen en la lista principal.
     */
    #[Test]
    public function relaciones_cerradas_no_aparecen_en_lista_principal(): void
    {
        $otro = $this->crearCiudadano('Pedro', 'Ruiz');
        $this->crearRelacion($this->ciudadano, $otro, $this->slugConyuge, today()->subDay()->toDateString());

        $comp = $this->montarFicha($this->usuario);

        $relacionesActivas = $comp->instance()->relacionesActivas;
        $this->assertTrue($relacionesActivas->isEmpty());
        // El nombre del ciudadano relacionado no debe aparecer en las vigentes
        $comp->assertSee('Sin relaciones registradas');
    }

    /**
     * TF-LW-REL-04 — Relaciones cerradas visibles al expandir historial.
     */
    #[Test]
    public function relaciones_cerradas_visibles_al_expandir_historial(): void
    {
        $otro = $this->crearCiudadano('Pedro', 'Ruiz');
        $this->crearRelacion($this->ciudadano, $otro, $this->slugConyuge, today()->subDay()->toDateString());

        $this->montarFicha($this->usuario)
            ->call('toggleHistorialRelaciones')
            ->assertSee($otro->nombre_completo);
    }

    /**
     * TF-LW-REL-05 — Botón "+ Añadir relación" visible para usuario con rol tramitacion/intervencion.
     */
    #[Test]
    public function boton_añadir_visible_para_usuario_con_permiso_editar(): void
    {
        foreach (['intervencion', 'tramitacion'] as $rol) {
            $user = $this->crearUsuarioConRol($rol);
            $this->montarFicha($user)
                ->assertSee('Añadir relación');
        }
    }

    /**
     * TF-LW-REL-06 — Botón "+ Añadir relación" ausente para usuario con rol consulta_basica.
     */
    #[Test]
    public function boton_añadir_ausente_para_consulta_basica(): void
    {
        $user = $this->crearUsuarioConRol('consulta_basica');
        $this->montarFicha($user)
            ->assertDontSee('Añadir relación');
    }

    /**
     * TF-LW-REL-07 — Modal de nueva relación se abre al pulsar el botón.
     */
    #[Test]
    public function modal_nueva_relacion_se_abre(): void
    {
        $this->montarFicha($this->usuario)
            ->call('abrirModalNuevaRelacion')
            ->assertSet('modalRelacionAbierto', true)
            ->assertSet('relacionId', null)
            ->assertSee('Nueva relación');
    }

    /**
     * TF-LW-REL-08 — Crear relación guarda el registro y su recíproco.
     */
    #[Test]
    public function crear_relacion_guarda_registro_y_reciproco(): void
    {
        $hijo = $this->crearCiudadano('Tomás', 'Martínez');

        $this->montarFicha($this->usuario)
            ->call('abrirModalNuevaRelacion')
            ->set('relacionTipo', $this->slugPadre)
            ->call('seleccionarCiudadanoRelacion', $hijo->id)
            ->set('relacionFechaInicio', today()->toDateString())
            ->call('guardarRelacion')
            ->assertSet('modalRelacionAbierto', false)
            ->assertHasNoErrors();

        // Registro directo
        $this->assertDatabaseHas('ciudadano_relaciones', [
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $hijo->id,
            'tipo_relacion' => $this->slugPadre,
            'fecha_fin' => null,
        ]);

        // Registro recíproco (hijo → padre)
        $this->assertDatabaseHas('ciudadano_relaciones', [
            'ciudadano_id' => $hijo->id,
            'ciudadano_relacionado_id' => $this->ciudadano->id,
            'fecha_fin' => null,
        ]);
    }

    /**
     * TF-LW-REL-09 — Relación simétrica genera exactamente dos registros.
     */
    #[Test]
    public function relacion_simetrica_genera_dos_registros(): void
    {
        $conyuge = $this->crearCiudadano('Marta', 'López');

        $this->montarFicha($this->usuario)
            ->call('abrirModalNuevaRelacion')
            ->set('relacionTipo', $this->slugConyuge)
            ->call('seleccionarCiudadanoRelacion', $conyuge->id)
            ->set('relacionFechaInicio', today()->toDateString())
            ->call('guardarRelacion');

        $this->assertSame(2, CiudadanoRelacion::whereNull('fecha_fin')->count());
    }

    /**
     * TF-LW-REL-10 — La relación recíproca aparece en la ficha del otro ciudadano.
     */
    #[Test]
    public function relacion_reciproca_aparece_en_ficha_del_otro(): void
    {
        $hijo = $this->crearCiudadano('Tomás', 'Martínez');

        // Crear relación padre → hijo (el modelo crea la recíproca automáticamente)
        CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $hijo->id,
            'tipo_relacion' => $this->slugPadre,
            'fecha_inicio' => today()->toDateString(),
        ]);

        // La ficha del hijo debe mostrar al padre
        $this->montarFicha($this->usuario, $hijo)
            ->assertSee($this->ciudadano->nombre_completo);
    }

    /**
     * TF-LW-REL-11 — Crear relación sin ciudadano relacionado falla con validación.
     */
    #[Test]
    public function crear_relacion_sin_ciudadano_falla(): void
    {
        $this->montarFicha($this->usuario)
            ->call('abrirModalNuevaRelacion')
            ->set('relacionTipo', $this->slugConyuge)
            ->set('relacionFechaInicio', today()->toDateString())
            ->call('guardarRelacion')
            ->assertHasErrors(['relacionCiudadanoSeleccionado']);

        $this->assertDatabaseCount('ciudadano_relaciones', 0);
    }

    /**
     * TF-LW-REL-12 — Crear relación sin tipo falla con validación.
     */
    #[Test]
    public function crear_relacion_sin_tipo_falla(): void
    {
        $otro = $this->crearCiudadano('Pedro', 'Ruiz');

        $this->montarFicha($this->usuario)
            ->call('abrirModalNuevaRelacion')
            ->call('seleccionarCiudadanoRelacion', $otro->id)
            ->set('relacionFechaInicio', today()->toDateString())
            ->call('guardarRelacion')
            ->assertHasErrors(['relacionTipo']);

        $this->assertDatabaseCount('ciudadano_relaciones', 0);
    }

    /**
     * TF-LW-REL-13 — No se puede crear una relación de un ciudadano consigo mismo.
     */
    #[Test]
    public function no_se_puede_relacionar_ciudadano_consigo_mismo(): void
    {
        $this->montarFicha($this->usuario)
            ->call('abrirModalNuevaRelacion')
            ->set('relacionTipo', $this->slugConyuge)
            ->set('relacionCiudadanoSeleccionado', $this->ciudadano->id)
            ->set('relacionFechaInicio', today()->toDateString())
            ->call('guardarRelacion')
            ->assertHasErrors(['relacionCiudadanoSeleccionado']);

        $this->assertDatabaseCount('ciudadano_relaciones', 0);
    }

    /**
     * TF-LW-REL-14 — Modal de edición se abre con datos precargados.
     */
    #[Test]
    public function modal_edicion_se_abre_con_datos_precargados(): void
    {
        $otro = $this->crearCiudadano('Pedro', 'Ruiz');
        $relacion = $this->crearRelacion($this->ciudadano, $otro, $this->slugConyuge);

        $this->montarFicha($this->usuario)
            ->call('abrirModalEditarRelacion', $relacion->id)
            ->assertSet('modalRelacionAbierto', true)
            ->assertSet('relacionId', $relacion->id)
            ->assertSet('relacionTipo', $this->slugConyuge)
            ->assertSet('relacionCiudadanoSeleccionado', $otro->id)
            ->assertSee('Editar relación')
            ->assertSee('Cerrar relación');
    }

    /**
     * TF-LW-REL-15 — "Cerrar relación" establece fecha_fin en ambos registros.
     */
    #[Test]
    public function cerrar_relacion_establece_fecha_fin_en_ambos_registros(): void
    {
        $otro = $this->crearCiudadano('Pedro', 'Ruiz');
        // Crear con reciprocidad real
        CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $otro->id,
            'tipo_relacion' => $this->slugConyuge,
            'fecha_inicio' => today()->subMonth()->toDateString(),
        ]);

        $relacion = CiudadanoRelacion::where('ciudadano_id', $this->ciudadano->id)->first();

        $this->montarFicha($this->usuario)
            ->call('cerrarRelacion', $relacion->id)
            ->assertSet('modalRelacionAbierto', false);

        // Ambos registros deben tener fecha_fin
        $this->assertDatabaseCount('ciudadano_relaciones', 2);
        $this->assertSame(
            2,
            CiudadanoRelacion::whereNotNull('fecha_fin')->count()
        );
    }

    /**
     * TF-LW-REL-16 — Editar observaciones no crea registro recíproco duplicado.
     */
    #[Test]
    public function editar_observaciones_no_crea_duplicado(): void
    {
        $otro = $this->crearCiudadano('Pedro', 'Ruiz');
        // Crear con reciprocidad real (2 registros)
        CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $otro->id,
            'tipo_relacion' => $this->slugConyuge,
            'fecha_inicio' => today()->subMonth()->toDateString(),
        ]);

        $this->assertSame(2, CiudadanoRelacion::count());

        $relacion = CiudadanoRelacion::where('ciudadano_id', $this->ciudadano->id)->first();

        $this->montarFicha($this->usuario)
            ->call('abrirModalEditarRelacion', $relacion->id)
            ->set('relacionObservaciones', 'Matrimonio civil 2015')
            ->call('guardarRelacion')
            ->assertHasNoErrors();

        // Sigue habiendo exactamente 2 registros
        $this->assertSame(2, CiudadanoRelacion::count());
        $this->assertDatabaseHas('ciudadano_relaciones', [
            'id' => $relacion->id,
            'observaciones' => 'Matrimonio civil 2015',
        ]);
    }

    /**
     * TF-LW-REL-17 — Usuario sin permiso de edición no puede guardar relaciones y no se crea ningún registro.
     */
    #[Test]
    public function usuario_sin_permiso_no_puede_guardar_relacion(): void
    {
        $consulta = $this->crearUsuarioConRol('consulta_basica');
        $otro = $this->crearCiudadano('Pedro', 'Ruiz');

        // Livewire 4 intercepta abort(403) y lo convierte en respuesta de error del componente
        $this->montarFicha($consulta)
            ->set('relacionTipo', $this->slugConyuge)
            ->set('relacionCiudadanoSeleccionado', $otro->id)
            ->set('relacionFechaInicio', today()->toDateString())
            ->call('guardarRelacion')
            ->assertForbidden();

        $this->assertDatabaseCount('ciudadano_relaciones', 0);
    }

    /**
     * TF-LW-REL-18 — El nombre del ciudadano relacionado enlaza a su ficha.
     */
    #[Test]
    public function nombre_relacionado_enlaza_a_su_ficha(): void
    {
        $otro = $this->crearCiudadano('Pedro', 'Ruiz');
        $this->crearRelacion($this->ciudadano, $otro, $this->slugConyuge);

        $fichaUrl = route('ciudadania.ciudadano.ficha', $otro->id);

        $this->montarFicha($this->usuario)
            ->assertSee($fichaUrl, false);
    }

    /**
     * TF-LW-REL-19 — Buscador filtra ciudadanos por nombre y excluye al propio ciudadano.
     */
    #[Test]
    public function buscador_filtra_ciudadanos_y_excluye_al_titular(): void
    {
        $this->crearCiudadano('Marcos', 'Fernández');
        $this->crearCiudadano('Marina', 'Fernández');

        $comp = $this->montarFicha($this->usuario)
            ->call('abrirModalNuevaRelacion')
            ->set('relacionBusqueda', 'Fernández');

        $resultados = $comp->instance()->relacionResultadosBusqueda;

        $this->assertGreaterThanOrEqual(2, $resultados->count());
        $this->assertFalse($resultados->contains('id', $this->ciudadano->id));
        $this->assertLessThanOrEqual(8, $resultados->count());
    }

    /**
     * TF-LW-REL-20 — Relaciones vigentes ordenadas por fecha_inicio descendente.
     */
    #[Test]
    public function relaciones_vigentes_ordenadas_por_fecha_inicio_desc(): void
    {
        $c1 = $this->crearCiudadano('Alfa', 'Uno');
        $c2 = $this->crearCiudadano('Beta', 'Dos');

        // c2 tiene fecha_inicio más reciente
        CiudadanoRelacion::withoutEvents(fn () => CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $c1->id,
            'tipo_relacion' => $this->slugConyuge,
            'fecha_inicio' => today()->subYear()->toDateString(),
        ]));
        CiudadanoRelacion::withoutEvents(fn () => CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $c2->id,
            'tipo_relacion' => $this->slugConyuge,
            'fecha_inicio' => today()->subMonth()->toDateString(),
        ]));

        $comp = $this->montarFicha($this->usuario);

        $relaciones = $comp->instance()->relacionesActivas;
        $this->assertSame($c2->id, $relaciones->first()->ciudadano_relacionado_id);
    }

    // -------------------------------------------------------------------------
    // GRUPO UC — Unidad de convivencia solo lectura
    // -------------------------------------------------------------------------

    /**
     * TF-LW-UC-01 — Panel UC muestra la unidad de convivencia vigente sin botones de edición.
     */
    #[Test]
    public function panel_uc_muestra_convivientes_sin_botones_edicion(): void
    {
        $conviviente = $this->crearCiudadano('Beatriz', 'Sánchez');
        $uc = UnidadConvivencia::factory()->create();
        $uc->agregarMiembro($this->ciudadano->id);
        $uc->agregarMiembro($conviviente->id);

        $this->montarFicha($this->usuario)
            ->assertSee('Unidad de convivencia')
            ->assertSee($conviviente->nombre_completo)
            ->assertDontSee('Añadir conviviente')
            ->assertDontSee('Eliminar miembro');
    }

    /**
     * TF-LW-UC-02 — Panel UC muestra el tipo de relación si existe en ciudadano_relaciones.
     */
    #[Test]
    public function panel_uc_muestra_tipo_relacion_si_existe(): void
    {
        $hijo = $this->crearCiudadano('Tomás', 'Martínez');

        $uc = UnidadConvivencia::factory()->create();
        $uc->agregarMiembro($this->ciudadano->id);
        $uc->agregarMiembro($hijo->id);

        // Relación padre → hijo (sin reciprocidad para test controlado)
        $slugHijo = TipoRelacion::where('slug_reciproco', $this->slugPadre)->value('slug')
            ?? $this->slugHijo;

        $this->crearRelacion($this->ciudadano, $hijo, $this->slugPadre);

        $tipo = TipoRelacion::where('slug', $this->slugPadre)->first();

        $this->montarFicha($this->usuario)
            ->assertSee($tipo->etiqueta ?? $this->slugPadre);
    }

    /**
     * TF-LW-UC-03 — Panel UC no se renderiza si el ciudadano no tiene UC activa.
     */
    #[Test]
    public function panel_uc_no_se_renderiza_sin_uc_activa(): void
    {
        $this->montarFicha($this->usuario)
            ->assertDontSee('Unidad de convivencia');
    }

    /**
     * TF-LW-UC-04 — No existe ningún método público de escritura de UC en FichaCiudadanoPage.
     */
    #[Test]
    public function no_existen_metodos_de_escritura_de_uc(): void
    {
        $prohibidos = ['añadirMiembro', 'eliminarMiembro', 'crearUC', 'agregarMiembro', 'darDeBajaMiembro'];
        $metodos = get_class_methods(FichaCiudadanoPage::class);

        foreach ($prohibidos as $metodo) {
            $this->assertNotContains(
                $metodo,
                $metodos,
                "FichaCiudadanoPage no debe tener el método '{$metodo}' — la gestión de UC es de CiudadanoPage."
            );
        }
    }
}
