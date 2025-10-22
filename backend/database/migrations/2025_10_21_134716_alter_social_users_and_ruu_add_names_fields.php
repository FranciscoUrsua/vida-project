<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Para social_users: Agrega nombres separados (nullable primero)
        Schema::table('social_users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name1')->nullable()->after('first_name');
            $table->string('last_name2')->nullable()->after('last_name1');
        });

        // Pobla nombres desde nombre_apellidos (split)
        DB::statement("
            UPDATE social_users 
            SET first_name = split_part(nombre_apellidos, ' ', 1),
                last_name1 = split_part(nombre_apellidos, ' ', 2),
                last_name2 = CASE 
                    WHEN length(nombre_apellidos) - length(replace(nombre_apellidos, ' ', '')) >= 2 
                    THEN split_part(nombre_apellidos, ' ', 3) 
                    ELSE NULL 
                END
            WHERE nombre_apellidos IS NOT NULL
        ");

        // Drop nombre_apellidos
        Schema::table('social_users', function (Blueprint $table) {
            $table->dropColumn('nombre_apellidos');
        });

        // Hace nombres NOT NULL (después de poblar)
        DB::statement("
            ALTER TABLE social_users 
            ALTER COLUMN first_name SET NOT NULL,
            ALTER COLUMN last_name1 SET NOT NULL;
        ");

        // Para campos existentes: Hace nullable con raw SQL (evita error en enum)
        DB::statement("
            ALTER TABLE social_users 
            ALTER COLUMN dni_nie_pasaporte DROP NOT NULL,
            ALTER COLUMN situacion_administrativa DROP NOT NULL,
            ALTER COLUMN numero_tarjeta_sanitaria DROP NOT NULL,
            ALTER COLUMN pais_origen DROP NOT NULL,
            ALTER COLUMN fecha_nacimiento DROP NOT NULL,
            ALTER COLUMN sexo DROP NOT NULL,
            ALTER COLUMN estado_civil DROP NOT NULL,
            ALTER COLUMN lugar_empadronamiento DROP NOT NULL,
            ALTER COLUMN correo DROP NOT NULL,
            ALTER COLUMN telefono DROP NOT NULL,
            ALTER COLUMN centro_adscripcion_id DROP NOT NULL,
            ALTER COLUMN profesional_referencia_id DROP NOT NULL,
            ALTER COLUMN tiene_representante_legal DROP NOT NULL,
            ALTER COLUMN representante_legal_id DROP NOT NULL,
            ALTER COLUMN requiere_permiso_especial DROP NOT NULL;
        ");

        // Agrega flag para ID desconocida
        Schema::table('social_users', function (Blueprint $table) {
            $table->boolean('identificacion_desconocida')->default(false)->after('dni_nie_pasaporte');
        });

        // Para ruu (mismos cambios)
        Schema::table('ruu', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name1')->nullable()->after('first_name');
            $table->string('last_name2')->nullable()->after('last_name1');
        });

        DB::statement("
            UPDATE ruu 
            SET first_name = split_part(nombre_apellidos, ' ', 1),
                last_name1 = split_part(nombre_apellidos, ' ', 2),
                last_name2 = CASE 
                    WHEN length(nombre_apellidos) - length(replace(nombre_apellidos, ' ', '')) >= 2 
                    THEN split_part(nombre_apellidos, ' ', 3) 
                    ELSE NULL 
                END
            WHERE nombre_apellidos IS NOT NULL
        ");

        Schema::table('ruu', function (Blueprint $table) {
            $table->dropColumn('nombre_apellidos');
        });

        DB::statement("
            ALTER TABLE ruu 
            ALTER COLUMN first_name SET NOT NULL,
            ALTER COLUMN last_name1 SET NOT NULL;
        ");

        DB::statement("
            ALTER TABLE ruu 
            ALTER COLUMN dni_nie_pasaporte DROP NOT NULL,
            ALTER COLUMN situacion_administrativa DROP NOT NULL,
            ALTER COLUMN numero_tarjeta_sanitaria DROP NOT NULL,
            ALTER COLUMN pais_origen DROP NOT NULL,
            ALTER COLUMN fecha_nacimiento DROP NOT NULL,
            ALTER COLUMN sexo DROP NOT NULL,
            ALTER COLUMN estado_civil DROP NOT NULL,
            ALTER COLUMN lugar_empadronamiento DROP NOT NULL,
            ALTER COLUMN correo DROP NOT NULL,
            ALTER COLUMN telefono DROP NOT NULL,
            ALTER COLUMN centro_adscripcion_id DROP NOT NULL,
            ALTER COLUMN profesional_referencia_id DROP NOT NULL,
            ALTER COLUMN tiene_representante_legal DROP NOT NULL,
            ALTER COLUMN representante_legal_id DROP NOT NULL,
            ALTER COLUMN requiere_permiso_especial DROP NOT NULL;
        ");

        Schema::table('ruu', function (Blueprint $table) {
            $table->boolean('identificacion_desconocida')->default(false)->after('dni_nie_pasaporte');
        });
    }

    public function down(): void
    {
        // Revertir para social_users
        Schema::table('social_users', function (Blueprint $table) {
            $table->string('nombre_apellidos')->after('id');
            $table->dropColumn(['first_name', 'last_name1', 'last_name2', 'identificacion_desconocida']);
        });

        // Poblar de vuelta
        DB::statement("
            UPDATE social_users 
            SET nombre_apellidos = COALESCE(first_name || ' ' || last_name1 || ' ' || COALESCE(last_name2, ''), first_name || ' ' || last_name1)
            WHERE first_name IS NOT NULL
        ");

        // Restaura NOT NULL si era original (ajusta según tu setup inicial)
        DB::statement("
            ALTER TABLE social_users 
            ALTER COLUMN dni_nie_pasaporte SET NOT NULL,
            ALTER COLUMN situacion_administrativa SET NOT NULL,
            // ... resto de campos que eran NOT NULL
        ");

        // Mismo para ruu...
        // (Copia el bloque down de social_users para ruu)
    }
};
