<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: registro genérico de entidades creadas por mundos demo aditivos.
 *
 * Permite localizar todo lo que un mundo aditivo (p. ej. «Prueba CIAM», etiqueta
 * TEST_CIAM) ha creado en un entorno con datos que no son suyos, sin añadir
 * columnas a las tablas de dominio. Es también la base de la idempotencia de
 * `demo:load`: (etiqueta, clave, registrable_type) identifica cada entidad.
 *
 * La tabla no está en TABLAS_TRUNCAR de `demo:reset`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demo_world_registros', function (Blueprint $table) {
            $table->id();
            $table->string('etiqueta', 60);
            $table->string('clave', 190);
            $table->string('registrable_type');
            $table->unsignedBigInteger('registrable_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['etiqueta', 'clave', 'registrable_type']);
            $table->index(['registrable_type', 'registrable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_world_registros');
    }
};
