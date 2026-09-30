<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CitaAcompanante;
use Modules\Agenda\Models\CitaEvento;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo F: atención y cierre (TF-CIT-29 a 33, 35 y 36).
 * TF-CIT-34 (propuesta de vinculación en la ficha) está en los tests de interfaz.
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md
 */
class AtencionCitaTest extends TestCase
{
    use CitasTestSetup;
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioCitas();
    }

    /**
     * @return AtencionCitaService
     */
    private function atencion(): AtencionCitaService
    {
        return app(AtencionCitaService::class);
    }

    /**
     * Apunte de María del TSR, vinculado a una cita.
     *
     * @param Cita $cita
     * @param TipoApunte $tipo
     * @return Apunte
     */
    private function apunte(Cita $cita, TipoApunte $tipo = TipoApunte::Entrevista): Apunte
    {
        return Apunte::create([
            'historia_id' => $this->historiaMaria->id,
            'autor_id' => $this->tsr->id,
            'fecha' => today()->toDateString(),
            'tipo' => $tipo,
            'contenido' => 'Entrevista de seguimiento',
            'visibilidad' => VisibilidadApunte::Profesionales,
            'cita_id' => $cita->id,
        ]);
    }

    /**
     * TF-CIT-29 — El apunte completa la cita.
     *
     * @return void
     */
    #[Test]
    public function el_apunte_completa_la_cita(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '11:00');

        $apunte = $this->apunte($cita);

        $this->assertSame($cita->id, $apunte->cita_id);
        $this->assertSame(EstadoCita::Completada, $cita->fresh()->estado);
        $this->assertNotNull($cita->fresh()->completada_en);
        $this->assertSame(1, CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::CitaCompletada)->count());
    }

    /**
     * TF-CIT-30 — Un segundo apunte se vincula sin duplicar el cierre.
     *
     * @return void
     */
    #[Test]
    public function un_segundo_apunte_no_duplica_el_cierre(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '11:00');
        $this->apunte($cita);
        $completadaEn = $cita->fresh()->completada_en;

        $this->apunte($cita, TipoApunte::Valoracion);

        $this->assertSame(2, $cita->apuntes()->count());
        $this->assertEquals($completadaEn, $cita->fresh()->completada_en);
        $this->assertSame(1, CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::CitaCompletada)->count());
    }

    /**
     * TF-CIT-31 — No se vincula un apunte a una incomparecencia; supervisión sí, con motivo. [negativo]
     *
     * @return void
     */
    #[Test]
    public function no_se_vincula_un_apunte_a_una_incomparecencia(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '11:00');
        $this->atencion()->registrarNoShow($cita, $this->tsr);

        try {
            $this->apunte($cita);
            $this->fail('No se vincula a una incomparecencia');
        } catch (LogicException) {
        }
        $this->assertSame(0, Apunte::where('cita_id', $cita->id)->count());

        $this->atencion()->vincularConCorreccion($cita, $this->supervisor, 'Se marcó por error: sí vino', fn () => $this->apunte($cita));

        $this->assertSame(1, Apunte::where('cita_id', $cita->id)->count());
        $this->assertSame(EstadoCita::Completada, $cita->fresh()->estado);
        $evento = CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::CitaCompletada)->sole();
        $this->assertSame($this->supervisor->id, $evento->actor_id);
        $this->assertSame('Se marcó por error: sí vino', $evento->motivo);
    }

    /**
     * TF-CIT-32 — La cita debe ser de la misma persona.
     *
     * @return void
     */
    #[Test]
    public function la_cita_debe_ser_de_la_misma_persona(): void
    {
        $citaJuan = $this->citaConfirmada($this->juan, $this->tsr, '2026-10-06', '11:00', $this->tipoInformacion);

        $this->expectException(LogicException::class);
        $this->apunte($citaJuan);
    }

    /**
     * TF-CIT-33 — El registro de atención completa la cita de quien no tiene Historia Social.
     *
     * @return void
     */
    #[Test]
    public function el_registro_de_atencion_completa_la_cita(): void
    {
        $cita = $this->citaConfirmada($this->juan, $this->tsr, '2026-10-06', '11:00', $this->tipoInformacion);

        RegistroAtencion::create([
            'ciudadano_id' => $this->juan->id,
            'tipo' => 'informacion',
            'fecha' => today()->toDateString(),
            'profesional_id' => $this->tsr->id,
            'demanda' => 'Información sobre ayudas',
            'origen' => 'manual',
            'cita_id' => $cita->id,
        ]);

        $this->assertSame(EstadoCita::Completada, $cita->fresh()->estado);
    }

    /**
     * TF-CIT-35 — Incomparecencia: estado y evento. (El timeline, en Intervención.)
     *
     * @return void
     */
    #[Test]
    public function incomparecencia(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '11:00');

        $this->atencion()->registrarNoShow($cita, $this->tsr);

        $this->assertSame(EstadoCita::NoShowCiudadano, $cita->fresh()->estado);
        $this->assertSame(1, CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::Incomparecencia)->count());

        // Otro profesional no la marca
        $otra = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '12:00');
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->atencion()->registrarNoShow($otra, $this->tsr2);
    }

    /**
     * TF-CIT-36 — Acompañantes: un familiar sin ficha (cifrado) y Juan como
     * miembro de la unidad; sin citas para Juan.
     *
     * @return void
     */
    #[Test]
    public function acompanantes(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '11:00');
        $this->apunte($cita);
        $citasJuan = Cita::where('ciudadano_id', $this->juan->id)->count();

        $this->atencion()->registrarAcompanantes($cita, [
            ['relacion' => 'familiar', 'nombre' => 'Rosa Fernández'],
            ['relacion' => 'unidad_convivencia', 'ciudadano_id' => $this->juan->id],
        ], $this->tsr);

        $this->assertSame(2, CitaAcompanante::where('cita_id', $cita->id)->count());
        $crudo = DB::table('cita_acompanantes')->whereNotNull('nombre')->value('nombre');
        $this->assertStringNotContainsString('Rosa', $crudo);
        $this->assertSame('Rosa Fernández', CitaAcompanante::whereNotNull('nombre')->sole()->nombre);
        $this->assertSame(1, CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::AcompanantesRegistrados)->count());
        $this->assertSame($citasJuan, Cita::where('ciudadano_id', $this->juan->id)->count());
    }
}
