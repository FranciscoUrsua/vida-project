<?php

namespace Modules\Intervencion\Tests\Concerns;

use App\Models\User;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Datos de los tests de las actuaciones del profesional de encargo
 * (docs/instrucciones-cli/2026-11-encargo-actuaciones.md, «Tests»): el escenario del encargo
 * más un área «Jurídica» con una ficha y una plantilla, y un área «Psicológica» con una escala.
 */
trait ActuacionesEncargoTestSetup
{
    use EncargoTestSetup;

    protected TipoFicha $fichaJuridica;

    protected TipoEscala $escalaPsicologica;

    protected PlantillaInforme $plantillaJuridica;

    /**
     * Monta el escenario del encargo y la caja de herramientas de las dos áreas.
     *
     * @return void
     */
    protected function montarActuaciones(): void
    {
        $this->montarEncargo();

        $this->fichaJuridica = TipoFicha::create([
            'nombre' => 'Situación jurídica (test)',
            'schema' => ['campos' => [['id' => 'situacion', 'tipo' => 'texto', 'etiqueta' => 'Situación', 'descripcion' => null, 'obligatorio' => true, 'orden' => 1]]],
            'activo' => true,
        ]);

        $this->escalaPsicologica = TipoEscala::create([
            'nombre' => 'Escala psicológica (test)',
            'codigo' => 'test_psicologica',
            'schema' => ['secciones' => [[
                'id' => 'sec_1', 'titulo' => 'Sección', 'orden' => 1,
                'items' => [['id' => 'item_1', 'texto' => 'Ítem', 'orden' => 1, 'opciones' => [['valor' => 0, 'etiqueta' => 'No'], ['valor' => 1, 'etiqueta' => 'Sí']]]],
            ]]],
            'rangos_interpretacion' => ['rangos' => [['desde' => 0, 'hasta' => 1, 'etiqueta' => 'Total', 'codigo' => 'total']]],
            'activa' => true,
        ]);

        $this->plantillaJuridica = $this->plantilla('Informe jurídico (test)', [
            ['id' => 'identificacion', 'tipo' => 'automatico', 'fuente' => 'ciudadano.datos_basicos', 'titulo' => 'Identificación', 'editable' => false],
            ['id' => 'valoracion', 'tipo' => 'texto_libre', 'titulo' => 'Valoración jurídica', 'obligatorio' => true, 'contenido_plantilla' => '<p>{{ nombre_ciudadano }}</p>'],
        ]);

        $this->areaJuridica->tiposFicha()->attach($this->fichaJuridica);
        $this->areaJuridica->plantillasInforme()->attach($this->plantillaJuridica);
        $this->areaPsicologica->tiposEscala()->attach($this->escalaPsicologica);
    }

    /**
     * Plantilla de informe activa con las secciones dadas.
     *
     * @param string $nombre
     * @param list<array<string, mixed>> $secciones
     *
     * @return PlantillaInforme
     */
    protected function plantilla(string $nombre, array $secciones): PlantillaInforme
    {
        return PlantillaInforme::create([
            'nombre' => $nombre,
            'tipo_informe' => TipoInforme::InformeJuridico->value,
            'unidad_organizativa_id' => $this->ciamVallecas->unidad_organizativa_id,
            'secciones' => $secciones,
            'activa' => true,
            'creada_por' => $this->tsNorte->id,
        ]);
    }

    /**
     * Administrador del sistema, que configura las áreas en el backoffice.
     *
     * @return User
     */
    protected function administrador(): User
    {
        return $this->usuario('adm.sistema', 'adm_sistema', $this->ciamVallecas->unidadOrganizativa);
    }
}
