<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Usuarios\Models\Profesional;

/**
 * Migración: tabla de profesionales.
 *
 * Un Profesional es la entidad raíz que describe a una persona
 * en su dimensión laboral: cargo, titulación, vínculo contractual,
 * datos de contacto profesional.
 *
 * Existe con independencia de si tiene cuenta de acceso al sistema
 * (la cuenta, si existe, vive en `users` con FK a esta tabla).
 *
 * Sujeta a versionado mediante el trait Versionable.
 * Usa SoftDeletes: no se eliminan registros de personas que han
 * participado en actividades con ciudadanos.
 *
 * @see Profesional
 * @see docs/modulo-usuarios-permisos.md sección 5.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesionales', function (Blueprint $table) {
            $table->id();

            // Datos personales
            $table->string('nombre', 100)
                ->comment('Nombre de pila');
            $table->string('apellido1', 100)
                ->comment('Primer apellido');
            $table->string('apellido2', 100)
                ->nullable()
                ->comment('Segundo apellido (puede no tener)');
            $table->char('sexo', 1)
                ->comment('M = Masculino, F = Femenino, D = No especificado');

            // Datos profesionales
            $table->foreignId('cargo_id')
                ->constrained('cargos')
                ->onDelete('restrict')
                ->comment('Cargo que ocupa: Trabajador/a Social, Psicólogo/a...');

            $table->string('categoria_profesional', 10)
                ->nullable()
                ->comment('Nivel administrativo: A1, A2, C1, C2, E');

            $table->foreignId('titulacion_id')
                ->nullable()
                ->constrained('titulaciones')
                ->onDelete('restrict')
                ->comment('Titulación académica (opcional)');

            $table->foreignId('tipo_relacion_id')
                ->constrained('tipos_relacion_profesional')
                ->onDelete('restrict')
                ->comment('Tipo de vínculo: funcionario, interino, externo...');

            $table->string('organizacion', 200)
                ->nullable()
                ->comment('Nombre de la organización externa (si tipo_relacion.es_externo = true)');

            // Contacto profesional
            $table->string('email_profesional', 255)
                ->nullable()
                ->comment('Correo de trabajo');
            $table->string('telefono_profesional', 20)
                ->nullable()
                ->comment('Teléfono de trabajo');
            $table->string('extension', 10)
                ->nullable()
                ->comment('Extensión telefónica interna');

            // Vigencia laboral
            $table->date('fecha_inicio')
                ->comment('Fecha de incorporación al servicio');
            $table->date('fecha_fin')
                ->nullable()
                ->comment('Fecha de baja del servicio. Null = en activo');
            $table->boolean('activo')
                ->default(true)
                ->comment('Para consultas rápidas sin filtrar por fecha');

            $table->timestamps();
            $table->softDeletes();

            // Índices para búsquedas frecuentes
            $table->index(['apellido1', 'apellido2', 'nombre']);
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesionales');
    }
};
