<?php

namespace Database\Seeders\Demo\Scenarios;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Modules\Intervencion\Enums\ClasificacionSia;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\MotivoCierre;
use Modules\Intervencion\Enums\TipoEntrevista;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Enums\UrgenciaSia;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\SiaContacto;

/**
 * Escenario de trayectoria cerrada.
 *
 * Ciudadano con historia social cerrada y plan de intervención finalizado
 * por objetivos cumplidos. Representa un caso concluido satisfactoriamente.
 */
class TrayectoriaCerrada extends TrayectoriaActiva
{
    /**
     * Construye la trayectoria cerrada para un ciudadano.
     *
     * @param Ciudadano $ciudadano Ciudadano sobre el que construir la trayectoria
     * @param User $tsr Profesional responsable (rol intervencion)
     * @param UnidadOrganizativa $uo Unidad Organizativa del profesional
     */
    public function construir(Ciudadano $ciudadano, User $tsr, UnidadOrganizativa $uo): void
    {
        // 1. SiaContacto (si hay auxiliar disponible)
        $auxiliar = $this->buscarAuxiliar($uo);

        if ($auxiliar !== null) {
            SiaContacto::create([
                'ciudadano_id' => $ciudadano->id,
                'auxiliar_id' => $auxiliar->id,
                'fecha_hora' => now()->subMonths(rand(8, 24)),
                'canal' => 'presencial',
                'descripcion_demanda' => fake('es_ES')->sentence(),
                'clasificacion' => ClasificacionSia::CompetenciaMunicipal,
                'urgencia' => UrgenciaSia::Ordinario,
            ]);
        } else {
            $this->warn("  [TrayectoriaCerrada] Sin auxiliar en UO {$uo->nombre} — SiaContacto omitido.");
        }

        // 2. Historia Social cerrada
        $historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'cerrada',
        ]);

        // 3. Entrevista inicial
        $fechaInicial = now()->subMonths(rand(8, 20));

        Entrevista::create([
            'historia_id' => $historia->id,
            'profesional_id' => $tsr->id,
            'fecha_hora' => $fechaInicial,
            'modalidad' => 'presencial',
            'tipo' => TipoEntrevista::Inicial,
            'estado' => 'realizada',
        ]);

        // 4. Plan de intervención cerrado
        $mesesAtras = rand(3, 12);
        $fechaInicioPlan = today()->subMonths($mesesAtras + rand(1, 6));
        $fechaCierre = today()->subMonths(rand(1, 6));

        $plan = PlanDeIntervencion::create([
            'historia_id' => $historia->id,
            'tipo' => TipoPlan::GeneralAsp,
            'profesional_responsable_id' => $tsr->id,
            'estado' => EstadoPlan::Cerrado,
            'fecha_inicio' => $fechaInicioPlan,
            'fecha_firma' => $fechaInicioPlan,
            'fecha_cierre' => $fechaCierre,
            'motivo_cierre' => MotivoCierre::ObjetivosCumplidos,
            'version' => 1,
        ]);

        // 5. Entre 3-6 seguimientos
        $numSeguimientos = rand(3, 6);
        $this->crearSeguimientos($historia, $plan, $tsr, $numSeguimientos);
    }
}
