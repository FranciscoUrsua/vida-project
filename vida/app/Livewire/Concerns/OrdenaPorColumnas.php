<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * Ordenación por las cabeceras de columna de una tabla Livewire.
 *
 * Guarda la columna y la dirección elegidas y valida la columna contra la lista
 * blanca del componente, de modo que ningún valor llegado del navegador alcanza
 * la consulta. Cada componente declara sus columnas con la dirección con la que
 * empiezan (`columnasOrdenables()`) y su orden por defecto (`ordenInicial()`).
 * La cabecera se pinta con `<x-op.th-orden>`.
 *
 * @property string $ordenarPor
 * @property string $direccion
 */
trait OrdenaPorColumnas
{
    /** @var string Columna de ordenación (clave de columnasOrdenables()) */
    public string $ordenarPor = '';

    /** @var string Dirección de ordenación: asc | desc */
    public string $direccion = 'asc';

    /**
     * Columnas por las que se puede ordenar, con la dirección con la que empiezan.
     *
     * @return array<string, 'asc'|'desc'>
     */
    abstract protected function columnasOrdenables(): array;

    /**
     * Orden con el que se abre la tabla.
     *
     * @return array{0: string, 1: 'asc'|'desc'} Columna y dirección.
     */
    abstract protected function ordenInicial(): array;

    /**
     * Fija el orden inicial al montar el componente (hook de Livewire por trait).
     *
     * @return void
     */
    public function mountOrdenaPorColumnas(): void
    {
        if ($this->ordenarPor === '' || ! array_key_exists($this->ordenarPor, $this->columnasOrdenables())) {
            [$this->ordenarPor, $this->direccion] = $this->ordenInicial();
        }
    }

    /**
     * Ordena por la columna indicada; si ya era la activa, invierte la dirección.
     *
     * Una columna fuera de la lista blanca se ignora.
     *
     * @param string $columna
     *
     * @return void
     */
    public function ordenar(string $columna): void
    {
        $columnas = $this->columnasOrdenables();

        if (! array_key_exists($columna, $columnas)) {
            return;
        }

        if ($this->ordenarPor === $columna) {
            $this->direccion = $this->direccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $columna;
            $this->direccion = $columnas[$columna];
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage($this->paginaDeLaTablaOrdenable());
        }
    }

    /**
     * Nombre del paginador de la tabla ordenable, que vuelve a la primera página al
     * cambiar el orden. Se sobrescribe si el componente pagina con otro nombre.
     *
     * @return string
     */
    protected function paginaDeLaTablaOrdenable(): string
    {
        return 'page';
    }

    /**
     * Dirección validada, apta para interpolar en SQL.
     *
     * @return 'asc'|'desc'
     */
    protected function direccionSql(): string
    {
        return $this->direccion === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Ordena la consulta por el nombre completo de la persona de una relación
     * BelongsTo cuyo nombre está cifrado en la BD (`ciudadano`, por lo general).
     *
     * El nombre cifrado no puede ordenarse en SQL: se descifran en PHP los nombres
     * de las personas que aparecen en la consulta (con las reglas de visibilidad
     * de la relación) y se ordena por la posición de cada fila en la lista de ids
     * resultante (`array_position`, PostgreSQL). La paginación sigue en la BD.
     *
     * @template TModelo of Model
     *
     * @param Builder<TModelo> $query Consulta a ordenar (sin paginar).
     * @param string $relacion Nombre de la relación BelongsTo del modelo de la consulta.
     * @param 'asc'|'desc' $dir
     *
     * @return void
     */
    protected function ordenarPorNombreCifrado(Builder $query, string $relacion, string $dir): void
    {
        /** @var BelongsTo<Model, TModelo> $belongsTo */
        $belongsTo = Relation::noConstraints(fn () => $query->getModel()->{$relacion}());
        $fk = $belongsTo->getQualifiedForeignKeyName();
        $tablaPersona = $belongsTo->getRelated()->getTable();
        $clave = $belongsTo->getOwnerKeyName();

        $idsEnConsulta = $query->clone()->toBase()->reorder()->select($fk)->distinct();
        $normalizar = fn (string $texto): string => Str::lower(Str::ascii(trim($texto)));

        $ids = $belongsTo->getQuery()
            ->whereIn("{$tablaPersona}.{$clave}", $idsEnConsulta)
            ->get(["{$tablaPersona}.{$clave}", "{$tablaPersona}.nombre", "{$tablaPersona}.apellido1", "{$tablaPersona}.apellido2"])
            ->sortBy(fn (Model $persona) => $normalizar((string) $persona->nombre_completo))
            ->pluck($clave)
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        $query->orderByRaw(
            "array_position(?::bigint[], {$fk}) {$dir} NULLS LAST",
            ['{'.implode(',', $ids).'}']
        );
    }

    /**
     * Ordena por el nombre visible de un usuario: el del profesional o, si no tiene
     * ficha, su email (el mismo texto que `User::nombreVisible()`). Sin usuario, al final.
     *
     * @template TModelo of Model
     *
     * @param Builder<TModelo> $query Consulta a ordenar; debe seleccionar sus columnas
     *                                (p. ej. `tabla.*`), porque se le añaden joins.
     * @param string $columnaUsuario Columna cualificada con el id del usuario (`audits.user_id`).
     * @param 'asc'|'desc' $dir
     *
     * @return void
     */
    protected function ordenarPorNombreDeUsuario(Builder $query, string $columnaUsuario, string $dir): void
    {
        $query->leftJoin('users as u_orden', 'u_orden.id', '=', $columnaUsuario)
            ->leftJoin('profesionales as p_orden', 'p_orden.id', '=', 'u_orden.profesional_id')
            ->orderByRaw(
                "LOWER(COALESCE(NULLIF(CONCAT_WS(' ', p_orden.nombre, p_orden.apellido1, p_orden.apellido2), ''), u_orden.email)) {$dir} NULLS LAST"
            );
    }
}
