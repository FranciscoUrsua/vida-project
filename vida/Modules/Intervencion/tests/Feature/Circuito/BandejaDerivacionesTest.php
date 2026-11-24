<?php

namespace Modules\Intervencion\Tests\Feature\Circuito;

use App\Services\PresentacionPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\DerivacionService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use Modules\Supervision\Http\Livewire\DerivacionesPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo C — Bandeja de derivaciones del centro de destino (TF-CIR-25 y 26; fase 3, B4.2).
 *
 * Personas protegidas (RN-19 de la addenda): solo la supervisión adscrita al centro de
 * destino ve su identidad y resuelve.
 */
class BandejaDerivacionesTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del circuito.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();
    }

    /** TF-CIR-25 — La directora ve las pendientes de su centro con sus datos y no las de otro CIAM. */
    #[Test]
    public function tf_cir_25_bandeja_de_derivaciones(): void
    {
        $servicio = app(DerivacionService::class);
        $aVallecas = $servicio->derivar($this->pisoAna, $this->derivPia, $this->ciamVallecas, 'Necesita atención integral', null, $this->tsNorte);
        $servicio->derivar($this->pisoAna, $this->derivPia, $this->ciamUsera, 'Alternativa', null, $this->tsNorte);

        $this->actingAs($this->directoraVallecas);
        $componente = Livewire::test(DerivacionesPage::class);
        $filas = $componente->get('pendientes');

        $this->assertSame([$aVallecas->id], $filas->pluck('derivacion.id')->all());
        $fila = $filas->first();
        $this->assertSame($this->ana->nombre_completo, $fila['persona']);
        $this->assertSame(['CSS Norte', $this->tsNorte->nombreVisible(), 'PIA', 'Necesita atención integral'], [$fila['origen'], $fila['tsr'], $fila['tipo'], $fila['motivo']]);
        $this->assertNotEmpty($fila['antiguedad']);
        $this->assertTrue($componente->get('resuelve'));

        // Aceptar desde la bandeja
        $componente->call('prepararAceptacion', $aVallecas->id)
            ->set('responsableId', $this->psicologaVallecas->id)
            ->call('aceptar')
            ->assertHasNoErrors();
        $this->assertSame(EstadoDerivacion::Aceptada, $aVallecas->fresh()->estado);
    }

    /** TF-CIR-ORD-01 — La bandeja abre con las más antiguas primero y se ordena por persona y antigüedad. */
    #[Test]
    public function bandeja_de_derivaciones_ordenable(): void
    {
        $servicio = app(DerivacionService::class);
        // La de Marta es la más antigua; la de Ana, la más reciente.
        $this->travelTo(now()->subDays(3));
        $deMarta = $servicio->derivar($this->pisoMarta, $this->derivPia, $this->ciamVallecas, 'Protocolo VG', null, $this->tsNorte);
        $this->travelBack();
        $deAna = $servicio->derivar($this->pisoAna, $this->derivPia, $this->ciamVallecas, 'Atención integral', null, $this->tsNorte);

        $this->actingAs($this->directoraVallecas);
        $componente = Livewire::test(DerivacionesPage::class);
        $ids = fn () => $componente->get('pendientes')->pluck('derivacion.id')->all();

        $this->assertSame([$deMarta->id, $deAna->id], $ids(), 'Por defecto, las más antiguas primero');

        $componente->call('ordenar', 'antiguedad');
        $this->assertSame([$deAna->id, $deMarta->id], $ids());

        $porNombre = collect([$deAna->id => $this->ana->nombre_completo, $deMarta->id => $this->marta->nombre_completo])
            ->sortBy(fn (string $n) => Str::lower(Str::ascii($n)))->keys()->all();
        $componente->call('ordenar', 'persona');
        $this->assertSame($porNombre, $ids());
        $componente->call('ordenar', 'persona');
        $this->assertSame(array_reverse($porNombre), $ids());
    }

    /** TF-CIR-26 — Persona protegida: la directora la ve; la coordinación por ámbito funcional, no, y no resuelve. */
    #[Test]
    public function tf_cir_26_bandeja_con_persona_protegida(): void
    {
        $derivacion = app(DerivacionService::class)->derivar($this->pisoMarta, $this->derivPia, $this->ciamVallecas, 'Protocolo VG', null, $this->tsNorte);

        $this->actingAs($this->directoraVallecas);
        $this->assertSame($this->marta->nombre_completo, Livewire::test(DerivacionesPage::class)->get('pendientes')->first()['persona']);

        app(CentroDeUsuario::class)->elegirSupervisado($this->coordIgualdad, $this->ciamVallecas);
        $this->actingAs($this->coordIgualdad);
        $componente = Livewire::test(DerivacionesPage::class);
        $this->assertSame(PresentacionPersona::NOMBRE_PROTEGIDO, $componente->get('pendientes')->first()['persona']);
        $this->assertFalse($componente->get('resuelve'));

        $componente->call('prepararAceptacion', $derivacion->id)
            ->set('responsableId', $this->psicologaVallecas->id)
            ->call('aceptar')
            ->assertHasErrors('responsableId');
        $this->assertSame(EstadoDerivacion::Pendiente, $derivacion->fresh()->estado);
        $this->assertSame(1, Derivacion::count());
    }
}
