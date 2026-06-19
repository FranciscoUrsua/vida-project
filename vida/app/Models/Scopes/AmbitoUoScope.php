<?php

namespace App\Models\Scopes;

use App\Models\AccesoProtegido;
use App\Models\Apunte;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Global Scope de ámbito de Unidad Organizativa para modelos sensibles.
 *
 * Restringe automáticamente todas las consultas sobre modelos sensibles
 * (HistoriaSocial, Ciudadano, Apunte, PlanDeIntervencion) para devolver
 * únicamente los registros accesibles para el usuario autenticado.
 *
 * Comportamiento por caso:
 *   - Sin usuario autenticado (consola, artisan, tests sin login): NO filtra.
 *   - Usuario con rol adm_sistema: NO filtra (acceso global de configuración).
 *   - Cualquier otro usuario autenticado: devuelve solo los registros de
 *     su UO o descendientes, MÁS los registros de otras UOs para los que
 *     tenga AccesoProtegido vigente.
 *
 * El scope se desactiva con withoutGlobalScope(AmbitoUoScope::class) para
 * auditorías, comandos de consola o consultas de supervisión global.
 *
 * IMPORTANTE: Este scope filtra por la columna $uoForeignKey del modelo.
 * Cada modelo que lo aplica debe declarar la FK adecuada:
 *   - HistoriaSocial → unidad_organizativa_id (directo)
 *   - Ciudadano → vía Historia Social (consulta subquery)
 *   - Apunte → historia_social_id → HistoriaSocial.unidad_organizativa_id
 *   - PlanDeIntervencion → historia_id → HistoriaSocial.unidad_organizativa_id
 *
 * @see HistoriaSocial
 * @see Ciudadano
 * @see Apunte
 * @see PlanDeIntervencion
 */
class AmbitoUoScope implements Scope
{
    /**
     * Nombre de la propiedad del modelo que indica la FK a unidades_organizativas.
     * Si es null, se resuelve vía subquery a historias_sociales.
     */
    public const ATTR_UO_COLUMN = 'ambitoUoColumn';

    /**
     * Nombre de la propiedad del modelo que indica la FK a historias_sociales.
     * Se usa cuando el modelo no tiene FK directa a UO.
     */
    public const ATTR_HISTORIA_COLUMN = 'ambitoHistoriaColumn';

    /**
     * Nombre de la propiedad que indica que el modelo ES el ciudadano,
     * de modo que el ámbito se resuelve buscando la UO en sus Historias Sociales.
     * El valor de esta propiedad indica el campo PK del modelo (normalmente 'id').
     */
    public const ATTR_CIUDADANO_PK = 'ambitoCiudadanoPk';

    /**
     * Aplica el filtro de ámbito de UO a la consulta.
     *
     * @param Builder<Model> $builder Consulta a modificar.
     * @param Model $model Modelo al que se aplica el scope.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Sin usuario autenticado → no filtrar (consola, artisan, tests sin login)
        if (! auth()->check()) {
            return;
        }

        $usuario = auth()->user();

        // adm_sistema → acceso global, no filtrar
        if ($usuario->hasRole('adm_sistema')) {
            return;
        }

        // Obtener los IDs de UO del usuario (subtree: propia + descendientes)
        $uoIds = $usuario->uoSubtreeIds();

        // Obtener los ciudadano_ids con AccesoProtegido vigente para este usuario
        $ciudadanosConAcceso = AccesoProtegido::where('usuario_id', $usuario->id)
            ->where('estado', 'aprobado')
            ->where(function ($q) {
                $q->whereNull('acceso_valido_hasta')
                    ->orWhere('acceso_valido_hasta', '>=', now());
            })
            ->pluck('ciudadano_id')
            ->toArray();

        // Determinar la estrategia de filtrado según la propiedad del modelo
        $uoColumn = $model->{self::ATTR_UO_COLUMN} ?? null;
        $historiaColumn = $model->{self::ATTR_HISTORIA_COLUMN} ?? null;
        $ciudadanoPk = $model->{self::ATTR_CIUDADANO_PK} ?? null;

        if ($uoColumn !== null) {
            // Estrategia directa: el modelo tiene FK a unidades_organizativas
            $this->aplicarFiltroDirecto($builder, $model, $uoColumn, $uoIds, $ciudadanosConAcceso);
        } elseif ($historiaColumn !== null) {
            // Estrategia indirecta vía Historia Social
            $this->aplicarFiltroViaHistoria($builder, $model, $historiaColumn, $uoIds, $ciudadanosConAcceso);
        } elseif ($ciudadanoPk !== null) {
            // Estrategia ciudadano: el modelo ES Ciudadano; la UO se resuelve por sus Historias Sociales
            $this->aplicarFiltroCiudadano($builder, $model, $ciudadanoPk, $uoIds, $ciudadanosConAcceso);
        }
        // Si no se puede determinar la estrategia, no filtrar (seguro por defecto para no romper consultas)
    }

    /**
     * Aplica filtro directo cuando el modelo tiene FK a unidades_organizativas.
     *
     * Para modelos con privada (Apunte): añade también la cláusula
     * `(privada = false OR profesional_id = <id>)`.
     *
     * @param Builder<Model> $builder
     * @param string $uoColumn Nombre de la columna FK a UO
     * @param array<int> $uoIds IDs de UO accesibles
     * @param array<int> $ciudadanosConAcceso IDs de ciudadanos con acceso protegido vigente
     */
    private function aplicarFiltroDirecto(
        Builder $builder,
        Model $model,
        string $uoColumn,
        array $uoIds,
        array $ciudadanosConAcceso
    ): void {
        $tabla = $model->getTable();

        $builder->where(function (Builder $q) use ($tabla, $uoColumn, $uoIds, $ciudadanosConAcceso) {
            // Condición A: el registro pertenece al ámbito de UO del usuario
            $q->whereIn("{$tabla}.{$uoColumn}", $uoIds);

            // Condición B: el registro corresponde a un ciudadano con acceso protegido vigente
            if (! empty($ciudadanosConAcceso)) {
                $q->orWhereIn("{$tabla}.ciudadano_id", $ciudadanosConAcceso);
            }
        });
    }

