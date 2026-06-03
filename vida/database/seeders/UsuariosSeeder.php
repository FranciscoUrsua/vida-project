<?php

namespace Database\Seeders;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Database\Seeder;
use Modules\Usuarios\Models\UsuarioRol;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder de usuarios de desarrollo.
 *
 * Crea 4 usuarios con credenciales conocidas que cubren los perfiles
 * funcionales principales del sistema. Idempotente: usa firstOrCreate
 * en todos los pasos.
 *
 * Credenciales:
 *   admin@vida.local       / Vida360!Admin  → adm_sistema
 *   supervisor@vida.local  / Vida360!Super  → supervision + adm_usuarios
 *   intervencion@vida.local/ Vida360!Inter  → intervencion
 *   consulta@vida.local    / Vida360!Basic  → consulta_basica
 *
 * Debe ejecutarse después de RolesSeeder y UoSeeder.
 *
 * @see RolesSeeder
 * @see UoSeeder
 */
class UsuariosSeeder extends Seeder
{
    /**
     * Definición de los usuarios de desarrollo.
     *
     * Cada entrada: name, email, password, roles[], uo (nombre de UO o null).
     *
     * @var list<array{name: string, email: string, password: string, roles: list<string>, uo: string|null}>
     */
    private const USUARIOS = [
        [
            'name' => 'Administrador Sistema',
            'email' => 'admin@vida.local',
            'password' => 'Vida360!Admin',
            'roles' => ['adm_sistema'],
            'uo' => null,
        ],
        [
            'name' => 'Supervisor Usuarios',
            'email' => 'supervisor@vida.local',
            'password' => 'Vida360!Super',
            'roles' => ['supervision', 'adm_usuarios'],
            'uo' => 'Departamento de Atención Primaria',
        ],
        [
            'name' => 'Técnico Intervención',
            'email' => 'intervencion@vida.local',
            'password' => 'Vida360!Inter',
            'roles' => ['intervencion'],
            'uo' => 'Centro de Servicios Sociales Arganzuela',
        ],
        [
            'name' => 'Consulta Básica',
            'email' => 'consulta@vida.local',
            'password' => 'Vida360!Basic',
            'roles' => ['consulta_basica'],
            'uo' => 'Centro de Servicios Sociales Retiro',
        ],
    ];

    /**
     * Crea los usuarios de desarrollo con sus roles y adscripciones a UO.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::USUARIOS as $datos) {
            $user = User::firstOrCreate(
                ['email' => $datos['email']],
                [
                    'name' => $datos['name'],
                    'password' => $datos['password'],
                    'email_verified_at' => now(),
                ]
            );

            foreach ($datos['roles'] as $nombreRol) {
                $this->asignarRol($user, $nombreRol);
            }

            if ($datos['uo'] !== null) {
                $this->adscribirAUo($user, $datos['uo']);
            }

            $rolesTexto = implode(' + ', $datos['roles']);
            $this->command->info("✓ {$datos['email']} / {$datos['password']}  [{$rolesTexto}]");
        }
    }

    /**
     * Asigna un rol al usuario mediante UsuarioRol (el observer sincroniza Spatie).
     * Si el usuario ya tiene el rol activo, no duplica el registro.
     *
     * @param User $user Usuario al que asignar el rol
     * @param string $nombreRol Nombre del rol (guard web)
     */
    private function asignarRol(User $user, string $nombreRol): void
    {
        $rol = Role::where('name', $nombreRol)->where('guard_name', 'web')->first();

        if (! $rol) {
            $this->command->warn("  Rol '{$nombreRol}' no encontrado. ¿Se ejecutó RolesSeeder?");

            return;
        }

        $yaAsignado = UsuarioRol::where('usuario_id', $user->id)
            ->where('rol_id', $rol->id)
            ->vigentes()
            ->exists();

        if ($yaAsignado) {
            return;
        }

        UsuarioRol::create([
            'usuario_id' => $user->id,
            'rol_id' => $rol->id,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => null,
            'asignado_por' => null,
            'estado' => 'activo',
        ]);
    }

    /**
     * Adscribe al usuario a la UO indicada por nombre, si no lo está ya.
     *
     * @param User $user Usuario a adscribir
     * @param string $nombreUo Nombre exacto de la Unidad Organizativa
     */
    private function adscribirAUo(User $user, string $nombreUo): void
    {
        $uo = UnidadOrganizativa::where('nombre', $nombreUo)->first();

        if (! $uo) {
            $this->command->warn("  UO '{$nombreUo}' no encontrada. ¿Se ejecutó UoSeeder?");

            return;
        }

        $yaAdscrito = UsuarioUo::where('usuario_id', $user->id)
            ->where('unidad_organizativa_id', $uo->id)
            ->vigentes()
            ->exists();

        if ($yaAdscrito) {
            return;
        }

        UsuarioUo::create([
            'usuario_id' => $user->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'funcionario',
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => null,
        ]);
    }
}
