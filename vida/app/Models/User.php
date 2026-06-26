<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Traits\TieneRoles;
use Modules\Usuarios\Traits\TieneUO;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * Cuenta de acceso al sistema.
 *
 * Representa la identidad de autenticación: las credenciales que
 * dan acceso al sistema. Es el "quién ha hecho esto" en la auditoría.
 *
 * Un User casi siempre tiene un Profesional asociado (su perfil
 * organizativo), salvo los perfiles técnicos sin función asistencial
 * (rol adm_sistema). La FK profesional_id es nullable por eso.
 *
 * Los permisos se gestionan mediante Spatie laravel-permission (HasRoles).
 * El ámbito de datos lo delimita la adscripción a UO — ver TieneUO.
 * El historial de roles se gestiona mediante usuario_rol — ver TieneRoles.
 *
 * @property int $id
 * @property int|null $profesional_id
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property bool $primer_acceso
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 1.1
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use TieneRoles;
    use TieneUO;

    /** @var list<string> */
    protected $fillable = [
        'profesional_id',
        'email',
        'password',
        'primer_acceso',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'primer_acceso' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * Asigna automáticamente el rol consulta_basica al crear un usuario sin roles.
     *
     * Solo aplica a usuarios con profesional_id (función asistencial).
     * Los perfiles técnicos sin profesional (adm_sistema) se gestionan manualmente.
     */
    protected static function booted(): void
    {
        // La columna name existe en el esquema pero no se expone en formularios.
        // Se rellena automáticamente con el email para mantener la restricción NOT NULL.
        static::creating(function (User $user): void {
            $user->name = $user->email;
        });

        static::created(function (User $user): void {
            if ($user->roles()->count() === 0
                && $user->profesional_id !== null
                && Role::where('name', 'consulta_basica')->exists()
            ) {
                $user->assignRole('consulta_basica');
            }
        });
    }

    // -------------------------------------------------------------------------
    // Filament
    // -------------------------------------------------------------------------

    /**
     * Solo roles de gestión y supervisión pueden acceder al panel de administración.
     *
     * @param Panel $panel Panel de Filament a evaluar.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['adm_sistema', 'supervision', 'adm_usuarios']);
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Perfil organizativo del usuario.
     *
     * Null para perfiles estrictamente técnicos (adm_sistema sin
     * función asistencial directa).
     *
     * @return BelongsTo<Profesional, $this>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }
}
