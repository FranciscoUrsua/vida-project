<?php

namespace Modules\Prestaciones\Tests\Feature\Solicitudes;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Prestaciones\Data\ActualizacionExpediente;
use Modules\Prestaciones\Http\Livewire\BandejaTramitacionPage;
use Modules\Prestaciones\Http\Livewire\PrestacionesPersona;
use Modules\Prestaciones\Http\Livewire\SolicitarPrestacion;
use Modules\Prestaciones\Http\Livewire\SolicitudPrestacionPage;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Tests\Concerns\SolicitudesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo F — Permisos y privacidad (TF-SOL-38 a 43; RN-10 y §5 del diseño).
 *
 * Restricción de dominio crítica (CLAUDE.md §3): tramitación accede a la persona solo a
 * través de las solicitudes de su unidad y nunca ve la Historia Social; los accesos a
 * solicitudes de personas protegidas se auditan como `acceso_restringido`.
 */
class PermisosSolicitudTest extends TestCase
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

    /** TF-SOL-38 — Tramitación no ve la Historia Social, sí la solicitud. [negativo] */
    #[Test]
    public function tf_sol_38_tramitacion_no_ve_la_historia_social(): void
    {
        $solicitud = $this->sadEnBandeja();
        $apunte = Apunte::create([
            'historia_id' => $this->historiaAna->id, 'autor_id' => $this->tsNorte->id, 'fecha' => '2026-10-15',
            'tipo' => TipoApunte::Anotacion, 'contenido' => 'Motivo profesional reservado',
            'visibilidad' => VisibilidadApunte::Profesionales,
        ]);

        $this->assertFalse(Gate::forUser($this->tramitadora1)->allows('view', $this->historiaAna));
        $this->actingAs($this->tramitadora1);
        $this->get(route('intervencion.ciudadano.show', $this->historiaAna))->assertForbidden();
        $this->assertFalse(Apunte::whereKey($apunte->id)->exists());

        Livewire::test(SolicitudPrestacionPage::class, ['solicitud' => $solicitud])
            ->assertOk()
            ->assertSee($this->ana->nombre_completo)
            ->assertSee('Historial')
            ->assertDontSee('Volver a la Historia Social')
            ->assertDontSee('Motivo profesional reservado');
    }

    /** TF-SOL-39 — Bandeja por unidad. */
    #[Test]
    public function tf_sol_39_bandeja_por_unidad(): void
    {
        $sad = $this->sadEnBandeja();
        $interna = $this->servicio()->crear($this->ana, $this->pInterna, $this->tsNorte, null, null, $this->cssNorte);
        $this->servicio()->pasarABandeja($interna, $this->tsNorte);

        $this->actingAs($this->tramitadoraOtra);
        $this->assertCount(0, Livewire::test(BandejaTramitacionPage::class)->get('solicitudes'));
        try {
            $this->servicio()->tomar($sad, $this->tramitadoraOtra);
            $this->fail('No debía poder tomarla');
        } catch (AuthorizationException) {
            $this->assertFalse(Gate::forUser($this->tramitadoraOtra)->allows('view', $sad));
        }

        $this->actingAs($this->tramitadoraCentro);
        $this->assertSame([$interna->id], Livewire::test(BandejaTramitacionPage::class)->get('solicitudes')->pluck('id')->all());
    }

    /** TF-SOL-40 — Persona protegida en tramitación: identidad para tramitar, acceso auditado y sin Historia Social. */
    #[Test]
    public function tf_sol_40_persona_protegida_en_tramitacion(): void
    {
        $solicitud = $this->servicio()->crear($this->marta, $this->pSad, $this->tsNorte, null, null, $this->cssNorte);
        $this->servicio()->pasarABandeja($solicitud, $this->tsNorte);

        Livewire::actingAs($this->tramitadora1)->test(SolicitudPrestacionPage::class, ['solicitud' => $solicitud->fresh()])
            ->assertSee($this->marta->nombre_completo);

        $this->assertTrue(Audit::where('user_id', $this->tramitadora1->id)->where('accion', AccionAuditEnum::AccesoRestringido->value)->where('ciudadano_id', $this->marta->id)->exists());
        $this->assertFalse(Gate::forUser($this->tramitadora1)->allows('view', $this->historiaMarta));
    }

    /** TF-SOL-41 — `consulta_basica` y `tramitacion` no crean solicitudes (acción y servicio). */
    #[Test]
    public function tf_sol_41_quien_crea_solicitudes(): void
    {
        $consulta = $this->usuario('consulta.norte', 'consulta_basica', $this->uoCssNorte);

        foreach ([$consulta, $this->tramitadoraCentro] as $usuario) {
            try {
                $this->servicio()->crear($this->ana, $this->pSad, $usuario, null, null, $this->cssNorte);
                $this->fail("{$usuario->email} no debía crear solicitudes");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }

            Livewire::actingAs($usuario)->test(SolicitarPrestacion::class, ['historiaId' => $this->historiaAna->id])
                ->call('abrir')->call('elegir', $this->pSad->id)->assertHasErrors('prestacion');
        }

        $this->assertSame(0, SolicitudPrestacion::count());
    }

    /** TF-SOL-42 — Sin `solicitud.registrar_manual` no se registra estado ni resolución. */
    #[Test]
    public function tf_sol_42_registro_manual_con_permiso(): void
    {
        $solicitud = $this->sadEnviada();
        $dto = new ActualizacionExpediente(null, 'Resuelto en la otra aplicación', Carbon::parse('2026-11-20'), estadoNormalizado: 'resuelta');

        foreach ([$this->tsNorte, $this->tramitadoraOtra] as $usuario) {
            try {
                $this->servicio()->registrarManual($solicitud, $dto, $usuario);
                $this->fail("{$usuario->email} no debía registrar a mano");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertNull($solicitud->fresh()->estado_externo_normalizado);
    }

    /** TF-SOL-43 — `consulta_basica` no ve el bloque «Prestaciones» ni las solicitudes. */
    #[Test]
    public function tf_sol_43_consulta_basica_no_ve_prestaciones(): void
    {
        $this->solicitudSad();
        $consulta = $this->usuario('consulta.norte', 'consulta_basica', $this->uoCssNorte);

        Livewire::actingAs($consulta)->test(PrestacionesPersona::class, ['ciudadanoId' => $this->ana->id])
            ->assertDontSee('Prestaciones')
            ->assertDontSee('Solicitudes en curso');

        Livewire::actingAs($this->tsNorte)->test(PrestacionesPersona::class, ['ciudadanoId' => $this->ana->id])
            ->assertSee('Prestaciones')
            ->assertSee('Solicitudes en curso');
    }
}
