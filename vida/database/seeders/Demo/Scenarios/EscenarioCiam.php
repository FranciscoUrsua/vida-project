<?php

namespace Database\Seeders\Demo\Scenarios;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Database\Seeders\Demo\DemoContextoAditivo;
use Illuminate\Support\Carbon;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\MotivoCierre;
use Modules\Intervencion\Enums\TipoEntrevista;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\SeguimientoPlan;

/**
 * Base de los escenarios de ciudadanas del mundo aditivo «Prueba CIAM».
 *
 * A diferencia de las trayectorias de `demo:reset`, cada entidad se crea a través
 * del DemoRegistrador con una clave derivada de la clave de la ciudadana, de modo
 * que una segunda carga reutiliza lo ya creado en lugar de duplicarlo.
 *
 * Restricciones de dominio comunes a todos los escenarios CIAM (decisión explícita
 * de esta fase): ninguna usuaria es VVG, ni pertenece a un colectivo especialmente
 * protegido, ni tiene PISO, ni recibe prestaciones de VG. No hay escenario urgente:
 * las urgencias por VG se derivan a otros sistemas de atención.
 */
abstract class EscenarioCiam
{
    /** Demandas iniciales verosímiles en un CIAM (texto no identificativo). */
    protected const DEMANDAS = [
        'Solicita orientación para la búsqueda de empleo tras un periodo largo de inactividad.',
        'Pide información sobre recursos de conciliación y apoyo a la crianza.',
        'Consulta sobre sus derechos laborales y la reclamación de salarios pendientes.',
        'Demanda apoyo psicológico por aislamiento social y baja autoestima.',
        'Solicita asesoramiento jurídico sobre un proceso de separación.',
        'Busca formación en competencias digitales para realizar trámites en línea.',
        'Pide acompañamiento para acceder a recursos de vivienda.',
        'Consulta sobre ayudas económicas y el Ingreso Mínimo Vital.',
        'Solicita participar en actividades grupales del centro.',
    ];

    /** Avances registrados en seguimientos (texto no identificativo). */
    protected const AVANCES = [
        'Mantiene la asistencia a las sesiones acordadas y muestra mayor autonomía.',
        'Ha iniciado un curso de formación y valora positivamente el proceso.',
        'Se revisan los objetivos; se mantiene el plan sin cambios.',
        'Ha realizado entrevistas de trabajo con apoyo de la trabajadora social.',
        'Refiere mejora en su estado de ánimo y en su red de apoyo.',
        'Se tramita la documentación pendiente con acompañamiento.',
        'Participa en el taller grupal y comparte avances con el grupo.',
    ];

