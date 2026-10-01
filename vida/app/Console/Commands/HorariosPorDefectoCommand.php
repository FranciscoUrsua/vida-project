<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Services\PerfilHorarioPorDefectoService;
use Modules\Centro\Models\Centro;

/**
 * Da el horario del centro a los profesionales adscritos que aún no tienen
 * perfil horario en él (los anteriores a la asignación automática al adscribir).
 *
 * Uso:
 *   php artisan agenda:horarios-por-defecto --centro=13
 *   php artisan agenda:horarios-por-defecto --todos
 *
 * Los perfiles quedan como «horario no personalizado» y la supervisión de cada
 * centro recibe un aviso. No modifica perfiles existentes.
 */
class HorariosPorDefectoCommand extends Command
{
    protected $signature = 'agenda:horarios-por-defecto
                            {--centro=* : Id de los centros a completar}
                            {--todos : Completa todos los centros}';

    protected $description = 'Asigna el horario del centro a los profesionales adscritos sin perfil horario';

    /**
     * Ejecuta el comando.
     *
     * @param PerfilHorarioPorDefectoService $perfiles
     * @return int
     */
    public function handle(PerfilHorarioPorDefectoService $perfiles): int
    {
        $ids = array_map('intval', (array) $this->option('centro'));

        if ($ids === [] && ! $this->option('todos')) {
            $this->error('Indica --centro=ID (repetible) o --todos.');

            return self::FAILURE;
        }

        $centros = Centro::query()
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->get();

        foreach ($centros as $centro) {
            $creados = $perfiles->completarCentro($centro);
            $this->line("{$centro->nombre}: {$creados->count()} perfiles creados.");

            foreach ($creados as $perfil) {
                /** @var PerfilHorarioProfesional $perfil */
                $this->line("  · {$perfil->usuario->nombre_completo} ({$perfil->jornada_semanal_horas} h)");
            }
        }

        return self::SUCCESS;
    }
}
