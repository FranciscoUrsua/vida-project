<?php

namespace Modules\Prestaciones\Tests\Feature\Solicitudes;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Documentos\Models\Documento;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Mensajes\Models\Alerta;
use Modules\Prestaciones\Enums\AccionEventoSolicitud;
use Modules\Prestaciones\Enums\EstadoPrestacionPersona;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Enums\ViaAcceso;
use Modules\Prestaciones\Http\Livewire\BandejaTramitacionPage;
use Modules\Prestaciones\Http\Livewire\SolicitarPrestacion;
use Modules\Prestaciones\Models\PrestacionPersona;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Tests\Concerns\SolicitudesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo B — Ciclo de la solicitud (TF-SOL-04 a 15): una sola puerta, borrador, bandeja,
 * tomar, soltar, devolver, sugerir y anular (RN-01 a RN-05).
 */
class SolicitudCicloTest extends TestCase
{
    use RefreshDatabase;
    use SolicitudesTestSetup;

    /**
     * Escenario de solicitudes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarSolicitudes();
    }

    /** TF-SOL-04 — «Solicitar prestación» sigue el camino de cada vía. */
    #[Test]
    public function tf_sol_04_solicitar_segun_la_via(): void
    {
        $this->derivPia->tiposCentro()->create(['tipo_centro_id' => $this->tipoCss->id]);
        $this->actingAs($this->tsNorte);

        Livewire::test(SolicitarPrestacion::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrir')->call('elegir', $this->pDirecta->id)->assertSet('directaId', $this->pDirecta->id)
            ->call('registrarDirecta')->assertHasNoErrors();
        $directa = PrestacionPersona::where('prestacion_id', $this->pDirecta->id)->sole();
        $this->assertSame([EstadoPrestacionPersona::Activa, $this->ana->id], [$directa->estado, $directa->ciudadano_id]);

        Livewire::test(SolicitarPrestacion::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrir')->call('elegir', $this->pPlaza->id)
            ->assertDispatched('abrir-prescribir-recurso', prestacionId: $this->pPlaza->id);

        Livewire::test(SolicitarPrestacion::class, ['historiaId' => $this->historiaAna->id, 'planId' => $this->pisoAna->id])
            ->call('abrir')->call('elegir', $this->derivPia->id)
            ->assertDispatched('abrir-derivacion');

        $componente = Livewire::test(SolicitarPrestacion::class, ['historiaId' => $this->historiaAna->id])->call('abrir')->call('elegir', $this->pSad->id);
        $solicitud = SolicitudPrestacion::where('prestacion_id', $this->pSad->id)->sole();
        $componente->assertRedirect(route('prestaciones.solicitud', $solicitud));
        $this->assertSame(EstadoSolicitudPrestacion::Borrador, $solicitud->estado);
    }

    /** TF-SOL-05 — Solo las prestaciones del centro del contexto. */
    #[Test]
    public function tf_sol_05_solo_prestaciones_del_centro(): void
    {
        $pCiam = $this->prestacionDe('CIAM-T', 'Ayuda del CIAM', ViaAcceso::TramitacionInterna, ['unidad_tramitadora_tipo' => 'centro_solicitud']);
        $pCiam->tiposCentro()->delete();
        $pCiam->tiposCentro()->create(['tipo_centro_id' => $this->tipoCiam->id]);

        $this->actingAs($this->tsNorte);
        $ofrecidas = Livewire::test(SolicitarPrestacion::class, ['historiaId' => $this->historiaAna->id])->get('prestaciones')->pluck('id')->all();
        $this->assertContains($this->pSad->id, $ofrecidas);
        $this->assertNotContains($pCiam->id, $ofrecidas);

        $this->expectException(DomainException::class);
        $this->servicio()->crear($this->ana, $pCiam, $this->tsNorte, null, null, $this->cssNorte);
    }

    /** TF-SOL-06 — Borrador con datos y documento de la carpeta, y a la bandeja de su unidad. */
    #[Test]
    public function tf_sol_06_borrador_y_bandeja(): void
    {
        $solicitud = $this->solicitudSad();
        $dni = $this->documentoEnCarpeta($this->ana, $this->tipoIdentidad);

        $this->servicio()->guardarDatos($solicitud, ['horas' => 10, 'observaciones' => 'Mañanas'], $this->tsNorte);
        $this->servicio()->vincularDocumento($solicitud, $dni, $this->tsNorte);
        $this->servicio()->pasarABandeja($solicitud->fresh(), $this->tsNorte);

        $solicitud->refresh();
        $this->assertSame(EstadoSolicitudPrestacion::EnBandeja, $solicitud->estado);
        $this->assertSame($this->uoTramitacionSad->id, $solicitud->unidad_tramitadora_id);
        $this->assertSame(['horas' => 10, 'observaciones' => 'Mañanas'], $solicitud->ficha->datos);
        $this->assertSame([$dni->id], $solicitud->vinculosDocumentos()->pluck('documento_id')->all());
        $this->assertSame(AccionEventoSolicitud::ABandeja, $solicitud->eventos()->get()->last()->accion);
        $this->assertSame([], $this->servicio()->documentosObligatoriosPendientes($solicitud));
    }

    /** TF-SOL-07 — Unidad «centro de solicitud». */
    #[Test]
    public function tf_sol_07_unidad_centro_de_solicitud(): void
    {
        $solicitud = $this->servicio()->crear($this->ana, $this->pInterna, $this->tsNorte, null, null, $this->cssNorte);
        $this->servicio()->pasarABandeja($solicitud, $this->tsNorte);

        $this->assertSame($this->uoCssNorte->id, $solicitud->fresh()->unidad_tramitadora_id);
    }

    /** TF-SOL-08 — Desde un objetivo: guarda plan y objetivo, y el objetivo la muestra. */
    #[Test]
    public function tf_sol_08_desde_un_objetivo(): void
    {
        $solicitud = $this->servicio()->crear($this->ana, $this->pSad, $this->tsNorte, $this->pisoAna, $this->objetivoAna1, $this->cssNorte);
        $this->assertSame([$this->pisoAna->id, $this->objetivoAna1->id], [$solicitud->plan_id, $solicitud->plan_objetivo_id]);

        $this->actingAs($this->tsNorte);
        $vinculos = Livewire::test(PlanPage::class, ['plan' => $this->pisoAna])->assertSee('Solicitud: Servicio de Ayuda a Domicilio')->get('prestacionesPorObjetivo');
        $this->assertCount(1, $vinculos->get($this->objetivoAna1->id));
    }

    /** TF-SOL-09 — Personas afectadas: solo miembros activos de la unidad de convivencia. */
    #[Test]
    public function tf_sol_09_personas_afectadas(): void
    {
        [$luis] = $this->personaEnCentro($this->cssNorte);
        [$ajena] = $this->personaEnCentro($this->cssNorte);
        $uc = UnidadConvivencia::create(['domicilio' => 'Calle Prueba 1', 'fecha_constitucion' => '2026-01-01']);
        $uc->agregarMiembro($this->ana->id);
        $uc->agregarMiembro($luis->id);

        $solicitud = $this->solicitudSad();
        $this->servicio()->anadirPersona($solicitud, $luis, $this->tsNorte);
        $this->assertSame([$luis->id], $solicitud->personasAfectadas()->pluck('ciudadanos.id')->all());

        $this->expectException(DomainException::class);
        $this->servicio()->anadirPersona($solicitud, $ajena, $this->tsNorte);
    }

    /** TF-SOL-10 — Un documento subido para la solicitud queda en la carpeta de la persona. */
    #[Test]
    public function tf_sol_10_documento_subido_para_la_solicitud(): void
    {
        $solicitud = $this->solicitudSad();
        $fichero = UploadedFile::fake()->createWithContent('informe.pdf', (string) file_get_contents(base_path('Modules/Documentos/tests/fixtures/valido.pdf')));

        $documento = $this->servicio()->subirDocumento($solicitud, $fichero, $this->tipoInformeMedico, $this->tsNorte);

        $this->assertContains($documento->id, Documento::vinculadosA($this->ana)->pluck('id')->all());
        $this->assertSame([$documento->id], $solicitud->vinculosDocumentos()->pluck('documento_id')->all());
    }

    /** TF-SOL-11 — Tomar y soltar. */
    #[Test]
    public function tf_sol_11_tomar_y_soltar(): void
    {
        $solicitud = $this->sadEnPreparacion();
        $this->assertSame([EstadoSolicitudPrestacion::EnPreparacion, $this->tramitadora1->id], [$solicitud->estado, $solicitud->responsable_id]);

        $this->servicio()->soltar($solicitud, $this->tramitadora1, 'Me voy de vacaciones');

        $solicitud->refresh();
        $this->assertSame([EstadoSolicitudPrestacion::EnBandeja, null], [$solicitud->estado, $solicitud->responsable_id]);
        $acciones = $solicitud->eventos()->pluck('accion')->map(fn ($a) => $a->value)->all();
        $this->assertSame(['creada', 'a_bandeja', 'tomada', 'soltada'], $acciones);
        $this->assertSame('Me voy de vacaciones', $solicitud->eventos()->get()->last()->nota);
    }

    /** TF-SOL-12 — No se toma ni se envía una ya tomada por otra. [negativo] */
    #[Test]
    public function tf_sol_12_no_se_toma_una_ya_tomada(): void
    {
        $solicitud = $this->sadEnPreparacion();

        foreach ([fn () => $this->servicio()->tomar($solicitud, $this->tramitadora2), fn () => $this->servicio()->enviar($solicitud, $this->tramitadora2, 'x')] as $intento) {
            try {
                $intento();
                $this->fail('Debía rechazarse');
            } catch (DomainException|AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame($this->tramitadora1->id, $solicitud->fresh()->responsable_id);
    }

    /** TF-SOL-13 — Devolver al TS con nota (y aviso); sin nota, no. */
    #[Test]
    public function tf_sol_13_devolver_al_ts(): void
    {
        $solicitud = $this->sadEnPreparacion();

        try {
            $this->servicio()->devolver($solicitud, $this->tramitadora1, ' ');
            $this->fail('Sin nota debía rechazarse');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->servicio()->devolver($solicitud, $this->tramitadora1, 'Faltan las horas solicitadas');

        $this->assertSame(EstadoSolicitudPrestacion::Borrador, $solicitud->fresh()->estado);
        $alerta = Alerta::pendientesPara($this->tsNorte)->where('origen_type', SolicitudPrestacion::class)->where('origen_id', $solicitud->id)->sole();
        $this->assertStringContainsString('Faltan las horas solicitadas', $alerta->cuerpo);
    }

    /** TF-SOL-14 — La sugerencia no es asignación: sigue en la bandeja de las dos. */
    #[Test]
    public function tf_sol_14_sugerencia_no_es_asignacion(): void
    {
        $solicitud = $this->solicitudSad();
        $this->servicio()->sugerir($solicitud, $this->tramitadora2, $this->tsNorte);
        $this->servicio()->pasarABandeja($solicitud->fresh(), $this->tsNorte);

        foreach ([$this->tramitadora1, $this->tramitadora2] as $tramitadora) {
            $this->actingAs($tramitadora);
            $bandeja = Livewire::test(BandejaTramitacionPage::class)->assertSee('Sugerida: '.$this->tramitadora2->nombreVisible());
            $this->assertSame([$solicitud->id], $bandeja->get('solicitudes')->pluck('id')->all());
        }
        $this->assertNull($solicitud->fresh()->responsable_id);
    }

    /** TF-SOL-ORD-01 — La bandeja abre con el movimiento más antiguo primero y se ordena por persona. */
    #[Test]
    public function bandeja_de_tramitacion_ordenable(): void
    {
        $this->travelTo(now()->subDays(2));
        $deMarta = $this->servicio()->crear($this->marta, $this->pSad, $this->tsNorte, null, null, $this->cssNorte);
        $this->servicio()->pasarABandeja($deMarta, $this->tsNorte);
        $this->travelBack();
        $deAna = $this->sadEnBandeja();

        $this->actingAs($this->tramitadora1);
        $bandeja = Livewire::test(BandejaTramitacionPage::class);
        $ids = fn () => $bandeja->get('solicitudes')->pluck('id')->all();

        $this->assertSame([$deMarta->id, $deAna->id], $ids(), 'Por defecto, lo que más espera primero');

        $bandeja->call('ordenar', 'movimiento');
        $this->assertSame([$deAna->id, $deMarta->id], $ids());

        $porNombre = collect([$deAna->id => $this->ana->nombre_completo, $deMarta->id => $this->marta->nombre_completo])
            ->sortBy(fn (string $n) => Str::lower(Str::ascii($n)))->keys()->all();
        $bandeja->call('ordenar', 'persona');
        $this->assertSame($porNombre, $ids());
        $bandeja->call('ordenar', 'persona');
        $this->assertSame(array_reverse($porNombre), $ids());

        $bandeja->call('ordenar', 'nada')->assertSet('ordenarPor', 'persona');
    }

    /** TF-SOL-15 — Anular antes del envío; enviada, no. */
    #[Test]
    public function tf_sol_15_anular_antes_del_envio(): void
    {
        $borrador = $this->solicitudSad();
        $this->servicio()->anular($borrador, $this->tsNorte, 'La persona renuncia');
        $this->assertSame([EstadoSolicitudPrestacion::Anulada, 'La persona renuncia'], [$borrador->fresh()->estado, $borrador->fresh()->motivo_cierre]);

        $enviada = $this->servicio()->crear($this->ana, $this->pSad, $this->tsNorte, null, null, $this->cssNorte);
        $this->servicio()->pasarABandeja($enviada, $this->tsNorte);
        $this->servicio()->tomar($enviada->fresh(), $this->tramitadora1);
        $this->servicio()->enviar($enviada->fresh(), $this->tramitadora1, 'Sin documentos');

        $this->expectException(DomainException::class);
        $this->servicio()->anular($enviada->fresh(), $this->tsNorte, 'Ya no');
    }
}
