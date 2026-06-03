<?php

namespace Database\Seeders\Demo\Scenarios;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Modules\Intervencion\Enums\ClasificacionSia;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoEntrevista;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Enums\UrgenciaSia;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\SeguimientoPlan;
use Modules\Intervencion\Models\SiaContacto;

/**
 * Escenario de trayectoria activa.
 *
 * Ciudadano con historia social abierta, plan activo y varios seguimientos.
 * Representa un caso en curso con intervención profesional completa.
 */
class TrayectoriaActiva
{
    /** @var callable|null */
    private mixed $output;

    /**
     * @param callable|null $output Callable para avisos no fatales
     */
    public function __construct(?callable $output = null)
    {
        $this->output = $output;
    }

    /**
     * Construye la trayectoria activa para un ciudadano.
     *
     * @param Ciudadano $ciudadano Ciudadano sobre el que construir la trayectoria
     * @param User $tsr Profesional responsable (rol intervencion)
     * @param UnidadOrganizativa $uo Unidad Organizativa del profesional
     *
     * @throws \RuntimeException Si no se puede construir la trayectoria
     */
    public function construir(Ciudadano $ciudadano, User $tsr, UnidadOrganizativa $uo): void
    {
        // 1. Buscar auxiliar (consulta_basica) en la misma UO para el SIA
        $auxiliar = $this->buscarAuxiliar($uo);

        // 2. SiaContacto (si hay auxiliar)
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
            $this->warn("  [TrayectoriaActiva] Sin auxiliar en UO {$uo->nombre} — SiaContacto omitido.");
        }

        // 3. Historia Social
        $historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        // 4. Entrevista inicial
        $fechaInicial = now()->subMonths(rand(2, 10));

        Entrevista::create([
            'historia_id' => $historia->id,
            'profesional_id' => $tsr->id,
            'fecha_hora' => $fechaInicial,
            'modalidad' => 'presencial',
            'tipo' => TipoEntrevista::Inicial,
            'estado' => 'realizada',
        ]);

        // 5. Plan de intervención activo
        $fechaInicioPlan = today()->subMonths(rand(1, 8));

        $plan = PlanDeIntervencion::create([
            'historia_id' => $historia->id,
            'tipo' => TipoPlan::GeneralAsp,
            'profesional_responsable_id' => $tsr->id,
            'estado' => EstadoPlan::Activo,
            'fecha_inicio' => $fechaInicioPlan,
            'fecha_firma' => $fechaInicioPlan,
            'version' => 1,
        ]);

        // 6. Entre 2-4 seguimientos
        $numSeguimientos = rand(2, 4);
        $this->crearSeguimientos($historia, $plan, $tsr, $numSeguimientos);
    }

    /**
     * Crea seguimientos para un plan, cada uno con su entrevista de seguimiento.
     *
     * @param HistoriaSocial $historia Historia social
     * @param PlanDeIntervencion $plan Plan de intervención
     * @param User $tsr Profesional responsable
     * @param int $numSeguimientos Número de seguimientos a crear
     */
    protected function crearSeguimientos(
        HistoriaSocial $historia,
        PlanDeIntervencion $plan,
        User $tsr,
        int $numSeguimientos
    ): void {
        for ($i = 0; $i < $numSeguimientos; $i++) {
            $mesesAtras = $numSeguimientos - $i;
            $fechaSeg = now()->subMonths($mesesAtras)->subDays(rand(0, 15));

            $entrevistaSeg = Entrevista::create([
                'historia_id' => $historia->id,
                'profesional_id' => $tsr->id,
                'plan_intervencion_id' => $plan->id,
                'fecha_hora' => $fechaSeg,
                'modalidad' => 'presencial',
                'tipo' => TipoEntrevista::Seguimiento,
                'estado' => 'realizada',
            ]);

            SeguimientoPlan::create([
                'plan_id' => $plan->id,
                'entrevista_id' => $entrevistaSeg->id,
                'profesional_id' => $tsr->id,
                'fecha' => $fechaSeg->toDateString(),
                'avances' => fake('es_ES')->sentence(),
                'requiere_revision_plan' => false,
            ]);
        }
    }

    /**
     * Busca un usuario con rol consulta_basica adscrito a la UO dada.
     *
     * @param UnidadOrganizativa $uo Unidad Organizativa donde buscar
     *
     * @return User|null Usuario auxiliar o null si no existe
     */
    protected function buscarAuxiliar(UnidadOrganizativa $uo): ?User
    {
        $uoIds = UsuarioUo::where('unidad_organizativa_id', $uo->id)
            ->pluck('usuario_id');

        return User::whereIn('id', $uoIds)
            ->role('consulta_basica')
            ->first();
    }

    /**
     * Emite un aviso si hay callable de output configurado.
     *
     * @param string $message Mensaje del aviso
     */
    protected function warn(string $message): void
    {
        if ($this->output !== null) {
            ($this->output)($message);
        }
    }
}
