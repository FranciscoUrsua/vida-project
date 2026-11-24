<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia local del callejero oficial de Madrid para el geocodificador sin terceros.
 *
 * `callejero_portales` guarda cada número de policía del fichero de numeraciones del
 * conjunto 200075 de datos.madrid.es (portales, parcelas, fachadas…), con su distrito,
 * barrio, sección censal y coordenadas. Es un dato externo que se reemplaza entero en
 * cada importación: no tiene `SoftDeletes` ni versionado. `callejero_importaciones`
 * deja constancia de cada carga.
 *
 * Crea las extensiones `pg_trgm` (similitud para las erratas) y `unaccent`; ambas son
 * «trusted» y las puede crear el propietario de la BD (comprobado el 24 nov 2026).
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
return new class extends Migration
{
    /**
     * Crea las extensiones y las dos tablas.
     *
     * @return void
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        Schema::create('callejero_portales', function (Blueprint $table) {
            $table->id();
            $table->string('cod_ndp', 12)->unique()->comment('Código del número de policía (NDP) de la Base de Datos Ciudad');
            $table->string('cod_via', 12)->index();
            $table->string('clase_via', 30)->comment('CALLE, AVENIDA, PLAZA, LUGAR…, tal como viene');
            $table->string('particula_via', 10)->nullable()->comment('DE, DEL, DE LA…');
            $table->string('nombre_via')->comment('Como viene en el fichero: mayúsculas sin tildes');
            $table->string('nombre_via_mostrar')->comment('Con tildes, tomado de la dirección completa');
            $table->string('nombre_via_busqueda')->comment('Minúsculas sin tildes, para buscar');
            $table->string('clase_numero', 10)->comment('numero o km');
            $table->string('numero', 10);
            $table->string('calificador', 5)->nullable()->comment('Letra del número; EN/SA en los kilómetros');
            $table->string('tipo_numero', 20)->comment('portal, frente_fachada, parcela, garaje, jardin');
            $table->char('codigo_postal', 5)->nullable();
            $table->char('distrito_codigo', 2);
            $table->string('barrio_codigo', 3);
            $table->char('seccion_censal_codigo', 10)->nullable()->comment('Código INE; null con la sección 000 del fichero');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('importado_en');

            $table->index(['nombre_via_busqueda', 'numero']);
        });

        // Búsqueda por similitud (erratas) y por fragmento (autocompletado)
        DB::statement('CREATE INDEX callejero_portales_nombre_trgm ON callejero_portales USING gin (nombre_via_busqueda gin_trgm_ops)');

        Schema::create('callejero_importaciones', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->comment('AAAAMM del fichero, o «local»');
            $table->string('origen', 500)->comment('URL descargada o ruta del fichero local');
            $table->unsignedInteger('numeros');
            $table->unsignedInteger('descartados')->default(0);
            $table->timestamp('importado_en');
            $table->timestamps();
        });
    }

    /**
     * Elimina las tablas (las extensiones se conservan: pueden usarlas otras).
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('callejero_importaciones');
        Schema::dropIfExists('callejero_portales');
    }
};
