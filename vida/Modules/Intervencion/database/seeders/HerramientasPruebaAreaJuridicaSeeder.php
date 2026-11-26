<?php

namespace Modules\Intervencion\Database\Seeders;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Models\AreaEncargo;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Herramientas de prueba del área jurídica (26 nov 2026), para probar en staging el circuito
 * completo de las actuaciones del encargo (docs/instrucciones-cli/2026-11-encargo-actuaciones.md):
 * una ficha de valoración, una escala y una plantilla de informe, asociadas al área `juridica`.
 *
 * No está en `DatabaseSeeder` ni en una migración: son herramientas de prueba y no deben llegar
 * a producción. Se ejecuta a mano (`php artisan db:seed --class=...`). Es idempotente: no
 * duplica nada ni toca lo que ya exista con el mismo nombre o código.
 *
 * La escala es un instrumento orientativo sin validar, y así lo dice su descripción. La
 * plantilla solo usa fuentes permitidas en un informe del encargo (EA-9): si no, el pivote del
 * área la rechazaría.
 */
class HerramientasPruebaAreaJuridicaSeeder extends Seeder
{
    /** Nombre de la ficha de valoración. */
    public const FICHA = 'Valoración jurídica inicial (prueba)';

    /** Código de la escala. */
    public const ESCALA = 'prueba_riesgo_vivienda';

    /** Nombre de la plantilla de informe. */
    public const PLANTILLA = 'Informe jurídico del encargo (prueba)';

    /**
     * Crea las tres herramientas, si no existen, y las asocia al área jurídica.
     *
     * @return void
     */
    public function run(): void
    {
        $area = AreaEncargo::where('codigo', 'juridica')->first();
        if ($area === null) {
            $this->command?->warn('No existe el área «juridica»: no se siembra nada.');

            return;
        }

        $area->tiposFicha()->syncWithoutDetaching([$this->ficha()->id]);
        $area->tiposEscala()->syncWithoutDetaching([$this->escala()->id]);

        $plantilla = $this->plantilla();
        if ($plantilla !== null) {
            $area->plantillasInforme()->syncWithoutDetaching([$plantilla->id]);
        }
    }

    /**
     * Ficha de valoración jurídica.
     *
     * @return TipoFicha
     */
    private function ficha(): TipoFicha
    {
        return TipoFicha::firstOrCreate(['nombre' => self::FICHA], [
            'descripcion' => 'Primera valoración de la situación jurídica en un encargo profesional. Herramienta de prueba.',
            'uso' => 'valoracion',
            'activo' => true,
            'schema' => ['campos' => [
                ['id' => 'materia', 'tipo' => 'select', 'orden' => 1, 'etiqueta' => 'Materia', 'descripcion' => null, 'obligatorio' => true,
                    'opciones' => ['Vivienda', 'Extranjería', 'Familia', 'Violencia de género', 'Deudas y consumo', 'Laboral', 'Otra']],
                ['id' => 'urgencia', 'tipo' => 'select', 'orden' => 2, 'etiqueta' => 'Urgencia', 'descripcion' => null, 'obligatorio' => true,
                    'opciones' => ['Ordinaria', 'Preferente', 'Urgente']],
                ['id' => 'procedimiento_abierto', 'tipo' => 'booleano', 'orden' => 3, 'etiqueta' => 'Hay un procedimiento judicial abierto', 'descripcion' => null, 'obligatorio' => false],
                ['id' => 'organo_judicial', 'tipo' => 'texto', 'orden' => 4, 'etiqueta' => 'Órgano judicial y número de procedimiento', 'descripcion' => null, 'obligatorio' => false],
                ['id' => 'proxima_actuacion', 'tipo' => 'fecha', 'orden' => 5, 'etiqueta' => 'Fecha de la próxima actuación', 'descripcion' => 'Vista, plazo o lanzamiento señalado.', 'obligatorio' => false],
                ['id' => 'situacion', 'tipo' => 'texto', 'orden' => 6, 'etiqueta' => 'Descripción de la situación', 'descripcion' => null, 'obligatorio' => true],
            ]],
        ]);
    }

