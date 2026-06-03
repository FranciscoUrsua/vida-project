<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Adscripción de un usuario a una Unidad Organizativa.
 *
 * Registra en qué UO opera el usuario y con qué tipo de vínculo laboral.
 * Tiene fechas de vigencia para mantener el historial completo
 * (principio 4.2: el pasado es inmutable).
 *
 * Los roles del usuario son globales (Spatie model_has_roles) y determinan
 * qué puede hacer; la adscripción determina dónde puede hacerlo con
 * acceso completo (nivel 1). En UOs ajenas solo puede consultar (nivel 2).
 *
 * @property int $id
 * @property int $usuario_id
 * @property int $unidad_organizativa_id
 * @property string $tipo_vinculo
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UsuarioUo extends Model
{
    use HasFactory;

    /** @var string Tabla de base de datos */
    protected $table = 'usuario_uo';

    /** @var list<string> Campos asignables en masa */
    protected $fillable = [
        'usuario_id',
        'unidad_organizativa_id',
        'tipo_vinculo',
        'fecha_inicio',
        'fecha_fin',
    ];

    /** @var array<string, string> Conversiones de tipo */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Usuario al que corresponde esta adscripción.
     *
     * @return BelongsTo<User, UsuarioUo>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Unidad Organizativa a la que el usuario está adscrito.
     *
     * @return BelongsTo<UnidadOrganizativa, UsuarioUo>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra únicamente las adscripciones vigentes:
     * aquellas donde fecha_fin es null o todavía no ha llegado.
     *
     * @param Builder<UsuarioUo> $consulta
     *
     * @return Builder<UsuarioUo>
     */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta->where(function (Builder $q) {
            $q->whereNull('fecha_fin')
                ->orWhere('fecha_fin', '>=', Carbon::today());
        });
    }
}
