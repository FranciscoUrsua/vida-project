<?php

namespace App\Traits;

use App\Models\Version;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait que añade versionado automático a un modelo Eloquent.
 *
 * Al actualizar un registro, guarda un snapshot completo del estado
 * anterior en la tabla `versiones`. Esto permite consultar el estado
 * de cualquier entidad en cualquier fecha pasada.
 *
 * El snapshot guarda el estado ANTERIOR al cambio. El registro
 * principal siempre tiene el estado actual.
 *
 * Para conocer el estado en una fecha X:
 * - Si X es posterior al último cambio → el registro actual es la respuesta.
 * - Si no → la versión más reciente anterior a X.
 *
 * @see docs/modulo-usuarios-permisos.md sección 5.4
 */
trait Versionable
{
    /**
     * Registra el hook de versionado al arrancar el modelo.
     */
    protected static function bootVersionable(): void
    {
        static::updating(function ($model) {
            Version::create([
                'versionable_type' => $model->getMorphClass(),
                'versionable_id' => $model->getKey(),
                'datos' => $model->getOriginal(),
                'usuario_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Todas las versiones históricas de este registro.
     *
     * @return MorphMany<Version, $this>
     */
    public function versiones(): MorphMany
    {
        return $this->morphMany(Version::class, 'versionable')->latest();
    }
}
