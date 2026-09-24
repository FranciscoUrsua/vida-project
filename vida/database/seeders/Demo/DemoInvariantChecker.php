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
 * En modo aditivo (`demo:load`) la comprobación se limita a los planes creados
 * por el mundo, para no bloquear la carga por datos preexistentes ajenos.
 *
 * @see DemoWorldBuilder
 * @see DemoScenarioBuilder
 */
class DemoInvariantChecker
{
    /** @var list<int>|null Planes a los que se limita la comprobación (null = todos) */
    private ?array $planIds = null;

    /**
     * Ejecuta todas las comprobaciones de invariantes.
     *
     * @param list<int>|null $planIds Si se indica, solo se comprueban esos planes
     *                                (p. ej. los registrados por un mundo aditivo)
     *
     * @return list<string> Lista de strings describiendo las violaciones encontradas.
     *                      Lista vacía si no hay violaciones.
     */
    public function check(?array $planIds = null): array
    {
        $this->planIds = $planIds;
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
            ->when($this->planIds !== null, fn ($q) => $q->whereIn('id', $this->planIds))
            ->count();

        if ($count === 0) {
            return [];
        }

        return ["INV-01: {$count} plan(es) de intervención sin historia social válida."];
    }

    /**
     * Invariante: los planes de tipo 'especializado' deben tener plan_asp_id.
     *
     * Se excluyen los planes cuyo tipo de plan admite entrada directa (p. ej. PIA
     * del CIAM): pueden existir sin plan ASP previo por regla de dominio.
     *
     * @return list<string>
     */
    private function checkPlanesEspecializadosConPlanAsp(): array
    {
        $count = DB::table('planes_intervencion as p')
            ->leftJoin('tipos_plan as t', 't.id', '=', 'p.tipo_plan_id')
            ->where('p.tipo', 'especializado')
            ->whereNull('p.plan_asp_id')
            ->where(fn ($q) => $q->whereNull('t.admite_entrada_directa')->orWhere('t.admite_entrada_directa', false))
            ->when($this->planIds !== null, fn ($q) => $q->whereIn('p.id', $this->planIds))
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
            ->when($this->planIds !== null, fn ($q) => $q->whereIn('p.id', $this->planIds))
            ->count();

        if ($count === 0) {
            return [];
        }

        return ["INV-03: {$count} plan(es) activo(s) vinculado(s) a historia(s) con estado 'cerrada'."];
    }
}
