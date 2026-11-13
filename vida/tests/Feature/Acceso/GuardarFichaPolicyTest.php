<?php

namespace Tests\Feature\Acceso;

use App\Models\AccesoProtegido;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La edición de la ficha del ciudadano la decide CiudadanoPolicy::update (TF-ACC-19
 * a 20 quater, B3 de docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md).
 *
 * Se edita en el ámbito de la UO propia (o sin historia); fuera de él la ficha es de
 * solo lectura, también para un colectivo protegido con acceso aprobado. Supervisión
 * no edita salvo que el usuario tenga además un rol que edite (decisión del 2026-11-12).
 */
class GuardarFichaPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uoA;

    private UnidadOrganizativa $uoB;

    /**
     * Dos UO independientes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uoA = UnidadOrganizativa::create(['nombre' => 'CSS Edición A', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);
        $this->uoB = UnidadOrganizativa::create(['nombre' => 'CSS Edición B', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);
    }

    /** TF-ACC-19 — Protegido de otra UO, visible por acceso aprobado: no se ofrece editar y guardar() responde 403 sin cambios. */
    #[Test]
    public function tf_acc_19_guardar_la_ficha_de_un_protegido_de_otra_uo_responde_403(): void
    {
        $ts = $this->usuario(['intervencion'], $this->uoA);
        $protegido = $this->ciudadano(['colectivo_extra_protegido' => true]);
        $this->historia($protegido, $this->uoB);
        $this->aprobarAcceso($ts, $protegido);

        $this->actingAs($ts);
        Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $protegido->id])
            ->assertDontSee('Editar datos')
            ->set('nombre', 'Cambiado')
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame('Ana', $protegido->fresh()->nombre);
    }

    /**
     * TF-ACC-20 — Los roles que editan siguen editando en su UO, con historia y sin ella.
     *
     * @param string $rol Rol que hoy edita la ficha.
     */
    #[Test]
    #[DataProvider('rolesQueEditan')]
    public function tf_acc_20_los_roles_que_editan_siguen_editando_en_su_uo(string $rol): void
    {
        $usuario = $this->usuario([$rol], $this->uoA);
        $conHistoria = $this->ciudadano();
        $this->historia($conHistoria, $this->uoA);
        $sinHistoria = $this->ciudadano();

        $this->actingAs($usuario);
        foreach ([$conHistoria, $sinHistoria] as $persona) {
            Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $persona->id])
                ->assertSee('Editar datos')
                ->call('activarEdicion')
                ->set('nombre', 'Cambiado')
                ->call('guardar')
                ->assertHasNoErrors()
                ->assertSet('modoEdicion', false);

            $this->assertSame('Cambiado', $persona->fresh()->nombre);
        }
    }

    /**
     * Roles que hoy editan la ficha del ciudadano.
     *
     * @return array<string, array{string}>
     */
    public static function rolesQueEditan(): array
    {
        return [
            'intervencion' => ['intervencion'],
            'tramitacion' => ['tramitacion'],
            'consulta_basica' => ['consulta_basica'],
        ];
    }

    /** TF-ACC-20 bis — Persona no protegida de otra UO (consulta libre): solo lectura, guardar() responde 403. */
    #[Test]
    public function tf_acc_20_bis_fuera_de_la_uo_la_ficha_es_de_solo_lectura(): void
    {
        $ts = $this->usuario(['intervencion'], $this->uoA);
        $ajena = $this->ciudadano();
        $this->historia($ajena, $this->uoB);

        $this->actingAs($ts);

        // Con sesión, AmbitoUoScope oculta la historia ajena: la policy no debe tomarla por «sin historia»
        $this->assertFalse(Gate::allows('update', $ajena));
        $this->assertFalse(Gate::allows('delete', $ajena));

        Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $ajena->id])
            ->assertSee('Ana')
            ->assertDontSee('Editar datos')
            ->set('nombre', 'Cambiado')
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame('Ana', $ajena->fresh()->nombre);
    }

    /** TF-ACC-20 ter — Solo supervisión: no edita (403). */
    #[Test]
    public function tf_acc_20_ter_supervision_sola_no_edita(): void
    {
        $supervisor = $this->usuario(['supervision'], $this->uoA);
        $persona = $this->ciudadano();
        $this->historia($persona, $this->uoA);

        $this->actingAs($supervisor);
        Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $persona->id])
            ->assertDontSee('Editar datos')
            ->set('nombre', 'Cambiado')
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame('Ana', $persona->fresh()->nombre);
    }

    /** TF-ACC-20 quater — Supervisión con un rol que edita: edita, en la ficha y por la policy. */
    #[Test]
    public function tf_acc_20_quater_supervision_con_rol_que_edita_si_edita(): void
    {
        $usuario = $this->usuario(['supervision', 'intervencion'], $this->uoA);
        $persona = $this->ciudadano();
        $this->historia($persona, $this->uoA);

        $this->assertTrue(Gate::forUser($usuario)->allows('update', $persona));

        $this->actingAs($usuario);
        Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $persona->id])
            ->assertSee('Editar datos')
            ->call('activarEdicion')
            ->set('nombre', 'Cambiado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Cambiado', $persona->fresh()->nombre);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Usuario con unos roles, adscrito a una UO.
     *
     * @param array<int, string> $roles Roles del usuario.
     * @param UnidadOrganizativa $uo UO de adscripción.
     *
     * @return User
     */
    private function usuario(array $roles, UnidadOrganizativa $uo): User
    {
        $usuario = User::create([
            'name' => 'edicion-'.uniqid(),
            'email' => 'edicion-'.uniqid().'@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $usuario->assignRole($roles);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        return $usuario;
    }

    /**
     * Ciudadano identificado llamado Ana.
     *
     * @param array<string, mixed> $attrs Atributos adicionales.
     *
     * @return Ciudadano
     */
    private function ciudadano(array $attrs = []): Ciudadano
    {
        return Ciudadano::create(array_merge([
            'nombre' => 'Ana',
            'apellido1' => 'Edición',
            'fecha_nacimiento' => '1980-01-01',
            'sexo' => 'F',
            'nivel_identificacion' => 'identificado',
            'activo' => true,
            'colectivo_extra_protegido' => false,
        ], $attrs));
    }

    /**
     * Historia abierta en una UO.
     *
     * @param Ciudadano $ciudadano Persona.
     * @param UnidadOrganizativa $uo UO de la historia.
     *
     * @return HistoriaSocial
     */
    private function historia(Ciudadano $ciudadano, UnidadOrganizativa $uo): HistoriaSocial
    {
        return HistoriaSocial::create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);
    }

    /**
     * Acceso aprobado y vigente de un profesional a una persona protegida de la UO B.
     *
     * @param User $usuario Profesional.
     * @param Ciudadano $protegido Persona protegida.
     *
     * @return AccesoProtegido
     */
    private function aprobarAcceso(User $usuario, Ciudadano $protegido): AccesoProtegido
    {
        $supervisorB = $this->usuario(['supervision'], $this->uoB);

        return AccesoProtegido::create([
            'usuario_id' => $usuario->id,
            'ciudadano_id' => $protegido->id,
            'solicitante_id' => $usuario->id,
            'justificacion' => 'Coordinación por derivación del caso',
            'estado' => 'aprobado',
            'aprobado_por' => $supervisorB->id,
            'fecha_resolucion' => now()->subDays(2),
            'acceso_valido_hasta' => now()->addDay(),
        ]);
    }
}
