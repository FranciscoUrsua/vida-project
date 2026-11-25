<?php

namespace Modules\Intervencion\Tests\Feature\Asignacion;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Enums\ModoAsignacionReferenciaCentro;
use Modules\Centro\Models\Centro;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Ciudadania\Http\Livewire\AltaCiudadano;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Services\AperturaHistoriaService;
use Modules\Intervencion\Services\Asignacion\PoolReferenciaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Solo un profesional con rol intervención abre Historias Sociales y es profesional de
 * referencia (TF-REF-01 a 06). Tramitación tampoco cierra historias; sí consulta su
 * estado (TF-REF-07).
 *
 * En staging, un usuario de `tramitacion` abrió una historia desde la ficha de la
 * persona y quedó como referencia (25 nov 2026): la policy solo miraba el permiso, que
 * el rol tenía por error. La regla vale en todas las vías: apertura (ficha y alta) y
 * cualquier asignación de referencia.
 */
class ReferenciaSoloIntervencionTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    private Centro $cssQuienAbre;

    private User $ts;

    private User $tramitador;

    /**
     * Escenario de asignación y un centro «quien abre» con una trabajadora social
     * (intervención) y un tramitador, los dos adscritos a él.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioAsignacion();

        $this->cssQuienAbre = $this->crearCentro('CSS Quien Abre', 'css_quien_abre', modo: ModoAsignacionReferenciaCentro::QuienAbre);
        $this->ts = $this->crearProfesionalEnCentro('ts.qa', $this->cssQuienAbre, $this->cargoTs, 35);
        $this->tramitador = $this->crearUsuario('tramitador.qa', $this->cssQuienAbre, 'tramitacion');
    }

    /**
     * Historia social de la persona, si tiene.
     *
     * @param int $ciudadanoId
     *
     * @return HistoriaSocial|null
     */
    private function historiaDeCiudadano(int $ciudadanoId): ?HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $ciudadanoId)->first();
    }

    #[Test]
    public function tf_ref_01_solo_intervencion_puede_abrir_una_historia(): void
    {
        $admSistema = $this->crearUsuario('adm.qa', $this->cssQuienAbre, 'adm_sistema');

        $this->assertTrue(Gate::forUser($this->ts)->allows('create', HistoriaSocial::class));
        $this->assertFalse(Gate::forUser($this->tramitador)->allows('create', HistoriaSocial::class));
        // Aunque tenga el permiso, sin rol intervención no
        $this->assertTrue($admSistema->can('historia.crear'));
        $this->assertFalse(Gate::forUser($admSistema)->allows('create', HistoriaSocial::class));

        $this->expectException(AuthorizationException::class);
        try {
            app(AperturaHistoriaService::class)->abrir($this->pedro->id, $this->tramitador);
        } finally {
            $this->assertNull($this->historiaDeCiudadano($this->pedro->id));
        }
    }

    #[Test]
    public function tf_ref_02_tramitacion_no_abre_la_historia_desde_la_ficha(): void
    {
        Livewire::actingAs($this->tramitador)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->pedro->id])
            ->assertSet('puedeAbrirHistoria', false)
            ->call('abrirHistoriaSocial')
            ->assertForbidden();

        $this->assertNull($this->historiaDeCiudadano($this->pedro->id));
    }

    #[Test]
    public function tf_ref_03_tramitacion_no_abre_la_historia_desde_el_alta(): void
    {
        $alta = Livewire::actingAs($this->tramitador)
            ->test(AltaCiudadano::class)
            ->set('fase', 'formulario')
            ->set('nombre', 'Julia')
            ->set('apellido1', 'Prueba')
            ->set('sexo', 'F')
            ->call('guardar')
            ->assertSet('puedeAbrirHistoria', false)
            ->set('abrirHistoria', true)
            ->call('confirmarAlta');

        $this->assertNull($this->historiaDeCiudadano((int) $alta->get('ciudadanoIdCreado')));
    }

    #[Test]
    public function tf_ref_04_la_trabajadora_social_abre_la_historia_y_queda_como_referencia(): void
    {
        Livewire::actingAs($this->ts)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->pedro->id])
            ->assertSet('puedeAbrirHistoria', true)
            ->call('abrirHistoriaSocial');

        $historia = $this->historiaDeCiudadano($this->pedro->id);
        $this->assertNotNull($historia);

        $referencia = AsignacionProfesional::vigente()->where('historia_id', $historia->id)->sole();
        $this->assertSame($this->ts->id, $referencia->profesional_id);
        $this->assertSame(OrigenAsignacionReferencia::QuienAbre, $referencia->origen);
    }

    #[Test]
    public function tf_ref_05_sin_rol_intervencion_no_entra_en_el_reparto_aunque_su_cargo_lo_permita(): void
    {
        // Cargo de trabajador social y perfil horario en el centro, pero rol tramitación
        $sinIntervencion = $this->crearProfesionalEnCentro('ts.sin.rol', $this->cssNorte, $this->cargoTs, 35);
        $sinIntervencion->syncRoles(['tramitacion']);
        $this->assertTrue(PerfilHorarioProfesional::where('usuario_id', $sinIntervencion->id)->exists());

        $pool = app(PoolReferenciaService::class);

        $this->assertNotContains($sinIntervencion->id, $pool->elegibles($this->cssNorte, today())->pluck('usuario.id')->all());
        $this->assertNotContains($sinIntervencion->id, $pool->profesionalesReferencia($this->cssNorte, today())->pluck('id')->all());
        $this->assertContains($this->ts1->id, $pool->profesionalesReferencia($this->cssNorte, today())->pluck('id')->all());
    }

    #[Test]
    public function tf_ref_06_ninguna_via_crea_una_referencia_sin_rol_intervencion(): void
    {
        $historia = $this->historiaDe($this->pedro, $this->cssQuienAbre);

        try {
            AsignacionProfesional::create([
                'historia_id' => $historia->id,
                'profesional_id' => $this->tramitador->id,
                'centro_id' => $this->cssQuienAbre->id,
                'origen' => OrigenAsignacionReferencia::Manual,
                'fecha_inicio' => today(),
            ]);
            $this->fail('Se ha creado una referencia de un usuario sin rol intervención.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('intervención', $e->getMessage());
        }

        $this->assertFalse(AsignacionProfesional::where('historia_id', $historia->id)->exists());

        // Con rol intervención, sí
        AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $this->ts->id,
            'centro_id' => $this->cssQuienAbre->id,
            'origen' => OrigenAsignacionReferencia::Manual,
            'fecha_inicio' => today(),
        ]);
        $this->assertTrue(AsignacionProfesional::where('historia_id', $historia->id)->exists());
    }

    #[Test]
    public function tf_ref_07_tramitacion_consulta_el_estado_de_la_historia_pero_no_la_cierra(): void
    {
        $this->assertTrue($this->tramitador->can('historia.ver_estado'));
        $this->assertFalse($this->tramitador->can('historia.cerrar'));

        // Quien la cierra es el trabajador social
        $this->assertTrue($this->ts->can('historia.cerrar'));
    }
}
