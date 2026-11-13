<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Services\AmbitoUsuario;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Modules\Agenda\Database\Factories\SlotFactory;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\TipoCentro\CentroService;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Services\AperturaHistoriaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo I — Estructura, ámbito y centro de trabajo (TF-TCE-35 a 42, 45 a 47; addenda D1 y D2).
 * TF-TCE-43 y 44 (barra superior y Mi equipo) están con la interfaz.
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class EstructuraAmbitoTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /** Persona no protegida con historia en `$ciamVallecas`. */
    private Ciudadano $persona;

    private HistoriaSocial $historia;

    /**
     * Escenario común y una persona atendida en el CIAM de Vallecas.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();

        $this->persona = Ciudadano::factory()->create(['colectivo_extra_protegido' => false]);
        $this->historia = HistoriaSocial::create(['ciudadano_id' => $this->persona->id, 'unidad_organizativa_id' => $this->uoCiamVallecas->id, 'estado' => 'abierta', 'ciudadano_protegido' => false]);
    }

    /**
     * Si el usuario ve la historia por el scope y a la persona por la policy.
     *
     * @param \App\Models\User $usuario
     *
     * @return array{0: bool, 1: bool}
     */
    private function ve(\App\Models\User $usuario): array
    {
        $this->actingAs($usuario);
        app(AmbitoUsuario::class)->olvidar();

        return [
            HistoriaSocial::whereKey($this->historia->id)->exists(),
            Gate::forUser($usuario)->allows('view', $this->persona),
        ];
    }

    /** TF-TCE-35 — Por la jerarquía funcional, la coordinación de igualdad ve el CIAM. */
    #[Test]
    public function tf_tce_35_ambito_funcional(): void
    {
        $this->assertSame([true, true], $this->ve($this->coordIgualdad));
        $this->assertContains($this->uoCiamVallecas->id, app(AmbitoUsuario::class)->uoIds($this->coordIgualdad));
    }

    /** TF-TCE-36 — Por la orgánica, la junta ve su centro y no el de otra junta. */
    #[Test]
    public function tf_tce_36_ambito_organico_sin_cambios(): void
    {
        $this->assertSame([true, true], $this->ve($this->jefaJuntaVallecas));
        $uos = app(AmbitoUsuario::class)->uoIds($this->jefaJuntaVallecas);
        $this->assertNotContains($this->uoCiamUsera->id, $uos);
    }

    /** TF-TCE-37 — Fuera de ámbito: la historia no aparece por el scope. */
    #[Test]
    public function tf_tce_37_fuera_de_ambito(): void
    {
        [$historia] = $this->ve($this->coordPrimaria);
        $this->assertFalse($historia);
        $this->assertNotContains($this->uoCiamVallecas->id, app(AmbitoUsuario::class)->uoIds($this->coordPrimaria));
    }

    /** TF-TCE-38 — El ámbito sigue a la UO funcional del tipo. */
    #[Test]
    public function tf_tce_38_el_ambito_sigue_a_la_uo_funcional_del_tipo(): void
    {
        $this->tipoCiam->update(['unidad_organizativa_id' => $this->uoPrimaria->id]);

        $this->assertSame([true, true], $this->ve($this->coordPrimaria));
        [$historia] = $this->ve($this->coordIgualdad);
        $this->assertFalse($historia);
    }

    /** TF-TCE-39 — Sin centro no se atiende (servicios y modelos), pero se consulta. */
    #[Test]
    public function tf_tce_39_sin_centro_no_se_atiende(): void
    {
        $mensaje = 'Para atender a ciudadanos tienes que estar adscrito a un centro.';
        $this->actingAs($this->tecnicaArea);
        $centros = app(CentroDeUsuario::class);
        $this->assertTrue($centros->centros($this->tecnicaArea)->isEmpty());
        $this->assertNull($centros->centroActivo($this->tecnicaArea));

        $intentos = [
            fn () => app(AperturaHistoriaService::class)->abrir(Ciudadano::factory()->create()->id, $this->tecnicaArea),
            fn () => app(CitacionService::class)->cancelar(Cita::factory()->create(), PedidoPor::Centro, 'Prueba', $this->tecnicaArea),
            fn () => RegistroAtencion::create(['tipo' => 'informacion', 'profesional_id' => $this->tecnicaArea->id, 'fecha' => today()]),
        ];
        foreach ($intentos as $i => $intento) {
            try {
                $intento();
                $this->fail("El intento {$i} debía rechazarse");
            } catch (DomainException $e) {
                $this->assertSame($mensaje, $e->getMessage());
            }
        }

        // Consulta dentro de su ámbito (funcional: el CIAM depende de Igualdad, hija del Área)
        $this->assertTrue(Gate::forUser($this->tecnicaArea)->allows('view', $this->persona));
    }

    /**
     * Perfiles de la psicóloga: lunes y martes en Vallecas, miércoles y jueves en Usera.
     *
     * @return void
     */
    private function perfilesDeLaPsicologa(): void
    {
        foreach ([[$this->ciamVallecas, ['1', '2']], [$this->ciamUsera, ['3', '4']]] as [$centro, $dias]) {
            PerfilHorarioProfesional::factory()->create([
                'usuario_id' => $this->psicologa->id, 'centro_id' => $centro->id, 'vigente_desde' => '2026-01-01',
                'horario_habitual' => array_fill_keys($dias, [['inicio' => '09:00', 'fin' => '14:00']]),
            ]);
        }
    }

    /** TF-TCE-40 — El centro activo por defecto es el del perfil horario del día. */
    #[Test]
    public function tf_tce_40_centro_activo_por_defecto_del_multicentro(): void
    {
        $this->perfilesDeLaPsicologa();
        $centros = app(CentroDeUsuario::class);

        $this->assertSame($this->ciamVallecas->id, $centros->centroActivo($this->psicologa)?->id);
        Carbon::setTestNow('2026-11-04 10:00:00');
        $this->assertSame($this->ciamUsera->id, $centros->centroActivo($this->psicologa)?->id);
    }

    /** TF-TCE-41 — Se elige uno de sus centros; uno ajeno, no. */
    #[Test]
    public function tf_tce_41_elegir_centro(): void
    {
        $this->perfilesDeLaPsicologa();
        $centros = app(CentroDeUsuario::class);

        $centros->elegir($this->psicologa, $this->ciamUsera);
        $this->assertSame($this->ciamUsera->id, $centros->centroActivo($this->psicologa)?->id);

        $this->expectException(DomainException::class);
        $centros->elegir($this->psicologa, $this->cssNorte);
    }

    /** TF-TCE-42 — El contexto manda: el apunte de una cita de Usera se hace en Usera. */
    #[Test]
    public function tf_tce_42_el_contexto_manda(): void
    {
        $this->perfilesDeLaPsicologa();
        $slot = SlotFactory::new()->reservado()->create(['centro_id' => $this->ciamUsera->id, 'usuario_id' => $this->psicologa->id]);
        $cita = Cita::factory()->create(['slot_id' => $slot->id, 'centro_id' => $this->ciamUsera->id, 'profesional_id' => $this->psicologa->id]);
        $historia = HistoriaSocial::create(['ciudadano_id' => $cita->ciudadano_id, 'unidad_organizativa_id' => $this->uoCiamUsera->id, 'estado' => 'abierta', 'ciudadano_protegido' => false]);

        $this->actingAs($this->psicologa);
        $apunte = Apunte::create([
            'historia_id' => $historia->id, 'autor_id' => $this->psicologa->id, 'fecha' => today()->toDateString(),
            'tipo' => TipoApunte::cases()[0], 'contenido' => 'Atendida', 'visibilidad' => VisibilidadApunte::Profesionales, 'cita_id' => $cita->id,
        ]);

        $this->assertNotNull($apunte->id);
        $this->assertSame($this->ciamVallecas->id, app(CentroDeUsuario::class)->centroActivo($this->psicologa)?->id);
    }

    /** TF-TCE-45 — Una UO, un centro; la UO de un centro no tiene hijas. */
    #[Test]
    public function tf_tce_45_integridad_uo_centro(): void
    {
        try {
            app(CentroService::class)->darDeAlta(['nombre' => 'Otro', 'tipo_gestion' => 'municipal_directo', 'unidad_organizativa_id' => $this->uoCssNorte->id], $this->tipoCss, null, $this->admin);
            $this->fail('Se esperaba DomainException');
        } catch (DomainException) {
            // esperado
        }

        $this->expectException(DomainException::class);
        UnidadOrganizativa::create(['nombre' => 'Equipo', 'tipo' => 'equipo', 'parent_id' => $this->uoCssNorte->id, 'activa' => true]);
    }

    /** TF-TCE-46 — Supervisión funcional y centros dependientes por jerarquía. */
    #[Test]
    public function tf_tce_46_supervision_funcional_y_centros_dependientes(): void
    {
        $centros = app(CentroDeUsuario::class);
        $this->assertTrue($centros->supervisa($this->coordIgualdad, $this->ciamVallecas));
        $this->assertTrue($centros->supervisa($this->coordIgualdad, $this->ciamUsera));
        $this->assertFalse($centros->supervisa($this->coordIgualdad, $this->cssNorte));

        $ids = fn (string $j) => $this->uoArea->centrosDependientes($j)->pluck('id')->sort()->values()->all();
        $todos = collect([$this->cssNorte, $this->cssSur, $this->ciamVallecas, $this->ciamUsera])->pluck('id')->sort()->values()->all();
        $this->assertSame($todos, $ids('funcional'));
        $this->assertSame([], $ids('organica'));
        $this->assertSame($todos, $ids('ambas'));
    }

    /** TF-TCE-47 — El centro no fija lo que es del tipo. */
    #[Test]
    public function tf_tce_47_el_centro_no_fija_lo_que_es_del_tipo(): void
    {
        foreach (['inscripcion_libre' => true, 'modo_asignacion_referencia' => 'sorteo', 'segmentos' => [1]] as $campo => $valor) {
            try {
                app(CentroService::class)->actualizar($this->cssNorte, [$campo => $valor], $this->admin);
                $this->fail("Se esperaba DomainException para {$campo}");
            } catch (DomainException) {
                // esperado
            }
        }

        $this->assertFalse(app(\Modules\Centro\Services\ConfiguracionEfectivaCentro::class)->inscripcionLibre(Centro::find($this->cssNorte->id)));
    }
}
