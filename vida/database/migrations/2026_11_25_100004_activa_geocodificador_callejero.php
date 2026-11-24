<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Activa el geocodificador local del callejero oficial (`geocoder.proveedor = callejero`)
 * donde el callejero ya está importado.
 *
 * Solo si `callejero_portales` tiene datos: en un entorno recién creado (y en los tests)
 * la tabla está vacía y el proveedor no cambia; allí se activa tras la primera
 * importación (`php artisan callejero:importar`). No pisa un proveedor ya configurado.
 * Se activa tras comprobar que una persona con asignación vigente no se reasigna al
 * normalizarse su domicilio (TF-CAL-12, paso 5 de las instrucciones).
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
return new class extends Migration
{
    /**
     * Crea la clave con el valor `callejero` si hay callejero y no hay proveedor configurado.
     *
     * @return void
     */
    public function up(): void
    {
        if (! DB::table('callejero_portales')->exists()
            || DB::table('organizacion_configuracion')->where('clave', 'geocoder.proveedor')->exists()) {
            return;
        }

        DB::table('organizacion_configuracion')->insert([
            'clave' => 'geocoder.proveedor',
            'valor' => 'callejero',
            'descripcion' => 'Geocodificador de direcciones: callejero (callejero oficial de Madrid, local), ninguno',
            'tipo' => 'texto',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Vuelve a dejar el sistema sin geocodificador si lo activó esta migración.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('organizacion_configuracion')
            ->where('clave', 'geocoder.proveedor')
            ->where('valor', 'callejero')
            ->delete();
    }
};
