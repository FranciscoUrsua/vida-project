<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de ciudadanos (beneficiarios/as del sistema de servicios sociales).
 *
 * Todos los campos de datos personales se cifran en la capa de aplicación
 * (modelo Ciudadano con cast 'encrypted'). Lo que se persiste en BD es texto
 * cifrado opaco.
 *
 * La implementación completa del módulo Ciudadanía (flujo de alta, motor de
 * matching, unidades de convivencia, fichas) se detalla en docs/modulo-ciudadania.md.
 * Esta migración crea la tabla base para que el módulo de Centros pueda
 * referenciarla antes de que el módulo Ciudadanía esté operativo.
 *
 * @see Modules\Ciudadania\Models\Ciudadano
 * @see docs/modulo-ciudadania.md § 3.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciudadanos', function (Blueprint $table) {
            $table->id();

            // Alias operativo para PSH u otros casos sin identificación formal
            $table->string('alias', 200)->nullable()
                ->comment('Identificador operativo para personas sin identificación formal');

            // Datos personales — cifrados en aplicación
            $table->text('nombre')->comment('Nombre de pila — cifrado');
            $table->text('apellido1')->comment('Primer apellido — cifrado');
            $table->text('apellido2')->nullable()->comment('Segundo apellido — cifrado');
            $table->text('fecha_nacimiento')->comment('Fecha de nacimiento — cifrada');
            $table->string('sexo', 30)->comment('Sexo (configurable, no enum cerrado)');

            // Domicilio — cifrado en aplicación
            $table->text('domicilio')->nullable()->comment('Dirección completa — cifrada');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();

            // Contacto — cifrado en aplicación
            $table->text('telefono')->nullable()->comment('Teléfono — cifrado');
            $table->text('email')->nullable()->comment('Email — cifrado');

            // Nivel de identificación y contexto
            $table->string('nivel_identificacion', 30)->default('identificado')
                ->comment('identificado | probable | no_identificado');
            $table->string('contexto_alta', 50)->nullable()
                ->comment('Contexto que determinó los requisitos de alta');

            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudadanos');
    }
};
