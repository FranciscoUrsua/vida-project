<?php

namespace Modules\Organizacion\Database\Seeders;

/**
 * Datos de ejemplo basados en la estructura del Ayuntamiento de Madrid.
 * Deben adaptarse a la organización que despliega VIDA 360.
 */

use Illuminate\Database\Seeder;
use Modules\Organizacion\Models\ServicioEmergenciaPreautorizado;

/**
 * Seeder de servicios de atención social de urgencia preautorizados.
 *
 * El SAMUR Social del Ayuntamiento de Madrid tiene acceso preautorizado
 * en modo consulta a expedientes de ciudadanos especialmente protegidos,
 * sin necesidad de aprobación previa (principio 3.6, excepción urgencia).
 */
class ServiciosEmergenciaSeeder extends Seeder
{
    /**
     * Crea el servicio SAMUR Social si no existe.
     */
    public function run(): void
    {
        ServicioEmergenciaPreautorizado::updateOrCreate(
            ['slug' => 'samur-social'],
            [
                'nombre' => 'SAMUR Social',
                'descripcion' => 'Servicio de Atención Municipal de Urgencias y Rescate Social del Ayuntamiento de Madrid. Atiende situaciones de emergencia social en la vía pública las 24 horas del día.',
                'activo' => true,
            ]
        );

        $this->command->info('✓ Servicios de emergencia preautorizados creados.');
    }
}
