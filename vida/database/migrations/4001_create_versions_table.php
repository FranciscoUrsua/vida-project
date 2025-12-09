<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versions', function (Blueprint $table) {
            $table->id();
            $table->morphs('versionable'); // Polimórfico: versionable_id y versionable_type para SocialUser, Centro, Profesional, etc.
            $table->unsignedInteger('version')->default(1); // Número secuencial de versión
            $table->json('data'); // Snapshot completo del modelo como JSON (todos fields)
            $table->foreignId('changed_by')->nullable()->constrained('app_users')->onDelete('set null'); // Quién hizo el cambio (AppUser)
            $table->string('change_reason')->nullable(); // Motivo del cambio (opcional)
            $table->timestamps();

            // Índices para queries eficientes (por entidad y versión)
            $table->index(['versionable_type', 'versionable_id', 'version']);
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versions');
    }
};
