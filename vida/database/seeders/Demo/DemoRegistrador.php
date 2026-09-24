<?php

namespace Database\Seeders\Demo;

use App\Models\DemoWorldRegistro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Punto único de creación de entidades en mundos demo aditivos.
 *
 * Garantiza la idempotencia de `demo:load`: antes de crear una entidad busca en
 * demo_world_registros la terna (etiqueta, clave, tipo). Si existe y la entidad
 * apuntada sigue existiendo (no borrada), la reutiliza; si no, la crea y la registra.
 *
 * Lleva además la contabilidad que se muestra en el resumen de la carga:
 * entidades creadas, ya existentes y referenciadas, por tipo.
 *
 * Nunca borra ni modifica entidades: solo crea y registra.
 *
 * @see DemoWorldRegistro
 */
class DemoRegistrador
{
    /** @var array<string, int> Entidades creadas en esta ejecución, por tipo */
    private array $creados = [];

    /** @var array<string, int> Entidades reutilizadas (ya creadas por el mundo antes), por tipo */
    private array $existentes = [];

    /** @var array<string, int> Entidades preexistentes referenciadas (no registradas), por tipo */
    private array $referenciados = [];

    /**
     * @param string $etiqueta Etiqueta del mundo (p. ej. TEST_CIAM)
     */
    public function __construct(private readonly string $etiqueta) {}

    /**
     * Devuelve la entidad registrada con esa clave o la crea y la registra.
     *
     * @template TModel of Model
     *
     * @param string $clave Clave lógica única dentro del mundo (p. ej. "usuaria_037.plan")
     * @param class-string<TModel> $modelClass Clase del modelo
     * @param callable(): TModel $crear Crea y persiste la entidad si no existe
     *
     * @return TModel
     */
    public function obtenerOCrear(string $clave, string $modelClass, callable $crear): Model
    {
        $existente = $this->buscar($clave, $modelClass);

        if ($existente !== null) {
            $this->contar($this->existentes, $modelClass);

            return $existente;
        }

        $modelo = $crear();
        $this->registrar($clave, $modelo);
        $this->contar($this->creados, $modelClass);

        return $modelo;
    }

    /**
     * Busca la entidad registrada con esa clave, si sigue existiendo.
     *
     * @template TModel of Model
     *
     * @param string $clave Clave lógica dentro del mundo
     * @param class-string<TModel> $modelClass Clase del modelo
     *
     * @return TModel|null
     */
    public function buscar(string $clave, string $modelClass): ?Model
    {
        $registro = $this->registroDe($clave, $modelClass);

        if ($registro === null) {
            return null;
        }

        // Sin global scopes (p. ej. AmbitoUoScope): la carga no tiene usuario autenticado.
        // Los soft-deleted cuentan como no existentes: se volverá a crear la entidad.
        return $modelClass::query()->withoutGlobalScopes()
            ->when(in_array(SoftDeletes::class, class_uses_recursive($modelClass), true), fn ($q) => $q->whereNull('deleted_at'))
            ->find($registro->registrable_id);
    }

    /**
     * Indica si un modelo concreto fue creado por este mundo (está registrado con su etiqueta).
     *
     * @param Model $modelo Entidad a comprobar
     */
    public function esDelMundo(Model $modelo): bool
    {
        return DemoWorldRegistro::de($this->etiqueta)
            ->where('registrable_type', $modelo->getMorphClass())
            ->where('registrable_id', $modelo->getKey())
            ->exists();
    }

    /**
     * Anota una entidad reutilizada que ya había creado este mundo en una carga anterior.
     *
     * @param class-string<Model> $modelClass Clase del modelo reutilizado
     */
    public function anotarExistente(string $modelClass): void
    {
        $this->contar($this->existentes, $modelClass);
    }

    /**
     * Anota una entidad preexistente referenciada por el mundo (no se registra).
     *
     * @param class-string<Model> $modelClass Clase del modelo referenciado
     */
    public function anotarReferencia(string $modelClass): void
    {
        $this->contar($this->referenciados, $modelClass);
    }

    /**
     * Resumen de la carga por tipo de entidad.
     *
     * @return array{creados: array<string, int>, existentes: array<string, int>, referenciados: array<string, int>}
     */
    public function resumen(): array
    {
        return [
            'creados' => $this->creados,
            'existentes' => $this->existentes,
            'referenciados' => $this->referenciados,
        ];
    }

    /**
     * Número total de entidades creadas en esta ejecución.
     */
    public function totalCreados(): int
    {
        return array_sum($this->creados);
    }

    /**
     * Etiqueta del mundo.
     */
    public function etiqueta(): string
    {
        return $this->etiqueta;
    }

    /**
     * Crea o actualiza la fila de registro que apunta a la entidad.
     *
     * Si la clave ya estaba registrada pero la entidad apuntada fue borrada, se
     * reutiliza la fila (índice único etiqueta+clave+tipo) apuntándola a la nueva.
     *
     * @param string $clave Clave lógica dentro del mundo
     * @param Model $modelo Entidad recién creada
     */
    private function registrar(string $clave, Model $modelo): void
    {
        DemoWorldRegistro::updateOrCreate(
            [
                'etiqueta' => $this->etiqueta,
                'clave' => $clave,
                'registrable_type' => $modelo->getMorphClass(),
            ],
            ['registrable_id' => $modelo->getKey()]
        );
    }

    /**
     * Fila de registro para una clave y tipo.
     *
     * @param string $clave Clave lógica dentro del mundo
     * @param class-string<Model> $modelClass Clase del modelo
     */
    private function registroDe(string $clave, string $modelClass): ?DemoWorldRegistro
    {
        return DemoWorldRegistro::de($this->etiqueta)
            ->deTipo($modelClass)
            ->where('clave', $clave)
            ->first();
    }

    /**
     * Incrementa un contador por nombre corto de clase.
     *
     * @param array<string, int> $contador Contador a incrementar (por referencia)
     * @param class-string<Model> $modelClass Clase del modelo
     */
    private function contar(array &$contador, string $modelClass): void
    {
        $tipo = class_basename($modelClass);
        $contador[$tipo] = ($contador[$tipo] ?? 0) + 1;
    }
}
