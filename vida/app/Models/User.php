<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modelo de usuario del sistema (profesional o personal administrativo).
 *
 * Representa al Profesional tal como se define en el glosario:
 * empleado público o trabajador de entidad contratada que presta
 * servicios en el Sistema Público de Servicios Sociales.
 *
 * Los permisos se gestionan mediante Spatie laravel-permission (HasRoles).
 * El ámbito de datos sobre el que puede ejercer esos permisos lo delimita
 * la adscripción a Unidades Organizativas (tabla usuario_uo_rol).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 1
 * @see docs/glosario.md § Profesional
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * Campos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Campos ocultos en la serialización.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversiones de tipo.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Todas las adscripciones a UO del usuario (históricas y vigentes).
     *
     * @return HasMany<UsuarioUoRol>
     */
    public function adscripciones(): HasMany
    {
        return $this->hasMany(UsuarioUoRol::class, 'usuario_id');
    }

    /**
     * Únicamente las adscripciones vigentes (fecha_fin null o futura).
     *
     * @return HasMany<UsuarioUoRol>
     */
    public function adscripcionesVigentes(): HasMany
    {
        return $this->adscripciones()->vigentes();
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Devuelve la colección de Unidades Organizativas activas
     * a las que el usuario está actualmente adscrito.
     *
     * @return Collection<int, UnidadOrganizativa>
     */
    public function uosActivas(): Collection
    {
        return UnidadOrganizativa::whereIn(
            'id',
            $this->adscripcionesVigentes()->pluck('unidad_organizativa_id')
        )->activas()->get();
    }

    /**
     * Indica si el usuario pertenece a la misma UO que la Historia Social
     * (o a alguna UO que la contenga en la jerarquía).
     *
     * Se usa en las Policies para distinguir gestión completa (misma UO)
     * de consulta libre (UO diferente).
     *
     * @param UnidadOrganizativa $uoHistoria UO responsable de la Historia
     * @return bool
     */
    public function perteneceAUo(UnidadOrganizativa $uoHistoria): bool
    {
        $idsUoUsuario = $this->adscripcionesVigentes()
            ->pluck('unidad_organizativa_id')
            ->toArray();

        return in_array($uoHistoria->id, $idsUoUsuario);
    }

    /**
     * Stub preparatorio para la Policy de acceso a Historias Sociales.
     * Implementación completa en App\Policies\HistoriaSocialPolicy.
     *
     * @param HistoriaSocial $historia
     * @return bool
     *
     * @todo Implementar la lógica completa en HistoriaSocialPolicy::view()
     */
    public function tieneAccesoA(HistoriaSocial $historia): bool
    {
        // La lógica detallada (rol, UO, colectivo protegido, aprobación)
        // reside en HistoriaSocialPolicy::view(). Este método es un
        // punto de entrada conveniente para llamadas desde código de dominio.
        return app(\Illuminate\Auth\Access\Gate::class)->allows('view', $historia);
    }
}