    /**
     * Construye el escenario para una ciudadana ya creada.
     *
     * @param string $clave Clave lógica de la ciudadana (p. ej. "usuaria_037")
     * @param Ciudadano $ciudadana Ciudadana del mundo
     * @param User $responsable Profesional responsable (rol intervencion)
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    abstract public function construir(string $clave, Ciudadano $ciudadana, User $responsable, DemoContextoAditivo $ctx): void;

    /**
     * Crea la Historia Social de la ciudadana en la UO del centro.
     *
     * @param string $clave Clave lógica de la ciudadana
     * @param Ciudadano $ciudadana Ciudadana del mundo
     * @param string $estado 'abierta' | 'cerrada'
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    protected function crearHistoria(string $clave, Ciudadano $ciudadana, string $estado, DemoContextoAditivo $ctx): HistoriaSocial
    {
        return $ctx->registrador->obtenerOCrear("{$clave}.historia", HistoriaSocial::class, fn () => HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => $ciudadana->id,
            'unidad_organizativa_id' => $ctx->uo->id,
            // Ninguna usuaria de este mundo pertenece a un colectivo especialmente protegido.
            'ciudadano_protegido' => false,
            'estado' => $estado,
        ]));
    }

    /**
     * Asigna la profesional de referencia a la historia (origen de «Mis casos»).
     *
     * @param string $clave Clave lógica de la ciudadana
     * @param HistoriaSocial $historia Historia social
     * @param User $responsable Profesional responsable
     * @param Carbon $inicio Fecha de inicio de la asignación
     * @param Carbon|null $fin Fecha de fin (null = vigente)
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    protected function crearAsignacion(string $clave, HistoriaSocial $historia, User $responsable, Carbon $inicio, ?Carbon $fin, DemoContextoAditivo $ctx): void
    {
        $ctx->registrador->obtenerOCrear("{$clave}.asignacion", AsignacionProfesional::class, fn () => AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $responsable->id,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin?->toDateString(),
        ]));
    }

    /**
     * Registra una entrevista realizada.
     *
     * @param string $claveEntrevista Clave lógica de la entrevista
     * @param HistoriaSocial $historia Historia social
     * @param User $profesional Profesional que la realiza
     * @param Carbon $fecha Fecha y hora
     * @param TipoEntrevista $tipo Tipo de entrevista
     * @param PlanDeIntervencion|null $plan Plan al que se vincula (seguimientos)
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    protected function crearEntrevista(
        string $claveEntrevista,
        HistoriaSocial $historia,
        User $profesional,
        Carbon $fecha,
        TipoEntrevista $tipo,
        ?PlanDeIntervencion $plan,
        DemoContextoAditivo $ctx
    ): Entrevista {
        return $ctx->registrador->obtenerOCrear($claveEntrevista, Entrevista::class, fn () => Entrevista::create([
            'historia_id' => $historia->id,
            'profesional_id' => $profesional->id,
            'plan_intervencion_id' => $plan?->id,
            'fecha_hora' => $fecha,
            'modalidad' => 'presencial',
            'tipo' => $tipo,
            'estado' => 'realizada',
        ]));
    }

    /**
     * Crea el plan de entrada directa (tipo del mundo, especializado, sin plan ASP).
     *
     * @param string $clave Clave lógica de la ciudadana
     * @param HistoriaSocial $historia Historia social
     * @param User $responsable Profesional responsable del plan
     * @param Carbon $inicio Fecha de inicio (y de firma)
     * @param Carbon|null $cierre Fecha de cierre (null = plan activo)
     * @param MotivoCierre|null $motivo Motivo de cierre si está cerrado
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     *
     * @throws \LogicException Si el mundo no define tipo de plan
     */
    protected function crearPlan(
        string $clave,
        HistoriaSocial $historia,
        User $responsable,
        Carbon $inicio,
        ?Carbon $cierre,
        ?MotivoCierre $motivo,
        DemoContextoAditivo $ctx
    ): PlanDeIntervencion {
        if ($ctx->tipoPlan === null) {
            throw new \LogicException('Los escenarios con plan requieren la clave tipo_plan en el mundo.');
        }

        return $ctx->registrador->obtenerOCrear("{$clave}.plan", PlanDeIntervencion::class, fn () => PlanDeIntervencion::create([
            'historia_id' => $historia->id,
            'tipo_plan_id' => $ctx->tipoPlan->id,
            'tipo' => TipoPlan::Especializado,
            // Entrada directa por el CIAM: no nace de una derivación desde un PISO.
            'plan_asp_id' => null,
            'profesional_responsable_id' => $responsable->id,
            'estado' => $cierre === null ? EstadoPlan::Activo : EstadoPlan::Cerrado,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_firma' => $inicio->toDateString(),
            'fecha_cierre' => $cierre?->toDateString(),
            'motivo_cierre' => $motivo,
            'version' => 1,
        ]));
    }

    /**
     * Crea N seguimientos repartidos entre dos fechas, cada uno con su entrevista de seguimiento.
     *
     * @param string $clave Clave lógica de la ciudadana
     * @param HistoriaSocial $historia Historia social
     * @param PlanDeIntervencion $plan Plan seguido
     * @param User $responsable Profesional que realiza los seguimientos
     * @param int $num Número de seguimientos
     * @param Carbon $desde Inicio del periodo (inicio del plan)
     * @param Carbon $hasta Fin del periodo (hoy o cierre del plan)
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    protected function crearSeguimientos(
        string $clave,
        HistoriaSocial $historia,
        PlanDeIntervencion $plan,
        User $responsable,
        int $num,
        Carbon $desde,
        Carbon $hasta,
        DemoContextoAditivo $ctx
    ): void {
        // Carbon 3 devuelve los días como float: se reparten días completos.
        $tramo = max(1, intdiv((int) floor($desde->diffInDays($hasta)), $num + 1));

        for ($i = 1; $i <= $num; $i++) {
            // Fechas crecientes dentro del periodo, siempre en el pasado.
            $fecha = $desde->copy()->addDays($tramo * $i)->min($hasta)->setTime(mt_rand(9, 13), 0);

            $entrevista = $this->crearEntrevista(
                "{$clave}.entrevista_seguimiento_{$i}",
                $historia,
                $responsable,
                $fecha,
                TipoEntrevista::Seguimiento,
                $plan,
                $ctx
            );

            $ctx->registrador->obtenerOCrear("{$clave}.seguimiento_{$i}", SeguimientoPlan::class, fn () => SeguimientoPlan::create([
                'plan_id' => $plan->id,
                'entrevista_id' => $entrevista->id,
                'profesional_id' => $responsable->id,
                'fecha' => $fecha->toDateString(),
                'avances' => $ctx->faker->randomElement(self::AVANCES),
                'requiere_revision_plan' => false,
            ]));
        }
    }

    /**
     * Profesional que prescribe las actividades: la responsable si tiene perfil, si no la primera con perfil.
     *
     * @param User $responsable Profesional responsable
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    protected function prescriptora(User $responsable, DemoContextoAditivo $ctx): User
    {
        if ($responsable->profesional_id !== null) {
            return $responsable;
        }

        return collect($ctx->profesionales)->first(fn (User $u) => $u->profesional_id !== null) ?? $responsable;
    }
}
