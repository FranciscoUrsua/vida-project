<?php

namespace Modules\Prestaciones\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prestaciones\Models\Prestacion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de consulta del catálogo de prestaciones desde otros módulos.
 *
 * Validan el consumo del catálogo mediante el modelo Eloquent directamente.
 */
class PrestacionConsultaTest extends TestCase
{
    use RefreshDatabase;

    private function prestacionBase(array $overrides = []): array
    {
        return array_merge([
            'codigo' => 'TMP001',
            'nombre' => 'Servicio base',
            'tipo_prestacion' => 'servicio',
            'nivel_garantia' => 'garantizada',
            'activa' => true,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Búsqueda y filtros
    // -------------------------------------------------------------------------

    #[Test]
    public function se_pueden_buscar_prestaciones_por_nombre_parcial(): void
    {
        Prestacion::create($this->prestacionBase(['codigo' => 'A01', 'nombre' => 'Servicio de información']));
        Prestacion::create($this->prestacionBase(['codigo' => 'A02', 'nombre' => 'Ayuda de urgencia']));
        Prestacion::create($this->prestacionBase(['codigo' => 'A03', 'nombre' => 'Información y orientación']));

        $resultados = Prestacion::activas()->where('nombre', 'ilike', '%información%')->get();

        $this->assertCount(2, $resultados);
    }

    #[Test]
    public function se_pueden_filtrar_prestaciones_por_objetivo_general(): void
    {
        Prestacion::create($this->prestacionBase(['codigo' => 'OBJ01A', 'objetivo_general' => '01']));
        Prestacion::create($this->prestacionBase(['codigo' => 'OBJ01B', 'objetivo_general' => '01']));
        Prestacion::create($this->prestacionBase(['codigo' => 'OBJ03A', 'objetivo_general' => '03']));
        Prestacion::create($this->prestacionBase(['codigo' => 'OBJ03B', 'objetivo_general' => '03']));

        $resultado = Prestacion::activas()->where('objetivo_general', '01')->get();

        $this->assertCount(2, $resultado);
    }

    #[Test]
    public function se_puede_obtener_la_lista_de_tipos_de_centro_de_una_prestacion(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase());
        $prestacion->tiposCentro()->create(['tipo_centro' => 'css_general']);
        $prestacion->tiposCentro()->create(['tipo_centro' => 'centro_dia']);

        $tipos = $prestacion->tiposCentro;

        $this->assertCount(2, $tipos);
        $this->assertTrue($tipos->pluck('tipo_centro')->contains('css_general'));
        $this->assertTrue($tipos->pluck('tipo_centro')->contains('centro_dia'));
    }

    #[Test]
    public function una_prestacion_inactiva_no_aparece_en_consultas_con_scope_activas(): void
    {
        Prestacion::create($this->prestacionBase(['codigo' => 'INA01', 'activa' => false]));

        $resultado = Prestacion::activas()->get();

        $this->assertCount(0, $resultado);
    }

    #[Test]
    public function una_prestacion_inactiva_es_recuperable_directamente_por_id(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase(['activa' => false]));

        $recuperada = Prestacion::find($prestacion->id);

        $this->assertNotNull($recuperada);
        $this->assertFalse($recuperada->activa);
    }
}
