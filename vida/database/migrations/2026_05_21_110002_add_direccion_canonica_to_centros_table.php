<?php

use App\Traits\TieneDireccion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopta el modelo canónico de dirección en la tabla centros.
 *
 * Renombra el campo de texto libre 'direccion' a 'direccion_texto', elimina el campo
 * 'coordenadas' (string "lat,lng" heredado) y añade los campos estructurados del modelo
 * canónico. El campo 'codigo_postal' ya existe en la tabla y no se duplica.
 *
 * Ver docs/geocodificacion.md § 3.1 y docs/decisiones-tecnicas.md § 9.
 *
 * @see TieneDireccion
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            // Renombrar al nombre canónico
            $table->renameColumn('direccion', 'direccion_texto');

            // Eliminar el campo de coordenadas heredado (formato "lat,lng")
            $table->dropColumn('coordenadas');

            // Campos de estado de normalización
            $table->boolean('direccion_normalizada')->default(false)->after('direccion_texto');
            $table->string('origen_direccion', 30)->nullable()->after('direccion_normalizada')
                ->comment('profesional | padron | geocodificacion');

            // Campos estructurados de la vía
            $table->string('tipo_via', 50)->nullable()->after('origen_direccion');
            $table->string('nombre_via', 200)->nullable()->after('tipo_via');
            $table->string('tipo_numeracion', 20)->nullable()->after('nombre_via')
                ->comment('numero | sin_numero | km');
            $table->string('numero', 20)->nullable()->after('tipo_numeracion');
            $table->string('portal', 20)->nullable()->after('numero');
            $table->string('escalera', 20)->nullable()->after('portal');
            $table->string('piso', 20)->nullable()->after('escalera');
            $table->string('puerta', 20)->nullable()->after('piso');

            // municipio (codigo_postal ya existe en la tabla)
            $table->string('municipio', 100)->nullable()->after('codigo_postal');

            // Coordenadas WGS84 en lugar del string "lat,lng" heredado
            $table->decimal('coordenadas_lat', 10, 7)->nullable()->after('municipio');
            $table->decimal('coordenadas_lng', 10, 7)->nullable()->after('coordenadas_lat');

            // Trazabilidad del geocoder
            $table->string('geocoder_proveedor', 50)->nullable()->after('coordenadas_lng');
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->renameColumn('direccion_texto', 'direccion');

            $table->dropColumn([
                'direccion_normalizada',
                'origen_direccion',
                'tipo_via',
                'nombre_via',
                'tipo_numeracion',
                'numero',
                'portal',
                'escalera',
                'piso',
                'puerta',
                'municipio',
                'coordenadas_lat',
                'coordenadas_lng',
                'geocoder_proveedor',
            ]);

            $table->string('coordenadas', 100)->nullable()
                ->comment('Coordenadas geográficas en formato "lat,lng"');
        });
    }
};
