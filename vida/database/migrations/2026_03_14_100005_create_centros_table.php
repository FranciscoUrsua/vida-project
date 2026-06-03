<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\Centro;

/**
 * Centros de servicios sociales.
 *
 * Un centro es la unidad operativa donde se prestan los servicios:
 * CSS, albergues, centros de día, recursos de acogida, etc.
 * Pertenece a una UnidadOrganizativa y se ubica en un Distrito.
 *
 * tipo_gestion valores: municipal_directo | municipal_concertado | privado_concertado | privado_puro
 *
 * @see Centro
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros', function (Blueprint $table) {
            $table->id();

            // Identificación
            $table->string('nombre', 200);
            $table->string('nombre_corto', 60)->nullable();
            $table->string('tipo_gestion', 30)->default('municipal_directo')
                ->comment('municipal_directo | municipal_concertado | privado_concertado | privado_puro');

            // Adscripción organizativa
            $table->foreignId('unidad_organizativa_id')
                ->nullable()
                ->constrained('unidades_organizativas')
                ->nullOnDelete()
                ->comment('UO responsable del centro');

            // Ubicación
            $table->string('direccion', 300)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->foreignId('distrito_id')
                ->nullable()
                ->constrained('distritos')
                ->nullOnDelete();
            $table->string('coordenadas', 100)->nullable()
                ->comment('Coordenadas geográficas en formato "lat,lng"');

            // Contacto
            $table->string('telefono', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('web', 255)->nullable();

            // Configuración operativa
            $table->boolean('inscripcion_libre')->default(false)
                ->comment('Si true, los ciudadanos pueden inscribirse sin derivación');
            $table->json('horario')->nullable()
                ->comment('Horario de atención estructurado como JSON');
            $table->text('notas')->nullable();

            // Estado
            $table->boolean('activo')->default(true);
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable()
                ->comment('Fecha de cierre del centro. Null = en funcionamiento');

            $table->timestamps();
            $table->softDeletes();

            $table->index('activo');
            $table->index('tipo_gestion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros');
    }
};
