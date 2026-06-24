<?php

namespace Database\Seeders\Demo\Scenarios;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoEntrevista;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Escenario de trayectoria urgente.
 *
 * Ciudadano atendido de urgencia: sin SiaContacto previo, con historia
 * abierta, entrevista de urgencia y plan activo en marcha con pocos seguimientos.
 * Representa un caso de atención prioritaria.
 */
class TrayectoriaUrgente extends TrayectoriaActiva
{
    /**
     * Construye la trayectoria urgente para un ciudadano.
     *
     * @param Ciudadano $ciudadano Ciudadano sobre el que construir la trayectoria
     * @param User $tsr Profesional responsable (rol intervencion)
     * @param UnidadOrganizativa $uo Unidad Organizativa del profesional
     */
    public function construir(Ciudadano $ciudadano, User $tsr, UnidadOrganizativa $uo): void
    {
        // Sin SiaContacto — acceso directo por urgencia (no pasa por el SIA)

        // 1. Historia Social abierta
        $historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        // 2. Entrevista de urgencia
        $fechaUrgencia = now()->subDays(rand(3, 30));

        Entrevista::create([
            'historia_id' => $historia->id,
            'profesional_id' => $tsr->id,
            'fecha_hora' => $fechaUrgencia,
            'modalidad' => 'presencial',
            'tipo' => TipoEntrevista::Urgencia,
            'estado' => 'realizada',
        ]);

        // 3. Plan de intervención activo (creado rápidamente por urgencia)
        $fechaInicioPlan = today()->subDays(rand(2, 25));

        $plan = PlanDeIntervencion::create([
            'historia_id' => $historia->id,
            'tipo' => TipoPlan::GeneralAsp,
            'profesional_responsable_id' => $tsr->id,
            'estado' => EstadoPlan::Activo,
            'fecha_inicio' => $fechaInicioPlan,
            'fecha_firma' => $fechaInicioPlan,
            'version' => 1,
        ]);

        AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $tsr->id,
            'fecha_inicio' => $fechaInicioPlan->toDateString(),
        ]);

        // 4. Entre 1-3 seguimientos (caso reciente)
        $numSeguimientos = rand(1, 3);
        $this->crearSeguimientos($historia, $plan, $tsr, $numSeguimientos);
    }
}
