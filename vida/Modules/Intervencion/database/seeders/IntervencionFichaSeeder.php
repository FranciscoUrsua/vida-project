<?php

namespace Modules\Intervencion\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Seeder de fichas de valoración del módulo Intervención.
 *
 * Crea o actualiza las tres fichas base del sistema en formato de schema
 * canónico (clave raíz 'campos' con id, tipo, etiqueta, obligatorio, orden).
 * El seeder es idempotente: puede ejecutarse múltiples veces sin duplicados.
 */
class IntervencionFichaSeeder extends Seeder
{
    public function run(): void
    {
        TipoFicha::updateOrCreate(
            ['nombre' => 'Situación económica'],
            [
                'descripcion' => 'Ingresos, gastos y deudas del hogar.',
                'schema' => [
                    'campos' => [
                        [
                            'id' => 'ingresos_mensuales',
                            'tipo' => 'numero',
                            'etiqueta' => 'Ingresos mensuales del hogar',
                            'descripcion' => 'Suma de todos los ingresos netos mensuales del hogar',
                            'unidad' => '€',
                            'obligatorio' => true,
                            'orden' => 1,
                        ],
                        [
                            'id' => 'num_convivientes',
                            'tipo' => 'numero',
                            'etiqueta' => 'Número de convivientes',
                            'descripcion' => null,
                            'obligatorio' => true,
                            'orden' => 2,
                        ],
                        [
                            'id' => 'fuente_ingresos',
                            'tipo' => 'select',
                            'etiqueta' => 'Fuente principal de ingresos',
                            'descripcion' => null,
                            'opciones' => ['Trabajo', 'Pensión contributiva', 'Pensión no contributiva', 'Prestación desempleo', 'Ayudas sociales', 'Sin ingresos', 'Otros'],
                            'obligatorio' => false,
                            'orden' => 3,
                        ],
                        [
                            'id' => 'deudas_relevantes',
                            'tipo' => 'booleano',
                            'etiqueta' => 'Tiene deudas relevantes',
                            'descripcion' => null,
                            'obligatorio' => false,
                            'orden' => 4,
                        ],
                        [
                            'id' => 'detalle_deudas',
                            'tipo' => 'texto',
                            'etiqueta' => 'Detalle de deudas',
                            'descripcion' => 'Descripción de las deudas más relevantes',
                            'obligatorio' => false,
                            'orden' => 5,
                        ],
                    ],
                ],
                'activo' => true,
            ]
        );

        TipoFicha::updateOrCreate(
            ['nombre' => 'Situación de vivienda'],
            [
                'descripcion' => 'Condiciones del domicilio y régimen de tenencia.',
                'schema' => [
                    'campos' => [
                        [
                            'id' => 'regimen_tenencia',
                            'tipo' => 'select',
                            'etiqueta' => 'Régimen de tenencia',
                            'descripcion' => null,
                            'opciones' => ['Propiedad sin hipoteca', 'Propiedad con hipoteca', 'Alquiler', 'Cedida', 'Realojo temporal', 'Sin vivienda'],
                            'obligatorio' => true,
                            'orden' => 1,
                        ],
                        [
                            'id' => 'metros_cuadrados',
                            'tipo' => 'numero',
                            'etiqueta' => 'Superficie de la vivienda',
                            'descripcion' => null,
                            'unidad' => 'm²',
                            'obligatorio' => false,
                            'orden' => 2,
                        ],
                        [
                            'id' => 'num_habitaciones',
                            'tipo' => 'numero',
                            'etiqueta' => 'Número de habitaciones',
                            'descripcion' => null,
                            'obligatorio' => false,
                            'orden' => 3,
                        ],
                        [
                            'id' => 'estado_conservacion',
                            'tipo' => 'select',
                            'etiqueta' => 'Estado de conservación',
                            'descripcion' => null,
                            'opciones' => ['Bueno', 'Deteriorado', 'Deficiente', 'Infravivienda'],
                            'obligatorio' => false,
                            'orden' => 4,
                        ],
                        [
                            'id' => 'barreras_arquitectonicas',
                            'tipo' => 'booleano',
                            'etiqueta' => 'Tiene barreras arquitectónicas',
                            'descripcion' => null,
                            'obligatorio' => false,
                            'orden' => 5,
                        ],
                        [
                            'id' => 'observaciones_vivienda',
                            'tipo' => 'texto',
                            'etiqueta' => 'Observaciones',
                            'descripcion' => null,
                            'obligatorio' => false,
                            'orden' => 6,
                        ],
                    ],
                ],
                'activo' => true,
            ]
        );

        TipoFicha::updateOrCreate(
            ['nombre' => 'Valoración social libre'],
            [
                'descripcion' => 'Ficha de uso general para registrar una valoración narrativa sin estructura preestablecida.',
                'schema' => [
                    'campos' => [
                        [
                            'id' => 'valoracion_libre',
                            'tipo' => 'texto',
                            'etiqueta' => 'Valoración narrativa',
                            'descripcion' => 'Descripción libre de la situación social del ciudadano',
                            'obligatorio' => true,
                            'orden' => 1,
                        ],
                    ],
                ],
                'activo' => true,
            ]
        );
    }
}
