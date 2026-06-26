<?php

namespace Modules\Usuarios\Models;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Perfil organizativo de una persona en el sistema.
 *
 * Describe a una persona en su dimensión laboral: cargo, titulación,
 * tipo de vínculo contractual y datos de contacto profesional.
 * No es la cuenta del sistema (User) ni el perfil de autorización.
 *
 * Profesional es la entidad raíz: existe con independencia de si
 * tiene cuenta de acceso. Un User casi siempre tiene un Profesional
 * detrás, salvo perfiles estrictamente técnicos (adm_sistema).
 *
 * Sujeta a versionado (trait Versionable): sus datos cambian a lo
 * largo de la vida laboral y el sistema debe poder conocer el estado
 * en cualquier fecha pasada.
 *
 * @property int $id
 * @property string $nombre
 * @property string $apellido1
 * @property string|null $apellido2
 * @property string $sexo M|F|D
 * @property int $cargo_id
 * @property string|null $categoria_profesional
 * @property int|null $titulacion_id
 * @property int $tipo_relacion_id
 * @property string|null $organizacion
 * @property string|null $email_profesional
 * @property string|null $telefono_profesional
 * @property string|null $extension
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property bool $activo
 * @property int|null $unidad_organizativa_id UO de adscripción directa (antes de tener cuenta de usuario)
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 5
 */
class Profesional extends Model
{
    use SoftDeletes;
    use Versionable;

    /** @var string */
    protected $table = 'profesionales';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'apellido1',
        'apellido2',
        'sexo',
        'cargo_id',
        'categoria_profesional',
        'titulacion_id',
        'tipo_relacion_id',
        'organizacion',
        'email_profesional',
        'telefono_profesional',
        'extension',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'unidad_organizativa_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activo' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Unidad Organizativa de adscripción directa del profesional.
     * Permite la gestión del profesional antes de vincularle una cuenta de usuario.
     *
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * Cargo que ocupa este profesional.
     *
     * @return BelongsTo<Cargo, Profesional>
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cargo_id');
    }

    /**
     * Titulación académica del profesional.
     *
     * @return BelongsTo<Titulacion, Profesional>
     */
    public function titulacion(): BelongsTo
    {
        return $this->belongsTo(Titulacion::class, 'titulacion_id');
    }

    /**
     * Tipo de relación contractual con la organización.
     *
     * @return BelongsTo<TipoRelacionProfesional, Profesional>
     */
    public function tipoRelacion(): BelongsTo
    {
        return $this->belongsTo(TipoRelacionProfesional::class, 'tipo_relacion_id');
    }

    /**
     * Cuenta de acceso al sistema vinculada a este profesional, si existe.
     *
     * @return HasOne<User>
     */
    public function usuario(): HasOne
    {
        return $this->hasOne(User::class, 'profesional_id');
    }

    // -------------------------------------------------------------------------
    // Accesores
    // -------------------------------------------------------------------------

    /**
     * Nombre completo: nombre + apellido1 [+ apellido2].
     */
    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido1} {$this->apellido2}");
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo profesionales activos.
     *
     * @param Builder<Profesional> $consulta
     *
     * @return Builder<Profesional>
     */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}
