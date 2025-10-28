<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Para social_users
        Schema::table('social_users', function (Blueprint $table) {
            $table->enum('tipo_documento', ['dni', 'nie', 'pasaporte', 'otro'])->nullable()->after('dni_nie_pasaporte');
            $table->string('numero_id')->nullable()->after('tipo_documento');
        });

        // Pobla datos existentes: Copia a numero_id, set tipo por defecto 'dni' (asumiendo formato DNI)
        DB::statement("
            UPDATE social_users 
            SET numero_id = dni_nie_pasaporte,
                tipo_documento = 'dni'  # Default; ajusta con lógica si tienes datos para detectar tipo
            WHERE dni_nie_pasaporte IS NOT NULL
        ");

        // Drop viejo
        Schema::table('social_users', function (Blueprint $table) {
            $table->dropColumn('dni_nie_pasaporte');
        });

        // Para ruu (mismos cambios)
        Schema::table('ruu', function (Blueprint $table) {
            $table->enum('tipo_documento', ['dni', 'nie', 'pasaporte', 'otro'])->nullable()->after('dni_nie_pasaporte');
            $table->string('numero_id')->nullable()->after('tipo_documento');
        });

        DB::statement("
            UPDATE ruu 
            SET numero_id = dni_nie_pasaporte,
                tipo_documento = 'dni'
            WHERE dni_nie_pasaporte IS NOT NULL
        ");

        Schema::table('ruu', function (Blueprint $table) {
            $table->dropColumn('dni_nie_pasaporte');
        });
    }

    public function down(): void
    {
        // Restaura viejo concatenando (ej: numero_id + letra si separada, pero como no, copia simple)
        Schema::table('social_users', function (Blueprint $table) {
            $table->string('dni_nie_pasaporte')->nullable()->after('situacion_administrativa');
            $table->dropColumn(['tipo_documento', 'numero_id']);
        });

        DB::statement("
            UPDATE social_users 
            SET dni_nie_pasaporte = numero_id
            WHERE numero_id IS NOT NULL
        ");

    }
};
