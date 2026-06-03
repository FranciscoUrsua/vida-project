<?php

namespace Modules\Centro\Tests\Feature;

use App\Models\Ciudadano;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\InscripcionCentro;
use Modules\Centro\Models\TipoActividad;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sección 9.6: Inscripción en centro.
 *
 * @see docs/modulo-centros.md §9.6
 */
class InscripcionCentroTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(): Centro
    {
        return Centro::create([
            'nombre' => 'Centro de prueba',
            'tipo_gestion' => 'municipal_directo',
            'inscripcion_libre' => true,
            'fecha_alta' => today()->toDateString(),
        ]);
    }

    private function crearCiudadano(): Ciudadano
    {
        return Ciudadano::create([
            'nombre' => 'Ciudadano',
            'apellido1' => 'Prueba',
            'fecha_nacimiento' => '1970-06-15',
            'sexo' => 'mujer',
            'activo' => true,
        ]);
    }

    private function crearInscripcion(Centro $centro, Ciudadano $ciudadano, bool $activa = true): InscripcionCentro
    {
        return InscripcionCentro::create([
            'centro_id' => $centro->id,
            'ciudadano_id' => $ciudadano->id,
            'fecha_alta' => today()->toDateString(),
            'activa' => $activa,
        ]);
    }

    private function crearActividad(Centro $centro, bool $requiereInscripcion): Actividad
    {
        $tipo = TipoActividad::create(['nombre' => 'Taller '.uniqid(), 'activo' => true]);

        return Actividad::create([
            'centro_id' => $centro->id,
            'tipo_actividad_id' => $tipo->id,
            'nombre' => 'Actividad de prueba',
            'modo_acceso' => 'libre',
            'requiere_inscripcion_centro' => $requiereInscripcion,
            'activa' => true,
            'fecha_alta' => today()->toDateString(),
        ]);
    }

    // =========================================================================
    // TC-01 — Un ciudadano puede inscribirse en un centro
    // =========================================================================

    #[Test]
    public function un_ciudadano_puede_inscribirse_en_un_centro(): void
    {
        $centro = $this->crearCentro();
        $ciudadano = $this->crearCiudadano();

        $inscripcion = $this->crearInscripcion($centro, $ciudadano);

        $this->assertTrue($inscripcion->activa);
    }

    // =========================================================================
    // TC-02 — La baja de inscripción es siempre explícita
    // =========================================================================

    #[Test]
    public function la_baja_de_inscripcion_es_siempre_explicita(): void
    {
        $centro = $this->crearCentro();
        $ciudadano = $this->crearCiudadano();
        $inscripcion = $this->crearInscripcion($centro, $ciudadano);

        // Simular que pasa tiempo sin ninguna acción: la inscripción sigue activa.
        $this->assertTrue($inscripcion->fresh()->activa);
        $this->assertNull($inscripcion->fresh()->fecha_baja);
    }

    // =========================================================================
    // TC-03 — Dar de baja una inscripción la desactiva
    // =========================================================================

    #[Test]
    public function dar_de_baja_una_inscripcion_la_desactiva(): void
    {
        $centro = $this->crearCentro();
        $ciudadano = $this->crearCiudadano();
        $inscripcion = $this->crearInscripcion($centro, $ciudadano);

        $inscripcion->update([
            'activa' => false,
            'fecha_baja' => today()->toDateString(),
        ]);

        $this->assertFalse($inscripcion->fresh()->activa);
    }

    // =========================================================================
    // TC-04 — Actividad con flag bloquea sin inscripción
    // =========================================================================

    #[Test]
    public function actividad_con_flag_requiere_inscripcion_bloquea_sin_inscripcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $centro = $this->crearCentro();
        $ciudadano = $this->crearCiudadano();
        $actividad = $this->crearActividad($centro, requiereInscripcion: true);

        // El ciudadano no tiene inscripción activa: debe lanzar excepción.
        $actividad->verificarInscripcionCentro($ciudadano->id);
    }

    // =========================================================================
    // TC-05 — Actividad con flag permite acceso con inscripción activa
    // =========================================================================

    #[Test]
    public function actividad_con_flag_requiere_inscripcion_permite_con_inscripcion_activa(): void
    {
        $centro = $this->crearCentro();
        $ciudadano = $this->crearCiudadano();
        $actividad = $this->crearActividad($centro, requiereInscripcion: true);

        $this->crearInscripcion($centro, $ciudadano);

        // No debe lanzar excepción.
        $actividad->verificarInscripcionCentro($ciudadano->id);

        $this->assertTrue(true);
    }
}
