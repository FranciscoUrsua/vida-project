<?php

namespace App\Models\Api;

use App\Exceptions\Anonimizacion\PerfilConExtraccionesException;
use App\Exceptions\Anonimizacion\PerfilSistemaNoEliminableException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
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
 * @property int         $id
 * @property string      $nombre        Identificador legible único
 * @property int         $nivel         1 | 2 | 3
 * @property int         $version       Incrementa automáticamente al cambiar campos o k_valor
 * @property string      $estado        activo | inactivo
 * @property bool        $es_sistema    Los perfiles de sistema no pueden eliminarse
 * @property array       $campos        Configuración campo a campo en JSON
 * @property int|null    $k_valor       Umbral K para perfiles de Nivel 3
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class PerfilAnonimizacion extends Model
{
    use HasFactory;

    /** @var string */
    protected $table = 'perfiles_anonimizacion';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'nivel',
        'version',
        'estado',
        'es_sistema',
        'campos',
        'k_valor',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'campos'     => 'array',
        'es_sistema' => 'boolean',
        'nivel'      => 'integer',
        'version'    => 'integer',
        'k_valor'    => 'integer',
    ];

    /**
     * Registra el versionado automático al arrancar el modelo.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updating(function (PerfilAnonimizacion $perfil) {
            if ($perfil->isDirty(['campos', 'k_valor'])) {
                // Guardar snapshot del estado anterior antes de aplicar el cambio
                PerfilAnonimizacionVersion::create([
                    'perfil_id' => $perfil->getKey(),
                    'version'   => $perfil->getOriginal('version'),
                    'campos'    => $perfil->getOriginal('campos'),
                    'k_valor'   => $perfil->getOriginal('k_valor'),
                ]);

                $perfil->version = $perfil->getOriginal('version') + 1;
            }
        });
    }

    /**
     * Intenta eliminar el perfil respetando las restricciones de dominio.
     *
     * @throws PerfilSistemaNoEliminableException Si es un perfil de sistema
     * @throws PerfilConExtraccionesException     Si tiene extracciones asociadas
     * @return bool|null
     */
    public function delete(): bool|null
    {
        if ($this->es_sistema) {
            throw new PerfilSistemaNoEliminableException($this->nombre);
        }

        // TODO: verificar extracciones asociadas cuando se implemente el modelo Extraccion
        // if ($this->extracciones()->exists()) {
        //     throw new PerfilConExtraccionesException($this->nombre);
        // }

        return parent::delete();
    }

    /**
     * Historial de versiones anteriores del perfil.
     *
     * @return HasMany<PerfilAnonimizacionVersion>
     */
    public function versiones(): HasMany
    {
        return $this->hasMany(PerfilAnonimizacionVersion::class, 'perfil_id')->latest('created_at');
    }

    /**
     * Solo perfiles en estado activo.
     *
     * @param Builder<PerfilAnonimizacion> $query
     * @return Builder<PerfilAnonimizacion>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Solo perfiles predefinidos del sistema.
     *
     * @param Builder<PerfilAnonimizacion> $query
     * @return Builder<PerfilAnonimizacion>
     */
    public function scopeDeSistema(Builder $query): Builder
    {
        return $query->where('es_sistema', true);
    }
}
