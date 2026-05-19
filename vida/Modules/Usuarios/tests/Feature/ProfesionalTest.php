<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del modelo Profesional y su versionado.
 *
 * Verifican la relación opcional con Usuario, las restricciones del
 * catálogo (cargo obligatorio), la relevancia del campo organizacion,
 * y que el trait Versionable genera snapshots completos.
 *
 * Grupos:
 *   E — Modelo Profesional (TF-USU-36 a TF-USU-40)
 *   F — Versionado de Profesional (TF-USU-41 a TF-USU-42)
 */
class ProfesionalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea los catálogos mínimos necesarios para instanciar un Profesional.
     *
     * @param array<string, mixed> $overrides
     * @return array{cargo: Cargo, tipo_relacion: TipoRelacionProfesional, datos: array<string, mixed>}
     */
    private function catalogosBase(array $overrides = []): array
    {
        $cargo = Cargo::create(['nombre' => 'Trabajador/a Social', 'activo' => true]);

        $tipoRelacion = TipoRelacionProfesional::create([
            'nombre'      => 'Funcionario/a de carrera',
            'es_externo'  => false,
            'activo'      => true,
        ]);

        $datos = array_merge([
            'nombre'           => 'Ana',
            'apellido1'        => 'García',
            'sexo'             => 'F',
            'cargo_id'         => $cargo->id,
            'tipo_relacion_id' => $tipoRelacion->id,
            'fecha_inicio'     => today(),
            'activo'           => true,
        ], $overrides);

        return compact('cargo', 'tipoRelacion', 'datos');
    }

    // -------------------------------------------------------------------------
    // Grupo E — Modelo Profesional (TF-USU-36 a TF-USU-40)
    // -------------------------------------------------------------------------

    #[Test]
    public function un_profesional_puede_existir_sin_usuario_asociado(): void
    {
        ['datos' => $datos] = $this->catalogosBase();

        $profesional = Profesional::create($datos);

        $this->assertNotNull($profesional->id);
        $this->assertNull($profesional->usuario);
    }

    #[Test]
    public function un_usuario_puede_existir_sin_profesional_asociado(): void
    {
        $usuario = User::factory()->create(['profesional_id' => null]);

        $this->assertNotNull($usuario->id);
        $this->assertNull($usuario->profesional);
    }

    #[Test]
    public function la_relacion_usuario_profesional_es_navegable_en_ambos_sentidos(): void
    {
        ['datos' => $datos] = $this->catalogosBase();

        $profesional = Profesional::create($datos);
        $usuario     = User::factory()->create(['profesional_id' => $profesional->id]);

        $this->assertEquals($profesional->id, $usuario->profesional->id);
        $this->assertEquals($usuario->id, $profesional->usuario->id);
    }

    #[Test]
    public function el_cargo_de_un_profesional_es_obligatorio(): void
    {
        ['datos' => $datos] = $this->catalogosBase(['cargo_id' => null]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Profesional::create($datos);
    }

    #[Test]
    public function el_campo_organizacion_es_relevante_solo_para_profesionales_externos(): void
    {
        $cargo = Cargo::create(['nombre' => 'Abogado/a', 'activo' => true]);

        $tipoExterno = TipoRelacionProfesional::create([
            'nombre'     => 'Empresa externa',
            'es_externo' => true,
            'activo'     => true,
        ]);
        $tipoInterno = TipoRelacionProfesional::create([
            'nombre'     => 'Funcionario/a de carrera',
            'es_externo' => false,
            'activo'     => true,
        ]);

        $base = [
            'nombre'       => 'Carlos',
            'apellido1'    => 'López',
            'sexo'         => 'M',
            'cargo_id'     => $cargo->id,
            'fecha_inicio' => today(),
            'activo'       => true,
        ];

        $externo = Profesional::create(array_merge($base, [
            'tipo_relacion_id' => $tipoExterno->id,
            'organizacion'     => 'Cruz Roja',
        ]));

        $interno = Profesional::create(array_merge($base, [
            'nombre'           => 'Laura',
            'tipo_relacion_id' => $tipoInterno->id,
            'organizacion'     => null,
        ]));

        $this->assertEquals('Cruz Roja', $externo->organizacion);
        $this->assertNull($interno->organizacion);
    }

    // -------------------------------------------------------------------------
    // Grupo F — Versionado de Profesional (TF-USU-41 a TF-USU-42)
    // -------------------------------------------------------------------------

    #[Test]
    public function modificar_un_profesional_crea_una_version_con_el_estado_anterior(): void
    {
        ['cargo' => $cargoA, 'datos' => $datos] = $this->catalogosBase();
        $cargoB = Cargo::create(['nombre' => 'Psicólogo/a', 'activo' => true]);

        $profesional = Profesional::create($datos);

        $this->assertDatabaseCount('versiones', 0);

        $profesional->update(['cargo_id' => $cargoB->id]);

        $this->assertDatabaseCount('versiones', 1);
        $this->assertDatabaseHas('versiones', [
            'versionable_type' => Profesional::class,
            'versionable_id'   => $profesional->id,
        ]);

        $version = $profesional->versiones()->first();
        $this->assertEquals($cargoA->id, $version->datos['cargo_id']);
        $this->assertEquals($cargoB->id, $profesional->fresh()->cargo_id);
    }

    #[Test]
    public function el_snapshot_de_version_contiene_el_estado_completo_no_solo_el_diff(): void
    {
        ['cargo' => $cargoA, 'datos' => $datos] = $this->catalogosBase([
            'apellido1'    => 'Martínez',
            'email_profesional' => 'ana@ayuntamiento.es',
        ]);
        $cargoB = Cargo::create(['nombre' => 'Educador/a Social', 'activo' => true]);

        $profesional = Profesional::create($datos);

        $profesional->update(['cargo_id' => $cargoB->id]);

        $version = $profesional->versiones()->first();

        // El snapshot contiene todos los campos, no solo el campo modificado
        $this->assertArrayHasKey('nombre', $version->datos);
        $this->assertArrayHasKey('apellido1', $version->datos);
        $this->assertArrayHasKey('cargo_id', $version->datos);
        $this->assertArrayHasKey('tipo_relacion_id', $version->datos);
        $this->assertEquals($cargoA->id, $version->datos['cargo_id']);
        $this->assertEquals('Martínez', $version->datos['apellido1']);
        $this->assertEquals('ana@ayuntamiento.es', $version->datos['email_profesional']);
    }
}
