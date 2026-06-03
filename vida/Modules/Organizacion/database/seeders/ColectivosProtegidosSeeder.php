<?php

namespace Modules\Organizacion\Database\Seeders;

/**
 * Datos de ejemplo basados en la estructura del Ayuntamiento de Madrid.
 * Deben adaptarse a la organización que despliega VIDA 360.
 */

use Illuminate\Database\Seeder;
use Modules\Organizacion\Models\ColectivoProtegido;

/**
 * Seeder de colectivos especialmente protegidos.
 *
 * Los dos colectivos iniciales son los definidos en la especificación
 * (docs/modulo-usuarios-permisos.md sección 3): menores y víctimas de
 * violencia de género.
 */
class ColectivosProtegidosSeeder extends Seeder
{
    /**
     * Colectivos iniciales.
     *
     * @var array<int, array{nombre: string, descripcion: string, requiere_aprobacion_previa: bool}>
     */
    private const COLECTIVOS = [
        [
            'nombre' => 'Menores',
            'descripcion' => 'Personas menores de 18 años. El acceso a sus expedientes desde fuera de la UO responsable requiere aprobación del supervisor de infancia.',
            'requiere_aprobacion_previa' => true,
        ],
        [
            'nombre' => 'Víctimas de violencia de género',
            'descripcion' => 'Mujeres en situación de violencia de género o en riesgo. El acceso a sus expedientes desde fuera de la UO responsable requiere aprobación del supervisor de violencia de género.',
            'requiere_aprobacion_previa' => true,
        ],
    ];

    /**
     * Crea los colectivos protegidos iniciales si no existen.
     */
    public function run(): void
    {
        foreach (self::COLECTIVOS as $datos) {
            ColectivoProtegido::firstOrCreate(
                ['nombre' => $datos['nombre']],
                array_merge($datos, ['activo' => true])
            );
        }

        $this->command->info('✓ Colectivos especialmente protegidos creados.');
    }
}
