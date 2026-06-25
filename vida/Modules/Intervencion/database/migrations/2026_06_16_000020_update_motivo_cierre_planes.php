<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const VALORES = [
        'negativa_firma',
        'consecucion_objetivos',
        'cambio_residencia',
        'imposibilidad_localizacion',
        'fallecimiento',
        'fin_intervencion',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE planes_intervencion DROP CONSTRAINT IF EXISTS planes_intervencion_motivo_cierre_check');
            // Poner a null los valores del enum antiguo que ya no son válidos
            DB::statement("UPDATE planes_intervencion SET motivo_cierre = NULL WHERE motivo_cierre NOT IN ('".implode("','", self::VALORES)."')");
            $valores = implode("','", self::VALORES);
            DB::statement("ALTER TABLE planes_intervencion ADD CONSTRAINT planes_intervencion_motivo_cierre_check CHECK (motivo_cierre IN ('{$valores}'))");
        }

        if (DB::getDriverName() === 'mysql') {
            $valores = "'".implode("','", self::VALORES)."'";
            DB::statement("ALTER TABLE planes_intervencion MODIFY COLUMN motivo_cierre ENUM({$valores}) NULL");
        }
    }

    public function down(): void
    {
        // Revertir si fuera necesario — omitido por simplicidad
    }
};
