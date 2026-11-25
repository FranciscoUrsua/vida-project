<?php

namespace Modules\Intervencion\Tests\Feature\Encargo;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use App\Models\Ciudadano;
use App\Support\Menu\RegistroMenu;
use App\Support\Superficie\DestinoInicial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use Modules\Intervencion\Http\Livewire\EncargoPage;
use Modules\Intervencion\Http\Livewire\MisEncargosPage;
use Modules\Intervencion\Services\EncargoService;
use Modules\Intervencion\Tests\Concerns\EncargoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Grupo C — Superficie, ámbito y permisos del encargo profesional (TF-ENC-19 a 29,
 * docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * El profesional de encargo (`consulta_profesional`) solo ve a las personas de sus encargos
 * aceptados o cerrados, en lectura y sin Historia Social (EP-6). Los negativos fallan si se
 * quita la restricción que protegen.
 */
class SuperficieEncargoTest extends TestCase
{
    use EncargoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del encargo profesional.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEncargo();
    }

    /** TF-ENC-19 — Permisos del rol: los del bloque C1 y ninguno de los retirados. */
    #[Test]
    public function tf_enc_19_permisos_del_rol(): void
    {
        $permisos = Role::findByName('consulta_profesional')->permissions->pluck('name')->all();

        foreach (['ciudadano.ver_ficha', 'ciudadano.leer_por_encargo', 'derivacion.ver_asignadas', 'derivacion.registrar_resultado', 'documento.subir'] as $permiso) {
            $this->assertContains($permiso, $permisos);
        }
        foreach (['ciudadano.leer', 'historia.leer', 'apunte.leer', 'apunte.crear', 'plan.leer', 'documento.ver_carpeta', 'ciudadano.buscar'] as $permiso) {
            $this->assertNotContains($permiso, $permisos);
        }
    }

    /** TF-ENC-19 bis — La migración de datos deja el rol igual que el seeder. */
    #[Test]
    public function tf_enc_19_bis_la_migracion_deja_el_rol_como_el_seeder(): void
    {
        $migracion = require base_path('database/migrations/2026_11_25_200002_permisos_encargo_profesional.php');
        $rol = Role::findByName('consulta_profesional');

        $migracion->down();
        $this->assertContains('ciudadano.leer', $rol->fresh()->permissions->pluck('name')->all());

        $migracion->up();
        $this->assertEqualsCanonicalizing(
            ['ciudadano.ver_ficha', 'ciudadano.leer_por_encargo', 'derivacion.ver_asignadas', 'derivacion.registrar_resultado', 'documento.subir'],
            $rol->fresh()->permissions->pluck('name')->all(),
        );
    }

    /** TF-ENC-20 — Página de inicio: «Mis derivaciones»; con `intervencion` además, Agenda. */
    #[Test]
    public function tf_enc_20_pagina_de_inicio(): void
    {
        $this->assertSame(route('intervencion.encargos.index'), DestinoInicial::para($this->abogadaVallecas));

        $this->abogadaVallecas->assignRole('intervencion');
        $this->assertSame(route('intervencion.agenda.index'), DestinoInicial::para($this->abogadaVallecas->fresh()));
    }

    /** TF-ENC-20 bis — Tras el login no va a la página de usuario sin perfil de trabajo. [negativo] */
    #[Test]
    public function tf_enc_20_bis_no_va_a_sin_rol(): void
    {
        $this->actingAs($this->abogadaVallecas)->get('/')
            ->assertRedirect(route('intervencion.encargos.index'));
    }

    /** TF-ENC-21 — Menú: Mis derivaciones y Buscador; sin Mis casos, Alta ni Supervisión. */
    #[Test]
    public function tf_enc_21_menu(): void
    {
        $secciones = RegistroMenu::para($this->abogadaVallecas);
        $claves = collect($secciones)->flatten()->map(fn ($entrada) => $entrada->clave)->all();

        $this->assertContains('encargos', $claves);
        $this->assertContains('buscador', $claves);
        $this->assertNotContains('casos', $claves);
        $this->assertNotContains('alta', $claves);
        $this->assertArrayNotHasKey('supervision', $secciones);

        $this->actingAs($this->abogadaVallecas)->get(route('intervencion.encargos.index'))
            ->assertOk()->assertSee('Mis derivaciones');
    }

    /** TF-ENC-22 — Mis derivaciones: activos e histórico propios; no los de otro profesional. */
    #[Test]
    public function tf_enc_22_mis_derivaciones(): void
    {
        $activo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        [$luis, $historiaLuis] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);
        $cerrado = $this->encargoCerrado($historiaLuis, $this->abogadaVallecas);
        [$eva, $historiaEva] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);
        $ajeno = $this->encargoAceptado($historiaEva, $this->educadorMulti);

        $this->actingAs($this->abogadaVallecas);
        $pagina = Livewire::test(MisEncargosPage::class);

        $this->assertSame([$activo->id], $pagina->get('activos')->pluck('encargo.id')->all());
        $this->assertSame([$cerrado->id], $pagina->get('historico')->pluck('encargo.id')->all());
        $pagina->assertSee($this->ana->nombre_completo)
            ->assertSee($luis->nombre_completo)
            ->assertDontSee($eva->nombre_completo);
        $this->assertNotContains($ajeno->id, $pagina->get('activos')->pluck('encargo.id')->all());
    }

    /** TF-ENC-23 — Ficha: la de las personas de sus encargos; otra persona → 403 auditado. [negativo] */
    #[Test]
    public function tf_enc_23_ficha_solo_de_sus_personas(): void
    {
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        [$otra] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);

        $this->actingAs($this->abogadaVallecas)->get(route('ciudadania.ciudadano.ficha', $this->ana->id))->assertOk();
        $this->get(route('ciudadania.ciudadano.ficha', $otra->id))->assertForbidden();

        $fila = Audit::where('ciudadano_id', $otra->id)->where('accion', AccionAuditEnum::Ver->value)->sole();
        $this->assertFalse($fila->contexto['autorizado']);

        // Cerrado el encargo, la persona (no protegida) sigue en su histórico
        app(EncargoService::class)->cerrar($encargo, 'Hecho.', $this->abogadaVallecas);
        $this->get(route('ciudadania.ciudadano.ficha', $this->ana->id))->assertOk();
    }

    /** TF-ENC-24 — Ficha en lectura: sin edición, Historia Social ni prestaciones; `update` denegado. [negativo] */
    #[Test]
    public function tf_enc_24_ficha_en_lectura(): void
    {
        $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $this->actingAs($this->abogadaVallecas);

        $this->assertFalse(Gate::allows('update', $this->ana));

        $ficha = Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $this->ana->id]);
        $this->assertFalse($ficha->get('puedeEditar'));
        $this->assertTrue($ficha->get('soloPorEncargo'));
        $ficha->assertSee($this->ana->nombre)
            ->assertDontSeeHtml('id="ficha-asignaciones"')
            ->assertDontSeeHtml('id="ficha-estado-historia"')
            ->assertDontSeeHtml('wire:click="activarEdicion"')
            ->assertDontSee('Prestaciones de la persona');

        $this->assertFalse($ficha->call('activarEdicion')->get('modoEdicion'));
    }

    /** TF-ENC-25 — Sin Historia Social ni plan, aunque esté adscrito al centro del plan. [negativo] */
    #[Test]
    public function tf_enc_25_sin_historia_social(): void
    {
        // El educador está adscrito al CSS Norte, centro de la historia y del plan de Ana
        $this->encargoAceptado($this->historiaAna, $this->educadorMulti);
        $this->actingAs($this->educadorMulti);

        $this->assertTrue(Gate::allows('view', $this->ana));
        $this->assertFalse(Gate::allows('view', $this->historiaAna));
        $this->assertFalse(Gate::allows('view', $this->pisoAna));
        $this->get(route('intervencion.ciudadano.show', $this->historiaAna->id))->assertForbidden();
        $this->get(route('intervencion.plan.show', $this->pisoAna->id))->assertForbidden();
    }

    /** TF-ENC-26 — Buscador: solo devuelve personas de sus encargos. [negativo] */
    #[Test]
    public function tf_enc_26_buscador_limitado(): void
    {
        $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        [$otra] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);

        $this->actingAs($this->abogadaVallecas)->get(route('ciudadania.buscar'))->assertOk();

        $buscador = Livewire::test(BuscarCiudadanoPage::class)
            ->set('query', $this->ana->nombre_completo)->call('buscar');
        $this->assertSame([$this->ana->id], array_column($buscador->get('resultados'), 'ciudadano_id'));
        $this->assertSame('ficha', $buscador->get('resultados')[0]['accion']);

        $buscador->set('query', $otra->nombre_completo)->call('buscar');
        $this->assertSame([], $buscador->get('resultados'));
        $buscador->assertDontSee('Dar de alta nuevo ciudadano/a');
    }

    /** TF-ENC-27 — Un encargo pendiente no da acceso. [negativo] */
    #[Test]
    public function tf_enc_27_pendiente_no_da_acceso(): void
    {
        $encargo = $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);
        // Aunque figurase ya como responsable, pendiente no cuenta
        $encargo->update(['profesional_responsable_id' => $this->abogadaVallecas->id]);
        $this->actingAs($this->abogadaVallecas);

        $this->assertFalse(Gate::allows('view', $this->ana));
        $buscador = Livewire::test(BuscarCiudadanoPage::class)->set('query', $this->ana->nombre_completo)->call('buscar');
        $this->assertSame([], $buscador->get('resultados'));
        $this->get(route('intervencion.encargos.show', $encargo->id))->assertForbidden();
    }

    /** TF-ENC-28 — EncargoPage: detalle con motivo, TSR y documentos; registrar el resultado lo cierra. */
    #[Test]
    public function tf_enc_28_detalle_y_cierre(): void
    {
        $documento = $this->documentoEnCarpeta($this->ana, 'Notificación del juzgado');
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas, [$documento->id]);

        $this->actingAs($this->abogadaVallecas);
        $pagina = Livewire::test(EncargoPage::class, ['encargo' => $encargo->id])
            ->assertSee('Orientación jurídica sobre el desahucio')
            ->assertSee($this->tsNorte->nombreVisible())
            ->assertSee('Notificación del juzgado');

        // Sin resultado no se cierra
        $pagina->set('resultado', '  ')->call('cerrar')->assertHasErrors('resultado');
        $this->assertSame(EstadoDerivacion::Aceptada, $encargo->fresh()->estado);

        $pagina->set('resultado', 'Se presenta solicitud de justicia gratuita.')->call('cerrar')->assertHasNoErrors();
        $encargo->refresh();
        $this->assertSame(EstadoDerivacion::Cerrada, $encargo->estado);
        $this->assertSame('Se presenta solicitud de justicia gratuita.', $encargo->resultado);
        $pagina->assertSee('Se presenta solicitud de justicia gratuita.')->assertDontSeeHtml('id="encargo-cerrar"');
    }

    /** TF-ENC-29 — EncargoPage de otro profesional → 403. [negativo] */
    #[Test]
    public function tf_enc_29_encargo_de_otro_profesional(): void
    {
        $encargo = $this->encargoAceptado($this->historiaAna, $this->educadorMulti);

        $this->actingAs($this->abogadaVallecas)->get(route('intervencion.encargos.show', $encargo->id))->assertForbidden();
        $this->actingAs($this->tsNorte)->get(route('intervencion.encargos.show', $encargo->id))->assertForbidden();
        $this->actingAs($this->educadorMulti)->get(route('intervencion.encargos.show', $encargo->id))->assertOk();
    }

    /** TF-ENC-33 (avance) — Protegida: con el encargo aceptado la ve; cerrado, enmascarada y sin ficha. [negativo] */
    #[Test]
    public function tf_enc_protegida_solo_con_encargo_aceptado(): void
    {
        $encargo = $this->encargoAceptado($this->historiaMarta, $this->abogadaVallecas);
        $this->actingAs($this->abogadaVallecas);
        $marta = Ciudadano::withoutGlobalScopes()->find($this->marta->id);

        $this->assertTrue(Gate::allows('view', $marta));

        app(EncargoService::class)->cerrar($encargo, 'Hecho.', $this->abogadaVallecas);

        $this->assertFalse(Gate::allows('view', $marta));
        $this->get(route('ciudadania.ciudadano.ficha', $marta->id))->assertForbidden();
        $this->get(route('intervencion.encargos.show', $encargo->id))->assertForbidden();
        Livewire::test(MisEncargosPage::class)
            ->assertSee('Persona con protección especial')
            ->assertDontSee($marta->nombre_completo)
            ->assertDontSeeHtml(route('intervencion.encargos.show', $encargo->id));
    }
}
