<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Usuarios\Traits\TieneRoles;
use Modules\Usuarios\Traits\TieneUO;
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
 * la adscripción a Unidades Organizativas (tabla usuario_uo) — ver TieneUO.
 * El historial de roles se gestiona mediante la tabla usuario_rol — ver TieneRoles.
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
    use TieneUO;
    use TieneRoles;

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
}
