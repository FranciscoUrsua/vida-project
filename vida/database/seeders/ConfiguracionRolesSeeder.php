<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Usuarios\Models\ConfiguracionRol;
use Spatie\Permission\Models\Role;

/**
 * Nivel de supervisión explícito para cada rol del sistema (sección 2.8 de modulo-usuarios-permisos).
 *
 * Aprobación previa para adm_sistema y supervision; alerta supervisada para el resto.
 * Es el mismo nivel que ConfiguracionRol::nivelPara() aplica por defecto, pero
 * dejarlo en la tabla hace que se vea y se pueda cambiar desde Roles → Configuración.
 *
 * Idempotente y no destructivo: no toca los roles que ya tienen nivel configurado
 * ni crea roles.
 */
class ConfiguracionRolesSeeder extends Seeder
{
    /**
     * Roles que requieren aprobación previa.
     *
     * @var list<string>
     */
    private const APROBACION_PREVIA = ['adm_sistema', 'supervision'];

    /**
     * Crea la configuración de los roles que aún no la tienen.
     *
     * @return void
     */
    public function run(): void
    {
        foreach (Role::orderBy('id')->get() as $rol) {
            $configuracion = ConfiguracionRol::firstOrCreate(
                ['rol_id' => $rol->id],
                ['nivel_supervision' => in_array($rol->name, self::APROBACION_PREVIA, true)
                    ? ConfiguracionRol::APROBACION_PREVIA
                    : ConfiguracionRol::ALERTA_SUPERVISADA],
            );

            if (! $configuracion->wasRecentlyCreated) {
                $this->command?->line("Rol «{$rol->name}» ya tiene nivel ({$configuracion->nivel_supervision}): no se modifica.");
            }
        }
    }
}
