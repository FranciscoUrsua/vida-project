<?php

namespace Modules\Intervencion\Tests\Concerns;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use App\Services\AmbitoUsuario;
use Modules\Centro\Models\Centro;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Models\PlanObjetivoIndicador;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Prestaciones\Enums\ViaAcceso;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;

/**
 * Datos de los tests del circuito de intervención (fase 3,
 * docs/instrucciones-cli/2026-11-fase3-circuito-intervencion.md, «Datos»).
 *
 * Reutiliza el escenario de Tipo de Centro (`$tipoCss` con el PISO, `$tipoCiam` con el
 * PIA, `$cssNorte`, `$ciamVallecas`, `$ciamUsera`…) y añade los profesionales, las
 * personas y los tipos de plan de ASP no principales de la fase.
 */
trait CircuitoTestSetup
{
    use TipoCentroTestSetup;

    protected User $tsNorte;

    protected User $tsNorte2;

    protected User $supNorte;

    protected User $psicologoNorte;

    protected User $psicologaVallecas;

    protected User $directoraVallecas;

    protected TipoPlan $planPsicologico;

    protected TipoPlan $planSocioeducativo;

    protected Ciudadano $ana;

    protected HistoriaSocial $historiaAna;

    protected PlanDeIntervencion $pisoAna;

    /** Objetivo de `$pisoAna` con indicador `conseguido_proceso_no`. */
    protected PlanObjetivo $objetivoAna1;

    /** Objetivo de `$pisoAna` con indicador `si_no`. */
    protected PlanObjetivo $objetivoAna2;

    protected Ciudadano $marta;

    protected HistoriaSocial $historiaMarta;

    protected PlanDeIntervencion $pisoMarta;

    /** Prestación de derivación al PIA. */
    protected Prestacion $derivPia;

    /**
     * Monta el escenario del circuito sobre el de Tipo de Centro.
     *
     * @return void
     */
    protected function montarCircuito(): void
    {
        $this->montarTipoCentro();

        $this->planPsicologico = TipoPlan::create(['slug' => 'asp_psicologico', 'nombre' => 'Plan de intervención psicológica', 'ambito' => 'asp', 'activo' => true]);
        $this->planSocioeducativo = TipoPlan::create(['slug' => 'asp_socioeducativo', 'nombre' => 'Plan socioeducativo', 'ambito' => 'asp', 'activo' => true]);
        $this->tipoCss->tiposPlan()->attach([$this->planPsicologico->id, $this->planSocioeducativo->id]);
        $this->planPia->update(['admite_entrada_directa' => false]);

        $this->tsNorte = $this->profesionalCon('ts.norte', 'intervencion', $this->cssNorte, 'Trabajador/a social');
        $this->tsNorte2 = $this->profesionalCon('ts.norte2', 'intervencion', $this->cssNorte, 'Trabajador/a social');
        $this->supNorte = $this->profesionalCon('sup.norte', 'supervision', $this->cssNorte, 'Coordinación de centro');
        $this->psicologoNorte = $this->profesionalCon('psicologo.norte', 'intervencion', $this->cssNorte, 'Psicólogo/a');
        $this->psicologaVallecas = $this->profesionalCon('psicologa.vallecas', 'intervencion', $this->ciamVallecas, 'Psicólogo/a');
        $this->directoraVallecas = $this->directora('directora.vallecas', $this->ciamVallecas);

        [$this->ana, $this->historiaAna] = $this->personaEnCentro($this->cssNorte, protegida: false, referencia: $this->tsNorte);
        $this->pisoAna = $this->planActivo($this->historiaAna, $this->planPiso, $this->cssNorte, $this->tsNorte);
        $this->objetivoAna1 = $this->objetivoConIndicador($this->pisoAna, 'Mejorar la situación de vivienda', 'conseguido_proceso_no', 1);
        $this->objetivoAna2 = $this->objetivoConIndicador($this->pisoAna, 'Escolarizar a los menores', 'si_no', 2);

        [$this->marta, $this->historiaMarta] = $this->personaEnCentro($this->cssNorte, protegida: true, referencia: $this->tsNorte);
        $this->pisoMarta = $this->planActivo($this->historiaMarta, $this->planPiso, $this->cssNorte, $this->tsNorte);

        $this->derivPia = Prestacion::create([
            'codigo' => 'DER-PIA', 'nombre' => 'Derivación al PIA', 'tipo_prestacion' => 'servicio', 'nivel_garantia' => 'garantizada',
            'via_acceso' => ViaAcceso::Derivacion, 'tipo_plan_derivacion_id' => $this->planPia->id, 'activa' => true,
        ]);

        app(AmbitoUsuario::class)->olvidar();
    }

