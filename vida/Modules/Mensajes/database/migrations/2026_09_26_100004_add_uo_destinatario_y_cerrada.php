<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía dos valores cerrados del módulo de Mensajes (decisiones del
 * desarrollador de 2026-09-26):
 *
 * - `alertas.destinatario_type = 'uo'`: aviso a todo el equipo de una UO
 *   (los avisos manuales del supervisor). Exige `destinatario_uo_id`.
 * - `alerta_reconocimientos.tipo = 'cerrada'`: el supervisor cierra una
 *   parte escalada con «Cerrar alerta».
 *
 * Laravel crea las columnas `enum` en PostgreSQL como varchar con una
 * restricción CHECK; se sustituyen esas restricciones.
 */
return new class extends Migration
{
    /**
     * Amplía las restricciones.
     *
     * @return void
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE alertas DROP CONSTRAINT IF EXISTS alertas_destinatario_type_check');
        DB::statement("ALTER TABLE alertas ADD CONSTRAINT alertas_destinatario_type_check
            CHECK (destinatario_type IN ('usuario', 'rol_uo', 'uo'))");

        DB::statement('ALTER TABLE alertas DROP CONSTRAINT IF EXISTS chk_alertas_destinatario');
        DB::statement("ALTER TABLE alertas ADD CONSTRAINT chk_alertas_destinatario CHECK (
            (destinatario_type = 'usuario' AND destinatario_usuario_id IS NOT NULL)
            OR (destinatario_type = 'rol_uo' AND destinatario_rol IS NOT NULL AND destinatario_uo_id IS NOT NULL)
            OR (destinatario_type = 'uo' AND destinatario_uo_id IS NOT NULL)
        )");

        DB::statement('ALTER TABLE alerta_reconocimientos DROP CONSTRAINT IF EXISTS alerta_reconocimientos_tipo_check');
        DB::statement("ALTER TABLE alerta_reconocimientos ADD CONSTRAINT alerta_reconocimientos_tipo_check
            CHECK (tipo IN ('reconocida', 'escalada', 'descartada', 'cerrada'))");
    }

    /**
     * Restaura las restricciones anteriores (falla si ya hay filas con los valores nuevos).
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE alerta_reconocimientos DROP CONSTRAINT IF EXISTS alerta_reconocimientos_tipo_check');
        DB::statement("ALTER TABLE alerta_reconocimientos ADD CONSTRAINT alerta_reconocimientos_tipo_check
            CHECK (tipo IN ('reconocida', 'escalada', 'descartada'))");

        DB::statement('ALTER TABLE alertas DROP CONSTRAINT IF EXISTS chk_alertas_destinatario');
        DB::statement("ALTER TABLE alertas ADD CONSTRAINT chk_alertas_destinatario CHECK (
            (destinatario_type = 'usuario' AND destinatario_usuario_id IS NOT NULL)
            OR (destinatario_type = 'rol_uo' AND destinatario_rol IS NOT NULL AND destinatario_uo_id IS NOT NULL)
        )");

        DB::statement('ALTER TABLE alertas DROP CONSTRAINT IF EXISTS alertas_destinatario_type_check');
        DB::statement("ALTER TABLE alertas ADD CONSTRAINT alertas_destinatario_type_check
            CHECK (destinatario_type IN ('usuario', 'rol_uo'))");
    }
};
