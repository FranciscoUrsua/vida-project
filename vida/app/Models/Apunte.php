<?php

namespace App\Models;

use App\Models\Scopes\AmbitoUoScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Modelo stub de Apunte (acto profesional).
 *
 * Stub mínimo para que las Policies y los tests puedan referenciar
 * la entidad. La implementación completa corresponde al módulo
 * Intervencion (docs/glosario.md § Apunte).
 *
 * Un apunte es cualquier acto profesional registrado en la Historia
 * Social: valoración, seguimiento, anotación, entrevista,
 * gestión/coordinación, informe social, derivación.
 *
 * Las anotaciones privadas son un subtipo especial: solo el autor
 * puede crearlas, leerlas y eliminarlas; ni el supervisor tiene
 * acceso (docs/modulo-usuarios-permisos.md § 4.7).
 *
 * @property int $id
 * @property int $historia_social_id
 * @property int $profesional_id Usuario autor del apunte
 * @property string $tipo Tipo de apunte (configurable)
 * @property bool $privada Si true, solo el autor tiene acceso
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @todo Mover a Modules\Intervencion\Models\Apunte y completar
 *       con todos los atributos, relaciones y tipos configurables.
 */
class Apunte extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Columna de FK a historias_sociales usada por AmbitoUoScope.
     * El ámbito de UO se resuelve vía Historia Social.
     */
    public string $ambitoHistoriaColumn = 'historia_social_id';

    /** @var string Tabla de base de datos (provisional) */
    protected $table = 'apuntes';

    /** @var list<string> Campos asignables en masa */
    protected $fillable = [
        'historia_social_id',
        'profesional_id',
        'tipo',
        'privada',
    ];

    /** @var array<string, string> Conversiones de tipo */
    protected $casts = [
        'privada' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * Registra el Global Scope de ámbito de UO y el filtro de privacidad.
     *
     * El AmbitoUoScope filtra por Historia Social. Para los apuntes privados,
     * el acceso se controla mediante la Policy (regla absoluta de autor).
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new AmbitoUoScope);
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Historia Social a la que pertenece este apunte.
     *
     * @return BelongsTo<HistoriaSocial, Apunte>
     */
    public function historiaSocial(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_social_id');
    }

    /**
     * Profesional autor del apunte.
     *
     * @return BelongsTo<User, Apunte>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }
}
