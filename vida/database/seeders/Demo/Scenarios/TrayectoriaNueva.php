<?php

namespace Database\Seeders\Demo\Scenarios;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Modules\Intervencion\Enums\ClasificacionSia;
use Modules\Intervencion\Enums\TipoEntrevista;
use Modules\Intervencion\Enums\UrgenciaSia;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\SiaContacto;

/**
 * Escenario de trayectoria nueva.
 *
 * Ciudadano recién dado de alta con historia social abierta recientemente.
 * Tiene contacto SIA reciente, entrevista inicial, pero aún sin plan ni seguimientos.
 * Representa un caso en sus primeros pasos de valoración.
 */
class TrayectoriaNueva extends TrayectoriaActiva
{
    /**
     * Construye la trayectoria nueva para un ciudadano.
     *
     * @param Ciudadano         $ciudadano Ciudadano sobre el que construir la trayectoria
     * @param User              $tsr       Profesional responsable (rol intervencion)
     * @param UnidadOrganizativa $uo       Unidad Organizativa del profesional
     */
    public function construir(Ciudadano $ciudadano, User $tsr, UnidadOrganizativa $uo): void
    {
        // 1. SiaContacto reciente (5-20 días atrás)
        $auxiliar = $this->buscarAuxiliar($uo);

        if ($auxiliar !== null) {
            SiaContacto::create([
                'ciudadano_id'        => $ciudadano->id,
                'auxiliar_id'         => $auxiliar->id,
                'fecha_hora'          => now()->subDays(rand(5, 20)),
                'canal'               => 'presencial',
                'descripcion_demanda' => fake('es_ES')->sentence(),
                'clasificacion'       => ClasificacionSia::CompetenciaMunicipal,
                'urgencia'            => UrgenciaSia::Ordinario,
            ]);
        } else {
            $this->warn("  [TrayectoriaNueva] Sin auxiliar en UO {$uo->nombre} — SiaContacto omitido.");
        }

        // 2. Historia Social abierta recientemente (3-15 días atrás)
        $historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id'           => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        // 3. Entrevista inicial reciente
        Entrevista::create([
            'historia_id'    => $historia->id,
            'profesional_id' => $tsr->id,
            'fecha_hora'     => now()->subDays(rand(3, 15)),
            'modalidad'      => 'presencial',
            'tipo'           => TipoEntrevista::Inicial,
            'estado'         => 'realizada',
        ]);

        // Sin plan ni seguimientos — caso en fase de valoración inicial
        // Citas omitidas — requieren maquinaria de agenda compleja (ver BACKLOG)
    }
}
