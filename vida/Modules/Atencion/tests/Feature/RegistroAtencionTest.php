<?php

namespace Modules\Atencion\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atencion\Models\RegistroAtencion;
use Tests\TestCase;

/**
 * Tests funcionales del modelo RegistroAtencion.
 * Nomenclatura: TF-AT-XX
 */
class RegistroAtencionTest extends TestCase
{
    use RefreshDatabase;

    // TF-AT-01: Se puede crear un registro de tipo informacion
    public function test_crear_registro_informacion(): void
    {
        $ciudadano   = Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        $registro = RegistroAtencion::create([
            'ciudadano_id'   => $ciudadano->id,
            'tipo'           => 'informacion',
            'fecha'          => now()->toDateString(),
            'profesional_id' => $profesional->id,
            'demanda'        => 'Solicita información sobre ayudas de alquiler',
            'respuesta'      => 'Se le informa sobre el programa de ayudas municipal',
            'origen'         => 'manual',
        ]);

        $this->assertDatabaseHas('registros_atencion', [
            'ciudadano_id' => $ciudadano->id,
            'tipo'         => 'informacion',
        ]);
        $this->assertEquals('Solicita información sobre ayudas de alquiler', $registro->demanda);
    }

    // TF-AT-02: Tipo informacion sin profesional lanza excepción
    public function test_informacion_sin_profesional_falla(): void
    {
        $ciudadano = Ciudadano::factory()->create();

        $this->expectException(\LogicException::class);

        RegistroAtencion::create([
            'ciudadano_id'   => $ciudadano->id,
            'tipo'           => 'informacion',
            'fecha'          => now()->toDateString(),
            'profesional_id' => null,
            'demanda'        => 'Consulta',
            'origen'         => 'manual',
        ]);
    }

    // TF-AT-03: Tipo actividad sin origen lanza excepción
    public function test_actividad_sin_origen_falla(): void
    {
        $ciudadano = Ciudadano::factory()->create();

        $this->expectException(\LogicException::class);

        RegistroAtencion::create([
            'ciudadano_id' => $ciudadano->id,
            'tipo'         => 'actividad',
            'fecha'        => now()->toDateString(),
            'origen'       => 'sistema',
        ]);
    }

    // TF-AT-04: Se puede crear un registro de tipo actividad con origen
    public function test_crear_registro_actividad(): void
    {
        $ciudadano = Ciudadano::factory()->create();

        $registro = RegistroAtencion::create([
            'ciudadano_id' => $ciudadano->id,
            'tipo'         => 'actividad',
            'fecha'        => now()->toDateString(),
            'origen'       => 'sistema',
            'origen_tipo'  => 'Modules\\Centro\\Models\\Inscripcion',
            'origen_id'    => 42,
        ]);

        $this->assertEquals('actividad', $registro->tipo);
        $this->assertNull($registro->profesional_id);
    }

    // TF-AT-05: crearDesdeOrigen crea el registro correctamente
    public function test_crear_desde_origen(): void
    {
        $ciudadano = Ciudadano::factory()->create();

        $registro = RegistroAtencion::crearDesdeOrigen(
            $ciudadano->id,
            'Modules\\Centro\\Models\\Inscripcion',
            99,
            now()
        );

        $this->assertEquals('actividad', $registro->tipo);
        $this->assertEquals('sistema', $registro->origen);
        $this->assertEquals(99, $registro->origen_id);
    }

    // TF-AT-06: resumenHistorial trunca la demanda a 80 caracteres
    public function test_resumen_historial_truncado(): void
    {
        $ciudadano   = Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        $registro = RegistroAtencion::factory()->create([
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $profesional->id,
            'demanda'        => str_repeat('a', 100),
        ]);

        $this->assertLessThanOrEqual(83, strlen($registro->resumenHistorial())); // 80 + '...'
    }

    // TF-AT-07: La relación ciudadano->registrosAtencion funciona
    public function test_relacion_ciudadano_registros(): void
    {
        $ciudadano   = Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        RegistroAtencion::factory()->count(3)->create([
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $profesional->id,
        ]);

        $this->assertCount(3, $ciudadano->registrosAtencion()->get());
    }

    // TF-AT-08: Los registros se ordenan por fecha descendente
    public function test_orden_cronologico_inverso(): void
    {
        $ciudadano   = Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        RegistroAtencion::factory()->create([
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $profesional->id,
            'fecha'          => '2024-01-01',
        ]);
        RegistroAtencion::factory()->create([
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $profesional->id,
            'fecha'          => '2024-06-15',
        ]);

        $primero = $ciudadano->registrosAtencion()->first();
        $this->assertEquals('2024-06-15', $primero->fecha->format('Y-m-d'));
    }
}
