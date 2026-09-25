<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Documentos\Enums\EstadoDocumento;

/**
 * Sustituye la tabla documentos de la custodia v1 (un fichero por fila, en claro)
 * por el documento lógico de la custodia v2 (paso 2).
 *
 * No migra datos: a 2026-09-25 no hay documentos en ninguna base de datos con datos
 * (se comprobó en el paso 0). Si encuentra filas, se detiene sin tocar nada para que
 * no se pierda ningún fichero; en ese caso hay que escribir la migración de datos
 * descrita en documentos-custodia-implementacion.md.
 *
 * Las tres claves foráneas que apuntan a documentos (informes, piso_firmados y
 * firmas_plan) se quitan y se vuelven a crear sobre la tabla nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        $filas = DB::table('documentos')->count();

        if ($filas > 0) {
            throw new RuntimeException(
                "La tabla documentos tiene {$filas} filas de la custodia v1. Esta migración no migra datos: "
                .'hay que migrarlos (cifrado y vínculos) antes de reestructurar la tabla.'
            );
        }

        Schema::table('informes', fn (Blueprint $table) => $table->dropForeign(['documento_id']));
        Schema::table('piso_firmados', fn (Blueprint $table) => $table->dropForeign(['documento_id']));
        Schema::table('firmas_plan', fn (Blueprint $table) => $table->dropForeign(['documento_firmado_id']));

        Schema::drop('documentos');

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tipo_documental_id')->constrained('tipos_documentales');
            $table->string('titulo', 255)->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_validez')->nullable();
            $table->string('organo_emisor', 255)->nullable();
            $table->boolean('visible_ciudadano')->default(false);
            $table->enum('estado', EstadoDocumento::valores())->default(EstadoDocumento::Vigente->value);
            $table->jsonb('metadatos')->default('{}');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('tipo_documental_id');
            $table->index('fecha_validez');
        });

        Schema::table('informes', function (Blueprint $table) {
            $table->foreign('documento_id')->references('id')->on('documentos')->nullOnDelete();
        });
        Schema::table('piso_firmados', function (Blueprint $table) {
            $table->foreign('documento_id')->references('id')->on('documentos')->cascadeOnDelete();
        });
        Schema::table('firmas_plan', function (Blueprint $table) {
            $table->foreign('documento_firmado_id')->references('id')->on('documentos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('documentos')->count() > 0) {
            throw new RuntimeException('No se puede volver a la custodia v1 con documentos v2 existentes.');
        }

        Schema::table('informes', fn (Blueprint $table) => $table->dropForeign(['documento_id']));
        Schema::table('piso_firmados', fn (Blueprint $table) => $table->dropForeign(['documento_id']));
        Schema::table('firmas_plan', fn (Blueprint $table) => $table->dropForeign(['documento_firmado_id']));

        Schema::drop('documentos');

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->foreignId('tipo_documento_id')->constrained('catalogos_sistema');
            $table->string('origen');
            $table->string('nombre_original');
            $table->string('ruta_almacenamiento');
            $table->string('disco');
            $table->string('mime_type');
            $table->unsignedBigInteger('tamano_bytes');
            $table->string('hash_sha256');
            $table->foreignId('subido_por')->constrained('users');
            $table->text('descripcion')->nullable();
            $table->timestamps();
            $table->index('subido_por');
        });

        Schema::table('informes', function (Blueprint $table) {
            $table->foreign('documento_id')->references('id')->on('documentos')->nullOnDelete();
        });
        Schema::table('piso_firmados', function (Blueprint $table) {
            $table->foreign('documento_id')->references('id')->on('documentos')->cascadeOnDelete();
        });
        Schema::table('firmas_plan', function (Blueprint $table) {
            $table->foreign('documento_firmado_id')->references('id')->on('documentos')->nullOnDelete();
        });
    }
};
