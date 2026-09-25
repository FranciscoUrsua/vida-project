<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\EstadoVersion;

/**
 * Versiones de documento: cada una es un PDF cifrado con su propia clave de datos (paso 2 y 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos');
            $table->integer('numero');
            $table->uuid('clave_almacenamiento')->unique()
                ->comment('Nombre del objeto en el disco. No contiene nada más');
            $table->string('disco', 50);
            $table->char('hash_sha256', 64)->comment('SHA-256 del PDF normalizado en claro');
            $table->bigInteger('tamanyo_bytes')->comment('Tamaño del PDF en claro');
            $table->integer('paginas');
            $table->text('nombre_original')->comment('Cifrado con el cast encrypted: puede contener datos personales');
            $table->string('mime_original', 100);
            $table->boolean('convertido')->default(false);
            $table->enum('canal', CanalCaptura::valores());
            $table->foreignId('subido_por')->constrained('users');
            $table->timestamp('fecha_captura');
            $table->foreignId('plantilla_informe_id')->nullable()->constrained('plantillas_informe');
            $table->foreignId('informe_id')->nullable()->constrained('informes');
            $table->enum('estado', EstadoVersion::valores())->default(EstadoVersion::Vigente->value);
            $table->text('clave_cifrada')->nullable()->comment('Clave de datos cifrada con la clave maestra; null tras destruir o purgar');
            $table->string('id_clave_maestra', 100)->nullable();
            $table->timestamps();

            $table->unique(['documento_id', 'numero']);
        });

        // Una sola versión vigente por documento.
        DB::statement("CREATE UNIQUE INDEX documento_versiones_una_vigente ON documento_versiones (documento_id) WHERE estado = 'vigente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_versiones');
    }
};
