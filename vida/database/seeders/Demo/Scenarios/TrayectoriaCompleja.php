<?php

namespace Database\Seeders\Demo\Scenarios;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Modules\Intervencion\Enums\ClasificacionSia;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoEntrevista;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Enums\UrgenciaSia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\SiaContacto;

/**
 * Escenario de trayectoria compleja.
 *
 * Ciudadano con intervención de doble nivel: plan general ASP activo y
 * plan especializado vinculado. Solo válido para UOs de tipo 'especializada'.
 *
 * Lanza LogicException si la UO no es de tipo especializada, ya que los
 * planes especializados requieren un centro especializado como contexto.
 *
 * @throws \LogicException Si la UO no es de tipo 'especializada'
 */
class TrayectoriaCompleja extends TrayectoriaActiva
{
    /**
     * Construye la trayectoria compleja para un ciudadano.
     *
     * @param Ciudadano $ciudadano Ciudadano sobre el que construir la trayectoria
     * @param User $tsr Profesional responsable (rol intervencion)
     * @param UnidadOrganizativa $uo Unidad Organizativa (debe ser tipo 'especializada')
     *
     * @throws \LogicException Si la UO no es de tipo 'especializada'
     */
    public function construir(Ciudadano $ciudadano, User $tsr, UnidadOrganizativa $uo): void
    {
        // Guardia: solo para UOs especializadas
        if ($uo->tipo !== 'especializada') {
            throw new \LogicException(
                "TrayectoriaCompleja requiere una UO de tipo 'especializada'. ".
                "La UO '{$uo->nombre}' es de tipo '{$uo->tipo}'."
            );
        }

        // 1. SiaContacto (si hay auxiliar)
        $auxiliar = $this->buscarAuxiliar($uo);

        if ($auxiliar !== null) {
            SiaContacto::create([
                'ciudadano_id' => $ciudadano->id,
                'auxiliar_id' => $auxiliar->id,
                'fecha_hora' => now()->subMonths(rand(3, 18)),
                'canal' => 'presencial',
                'descripcion_demanda' => fake('es_ES')->sentence(),
                'clasificacion' => ClasificacionSia::CompetenciaMunicipal,
                'urgencia' => UrgenciaSia::Ordinario,
            ]);
        } else {
            $this->warn("  [TrayectoriaCompleja] Sin auxiliar en UO {$uo->nombre} — SiaContacto omitido.");
        }

        // 2. Historia Social
        $historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        // 3. Entrevista inicial
        $fechaInicial = now()->subMonths(rand(2, 10));

        Entrevista::create([
            'historia_id' => $historia->id,
            'profesional_id' => $tsr->id,
            'fecha_hora' => $fechaInicial,
            'modalidad' => 'presencial',
            'tipo' => TipoEntrevista::Inicial,
            'estado' => 'realizada',
        ]);

        // 4. Plan general ASP activo
        $fechaInicioPlanAsp = today()->subMonths(rand(3, 10));

        $planAsp = PlanDeIntervencion::create([
            'historia_id' => $historia->id,
            'tipo' => TipoPlan::GeneralAsp,
            'profesional_responsable_id' => $tsr->id,
            'estado' => EstadoPlan::Activo,
            'fecha_inicio' => $fechaInicioPlanAsp,
            'fecha_firma' => $fechaInicioPlanAsp,
            'version' => 1,
        ]);

        AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $tsr->id,
            'fecha_inicio' => $fechaInicioPlanAsp->toDateString(),
        ]);

        // 5. Seguimientos del plan ASP (2-4)
        $numSeguimientosAsp = rand(2, 4);
        $this->crearSeguimientos($historia, $planAsp, $tsr, $numSeguimientosAsp);

        // 6. Plan especializado vinculado al plan ASP
        $fechaInicioPlanEsp = today()->subMonths(rand(1, 3));

        $planEspecializado = PlanDeIntervencion::create([
            'historia_id' => $historia->id,
            'tipo' => TipoPlan::Especializado,
            'plan_asp_id' => $planAsp->id,
            'profesional_responsable_id' => $tsr->id,
            'estado' => EstadoPlan::Activo,
            'fecha_inicio' => $fechaInicioPlanEsp,
            'fecha_firma' => $fechaInicioPlanEsp,
            'version' => 1,
        ]);

        // 7. Entre 1-2 seguimientos del plan especializado
        $numSeguimientosEsp = rand(1, 2);
        $this->crearSeguimientos($historia, $planEspecializado, $tsr, $numSeguimientosEsp);
    }
}