    /**
     * Aplica filtro vía subquery a historias_sociales cuando el modelo
     * no tiene FK directa a unidades_organizativas.
     *
     * @param Builder<Model> $builder
     * @param string $historiaColumn Nombre de la columna FK a historias_sociales
     * @param array<int> $uoIds IDs de UO accesibles
     * @param array<int> $ciudadanosConAcceso IDs de ciudadanos con acceso protegido vigente
     */
    private function aplicarFiltroViaHistoria(
        Builder $builder,
        Model $model,
        string $historiaColumn,
        array $uoIds,
        array $ciudadanosConAcceso
    ): void {
        $tabla = $model->getTable();

        $builder->where(function (Builder $q) use ($tabla, $historiaColumn, $uoIds, $ciudadanosConAcceso) {
            // Condición A: la Historia del registro pertenece al ámbito de UO del usuario
            $q->whereIn("{$tabla}.{$historiaColumn}", function ($sub) use ($uoIds) {
                $sub->select('id')
                    ->from('historias_sociales')
                    ->whereIn('unidad_organizativa_id', $uoIds)
                    ->whereNull('deleted_at');
            });

            // Condición B: la Historia del registro corresponde a un ciudadano con acceso protegido vigente
            if (! empty($ciudadanosConAcceso)) {
                $q->orWhereIn("{$tabla}.{$historiaColumn}", function ($sub) use ($ciudadanosConAcceso) {
                    $sub->select('id')
                        ->from('historias_sociales')
                        ->whereIn('ciudadano_id', $ciudadanosConAcceso)
                        ->whereNull('deleted_at');
                });
            }
        });
    }

    /**
     * Aplica filtro para el modelo Ciudadano, cuya UO se determina
     * buscando Historias Sociales asociadas al ciudadano.
     *
     * Un ciudadano pertenece al ámbito del usuario si tiene al menos una
     * Historia Social asignada a alguna UO en el árbol del usuario.
     * También se incluyen ciudadanos con AccesoProtegido vigente.
     *
     * @param Builder<Model> $builder
     * @param string $ciudadanoPk Columna PK del ciudadano (normalmente 'id')
     * @param array<int> $uoIds IDs de UO accesibles
     * @param array<int> $ciudadanosConAcceso IDs de ciudadanos con acceso protegido vigente
     */
    private function aplicarFiltroCiudadano(
        Builder $builder,
        Model $model,
        string $ciudadanoPk,
        array $uoIds,
        array $ciudadanosConAcceso
    ): void {
        $tabla = $model->getTable();

        $builder->where(function (Builder $q) use ($tabla, $ciudadanoPk, $uoIds, $ciudadanosConAcceso) {
            // Condición A: el ciudadano tiene al menos una Historia Social en el ámbito de UO
            $q->whereIn("{$tabla}.{$ciudadanoPk}", function ($sub) use ($uoIds) {
                $sub->select('ciudadano_id')
                    ->from('historias_sociales')
                    ->whereIn('unidad_organizativa_id', $uoIds)
                    ->whereNull('deleted_at');
            });

            // Condición B: el ciudadano tiene AccesoProtegido vigente para este usuario
            if (! empty($ciudadanosConAcceso)) {
                $q->orWhereIn("{$tabla}.{$ciudadanoPk}", $ciudadanosConAcceso);
            }
        });
    }
}
