<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de cita (docs/modulo-citas.md §2.1): qué se hace en la cita, con nombre
 * interno y etiqueta pública separados, la herramienta de Intervención que abre
 * y los tipos de slot en los que puede darse. Catálogo global.
 *
 * Crea el tipo genérico «cita», compatible con todos los tipos de slot
 * existentes, porque las citas existentes lo toman al ampliar `citas`.
 */
return new class extends Migration
{
    /**
     * Crea la tabla, el pivote con los tipos de slot y el tipo genérico.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tipos_cita', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100)->unique()->comment('Inmutable si hay citas del tipo');
            $table->string('nombre', 200)->comment('Nombre interno: solo roles con acceso a la Historia Social');
            $table->string('etiqueta_publica', 100)->comment('Nombre neutro para consulta_basica, canal externo y avisos');
            $table->string('herramienta', 30)->comment('HerramientaCita');
            $table->string('modalidad_defecto', 20)->default('presencial')->comment('ModalidadCita');
            $table->boolean('requiere_historia_social')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tipo_cita_tipo_slot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_cita_id')->constrained('tipos_cita')->cascadeOnDelete();
            $table->foreignId('tipo_slot_id')->constrained('tipos_slot')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tipo_cita_id', 'tipo_slot_id']);
        });

        $ahora = now();
        $id = DB::table('tipos_cita')->insertGetId([
            'codigo' => 'cita',
            'nombre' => 'Cita',
            'etiqueta_publica' => 'Cita',
            'herramienta' => 'ninguna',
            'modalidad_defecto' => 'presencial',
            'requiere_historia_social' => false,
            'activo' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        foreach (DB::table('tipos_slot')->pluck('id') as $tipoSlotId) {
            DB::table('tipo_cita_tipo_slot')->insert([
                'tipo_cita_id' => $id,
                'tipo_slot_id' => $tipoSlotId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    /**
     * Elimina el pivote y la tabla.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_cita_tipo_slot');
        Schema::dropIfExists('tipos_cita');
    }
};
