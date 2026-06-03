<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Facades\DB;

/**
 * Verificador de invariantes de dominio para entornos de demo.
 *
 * Comprueba que los datos generados por el sistema de world-building
 * no violan restricciones fundamentales de dominio del modelo de datos.
 *
 * Solo verifica invariantes compatibles con el esquema real de tablas.
 * Las invariantes que requieren columnas no existentes (fecha_apertura,
 * fecha_cierre en historias_sociales) o citas están omitidas.
 *
 * @see DemoWorldBuilder
 * @see DemoScenarioBuilder
 */
class DemoInvariantChecker
{
    /**
     * Ejecuta todas las comprobaciones de invariantes.
     *
     * @return list<string> Lista de strings describiendo las violaciones encontradas.
     *                      Lista vacía si no hay violaciones.
     */
    public function check(): array
    {
        $violaciones = [];

        $violaciones = array_merge($violaciones, $this->checkPlanesConHistoria());
        $violaciones = array_merge($violaciones, $this->checkPlanesEspecializadosConPlanAsp());
        $violaciones = array_merge($violaciones, $this->checkHistoriasCerradasConPlanesActivos());

        return $violaciones;
    }

    /**
     * Invariante: todos los planes de intervención deben tener una historia social válida.
     *
     * @return list<string>
     */
    private function checkPlanesConHistoria(): array
    {
        $count = DB::table('planes_intervencion')
            ->whereNotIn('historia_id', DB::table('historias_sociales')->pluck('id'))
            ->count();

        if ($count === 0) {
            return [];
        }

        return ["INV-01: {$count} plan(es) de intervención sin historia social válida."];
    }

    /**
     * Invariante: los planes de tipo 'especializado' deben tener plan_asp_id.
     *
     * @return list<string>
     */
    private function checkPlanesEspecializadosConPlanAsp(): array
    {
        $count = DB::table('planes_intervencion')
            ->where('tipo', 'especializado')
            ->whereNull('plan_asp_id')
            ->count();

        if ($count === 0) {
            return [];
        }

        return ["INV-02: {$count} plan(es) especializado(s) sin plan_asp_id referenciado."];
    }

    /**
     * Invariante: no debe haber planes activos vinculados a historias cerradas.
     *
     * @return list<string>
     */
    private function checkHistoriasCerradasConPlanesActivos(): array
    {
        $count = DB::table('historias_sociales as h')
            ->join('planes_intervencion as p', 'p.historia_id', '=', 'h.id')
            ->where('h.estado', 'cerrada')
            ->where('p.estado', 'activo')
            ->whereNull('h.deleted_at')
            ->whereNull('p.deleted_at')
            ->count();

        if ($count === 0) {
            return [];
        }

        return ["INV-03: {$count} plan(es) activo(s) vinculado(s) a historia(s) con estado 'cerrada'."];
    }
}
