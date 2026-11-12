<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horario y calendario por tipo de centro (docs/modulo-tipo-centro.md §5, paso 5).
 *
 * - `horarios_tipo_centro`: horario semanal del tipo, con vigencia.
 * - `horarios_centro.hereda_horario_tipo`: los horarios existentes quedan como
 *   propios (false); con true, los campos de horario del registro van a null.
 * - `cierres_calendario`: cierres y horario reducido, del tipo o del centro.
 *
 * Horario inicial de cada tipo (addenda §6.1): el más frecuente entre sus centros
 * (en empate, el del centro con menor id); sin centros con horario, L-V 9:00-14:00.
 */
return new class extends Migration
{
    /**
     * Crea las tablas, amplía `horarios_centro` y da a cada tipo su horario inicial.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('horarios_tipo_centro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_centro_id')->constrained('tipos_centro')->cascadeOnDelete();
            $table->string('nombre');
            $table->json('dias_laborables');
            $table->time('hora_apertura');
            $table->time('hora_cierre');
            $table->time('hora_inicio_atencion');
            $table->time('hora_fin_atencion');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->index(['tipo_centro_id', 'vigente_desde']);
        });

        Schema::table('horarios_centro', function (Blueprint $table) {
            $table->boolean('hereda_horario_tipo')->default(true)->after('nombre');
            $table->json('dias_laborables')->nullable()->change();
            $table->time('hora_apertura')->nullable()->change();
            $table->time('hora_cierre')->nullable()->change();
            $table->time('hora_inicio_atencion')->nullable()->change();
            $table->time('hora_fin_atencion')->nullable()->change();
        });
        // Los horarios existentes tienen su propio horario
        DB::table('horarios_centro')->update(['hereda_horario_tipo' => false]);

        Schema::create('cierres_calendario', function (Blueprint $table) {
            $table->id();
            $table->morphs('titular');
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->string('tipo', 20)->comment('cierre | horario_reducido');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('motivo', 200);
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['titular_type', 'titular_id', 'fecha_desde', 'fecha_hasta']);
        });

        $this->horariosInicialesDeLosTipos();
    }

    /**
     * Horario por defecto de cada tipo: el más frecuente entre sus centros.
     *
     * @return void
     */
    private function horariosInicialesDeLosTipos(): void
    {
        foreach (DB::table('tipos_centro')->get(['id']) as $tipo) {
            $horarios = DB::table('horarios_centro')
                ->join('centros', 'centros.id', '=', 'horarios_centro.centro_id')
                ->where('centros.tipo_centro_id', $tipo->id)
                ->whereNull('horarios_centro.deleted_at')
                ->where('horarios_centro.activo', true)
                ->orderBy('centros.id')
                ->get(['centros.id as centro_id', 'dias_laborables', 'hora_apertura', 'hora_cierre', 'hora_inicio_atencion', 'hora_fin_atencion']);

            $elegido = $horarios
                ->groupBy(fn ($h) => json_encode([json_decode($h->dias_laborables), $h->hora_apertura, $h->hora_cierre, $h->hora_inicio_atencion, $h->hora_fin_atencion]))
                // Más frecuente; en empate, el del centro con menor id (el primero por el orderBy)
                ->sortBy([fn ($a, $b) => $b->count() <=> $a->count(), fn ($a, $b) => $a->first()->centro_id <=> $b->first()->centro_id])
                ->first()?->first();

            DB::table('horarios_tipo_centro')->insert([
                'tipo_centro_id' => $tipo->id,
                'nombre' => 'Horario general',
                'dias_laborables' => $elegido->dias_laborables ?? json_encode([1, 2, 3, 4, 5]),
                'hora_apertura' => $elegido->hora_apertura ?? '09:00',
                'hora_cierre' => $elegido->hora_cierre ?? '14:00',
                'hora_inicio_atencion' => $elegido->hora_inicio_atencion ?? '09:00',
                'hora_fin_atencion' => $elegido->hora_fin_atencion ?? '14:00',
                // Desde siempre: cubre las fechas pasadas de los centros que pasen a heredar
                'vigente_desde' => '2000-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Elimina las tablas y la columna; los horarios heredados quedan sin horas.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('cierres_calendario');
        Schema::table('horarios_centro', function (Blueprint $table) {
            $table->dropColumn('hereda_horario_tipo');
        });
        Schema::dropIfExists('horarios_tipo_centro');
    }
};
