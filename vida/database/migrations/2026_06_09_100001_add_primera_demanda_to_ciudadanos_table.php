<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade a ciudadanos los campos del flujo de alta.
 *
 * - primera_demanda: texto libre del momento del alta, dato operativo no sensible.
 * - *_hash: hashes deterministas para búsqueda sin descifrar (matching de duplicados).
 *
 * Ver docs/instrucciones-cli/instrucciones-cli-alta-ciudadano.md Tarea 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciudadanos', function (Blueprint $table) {
            $table->text('primera_demanda')->nullable()
                ->comment('Motivo de la visita en palabras del ciudadano — dato del momento del alta');

            // Hashes deterministas para matching sin descifrar campos cifrados
            $table->string('telefono_hash', 64)->nullable()->after('telefono')
                ->comment('SHA-256(trim(telefono)) — índice de búsqueda');
            $table->string('email_hash', 64)->nullable()->after('email')
                ->comment('SHA-256(strtolower(email)) — índice de búsqueda');
            $table->string('fecha_nacimiento_hash', 64)->nullable()->after('fecha_nacimiento')
                ->comment('SHA-256(fecha_nacimiento) — índice de búsqueda futuro');

            $table->index('telefono_hash');
            $table->index('email_hash');
            $table->index('fecha_nacimiento_hash');
        });
    }

    public function down(): void
    {
        Schema::table('ciudadanos', function (Blueprint $table) {
            $table->dropIndex(['telefono_hash']);
            $table->dropIndex(['email_hash']);
            $table->dropIndex(['fecha_nacimiento_hash']);
            $table->dropColumn(['primera_demanda', 'telefono_hash', 'email_hash', 'fecha_nacimiento_hash']);
        });
    }
};
