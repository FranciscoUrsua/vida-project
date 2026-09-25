<?php

namespace Modules\Usuarios\Models;

use App\Contracts\AuditableModel;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

/**
 * Rol sugerido para los usuarios que se dan de alta con un profesional de un cargo.
 *
 * Es una comodidad de gestión, no una fuente de permisos: solo pre-rellena el
 * selector de roles del alta de usuario y alimenta el aviso de cambio de cargo.
 * Los roles nunca se deducen del cargo (principio 3.3); por eso ningún otro
 * código debe leer esta tabla.
 *
 * @property int $id
 * @property int $cargo_id
 * @property string $rol Nombre del rol Spatie
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Cargo $cargo
 *
 * @see docs/modulo-usuarios-permisos.md sección 2.9
 */
class CargoRolSugerido extends Model implements AuditableModel
{
    use Auditable;

    /** @var string */
    protected $table = 'cargo_roles_sugeridos';

    /** @var list<string> */
    protected $fillable = [
        'cargo_id',
        'rol',
    ];

    /**
     * Impide guardar una sugerencia de un rol que no existe en el catálogo de Spatie.
     *
     *
     * @throws InvalidArgumentException si el rol no existe.
     */
    protected static function booted(): void
    {
        static::saving(function (CargoRolSugerido $sugerencia): void {
            self::verificarRolExiste($sugerencia->rol);
        });
    }

    /**
     * Cargo al que pertenece la sugerencia.
     *
     * @return BelongsTo<Cargo, $this>
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cargo_id');
    }

    /**
     * Lanza una excepción si el rol no existe en el catálogo de roles de Spatie.
     *
     * @param string $rol Nombre del rol.
     *
     * @throws InvalidArgumentException si el rol no existe.
     */
    private static function verificarRolExiste(string $rol): void
    {
        if (! Role::where('name', $rol)->exists()) {
            throw new InvalidArgumentException("El rol «{$rol}» no existe y no puede sugerirse.");
        }
    }
}
