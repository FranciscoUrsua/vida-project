<?php

namespace Tests\Feature;

use App\Filament\Pages\DemoWorldsPage;
use App\Filament\Resources\AuditResource;
use App\Filament\Resources\CuadranteMesResource;
use App\Filament\Resources\EstiloInformeResource;
use App\Filament\Resources\ExcepcionProfesionalResource;
use App\Filament\Resources\ParametroInformeResource;
use App\Filament\Resources\PerfilHorarioProfesionalResource;
use App\Filament\Resources\PlantillaInformeResource;
use App\Filament\Resources\ProfesionalResource;
use App\Models\Audit;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use App\Support\Ambito\AmbitoGestionUsuarios;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;
use Modules\Documentos\Models\EstiloInforme;
use Modules\Documentos\Models\ParametroInforme;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ámbito de la supervisión y de `adm_usuarios` en el backoffice (25 nov 2026).
 *
 * - Auditoría de accesos: la supervisión la consulta para todo el sistema.
 * - Entornos demo: solo `adm_sistema`.
 * - Profesionales, perfiles horarios, excepciones y cuadrantes: `adm_usuarios`
 *   solo gestiona los de su UO y las que dependen de ella (AmbitoGestionUsuarios).
 * - Plantillas y estilos de informe: la supervisión edita los de su ámbito y ve los
 *   demás en solo lectura; los parámetros (globales), solo los ve.
 */
class BackofficeAmbitoTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uoPropia;

    private UnidadOrganizativa $uoAjena;

    private Centro $centroPropio;

    private Centro $centroAjeno;

    /** Dirección de centro con supervision, adm_usuarios e intervencion (como dir.ciam). */
    private User $direccion;

    private User $supervisionSola;

    private User $admSistema;

    /**
     * Dos UO hermanas con su centro, y los usuarios del backoffice en la propia.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $raiz = UnidadOrganizativa::create(['nombre' => 'Área', 'tipo' => 'area', 'activa' => true]);
        $this->uoPropia = UnidadOrganizativa::create(['nombre' => 'CIAM propio', 'tipo' => 'centro', 'parent_id' => $raiz->id, 'activa' => true]);
        $this->uoAjena = UnidadOrganizativa::create(['nombre' => 'CSS ajeno', 'tipo' => 'centro', 'parent_id' => $raiz->id, 'activa' => true]);
        $this->centroPropio = $this->centroParaUo($this->uoPropia);
        $this->centroAjeno = $this->centroParaUo($this->uoAjena);

        $this->direccion = $this->usuarioEn($this->uoPropia, 'direccion@vida360.test', ['supervision', 'adm_usuarios', 'intervencion']);
        $this->supervisionSola = $this->usuarioEn($this->uoPropia, 'coord@vida360.test', ['supervision']);
        $this->admSistema = $this->usuarioEn($this->uoPropia, 'admin@vida360.test', ['adm_sistema']);
    }

    /** La supervisión entra en la auditoría de accesos (antes, error SQL) y ve la de todo el sistema. */
    #[Test]
    public function la_supervision_ve_la_auditoria_de_todo_el_sistema(): void
    {
        $deFuera = $this->usuarioEn($this->uoAjena, 'ts.ajena@vida360.test', ['intervencion']);
        Audit::withoutEvents(fn () => Audit::create([
            'user_id' => $deFuera->id, 'accion' => 'ver', 'auditable_type' => User::class, 'auditable_id' => $deFuera->id,
        ]));

        $this->actingAs($this->supervisionSola)->get(AuditResource::getUrl('index'))->assertOk();
        $this->assertTrue(AuditResource::getEloquentQuery()->where('user_id', $deFuera->id)->exists());
    }

    /** Entornos demo: solo adm_sistema, en la página y en sus acciones. [negativo] */
    #[Test]
    public function los_entornos_demo_son_solo_de_adm_sistema(): void
    {
        foreach ([$this->direccion, $this->supervisionSola] as $usuario) {
            $this->actingAs($usuario);
            $this->assertFalse(DemoWorldsPage::canAccess(), $usuario->email);
            $this->get(DemoWorldsPage::getUrl())->assertForbidden();
        }

        $this->actingAs($this->admSistema);
        $this->assertTrue(DemoWorldsPage::canAccess());
    }

    /** adm_usuarios edita los profesionales de su ámbito: por la adscripción del usuario o, sin cuenta, por su UO. [negativo] */
    #[Test]
    public function adm_usuarios_solo_edita_profesionales_de_su_ambito(): void
    {
        $propio = $this->profesional('Propia');
        $this->usuarioEn($this->uoPropia, 'propia@vida360.test', ['intervencion'], $propio);
        $ajeno = $this->profesional('Ajena');
        $this->usuarioEn($this->uoAjena, 'ajena@vida360.test', ['intervencion'], $ajeno);
        $sinCuentaPropio = $this->profesional('Sin cuenta propia', $this->uoPropia);
        $sinCuentaAjeno = $this->profesional('Sin cuenta ajena', $this->uoAjena);

        $this->actingAs($this->direccion);
        $this->assertTrue(ProfesionalResource::canEdit($propio));
        $this->assertTrue(ProfesionalResource::canEdit($sinCuentaPropio));
        $this->assertFalse(ProfesionalResource::canEdit($ajeno));
        $this->assertFalse(ProfesionalResource::canEdit($sinCuentaAjeno));

        $this->actingAs($this->admSistema);
        $this->assertTrue(ProfesionalResource::canEdit($ajeno));
    }

    /** Perfiles horarios: adm_usuarios lista, edita y elige solo los centros y usuarios de su ámbito. [negativo] */
    #[Test]
    public function adm_usuarios_solo_ve_y_edita_perfiles_horarios_de_su_ambito(): void
    {
        $tsPropia = $this->usuarioEn($this->uoPropia, 'ts.propia@vida360.test', ['intervencion']);
        $tsAjena = $this->usuarioEn($this->uoAjena, 'ts.ajena@vida360.test', ['intervencion']);
        $perfilPropio = PerfilHorarioProfesional::factory()->create(['usuario_id' => $tsPropia->id, 'centro_id' => $this->centroPropio->id]);
        $perfilAjeno = PerfilHorarioProfesional::factory()->create(['usuario_id' => $tsAjena->id, 'centro_id' => $this->centroAjeno->id]);

        $this->actingAs($this->direccion);
        $this->assertSame([$perfilPropio->id], PerfilHorarioProfesionalResource::getEloquentQuery()->pluck('id')->all());
        $this->assertTrue(PerfilHorarioProfesionalResource::canEdit($perfilPropio));
        $this->assertFalse(PerfilHorarioProfesionalResource::canEdit($perfilAjeno));
        $this->assertFalse(PerfilHorarioProfesionalResource::canDelete($perfilAjeno));

        $ambito = app(AmbitoGestionUsuarios::class);
        $this->assertSame([$this->centroPropio->id], $ambito->opcionesCentros($this->direccion)->keys()->all());
        $this->assertTrue($ambito->opcionesUsuarios($this->direccion)->has($tsPropia->id));
        $this->assertFalse($ambito->opcionesUsuarios($this->direccion)->has($tsAjena->id));

        $this->actingAs($this->admSistema);
        $this->assertCount(2, PerfilHorarioProfesionalResource::getEloquentQuery()->get());
        $this->assertTrue(PerfilHorarioProfesionalResource::canEdit($perfilAjeno));
    }

    /** Excepciones y cuadrantes siguen la misma regla por centro. [negativo] */
    #[Test]
    public function excepciones_y_cuadrantes_se_limitan_al_centro_del_ambito(): void
    {
        $this->actingAs($this->direccion);

        foreach ([ExcepcionProfesionalResource::class, CuadranteMesResource::class] as $recurso) {
            $modelo = $recurso::getModel();
            $this->assertTrue($recurso::canEdit(new $modelo(['centro_id' => $this->centroPropio->id])), $recurso);
            $this->assertFalse($recurso::canEdit(new $modelo(['centro_id' => $this->centroAjeno->id])), $recurso);
            $this->assertStringContainsString('centro_id', $recurso::getEloquentQuery()->toSql(), $recurso);
        }
    }

    /** Plantillas: la supervisión crea y edita las de su ámbito; las de otras UO, solo las ve. [negativo] */
    #[Test]
    public function la_supervision_edita_plantillas_de_su_ambito_y_ve_las_demas(): void
    {
        $propia = $this->plantilla($this->uoPropia, 'Plantilla propia');
        $ajena = $this->plantilla($this->uoAjena, 'Plantilla ajena');

        $this->actingAs($this->supervisionSola);
        $this->assertTrue(PlantillaInformeResource::canCreate());
        $this->assertTrue(PlantillaInformeResource::canEdit($propia));
        $this->assertFalse(PlantillaInformeResource::canEdit($ajena));
        $this->assertFalse(PlantillaInformeResource::canDelete($ajena));

        $this->get(PlantillaInformeResource::getUrl('index'))->assertOk();
        $this->get(PlantillaInformeResource::getUrl('edit', ['record' => $propia]))->assertOk();
        $this->get(PlantillaInformeResource::getUrl('view', ['record' => $ajena]))->assertOk()->assertSee('Plantilla ajena');
        $this->get(PlantillaInformeResource::getUrl('edit', ['record' => $ajena]))->assertForbidden();
    }

    /** Estilos: igual que las plantillas; el formulario solo ofrece UO del ámbito. [negativo] */
    #[Test]
    public function la_supervision_edita_estilos_de_su_ambito_y_ve_los_demas(): void
    {
        $propio = EstiloInforme::create(['unidad_organizativa_id' => $this->uoPropia->id, 'nombre_unidad_cabecera' => 'CIAM propio', 'creado_por' => $this->admSistema->id]);
        $ajeno = EstiloInforme::create(['unidad_organizativa_id' => $this->uoAjena->id, 'nombre_unidad_cabecera' => 'CSS ajeno', 'creado_por' => $this->admSistema->id]);

        $this->actingAs($this->supervisionSola);
        $this->assertTrue(EstiloInformeResource::canCreate());
        $this->assertTrue(EstiloInformeResource::canEdit($propio));
        $this->assertFalse(EstiloInformeResource::canEdit($ajeno));

        $this->get(EstiloInformeResource::getUrl('view', ['record' => $ajeno]))->assertOk();
        $this->get(EstiloInformeResource::getUrl('edit', ['record' => $ajeno]))->assertForbidden();
        $this->get(EstiloInformeResource::getUrl('create'))->assertOk()->assertDontSee('CSS ajeno');
    }

    /** Parámetros de informe (globales): la supervisión los ve, pero no los crea ni los edita. [negativo] */
    #[Test]
    public function la_supervision_ve_los_parametros_pero_no_los_edita(): void
    {
        $parametro = ParametroInforme::create(['clave' => 'ciudad', 'etiqueta' => 'Municipio', 'valor' => 'Madrid']);

        $this->actingAs($this->supervisionSola);
        $this->assertFalse(ParametroInformeResource::canCreate());
        $this->assertFalse(ParametroInformeResource::canEdit($parametro));
        $this->get(ParametroInformeResource::getUrl('view', ['record' => $parametro]))->assertOk()->assertSee('Madrid');
        $this->get(ParametroInformeResource::getUrl('edit', ['record' => $parametro]))->assertForbidden();
    }

    /**
     * Plantilla de informe mínima de una UO.
     *
     * @param UnidadOrganizativa $uo
     * @param string $nombre
     *
     * @return PlantillaInforme
     */
    private function plantilla(UnidadOrganizativa $uo, string $nombre): PlantillaInforme
    {
        return PlantillaInforme::create([
            'unidad_organizativa_id' => $uo->id,
            'nombre' => $nombre,
            'tipo_informe' => 'informe_social',
            'activa' => true,
            'creada_por' => $this->admSistema->id,
            'secciones' => [['id' => 'valoracion', 'titulo' => 'Valoración', 'tipo' => 'texto_libre', 'obligatorio' => true]],
        ]);
    }

    /**
     * Usuario activo adscrito a la UO, con roles y profesional opcional.
     *
     * @param UnidadOrganizativa $uo
     * @param string $email
     * @param list<string> $roles
     * @param Profesional|null $profesional
     *
     * @return User
     */
    private function usuarioEn(UnidadOrganizativa $uo, string $email, array $roles, ?Profesional $profesional = null): User
    {
        $usuario = User::factory()->create([
            'email' => $email,
            'primer_acceso' => false,
            'profesional_id' => $profesional?->id,
        ]);
        $usuario->syncRoles($roles);
        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->subMonth()->toDateString(),
        ]);

        return $usuario;
    }

    /**
     * Profesional de prueba, con UO de adscripción directa si aún no tiene cuenta.
     *
     * @param string $nombre
     * @param UnidadOrganizativa|null $uo
     *
     * @return Profesional
     */
    private function profesional(string $nombre, ?UnidadOrganizativa $uo = null): Profesional
    {
        return Profesional::create([
            'nombre' => $nombre,
            'apellido1' => 'Prueba',
            'sexo' => 'F',
            'cargo_id' => Cargo::firstOrCreate(['nombre' => 'TSR Test', 'activo' => true])->id,
            'tipo_relacion_id' => TipoRelacionProfesional::firstOrCreate(['nombre' => 'Funcionario/a test'], ['es_externo' => false, 'activo' => true])->id,
            'fecha_inicio' => today()->toDateString(),
            'activo' => true,
            'unidad_organizativa_id' => $uo?->id,
        ]);
    }
}
