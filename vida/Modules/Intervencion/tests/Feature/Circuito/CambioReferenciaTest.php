<?php

namespace Modules\Intervencion\Tests\Feature\Circuito;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Http\Livewire\CambioReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use Modules\Mensajes\Models\Alerta;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo E — Cambio de profesional de referencia (TF-CIR-36 a 38; fase 3, bloque E).
 *
 * Solo la supervisión adscrita al centro de la historia, no la de UO superiores; con
 * persona protegida, igual (RN-17/RN-19). Comprobado en la acción Livewire y en el servicio.
 */
class CambioReferenciaTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del circuito; los dos TS del CSS Norte pueden ser referencia.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();

        $this->tsNorte->profesional->cargo->update(['puede_ser_referencia' => true]);
        foreach ([$this->tsNorte, $this->tsNorte2] as $ts) {
            PerfilHorarioProfesional::factory()->create(['usuario_id' => $ts->id, 'centro_id' => $this->cssNorte->id, 'vigente_desde' => '2026-01-01']);
        }
    }

    /** TF-CIR-36 — La supervisión del centro cambia la referencia desde la ficha. */
    #[Test]
    public function tf_cir_36_cambiar_referencia_desde_la_interfaz(): void
    {
        $anterior = AsignacionProfesional::vigente()->where('historia_id', $this->historiaAna->id)->sole();

        Livewire::actingAs($this->supNorte)->test(CambioReferencia::class, ['historiaId' => $this->historiaAna->id])
            ->assertSee('Cambiar referencia')
            ->call('abrir')
            ->set('profesionalId', $this->tsNorte2->id)
            ->set('motivo', 'otro')
            ->call('cambiar')
            ->assertHasErrors('motivoTexto')
            ->set('motivoTexto', 'Petición de la persona')
            ->call('cambiar')
            ->assertHasNoErrors();

        $nueva = AsignacionProfesional::vigente()->where('historia_id', $this->historiaAna->id)->sole();
        $this->assertSame([$this->tsNorte2->id, OrigenAsignacionReferencia::Manual, false, 'Otro: Petición de la persona'],
            [$nueva->profesional_id, $nueva->origen, $nueva->cuenta_en_reparto, $nueva->motivo]);
        $this->assertNotNull($anterior->fresh()->fecha_fin);
        foreach ([$this->tsNorte, $this->tsNorte2] as $profesional) {
            $this->assertTrue(Alerta::pendientesPara($profesional)->where('origen_type', AsignacionProfesional::class)->where('origen_id', $nueva->id)->exists());
        }
    }

    /** TF-CIR-37 — Quién cambia la referencia y a quién, por la acción y por el servicio. [negativo] */
    #[Test]
    public function tf_cir_37_quien_cambia_la_referencia(): void
    {
        $supervisorOtroCentro = $this->directoraSur;

        foreach ([$this->tsNorte, $this->coordPrimaria, $supervisorOtroCentro] as $actor) {
            Livewire::actingAs($actor)->test(CambioReferencia::class, ['historiaId' => $this->historiaAna->id])
                ->assertDontSee('Cambiar referencia')
                ->call('abrir')
                ->assertSet('abierto', false);
        }

        // También en el servicio: la UO superior (coordPrimaria, por la funcional) tampoco.
        foreach ([$this->tsNorte, $this->coordPrimaria, $supervisorOtroCentro] as $actor) {
            $this->assertExcepcion(AuthorizationException::class, fn () => app(AsignacionReferenciaService::class)
                ->cambiarManual($this->historiaAna, $this->tsNorte2, 'Corrección', $actor, $this->cssNorte));
        }

        // Destino no adscrito al centro o sin cargo elegible
        foreach ([$this->psicologaVallecas, $this->psicologoNorte] as $destino) {
            $this->assertExcepcion(InvalidArgumentException::class, fn () => app(AsignacionReferenciaService::class)
                ->cambiarManual($this->historiaAna, $destino, 'Corrección', $this->supNorte, $this->cssNorte));
        }

        $this->assertSame($this->tsNorte->id, AsignacionProfesional::vigente()->where('historia_id', $this->historiaAna->id)->value('profesional_id'));
    }

    /** TF-CIR-38 — Persona protegida: solo la supervisión adscrita al CSS Norte ve la acción y la ejecuta. */
    #[Test]
    public function tf_cir_38_referencia_de_persona_protegida(): void
    {
        Livewire::actingAs($this->coordPrimaria)->test(CambioReferencia::class, ['historiaId' => $this->historiaMarta->id])
            ->assertDontSee('Cambiar referencia');
        $this->assertExcepcion(AuthorizationException::class, fn () => app(AsignacionReferenciaService::class)
            ->cambiarManual($this->historiaMarta, $this->tsNorte2, 'Corrección', $this->coordPrimaria, $this->cssNorte));

        Livewire::actingAs($this->supNorte)->test(CambioReferencia::class, ['historiaId' => $this->historiaMarta->id])
            ->assertSee('Cambiar referencia')
            ->call('abrir')
            ->set('profesionalId', $this->tsNorte2->id)
            ->set('motivo', 'conflicto')
            ->call('cambiar')
            ->assertHasNoErrors();

        $this->assertSame($this->tsNorte2->id, AsignacionProfesional::vigente()->where('historia_id', $this->historiaMarta->id)->value('profesional_id'));
    }

    /**
     * Comprueba que la operación lanza la excepción indicada.
     *
     * @param class-string<\Throwable> $clase
     * @param callable $operacion
     *
     * @return void
     */
    private function assertExcepcion(string $clase, callable $operacion): void
    {
        try {
            $operacion();
            $this->fail("Se esperaba {$clase}");
        } catch (\Throwable $e) {
            $this->assertInstanceOf($clase, $e, $e->getMessage());
        }
    }
}
