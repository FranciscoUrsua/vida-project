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
 * Los cargos se buscan por nombre porque en las instalaciones existentes el
 * slug está vacío. Los nombres del catálogo difieren de los del diseño:
 * «Directora de centro» es «Coordinador/a de Centro» (el mismo que usa el mundo
 * demo CIAM para la dirección) y «Administrativa» es «Administrativo/a».
 * «Abogado/a» no tiene sugerencia a propósito: en el CIAM trabaja con
 * intervencion y en el SOJ con consulta_profesional.
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
        'Coordinador/a de Centro' => ['supervision', 'intervencion'],
        'Trabajador/a Social' => ['intervencion'],
        'Psicólogo/a' => ['intervencion'],
        'Auxiliar de Servicios Sociales' => ['intervencion'],
        'Administrativo/a' => ['tramitacion'],
    ];

    /**
     * Carga las sugerencias en los cargos que aún no tienen ninguna.
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
