<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Escalas\Enums\EstadoPase;
use Modules\Escalas\Models\PaseEscala;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\RegistrarValoracionPage;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\TipoValoracion;
use Modules\Intervencion\Models\Valoracion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del componente Livewire CiudadanoPage.
 *
 * TF-LW-CIU-01 a TF-LW-CIU-23
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega3.md §6
 */
class CiudadanoPageTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    private User $usuario;

    private Ciudadano $ciudadano;

    private HistoriaSocial $historia;

    private PlanDeIntervencion $piso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Test CiudadanoPage',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->usuario = User::create([
            'name' => 'TSR Prueba',
            'email' => 'ciudadano-page@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);

        $this->usuario->assignRole('intervencion');

        UsuarioUo::create([
            'usuario_id' => $this->usuario->id,
            'unidad_organizativa_id' => $this->uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        $this->ciudadano = Ciudadano::factory()->create();

        $this->historia = HistoriaSocial::create([
            'ciudadano_id' => $this->ciudadano->id,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $this->piso = PlanDeIntervencion::factory()->create([
            'historia_id' => $this->historia->id,
            'profesional_responsable_id' => $this->usuario->id,
            'tipo' => TipoPlan::GeneralAsp,
            'estado' => EstadoPlan::Activo,
        ]);
    }

    /**
     * Crea un apunte en el plan activo de la Historia Social.
     *
     * @param array<string, mixed> $attrs
     */
    private function crearApunte(array $attrs = []): Apunte
    {
        return Apunte::factory()->create(array_merge([
            'historia_id' => $this->historia->id,
            'plan_id'     => $this->piso->id,
            'autor_id'    => $this->usuario->id,
            'tipo'        => TipoApunte::Anotacion,
            'visibilidad' => VisibilidadApunte::Profesionales,
        ], $attrs));
    }

    // -------------------------------------------------------------------------
    // Acceso
    // -------------------------------------------------------------------------

    /**
     * TF-LW-CIU-01 — La ruta aplica la policy: usuario sin acceso recibe 403.
     */
    #[Test]
    public function ruta_aplica_policy_y_devuelve_403_sin_acceso(): void
    {
        $usuarioSinPermiso = User::create([
            'name' => 'Sin Permiso',
            'email' => 'sinpermiso@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);

        $this->actingAs($usuarioSinPermiso)
            ->get("/intervencion/ciudadano/{$this->historia->id}")
            ->assertForbidden();
    }

    /**
     * TF-LW-CIU-02 — El componente monta con la historia inyectada correctamente.
     */
    #[Test]
    public function monta_con_historia_correctamente(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->assertSet('historia.id', $this->historia->id)
            ->assertSet('filtroHS', 'todos')
            ->assertSet('ucExpandida', false)
            ->assertSet('herramientaActiva', null);
    }

    /**
     * TF-LW-CIU-03 — getApuntesHSProperty devuelve solo apuntes visibles para el usuario.
     */
    #[Test]
    public function apuntes_hs_devuelve_solo_visibles(): void
    {
        $this->crearApunte(['visibilidad' => VisibilidadApunte::Profesionales]);
        $this->crearApunte(['visibilidad' => VisibilidadApunte::Profesionales]);

        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);

        $this->assertCount(2, $componente->get('apuntesHS'));
    }

    /**
     * TF-LW-CIU-04 — Apunte privado de otro profesional no aparece en el timeline.
     */
    #[Test]
    public function apunte_privado_de_otro_profesional_no_aparece(): void
    {
        $otro = User::create([
            'name' => 'Otro Prof',
            'email' => 'otro-prof@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);

        $this->crearApunte(['autor_id' => $otro->id, 'visibilidad' => VisibilidadApunte::Privada]);
        $this->crearApunte(['visibilidad' => VisibilidadApunte::Profesionales]);

        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);

        $this->assertCount(1, $componente->get('apuntesHS'));
    }

    /**
     * TF-LW-CIU-05 — filtroHS 'plan' devuelve solo apuntes de tipo plan_intervencion.
     */
    #[Test]
    public function filtro_plan_devuelve_solo_tipo_plan_intervencion(): void
    {
        $this->crearApunte(['tipo' => TipoApunte::PlanIntervencion]);
        $this->crearApunte(['tipo' => TipoApunte::Entrevista]);
        $this->crearApunte(['tipo' => TipoApunte::Anotacion]);

        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->call('setFiltroHS', 'plan');

        $apuntes = $componente->get('apuntesHS');
        $this->assertCount(1, $apuntes);
        $this->assertEquals(TipoApunte::PlanIntervencion, $apuntes->first()->tipo);
    }

    /**
     * TF-LW-CIU-06 — filtroHS 'entrevista' devuelve solo apuntes de tipo entrevista.
     */
    #[Test]
    public function filtro_entrevista_devuelve_solo_tipo_entrevista(): void
    {
        $this->crearApunte(['tipo' => TipoApunte::Entrevista]);
        $this->crearApunte(['tipo' => TipoApunte::Entrevista]);
        $this->crearApunte(['tipo' => TipoApunte::Anotacion]);

        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->call('setFiltroHS', 'entrevista');

        $apuntes = $componente->get('apuntesHS');
        $this->assertCount(2, $apuntes);
        $this->assertTrue($apuntes->every(fn ($a) => $a->tipo === TipoApunte::Entrevista));
    }

    /**
     * TF-LW-CIU-07 — toggleApunte alterna el estado expandido del apunte.
     */
    #[Test]
    public function toggle_apunte_alterna_expandido(): void
    {
        $apunte = $this->crearApunte();

        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);

        $this->assertFalse($componente->get('apuntesExpandidos')[$apunte->id] ?? false);

        $componente->call('toggleApunte', $apunte->id);
        $this->assertTrue($componente->get('apuntesExpandidos')[$apunte->id]);

        $componente->call('toggleApunte', $apunte->id);
        $this->assertFalse($componente->get('apuntesExpandidos')[$apunte->id]);
    }

    /**
     * TF-LW-CIU-08 — toggleUC alterna la visibilidad de la unidad de convivencia.
     */
    #[Test]
    public function toggle_uc_alterna_expandida(): void
    {
        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);

        $this->assertFalse($componente->get('ucExpandida'));
        $componente->call('toggleUC');
        $this->assertTrue($componente->get('ucExpandida'));
        $componente->call('toggleUC');
        $this->assertFalse($componente->get('ucExpandida'));
    }

    /**
     * TF-LW-CIU-09 — pisoActivo devuelve el plan general_asp activo más reciente.
     */
    #[Test]
    public function piso_activo_devuelve_plan_activo(): void
    {
        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);

        $this->assertNotNull($componente->get('pisoActivo'));
        $this->assertEquals($this->piso->id, $componente->get('pisoActivo')->id);
    }

    /**
     * TF-LW-CIU-10 — pisoActivo devuelve null si no hay plan activo.
     */
    #[Test]
    public function piso_activo_null_sin_plan_activo(): void
    {
        $historiaVacia = HistoriaSocial::create([
            'ciudadano_id' => Ciudadano::factory()->create()->id,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $historiaVacia]);

        $this->assertNull($componente->get('pisoActivo'));
    }

    // -------------------------------------------------------------------------
    // Herramientas inline
    // -------------------------------------------------------------------------

    /**
     * TF-LW-CIU-11 — guardarEntrevista crea Entrevista y Apunte de tipo entrevista.
     */
    #[Test]
    public function guardar_entrevista_crea_entrevista_y_apunte(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->set('formEntrevista.tipo', 'seguimiento')
            ->set('formEntrevista.modalidad', 'presencial')
            ->set('formEntrevista.notas', 'Notas de la entrevista.')
            ->call('guardarEntrevista');

        $this->assertDatabaseHas('entrevistas', [
            'historia_id' => $this->historia->id,
            'profesional_id' => $this->usuario->id,
        ]);

        $this->assertDatabaseHas('plan_apuntes', [
            'plan_id' => $this->piso->id,
            'tipo' => TipoApunte::Entrevista->value,
            'autor_id' => $this->usuario->id,
        ]);
    }

    /**
     * TF-LW-CIU-12 — guardarEntrevista con programar_seguimiento actualiza fecha_siguiente_seguimiento.
     */
    #[Test]
    public function guardar_entrevista_con_seguimiento_crea_seguimiento(): void
    {
        $fechaSig = today()->addDays(14)->toDateString();

        Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->set('formEntrevista.programar_seguimiento', true)
            ->set('formEntrevista.fecha_siguiente_seguimiento', $fechaSig)
            ->call('guardarEntrevista');

        $this->assertDatabaseHas('seguimientos_plan', [
            'plan_id' => $this->piso->id,
            'fecha_siguiente_seguimiento' => $fechaSig,
        ]);
    }

    /**
     * TF-LW-CIU-13 — guardarAnotacion con visibilidad privada crea apunte con autor correcto.
     */
    #[Test]
    public function guardar_anotacion_privada_crea_apunte_con_autor_correcto(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->set('formAnotacion.contenido', 'Nota privada de prueba.')
            ->set('formAnotacion.visibilidad', 'privada')
            ->call('guardarAnotacion');

        $this->assertDatabaseHas('plan_apuntes', [
            'plan_id' => $this->piso->id,
            'tipo' => TipoApunte::Anotacion->value,
            'visibilidad' => VisibilidadApunte::Privada->value,
            'autor_id' => $this->usuario->id,
            'contenido' => 'Nota privada de prueba.',
        ]);
    }

    /**
     * TF-LW-CIU-14 — Apunte privado creado no es visible para otro profesional en la misma HS.
     */
    #[Test]
    public function apunte_privado_no_visible_para_otro_profesional(): void
    {
        $otro = User::create([
            'name' => 'Otro Prof 14',
            'email' => 'otro14@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $otro->assignRole('intervencion');
        UsuarioUo::create([
            'usuario_id' => $otro->id,
            'unidad_organizativa_id' => $this->uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        // Crear apunte privado como el usuario principal
        Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->set('formAnotacion.contenido', 'Solo yo.')
            ->set('formAnotacion.visibilidad', 'privada')
            ->call('guardarAnotacion');

        // El otro profesional no lo ve
        $comp = Livewire::actingAs($otro)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);

        $this->assertCount(0, $comp->get('apuntesHS'));
    }

    /**
     * TF-LW-CIU-15 — crearDerivacion crea Apunte de tipo derivacion.
     */
    #[Test]
    public function crear_derivacion_crea_apunte_de_tipo_derivacion(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->set('formDerivacion.urgencia', 'preferente')
            ->set('formDerivacion.motivo', 'Motivo de prueba.')
            ->call('crearDerivacion');

        $this->assertDatabaseHas('plan_apuntes', [
            'plan_id' => $this->piso->id,
            'tipo' => TipoApunte::Derivacion->value,
            'autor_id' => $this->usuario->id,
        ]);
    }

    /**
     * TF-LW-CIU-16 — guardarGestion crea Apunte con los campos correctos.
     */
    #[Test]
    public function guardar_gestion_crea_apunte(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->set('formGestion.tipo_gestion', 'coordinacion')
            ->set('formGestion.recurso_interlocutor', 'Servicio de Dependencia')
            ->set('formGestion.descripcion', 'Coordinación sobre ayuda a domicilio.')
            ->call('guardarGestion');

        $this->assertDatabaseHas('plan_apuntes', [
            'plan_id' => $this->piso->id,
            'tipo' => TipoApunte::GestionCoordinacion->value,
            'autor_id' => $this->usuario->id,
        ]);
    }

    /**
     * TF-LW-CIU-17 — cancelarHerramienta limpia herramientaActiva.
     */
    #[Test]
    public function cancelar_herramienta_limpia_herramienta_activa(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->call('seleccionarHerramienta', 'entrevista')
            ->assertSet('herramientaActiva', 'entrevista')
            ->call('cancelarHerramienta')
            ->assertSet('herramientaActiva', null);
    }

    /**
     * TF-LW-CIU-18 — Después de guardar cualquier herramienta, herramientaActiva es null.
     */
    #[Test]
    public function despues_de_guardar_herramienta_activa_es_null(): void
    {
        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->call('seleccionarHerramienta', 'anotacion')
            ->set('formAnotacion.contenido', 'Prueba de cierre.')
            ->call('guardarAnotacion');

        $this->assertNull($componente->get('herramientaActiva'));
    }

    // -------------------------------------------------------------------------
    // Herramientas a pantalla completa
    // -------------------------------------------------------------------------

    /**
     * TF-LW-CIU-19 — guardarValoracion crea Valoracion y Apunte de tipo valoracion.
     */
    #[Test]
    public function guardar_valoracion_crea_valoracion_y_apunte(): void
    {
        $tipoVal = TipoValoracion::factory()->create();
        $tipoFicha = TipoFicha::factory()->create();

        $page = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);

        $page->instance()->guardarValoracion($tipoFicha->id, ['campo_prueba' => 'valor_prueba']);

        $this->assertDatabaseHas('valoraciones', [
            'historia_id' => $this->historia->id,
            'profesional_id' => $this->usuario->id,
        ]);

        $this->assertDatabaseHas('plan_apuntes', [
            'plan_id' => $this->piso->id,
            'tipo' => TipoApunte::Valoracion->value,
            'autor_id' => $this->usuario->id,
        ]);
    }

    /**
     * TF-LW-CIU-20 — guardarValoracion vincula la Valoracion a la entrevista si se pasa entrevista_id.
     */
    #[Test]
    public function guardar_valoracion_vincula_entrevista(): void
    {
        TipoValoracion::factory()->create();
        $tipoFicha = TipoFicha::factory()->create();

        $entrevista = Entrevista::factory()->create([
            'historia_id' => $this->historia->id,
            'profesional_id' => $this->usuario->id,
        ]);

        $page = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);

        $page->instance()->guardarValoracion($tipoFicha->id, [], $entrevista->id);

        $this->assertDatabaseHas('valoraciones', [
            'historia_id' => $this->historia->id,
            'entrevista_id' => $entrevista->id,
        ]);
    }

    /**
     * TF-LW-CIU-21 — guardarEscala crea PaseEscala con puntuacion_total calculada correctamente.
     */
    #[Test]
    public function guardar_escala_crea_pase_con_score_correcto(): void
    {
        $tipoEscala = TipoEscala::factory()->create();

        $respuestas = [
            'item_1_1' => 10,
            'item_1_2' => 5,
        ];

        $page = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);

        $page->instance()->guardarEscala($tipoEscala->id, $respuestas);

        $pase = PaseEscala::where('tipo_escala_id', $tipoEscala->id)->first();
        $this->assertNotNull($pase);
        $this->assertEquals(15, $pase->score_total);
        $this->assertEquals(EstadoPase::Completado, $pase->estado);
    }

    /**
     * TF-LW-CIU-22 — guardarEscala crea Apunte de tipo escala con la puntuación en el resumen.
     */
    #[Test]
    public function guardar_escala_crea_apunte_con_puntuacion(): void
    {
        $tipoEscala = TipoEscala::factory()->create(['nombre' => 'Barthel Test']);

        $page = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);

        $page->instance()->guardarEscala($tipoEscala->id, ['item_1_1' => 10, 'item_1_2' => 0]);

        $apunte = Apunte::where('tipo', TipoApunte::Escala->value)
            ->where('plan_id', $this->piso->id)
            ->first();

        $this->assertNotNull($apunte);
        $this->assertStringContainsString('10', $apunte->contenido);
    }

    /**
     * TF-LW-CIU-23 — La ruta de valoracion nueva requiere un tipo_ficha_id válido.
     */
    #[Test]
    public function ruta_valoracion_nueva_requiere_tipo_ficha_valido(): void
    {
        // Sin tipo_ficha, el componente monta pero tipoFicha es null
        $componente = Livewire::actingAs($this->usuario)
            ->test(RegistrarValoracionPage::class, [
                'historia' => $this->historia,
                'tipo_ficha' => null,
            ]);

        $this->assertNull($componente->get('tipoFichaId'));
    }

    // -------------------------------------------------------------------------
    // Filtro sugerido y modal de detalle
    // -------------------------------------------------------------------------

    /**
     * TF-LW-CIU-28 — Activar herramienta preselecciona filtro sin aplicarlo.
     */
    #[Test]
    public function activar_herramienta_preselecciona_filtro_sin_aplicarlo(): void
    {
        $this->crearApunte(['tipo' => TipoApunte::Entrevista]);
        $this->crearApunte(['tipo' => TipoApunte::Anotacion]);

        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->call('seleccionarHerramienta', 'entrevista');

        // El filtro sugerido refleja la herramienta activa
        $componente->assertSet('filtroSugerido', 'entrevista');
        // El filtro aplicado no ha cambiado
        $componente->assertSet('filtroHS', 'todos');
        // Los apuntes no están filtrados (se ven todos)
        $this->assertCount(2, $componente->get('apuntesHS'));
    }

    /**
     * TF-LW-CIU-29 — verApunte abre el modal con los datos correctos.
     */
    #[Test]
    public function ver_apunte_abre_modal_con_datos_correctos(): void
    {
        $apunte = $this->crearApunte([
            'tipo' => TipoApunte::Anotacion,
            'contenido' => 'Contenido de prueba',
        ]);

        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->call('verApunte', $apunte->id);

        $componente->assertSet('modalApunteAbierto', true);
        $componente->assertSet('modalApunteId', $apunte->id);
        $componente->assertSet('modalApunteTipo', TipoApunte::Anotacion->value);

        $datos = $componente->get('modalApunteDatos');
        $this->assertArrayHasKey('fecha', $datos);
        $this->assertArrayHasKey('autor', $datos);
        $this->assertArrayHasKey('contenido', $datos);
        $this->assertEquals('Contenido de prueba', $datos['contenido']);
    }

    /**
     * TF-LW-CIU-30 — cerrarModalApunte limpia todo el estado del modal.
     */
    #[Test]
    public function cerrar_modal_limpia_estado(): void
    {
        $apunte = $this->crearApunte();

        $componente = Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->call('verApunte', $apunte->id)
            ->call('cerrarModalApunte');

        $componente->assertSet('modalApunteAbierto', false);
        $componente->assertSet('modalApunteId', null);
        $this->assertEmpty($componente->get('modalApunteDatos'));
    }
}
