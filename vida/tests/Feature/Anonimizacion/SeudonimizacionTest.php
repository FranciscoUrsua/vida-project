<?php

namespace Tests\Feature\Anonimizacion;

use App\Models\Ciudadano;
use App\Models\RevelacionIdentidad;
use App\Models\User;
use App\Services\Api\AnonimizadorService;
use App\Services\Api\RevelacionIdentidadService;
use Database\Factories\PerfilAnonimizacionFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Grupo 1 — Seudonimización (Nivel 1).
 * Ver docs/tests-anonimizacion.md § Grupo 1.
 */
class SeudonimizacionTest extends TestCase
{
    use RefreshDatabase;

    private AnonimizadorService $servicio;

    private RevelacionIdentidadService $revelacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(AnonimizadorService::class);
        $this->revelacion = app(RevelacionIdentidadService::class);
    }

    // -------------------------------------------------------------------------
    // 1.1 El alias es determinista
    // -------------------------------------------------------------------------

    #[Test]
    public function el_alias_es_determinista(): void
    {
        $perfil = PerfilAnonimizacionFactory::new()->supervisionInterna()->create();
        $registro = collect([['id' => 4821, 'nombre' => 'María', 'fecha_nacimiento' => '1980-01-01']]);

        $resultado1 = $this->servicio->anonimizar($registro, 'supervision_interna');
        $resultado2 = $this->servicio->anonimizar($registro, 'supervision_interna');

        $this->assertEquals(
            $resultado1->first()['alias_ciudadano'],
            $resultado2->first()['alias_ciudadano']
        );
    }

    // -------------------------------------------------------------------------
    // 1.2 El alias es opaco
    // -------------------------------------------------------------------------

    #[Test]
    public function el_alias_es_opaco(): void
    {
        PerfilAnonimizacionFactory::new()->supervisionInterna()->create();
        $registro = collect([['id' => 4821, 'nombre' => 'María García']]);

        $resultado = $this->servicio->anonimizar($registro, 'supervision_interna');
        $alias = $resultado->first()['alias_ciudadano'];

        $this->assertStringNotContainsString('4821', $alias);
        $this->assertStringNotContainsString('María', $alias);
        $this->assertStringNotContainsString('García', $alias);
        $this->assertMatchesRegularExpression('/^CIU-[0-9a-f]{8}$/', $alias);
    }

    // -------------------------------------------------------------------------
    // 1.3 Alias distintos para ciudadanos distintos
    // -------------------------------------------------------------------------

    #[Test]
    public function ciudadanos_distintos_tienen_alias_distintos(): void
    {
        PerfilAnonimizacionFactory::new()->supervisionInterna()->create();
        $registros = collect([
            ['id' => 1, 'nombre' => 'Ana'],
            ['id' => 2, 'nombre' => 'Luis'],
        ]);

        $resultado = $this->servicio->anonimizar($registros, 'supervision_interna');

        $aliasA = $resultado->first()['alias_ciudadano'];
        $aliasB = $resultado->last()['alias_ciudadano'];

        $this->assertNotEquals($aliasA, $aliasB);
    }

    // -------------------------------------------------------------------------
    // 1.4 Cambiar la clave invalida los alias
    // -------------------------------------------------------------------------

    #[Test]
    public function cambiar_la_clave_invalida_los_alias(): void
    {
        PerfilAnonimizacionFactory::new()->supervisionInterna()->create();
        $registro = collect([['id' => 4821, 'nombre' => 'María']]);

        Config::set('app.pseudonym_key', 'clave_original');
        $alias1 = $this->servicio->anonimizar($registro, 'supervision_interna')->first()['alias_ciudadano'];

        Config::set('app.pseudonym_key', 'clave_nueva');
        $alias2 = $this->servicio->anonimizar($registro, 'supervision_interna')->first()['alias_ciudadano'];

        $this->assertNotEquals($alias1, $alias2);
    }

    // -------------------------------------------------------------------------
    // 1.5 La seudonimización suprime los identificadores directos
    // -------------------------------------------------------------------------

    #[Test]
    public function la_seudonimizacion_suprime_los_identificadores_directos(): void
    {
        PerfilAnonimizacionFactory::new()->supervisionInterna()->create();
        $registro = collect([[
            'id' => 4821,
            'nombre' => 'María',
            'apellido1' => 'García',
            'apellido2' => 'López',
            'documento_identidad' => '12345678A',
            'telefono' => '600000000',
            'email' => 'maria@example.com',
            'fecha_nacimiento' => '1980-01-01',
        ]]);

        $resultado = $this->servicio->anonimizar($registro, 'supervision_interna')->first();

        $this->assertArrayNotHasKey('nombre', $resultado);
        $this->assertArrayNotHasKey('apellido1', $resultado);
        $this->assertArrayNotHasKey('apellido2', $resultado);
        $this->assertArrayNotHasKey('documento_identidad', $resultado);
        $this->assertArrayNotHasKey('telefono', $resultado);
        $this->assertArrayNotHasKey('email', $resultado);
        $this->assertArrayHasKey('alias_ciudadano', $resultado);
        $this->assertMatchesRegularExpression('/^CIU-[0-9a-f]{8}$/', $resultado['alias_ciudadano']);
    }

    // -------------------------------------------------------------------------
    // 1.6 Los datos no identificativos se preservan en Nivel 1
    // -------------------------------------------------------------------------

    #[Test]
    public function los_datos_no_identificativos_se_preservan_en_nivel_1(): void
    {
        PerfilAnonimizacionFactory::new()->supervisionInterna()->create();
        $registro = collect([[
            'id' => 4821,
            'nombre' => 'María',
            'fecha_nacimiento' => '1980-07-15',
            'sexo' => 'mujer',
            'tipo_intervencion' => 'urgente',
            'estado_caso' => 'abierto',
        ]]);

        $resultado = $this->servicio->anonimizar($registro, 'supervision_interna')->first();

        $this->assertEquals('1980-07-15', $resultado['fecha_nacimiento']);
        $this->assertEquals('mujer', $resultado['sexo']);
        $this->assertEquals('urgente', $resultado['tipo_intervencion']);
        $this->assertEquals('abierto', $resultado['estado_caso']);
    }

    // -------------------------------------------------------------------------
    // 1.7 La tabla de correspondencias permite la reversión
    // -------------------------------------------------------------------------

    #[Test]
    public function la_tabla_de_correspondencias_permite_la_reversion(): void
    {
        $ciudadano = Ciudadano::factory()->create(['activo' => true]);
        $permiso = Permission::firstOrCreate(['name' => 'ciudadano.revelar_identidad', 'guard_name' => 'web']);
        $usuario = User::factory()->create();
        $usuario->givePermissionTo($permiso);

        $alias = 'CIU-'.substr(hash_hmac('sha256', (string) $ciudadano->id, config('app.pseudonym_key')), 0, 8);

        $encontrado = $this->revelacion->revelarPorAlias($alias, $usuario->id, 'Revisión de caso');

        $this->assertEquals($ciudadano->id, $encontrado->id);
    }

    // -------------------------------------------------------------------------
    // 1.8 La reversión requiere el permiso ciudadano.revelar_identidad
    // -------------------------------------------------------------------------

    #[Test]
    public function la_reversion_requiere_el_permiso_revelar_identidad(): void
    {
        Ciudadano::factory()->create(['activo' => true]);
        $usuario = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->revelacion->revelarPorAlias('CIU-00000000', $usuario->id, 'Justificación válida');
    }

    // -------------------------------------------------------------------------
    // 1.9 La reversión queda registrada en auditoría
    // -------------------------------------------------------------------------

    #[Test]
    public function la_reversion_queda_registrada_en_auditoria(): void
    {
        $ciudadano = Ciudadano::factory()->create(['activo' => true]);
        $permiso = Permission::firstOrCreate(['name' => 'ciudadano.revelar_identidad', 'guard_name' => 'web']);
        $usuario = User::factory()->create();
        $usuario->givePermissionTo($permiso);

        $alias = 'CIU-'.substr(hash_hmac('sha256', (string) $ciudadano->id, config('app.pseudonym_key')), 0, 8);

        $this->revelacion->revelarPorAlias($alias, $usuario->id, 'revisión de caso');

        $this->assertDatabaseHas('revelaciones_identidad', [
            'usuario_id' => $usuario->id,
            'accion' => 'revelar_identidad',
            'alias' => $alias,
            'ciudadano_id' => $ciudadano->id,
            'justificacion' => 'revisión de caso',
        ]);

        $registro = RevelacionIdentidad::where('ciudadano_id', $ciudadano->id)->first();
        $this->assertNotNull($registro->created_at);
    }

    // -------------------------------------------------------------------------
    // 1.10 La reversión sin justificación es rechazada
    // -------------------------------------------------------------------------

    #[Test]
    public function la_reversion_sin_justificacion_es_rechazada(): void
    {
        $permiso = Permission::firstOrCreate(['name' => 'ciudadano.revelar_identidad', 'guard_name' => 'web']);
        $usuario = User::factory()->create();
        $usuario->givePermissionTo($permiso);

        $this->expectException(ValidationException::class);

        $this->revelacion->revelarPorAlias('CIU-00000000', $usuario->id, '');
    }
}
