<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Eloquent\Model;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Sala;
use Modules\Centro\Models\TipoActividad;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Usuarios\Models\Cargo;

/**
 * Resuelve la sección 'existentes' de un mundo aditivo contra la base de datos.
 *
 * Cada entrada se busca por sus criterios 'buscar_por' y debe devolver exactamente
 * un registro. Si no hay ninguno y la entrada tiene 'crear_si_no_existe', se crea
 * (y se registra con la etiqueta del mundo); si no, la carga falla. Una búsqueda
 * ambigua (más de un registro) siempre hace fallar la carga.
 *
 * Las entidades encontradas nunca se modifican: solo se leen.
 *
 * @see DemoWorldLoader::validarExistentes()
 */
class DemoReferenciaResolver
{
    /** @var array<string, class-string<Model>> Modelo de cada sección de 'existentes' */
    private const MODELOS = [
        'centros' => Centro::class,
        'tipos_plan' => TipoPlan::class,
        'cargos' => Cargo::class,
        'salas' => Sala::class,
        'tipos_actividad' => TipoActividad::class,
    ];

    /**
     * Atributos por defecto al crear una entidad con 'crear_si_no_existe'.
     *
     * @var array<string, array<string, mixed>>
     */
    private const DEFECTOS_CREACION = [
        'cargos' => ['activo' => true],
        'salas' => ['activa' => true, 'accesible' => false],
        'tipos_actividad' => ['activo' => true],
    ];

    /**
     * @param DemoRegistrador $registrador Registro de lo creado por el mundo
     */
    public function __construct(private readonly DemoRegistrador $registrador) {}

    /**
     * Resuelve todas las entradas de 'existentes'.
     *
     * @param array<string, list<array<string, mixed>>> $existentes Sección 'existentes' validada
     *
     * @return array<string, array<string, Model>> Modelos por sección e id local del YAML
     *
     * @throws \RuntimeException Si una referencia no se encuentra o es ambigua
     */
    public function resolver(array $existentes): array
    {
        $resueltos = array_fill_keys(array_keys(self::MODELOS), []);

        // El orden importa: las salas se buscan dentro de un centro ya resuelto.
        foreach (DemoWorldLoader::SECCIONES_EXISTENTES as $seccion) {
            foreach ($existentes[$seccion] ?? [] as $entrada) {
                $resueltos[$seccion][$entrada['id']] = $this->resolverEntrada($seccion, $entrada, $resueltos);
            }
        }

        return $resueltos;
    }

    /**
     * Resuelve una entrada: la busca, la crea si procede o falla con un mensaje explícito.
     *
     * @param string $seccion Sección de 'existentes' (centros, salas...)
     * @param array<string, mixed> $entrada Entrada del YAML
     * @param array<string, array<string, Model>> $resueltos Entradas ya resueltas (para salas)
     *
     * @throws \RuntimeException Si no se encuentra y no se puede crear, o si es ambigua
     */
    private function resolverEntrada(string $seccion, array $entrada, array $resueltos): Model
    {
        $modelClass = self::MODELOS[$seccion];
        $criterios = $entrada['buscar_por'];
        $ref = "existentes.{$seccion} '{$entrada['id']}' (buscar_por: ".json_encode($criterios, JSON_UNESCAPED_UNICODE).')';

        if ($seccion === 'salas') {
            $criterios['centro_id'] = $resueltos['centros'][$entrada['centro']]->getKey();
        }

        $query = $modelClass::query()->withoutGlobalScopes()->where($criterios);

        if (method_exists($modelClass, 'bootSoftDeletes')) {
            $query->whereNull('deleted_at');
        }

        $encontrados = $query->limit(2)->get();

        if ($encontrados->count() > 1) {
            throw new \RuntimeException("{$ref}: la búsqueda es ambigua (devuelve más de un registro).");
        }

        if ($encontrados->count() === 1) {
            $modelo = $encontrados->first();

            // Una entidad creada por este mundo en una carga anterior no es una referencia ajena.
            if ($this->registrador->esDelMundo($modelo)) {
                $this->registrador->anotarExistente($modelClass);
            } else {
                $this->registrador->anotarReferencia($modelClass);
            }

            return $modelo;
        }

        $crear = $entrada['crear_si_no_existe'] ?? false;

        if ($crear === false) {
            throw new \RuntimeException("{$ref}: no existe y la entrada no tiene 'crear_si_no_existe'.");
        }

        $atributos = array_merge(
            self::DEFECTOS_CREACION[$seccion] ?? [],
            is_array($crear) ? $crear : [],
            $criterios
        );

        return $this->registrador->obtenerOCrear(
            "existentes.{$seccion}.{$entrada['id']}",
            $modelClass,
            fn () => $modelClass::create($atributos)
        );
    }
}
