<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\RegistrarValoracionPage;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo VAL: RegistrarValoracionPage.
 *
 * TF-LW-VAL-01 a TF-LW-VAL-10
 *
 * @see docs/instrucciones-cli/valoracion-page-implementacion.md
 */
class RegistrarValoracionPageTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private HistoriaSocial $historia;

    private TipoFicha $tipoFicha;

    protected function setUp(): void
    {
        parent::setUp();

        $uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Test Valoracion',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->usuario = User::factory()->create();

        $this->historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => 1,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $this->tipoFicha = TipoFicha::create([
            'nombre' => 'Ficha Test Observaciones',
            'activo' => true,
            'schema' => [
                'campos' => [
                    [
                        'id' => 'observaciones',
                        'tipo' => 'texto',
                        'etiqueta' => 'Observaciones',
                        'descripcion' => null,
                        'obligatorio' => false,
                        'orden' => 1,
                    ],
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-LW-VAL-01: El componente monta correctamente y asigna historiaId.
     */
    #[Test]
    public function componente_monta_y_asigna_historia_id(): void
    {
        $this->actingAs($this->usuario);

        Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia])
            ->assertSet('historiaId', $this->historia->id)
            ->assertSet('tipoFichaId', null)
            ->assertSet('estadoGuardado', null);
    }

    /**
     * TF-LW-VAL-02: seleccionarFicha() actualiza tipoFichaId e inicializa datos.
     */
    #[Test]
    public function seleccionar_ficha_actualiza_tipo_ficha_id_e_inicializa_datos(): void
    {
        $this->actingAs($this->usuario);

        Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia])
            ->call('seleccionarFicha', $this->tipoFicha->id)
            ->assertSet('tipoFichaId', $this->tipoFicha->id)
            ->assertSet('datos', ['observaciones' => null]);
    }

    /**
     * TF-LW-VAL-03: inicializarDatos crea una clave null por cada campo del schema.
     */
    #[Test]
    public function inicializar_datos_crea_clave_null_por_campo(): void
    {
        $fichaDosCampos = TipoFicha::create([
            'nombre' => 'Ficha Dos Campos',
            'activo' => true,
            'schema' => [
                'campos' => [
                    ['id' => 'nombre', 'tipo' => 'texto', 'etiqueta' => 'Nombre', 'descripcion' => null, 'obligatorio' => false, 'orden' => 1],
                    ['id' => 'edad',   'tipo' => 'numero', 'etiqueta' => 'Edad',   'descripcion' => null, 'obligatorio' => false, 'orden' => 2],
                ],
            ],
        ]);

        $this->actingAs($this->usuario);

        Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia])
            ->call('seleccionarFicha', $fichaDosCampos->id)
            ->assertSet('datos', ['nombre' => null, 'edad' => null]);
    }

    /**
     * TF-LW-VAL-04: El computed tipoFicha devuelve el modelo correcto tras seleccionar.
     */
    #[Test]
    public function computed_tipo_ficha_devuelve_el_modelo_seleccionado(): void
    {
        $this->actingAs($this->usuario);

        $component = Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia])
            ->call('seleccionarFicha', $this->tipoFicha->id);

        $this->assertInstanceOf(TipoFicha::class, $component->get('tipoFicha'));
        $this->assertEquals($this->tipoFicha->id, $component->get('tipoFicha')->id);
    }

    /**
     * TF-LW-VAL-05: fichasDisponibles devuelve solo fichas activas.
     */
    #[Test]
    public function fichas_disponibles_excluye_fichas_inactivas(): void
    {
        $fichaInactiva = TipoFicha::create([
            'nombre' => 'Ficha Inactiva',
            'activo' => false,
            'schema' => ['campos' => [
                ['id' => 'x', 'tipo' => 'texto', 'etiqueta' => 'X', 'descripcion' => null, 'obligatorio' => false, 'orden' => 1],
            ]],
        ]);

        $this->actingAs($this->usuario);

        $component = Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia]);

        $disponibles = $component->get('fichasDisponibles');
        $this->assertArrayHasKey($this->tipoFicha->id, $disponibles);
        $this->assertArrayNotHasKey($fichaInactiva->id, $disponibles);
    }

    /**
     * TF-LW-VAL-06: guardar() persiste una Ficha con los datos y la historia correcta.
     */
    #[Test]
    public function guardar_crea_ficha_con_datos_y_historia_correctos(): void
    {
        $this->actingAs($this->usuario);

        Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia])
            ->call('seleccionarFicha', $this->tipoFicha->id)
            ->set('datos.observaciones', 'Texto de prueba')
            ->call('guardar');

        $ficha = Ficha::first();
        $this->assertNotNull($ficha);
        $this->assertEquals($this->historia->id, $ficha->historia_id);
        $this->assertEquals($this->tipoFicha->id, $ficha->tipo_ficha_id);
        $this->assertEquals('Texto de prueba', $ficha->datos['observaciones']);
    }

    /**
     * TF-LW-VAL-07: guardar() con campo obligatorio vacío genera error y no crea Ficha.
     */
    #[Test]
    public function guardar_con_campo_obligatorio_vacio_genera_error(): void
    {
        $fichaObligatoria = TipoFicha::create([
            'nombre' => 'Ficha Obligatoria',
            'activo' => true,
            'schema' => ['campos' => [
                ['id' => 'campo_req', 'tipo' => 'texto', 'etiqueta' => 'Campo requerido', 'descripcion' => null, 'obligatorio' => true, 'orden' => 1],
            ]],
        ]);

        $this->actingAs($this->usuario);

        Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia])
            ->call('seleccionarFicha', $fichaObligatoria->id)
            ->call('guardar')
            ->assertHasErrors(['datos.campo_req'])
            ->assertSet('estadoGuardado', null);

        $this->assertEquals(0, Ficha::count());
    }

    /**
     * TF-LW-VAL-08: Dos llamadas a guardar() actualizan la misma Ficha (updateOrCreate).
     */
    #[Test]
    public function guardar_dos_veces_actualiza_la_misma_ficha(): void
    {
        $this->actingAs($this->usuario);

        Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia])
            ->call('seleccionarFicha', $this->tipoFicha->id)
            ->set('datos.observaciones', 'Primera versión')
            ->call('guardar');

        Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia])
            ->call('seleccionarFicha', $this->tipoFicha->id)
            ->set('datos.observaciones', 'Segunda versión')
            ->call('guardar');

        $this->assertEquals(1, Ficha::count());
        $this->assertEquals('Segunda versión', Ficha::first()->datos['observaciones']);
    }

    /**
     * TF-LW-VAL-09: guardar() persiste el campo notas en la Ficha.
     */
    #[Test]
    public function guardar_persiste_el_campo_notas(): void
    {
        $this->actingAs($this->usuario);

        Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia])
            ->call('seleccionarFicha', $this->tipoFicha->id)
            ->set('notas', 'Notas de la entrevista domiciliaria')
            ->call('guardar');

        $this->assertEquals('Notas de la entrevista domiciliaria', Ficha::first()->notas);
    }

    /**
     * TF-LW-VAL-10: estadoGuardado es 'guardado' tras una llamada exitosa a guardar().
     */
    #[Test]
    public function estado_guardado_es_guardado_tras_guardar_exitoso(): void
    {
        $this->actingAs($this->usuario);

        Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historia])
            ->call('seleccionarFicha', $this->tipoFicha->id)
            ->call('guardar')
            ->assertSet('estadoGuardado', 'guardado');
    }
}
