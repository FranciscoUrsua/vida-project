<?php

namespace Modules\Ciudadania\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ciudadania\Models\Ciudadano;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests funcionales de Unidad de Convivencia.
 * Nomenclatura: TF-UC-XX
 */
class UnidadConvivenciaTest extends TestCase
{
    use RefreshDatabase;

    // TF-UC-01: Se puede crear una UC con domicilio encriptado
    public function test_crea_uc_con_domicilio_encriptado(): void
    {
        $uc = UnidadConvivencia::factory()->create([
            'domicilio' => 'Calle Mayor 1, Madrid',
        ]);

        // El valor en BD está encriptado (no es el texto plano)
        $raw = \DB::table('unidades_convivencia')
            ->where('id', $uc->id)
            ->value('domicilio');

        $this->assertNotEquals('Calle Mayor 1, Madrid', $raw);
        $this->assertEquals('Calle Mayor 1, Madrid', $uc->domicilio);
    }

    // TF-UC-02: Se puede añadir un ciudadano como miembro
    public function test_agrega_miembro_a_uc(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = \App\Models\Ciudadano::factory()->create();

        $miembro = $uc->agregarMiembro($ciudadano->id);

        $this->assertInstanceOf(UnidadConvivenciaMiembro::class, $miembro);
        $this->assertEquals($ciudadano->id, $miembro->ciudadano_id);
        $this->assertNull($miembro->fecha_fin);
        $this->assertFalse($miembro->verificado);
    }

    // TF-UC-03: No se puede añadir el mismo ciudadano dos veces como miembro activo
    public function test_no_permite_miembro_activo_duplicado(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = \App\Models\Ciudadano::factory()->create();

        $uc->agregarMiembro($ciudadano->id);

        $this->expectException(\LogicException::class);
        $uc->agregarMiembro($ciudadano->id);
    }

    // TF-UC-04: Se puede añadir el mismo ciudadano tras darle de baja (histórico)
    public function test_permite_miembro_tras_baja(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = \App\Models\Ciudadano::factory()->create();

        $uc->agregarMiembro($ciudadano->id);
        $uc->darDeBajaMiembro($ciudadano->id);

        // Puede volver a añadirse
        $miembro = $uc->agregarMiembro($ciudadano->id);
        $this->assertNull($miembro->fecha_fin);
    }

    // TF-UC-05: Dar de baja a un miembro no activo lanza excepción
    public function test_baja_miembro_no_activo_lanza_excepcion(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = \App\Models\Ciudadano::factory()->create();

        $this->expectException(\LogicException::class);
        $uc->darDeBajaMiembro($ciudadano->id);
    }

    // TF-UC-06: miembrosActivos() solo devuelve miembros sin fecha_fin
    public function test_miembros_activos_excluye_dados_de_baja(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $activo = \App\Models\Ciudadano::factory()->create();
        $baja   = \App\Models\Ciudadano::factory()->create();

        $uc->agregarMiembro($activo->id);
        $uc->agregarMiembro($baja->id);
        $uc->darDeBajaMiembro($baja->id);

        $activos = $uc->miembrosActivos()->get();
        $this->assertCount(1, $activos);
        $this->assertEquals($activo->id, $activos->first()->ciudadano_id);
    }

    // TF-UC-07: verificar() marca la membresía con el profesional y timestamp
    public function test_verificar_membresia(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = \App\Models\Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        $miembro = $uc->agregarMiembro($ciudadano->id);
        $miembro->verificar($profesional);

        $miembro->refresh();
        $this->assertTrue($miembro->verificado);
        $this->assertEquals($profesional->id, $miembro->verificado_por);
        $this->assertNotNull($miembro->verificado_en);
    }

    // TF-UC-08: puedeSerPerceptorPrestaciones() requiere activo Y verificado
    public function test_perceptor_prestaciones_requiere_activo_y_verificado(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = \App\Models\Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        $miembro = $uc->agregarMiembro($ciudadano->id);

        // Activo pero no verificado
        $this->assertFalse($miembro->puedeSerPerceptorPrestaciones());

        $miembro->verificar($profesional);
        $miembro->refresh();

        // Activo y verificado
        $this->assertTrue($miembro->puedeSerPerceptorPrestaciones());

        $uc->darDeBajaMiembro($ciudadano->id);
        $miembro->refresh();

        // Verificado pero no activo
        $this->assertFalse($miembro->puedeSerPerceptorPrestaciones());
    }

    // TF-UC-09: tieneResidenciaVerificada() en Ciudadano funciona correctamente
    public function test_ciudadano_tiene_residencia_verificada(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = \App\Models\Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        $miembro = $uc->agregarMiembro($ciudadano->id);

        $this->assertFalse($ciudadano->tieneResidenciaVerificada());

        $miembro->verificar($profesional);

        $this->assertTrue($ciudadano->fresh()->tieneResidenciaVerificada());
    }

    // TF-UC-10: Un ciudadano puede pertenecer a dos UC activas simultáneamente
    public function test_ciudadano_en_dos_uc_simultaneas(): void
    {
        $uc1 = UnidadConvivencia::factory()->create();
        $uc2 = UnidadConvivencia::factory()->create();
        $ciudadano = \App\Models\Ciudadano::factory()->create();

        $uc1->agregarMiembro($ciudadano->id);
        $uc2->agregarMiembro($ciudadano->id);

        $this->assertCount(2, $ciudadano->unidadesConvivenciaActivas()->get());
    }

    // TF-UC-11: estaDisuelta() detecta correctamente la disolución
    public function test_uc_disuelta(): void
    {
        $activa   = UnidadConvivencia::factory()->create();
        $disuelta = UnidadConvivencia::factory()->disuelta()->create();

        $this->assertFalse($activa->estaDisuelta());
        $this->assertTrue($disuelta->estaDisuelta());
    }

    // TF-UC-12: softDelete no elimina los registros de miembros
    public function test_soft_delete_uc_preserva_miembros(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = \App\Models\Ciudadano::factory()->create();
        $uc->agregarMiembro($ciudadano->id);

        $uc->delete();

        // La UC ya no aparece en consultas normales
        $this->assertNull(UnidadConvivencia::find($uc->id));

        // Pero los miembros siguen en la BD
        $this->assertDatabaseHas('unidad_convivencia_miembros', [
            'unidad_convivencia_id' => $uc->id,
            'ciudadano_id'          => $ciudadano->id,
        ]);
    }

    // TF-UC-13: fuente se guarda correctamente para miembros importados de padrón
    public function test_fuente_padron_en_miembro(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = \App\Models\Ciudadano::factory()->create();

        $miembro = $uc->agregarMiembro($ciudadano->id, fuente: 'padron');

        $this->assertEquals('padron', $miembro->fuente);
        $this->assertFalse($miembro->verificado);
    }
}
