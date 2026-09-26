<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Services\IntervencionSidebarDataService;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Enums\TipoReconocimiento;
use Modules\Mensajes\Livewire\BandejaAlertas;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Services\AlertaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de visibilidad de alertas dirigidas a un rol en una UO.
 *
 * Cubren el fallo por el que las alertas `rol_uo` (p. ej. «rol asignado» o
 * «solicitud de acceso a ciudadano protegido», dirigidas a `supervision`)
 * no aparecían en el buzón ni en el contador del menú, y el que permitía
 * reconocer alertas `rol_uo` de otra UO. TF-MSG-VIS-01 a 10.
 */
class AlertasVisiblesTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    private UnidadOrganizativa $otraUo;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create(['nombre' => 'CSS Propio', 'tipo' => 'centro', 'activa' => true]);
        $this->otraUo = UnidadOrganizativa::create(['nombre' => 'CSS Ajeno', 'tipo' => 'centro', 'activa' => true]);

        $this->supervisor = $this->crearUsuario('supervisor@vida360.test', 'supervision', $this->uo);

        // Supervisor de la otra UO: sin él, las alertas a su colectivo nacerían vencidas.
        $this->crearUsuario('supervisor-otra@vida360.test', 'supervision', $this->otraUo);
    }

    /**
     * Crea un usuario con un rol y adscripción vigente a una UO.
     */
    private function crearUsuario(string $email, string $rol, UnidadOrganizativa $uo, ?string $fechaFin = null): User
    {
        $usuario = User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $usuario->assignRole($rol);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->subYear()->toDateString(),
            'fecha_fin' => $fechaFin,
        ]);

        return $usuario;
    }

    /**
     * Crea una alerta pendiente dirigida a un rol en una UO.
     */
    private function crearAlertaRolUo(UnidadOrganizativa $uo, string $rol = 'supervision', TipoAlerta $tipo = TipoAlerta::Alerta): Alerta
    {
        return app(AlertaService::class)->crear([
            'tipo' => $tipo,
            'origen_type' => User::class,
            'origen_id' => 1,
            'titulo' => "Alerta para {$rol} en {$uo->nombre}",
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::RolUo,
            'destinatario_rol' => $rol,
            'destinatario_uo_id' => $uo->id,
        ]);
    }

    /** TF-MSG-VIS-01 — El scope incluye las alertas del rol del usuario en su UO y excluye las de otra UO o de otro rol. */
    #[Test]
    public function visibles_para_filtra_por_rol_y_uo_del_usuario(): void
    {
        $propia = $this->crearAlertaRolUo($this->uo);
        $otraUo = $this->crearAlertaRolUo($this->otraUo);
        $otroRol = $this->crearAlertaRolUo($this->uo, 'intervencion');

        $ids = Alerta::visiblesPara($this->supervisor)->pluck('id');

        $this->assertTrue($ids->contains($propia->id));
        $this->assertFalse($ids->contains($otraUo->id));
        $this->assertFalse($ids->contains($otroRol->id));
    }

    /** TF-MSG-VIS-02 — Una adscripción terminada no da acceso a las alertas rol_uo de esa UO. */
    #[Test]
    public function adscripcion_terminada_no_da_visibilidad(): void
    {
        $exSupervisor = $this->crearUsuario('ex@vida360.test', 'supervision', $this->uo, today()->subDay()->toDateString());
        $alerta = $this->crearAlertaRolUo($this->uo);

        $this->assertFalse(Alerta::visiblesPara($exSupervisor)->whereKey($alerta->id)->exists());
    }

    /** TF-MSG-VIS-03 — La bandeja muestra al supervisor las alertas rol_uo de su UO. */
    #[Test]
    public function buzon_muestra_alertas_rol_uo_de_la_propia_uo(): void
    {
        $propia = $this->crearAlertaRolUo($this->uo);
        $ajena = $this->crearAlertaRolUo($this->otraUo);

        $alertas = Livewire::actingAs($this->supervisor)
            ->test(BandejaAlertas::class, ['tipo' => 'alerta'])
            ->instance()->alertas;

        $this->assertTrue($alertas->contains('id', $propia->id));
        $this->assertFalse($alertas->contains('id', $ajena->id));
    }

    /** TF-MSG-VIS-04 — La pestaña «Avisos» muestra los avisos rol_uo de la propia UO. */
    #[Test]
    public function buzon_muestra_avisos_rol_uo_de_la_propia_uo(): void
    {
        $aviso = $this->crearAlertaRolUo($this->uo, 'supervision', TipoAlerta::Aviso);

        $avisos = Livewire::actingAs($this->supervisor)
            ->test(BandejaAlertas::class, ['tipo' => 'aviso'])
            ->instance()->alertas;

        $this->assertTrue($avisos->contains('id', $aviso->id));
    }

    /** TF-MSG-VIS-05 — Reconocer desde la bandeja deja constancia de quién y desde qué IP. */
    #[Test]
    public function reconocer_desde_buzon_registra_reconocimiento(): void
    {
        $alerta = $this->crearAlertaRolUo($this->uo);

        Livewire::actingAs($this->supervisor)
            ->test(BandejaAlertas::class, ['tipo' => 'alerta'])
            ->call('confirmarReconocimiento', $alerta->id)
            ->call('reconocer');

        $this->assertSame(EstadoAlerta::Reconocida, $alerta->fresh()->estado);
        $this->assertDatabaseHas('alerta_reconocimientos', [
            'alerta_id' => $alerta->id,
            'usuario_id' => $this->supervisor->id,
            'tipo' => TipoReconocimiento::Reconocida->value,
        ]);
    }

    /** TF-MSG-VIS-06 — Desde la pestaña de avisos no se puede descartar un aviso rol_uo de otra UO. */
    #[Test]
    public function buzon_no_reconoce_alerta_rol_uo_de_otra_uo(): void
    {
        $ajena = $this->crearAlertaRolUo($this->otraUo, 'supervision', TipoAlerta::Aviso);

        Livewire::actingAs($this->supervisor)
            ->test(BandejaAlertas::class, ['tipo' => 'aviso'])
            ->call('descartar', $ajena->id)
            ->assertForbidden();

        $this->assertSame(EstadoAlerta::Pendiente, $ajena->fresh()->estado);
        $this->assertDatabaseMissing('alerta_reconocimientos', ['alerta_id' => $ajena->id]);
    }

    /** TF-MSG-VIS-07 — Desde la bandeja de alertas tampoco se puede reconocer una alerta rol_uo de otra UO. */
    #[Test]
    public function bandeja_no_reconoce_alerta_rol_uo_de_otra_uo(): void
    {
        $ajena = $this->crearAlertaRolUo($this->otraUo);

        Livewire::actingAs($this->supervisor)
            ->test(BandejaAlertas::class)
            ->call('confirmarReconocimiento', $ajena->id)
            ->call('reconocer')
            ->assertForbidden();

        $this->assertSame(EstadoAlerta::Pendiente, $ajena->fresh()->estado);
        $this->assertDatabaseMissing('alerta_reconocimientos', ['alerta_id' => $ajena->id]);
    }

    /** TF-MSG-VIS-08 — El contador del menú incluye las alertas rol_uo de la propia UO, no las de otra. */
    #[Test]
    public function contador_menu_incluye_alertas_rol_uo(): void
    {
        $this->crearAlertaRolUo($this->uo);
        $this->crearAlertaRolUo($this->otraUo);

        $this->actingAs($this->supervisor);

        $this->assertSame(1, app(IntervencionSidebarDataService::class)->getData()['alertas']);
    }

    /** TF-MSG-VIS-09 — resolverDestinatarios no incluye usuarios del mismo rol adscritos a otra UO. */
    #[Test]
    public function resolver_destinatarios_excluye_otras_uo(): void
    {
        // Adscripción con fecha de fin futura: es el caso que dejaba pasar el
        // orWhere sin agrupar de la consulta original.
        $supervisorAjeno = $this->crearUsuario('ajeno@vida360.test', 'supervision', $this->otraUo, today()->addMonth()->toDateString());
        $alerta = $this->crearAlertaRolUo($this->uo);

        $destinatarios = app(AlertaService::class)->resolverDestinatarios($alerta);

        $this->assertTrue($destinatarios->contains('id', $this->supervisor->id));
        $this->assertFalse($destinatarios->contains('id', $supervisorAjeno->id));
    }

    /** TF-MSG-VIS-10 — resolverDestinatarios no incluye usuarios con la adscripción a la UO terminada. */
    #[Test]
    public function resolver_destinatarios_excluye_adscripciones_terminadas(): void
    {
        $exSupervisor = $this->crearUsuario('ex2@vida360.test', 'supervision', $this->uo, today()->subDay()->toDateString());
        $alerta = $this->crearAlertaRolUo($this->uo);

        $destinatarios = app(AlertaService::class)->resolverDestinatarios($alerta);

        $this->assertFalse($destinatarios->contains('id', $exSupervisor->id));
    }
}
