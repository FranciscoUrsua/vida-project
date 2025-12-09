<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->text('first_name')->nullable(); // Text para encrypted string
            $table->text('last_name1')->nullable();
            $table->text('last_name2')->nullable();
            $table->enum('situacion_administrativa', ['activa', 'inactiva', 'suspendida'])->default('activa')->nullable();
            $table->text('numero_tarjeta_sanitaria')->nullable();
            $table->foreignId('pais_origen_id')->nullable()->constrained('countries')->onDelete('set null');
            $table->foreignId('region_id')->nullable()->constrained('regions')->onDelete('set null');
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('sexo', ['M', 'F', 'D'])->nullable();
            $table->enum('estado_civil', ['single', 'married', 'divorced', 'widowed', 'other'])->nullable();
            $table->text('lugar_empadronamiento')->nullable();
            // Georeferenciación split (text para encrypted)
            $table->text('street_type')->nullable();
            $table->text('street_name')->nullable();
            $table->text('street_number')->nullable();
            $table->text('additional_info')->nullable();
            $table->text('postal_code')->nullable();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->onDelete('set null');
            $table->string('city')->default('Madrid');
            $table->string('country')->default('España');
            $table->text('correo')->nullable()->unique(); // Unique en encrypted? Usa scope para query
            $table->text('telefono')->nullable();
            $table->foreignId('centro_adscripcion_id')->nullable()->constrained('centros')->onDelete('set null');
            $table->foreignId('profesional_referencia_id')->nullable()->constrained('profesionales')->onDelete('set null');
            $table->boolean('tiene_representante_legal')->default(false)->nullable();
            $table->foreignId('representante_legal_id')->nullable()->constrained('social_users')->onDelete('set null');
            $table->boolean('requiere_permiso_especial')->default(false)->nullable();
            $table->boolean('identificacion_desconocida')->default(false);
            $table->json('identificacion_historial')->nullable();
            $table->enum('tipo_documento', ['dni', 'nie', 'pasaporte', 'otro'])->nullable();
            $table->text('numero_id')->nullable()->unique();
            $table->decimal('lat', 11, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->boolean('direccion_validada')->default(false);
            $table->text('formatted_address')->nullable();
            $table->boolean('identificacion_validada')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('distrito_id');
            $table->index('created_by');
            $table->index('updated_by');
            $table->foreign('created_by')->references('id')->on('app_users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('app_users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('social_users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['created_by', 'updated_by']);
        });
    }
};
