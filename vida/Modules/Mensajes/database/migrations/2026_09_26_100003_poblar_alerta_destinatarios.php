<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea las filas de `alerta_destinatarios` de las alertas anteriores al
 * reconocimiento por destinatario.
 *
 * - Directas: un destinatario con el estado que tenía la alerta.
 * - A un colectivo y pendientes: un destinatario pendiente por cada usuario que
 *   hoy tiene el rol y adscripción vigente en la UO.
 * - A un colectivo y ya cerradas: solo quienes dejaron reconocimiento.
 *
 * A 2026-09-26 la tabla `alertas` de staging estaba vacía; se hace por si hay
 * datos en otro entorno. Es idempotente (ON CONFLICT DO NOTHING).
 */
return new class extends Migration
{
    /**
     * Rellena los destinatarios de las alertas existentes.
     *
     * @return void
     */
    public function up(): void
    {
        $this->poblar();
    }

    /**
     * Sin marcha atrás: la tabla desaparece con la migración que la crea.
     *
     * @return void
     */
    public function down(): void
    {
        //
    }

    /**
     * Inserta los destinatarios. Público para poder probarlo con datos previos.
     *
     * @return void
     */
    public function poblar(): void
    {
        DB::statement("
            INSERT INTO alerta_destinatarios
                (alerta_id, usuario_id, estado, atendida_en, escalada_en, escalada_a_usuario_id, created_at, updated_at)
            SELECT a.id, a.destinatario_usuario_id, a.estado,
                   (SELECT MIN(r.reconocida_en) FROM alerta_reconocimientos r
                     WHERE r.alerta_id = a.id AND r.usuario_id = a.destinatario_usuario_id
                       AND r.tipo IN ('reconocida', 'descartada')),
                   a.escalada_en, a.escalada_a_usuario_id, a.created_at, NOW()
            FROM alertas a
            WHERE a.destinatario_type = 'usuario' AND a.destinatario_usuario_id IS NOT NULL
            ON CONFLICT (alerta_id, usuario_id) DO NOTHING
        ");

        DB::statement("
            INSERT INTO alerta_destinatarios (alerta_id, usuario_id, estado, created_at, updated_at)
            SELECT DISTINCT a.id, uu.usuario_id, 'pendiente', a.created_at, NOW()
            FROM alertas a
            JOIN usuario_uo uu ON uu.unidad_organizativa_id = a.destinatario_uo_id
                AND (uu.fecha_fin IS NULL OR uu.fecha_fin >= CURRENT_DATE)
            JOIN model_has_roles mr ON mr.model_id = uu.usuario_id AND mr.model_type = ?
            JOIN roles ro ON ro.id = mr.role_id AND ro.name = a.destinatario_rol
            JOIN users u ON u.id = uu.usuario_id AND u.deleted_at IS NULL
            WHERE a.destinatario_type = 'rol_uo' AND a.estado = 'pendiente'
            ON CONFLICT (alerta_id, usuario_id) DO NOTHING
        ", [(new \App\Models\User)->getMorphClass()]);

        DB::statement("
            INSERT INTO alerta_destinatarios (alerta_id, usuario_id, estado, atendida_en, created_at, updated_at)
            SELECT r.alerta_id, r.usuario_id, 'reconocida', MIN(r.reconocida_en), MIN(r.reconocida_en), NOW()
            FROM alerta_reconocimientos r
            JOIN alertas a ON a.id = r.alerta_id
            WHERE a.destinatario_type = 'rol_uo' AND a.estado <> 'pendiente'
              AND r.tipo IN ('reconocida', 'descartada')
            GROUP BY r.alerta_id, r.usuario_id
            ON CONFLICT (alerta_id, usuario_id) DO NOTHING
        ");

        DB::statement("
            UPDATE alerta_reconocimientos r
            SET alerta_destinatario_id = d.id
            FROM alerta_destinatarios d
            WHERE d.alerta_id = r.alerta_id AND d.usuario_id = r.usuario_id
              AND r.tipo IN ('reconocida', 'descartada')
              AND r.alerta_destinatario_id IS NULL
        ");
    }
};
