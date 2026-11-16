<?php

namespace Modules\Documentos\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Documentos\Models\ParametroInforme;

/**
 * Parámetros configurables de ejemplo para una instalación nueva (idempotente).
 */
class ParametroInformeSeeder extends Seeder
{
    /**
     * Crea o actualiza los parámetros de ejemplo.
     *
     * @return void
     */
    public function run(): void
    {
        $parametros = [
            ['clave' => 'ciudad', 'etiqueta' => 'Nombre del municipio', 'valor' => 'Madrid', 'descripcion' => 'Nombre del municipio que aparece en los informes.'],
            ['clave' => 'nombre_sistema', 'etiqueta' => 'Nombre del sistema de servicios sociales', 'valor' => 'Servicios Sociales Municipales', 'descripcion' => 'Denominación institucional del sistema.'],
            ['clave' => 'web_municipal', 'etiqueta' => 'Web del ayuntamiento', 'valor' => 'www.madrid.es', 'descripcion' => 'URL de la web municipal para el pie de los informes.'],
            ['clave' => 'telefono_atencion', 'etiqueta' => 'Teléfono de atención ciudadana', 'valor' => '010', 'descripcion' => 'Teléfono general de información y atención al ciudadano.'],
        ];

        foreach ($parametros as $parametro) {
            ParametroInforme::updateOrCreate(['clave' => $parametro['clave']], $parametro);
        }
    }
}
