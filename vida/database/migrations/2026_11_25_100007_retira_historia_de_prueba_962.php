<?php

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Modules\Intervencion\Models\AsignacionProfesional;

/**
 * Retira (baja lógica) la Historia Social 962 de staging y su referencia.
 *
 * La abrió el 25 nov 2026 `adm1.ciam@vida.local` (`tramitacion`) en una prueba del
 * desarrollador, por el error ya corregido que dejaba a tramitación abrir historias
 * y quedar como referencia (migración `2026_11_25_100005`). El desarrollador confirma
 * que era solo una prueba. No tiene apuntes, planes ni otros registros colgando. La
 * persona (ciudadano 1073) no se toca.
 *
 * Solo actúa si la historia y la referencia son exactamente esas: en otra base de
 * datos no hace nada. No hay borrado físico.
 */
return new class extends Migration
{
    /** Historia de prueba. */
    private const HISTORIA_ID = 962;

    /** Persona de la historia. */
    private const CIUDADANO_ID = 1073;

    /** Usuario de tramitación que la abrió. */
    private const EMAIL_TRAMITADOR = 'adm1.ciam@vida.local';

    /**
     * Da de baja lógica la referencia y la historia.
     *
     * @return void
     */
    public function up(): void
    {
        $historia = HistoriaSocial::withoutGlobalScopes()
            ->where('id', self::HISTORIA_ID)
            ->where('ciudadano_id', self::CIUDADANO_ID)
            ->first();
        $tramitadorId = User::withTrashed()->where('email', self::EMAIL_TRAMITADOR)->value('id');

        if ($historia === null || $tramitadorId === null) {
            return;
        }

        $referencias = AsignacionProfesional::where('historia_id', $historia->id)->get();

        // Si alguien distinto del tramitador ya fuera referencia, no es la historia de la prueba
        if ($referencias->contains(fn (AsignacionProfesional $a) => $a->profesional_id !== $tramitadorId)) {
            return;
        }

        $referencias->each(fn (AsignacionProfesional $a) => $a->delete());
        $historia->delete();
    }

    /**
     * Recupera la historia y su referencia.
     *
     * @return void
     */
    public function down(): void
    {
        $historia = HistoriaSocial::withoutGlobalScopes()->onlyTrashed()
            ->where('id', self::HISTORIA_ID)
            ->where('ciudadano_id', self::CIUDADANO_ID)
            ->first();

        if ($historia === null) {
            return;
        }

        $historia->restore();
        AsignacionProfesional::onlyTrashed()->where('historia_id', $historia->id)->get()
            ->each(fn (AsignacionProfesional $a) => $a->restore());
    }
};
