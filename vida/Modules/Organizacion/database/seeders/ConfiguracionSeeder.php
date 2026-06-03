<?php

namespace Modules\Organizacion\Database\Seeders;

/**
 * Datos de ejemplo basados en la estructura del Ayuntamiento de Madrid.
 * Deben adaptarse a la organización que despliega VIDA 360.
 */

use Illuminate\Database\Seeder;
use Modules\Organizacion\Models\Configuracion;

/**
 * Seeder de configuración inicial de la organización.
 *
 * Establece los parámetros mínimos necesarios para el funcionamiento
 * del sistema. Deben editarse tras el despliegue inicial.
 */
class ConfiguracionSeeder extends Seeder
{
    /**
     * Configuraciones iniciales del sistema.
     *
     * @var array<int, array{clave: string, valor: string, descripcion: string, tipo: string}>
     */
    private const CONFIGURACIONES = [
        [
            'clave' => 'nombre_organizacion',
            'valor' => 'Ayuntamiento de Madrid',
            'descripcion' => 'Nombre de la organización que despliega VIDA 360',
            'tipo' => 'texto',
        ],
        [
            'clave' => 'municipio',
            'valor' => 'Madrid',
            'descripcion' => 'Nombre del municipio',
            'tipo' => 'texto',
        ],
        [
            'clave' => 'provincia',
            'valor' => 'Madrid',
            'descripcion' => 'Nombre de la provincia',
            'tipo' => 'texto',
        ],
    ];

    /**
     * Crea las configuraciones iniciales si no existen.
     */
    public function run(): void
    {
        foreach (self::CONFIGURACIONES as $datos) {
            Configuracion::firstOrCreate(
                ['clave' => $datos['clave']],
                $datos
            );
        }

        $this->command->info('✓ Configuraciones iniciales creadas.');
    }
}
