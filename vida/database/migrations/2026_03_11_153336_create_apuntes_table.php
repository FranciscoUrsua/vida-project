<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración stub para la tabla de Apuntes (actos profesionales).
 *
 * Crea la estructura mínima necesaria para que las Policies y sus tests
 * puedan funcionar. La implementación completa corresponde al módulo
 * Intervencion (docs/glosario.md § Apunte).
 *
 * @todo Mover a Modules\Intervencion cuando se implemente ese módulo,
 *       con tipos configurables, campos específicos por tipo y relaciones completas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apuntes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('historia_social_id')
                ->constrained('historias_sociales')
                ->onDelete('restrict')
                ->comment('Historia Social a la que pertenece este apunte');

            $table->foreignId('profesional_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Usuario autor del apunte');

            // TODO: convertir en FK a tabla tipos_apunte cuando se configure el backoffice
            $table->string('tipo', 50)
                ->comment('Tipo de apunte: valoracion, seguimiento, anotacion, entrevista, gestion, informe, derivacion');

            $table->boolean('privada')
                ->default(false)
                ->comment('Si true, solo el autor tiene acceso. Precedencia absoluta sobre cualquier rol.');

            $table->timestamps();
            $table->softDeletes();

            $table->index('profesional_id');
            $table->index(['historia_social_id', 'privada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apuntes');
    }
};
