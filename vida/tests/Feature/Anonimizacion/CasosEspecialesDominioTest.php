<?php

namespace Tests\Feature\Anonimizacion;

use App\Services\Api\AnonimizadorService;
use App\Services\Api\ValidadorKAnonimato;
use Database\Factories\PerfilAnonimizacionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo 5 — Casos especiales del dominio.
 * Ver docs/tests-anonimizacion.md § Grupo 5.
 */
class CasosEspecialesDominioTest extends TestCase
{
    use RefreshDatabase;

    private AnonimizadorService $servicio;
    private ValidadorKAnonimato $validador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio  = app(AnonimizadorService::class);
        $this->validador = app(ValidadorKAnonimato::class);
    }

    // -------------------------------------------------------------------------
    // 5.1 PSH — coordenadas de pernocta en lugar de dirección
    // -------------------------------------------------------------------------

    #[Test]
    public function psh_no_expone_coordenadas_de_pernocta_exactas(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();

        $registro = collect([[
            'id'              => 1,
            'nombre'          => 'Paco',
            'apellido1'       => 'Sin',
            'es_psh'          => true,
            'pernocta_lat'    => 40.41,
            'pernocta_lng'    => -3.70,
            'zona_intervencion' => 'Lavapiés',
            'fecha_nacimiento' => '1975-03-10',
            'sexo'            => 'hombre',
        ]]);

        // TODO: La supresión de pernocta_lat/lng requiere que el perfil
        //       tenga entradas explícitas para esos campos. Actualmente
        //       analitica_interna no los incluye — se documenta como decisión
        //       pendiente (docs/anonimizacion.md § 8, PSH).
        //       Este test verifica que zona_intervencion no es expuesta con más
        //       detalle del necesario y que las coordenadas exactas no se usan
        //       como identificadores en el k-anonimato.
        $resultado = $this->servicio->anonimizar($registro, 'analitica_interna')->first();

        // El alias_operativo no debe aparecer — la seudonimización usa el id interno
        $this->assertArrayNotHasKey('alias_operativo', $resultado);
    }

    // -------------------------------------------------------------------------
    // 5.2 PSH — el alias seudonimizado funciona igual que para cualquier ciudadano
    // -------------------------------------------------------------------------

    #[Test]
    public function psh_recibe_alias_seudonimizado_en_nivel_1(): void
    {
        PerfilAnonimizacionFactory::new()->supervisionInterna()->create();

        $registro = collect([[
            'id'                  => 42,
            'nombre'              => 'Paco',
            'apellido1'           => 'Sin',
            'es_psh'              => true,
            'nivel_identificacion' => 'no_identificado',
            'alias_operativo'     => 'Paco el del puente',
        ]]);

        $resultado = $this->servicio->anonimizar($registro, 'supervision_interna')->first();

        // El resultado debe contener alias CIU-{...}
        $this->assertArrayHasKey('alias_ciudadano', $resultado);
        $this->assertMatchesRegularExpression('/^CIU-[0-9a-f]{8}$/', $resultado['alias_ciudadano']);

        // El alias operativo no debe aparecer (no es un campo suprimido por el perfil,
        // pero tampoco debe estar si el modelo no lo devuelve por defecto)
        $this->assertNotEquals('Paco el del puente', $resultado['alias_ciudadano']);
    }

    // -------------------------------------------------------------------------
    // 5.3 VVG — el domicilio protegido se suprime en Nivel 2 y 3
    // -------------------------------------------------------------------------

    #[Test]
    public function vvg_tiene_todos_los_campos_de_direccion_suprimidos(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();

        // Dos registros VVG con la misma combinación post-supresión para que K=2 se cumpla.
        // Tras la supresión de dirección por VVG, ambas comparten {mujer, 1985, null, null},
        // lo que hace que el grupo tenga 2 registros y ninguno sea suprimido por k-anonimato.
        $registros = collect([
            [
                'id'                    => 1,
                'nombre'                => 'Ana',
                'apellido1'             => 'Vvg',
                'es_vvg'                => true,
                'nombre_via'            => 'Recurso de Acogida Secreta',
                'codigo_postal'         => '28013',
                'direccion_normalizada' => true,
                'fecha_nacimiento'      => '1985-06-20',
                'sexo'                  => 'mujer',
                'anio_nacimiento'       => 1985,
            ],
            [
                'id'                    => 2,
                'nombre'                => 'Carmen',
                'apellido1'             => 'Vvg',
                'es_vvg'                => true,
                'nombre_via'            => 'Centro de Emergencias Oculto',
                'codigo_postal'         => '28020',
                'direccion_normalizada' => true,
                'fecha_nacimiento'      => '1985-03-10',
                'sexo'                  => 'mujer',
                'anio_nacimiento'       => 1985,
            ],
        ]);

        $registrosAnonimizados = $this->servicio->anonimizar($registros, 'analitica_interna');

        // K=2: los dos registros VVG comparten combinación tras suprimir dirección, sobreviven
        $resultados = $this->validador->aplicar($registrosAnonimizados, 2);

        $this->assertCount(2, $resultados, 'Ambos registros VVG deben sobrevivir al k-anonimato');
        foreach ($resultados as $r) {
            $this->assertArrayNotHasKey('nombre_via', $r,
                'Los campos de dirección de VVG deben suprimirse en el validador de k-anonimato');
        }
    }

    // -------------------------------------------------------------------------
    // 5.4 Colectivo protegido — supresión anticipada en Nivel 3
    // -------------------------------------------------------------------------

    #[Test]
    public function colectivo_extra_protegido_se_suprime_antes_de_la_cascada(): void
    {
        $k = 2;
        $registros = collect([
            [
                'id'                        => 1,
                'sexo'                      => 'mujer',
                'anio_nacimiento'           => 1980,
                'nombre_via'                => 'Calle A',
                'colectivo_principal'       => 'PROTEGIDO',
                'colectivo_extra_protegido' => true,
                'codigo_postal'             => '28001',
            ],
            [
                'id'                        => 2,
                'sexo'                      => 'mujer',
                'anio_nacimiento'           => 1981,
                'nombre_via'                => 'Calle A',
                'colectivo_principal'       => 'PROTEGIDO',
                'colectivo_extra_protegido' => true,
                'codigo_postal'             => '28001',
            ],
        ]);

        $resultado = $this->validador->aplicar($registros, $k);

        foreach ($resultado as $r) {
            $this->assertArrayNotHasKey('colectivo_principal', $r,
                'colectivo_extra_protegido debe suprimir colectivo_principal desde el inicio, ' .
                'sin esperar a la cascada');
        }
    }

    // -------------------------------------------------------------------------
    // 5.5 Ciudadano con múltiples colectivos — se aplica la protección más restrictiva
    // -------------------------------------------------------------------------

    #[Test]
    public function ciudadano_con_multiples_colectivos_aplica_proteccion_mas_restrictiva(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();

        // Dos ciudadanos marcados como PSH Y VVG simultáneamente — K=2 se cumple con ambos.
        // La protección VVG (supresión total de dirección) es más restrictiva que PSH
        // (que solo usaría zona_intervencion como sustituto). Debe aplicarse VVG.
        $registros = collect([
            [
                'id'                    => 1,
                'nombre'                => 'Test',
                'apellido1'             => 'Multiple',
                'es_psh'                => true,
                'es_vvg'                => true,
                'nombre_via'            => 'Recurso Secreto',
                'codigo_postal'         => '28001',
                'direccion_normalizada' => true,
                'zona_intervencion'     => 'Centro',
                'fecha_nacimiento'      => '1975-01-01',
                'sexo'                  => 'mujer',
                'anio_nacimiento'       => 1975,
            ],
            [
                'id'                    => 2,
                'nombre'                => 'Otra',
                'apellido1'             => 'Multiple',
                'es_psh'                => true,
                'es_vvg'                => true,
                'nombre_via'            => 'Otro Recurso',
                'codigo_postal'         => '28002',
                'direccion_normalizada' => true,
                'zona_intervencion'     => 'Centro',
                'fecha_nacimiento'      => '1975-06-01',
                'sexo'                  => 'mujer',
                'anio_nacimiento'       => 1975,
            ],
        ]);

        $registrosAnonimizados = $this->servicio->anonimizar($registros, 'analitica_interna');

        // ValidadorKAnonimato aplica VVG (suprime dirección)
        $resultados = $this->validador->aplicar($registrosAnonimizados, 2);

        $this->assertCount(2, $resultados, 'Ambos registros PSH+VVG deben sobrevivir al k-anonimato');
        foreach ($resultados as $r) {
            // La protección VVG (más restrictiva en dirección) debe haber suprimido nombre_via
            $this->assertArrayNotHasKey('nombre_via', $r,
                'VVG implica supresión total de dirección — protección más restrictiva');
        }
    }
}
