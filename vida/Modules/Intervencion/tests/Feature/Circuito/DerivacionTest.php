<?php

namespace Modules\Intervencion\Tests\Feature\Circuito;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\DerivacionEvento;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Services\DerivacionService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use Modules\Mensajes\Models\Alerta;
use Modules\Prestaciones\Enums\ViaAcceso;
use Modules\Prestaciones\Models\Prestacion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo C — Derivación a atención especializada (TF-CIR-15 a 24, 27 y 43; fase 3, F3-5 y F3-6).
 *
 * El historial de cada derivación es inmutable (principio 4.2): solo inserción, también
 * en la base de datos.
 */
class DerivacionTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    private DerivacionService $servicio;

    /**
     * Escenario del circuito.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();
        $this->servicio = app(DerivacionService::class);
    }

    /** TF-CIR-15 — Una prestación de derivación exige un tipo de plan especializado. */
    #[Test]
    public function tf_cir_15_configuracion_de_la_prestacion_de_derivacion(): void
    {
        $datos = ['codigo' => 'DER-X', 'nombre' => 'Derivación X', 'tipo_prestacion' => 'servicio', 'nivel_garantia' => 'garantizada', 'via_acceso' => ViaAcceso::Derivacion, 'activa' => true];

        $this->assertRechazado(fn () => Prestacion::create($datos));
        $this->assertRechazado(fn () => Prestacion::create($datos + ['tipo_plan_derivacion_id' => $this->planPiso->id]));
        $this->assertFalse(Prestacion::where('codigo', 'DER-X')->exists());

        // Una prestación que no es de derivación no guarda tipo de plan.
        $plaza = Prestacion::create(['codigo' => 'PLZ', 'via_acceso' => ViaAcceso::Plaza, 'tipo_plan_derivacion_id' => $this->planPia->id] + array_diff_key($datos, ['codigo' => 1, 'via_acceso' => 1]));
        $this->assertNull($plaza->tipo_plan_derivacion_id);
    }

    /** TF-CIR-16 — Derivar: pendiente, evento, apunte y alerta a la dirección del destino. */
    #[Test]
    public function tf_cir_16_derivar(): void
    {
        $derivacion = $this->derivarAna();

        $this->assertSame(EstadoDerivacion::Pendiente, $derivacion->estado);
        $this->assertSame([$this->planPia->id, $this->ciamVallecas->id], [$derivacion->tipo_plan_id, $derivacion->centro_destino_id]);
        $this->assertSame(1, $derivacion->eventos()->count());
        $this->assertTrue(Apunte::withoutGlobalScopes()->where('historia_id', $this->historiaAna->id)->where('tipo', TipoApunte::Derivacion)->where('apuntable_id', $derivacion->id)->exists());
        $this->assertTrue(Alerta::pendientesPara($this->directoraVallecas)->where('origen_type', Derivacion::class)->where('origen_id', $derivacion->id)->exists());
    }

    /** TF-CIR-17 — Destino que no ofrece el tipo de plan. [negativo] */
    #[Test]
    public function tf_cir_17_destino_que_no_ofrece_el_tipo(): void
    {
        $this->assertRechazado(fn () => $this->servicio->derivar($this->pisoAna, $this->derivPia, $this->cssNorte, 'Motivo', null, $this->tsNorte));
        $this->assertSame(0, Derivacion::count());
    }

    /** TF-CIR-18 — Solo desde el plan principal activo y por quien puede editarlo. */
    #[Test]
    public function tf_cir_18_solo_desde_el_plan_principal_activo_y_editable(): void
    {
        $pia = $this->planActivo($this->historiaAna, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $this->pisoAna);
        $psico = $this->planActivo($this->historiaAna, $this->planPsicologico, $this->cssNorte, $this->psicologoNorte);
        [, $historia] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);
        $cerrado = $this->planActivo($historia, $this->planPiso, $this->cssNorte, $this->tsNorte);
        $cerrado->update(['estado' => EstadoPlan::Cerrado, 'fecha_cierre' => '2026-11-02', 'motivo_cierre' => 'fin_intervencion']);

        $this->assertRechazado(fn () => $this->servicio->derivar($pia, $this->derivPia, $this->ciamUsera, 'Motivo', null, $this->psicologaVallecas));
        $this->assertRechazado(fn () => $this->servicio->derivar($psico, $this->derivPia, $this->ciamVallecas, 'Motivo', null, $this->psicologoNorte));
        $this->assertRechazado(fn () => $this->servicio->derivar($cerrado, $this->derivPia, $this->ciamVallecas, 'Motivo', null, $this->tsNorte));
        $this->assertRechazado(fn () => $this->servicio->derivar($this->pisoAna, $this->derivPia, $this->ciamVallecas, 'Motivo', null, $this->tsNorte2));
        $this->assertSame(0, Derivacion::count());
    }

    /** TF-CIR-43 — Sin herramienta «Derivación» en la caja; los apuntes antiguos siguen en el timeline. [negativo] */
    #[Test]
    public function tf_cir_43_sin_herramienta_derivacion_en_la_caja(): void
    {
        $antiguo = Apunte::create([
            'historia_id' => $this->historiaAna->id, 'autor_id' => $this->tsNorte->id, 'fecha' => '2026-09-01',
            'tipo' => TipoApunte::Derivacion, 'contenido' => "Urgencia: ordinaria\nMotivo: antiguo", 'visibilidad' => VisibilidadApunte::Profesionales,
        ]);

        $this->actingAs($this->tsNorte);
        $componente = Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna]);

        $componente->assertDontSee('Crear derivación')->assertDontSeeHtml("seleccionarHerramienta('derivacion')");
        $this->assertContains($antiguo->id, $componente->get('apuntesHS')->pluck('id')->all());

        $this->expectException(MethodNotFoundException::class);
        $componente->call('crearDerivacion');
    }

    /** TF-CIR-19 — Sin duplicados pendientes al mismo destino; a otro centro, sí. */
    #[Test]
    public function tf_cir_19_sin_duplicados(): void
    {
        $this->derivarAna();

        $this->assertRechazado(fn () => $this->derivarAna());
        $otra = $this->servicio->derivar($this->pisoAna, $this->derivPia, $this->ciamUsera, 'Más cerca de su domicilio', null, $this->tsNorte);
        $this->assertSame($this->ciamUsera->id, $otra->centro_destino_id);
    }

    /** TF-CIR-20 — Aceptar abre el PIA en borrador vinculado al PISO. */
    #[Test]
    public function tf_cir_20_aceptar_crea_el_plan(): void
    {
        $derivacion = $this->derivarAna();

        $pia = $this->servicio->aceptar($derivacion, $this->psicologaVallecas, $this->directoraVallecas);

        $derivacion->refresh();
        $this->assertSame(EstadoDerivacion::Aceptada, $derivacion->estado);
        $this->assertSame($pia->id, $derivacion->plan_especializado_id);
        $this->assertSame(EstadoPlan::Borrador, $pia->estado);
        $this->assertSame([$this->pisoAna->id, $this->ciamVallecas->id, $this->psicologaVallecas->id, $this->planPia->id],
            [$pia->plan_asp_id, $pia->centro_id, $pia->profesional_responsable_id, $pia->tipo_plan_id]);
        $this->assertSame(2, $derivacion->eventos()->count());
        $this->assertTrue(Alerta::pendientesPara($this->tsNorte)->where('origen_id', $derivacion->id)->where('origen_type', Derivacion::class)->exists());
        $this->assertTrue(Alerta::pendientesPara($this->psicologaVallecas)->where('origen_id', $derivacion->id)->where('origen_type', Derivacion::class)->exists());
    }

    /** TF-CIR-21 — Quién acepta: solo la dirección o supervisión adscrita, con responsable del centro. [negativo] */
    #[Test]
    public function tf_cir_21_quien_acepta(): void
    {
        $derivacion = $this->derivarAna();

        foreach ([$this->psicologaVallecas, $this->tsNorte, $this->coordIgualdad] as $actor) {
            $this->assertRechazado(fn () => $this->servicio->aceptar($derivacion, $this->psicologaVallecas, $actor));
        }
        $this->assertRechazado(fn () => $this->servicio->aceptar($derivacion, $this->psicologoNorte, $this->directoraVallecas));

        $this->assertSame(EstadoDerivacion::Pendiente, $derivacion->fresh()->estado);
        $this->assertFalse(PlanDeIntervencion::withoutGlobalScopes()->where('tipo_plan_id', $this->planPia->id)->exists());
    }

    /** TF-CIR-22 — Rechazar con motivo; sin motivo, no. */
    #[Test]
    public function tf_cir_22_rechazar(): void
    {
        $derivacion = $this->derivarAna();

        $this->assertRechazado(fn () => $this->servicio->rechazar($derivacion, '  ', $this->directoraVallecas));

        $this->servicio->rechazar($derivacion, 'No cumple el perfil del centro', $this->directoraVallecas);

        $derivacion->refresh();
        $this->assertSame(EstadoDerivacion::Rechazada, $derivacion->estado);
        $this->assertSame('No cumple el perfil del centro', $derivacion->motivo_rechazo);
        $this->assertNull($derivacion->plan_especializado_id);
        $alerta = Alerta::pendientesPara($this->tsNorte)->where('origen_id', $derivacion->id)->where('origen_type', Derivacion::class)->sole();
        $this->assertStringContainsString('No cumple el perfil del centro', $alerta->cuerpo);
    }

    /** TF-CIR-23 — Anular: quien derivó y solo si está pendiente. */
    #[Test]
    public function tf_cir_23_anular(): void
    {
        $derivacion = $this->derivarAna();
        $this->assertRechazado(fn () => $this->servicio->anular($derivacion, 'Ya no procede', $this->tsNorte2));

        $this->servicio->anular($derivacion, 'Ya no procede', $this->tsNorte);
        $this->assertSame(EstadoDerivacion::Anulada, $derivacion->fresh()->estado);

        $aceptada = $this->servicio->derivar($this->pisoAna, $this->derivPia, $this->ciamUsera, 'Motivo', null, $this->tsNorte);
        $directoraUsera = $this->directora('directora.usera', $this->ciamUsera);
        $this->adscribir($this->psicologaVallecas, $this->ciamUsera->unidadOrganizativa);
        $this->servicio->aceptar($aceptada, $this->psicologaVallecas->fresh(), $directoraUsera);
        $this->assertRechazado(fn () => $this->servicio->anular($aceptada->fresh(), 'Ya no procede', $this->tsNorte));
    }

    /** TF-CIR-24 — Historial inmutable también en la base de datos. [negativo] */
    #[Test]
    public function tf_cir_24_historial_inmutable(): void
    {
        $evento = $this->derivarAna()->eventos()->first();

        foreach (['update derivacion_eventos set motivo = null where id = ?', 'delete from derivacion_eventos where id = ?'] as $sql) {
            try {
                DB::transaction(fn () => DB::statement($sql, [$evento->id]));
                $this->fail("El trigger debía impedir: {$sql}");
            } catch (QueryException $e) {
                $this->assertStringContainsString('solo inserción', $e->getMessage());
            }
        }

        $this->assertTrue(DerivacionEvento::whereKey($evento->id)->exists());
    }

    /** TF-CIR-27 — Derivación desde un objetivo: se guarda y el objetivo la muestra con su estado. */
    #[Test]
    public function tf_cir_27_derivacion_desde_un_objetivo(): void
    {
        $derivacion = $this->servicio->derivar($this->pisoAna, $this->derivPia, $this->ciamVallecas, 'Apoyo psicológico especializado', $this->objetivoAna1, $this->tsNorte);
        $this->assertSame($this->objetivoAna1->id, $derivacion->plan_objetivo_id);

        $this->actingAs($this->tsNorte);
        $porObjetivo = Livewire::test(PlanPage::class, ['plan' => $this->pisoAna])
            ->assertSee('Derivación a CIAM Puente de Vallecas')
            ->get('derivacionesPorObjetivo');
        $this->assertSame([$derivacion->id], $porObjetivo->get($this->objetivoAna1->id)->pluck('id')->all());
        $this->assertSame(EstadoDerivacion::Pendiente, $porObjetivo->get($this->objetivoAna1->id)->first()->estado);

        // Un objetivo de otro plan no vale.
        $otro = $this->objetivoConIndicador($this->pisoMarta, 'Otro', 'si_no', 1);
        $this->assertRechazado(fn () => $this->servicio->derivar($this->pisoAna, $this->derivPia, $this->ciamUsera, 'Motivo', $otro, $this->tsNorte));
    }

    /**
     * Derivación de Ana desde su PISO al CIAM de Vallecas por su TSR.
     *
     * @return Derivacion
     */
    private function derivarAna(): Derivacion
    {
        return $this->servicio->derivar($this->pisoAna, $this->derivPia, $this->ciamVallecas, 'Necesita atención integral especializada', null, $this->tsNorte);
    }

    /**
     * Comprueba que la operación lanza DomainException.
     *
     * @param callable $operacion
     *
     * @return void
     */
    private function assertRechazado(callable $operacion): void
    {
        try {
            $operacion();
            $this->fail('Se esperaba DomainException');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }
    }
}
