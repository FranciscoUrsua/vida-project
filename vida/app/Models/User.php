<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Traits\TieneRoles;
use Modules\Usuarios\Traits\TieneUO;
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
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 1.1
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use TieneUO;
    use TieneRoles;

    /** @var list<string> */
    protected $fillable = [
        'profesional_id',
        'name',
        'email',
        'password',
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
            'password'          => 'hashed',
        ];
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
     * @return BelongsTo<Profesional, User>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }
}