    /**
     * Escala orientativa de riesgo de pérdida de la vivienda (0 a 10).
     *
     * @return TipoEscala
     */
    private function escala(): TipoEscala
    {
        $item = fn (string $id, int $orden, string $texto, array $etiquetas): array => [
            'id' => $id,
            'orden' => $orden,
            'texto' => $texto,
            'opciones' => array_map(fn (int $valor, string $etiqueta): array => ['valor' => $valor, 'etiqueta' => $etiqueta], array_keys($etiquetas), $etiquetas),
        ];

        return TipoEscala::firstOrCreate(['codigo' => self::ESCALA], [
            'nombre' => 'Riesgo de pérdida de la vivienda (prueba)',
            'descripcion' => 'Instrumento orientativo SIN VALIDAR, creado para probar las escalas del encargo profesional. No usar para decidir.',
            'instrucciones_aplicacion' => 'Marque la opción que mejor describe la situación actual en cada ítem. A mayor puntuación, mayor riesgo.',
            'confirmar_instrucciones' => false,
            'activa' => true,
            'schema' => ['secciones' => [[
                'id' => 'riesgo',
                'titulo' => 'Situación de la vivienda',
                'orden' => 1,
                'items' => [
                    $item('pagos', 1, 'Pago del alquiler o la hipoteca', ['Al día', 'Retraso de menos de tres meses', 'Retraso de tres meses o más']),
                    $item('procedimiento', 2, 'Procedimiento judicial', ['No hay', 'Demanda presentada', 'Lanzamiento señalado']),
                    $item('ingresos', 3, 'Ingresos del hogar', ['Estables', 'Irregulares', 'Sin ingresos']),
                    $item('apoyos', 4, 'Red de apoyo', ['Suficiente', 'Limitada', 'Inexistente']),
                    $item('alternativa', 5, 'Alternativa habitacional', ['Disponible', 'Solo temporal', 'Ninguna']),
                ],
            ]]],
            'rangos_interpretacion' => ['rangos' => [
                ['desde' => 0, 'hasta' => 3, 'codigo' => 'bajo', 'etiqueta' => 'Riesgo bajo'],
                ['desde' => 4, 'hasta' => 6, 'codigo' => 'moderado', 'etiqueta' => 'Riesgo moderado'],
                ['desde' => 7, 'hasta' => 10, 'codigo' => 'alto', 'etiqueta' => 'Riesgo alto'],
            ]],
        ]);
    }

    /**
     * Plantilla de informe jurídico con fuentes permitidas en un encargo (EA-9). Null si no hay
     * usuarios ni UO raíz a las que atribuirla (instalación vacía).
     *
     * La plantilla pertenece a la UO raíz, como las de la fase 5; en el asistente de la Historia
     * Social la verían también los TSR, con la sección del encargo vacía (BACKLOG).
     *
     * @return PlantillaInforme|null
     */
    private function plantilla(): ?PlantillaInforme
    {
        $existente = PlantillaInforme::where('nombre', self::PLANTILLA)->first();
        if ($existente !== null) {
            return $existente;
        }

        $uoRaiz = UnidadOrganizativa::whereNull('parent_id')->orderBy('id')->value('id');
        $autor = User::orderBy('id')->value('id');
        if ($uoRaiz === null || $autor === null) {
            return null;
        }

        return PlantillaInforme::create([
            'unidad_organizativa_id' => $uoRaiz,
            'nombre' => self::PLANTILLA,
            'descripcion' => 'Informe del profesional de encargo con los datos de la persona, del encargo y la valoración jurídica. Herramienta de prueba.',
            'tipo_informe' => TipoInforme::InformeJuridico->value,
            'activa' => true,
            'creada_por' => $autor,
            'secciones' => [
                ['id' => 'persona', 'titulo' => 'Datos de la persona', 'tipo' => 'automatico', 'fuente' => 'ciudadano.datos_basicos', 'editable' => false],
                ['id' => 'encargo', 'titulo' => 'Encargo', 'tipo' => 'automatico', 'fuente' => 'encargo.datos', 'editable' => false],
                ['id' => 'valoracion', 'titulo' => 'Valoración jurídica', 'tipo' => 'texto_libre', 'obligatorio' => true,
                    'instrucciones' => 'Describa la situación jurídica y las actuaciones realizadas.',
                    'contenido_plantilla' => '<p>Atendida {{ nombre_ciudadano }}, se informa de lo siguiente:</p>'],
                ['id' => 'recomendaciones', 'titulo' => 'Recomendaciones', 'tipo' => 'texto_libre', 'obligatorio' => false],
                ['id' => 'profesional', 'titulo' => 'Profesional', 'tipo' => 'automatico', 'fuente' => 'profesional.datos', 'editable' => false],
            ],
        ]);
    }
}
