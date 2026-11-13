<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Usuarios\Models\Cargo;
use Spatie\Permission\Models\Role;

/**
 * Sugerencias iniciales de roles por cargo (sección 2.9 de modulo-usuarios-permisos).
 *
 * Idempotente y no destructivo: un cargo que ya tiene alguna sugerencia
 * configurada no se toca. No crea cargos ni roles: los que no existen se omiten
 * y se informa por consola.
 *
 * Las sugerencias reflejan las configuradas en la BD compartida el 2026-09-25.
 * Los cargos se buscan por nombre, que es lo que se ha mantenido estable entre
 * instalaciones (los slugs estuvieron vacíos hasta esa fecha). Los nombres del
 * catálogo difieren de los del diseño: «Directora de centro» es «Coordinador/a
 * de Centro» (el mismo que usa el mundo demo CIAM para la dirección) y
 * «Administrativa» es «Administrativo/a».
 *
 * @see docs/instrucciones-cli/2026-09-roles-sugeridos-cargo.md Fase 3
 */
class RolesSugeridosCargoSeeder extends Seeder
{
    /**
     * Roles sugeridos por nombre de cargo del catálogo.
     *
     * @var array<string, list<string>>
     */
    private const SUGERENCIAS = [
        'Trabajador/a Social' => ['intervencion'],
        'Psicólogo/a' => ['intervencion', 'consulta_profesional'],
        'Educador/a Social' => ['intervencion', 'consulta_profesional'],
        'Terapeuta Ocupacional' => ['consulta_profesional'],
        'Auxiliar de Servicios Sociales' => ['intervencion', 'consulta_basica'],
        'Abogado/a' => ['consulta_profesional', 'intervencion'],
        'Coordinador/a de Centro' => ['supervision', 'intervencion'],
        'Administrativo/a' => ['tramitacion'],
        'Auxiliar Administrativo/a' => ['consulta_basica', 'tramitacion'],
    ];

    /**
     * Carga las sugerencias en los cargos que aún no tienen ninguna.
     *
     * @return void
     */
    public function run(): void
    {
        foreach (self::SUGERENCIAS as $nombreCargo => $roles) {
            $cargo = Cargo::where('nombre', $nombreCargo)->first();

            if ($cargo === null) {
                $this->command->warn("Cargo «{$nombreCargo}» no existe: se omite su sugerencia.");

                continue;
            }

            if ($cargo->rolesSugeridos()->exists()) {
                $this->command->line("Cargo «{$nombreCargo}» ya tiene sugerencias: no se modifica.");

                continue;
            }

            $existentes = Role::whereIn('name', $roles)->pluck('name')->all();

            foreach (array_diff($roles, $existentes) as $rolAusente) {
                $this->command->warn("Rol «{$rolAusente}» no existe: se omite para «{$nombreCargo}».");
            }

            $cargo->sincronizarRolesSugeridos($existentes);
        }
    }
}
