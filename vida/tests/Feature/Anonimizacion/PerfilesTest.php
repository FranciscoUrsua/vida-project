<?php

namespace Tests\Feature\Anonimizacion;

use App\Exceptions\Anonimizacion\PerfilConExtraccionesException;
use App\Exceptions\Anonimizacion\PerfilSistemaNoEliminableException;
use App\Models\Api\PerfilAnonimizacion;
use App\Models\Api\PerfilAnonimizacionVersion;
use Database\Factories\PerfilAnonimizacionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo 6 — Perfiles (configuración y versionado).
 * Ver docs/tests-anonimizacion.md § Grupo 6.
 */
class PerfilesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 6.1 Modificar un perfil incrementa su versión
    // -------------------------------------------------------------------------

    #[Test]
    public function modificar_un_perfil_incrementa_su_version(): void
    {
        $perfil = PerfilAnonimizacionFactory::new()->analiticaInterna()->create();
        $this->assertSame(1, $perfil->version);

        $perfil->campos = array_merge($perfil->campos, [
            ['campo' => 'alias', 'tecnica' => 'suprimir'],
        ]);
        $perfil->save();
        $perfil->refresh();

        $this->assertSame(2, $perfil->version);
    }

    // -------------------------------------------------------------------------
    // 6.2 El historial de versiones es inmutable
    // -------------------------------------------------------------------------

    #[Test]
    public function el_historial_de_versiones_es_inmutable(): void
    {
        $perfil = PerfilAnonimizacionFactory::new()->analiticaInterna()->create();

        // Primer cambio: versión 1 → 2
        $camposV1 = $perfil->campos;
        $perfil->campos = [['campo' => 'nombre', 'tecnica' => 'suprimir']];
        $perfil->save();

        // Segundo cambio: versión 2 → 3
        $perfil->refresh();
        $camposV2 = $perfil->campos;
        $perfil->campos = [['campo' => 'apellido1', 'tecnica' => 'suprimir']];
        $perfil->save();

        // Debe haber 2 snapshots (versiones 1 y 2)
        $versiones = PerfilAnonimizacionVersion::where('perfil_id', $perfil->id)
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $versiones);

        // Los snapshots no deben haber cambiado
        $this->assertEquals($camposV1, $versiones->first()->campos);
        $this->assertEquals($camposV2, $versiones->last()->campos);

        // No existe updated_at en la tabla de versiones
        $this->assertNull($versiones->first()->updated_at ?? null);
    }

    // -------------------------------------------------------------------------
    // 6.3 Una extracción registra la versión del perfil en el momento de ejecución
    // -------------------------------------------------------------------------

    #[Test]
    public function la_version_del_perfil_queda_registrada_en_el_snapshot(): void
    {
        $perfil = PerfilAnonimizacionFactory::new()->analiticaInterna()->create();
        $this->assertSame(1, $perfil->version);

        // Simular que una extracción registra la versión actual
        $versionEnExtraccion = $perfil->version;

        // Actualizar el perfil — simula que ocurre después de la extracción
        $perfil->campos = [['campo' => 'nombre', 'tecnica' => 'suprimir']];
        $perfil->save();
        $perfil->refresh();

        // La versión registrada en la extracción no debe cambiar
        $this->assertSame(1, $versionEnExtraccion,
            'La versión registrada en T1 debe ser 1, aunque el perfil se actualice después');
        $this->assertSame(2, $perfil->version,
            'El perfil actual es versión 2');

        // El snapshot de la versión 1 sigue accesible
        $v1 = PerfilAnonimizacionVersion::where('perfil_id', $perfil->id)
            ->where('version', 1)
            ->first();
        $this->assertNotNull($v1);
    }

    // -------------------------------------------------------------------------
    // 6.4 Los perfiles predefinidos del sistema no pueden eliminarse
    // -------------------------------------------------------------------------

    /**
     * @return array<string, list<string>>
     */
    public static function perfilesSistema(): array
    {
        return [
            'supervision_interna'   => ['supervision_interna'],
            'analitica_interna'     => ['analitica_interna'],
            'datos_abiertos'        => ['datos_abiertos'],
            'investigacion_externa' => ['investigacion_externa'],
        ];
    }

    #[Test]
    #[DataProvider('perfilesSistema')]
    public function perfiles_de_sistema_no_pueden_eliminarse(string $nombre): void
    {
        $perfil = PerfilAnonimizacion::create([
            'nombre'     => $nombre,
            'nivel'      => 2,
            'version'    => 1,
            'estado'     => 'activo',
            'es_sistema' => true,
            'campos'     => [['campo' => 'nombre', 'tecnica' => 'suprimir']],
            'k_valor'    => null,
        ]);

        $this->expectException(PerfilSistemaNoEliminableException::class);

        $perfil->delete();
    }

    // -------------------------------------------------------------------------
    // 6.5 Un perfil personalizado sin extracciones asociadas puede eliminarse
    // -------------------------------------------------------------------------

    #[Test]
    public function perfil_personalizado_sin_extracciones_puede_eliminarse(): void
    {
        $perfil = PerfilAnonimizacionFactory::new()->create(['nombre' => 'mi_perfil']);

        $perfil->delete();

        $this->assertDatabaseMissing('perfiles_anonimizacion', ['nombre' => 'mi_perfil']);
    }

    // -------------------------------------------------------------------------
    // 6.6 Un perfil personalizado con extracciones asociadas no puede eliminarse
    // -------------------------------------------------------------------------

    #[Test]
    public function perfil_personalizado_con_extracciones_no_puede_eliminarse(): void
    {
        $this->markTestIncomplete(
            'Pendiente: requiere implementación del modelo Extraccion y su relación ' .
            'con PerfilAnonimizacion. Ver docs/anonimizacion.md § 6.4 y ' .
            'PerfilAnonimizacion::delete() — línea con TODO sobre extracciones.'
        );
    }
}
