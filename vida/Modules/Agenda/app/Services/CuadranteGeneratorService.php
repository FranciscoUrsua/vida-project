<?php

namespace Modules\Agenda\Services;

use Carbon\Carbon;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;

/**
 * Genera el borrador de LineaCuadrante para un mes.
 *
 * En modo básico, genera y publica automáticamente sin intervención del supervisor.
 * En modo estándar/avanzado, produce un borrador para revisión antes de publicar.
 */
class CuadranteGeneratorService
{
    /**
     * Genera las LineaCuadrante para todos los profesionales con perfil activo en el centro.
     *
     * Itera cada día laborable del mes, aplica el horario habitual del perfil de cada
     * profesional e incorpora las ExcepcionProfesional ya conocidas marcando esas
     * líneas como anulada = true con referencia a la excepción.
     *
     * @param CuadranteMes $cuadrante Cuadrante en estado 'borrador'
     *
     * @return void
     */
    public function generarBorrador(CuadranteMes $cuadrante): void
    {
        $primerDia = Carbon::create($cuadrante->anyo, $cuadrante->mes, 1);
        $ultimoDia = $primerDia->copy()->endOfMonth();

        $horario = HorarioCentro::where('centro_id', $cuadrante->centro_id)
            ->where('activo', true)
            ->where('vigente_desde', '<=', $primerDia->toDateString())
            ->where(function ($q) use ($primerDia) {
                $q->whereNull('vigente_hasta')
                    ->orWhere('vigente_hasta', '>=', $primerDia->toDateString());
            })
            ->first();

        if (! $horario) {
            return;
        }

        $diasLaborables = $horario->dias_laborables;

        // Perfiles activos cuya vigencia se solapa con el mes a generar
        $perfiles = PerfilHorarioProfesional::where('centro_id', $cuadrante->centro_id)
            ->where('activo', true)
            ->where('vigente_desde', '<=', $ultimoDia->toDateString())
            ->where(function ($q) use ($primerDia) {
                $q->whereNull('vigente_hasta')
                    ->orWhere('vigente_hasta', '>=', $primerDia->toDateString());
            })
            ->get();

        if ($perfiles->isEmpty()) {
            return;
        }

        $usuarioIds = $perfiles->pluck('usuario_id');

        // Excepciones del período para estos profesionales en este centro
        $excepciones = ExcepcionProfesional::where('centro_id', $cuadrante->centro_id)
            ->whereIn('usuario_id', $usuarioIds)
            ->where('fecha_inicio', '<=', $ultimoDia->toDateString())
            ->where('fecha_fin', '>=', $primerDia->toDateString())
            ->get()
            ->groupBy('usuario_id');

        $lineasParaCrear = [];
        $now = now();

        foreach ($perfiles as $perfil) {
            $excepcionesProfesional = $excepciones->get($perfil->usuario_id, collect());

            $dia = $primerDia->copy();
            while ($dia->lte($ultimoDia)) {
                $diaSemana = $dia->isoWeekday(); // 1 = lunes … 7 = domingo

                if (in_array($diaSemana, $diasLaborables)) {
                    // Los keys del JSON horario_habitual son strings tras el cast
                    $franjas = $perfil->horario_habitual[(string) $diaSemana] ?? null;

                    if (empty($franjas)) {
                        $dia->addDay();

                        continue;
                    }

                    $fechaStr = $dia->toDateString();

                    // Primera excepción que cubre este día concreto
                    $excepcion = $excepcionesProfesional->first(
                        fn ($e) => $e->fecha_inicio->toDateString() <= $fechaStr
                               && $e->fecha_fin->toDateString() >= $fechaStr
                    );

                    $lineasParaCrear[] = [
                        'cuadrante_mes_id' => $cuadrante->id,
                        'usuario_id' => $perfil->usuario_id,
                        'centro_id' => $cuadrante->centro_id,
                        'fecha' => $fechaStr,
                        'franjas' => json_encode($franjas),
                        'anulada' => $excepcion !== null,
                        'excepcion_id' => $excepcion?->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $dia->addDay();
            }
        }

        if (! empty($lineasParaCrear)) {
            LineaCuadrante::insert($lineasParaCrear);
        }
    }

    /**
     * Genera y publica automáticamente el cuadrante de un mes.
     *
     * Usado en modo básico: crea el CuadranteMes, genera las líneas y materializa
     * los slots en un único paso, sin intervención del supervisor.
     *
     * @param Centro $centro Centro para el que se genera el cuadrante
     * @param int $anyo Año del cuadrante
     * @param int $mes Mes del cuadrante (1-12)
     *
     * @return CuadranteMes Cuadrante en estado 'publicado'
     */
    public function generarYPublicarAutomaticamente(Centro $centro, int $anyo, int $mes): CuadranteMes
    {
        $cuadrante = CuadranteMes::create([
            'centro_id' => $centro->id,
            'anyo' => $anyo,
            'mes' => $mes,
            'estado' => EstadoCuadrante::Borrador->value,
            'generado_con_ia' => false,
            'generado_automaticamente' => true,
        ]);

        $this->generarBorrador($cuadrante);

        $cuadrante->update([
            'estado' => EstadoCuadrante::Publicado->value,
            'publicado_en' => now(),
        ]);

        (new SlotMaterializadorService)->materializar($cuadrante);

        return $cuadrante->fresh();
    }
}
