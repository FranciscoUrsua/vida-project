<?php

namespace Modules\Intervencion\Tests\Feature\Encargo\Actuaciones;

use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Agenda\Database\Factories\CitaFactory;
use Modules\Agenda\Database\Factories\SlotFactory;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\HerramientaCita;
use Modules\Agenda\Enums\ModalidadCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\TipoCita;
use Modules\Agenda\Services\Citas\EnlaceAtencionCita;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\EncargoPage;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Tests\Concerns\ActuacionesEncargoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo C — Anotaciones y citas (TF-EAC-11 a 16, docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * La profesional de encargo registra anotaciones desde `EncargoPage`: las de profesionales
 * llegan al timeline del TSR con la marca del encargo (EA-6) y las privadas solo las ve ella.
 * Una anotación vinculada a una cita suya con la persona la completa (EA-10), y «Atender»
 * desde la agenda abre el encargo con la cita propuesta. Los negativos fallan si se quita la
 * restricción que protegen.
 */
class AnotacionesCitasEncargoTest extends TestCase
{
    use ActuacionesEncargoTestSetup;
    use RefreshDatabase;

    private Derivacion $encargo;

    private TipoCita $tipoCita;

    /**
     * Escenario de las actuaciones con un encargo en curso de `$ana` para la abogada.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarActuaciones();

        $this->encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $this->tipoCita = TipoCita::create([
            'codigo' => 'orientacion_juridica',
            'nombre' => 'Orientación jurídica',
            'etiqueta_publica' => 'Entrevista',
            'herramienta' => HerramientaCita::Ninguna,
            'modalidad_defecto' => ModalidadCita::Presencial,
            'requiere_historia_social' => false,
            'activo' => true,
        ]);
    }

    /** TF-EAC-11 — La anotación para profesionales llega al timeline del TSR con la marca del encargo. */
    #[Test]
    public function tf_eac_11_anotacion_en_el_timeline_del_tsr(): void
    {
        $this->actingAs($this->abogadaVallecas);
        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertSee('Anotación')
            ->set('formAnotacion.contenido', 'Primera entrevista jurídica')
            ->call('guardarAnotacion')
            ->assertHasNoErrors()
            ->assertSee('Primera entrevista jurídica');

        $apunte = Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Anotacion)->sole();
        $this->assertSame(VisibilidadApunte::Profesionales, $apunte->visibilidad);
        $this->assertSame($this->encargo->id, $apunte->derivacion_id);
        $this->assertNull($apunte->plan_id);

        $this->actingAs($this->tsNorte);
        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertSee('Primera entrevista jurídica')
            ->assertSee('Encargo · Jurídica (test) · '.$this->ciamVallecas->nombre);
    }

    /** TF-EAC-12 — La anotación privada solo la ve la abogada: ni el TSR ni la supervisión. [negativo] */
    #[Test]
    public function tf_eac_12_anotacion_privada_solo_para_su_autora(): void
    {
        $this->actingAs($this->abogadaVallecas);
        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->set('formAnotacion', ['contenido' => 'Impresión personal de la entrevista', 'visibilidad' => 'privada'])
            ->call('guardarAnotacion')
            ->assertHasNoErrors()
            ->assertSee('Impresión personal de la entrevista')
            ->assertSee('Privada');

        $apunte = Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Anotacion)->sole();
        $this->assertSame(VisibilidadApunte::Privada, $apunte->visibilidad);
        $this->assertTrue(Gate::forUser($this->abogadaVallecas)->allows('view', $apunte));

        $this->actingAs($this->tsNorte);
        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertDontSee('Impresión personal de la entrevista');

        foreach ([$this->tsNorte, $this->supNorte, $this->directoraVallecas] as $otro) {
            $this->assertFalse(Gate::forUser($otro)->allows('view', $apunte), "{$otro->name} no debe ver la anotación privada");
            $this->assertFalse(Apunte::withoutGlobalScopes()->visiblesParaUsuario($otro->id)->whereKey($apunte->id)->exists());
        }
    }

    /** TF-EAC-13 — Una anotación vinculada a la cita del día la completa. */
    #[Test]
    public function tf_eac_13_la_anotacion_completa_la_cita_del_dia(): void
    {
        $cita = $this->citaDeAbogada(today(), '10:00');

        $this->actingAs($this->abogadaVallecas);
        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertSee('Vincular a la cita de las 10:00')
            ->set('formAnotacion.contenido', 'Atendida en la cita')
            ->call('guardarAnotacion')
            ->assertHasNoErrors();

        $apunte = Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Anotacion)->sole();
        $this->assertSame($cita->id, $apunte->cita_id);
        $this->assertSame(EstadoCita::Completada, $cita->refresh()->estado);

        // Sin la casilla, la anotación no se vincula
        $otra = $this->citaDeAbogada(today(), '12:00');
        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->set('vincularCita', false)
            ->set('formAnotacion.contenido', 'Llamada sin cita')
            ->call('guardarAnotacion')
            ->assertHasNoErrors();
        $this->assertNull(Apunte::withoutGlobalScopes()->where('contenido', 'Llamada sin cita')->value('cita_id'));
        $this->assertSame(EstadoCita::Confirmada, $otra->refresh()->estado);
    }

    /** TF-EAC-14 — Una cita pendiente de cierre se completa con una anotación posterior. */
    #[Test]
    public function tf_eac_14_completa_una_cita_pendiente_de_cierre(): void
    {
        $ayer = today()->subDay();
        $cita = $this->citaDeAbogada($ayer, '09:30');
        $cita->update(['pendiente_cierre' => true, 'pendiente_cierre_desde' => now()]);

        $this->actingAs($this->abogadaVallecas);
        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            // El partial parte la etiqueta en dos líneas: la hora y la fecha, en orden
            ->assertSeeInOrder(['Vincular a la cita de las 09:30', 'del '.$ayer->format('d/m/Y')])
            ->set('formAnotacion.contenido', 'Registro posterior de la cita')
            ->call('guardarAnotacion')
            ->assertHasNoErrors();

        $this->assertSame($cita->id, Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Anotacion)->sole()->cita_id);
        $this->assertSame(EstadoCita::Completada, $cita->refresh()->estado);
    }

    /** TF-EAC-15 — No se vincula a una cita cancelada, con incomparecencia o de otro profesional. [negativo] */
    #[Test]
    public function tf_eac_15_citas_no_vinculables(): void
    {
        $cancelada = $this->citaDeAbogada(today(), '10:00', EstadoCita::Cancelada);
        $incomparecencia = $this->citaDeAbogada(today(), '11:00', EstadoCita::NoShowCiudadano);
        $deOtro = $this->cita($this->educadorMulti, $this->ciamVallecas, today(), '12:00');

        foreach ([$cancelada, $incomparecencia, $deOtro] as $cita) {
            try {
                ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas, $cita->id);
                $this->fail("La cita {$cita->id} ({$cita->estado->value}) no debía poder vincularse");
            } catch (DomainException $e) {
                $this->assertStringContainsString('no se puede vincular', $e->getMessage());
            }
        }

        // Desde la pantalla, ?cita= con una cita no vinculable no la propone ni la vincula
        $this->actingAs($this->abogadaVallecas);
        foreach ([$cancelada, $incomparecencia, $deOtro] as $cita) {
            Livewire::withQueryParams(['cita' => $cita->id])
                ->test(EncargoPage::class, ['encargo' => $this->encargo->id])
                ->assertDontSee('Vincular a la cita')
                ->set('formAnotacion.contenido', "Anotación {$cita->id}")
                ->call('guardarAnotacion')
                ->assertHasNoErrors();
        }

        $this->assertSame(3, Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Anotacion)->whereNull('cita_id')->count());
        $this->assertSame(EstadoCita::Cancelada, $cancelada->refresh()->estado);
        $this->assertSame(EstadoCita::NoShowCiudadano, $incomparecencia->refresh()->estado);
        $this->assertSame(EstadoCita::Confirmada, $deOtro->refresh()->estado);
    }

    /** TF-EAC-16 — «Atender» desde la agenda abre el encargo con la cita propuesta en las herramientas. */
    #[Test]
    public function tf_eac_16_atender_abre_el_encargo_con_la_cita(): void
    {
        // Una cita de otro día: sin ?cita= no se propondría (no es de hoy ni está pendiente de cierre)
        $cita = $this->citaDeAbogada(today()->addDays(3), '12:00');

        $url = app(EnlaceAtencionCita::class)->url($cita);
        $this->assertSame(route('intervencion.encargos.show', ['encargo' => $this->encargo->id, 'cita' => $cita->id]), $url);

        $this->actingAs($this->abogadaVallecas);
        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertDontSee('Vincular a la cita');

        Livewire::withQueryParams(['cita' => $cita->id])
            ->test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertSee('Vincular a la cita de las 12:00')
            ->set('formAnotacion.contenido', 'Atendida desde la agenda')
            ->call('guardarAnotacion')
            ->assertHasNoErrors();

        $this->assertSame($cita->id, Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Anotacion)->sole()->cita_id);
        $this->assertSame(EstadoCita::Completada, $cita->refresh()->estado);
    }

    /**
     * Cita de la abogada con `$ana` en el centro del encargo.
     *
     * @param Carbon $fecha
     * @param string $hora
     * @param EstadoCita $estado
     *
     * @return Cita
     */
    private function citaDeAbogada(Carbon $fecha, string $hora, EstadoCita $estado = EstadoCita::Confirmada): Cita
    {
        return $this->cita($this->abogadaVallecas, $this->ciamVallecas, $fecha, $hora, $estado);
    }

    /**
     * Cita de un profesional con `$ana` sobre un hueco reservado suyo.
     *
     * @param User $profesional
     * @param Centro $centro
     * @param Carbon $fecha
     * @param string $hora
     * @param EstadoCita $estado
     *
     * @return Cita
     */
    private function cita(User $profesional, Centro $centro, Carbon $fecha, string $hora, EstadoCita $estado = EstadoCita::Confirmada): Cita
    {
        $fin = Carbon::parse($hora)->addMinutes(45)->format('H:i');
        $slot = SlotFactory::new()->reservado()->create([
            'usuario_id' => $profesional->id,
            'centro_id' => $centro->id,
            'fecha' => $fecha->toDateString(),
            'hora_inicio' => $hora,
            'hora_fin' => $fin,
        ]);

        return CitaFactory::new()->create([
            'slot_id' => $slot->id,
            'tipo_slot_id' => $slot->tipo_slot_id,
            'tipo_cita_id' => $this->tipoCita->id,
            'ciudadano_id' => $this->ana->id,
            'profesional_id' => $profesional->id,
            'centro_id' => $centro->id,
            'fecha' => $fecha->toDateString(),
            'hora_inicio' => $hora,
            'hora_fin' => $fin,
            'estado' => $estado->value,
        ]);
    }
}
