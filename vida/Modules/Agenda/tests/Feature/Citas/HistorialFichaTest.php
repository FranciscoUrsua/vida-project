<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Livewire\Citas\CitaPage;
use Modules\Agenda\Livewire\Citas\HistorialCitasFicha;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Ciudadania\Tests\Concerns\ConsultaBasicaTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Historial de citas en la ficha del ciudadano (docs/modulo-citas.md §6 bis; fase 4, grupo B).
 *
 * TF-CBA-13 a 19.
 *
 * @see docs/instrucciones-cli/2026-11-fase4-consulta-basica.md
 */
class HistorialFichaTest extends TestCase
{
    use ConsultaBasicaTestSetup;
    use RefreshDatabase;

    /**
     * Prepara el escenario de la fase (seis citas de Ana).
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarConsultaBasica();
    }

    /** TF-CBA-13 — Historial completo, de cualquier centro. */
    #[Test]
    public function tf_cba_13_completo_y_de_cualquier_centro(): void
    {
        $bloque = Livewire::actingAs($this->ventanillaNorte)
            ->test(HistorialCitasFicha::class, ['ciudadanoId' => $this->ana->id]);

        foreach (['20/10/2026', '22/10/2026', '27/10/2026', '29/10/2026', '05/11/2026', '06/11/2026'] as $fecha) {
            $bloque->assertSee($fecha);
        }

        $bloque->assertSee(self::TIPO_PUBLICO)
            ->assertSee('CIAM Puente de Vallecas')
            ->assertSee('CSS Norte')
            ->assertSee($this->psicologaVallecas->nombreVisible())
            ->assertSee('Presencial')
            ->assertSee(EstadoCita::Completada->label())
            ->assertSee(EstadoCita::NoShowCiudadano->label())
            ->assertSee(EstadoCita::Confirmada->label());

        // También en la ficha
        $this->actingAs($this->ventanillaNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertSee('id="ficha-citas"', false)
            ->assertSee('06/11/2026');
    }

    /** TF-CBA-14 — Nunca lo interno [negativo]. */
    #[Test]
    public function tf_cba_14_nunca_lo_interno(): void
    {
        $this->actingAs($this->ventanillaNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertOk()
            ->assertSee(self::TIPO_PUBLICO)
            ->assertDontSee(self::TIPO_INTERNO)
            ->assertDontSee(self::MOTIVO_SECRETO);
    }

    /** TF-CBA-15 — Las reprogramadas se agrupan con su sustituta. */
    #[Test]
    public function tf_cba_15_reprogramadas_agrupadas(): void
    {
        $bloque = Livewire::actingAs($this->ventanillaNorte)
            ->test(HistorialCitasFicha::class, ['ciudadanoId' => $this->ana->id]);

        $grupos = $bloque->instance()->grupos;
        $this->assertCount(5, $grupos, 'Seis citas, cinco grupos: la reprogramada va con su sustituta');

        $grupo = $grupos->first(fn (array $g): bool => $g['cita']->id === $this->citaSustituta->id);
        $this->assertSame([$this->citaReprogramada->id], $grupo['anteriores']->pluck('id')->all());
        $this->assertFalse($grupos->contains(fn (array $g): bool => $g['cita']->id === $this->citaReprogramada->id));

        $bloque->assertSee('Reprogramada desde el 27/10/2026');
    }

    /** TF-CBA-16 — Acciones solo sobre las de su UO. */
    #[Test]
    public function tf_cba_16_acciones_en_su_uo(): void
    {
        $bloque = Livewire::actingAs($this->ventanillaNorte)
            ->test(HistorialCitasFicha::class, ['ciudadanoId' => $this->ana->id]);

        $this->assertTrue($bloque->instance()->gestionable($this->citaFuturaNorte));
        $this->assertFalse($bloque->instance()->gestionable($this->citaFuturaCiam));
        $bloque->assertSee(route('agenda.citas.show', $this->citaFuturaNorte))
            ->assertDontSee(route('agenda.citas.show', $this->citaFuturaCiam));

        // Su UO: el detalle ofrece reprogramar y cancelar, y cancelar funciona
        Livewire::actingAs($this->ventanillaNorte)->test(CitaPage::class, ['cita' => $this->citaFuturaNorte])
            ->assertSet('puedeReprogramar', true)
            ->assertSet('puedeCancelar', true);
        app(CitacionService::class)->cancelar($this->citaFuturaNorte, PedidoPor::Ciudadano, 'La persona no puede venir', $this->ventanillaNorte);
        $this->assertSame(EstadoCita::Cancelada, $this->citaFuturaNorte->fresh()->estado);

        // Otra UO: ni detalle ni cancelación en el servidor
        $this->actingAs($this->ventanillaNorte)->get(route('agenda.citas.show', $this->citaFuturaCiam))->assertForbidden();
        try {
            app(CitacionService::class)->cancelar($this->citaFuturaCiam, PedidoPor::Ciudadano, 'Intento', $this->ventanillaNorte);
            $this->fail('No debería poder cancelar una cita de otra UO.');
        } catch (AuthorizationException|\DomainException) {
            // RN-15 o la policy lo rechazan
        }
        $this->assertSame(EstadoCita::Confirmada, $this->citaFuturaCiam->fresh()->estado);
    }

    /** TF-CBA-17 — Las propias no [negativo]. */
    #[Test]
    public function tf_cba_17_las_propias_no(): void
    {
        /** @var User $auxiliar */
        $auxiliar = $this->profesionalCon('auxiliar.norte', 'intervencion', $this->cssNorte, 'Auxiliar de servicios sociales');
        $auxiliar->assignRole('consulta_basica');
        $propia = $this->cita($this->ana, $auxiliar, $this->cssNorte, '2026-11-10', '09:00', EstadoCita::Confirmada);

        $bloque = Livewire::actingAs($auxiliar)->test(HistorialCitasFicha::class, ['ciudadanoId' => $this->ana->id])
            ->assertSee('10/11/2026');
        $this->assertFalse($bloque->instance()->gestionable($propia));
        $this->assertTrue($bloque->instance()->gestionable($this->citaFuturaNorte));

        Livewire::actingAs($auxiliar)->test(CitaPage::class, ['cita' => $propia])
            ->assertSet('puedeReprogramar', false)
            ->assertSet('puedeCancelar', false);

        $this->expectException(AuthorizationException::class);
        app(CitacionService::class)->cancelar($propia, PedidoPor::Ciudadano, 'Intento', $auxiliar);
    }

    /** TF-CBA-18 — Intervención también ve el historial. */
    #[Test]
    public function tf_cba_18_intervencion_tambien_ve_el_historial(): void
    {
        $this->actingAs($this->tsNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertOk()
            ->assertSee('id="ficha-citas"', false)
            // Quien lee la historia ve el nombre interno del tipo (su vista habitual)
            ->assertSee(self::TIPO_INTERNO);
    }

    /** TF-CBA-19 — Paginación: 10 pasadas más recientes, todas las futuras y «Ver más». */
    #[Test]
    public function tf_cba_19_paginacion(): void
    {
        // 25 citas pasadas en total (3 grupos ya existentes, uno con su reprogramada, + 22 nuevas)
        for ($i = 1; $i <= 22; $i++) {
            $this->cita($this->ana, $this->tsNorte2, $this->cssNorte, sprintf('2026-09-%02d', $i), '10:00', EstadoCita::Completada);
        }

        $bloque = Livewire::actingAs($this->ventanillaNorte)
            ->test(HistorialCitasFicha::class, ['ciudadanoId' => $this->ana->id]);

        $this->assertCount(2, $bloque->instance()->futuras);
        $this->assertCount(10, $bloque->instance()->pasadas);
        $this->assertSame('29/10/2026', $bloque->instance()->pasadas->first()['cita']->fecha->format('d/m/Y'));
        $bloque->assertSee('Ver más (15)')
            ->assertDontSee('01/09/2026');

        $bloque->call('verMas')
            ->assertSee('01/09/2026')
            ->assertDontSee('Ver más');
        $this->assertCount(25, $bloque->instance()->pasadas);
    }
}
