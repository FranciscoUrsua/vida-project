<?php

namespace Modules\Intervencion\Tests\Feature\Usabilidad;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Tests\Concerns\ActividadesTestSetup;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\DetalleAccesoDrawer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Auditoría con resumen legible y drawer de detalle (U3, U3.1 a U3.3; TF-USA-10 a 14).
 *
 * Datos: Ana con una prescripción al «Taller de autonomía»; `tsNorte` es responsable de su PISO
 * y ve todos los accesos; `tsNorte2` (intervención en el mismo centro, sin planes de Ana) solo
 * ve los suyos (`AccesosExpedienteQuery::paraUsuario`).
 *
 * @see docs/instrucciones-cli/2026-11-usabilidad-historia-social.md, paso 3
 */
class AuditoriaAccesosTest extends TestCase
{
    use ActividadesTestSetup;
    use RefreshDatabase;

    private Prescripcion $prescripcion;

    private string $estadoInicial;

    private const MOTIVO = 'La persona prefiere el grupo de la tarde.';

    /**
     * Prescripción de Ana y su cancelación por el TS de referencia desde la Historia Social.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarActividades();

        $this->actingAs($this->tsNorte);
        $this->prescripcion = $this->prescribir($this->sesion23, $this->ana);
        $this->estadoInicial = $this->prescripcion->fresh()->estado;

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->call('verPrescripcion', $this->prescripcion->id)
            ->call('iniciarCancelacionPrescripcion')
            ->set('motivoCancelacion', self::MOTIVO)
            ->call('cancelarPrescripcion');
    }

    /**
     * Registro de auditoría de la cancelación.
     *
     * @return Audit
     */
    private function auditCancelacion(): Audit
    {
        return Audit::where('auditable_type', Prescripcion::class)
            ->where('auditable_id', $this->prescripcion->id)
            ->where('accion', AccionAuditEnum::Editar->value)
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * TF-USA-10 — La cancelación queda auditada con resumen y motivo.
     *
     * @return void
     */
    #[Test]
    public function la_cancelacion_se_audita_con_resumen_y_motivo(): void
    {
        $audit = $this->auditCancelacion();

        $this->assertSame($this->ana->id, $audit->ciudadano_id);
        $this->assertSame($this->tsNorte->id, $audit->user_id);
        $this->assertSame('Canceló la prescripción de “Taller de autonomía”', $audit->contexto['resumen']);
        $this->assertSame('cancelada', $audit->datos_despues['estado']);
        $this->assertSame(self::MOTIVO, $audit->datos_despues['motivo_cancelacion']);
        $this->assertStringNotContainsString(self::MOTIVO, $audit->contexto['resumen']);
    }

    /**
     * TF-USA-11 — «Últimos accesos» muestra el resumen de la cancelación.
     *
     * @return void
     */
    #[Test]
    public function el_widget_muestra_el_resumen(): void
    {
        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertSee('Canceló la prescripción de “Taller de autonomía”');
    }

    /**
     * TF-USA-12 — El drawer enseña quién, cuándo, la acción, el registro y el cambio de estado
     * con etiquetas en español.
     *
     * @return void
     */
    #[Test]
    public function el_drawer_muestra_el_detalle_con_etiquetas_en_espanol(): void
    {
        $audit = $this->auditCancelacion();

        Livewire::test(DetalleAccesoDrawer::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrirDetalle', $audit->id)
            ->assertSee($this->tsNorte->nombreVisible())
            ->assertSee($audit->created_at->format('d/m/Y H:i:s'))
            ->assertSee('Canceló la prescripción de “Taller de autonomía”')
            ->assertSee('Una prescripción')
            ->assertSee('Estado')
            ->assertSee(Prescripcion::ETIQUETAS_ESTADO[$this->estadoInicial])
            ->assertSee('Cancelada')
            ->assertSee('Motivo de la cancelación')
            ->assertSee(self::MOTIVO)
            ->assertDontSee('motivo_cancelacion');
    }

    /**
     * TF-USA-13 [negativo] — Quien solo ve sus accesos no abre el detalle de otro profesional,
     * ni pasando el id directamente.
     *
     * @return void
     */
    #[Test]
    public function quien_solo_ve_sus_accesos_no_abre_los_de_otro(): void
    {
        $this->actingAs($this->tsNorte2);

        Livewire::test(DetalleAccesoDrawer::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrirDetalle', $this->auditCancelacion()->id)
            ->assertForbidden();

        // Sus propios accesos sí
        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna]);
        $propio = Audit::where('user_id', $this->tsNorte2->id)->latest('id')->firstOrFail();

        Livewire::test(DetalleAccesoDrawer::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrirDetalle', $propio->id)
            ->assertSee('Consultó el expediente');
    }

    /**
     * TF-USA-14 — «Ver todo» abre la lista paginada; con «Solo modificaciones» no hay consultas.
     *
     * @return void
     */
    #[Test]
    public function ver_todo_lista_los_accesos_y_filtra_las_modificaciones(): void
    {
        // Consultas del expediente (lecturas)
        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna]);

        $drawer = Livewire::test(DetalleAccesoDrawer::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrirLista')
            ->assertSee('Historial de accesos')
            ->assertSee('Consultó el expediente')
            ->assertSee('Canceló la prescripción de “Taller de autonomía”')
            ->set('soloModificaciones', true)
            ->assertDontSee('Consultó el expediente')
            ->assertSee('Canceló la prescripción de “Taller de autonomía”');

        $this->assertSame(20, $drawer->get('lista')->perPage());

        // Una fila abre su detalle con «Volver a la lista»
        $drawer->call('abrirDetalle', $this->auditCancelacion()->id)
            ->assertSee('Volver a la lista')
            ->call('volver')
            ->assertSet('modo', 'lista');
    }
}
