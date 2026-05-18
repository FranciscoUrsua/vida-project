<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ámbito territorial de atención de un centro.
 *
 * Reemplaza el campo distrito_id en centros. Un centro puede tener varios
 * ámbitos combinando tipos distintos, excepto ciudad_completa, que es excluyente.
 *
 * tipo: ciudad_completa | demarcacion_oficial | barrios | secciones_censales | poligono_gis
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambitos_territoriales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('centro_id')
                ->constrained('centros')
                ->cascadeOnDelete();

            $table->string('tipo')
                ->comment('ciudad_completa | demarcacion_oficial | barrios | secciones_censales | poligono_gis');

            $table->string('descripcion')
                ->comment('Nombre legible. Ej: "Distrito de Vallecas", "Barrio de Lavapiés"');

            $table->unsignedBigInteger('referencia_id')->nullable()
                ->comment('ID de la entidad referenciada (distrito, barrio...) según tipo');

            $table->string('referencia_tipo')->nullable()
                ->comment('Nombre del modelo referenciado. Ej: Distrito, Barrio');

            $table->json('geojson')->nullable()
                ->comment('Solo para tipo poligono_gis');

            $table->timestamps();

            $table->index('centro_id');
            $table->index(['centro_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambitos_territoriales');
    }
};
