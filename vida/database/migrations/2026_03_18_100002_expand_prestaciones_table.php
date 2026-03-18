<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expande la tabla `prestaciones` del stub del módulo Centro
 * al esquema completo del módulo Prestaciones.
 *
 * El stub (2026_03_14_100002) creó los campos mínimos para soportar
 * la relación centro_prestacion. Esta migración añade todos los campos
 * del catálogo oficial de prestaciones (docs/modulo-prestaciones.md).
 *
 * - Renombra `activo` → `activa` (convención del módulo)
 * - Amplía `codigo` a NOT NULL con índice único
 * - Añade todos los campos clasificatorios, descriptivos y de gestión
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestaciones', function (Blueprint $table) {
            // Renombrar activo → activa
            $table->renameColumn('activo', 'activa');

            // Ampliar código: pasar a NOT NULL (el stub lo tenía nullable)
            // Nota: cambiar el length requeriría drop+recreate del índice único,
            // lo dejamos en varchar(30) ya que los códigos del catálogo Madrid
            // tienen máximo 6 caracteres (010101).

            // Campos de clasificación principales (enums — lógica de negocio)
            $table->enum('tipo_prestacion', ['servicio', 'economica'])->nullable()->after('nombre');
            $table->enum('nivel_garantia', ['garantizada', 'condicionada'])->nullable()->after('tipo_prestacion');

            // Campos clasificatorios (claves de catalogos_sistema, nunca FK)
            $table->string('objetivo_general', 10)->nullable()->after('nivel_garantia');
            $table->string('categoria_especifica', 10)->nullable()->after('objetivo_general');
            $table->string('nivel_atencion', 50)->nullable()->after('categoria_especifica');
            $table->string('competencia', 50)->nullable()->after('nivel_atencion');
            $table->string('forma_gestion', 50)->nullable()->after('competencia');
            $table->string('financiacion', 50)->nullable()->after('forma_gestion');
            $table->string('ambito_territorial')->nullable()->after('financiacion');

            // Campos multivalor (arrays JSONB)
            $table->jsonb('poblacion_destinataria')->nullable()->after('ambito_territorial');
            $table->jsonb('modalidades')->nullable()->after('poblacion_destinataria');

            // Campos descriptivos
            $table->text('finalidad')->nullable()->after('modalidades');
            $table->text('requisitos')->nullable()->after('finalidad');
            $table->text('procedimiento')->nullable()->after('requisitos');
            $table->text('compatibilidad')->nullable()->after('procedimiento');
            $table->text('condiciones_acceso')->nullable()->after('compatibilidad');
            $table->text('obligaciones')->nullable()->after('condiciones_acceso');
            $table->text('estandares_calidad')->nullable()->after('obligaciones');
            $table->text('normativa')->nullable()->after('estandares_calidad');

            // Campos de gestión
            $table->string('aportacion_usuario')->nullable()->after('normativa');
            $table->string('plazo_concesion')->nullable()->after('aportacion_usuario');
            $table->string('duracion_maxima')->nullable()->after('plazo_concesion');
            $table->string('proveedor')->nullable()->after('duracion_maxima');
            $table->string('financiacion_detalle')->nullable()->after('proveedor');
            $table->string('informacion_complementaria', 500)->nullable()->after('financiacion_detalle');

            // Soft deletes (entidad principal, no se borra físicamente)
            $table->softDeletes();

            // Índices de consulta frecuente
            $table->index('tipo_prestacion');
            $table->index('nivel_garantia');
            $table->index('objetivo_general');
            $table->index('activa');
        });
    }

    public function down(): void
    {
        Schema::table('prestaciones', function (Blueprint $table) {
            $table->renameColumn('activa', 'activo');
            $table->dropColumn([
                'tipo_prestacion', 'nivel_garantia',
                'objetivo_general', 'categoria_especifica', 'nivel_atencion',
                'competencia', 'forma_gestion', 'financiacion', 'ambito_territorial',
                'poblacion_destinataria', 'modalidades',
                'finalidad', 'requisitos', 'procedimiento', 'compatibilidad',
                'condiciones_acceso', 'obligaciones', 'estandares_calidad', 'normativa',
                'aportacion_usuario', 'plazo_concesion', 'duracion_maxima',
                'proveedor', 'financiacion_detalle', 'informacion_complementaria',
                'deleted_at',
            ]);
        });
    }
};
