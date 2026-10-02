<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Livewire\PerfilHorarioComponent;
use Modules\Agenda\Livewire\Supervisor\CuadranteSupervisorPage;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Tests\Feature\Supervisor\AgendaSupervisorTestHelpers;
use Modules\Centro\Models\Centro;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Models\Alerta;
use Modules\Supervision\Http\Livewire\EquipoPage;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Perfil horario por defecto: ningún profesional sin horario en su centro.
 *
 * Al adscribirse a la UO de un centro, el profesional recibe el horario del
 * centro (L–V 09:00–15:00 en el escenario) como «horario no personalizado» y la
 * supervisión recibe un aviso. Guardar el perfil lo verifica.
 */
class PerfilHorarioPorDefectoTest extends TestCase
{
    use AgendaSupervisorTestHelpers;
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->construirFixturesSupervisor();
    }

    /**
     * Cuenta vinculada a una ficha de profesional, aún sin adscripción.
     *
     * @param string $alias
     * @return User
     */
    private function cuentaDeProfesional(string $alias): User
    {
        $cargo = Cargo::firstOrCreate(['slug' => 'ts'], ['nombre' => 'Trabajador/a Social', 'activo' => true, 'puede_ser_referencia' => true]);
        $tipoRelacion = TipoRelacionProfesional::firstOrCreate(['nombre' => 'Funcionario/a de carrera'], ['es_externo' => false, 'activo' => true]);

        $profesional = Profesional::create([
            'nombre' => ucfirst($alias),
            'apellido1' => 'Prueba',
            'sexo' => 'F',
            'cargo_id' => $cargo->id,
            'tipo_relacion_id' => $tipoRelacion->id,
            'fecha_inicio' => '2020-01-01',
            'activo' => true,
        ]);

        return User::create([
            'email' => "{$alias}@vida360.test",
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
            'profesional_id' => $profesional->id,
        ]);
    }

    /**
     * @param User $usuario
     * @param string|null $desde
     * @return UsuarioUo
     */
    private function adscribir(User $usuario, ?string $desde = null): UsuarioUo
    {
        return UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $this->uoSupervisor->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => $desde ?? today()->toDateString(),
        ]);
    }

    /**
     * @param User $usuario
     * @return PerfilHorarioProfesional|null
     */
    private function perfilDe(User $usuario): ?PerfilHorarioProfesional
    {
        return PerfilHorarioProfesional::where('usuario_id', $usuario->id)->where('centro_id', $this->centro->id)->where('activo', true)->first();
    }

    /**
     * Avisos de horario a la supervisión de la UO del centro.
     *
     * @return int
     */
    private function avisosDeHorario(): int
    {
        return Alerta::where('destinatario_type', DestinatarioType::RolUo)
            ->where('destinatario_rol', 'supervision')
            ->where('destinatario_uo_id', $this->uoSupervisor->id)
            ->where('titulo', 'like', '%horario%')
            ->count();
    }

    #[Test]
    public function al_adscribirse_recibe_el_horario_del_centro_sin_personalizar_y_se_avisa_a_supervision(): void
    {
        $usuario = $this->cuentaDeProfesional('nueva');

        $this->adscribir($usuario);

        $perfil = $this->perfilDe($usuario);
        $this->assertNotNull($perfil);
        $this->assertTrue($perfil->pendiente_verificar);
        $this->assertSame(today()->toDateString(), $perfil->vigente_desde->toDateString());
        $this->assertEquals(30.0, (float) $perfil->jornada_semanal_horas);
        $this->assertSame(['1', '2', '3', '4', '5'], array_map('strval', array_keys($perfil->horario_habitual)));
        $this->assertSame([['inicio' => '09:00', 'fin' => '15:00']], $perfil->horario_habitual['1']);

        $aviso = Alerta::where('origen_type', PerfilHorarioProfesional::class)->where('origen_id', $perfil->id)->first();
        $this->assertNotNull($aviso);
        $this->assertSame('Nuevo profesional en el centro: verifica su horario', $aviso->titulo);
        $this->assertSame(DestinatarioType::RolUo, $aviso->destinatario_type);
        $this->assertSame($this->uoSupervisor->id, $aviso->destinatario_uo_id);
    }

    #[Test]
    public function una_cuenta_tecnica_sin_profesional_no_recibe_horario(): void
    {
        $tecnico = User::create(['email' => 'tecnico@vida360.test', 'password' => 'secreto', 'email_verified_at' => now(), 'primer_acceso' => false]);

        $this->adscribir($tecnico);

        $this->assertNull($this->perfilDe($tecnico));
        $this->assertSame(0, $this->avisosDeHorario());
    }

    #[Test]
    public function no_toca_un_perfil_activo_existente(): void
    {
        $usuario = $this->cuentaDeProfesional('conperfil');
        $propio = PerfilHorarioProfesional::create([
            'usuario_id' => $usuario->id,
            'centro_id' => $this->centro->id,
            'jornada_semanal_horas' => 17.5,
            'horario_habitual' => ['1' => [['inicio' => '09:00', 'fin' => '12:30']]],
            'vigente_desde' => '2026-01-01',
            'activo' => true,
        ]);

        $this->adscribir($usuario);

        $this->assertSame(1, PerfilHorarioProfesional::where('usuario_id', $usuario->id)->count());
        $this->assertFalse($propio->fresh()->pendiente_verificar);
        $this->assertSame(0, $this->avisosDeHorario());
    }

    #[Test]
    public function sin_horario_de_centro_vigente_no_crea_perfil(): void
    {
        $this->horario->update(['vigente_hasta' => today()->subDay()->toDateString()]);
        $usuario = $this->cuentaDeProfesional('sinhorario');

        $this->adscribir($usuario);

        $this->assertNull($this->perfilDe($usuario));
    }

    #[Test]
    public function una_adscripcion_con_fecha_pasada_no_reescribe_el_pasado(): void
    {
        $usuario = $this->cuentaDeProfesional('antigua');

        $this->adscribir($usuario, '2025-03-01');

        $this->assertSame(today()->toDateString(), $this->perfilDe($usuario)->vigente_desde->toDateString());
    }

    #[Test]
    public function al_vincular_la_ficha_a_una_cuenta_ya_adscrita_recibe_el_horario(): void
    {
        $usuario = $this->cuentaDeProfesional('tardia');
        $profesionalId = $usuario->profesional_id;
        $usuario->update(['profesional_id' => null]);
        $this->adscribir($usuario);
        $this->assertNull($this->perfilDe($usuario));

        $usuario->update(['profesional_id' => $profesionalId]);

        $this->assertTrue($this->perfilDe($usuario)->pendiente_verificar);
    }

    #[Test]
    public function guardar_el_perfil_lo_verifica(): void
    {
        $usuario = $this->cuentaDeProfesional('verificar');
        $this->adscribir($usuario);

        Livewire::actingAs($this->supervisor)
            ->test(PerfilHorarioComponent::class, ['profesional' => $usuario, 'centro' => $this->centro])
            ->assertSee('Horario no personalizado')
            ->call('guardar')
            ->assertDontSee('Horario no personalizado');

        $this->assertFalse($this->perfilDe($usuario)->pendiente_verificar);
    }

    #[Test]
    public function el_comando_completa_el_centro_sin_tocar_los_perfiles_existentes_y_avisa_una_vez(): void
    {
        // Adscritos antes de la asignación automática: sin perfil
        $sinPerfil = collect(['uno', 'dos'])->map(fn ($a) => $this->cuentaDeProfesional($a));
        UsuarioUo::withoutEvents(fn () => $sinPerfil->each(fn (User $u) => $this->adscribir($u)));
        $existentes = PerfilHorarioProfesional::count();

        $this->artisan('agenda:horarios-por-defecto', ['--centro' => [$this->centro->id]])->assertSuccessful();

        $this->assertSame($existentes + 2, PerfilHorarioProfesional::count());
        $sinPerfil->each(fn (User $u) => $this->assertTrue($this->perfilDe($u)->pendiente_verificar));
        $this->assertSame(1, Alerta::where('origen_type', Centro::class)->where('origen_id', $this->centro->id)->count());

        // Idempotente: una segunda pasada no crea ni avisa
        $this->artisan('agenda:horarios-por-defecto', ['--centro' => [$this->centro->id]])->assertSuccessful();
        $this->assertSame($existentes + 2, PerfilHorarioProfesional::count());
        $this->assertSame(1, Alerta::where('origen_type', Centro::class)->count());
    }

    #[Test]
    public function el_comando_exige_centro_o_todos(): void
    {
        $this->artisan('agenda:horarios-por-defecto')->assertFailed();
    }

    #[Test]
    public function mi_equipo_marca_el_horario_no_personalizado_y_la_falta_de_horario(): void
    {
        $usuario = $this->cuentaDeProfesional('pendiente');
        $this->adscribir($usuario);

        $sinHorario = $this->cuentaDeProfesional('sinperfil');
        UsuarioUo::withoutEvents(fn () => $this->adscribir($sinHorario));

        $estados = Livewire::actingAs($this->supervisor)->test(EquipoPage::class)
            ->assertSee('Horario no personalizado')
            ->assertSee('Sin horario en el centro')
            ->instance()->estadosHorario;

        $this->assertSame('no_personalizado', $estados[$usuario->id]);
        $this->assertSame('sin_horario', $estados[$sinHorario->id]);
        // Los perfiles configurados a mano no se marcan
        $this->assertArrayNotHasKey($this->profesional1->id, $estados);
    }

    #[Test]
    public function mi_equipo_marca_la_ficha_de_profesional_sin_cuenta_de_usuario(): void
    {
        $conCuenta = $this->cuentaDeProfesional('concuenta');
        $this->adscribir($conCuenta);

        $html = Livewire::actingAs($this->supervisor)->test(EquipoPage::class)->html();
        $this->assertStringNotContainsString('Sin cuenta de usuario', $html);

        // Ficha creada por el supervisor directamente en la UO, sin cuenta
        Profesional::create([
            'nombre' => 'Sincuenta',
            'apellido1' => 'Prueba',
            'sexo' => 'F',
            'cargo_id' => $conCuenta->profesional->cargo_id,
            'tipo_relacion_id' => $conCuenta->profesional->tipo_relacion_id,
            'fecha_inicio' => '2020-01-01',
            'activo' => true,
            'unidad_organizativa_id' => $this->uoSupervisor->id,
        ]);

        Livewire::actingAs($this->supervisor)->test(EquipoPage::class)
            ->assertSeeInOrder(['Sincuenta', 'Sin cuenta de usuario']);
    }

    #[Test]
    public function el_cuadrante_solo_muestra_perfiles_vigentes(): void
    {
        PerfilHorarioProfesional::where('usuario_id', $this->profesional3->id)
            ->update(['vigente_desde' => today()->addMonth()->toDateString()]);

        $ids = Livewire::actingAs($this->supervisor)->test(CuadranteSupervisorPage::class)
            ->get('profesionales')->pluck('id');

        $this->assertContains($this->profesional1->id, $ids);
        $this->assertNotContains($this->profesional3->id, $ids);
    }
}
