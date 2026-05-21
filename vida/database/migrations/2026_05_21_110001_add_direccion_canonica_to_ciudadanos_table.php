<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopta el modelo canónico de dirección en la tabla ciudadanos.
 *
 * Renombra el campo de texto libre 'domicilio' a 'direccion_texto' para alinearlo
 * con el trait TieneDireccion, elimina los campos genéricos latitud/longitud y añade
 * los 14 campos estructurados del modelo canónico.
 *
 * Ver docs/geocodificacion.md § 3.1 y docs/decisiones-tecnicas.md § 9.
 *
 * @see \App\Traits\TieneDireccion
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciudadanos', function (Blueprint $table) {
            // Renombrar el campo de texto libre al nombre canónico
            $table->renameColumn('domicilio', 'direccion_texto');

            // Eliminar coordenadas genéricas sustituidas por los campos canónicos
            $table->dropColumn(['latitud', 'longitud']);

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

            // Localización
            $table->string('codigo_postal', 5)->nullable()->after('puerta');
            $table->string('municipio', 100)->nullable()->after('codigo_postal');

            // Coordenadas WGS84
            $table->decimal('coordenadas_lat', 10, 7)->nullable()->after('municipio');
            $table->decimal('coordenadas_lng', 10, 7)->nullable()->after('coordenadas_lat');

            // Trazabilidad del geocoder
            $table->string('geocoder_proveedor', 50)->nullable()->after('coordenadas_lng');
        });
    }

    public function down(): void
    {
        Schema::table('ciudadanos', function (Blueprint $table) {
            $table->renameColumn('direccion_texto', 'domicilio');

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
                'codigo_postal',
                'municipio',
                'coordenadas_lat',
                'coordenadas_lng',
                'geocoder_proveedor',
            ]);

            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
        });
    }
};
