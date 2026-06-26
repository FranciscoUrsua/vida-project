<?php

namespace App\Models;

use Database\Factories\UsuarioUoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>>
 *
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
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory;

    protected $table = 'usuario_uo';

    protected $fillable = [
        'usuario_id',
        'unidad_organizativa_id',
        'tipo_vinculo',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
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
