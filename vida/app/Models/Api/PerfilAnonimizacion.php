<?php

namespace App\Models\Api;

use App\Exceptions\Anonimizacion\PerfilConExtraccionesException;
use App\Exceptions\Anonimizacion\PerfilSistemaNoEliminableException;
use Database\Factories\PerfilAnonimizacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @use HasFactory<PerfilAnonimizacionFactory>
 *
 * Perfil de anonimización.
 *
 * Define, campo a campo, qué técnica se aplica a un conjunto de registros para
 * un caso de uso concreto. Los perfiles son configurables desde el backoffice
 * y están versionados: cada modificación de 'campos' o 'k_valor' genera un
 * snapshot inmutable antes del cambio.
 *
 * Los perfiles de sistema (es_sistema = true) son invariantes del contrato
 * del sistema — no pueden eliminarse.
 *
 * Ver docs/anonimizacion.md § 5 y docs/decisiones-tecnicas.md §§ 7 y 8.
 *
 * @property int $id
 * @property string $nombre
 * @property int $nivel
 * @property int $version
 * @property string $estado
 * @property bool $es_sistema
 * @property array<string, mixed> $campos
 * @property int|null $k_valor
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PerfilAnonimizacion extends Model
{
    use HasFactory;

    protected $table = 'perfiles_anonimizacion';

    protected $fillable = [
        'nombre',
        'nivel',
        'version',
        'estado',
        'es_sistema',
        'campos',
        'k_valor',
    ];

    protected $casts = [
        'campos' => 'array',
        'es_sistema' => 'boolean',
        'nivel' => 'integer',
        'version' => 'integer',
        'k_valor' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (PerfilAnonimizacion $perfil) {
            if ($perfil->isDirty(['campos', 'k_valor'])) {
                PerfilAnonimizacionVersion::create([
                    'perfil_id' => $perfil->getKey(),
                    'version' => $perfil->getOriginal('version'),
                    'campos' => $perfil->getOriginal('campos'),
                    'k_valor' => $perfil->getOriginal('k_valor'),
                ]);

                $perfil->version = $perfil->getOriginal('version') + 1;
            }
        });
    }

    public function delete(): ?bool
    {
        if ($this->es_sistema) {
            throw new PerfilSistemaNoEliminableException($this->nombre);
        }

        return parent::delete();
    }

    /**
     * @return HasMany<PerfilAnonimizacionVersion, $this>
     */
    public function versiones(): HasMany
    {
        return $this->hasMany(PerfilAnonimizacionVersion::class, 'perfil_id')->latest('created_at');
    }

    /**
     * @param Builder<PerfilAnonimizacion> $query
     *
     * @return Builder<PerfilAnonimizacion>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'activo');
    }

    /**
     * @param Builder<PerfilAnonimizacion> $query
     *
     * @return Builder<PerfilAnonimizacion>
     */
    public function scopeDeSistema(Builder $query): Builder
    {
        return $query->where('es_sistema', true);
    }
}
