<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfiles_horario_profesional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->decimal('jornada_semanal_horas', 4, 2);
            $table->json('horario_habitual');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();

            // Constraint de negocio: no pueden existir dos perfiles activos para el mismo
            // (usuario_id, centro_id) en el mismo período. Se valida en capa de aplicación,
            // no en la base de datos, para permitir mensajes de error apropiados.

            $table->index('usuario_id');
            $table->index('centro_id');
            $table->index(['usuario_id', 'centro_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfiles_horario_profesional');
    }
};
