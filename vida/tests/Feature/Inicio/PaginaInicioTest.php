<?php

namespace Tests\Feature\Inicio;

use App\Models\User;
use App\Support\Superficie\DestinoInicial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Página de inicio, primer acceso y preferencia (pasos 1 y 6 de la ronda 1 de staging, R4):
 * grupo A (TF-STG-01 a 04) y grupo F (TF-STG-25 a 29 y 31).
 */
class PaginaInicioTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /**
     * Escenario de Tipo de Centro.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();
    }

    /**
     * Usuario del CSS Norte con varios roles.
     *
     * @param list<string> $roles
     * @param bool $primerAcceso
     *
     * @return User
     */
    private function conRoles(array $roles, bool $primerAcceso = false): User
    {
        static $n = 0;
        $usuario = $this->usuario('varios'.(++$n), $roles[0] ?? 'intervencion', $this->uoCssNorte);
        $usuario->syncRoles($roles);
        $usuario->update(['primer_acceso' => $primerAcceso]);

        return $usuario->fresh();
    }

    /**
     * Directora del CSS Norte con intervención además de supervisión.
     *
     * @return User
     */
    private function directoraConIntervencion(): User
    {
        $this->directoraNorte->assignRole('intervencion');

        return $this->directoraNorte->fresh();
    }

    /**
     * Combinaciones de roles y la página de inicio que les corresponde (R4.5).
     *
     * @return array<string, array{0: list<string>, 1: string}>
     */
    public static function combinaciones(): array
    {
        return [
            'intervención + supervisión + adm_usuarios' => [['intervencion', 'supervision', 'adm_usuarios'], 'supervision.inicio'],
            'intervención + consulta_basica' => [['intervencion', 'consulta_basica'], 'intervencion.agenda.index'],
            'tramitación + consulta_basica' => [['tramitacion', 'consulta_basica'], 'prestaciones.bandeja'],
            'consulta_basica sola' => [['consulta_basica'], 'agenda.citas.bandeja'],
            'supervisión sola' => [['supervision'], 'supervision.inicio'],
            'adm_sistema solo' => [['adm_sistema'], 'admin'],
            'consulta_profesional sola' => [['consulta_profesional'], 'sin-rol'],
            'intervención sola' => [['intervencion'], 'intervencion.agenda.index'],
            'tramitación sola' => [['tramitacion'], 'prestaciones.bandeja'],
            'adm_usuarios solo' => [['adm_usuarios'], 'admin'],
            'sin roles' => [[], 'sin-rol'],
        ];
    }

    /**
     * URL esperada para un nombre de ruta del proveedor (`admin` es el panel).
     *
     * @param string $destino
     *
     * @return string
     */
    private function url(string $destino): string
    {
        return $destino === 'admin' ? url('/admin') : route($destino);
    }

    /** TF-STG-01 — Rol sin superficie: página informativa, sin cadena de redirecciones. [negativo] */
    #[Test]
    public function tf_stg_01_rol_sin_superficie_ve_la_pagina_informativa(): void
    {
        $usuario = $this->conRoles(['consulta_profesional']);

        $this->post(route('login.post'), ['email' => $usuario->email, 'password' => 'secreto'])
            ->assertRedirect(route('sin-rol'));

        // La raíz lleva directamente a la página informativa (antes: «Redirigiendo…» sin destino)
        $this->get('/')->assertRedirect(route('sin-rol'));
        $this->get(route('sin-rol'))
            ->assertOk()
            ->assertSee('Tu usuario no tiene un perfil de trabajo configurado. Contacta con la administración de tu centro.')
            ->assertDontSee('Redirigiendo');
    }

    /** TF-STG-02 — Multicentro sin perfil horario para hoy: entra y ve el selector de centro. */
    #[Test]
    public function tf_stg_02_multicentro_sin_horario_hoy_entra_y_elige_centro(): void
    {
        // La psicóloga está adscrita al CIAM de Vallecas y al de Usera, sin perfil horario
        $this->post(route('login.post'), ['email' => $this->psicologa->email, 'password' => 'secreto'])
            ->assertRedirect(route('intervencion.agenda.index'));

        $this->get(route('intervencion.agenda.index'))->assertOk();
        Livewire::test('centro-trabajo')
            ->assertSee('Elige centro')
            ->assertSee('Puente de Vallecas')
            ->assertSee('Usera');
    }

    /** TF-STG-03 — Multicentro con perfil horario hoy: entra con el centro de su perfil. */
    #[Test]
    public function tf_stg_03_multicentro_con_horario_hoy_entra_en_ese_centro(): void
    {
        // Hoy es lunes (2 nov 2026): perfil con franja el lunes en Usera
        PerfilHorarioProfesional::create([
            'usuario_id' => $this->psicologa->id,
            'centro_id' => $this->ciamUsera->id,
            'jornada_semanal_horas' => 17.5,
            'horario_habitual' => ['1' => [['inicio' => '09:00', 'fin' => '14:00']]],
            'vigente_desde' => '2026-01-01',
            'activo' => true,
        ]);

        $this->post(route('login.post'), ['email' => $this->psicologa->email, 'password' => 'secreto'])
            ->assertRedirect(route('intervencion.agenda.index'));

        $this->assertSame($this->ciamUsera->id, Livewire::test('centro-trabajo')->get('centroActivo')?->id);
    }

    /**
     * TF-STG-04 — El primer acceso termina en el destino de DestinoInicial, para cada rol y para
     * intervención + supervisión.
     *
     * @param list<string> $roles
     * @param string $destino
     */
    #[Test]
    #[DataProvider('combinaciones')]
    public function tf_stg_04_primer_acceso_termina_en_su_pagina_de_inicio(array $roles, string $destino): void
    {
        $usuario = $this->conRoles($roles, primerAcceso: true);

        $this->post(route('login.post'), ['email' => $usuario->email, 'password' => 'secreto'])
            ->assertRedirect(route('onboarding'));

        $this->post(route('onboarding.completar'))->assertRedirect($this->url($destino));
        $this->assertFalse($usuario->fresh()->primer_acceso);
    }

    /** TF-STG-04 — Intervención + supervisión: el primer acceso lleva al inicio de Supervisión. */
    #[Test]
    public function tf_stg_04_primer_acceso_de_la_directora(): void
    {
        $directora = $this->directoraConIntervencion();
        $directora->update(['primer_acceso' => true]);

        $this->actingAs($directora)->post(route('onboarding.completar'))
            ->assertRedirect(route('supervision.inicio'));
    }

    /**
     * TF-STG-25 — Página de inicio por defecto según la prioridad de R4.5.
     *
     * @param list<string> $roles
     * @param string $destino
     */
    #[Test]
    #[DataProvider('combinaciones')]
    public function tf_stg_25_inicio_por_defecto(array $roles, string $destino): void
    {
        $usuario = $this->conRoles($roles);

        $this->assertSame($this->url($destino), DestinoInicial::para($usuario));
        $this->actingAs($usuario)->get('/')->assertRedirect($this->url($destino));
    }

    /** TF-STG-26 — La directora elige «Agenda» y vuelve a «Automática». */
    #[Test]
    public function tf_stg_26_preferencia_de_pagina_de_inicio(): void
    {
        $directora = $this->directoraConIntervencion();

        $this->actingAs($directora)
            ->post(route('preferencias.pagina-inicio'), ['pagina_inicio' => 'agenda'])
            ->assertSessionHasNoErrors();
        $this->assertSame('agenda', $directora->fresh()->pagina_inicio);

        $this->post(route('logout'));
        $this->post(route('login.post'), ['email' => $directora->email, 'password' => 'secreto'])
            ->assertRedirect(route('intervencion.agenda.index'));

        $this->post(route('preferencias.pagina-inicio'), ['pagina_inicio' => 'automatica'])
            ->assertSessionHasNoErrors();
        $this->assertNull($directora->fresh()->pagina_inicio);
        $this->get('/')->assertRedirect(route('supervision.inicio'));
    }

    /** TF-STG-27 — Preferencia no válida: rechazada en el servidor; si se pierde el acceso, prioridad. [negativo] */
    #[Test]
    public function tf_stg_27_preferencia_no_valida(): void
    {
        $directora = $this->directoraConIntervencion();
        $this->actingAs($directora);

        // Sin `tramitacion.bandeja` y la administración nunca es elegible
        foreach (['tramitacion', 'admin', 'inventada'] as $valor) {
            $this->post(route('preferencias.pagina-inicio'), ['pagina_inicio' => $valor])
                ->assertSessionHasErrors('pagina_inicio');
            $this->assertNull($directora->fresh()->pagina_inicio, "«{$valor}» no se puede guardar");
        }

        // Con una sola superficie no hay nada que elegir
        $ventanilla = $this->conRoles(['consulta_basica']);
        $this->actingAs($ventanilla)
            ->post(route('preferencias.pagina-inicio'), ['pagina_inicio' => 'citacion'])
            ->assertSessionHasErrors('pagina_inicio');

        // Preferencia guardada cuyo rol se retira: prioridad, sin error y sin borrarla
        $this->actingAs($directora)->post(route('preferencias.pagina-inicio'), ['pagina_inicio' => 'agenda']);
        $directora->removeRole('intervencion');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $sinIntervencion = $directora->fresh();
        $this->assertSame(route('supervision.inicio'), DestinoInicial::para($sinIntervencion));
        $this->actingAs($sinIntervencion)->get('/')->assertRedirect(route('supervision.inicio'));
        $this->assertSame('agenda', $sinIntervencion->pagina_inicio);

        // Al devolverle el rol, la preferencia vuelve a valer
        $sinIntervencion->assignRole('intervencion');
        $this->assertSame(route('intervencion.agenda.index'), DestinoInicial::para($sinIntervencion->fresh()));
    }

    /** TF-STG-28 — Con una sola superficie no se ofrece «Página de inicio». */
    #[Test]
    public function tf_stg_28_opcion_oculta_con_una_sola_superficie(): void
    {
        $ventanilla = $this->conRoles(['consulta_basica']);
        $this->actingAs($ventanilla)->get(route('agenda.citas.bandeja'))
            ->assertOk()
            ->assertDontSee('Página de inicio');

        // Con dos, sí
        $this->actingAs($this->directoraConIntervencion())->get(route('supervision.inicio'))
            ->assertOk()
            ->assertSee('Página de inicio')
            ->assertSee('Automática');
    }

    /** TF-STG-29 — Tras el login, el enlace directo manda sobre la página de inicio. */
    #[Test]
    public function tf_stg_29_el_enlace_directo_manda(): void
    {
        $this->get(route('intervencion.casos.index'))->assertRedirect(route('login'));

        $this->post(route('login.post'), ['email' => $this->psicologa->email, 'password' => 'secreto'])
            ->assertRedirect(route('intervencion.casos.index'));
    }

    /** TF-STG-31 — «Administración» en el menú de usuario, nunca en el menú operativo. */
    #[Test]
    public function tf_stg_31_administracion_aparte(): void
    {
        $directora = $this->directoraConIntervencion();
        $directora->assignRole('adm_usuarios');

        $html = $this->actingAs($directora->fresh())->get(route('supervision.inicio'))->assertOk()->getContent();
        [$menu, $resto] = $this->separarMenu($html);

        $this->assertStringNotContainsString('Administración', $menu);
        $this->assertStringContainsString('Administración', $resto);
        $this->assertStringContainsString('href="'.url('/admin').'"', $resto);

        // Sin acceso al backoffice, sin enlace
        $html = $this->actingAs($this->psicologa)->get(route('intervencion.agenda.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Administración', $html);
    }

    /**
     * Separa el HTML del menú lateral del resto de la página.
     *
     * @param string $html
     *
     * @return array{0: string, 1: string}
     */
    private function separarMenu(string $html): array
    {
        $this->assertSame(1, preg_match('/<aside[^>]*class="op-sidebar".*?<\/aside>/s', $html, $m));

        return [$m[0], str_replace($m[0], '', $html)];
    }
}
