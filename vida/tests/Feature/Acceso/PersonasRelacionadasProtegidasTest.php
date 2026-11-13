<?php

namespace Tests\Feature\Acceso;

use App\Models\AccesoProtegido;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use App\Services\PresentacionPersona;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Documentos\Http\Livewire\DocumentosCiudadano;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Personas relacionadas de un colectivo protegido (TF-ACC-PR-01 a 08, B1 de
 * docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md).
 *
 * En la ficha y el expediente, un conviviente, una relación o un representante de
 * un colectivo protegido que la policy no deja ver aparece como «Persona con
 * protección especial»: consta que la relación existe y su tipo, pero no quién es,
 * y no se ofrecen acciones sobre ella. Quien sí puede verla la ve como siempre.
 */
class PersonasRelacionadasProtegidasTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uoA;

    private UnidadOrganizativa $uoB;

    /** Profesional de intervención de la UO A. */
    private User $tsA;

    /** Titular de la ficha, con historia en la UO A. */
    private Ciudadano $titular;

    private HistoriaSocial $historiaTitular;

    /** Persona de colectivo protegido con historia solo en la UO B. */
    private Ciudadano $protegido;

    /** Persona no protegida con historia en la UO B. */
    private Ciudadano $ajeno;

    /** Relación del titular con la persona protegida (representante). */
    private CiudadanoRelacion $relacionProtegido;

    /** Registro de la persona protegida en la UC del titular. */
    private UnidadConvivenciaMiembro $miembroProtegido;

    /**
     * Titular con representante protegido de otra UO, conviviente con él, y una
     * relación con una persona no protegida de otra UO.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->seed(TipoRelacionSeeder::class);

        $this->uoA = UnidadOrganizativa::create(['nombre' => 'CSS Relaciones A', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);
        $this->uoB = UnidadOrganizativa::create(['nombre' => 'CSS Relaciones B', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);
        $this->tsA = $this->usuarioEnUo('ts.rel.a', 'intervencion', $this->uoA);

        $this->titular = $this->crearCiudadano('María', 'Titular');
        $this->historiaTitular = $this->crearHistoria($this->titular, $this->uoA);
        $this->protegido = $this->crearCiudadano('Remedios', 'Escondida', ['colectivo_extra_protegido' => true, 'telefono' => '600111222']);
        $this->crearHistoria($this->protegido, $this->uoB);
        $this->ajeno = $this->crearCiudadano('Pedro', 'Visible');
        $this->crearHistoria($this->ajeno, $this->uoB);

        $this->relacionProtegido = CiudadanoRelacion::create([
            'ciudadano_id' => $this->titular->id,
            'ciudadano_relacionado_id' => $this->protegido->id,
            'tipo_relacion' => 'representante',
            'fecha_inicio' => '2026-01-01',
        ]);
        CiudadanoRelacion::create([
            'ciudadano_id' => $this->titular->id,
            'ciudadano_relacionado_id' => $this->ajeno->id,
            'tipo_relacion' => 'hermano',
            'fecha_inicio' => '2026-01-01',
        ]);

        $uc = UnidadConvivencia::create(['domicilio' => 'Calle Prueba 1', 'fecha_constitucion' => '2026-01-01']);
        $uc->agregarMiembro($this->titular->id);
        $this->miembroProtegido = $uc->agregarMiembro($this->protegido->id);
    }

    // -------------------------------------------------------------------------
    // Ficha del ciudadano
    // -------------------------------------------------------------------------

    /** TF-ACC-PR-01 — En la ficha, la relación y la convivencia con un protegido ajeno no dicen quién es. */
    #[Test]
    public function tf_acc_pr_01_la_ficha_muestra_la_relacion_con_un_protegido_ajeno_sin_su_identidad(): void
    {
        $this->montarFicha()
            ->assertSee('Representante')
            ->assertSee(PresentacionPersona::NOMBRE_PROTEGIDO)
            ->assertDontSee('Remedios')
            ->assertDontSee('Escondida')
            ->assertDontSeeHtml(route('ciudadania.ciudadano.ficha', $this->protegido->id).'"')
            ->assertDontSeeHtml('abrirModalEditarRelacion('.$this->relacionProtegido->id.')');
    }

    /** TF-ACC-PR-02 — Con acceso aprobado y vigente, la persona protegida se ve como siempre. */
    #[Test]
    public function tf_acc_pr_02_con_acceso_aprobado_la_persona_protegida_se_ve_con_su_enlace(): void
    {
        $this->aprobarAcceso();

        $this->montarFicha()
            ->assertSee('Remedios')
            ->assertSeeHtml(route('ciudadania.ciudadano.ficha', $this->protegido->id))
            ->assertSeeHtml('abrirModalEditarRelacion('.$this->relacionProtegido->id.')')
            ->assertDontSee(PresentacionPersona::NOMBRE_PROTEGIDO);
    }

    /** TF-ACC-PR-03 — Una persona no protegida de otra UO se sigue viendo (no se oculta de más). */
    #[Test]
    public function tf_acc_pr_03_una_persona_no_protegida_de_otra_uo_se_sigue_viendo(): void
    {
        $this->montarFicha()
            ->assertSee('Pedro')
            ->assertSeeHtml(route('ciudadania.ciudadano.ficha', $this->ajeno->id));
    }

    /** TF-ACC-PR-04 — Las acciones de la ficha sobre la relación con un protegido ajeno se rechazan en el servidor. */
    #[Test]
    public function tf_acc_pr_04_editar_o_cerrar_la_relacion_con_un_protegido_ajeno_se_rechaza(): void
    {
        $this->montarFicha()
            ->call('abrirModalEditarRelacion', $this->relacionProtegido->id)
            ->assertSet('modalRelacionAbierto', false)
            ->call('cerrarRelacion', $this->relacionProtegido->id)
            ->assertForbidden();

        $this->montarFicha()
            ->set('relacionId', $this->relacionProtegido->id)
            ->set('relacionObservaciones', 'Intento de edición')
            ->call('guardarRelacion')
            ->assertForbidden();

        $relacion = $this->relacionProtegido->fresh();
        $this->assertNull($relacion->fecha_fin);
        $this->assertNull($relacion->observaciones);
    }

    // -------------------------------------------------------------------------
    // Expediente
    // -------------------------------------------------------------------------

    /** TF-ACC-PR-05 — En el expediente, representante, convivencia y relaciones no muestran al protegido ajeno. */
    #[Test]
    public function tf_acc_pr_05_el_expediente_no_muestra_al_protegido_ajeno_ni_su_contacto(): void
    {
        $this->actingAs($this->tsA);

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaTitular])
            ->assertSee('Representante')
            ->assertSee(PresentacionPersona::NOMBRE_PROTEGIDO)
            ->assertDontSee('Remedios')
            ->call('abrirModalRepresentante')
            ->assertSet('modalRepresentanteAbierto', false)
            ->assertDontSee('600111222')
            ->call('toggleUC')
            ->assertDontSee('Remedios')
            ->call('abrirModalRelaciones')
            ->assertSee('Pedro')
            ->assertDontSee('Remedios')
            ->assertDontSee('600111222')
            ->assertDontSeeHtml(route('ciudadania.ciudadano.ficha', $this->protegido->id).'"');
    }

    /** TF-ACC-PR-06 — Baja y verificación de un conviviente protegido ajeno no se ofrecen ni se ejecutan. */
    #[Test]
    public function tf_acc_pr_06_la_baja_y_la_verificacion_de_un_conviviente_protegido_ajeno_se_rechazan(): void
    {
        $this->actingAs($this->tsA);

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaTitular])
            ->call('abrirModalUc')
            ->assertDontSeeHtml('iniciarBajaMiembro('.$this->miembroProtegido->id.')')
            ->assertDontSeeHtml('verificarMiembro('.$this->miembroProtegido->id.')')
            ->call('verificarMiembro', $this->miembroProtegido->id)
            ->call('iniciarBajaMiembro', $this->miembroProtegido->id)
            ->assertSet('ucMiembroParaBaja', null)
            ->set('ucMiembroParaBaja', $this->miembroProtegido->id)
            ->call('confirmarBajaMiembro');

        $miembro = $this->miembroProtegido->fresh();
        $this->assertFalse((bool) $miembro->verificado);
        $this->assertNull($miembro->fecha_fin);
    }

    // -------------------------------------------------------------------------
    // Presentador y otros listados
    // -------------------------------------------------------------------------

    /** TF-ACC-PR-07 — El presentador no devuelve id, enlace ni modelo de un protegido que no se puede ver. */
    #[Test]
    public function tf_acc_pr_07_el_presentador_oculta_al_protegido_tambien_sin_usuario(): void
    {
        $oculto = PresentacionPersona::paraListado($this->protegido, $this->tsA);
        $this->assertSame(
            ['visible' => false, 'id' => null, 'nombre' => PresentacionPersona::NOMBRE_PROTEGIDO, 'url' => null, 'ciudadano' => null],
            $oculto,
        );

        // Sin sesión (p. ej. un proceso en segundo plano) un protegido nunca se muestra; el resto, sí.
        $this->assertFalse(PresentacionPersona::visible($this->protegido, null));
        $this->assertTrue(PresentacionPersona::visible($this->ajeno, null));

        $this->aprobarAcceso();
        $this->assertSame('Remedios Escondida', PresentacionPersona::paraListado($this->protegido->fresh(), $this->tsA)['nombre']);
    }

    /** TF-ACC-PR-08 — Al subir un documento no se ofrece vincularlo a un conviviente protegido ajeno. */
    #[Test]
    public function tf_acc_pr_08_el_alta_de_documentos_no_ofrece_al_conviviente_protegido_ajeno(): void
    {
        $this->actingAs($this->tsA);

        $componente = Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->titular->id]);

        $this->assertSame([], $componente->instance()->otrosMiembros->pluck('ciudadano_id')->all());

        $this->aprobarAcceso();
        $componente = Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->titular->id]);
        $this->assertSame([$this->protegido->id], $componente->instance()->otrosMiembros->pluck('ciudadano_id')->all());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Monta la ficha del titular como profesional de la UO A.
     *
     * @return Testable<FichaCiudadanoPage>
     */
    private function montarFicha(): Testable
    {
        $this->actingAs($this->tsA);

        return Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $this->titular->id]);
    }

    /**
     * Usuario con un rol, adscrito a una UO.
     *
     * @param string $alias Prefijo del email.
     * @param string $rol Rol.
     * @param UnidadOrganizativa $uo UO de adscripción.
     *
     * @return User
     */
    private function usuarioEnUo(string $alias, string $rol, UnidadOrganizativa $uo): User
    {
        $usuario = User::create([
            'name' => $alias,
            'email' => "{$alias}@vida360.test",
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $usuario->assignRole($rol);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        return $usuario;
    }

    /**
     * Ciudadano identificado.
     *
     * @param string $nombre Nombre.
     * @param string $apellido Primer apellido.
     * @param array<string, mixed> $attrs Atributos adicionales.
     *
     * @return Ciudadano
     */
    private function crearCiudadano(string $nombre, string $apellido, array $attrs = []): Ciudadano
    {
        return Ciudadano::create(array_merge([
            'nombre' => $nombre,
            'apellido1' => $apellido,
            'fecha_nacimiento' => '1980-01-01',
            'sexo' => 'F',
            'nivel_identificacion' => 'identificado',
            'activo' => true,
            'colectivo_extra_protegido' => false,
        ], $attrs));
    }

    /**
     * Historia social abierta en una UO.
     *
     * @param Ciudadano $ciudadano Persona.
     * @param UnidadOrganizativa $uo UO de la historia.
     *
     * @return HistoriaSocial
     */
    private function crearHistoria(Ciudadano $ciudadano, UnidadOrganizativa $uo): HistoriaSocial
    {
        return HistoriaSocial::create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);
    }

    /**
     * Acceso aprobado y vigente del profesional de la UO A a la persona protegida.
     *
     * @return AccesoProtegido
     */
    private function aprobarAcceso(): AccesoProtegido
    {
        $supervisorB = $this->usuarioEnUo('sup.rel.b', 'supervision', $this->uoB);

        return AccesoProtegido::create([
            'usuario_id' => $this->tsA->id,
            'ciudadano_id' => $this->protegido->id,
            'solicitante_id' => $this->tsA->id,
            'justificacion' => 'Coordinación por derivación del caso',
            'estado' => 'aprobado',
            'aprobado_por' => $supervisorB->id,
            'fecha_resolucion' => now()->subDays(2),
            'acceso_valido_hasta' => now()->addDay(),
        ]);
    }
}
