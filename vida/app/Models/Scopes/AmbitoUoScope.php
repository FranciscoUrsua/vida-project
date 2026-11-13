<?php

namespace App\Models\Scopes;

use App\Models\AccesoProtegido;
use App\Models\Apunte;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Services\AmbitoUsuario;
use Closure;
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
 *     su ámbito (RN-13), sin las personas protegidas de los centros donde no
 *     las gestiona (RN-17, AmbitoUsuario::restringirHistoriasGestionables()),
 *     MÁS los registros de personas para las que tenga AccesoProtegido vigente.
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

        // Historias que gestiona (ámbito y RN-17), como subconsulta de ids
        $gestionables = fn ($sub) => app(AmbitoUsuario::class)
            ->restringirHistoriasGestionables($sub->from('historias_sociales')->whereNull('historias_sociales.deleted_at'), $usuario);

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
            $this->aplicarFiltroDirecto($builder, $model, $uoColumn, $usuario->uoSubtreeIds(), $ciudadanosConAcceso, $gestionables);
        } elseif ($historiaColumn !== null) {
            // Estrategia indirecta vía Historia Social
            $this->aplicarFiltroViaHistoria($builder, $model, $historiaColumn, $ciudadanosConAcceso, $gestionables);
        } elseif ($ciudadanoPk !== null) {
            // Estrategia ciudadano: el modelo ES Ciudadano; la UO se resuelve por sus Historias Sociales
            $this->aplicarFiltroCiudadano($builder, $model, $ciudadanoPk, $ciudadanosConAcceso, $gestionables);
        }
        // Si no se puede determinar la estrategia, no filtrar (seguro por defecto para no romper consultas)
    }

    /**
     * Aplica filtro directo cuando el modelo tiene FK a unidades_organizativas.
     *
     * Para la propia historia, el filtro es la subconsulta de historias
     * gestionables (incluye RN-17); otro modelo con FK directa a UO se filtra
     * solo por el ámbito.
     *
     * @param Builder<Model> $builder
     * @param Model $model
     * @param string $uoColumn Nombre de la columna FK a UO
     * @param array<int> $uoIds IDs de UO del ámbito
     * @param array<int> $ciudadanosConAcceso IDs de ciudadanos con acceso protegido vigente
     * @param Closure $gestionables Subconsulta de historias gestionables
     */
    private function aplicarFiltroDirecto(
        Builder $builder,
        Model $model,
        string $uoColumn,
        array $uoIds,
        array $ciudadanosConAcceso,
        Closure $gestionables
    ): void {
        $tabla = $model->getTable();

        $builder->where(function (Builder $q) use ($tabla, $uoColumn, $uoIds, $ciudadanosConAcceso, $gestionables) {
            // Condición A: el registro pertenece al ámbito del usuario (y, si es una historia, la gestiona: RN-17)
            if ($tabla === 'historias_sociales') {
                $q->whereIn("{$tabla}.id", fn ($sub) => $gestionables($sub->select('historias_sociales.id')));
            } else {
                $q->whereIn("{$tabla}.{$uoColumn}", $uoIds);
            }

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
     * @param Model $model
     * @param string $historiaColumn Nombre de la columna FK a historias_sociales
     * @param array<int> $ciudadanosConAcceso IDs de ciudadanos con acceso protegido vigente
     * @param Closure $gestionables Subconsulta de historias gestionables
     */
    private function aplicarFiltroViaHistoria(
        Builder $builder,
        Model $model,
        string $historiaColumn,
        array $ciudadanosConAcceso,
        Closure $gestionables
    ): void {
        $tabla = $model->getTable();

        $builder->where(function (Builder $q) use ($tabla, $historiaColumn, $ciudadanosConAcceso, $gestionables) {
            // Condición A: el usuario gestiona la historia del registro (ámbito y RN-17)
            $q->whereIn("{$tabla}.{$historiaColumn}", fn ($sub) => $gestionables($sub->select('historias_sociales.id')));

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
     * Historia Social que el usuario gestiona (ámbito y RN-17).
     * También se incluyen ciudadanos con AccesoProtegido vigente.
     *
     * @param Builder<Model> $builder
     * @param Model $model
     * @param string $ciudadanoPk Columna PK del ciudadano (normalmente 'id')
     * @param array<int> $ciudadanosConAcceso IDs de ciudadanos con acceso protegido vigente
     * @param Closure $gestionables Subconsulta de historias gestionables
     */
    private function aplicarFiltroCiudadano(
        Builder $builder,
        Model $model,
        string $ciudadanoPk,
        array $ciudadanosConAcceso,
        Closure $gestionables
    ): void {
        $tabla = $model->getTable();

        $builder->where(function (Builder $q) use ($tabla, $ciudadanoPk, $ciudadanosConAcceso, $gestionables) {
            // Condición A: el ciudadano tiene al menos una Historia Social que el usuario gestiona
            $q->whereIn("{$tabla}.{$ciudadanoPk}", fn ($sub) => $gestionables($sub->select('historias_sociales.ciudadano_id')));

            // Condición B: el ciudadano tiene AccesoProtegido vigente para este usuario
            if (! empty($ciudadanosConAcceso)) {
                $q->orWhereIn("{$tabla}.{$ciudadanoPk}", $ciudadanosConAcceso);
            }
        });
    }
}
