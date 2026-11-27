<?php

namespace Tests\Feature\Auth;

use App\Livewire\CambiarContrasena;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Desplegable del usuario en la barra superior (muestra el puesto, no el rol) y cambio de la
 * propia contraseña (27 nov 2026).
 */
class MenuUsuarioYContrasenaTest extends TestCase
{
    use RefreshDatabase;

    private const ACTUAL = 'Actual-segura-2026';

    private const NUEVA = 'Nueva-clave-segura-27';

    private User $usuario;

    /**
     * Trabajadora social con rol intervención y contraseña conocida.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $profesional = Profesional::create([
            'nombre' => 'Lucía',
            'apellido1' => 'Prueba',
            'sexo' => 'F',
            'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Trabajador/a social'], ['activo' => true])->id,
            'tipo_relacion_id' => TipoRelacionProfesional::firstOrCreate(['nombre' => 'Funcionario/a de carrera'], ['es_externo' => false, 'activo' => true])->id,
            'fecha_inicio' => '2020-01-01',
            'activo' => true,
        ]);

        $this->usuario = User::factory()->create([
            'profesional_id' => $profesional->id,
            'password' => self::ACTUAL,
            'primer_acceso' => false,
        ]);
        Role::findOrCreate('intervencion', 'web');
        $this->usuario->assignRole('intervencion');
    }

    #[Test]
    public function el_desplegable_muestra_el_puesto_y_no_el_rol(): void
    {
        $html = $this->actingAs($this->usuario)->get(route('cuenta.contrasena'))->assertOk()->getContent();

        $this->assertStringContainsString('Trabajador/a social', $html);
        $this->assertStringNotContainsString('>intervencion<', preg_replace('/\s+/', '', $html));
        $this->assertStringContainsString(route('cuenta.contrasena'), $html, 'El desplegable enlaza al cambio de contraseña.');
    }

    #[Test]
    public function sin_ficha_de_profesional_no_muestra_ni_puesto_ni_rol(): void
    {
        $tecnico = User::factory()->create(['primer_acceso' => false]);
        $tecnico->assignRole('intervencion');

        $html = preg_replace('/\s+/', '', $this->actingAs($tecnico)->get(route('cuenta.contrasena'))->assertOk()->getContent());

        $this->assertStringNotContainsString('>intervencion<', $html);
    }

    #[Test]
    public function cambia_la_contrasena_y_cierra_las_demas_sesiones(): void
    {
        $this->actingAs($this->usuario);
        DB::table('sessions')->insert([
            'id' => 'otra-sesion', 'user_id' => $this->usuario->id, 'ip_address' => '10.0.0.1',
            'user_agent' => 'otro', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);
        DB::table('sessions')->insert([
            'id' => 'sesion-ajena', 'user_id' => User::factory()->create()->id, 'ip_address' => '10.0.0.2',
            'user_agent' => 'otro', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);

        Livewire::test(CambiarContrasena::class)
            ->set('actual', self::ACTUAL)
            ->set('nueva', self::NUEVA)
            ->set('nuevaConfirmacion', self::NUEVA)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('actual', '')
            ->assertSee('Contraseña cambiada');

        $this->assertTrue(Hash::check(self::NUEVA, $this->usuario->fresh()->password));
        $this->assertFalse(DB::table('sessions')->where('id', 'otra-sesion')->exists(), 'Las demás sesiones del usuario se cierran.');
        $this->assertTrue(DB::table('sessions')->where('id', 'sesion-ajena')->exists(), 'Las de otros usuarios, no.');
    }

    #[Test]
    public function rechaza_una_contrasena_actual_incorrecta_y_no_cambia_nada(): void
    {
        $this->actingAs($this->usuario);

        Livewire::test(CambiarContrasena::class)
            ->set('actual', 'no-es-esta')
            ->set('nueva', self::NUEVA)
            ->set('nuevaConfirmacion', self::NUEVA)
            ->call('guardar')
            ->assertHasErrors(['actual']);

        $this->assertTrue(Hash::check(self::ACTUAL, $this->usuario->fresh()->password));
    }

    #[Test]
    public function rechaza_contrasenas_debiles_iguales_a_la_actual_o_sin_confirmar(): void
    {
        $this->actingAs($this->usuario);

        foreach (['corta1A', 'solo-letras-largas', '123456789012345', self::ACTUAL] as $nueva) {
            Livewire::test(CambiarContrasena::class)
                ->set('actual', self::ACTUAL)
                ->set('nueva', $nueva)
                ->set('nuevaConfirmacion', $nueva)
                ->call('guardar')
                ->assertHasErrors(['nueva']);
        }

        Livewire::test(CambiarContrasena::class)
            ->set('actual', self::ACTUAL)
            ->set('nueva', self::NUEVA)
            ->set('nuevaConfirmacion', 'Otra-distinta-2026')
            ->call('guardar')
            ->assertHasErrors(['nueva']);

        $this->assertTrue(Hash::check(self::ACTUAL, $this->usuario->fresh()->password));
    }

    #[Test]
    public function sin_sesion_la_pagina_lleva_al_login(): void
    {
        $this->get(route('cuenta.contrasena'))->assertRedirect(route('login'));
    }
}