    /**
     * Usuario con ficha de profesional y cargo, adscrito a la UO de un centro.
     *
     * @param string $alias
     * @param string $rol
     * @param Centro $centro
     * @param string $cargo
     *
     * @return User
     */
    protected function profesionalCon(string $alias, string $rol, Centro $centro, string $cargo): User
    {
        $profesional = Profesional::create([
            'nombre' => ucfirst($alias),
            'apellido1' => 'Prueba',
            'sexo' => 'F',
            'cargo_id' => Cargo::firstOrCreate(['nombre' => $cargo], ['activo' => true])->id,
            'tipo_relacion_id' => TipoRelacionProfesional::firstOrCreate(['nombre' => 'Funcionario/a de carrera'], ['es_externo' => false, 'activo' => true])->id,
            'fecha_inicio' => '2020-01-01',
            'activo' => true,
        ]);

        return $this->usuario($alias, $rol, $centro->unidadOrganizativa, $profesional);
    }

    /**
     * Persona con historia abierta en un centro y, si se indica, profesional de referencia.
     *
     * @param Centro $centro
     * @param bool $protegida
     * @param User|null $referencia
     *
     * @return array{0: Ciudadano, 1: HistoriaSocial}
     */
    protected function personaEnCentro(Centro $centro, bool $protegida = false, ?User $referencia = null): array
    {
        $persona = Ciudadano::factory()->create(['colectivo_extra_protegido' => $protegida]);
        $historia = HistoriaSocial::create([
            'ciudadano_id' => $persona->id,
            'unidad_organizativa_id' => $centro->unidad_organizativa_id,
            'estado' => 'abierta',
            'ciudadano_protegido' => $protegida,
        ]);

        if ($referencia !== null) {
            AsignacionProfesional::create([
                'historia_id' => $historia->id,
                'profesional_id' => $referencia->id,
                'centro_id' => $centro->id,
                'fecha_inicio' => '2026-01-01',
            ]);
        }

        return [$persona, $historia];
    }

    /**
     * Plan activo de un tipo en un centro, creado directamente (datos de partida).
     *
     * @param HistoriaSocial $historia
     * @param TipoPlan $tipo
     * @param Centro $centro
     * @param User $responsable
     * @param PlanDeIntervencion|null $planAsp
     *
     * @return PlanDeIntervencion
     */
    protected function planActivo(HistoriaSocial $historia, TipoPlan $tipo, Centro $centro, User $responsable, ?PlanDeIntervencion $planAsp = null): PlanDeIntervencion
    {
        return PlanDeIntervencion::create([
            'historia_id' => $historia->id,
            'tipo_plan_id' => $tipo->id,
            'centro_id' => $centro->id,
            'profesional_responsable_id' => $responsable->id,
            'plan_asp_id' => $planAsp?->id,
            'estado' => EstadoPlan::Activo,
            'fecha_inicio' => '2026-10-01',
            'version' => 1,
        ]);
    }

    /**
     * Objetivo general de un plan con un indicador.
     *
     * @param PlanDeIntervencion $plan
     * @param string $texto
     * @param string $tipoValoracion
     * @param int $orden
     *
     * @return PlanObjetivo
     */
    protected function objetivoConIndicador(PlanDeIntervencion $plan, string $texto, string $tipoValoracion, int $orden): PlanObjetivo
    {
        $objetivo = PlanObjetivo::create([
            'plan_id' => $plan->id,
            'nivel' => 'general',
            'texto' => $texto,
            'estado' => 'pendiente',
            'orden' => $orden,
        ]);

        PlanObjetivoIndicador::create([
            'plan_objetivo_id' => $objetivo->id,
            'descripcion' => "Indicador de {$texto}",
            'tipo_valoracion' => $tipoValoracion,
        ]);

        return $objetivo;
    }
}
