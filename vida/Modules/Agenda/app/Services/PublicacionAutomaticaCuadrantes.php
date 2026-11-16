<?php

namespace Modules\Agenda\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Models\Slot;
use Modules\Centro\Models\Centro;

/**
 * Publica automáticamente el cuadrante mensual de los centros que no lo tienen
 * (tarea programada `agenda:publicar-cuadrantes`, incidencia del 2026-11-16: sin
 * cuadrante publicado no hay huecos y nadie puede dar citas).
 *
 * Por centro y mes, genera las líneas desde los perfiles horarios y materializa los
 * slots (`CuadranteGeneratorService::generarYPublicarAutomaticamente`). No toca un
 * centro que ya tenga cuadrante ese mes, en cualquier estado: la supervisión puede
 * estar preparándolo a mano. Tampoco publica un cuadrante que nacería vacío (sin
 * horario vigente con tipos de slot o sin perfiles activos), porque ocuparía el mes
 * (un cuadrante por centro y mes) y nadie lo vería.
 */
class PublicacionAutomaticaCuadrantes
{
    /**
     * Crea el servicio.
     *
     * @param CuadranteGeneratorService $generador Generación y publicación del cuadrante.
     */
    public function __construct(private CuadranteGeneratorService $generador) {}

    /**
     * Publica el cuadrante del mes en cada centro que lo necesite.
     *
     * @param int $anyo Año.
     * @param int $mes Mes (1-12).
     * @param list<int> $centroIds Centros a considerar; vacío = todos.
     *
     * @return list<array{centro: string, resultado: string, slots: int}> Resultado por centro.
     */
    public function publicarMes(int $anyo, int $mes, array $centroIds = []): array
    {
        $resultados = [];

        $centros = Centro::query()
            ->when($centroIds !== [], fn ($q) => $q->whereIn('id', $centroIds))
            ->orderBy('id')
            ->get();

        foreach ($centros as $centro) {
            $motivo = $this->motivoParaSaltar($centro, $anyo, $mes);

            if ($motivo !== null) {
                $resultados[] = ['centro' => $centro->nombre, 'resultado' => $motivo, 'slots' => 0];

                continue;
            }

            $cuadrante = DB::transaction(fn (): CuadranteMes => $this->generador->generarYPublicarAutomaticamente($centro, $anyo, $mes));
            $slots = Slot::whereIn('linea_cuadrante_id', $cuadrante->lineas()->select('id'))->count();

            $resultados[] = ['centro' => $centro->nombre, 'resultado' => 'publicado', 'slots' => $slots];
        }

        return $resultados;
    }

    /**
     * Motivo por el que no se publica el cuadrante del centro ese mes, o null si procede.
     *
     * @param Centro $centro Centro.
     * @param int $anyo Año.
     * @param int $mes Mes.
     *
     * @return string|null
     */
    private function motivoParaSaltar(Centro $centro, int $anyo, int $mes): ?string
    {
        if (CuadranteMes::delCentro($centro->id)->delMes($anyo, $mes)->exists()) {
            return 'ya tiene cuadrante';
        }

        $primerDia = Carbon::create($anyo, $mes, 1)->toDateString();
        $ultimoDia = Carbon::create($anyo, $mes, 1)->endOfMonth()->toDateString();

        $horario = HorarioCentro::where('centro_id', $centro->id)
            ->where('activo', true)
            ->where('vigente_desde', '<=', $primerDia)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $primerDia))
            ->first();

        if ($horario === null) {
            return 'sin horario vigente';
        }

        if (! $horario->tiposSlot()->where('tipos_slot.activo', true)->exists()) {
            return 'el horario no tiene tipos de slot';
        }

        $hayPerfiles = PerfilHorarioProfesional::where('centro_id', $centro->id)
            ->where('activo', true)
            ->where('vigente_desde', '<=', $ultimoDia)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $primerDia))
            ->exists();

        return $hayPerfiles ? null : 'sin perfiles horarios activos';
    }
}
