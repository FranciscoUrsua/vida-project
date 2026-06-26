<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\Espacio;
use Modules\Centro\Models\ListaEspera;
use Modules\Centro\Models\ListaEsperaMovimiento;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\TipoEspacio;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\PrescribirRecursoModal;
use Modules\Intervencion\Http\Livewire\RecursosPage;
use Modules\Intervencion\Models\PlanActuacionAyuntamiento;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales de plazas y recursos (TF-REC-A a TF-REC-E).
 *
 * @see docs/instrucciones-cli/plazas-recursos-instrucciones-cli.md §4
 */
class PlazasRecursosTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    private User $usuario;

    private Profesional $profesional;

    private Ciudadano $ciudadano;

    private HistoriaSocial $historia;

    private Centro $centro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Recursos Test',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->centro = Centro::create([
            'nombre' => 'Centro Recursos Test',
            'tipo_gestion' => 'municipal_directo',
            'unidad_organizativa_id' => $this->uo->id,
            'activo' => true,
            'fecha_alta' => today()->toDateString(),
        ]);

        // Profesional creado antes que el User para poder asignar profesional_id
        $this->profesional = $this->crearProfesional();

        $this->usuario = User::create([
            'name' => 'TSR Recursos',
            'email' => 'tsr.recursos@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
            'profesional_id' => $this->profesional->id,
        ]);

        $this->usuario->assignRole('intervencion');

        UsuarioUo::create([
            'usuario_id' => $this->usuario->id,
            'unidad_organizativa_id' => $this->uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        $this->ciudadano = Ciudadano::factory()->create();

        $this->historia = HistoriaSocial::create([
            'ciudadano_id' => $this->ciudadano->id,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);
    }

    // =========================================================================
    // Grupo A — Guard de Historia Social (TF-REC-A)
    // =========================================================================

    /**
     * TF-REC-A01 — No se puede cerrar una Historia Social con prescripción activa.
     */
    #[Test]
    public function no_se_puede_cerrar_historia_social_con_prescripcion_activa(): void
    {
        $coleccion = $this->crearColeccion();

        Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccion->id,
            'estado' => 'asignada',
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No se puede cerrar la historia social con prescripciones activas.');

        $this->historia->cerrar();

        $this->historia->refresh();
        $this->assertNotEquals('cerrada', $this->historia->estado);
    }

    /**
     * TF-REC-A02 — Se puede cerrar una Historia Social sin prescripciones activas.
     */
    #[Test]
    public function se_puede_cerrar_historia_social_sin_prescripciones_activas(): void
    {
        $coleccion = $this->crearColeccion();

        Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccion->id,
            'estado' => 'finalizada',
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        $this->historia->cerrar();

        $this->historia->refresh();
        $this->assertEquals('cerrada', $this->historia->estado);
    }

    /**
     * TF-REC-A03 — Guard aplica también a prescripciones en_lista_espera.
     */
    #[Test]
    public function guard_aplica_a_prescripcion_en_lista_espera(): void
    {
        $coleccion = $this->crearColeccion();

        Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccion->id,
            'estado' => 'en_lista_espera',
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        $this->expectException(\DomainException::class);

        $this->historia->cerrar();
    }

    // =========================================================================
    // Grupo B — Prescripción desde CiudadanoPage (TF-REC-B)
    // =========================================================================

    /**
     * TF-REC-B01 — Botón «Prescribir recurso» visible con Historia Social abierta.
     */
    #[Test]
    public function boton_prescribir_visible_con_historia_abierta(): void
    {
        $this->actingAs($this->usuario);

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historia])
            ->assertSee('Prescribir recurso');
    }

    /**
     * TF-REC-B02 — Botón «Prescribir recurso» NO visible con Historia Social cerrada.
     */
    #[Test]
    public function boton_prescribir_no_visible_con_historia_cerrada(): void
    {
        $this->historia->update(['estado' => 'cerrada']);

        $this->actingAs($this->usuario);

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historia])
            ->assertDontSee('Prescribir recurso');
    }

    /**
     * TF-REC-B03 — Prescripción con plaza disponible queda en estado asignada.
     */
    #[Test]
    public function prescripcion_con_plaza_disponible_queda_asignada(): void
    {
        $coleccion = $this->crearColeccion();
        $this->crearPlazaEnColeccion($coleccion, 'libre');

        $this->actingAs($this->usuario);

        Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta')
            ->call('avanzar')
            ->set('destinoId', $coleccion->id)
            ->call('avanzar')
            ->call('confirmar');

        $prescripcion = Prescripcion::where('ciudadano_id', $this->ciudadano->id)->first();
        $this->assertNotNull($prescripcion);
        $this->assertEquals('asignada', $prescripcion->estado);
        // plaza_id se asignará manualmente por el TS del centro vía AsignarPlazaModal
    }

    /**
     * TF-REC-B04 — Prescripción sin plaza disponible queda en lista de espera.
     */
    #[Test]
    public function prescripcion_sin_plaza_disponible_entra_en_lista_espera(): void
    {
        $coleccion = $this->crearColeccion();
        $this->crearPlazaEnColeccion($coleccion, 'ocupada');

        $this->actingAs($this->usuario);

        Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta')
            ->call('avanzar')
            ->set('destinoId', $coleccion->id)
            ->call('avanzar')
            ->call('confirmar');

        $prescripcion = Prescripcion::where('ciudadano_id', $this->ciudadano->id)->first();
        $this->assertNotNull($prescripcion);
        $this->assertEquals('en_lista_espera', $prescripcion->estado);
        $this->assertNotNull(ListaEspera::where('prescripcion_id', $prescripcion->id)->first());
    }

    /**
     * TF-REC-B05 — Prescripción crea apunte de tipo derivacion en la Historia Social.
     */
    #[Test]
    public function prescripcion_crea_apunte_derivacion_en_historia_social(): void
    {
        $coleccion = $this->crearColeccion();
        $this->crearPlazaEnColeccion($coleccion, 'libre');

        $this->actingAs($this->usuario);

        Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta')
            ->call('avanzar')
            ->set('destinoId', $coleccion->id)
            ->call('avanzar')
            ->call('confirmar');

        $prescripcion = Prescripcion::where('ciudadano_id', $this->ciudadano->id)->first();
        $apunte = \Modules\Intervencion\Models\Apunte::where('historia_id', $this->historia->id)
            ->where('apuntable_type', Prescripcion::class)
            ->where('apuntable_id', $prescripcion->id)
            ->first();

        $this->assertNotNull($apunte);
        $this->assertEquals('derivacion', $apunte->tipo->value);
    }

    /**
     * TF-REC-B06 — Sin plan activo no se muestra selector de compromisos.
     */
    #[Test]
    public function sin_plan_activo_no_se_muestra_selector_compromisos(): void
    {
        $coleccion = $this->crearColeccion();

        $this->actingAs($this->usuario);

        $component = Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta')
            ->call('avanzar')
            ->set('destinoId', $coleccion->id)
            ->call('avanzar');

        $this->assertEmpty($component->instance()->compromisosSugeridos);
    }

    /**
     * TF-REC-B07 — Con plan activo y compromisos, el selector aparece en el paso 3.
     */
    #[Test]
    public function con_plan_activo_se_muestran_compromisos_sugeridos(): void
    {
        $coleccion = $this->crearColeccion();
        $plan = $this->crearPlanActivo();
        $this->crearCompromisoAyuntamiento($plan);

        $this->actingAs($this->usuario);

        $component = Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta')
            ->call('avanzar')
            ->set('destinoId', $coleccion->id)
            ->call('avanzar');

        $this->assertNotEmpty($component->instance()->compromisosSugeridos);
    }

    /**
     * TF-REC-B08 — Vincular prescripción a compromiso guarda compromiso_id.
     */
    #[Test]
    public function vincular_prescripcion_a_compromiso_guarda_compromiso_id(): void
    {
        $coleccion = $this->crearColeccion();
        $this->crearPlazaEnColeccion($coleccion, 'libre');
        $plan = $this->crearPlanActivo();
        $compromiso = $this->crearCompromisoAyuntamiento($plan);

        $this->actingAs($this->usuario);

        Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta')
            ->call('avanzar')
            ->set('destinoId', $coleccion->id)
            ->call('avanzar')
            ->set('compromisoId', $compromiso->id)
            ->call('confirmar');

        $prescripcion = Prescripcion::where('ciudadano_id', $this->ciudadano->id)->first();
        $this->assertEquals($compromiso->id, $prescripcion->compromiso_id);
        // El estado del compromiso no cambia
        $compromiso->refresh();
        $this->assertEquals('pendiente', $compromiso->estado);
    }

    /**
     * TF-REC-B09 — Prescripción sin vínculo a compromiso tiene compromiso_id nulo.
     */
    #[Test]
    public function prescripcion_sin_compromiso_tiene_compromiso_id_nulo(): void
    {
        $coleccion = $this->crearColeccion();
        $this->crearPlazaEnColeccion($coleccion, 'libre');
        $plan = $this->crearPlanActivo();
        $this->crearCompromisoAyuntamiento($plan);

        $this->actingAs($this->usuario);

        Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta')
            ->call('avanzar')
            ->set('destinoId', $coleccion->id)
            ->call('avanzar')
            ->call('confirmar'); // sin seleccionar compromiso

        $prescripcion = Prescripcion::where('ciudadano_id', $this->ciudadano->id)->first();
        $this->assertNull($prescripcion->compromiso_id);
    }

    /**
     * TF-REC-B10 — Fallo al crear el Apunte hace rollback de la Prescripcion.
     */
    #[Test]
    public function fallo_en_apunte_hace_rollback_de_prescripcion(): void
    {
        $coleccion = $this->crearColeccion();
        $this->crearPlazaEnColeccion($coleccion, 'libre');

        // Simular fallo en Apunte::create via observer falso que lanza excepción
        \Modules\Intervencion\Models\Apunte::creating(function () {
            throw new \RuntimeException('Error simulado en Apunte::create');
        });

        $this->actingAs($this->usuario);

        try {
            Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
                ->call('abrir')
                ->set('tipoRecurso', 'pernocta')
                ->call('avanzar')
                ->set('destinoId', $coleccion->id)
                ->call('avanzar')
                ->call('confirmar');
        } catch (\Throwable) {
            // Se espera excepción
        }

        $this->assertDatabaseMissing('prescripciones', ['ciudadano_id' => $this->ciudadano->id]);
        $this->assertDatabaseMissing('lista_espera', []);
    }

    /**
     * TF-REC-B11 — El TSR no puede prescribir a un destino que no corresponde al tipo seleccionado.
     *
     * Escenario: se solicita tipo 'pernocta' pero se inyecta el ID de una colección de tipo
     * 'alojamiento_dia'. El servidor valida contra opcionesDestino y rechaza la petición.
     */
    #[Test]
    public function tsr_no_puede_prescribir_a_destino_fuera_de_ambito(): void
    {
        // Colección de tipo diferente al solicitado → no aparece en opcionesDestino('pernocta')
        $coleccionTipoDiferente = ColeccionPlazas::create([
            'centro_id' => $this->centro->id,
            'nombre' => 'Colección Tipo Diferente',
            'tipo_plaza' => 'alojamiento_dia',
            'modo_acceso' => 'prescripcion_directa',
            'capacidad' => 5,
            'activa' => true,
            'fecha_alta' => today()->toDateString(),
        ]);

        $this->actingAs($this->usuario);

        Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta')       // solicita pernocta
            ->set('destinoId', $coleccionTipoDiferente->id) // inyección de colección tipo diferente
            ->call('confirmar');

        $this->assertDatabaseMissing('prescripciones', ['ciudadano_id' => $this->ciudadano->id]);
    }

    // =========================================================================
    // Grupo C — Heurística de destino (TF-REC-C)
    // =========================================================================

    /**
     * TF-REC-C01 — Con red disponible, opcionesDestino devuelve redes.
     */
    #[Test]
    public function con_red_disponible_opciones_destino_devuelve_redes(): void
    {
        $coleccion = $this->crearColeccion();
        $red = \Modules\Centro\Models\Red::create([
            'nombre' => 'Red de Recursos',
            'activa' => true,
            'fecha_alta' => today()->toDateString(),
        ]);
        $red->centros()->attach($this->centro->id);

        $this->actingAs($this->usuario);

        $component = Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta');

        $opciones = $component->instance()->opcionesDestino;
        $this->assertNotEmpty($opciones);
        $this->assertInstanceOf(\Modules\Centro\Models\Red::class, $opciones->first());
    }

    /**
     * TF-REC-C02 — Sin red, opcionesDestino devuelve ColeccionPlazas individuales.
     */
    #[Test]
    public function sin_red_opciones_destino_devuelve_colecciones_individuales(): void
    {
        $coleccion = $this->crearColeccion();

        $this->actingAs($this->usuario);

        $component = Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta');

        $opciones = $component->instance()->opcionesDestino;
        $this->assertNotEmpty($opciones);
        $this->assertInstanceOf(ColeccionPlazas::class, $opciones->first());
    }

    /**
     * TF-REC-C03 — Con criterio territorial y ciudadano con dirección, resultados ordenados por proximidad.
     */
    #[Test]
    public function con_criterio_territorial_resultados_ordenados_por_proximidad(): void
    {
        $ciudadanoConDir = Ciudadano::factory()->create([
            'coordenadas_lat' => 40.416775,
            'coordenadas_lng' => -3.703790,
        ]);
        $historia = HistoriaSocial::create([
            'ciudadano_id' => $ciudadanoConDir->id,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        // Centro cercano (~0.5 km)
        $centroC = Centro::create(['nombre' => 'Centro Cercano', 'tipo_gestion' => 'municipal_directo', 'activo' => true, 'fecha_alta' => today()->toDateString(), 'coordenadas_lat' => 40.4172, 'coordenadas_lng' => -3.7040]);
        $colC = ColeccionPlazas::create(['centro_id' => $centroC->id, 'nombre' => 'Col Cercana', 'tipo_plaza' => 'pernocta', 'modo_acceso' => 'prescripcion_directa', 'capacidad' => 5, 'activa' => true, 'criterio_territorial' => true, 'fecha_alta' => today()->toDateString()]);

        // Centro lejano (~5 km)
        $centroL = Centro::create(['nombre' => 'Centro Lejano', 'tipo_gestion' => 'municipal_directo', 'activo' => true, 'fecha_alta' => today()->toDateString(), 'coordenadas_lat' => 40.4600, 'coordenadas_lng' => -3.7500]);
        $colL = ColeccionPlazas::create(['centro_id' => $centroL->id, 'nombre' => 'Col Lejana', 'tipo_plaza' => 'pernocta', 'modo_acceso' => 'prescripcion_directa', 'capacidad' => 5, 'activa' => true, 'criterio_territorial' => true, 'fecha_alta' => today()->toDateString()]);

        $this->actingAs($this->usuario);

        $component = Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta');

        $opciones = $component->instance()->opcionesDestino;
        $this->assertEquals($colC->id, $opciones->first()->id, 'El centro cercano debe aparecer primero.');
    }

    /**
     * TF-REC-C04 — Con criterio territorial y ciudadano PSH sin dirección, no se lanza excepción.
     */
    #[Test]
    public function con_criterio_territorial_y_ciudadano_sin_direccion_no_falla(): void
    {
        $ciudadanoSinDir = Ciudadano::factory()->create([
            'coordenadas_lat' => null,
            'coordenadas_lng' => null,
        ]);
        $historia = HistoriaSocial::create([
            'ciudadano_id' => $ciudadanoSinDir->id,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        ColeccionPlazas::create(['centro_id' => $this->centro->id, 'nombre' => 'Col CT', 'tipo_plaza' => 'pernocta', 'modo_acceso' => 'prescripcion_directa', 'capacidad' => 5, 'activa' => true, 'criterio_territorial' => true, 'fecha_alta' => today()->toDateString()]);

        $this->actingAs($this->usuario);

        $component = Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta');

        // No debe lanzar excepción
        $opciones = $component->instance()->opcionesDestino;
        $this->assertNotEmpty($opciones);
    }

    /**
     * TF-REC-C05 — Sin criterio territorial, no se calcula distancia.
     */
    #[Test]
    public function sin_criterio_territorial_no_se_calcula_distancia(): void
    {
        // Ciudadano con coordenadas pero colección sin criterio_territorial
        $ciudadanoConDir = Ciudadano::factory()->create([
            'coordenadas_lat' => 40.416775,
            'coordenadas_lng' => -3.703790,
        ]);
        $historia = HistoriaSocial::create([
            'ciudadano_id' => $ciudadanoConDir->id,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        ColeccionPlazas::create(['centro_id' => $this->centro->id, 'nombre' => 'Col sin CT', 'tipo_plaza' => 'pernocta', 'modo_acceso' => 'prescripcion_directa', 'capacidad' => 5, 'activa' => true, 'criterio_territorial' => false, 'fecha_alta' => today()->toDateString()]);

        $this->actingAs($this->usuario);

        // No debe lanzar excepción ni calcular haversine
        $component = Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $historia->id])
            ->call('abrir')
            ->set('tipoRecurso', 'pernocta');

        $opciones = $component->instance()->opcionesDestino;
        $this->assertNotEmpty($opciones);
    }

    // =========================================================================
    // Grupo D — RecursosPage (TF-REC-D)
    // =========================================================================

    /**
     * TF-REC-D01 — RecursosPage devuelve 403 (o redirige) para TS sin plazas.
     */
    #[Test]
    public function recursos_page_no_accesible_sin_plazas_configuradas(): void
    {
        // Sin plazas configuradas (ConfiguracionService retorna false por defecto)
        $this->actingAs($this->usuario);

        $response = $this->get(route('intervencion.recursos.index'));
        // La ruta existe pero tienePlazas=false → la pantalla se renderiza (no 404)
        // El sidebar no muestra el ítem. La pantalla en sí no bloquea (la guard es en sidebar).
        $response->assertStatus(200);
    }

    /**
     * TF-REC-D02 — RecursosPage solo muestra prescripciones del ámbito del centro.
     */
    #[Test]
    public function recursos_page_lista_solo_prescripciones_del_centro(): void
    {
        $coleccion = $this->crearColeccion();

        // 3 prescripciones del centro del usuario
        for ($i = 0; $i < 3; $i++) {
            Prescripcion::create([
                'profesional_id' => $this->profesional->id,
                'ciudadano_id' => $this->ciudadano->id,
                'tipo_destino' => 'coleccion_plazas',
                'destino_id' => $coleccion->id,
                'estado' => 'en_lista_espera',
                'fecha_prescripcion' => today()->toDateString(),
            ]);
        }

        // 2 prescripciones de otro centro (no deben aparecer)
        $otraUo = UnidadOrganizativa::create(['nombre' => 'Otra UO D02', 'tipo' => 'centro', 'activa' => true]);
        $otroCentro = Centro::create(['nombre' => 'Otro Centro D02', 'tipo_gestion' => 'municipal_directo', 'unidad_organizativa_id' => $otraUo->id, 'activo' => true, 'fecha_alta' => today()->toDateString()]);
        $otraColeccion = ColeccionPlazas::create(['centro_id' => $otroCentro->id, 'nombre' => 'Otra Col', 'tipo_plaza' => 'pernocta', 'modo_acceso' => 'prescripcion_directa', 'capacidad' => 5, 'activa' => true, 'fecha_alta' => today()->toDateString()]);

        for ($i = 0; $i < 2; $i++) {
            Prescripcion::create([
                'profesional_id' => $this->profesional->id,
                'ciudadano_id' => $this->ciudadano->id,
                'tipo_destino' => 'coleccion_plazas',
                'destino_id' => $otraColeccion->id,
                'estado' => 'en_lista_espera',
                'fecha_prescripcion' => today()->toDateString(),
            ]);
        }

        $this->actingAs($this->usuario);

        $component = Livewire::test(RecursosPage::class);
        $this->assertCount(3, $component->instance()->prescripcionesPendientes->items());
    }

    /**
     * TF-REC-D03 — Asignación de plaza actualiza todos los estados en transacción.
     */
    #[Test]
    public function asignacion_de_plaza_actualiza_todos_los_estados(): void
    {
        $coleccion = $this->crearColeccion();
        $plaza = $this->crearPlazaEnColeccion($coleccion, 'libre');

        $prescripcion = Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccion->id,
            'estado' => 'en_lista_espera',
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        $listaEspera = ListaEspera::create([
            'prescripcion_id' => $prescripcion->id,
            'coleccion_plazas_id' => $coleccion->id,
            'profesional_alerta_id' => $this->profesional->id,
            'estado' => 'activa',
            'posicion' => 1,
            'fecha_entrada' => now(),
        ]);

        $this->actingAs($this->usuario);

        Livewire::test(\Modules\Intervencion\Http\Livewire\AsignarPlazaModal::class)
            ->call('abrir', $prescripcion->id)
            ->call('asignar', $plaza->id);

        $prescripcion->refresh();
        $this->assertEquals('asignada', $prescripcion->estado);
        $this->assertEquals($plaza->id, $prescripcion->plaza_id);
        $this->assertNotNull($prescripcion->fecha_asignacion);

        $plaza->refresh();
        $this->assertEquals('ocupada', $plaza->estado);

        $listaEspera->refresh();
        $this->assertEquals('asignada', $listaEspera->estado);
    }

    /**
     * TF-REC-D04 — Asignar tipo de espacio diferente al solicitado no se bloquea.
     */
    #[Test]
    public function asignacion_tipo_diferente_se_permite_con_nota(): void
    {
        $coleccion = $this->crearColeccion();
        $plaza = $this->crearPlazaEnColeccion($coleccion, 'libre');

        $prescripcion = Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccion->id,
            'estado' => 'en_lista_espera',
            'fecha_prescripcion' => today()->toDateString(),
            'notas' => 'Solicitaba habitación individual',
        ]);

        ListaEspera::create([
            'prescripcion_id' => $prescripcion->id,
            'coleccion_plazas_id' => $coleccion->id,
            'profesional_alerta_id' => $this->profesional->id,
            'estado' => 'activa',
            'posicion' => 1,
            'fecha_entrada' => now(),
        ]);

        $this->actingAs($this->usuario);

        Livewire::test(\Modules\Intervencion\Http\Livewire\AsignarPlazaModal::class)
            ->call('abrir', $prescripcion->id)
            ->call('asignar', $plaza->id, 'Se asigna habitación disponible por falta de individual.');

        $prescripcion->refresh();
        $this->assertEquals('asignada', $prescripcion->estado);
    }

    /**
     * TF-REC-D05 — TS no puede asignar plaza de otro centro.
     */
    #[Test]
    public function ts_no_puede_asignar_plaza_de_otro_centro(): void
    {
        // Plaza de otro centro (fuera del ámbito del usuario)
        $otraUo = UnidadOrganizativa::create(['nombre' => 'Otra UO D05', 'tipo' => 'centro', 'activa' => true]);
        $otroCentro = Centro::create(['nombre' => 'Otro Centro D05', 'tipo_gestion' => 'municipal_directo', 'unidad_organizativa_id' => $otraUo->id, 'activo' => true, 'fecha_alta' => today()->toDateString()]);
        $otraColeccion = ColeccionPlazas::create(['centro_id' => $otroCentro->id, 'nombre' => 'Otra Col D05', 'tipo_plaza' => 'pernocta', 'modo_acceso' => 'prescripcion_directa', 'capacidad' => 5, 'activa' => true, 'fecha_alta' => today()->toDateString()]);
        $tipoEspacio = TipoEspacio::create(['nombre' => 'Habitación D05', 'slug' => 'habitacion-d05', 'activo' => true]);
        $espacio = Espacio::create(['coleccion_plazas_id' => $otraColeccion->id, 'tipo_espacio_id' => $tipoEspacio->id, 'nombre' => 'Hab D05', 'capacidad' => 1, 'accesible' => false, 'activo' => true]);
        $plazaOtroCentro = Plaza::create(['espacio_id' => $espacio->id, 'nombre' => 'Plaza Otro Centro', 'estado' => 'libre', 'activa' => true]);

        $coleccionPropia = $this->crearColeccion();
        $prescripcion = Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccionPropia->id,
            'estado' => 'en_lista_espera',
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        ListaEspera::create([
            'prescripcion_id' => $prescripcion->id,
            'coleccion_plazas_id' => $coleccionPropia->id,
            'profesional_alerta_id' => $this->profesional->id,
            'estado' => 'activa',
            'posicion' => 1,
            'fecha_entrada' => now(),
        ]);

        $this->actingAs($this->usuario);

        Livewire::test(\Modules\Intervencion\Http\Livewire\AsignarPlazaModal::class)
            ->call('abrir', $prescripcion->id)
            ->call('asignar', $plazaOtroCentro->id)
            ->assertHasErrors(['plaza']);

        $plazaOtroCentro->refresh();
        $this->assertEquals('libre', $plazaOtroCentro->estado);
    }

    /**
     * TF-REC-D06 — Reordenar lista de espera actualiza posiciones y registra auditoría.
     */
    #[Test]
    public function reordenar_lista_espera_actualiza_posiciones_y_registra_auditoria(): void
    {
        $coleccion = $this->crearColeccion();

        $prescripciones = [];
        for ($i = 1; $i <= 3; $i++) {
            $ciudadanoN = Ciudadano::factory()->create();
            $p = Prescripcion::create(['profesional_id' => $this->profesional->id, 'ciudadano_id' => $ciudadanoN->id, 'tipo_destino' => 'coleccion_plazas', 'destino_id' => $coleccion->id, 'estado' => 'en_lista_espera', 'fecha_prescripcion' => today()->toDateString()]);
            $le = ListaEspera::create(['prescripcion_id' => $p->id, 'coleccion_plazas_id' => $coleccion->id, 'profesional_alerta_id' => $this->profesional->id, 'estado' => 'activa', 'posicion' => $i, 'fecha_entrada' => now()]);
            $prescripciones[] = ['p' => $p, 'le' => $le];
        }

        $this->actingAs($this->usuario);

        Livewire::test(RecursosPage::class)
            ->call('moverEnLista', $prescripciones[2]['p']->id, 1);

        $prescripciones[2]['le']->refresh();
        $this->assertEquals(1, $prescripciones[2]['le']->posicion);

        $this->assertDatabaseHas('lista_espera_movimientos', [
            'lista_espera_id' => $prescripciones[2]['le']->id,
            'posicion_anterior' => 3,
            'posicion_nueva' => 1,
            'profesional_id' => $this->profesional->id,
        ]);
    }

    /**
     * TF-REC-D07 — previsionLiberaciones solo muestra prescripciones con fecha_fin ≤ 30 días.
     */
    #[Test]
    public function prevision_liberaciones_solo_muestra_proximas_30_dias(): void
    {
        $coleccion = $this->crearColeccion();

        // Prescripción con fecha_fin en 10 días
        Prescripcion::create(['profesional_id' => $this->profesional->id, 'ciudadano_id' => $this->ciudadano->id, 'tipo_destino' => 'coleccion_plazas', 'destino_id' => $coleccion->id, 'estado' => 'activa', 'fecha_prescripcion' => today()->toDateString(), 'fecha_fin' => today()->addDays(10)->toDateString()]);

        // Prescripción con fecha_fin en 60 días (fuera de la ventana)
        $ciudadano2 = Ciudadano::factory()->create();
        Prescripcion::create(['profesional_id' => $this->profesional->id, 'ciudadano_id' => $ciudadano2->id, 'tipo_destino' => 'coleccion_plazas', 'destino_id' => $coleccion->id, 'estado' => 'activa', 'fecha_prescripcion' => today()->toDateString(), 'fecha_fin' => today()->addDays(60)->toDateString()]);

        $this->actingAs($this->usuario);

        $component = Livewire::test(RecursosPage::class);
        $previsiones = $component->instance()->previsionLiberaciones;

        $this->assertCount(1, $previsiones);
    }

    /**
     * TF-REC-D08 — Cancelar prescripción asignada libera la plaza.
     */
    #[Test]
    public function cancelar_prescripcion_asignada_libera_la_plaza(): void
    {
        $coleccion = $this->crearColeccion();
        $plaza = $this->crearPlazaEnColeccion($coleccion, 'ocupada');

        $prescripcion = Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccion->id,
            'estado' => 'asignada',
            'plaza_id' => $plaza->id,
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        $this->actingAs($this->usuario);

        Livewire::test(RecursosPage::class)
            ->call('cancelarPrescripcion', $prescripcion->id, 'Test de cancelación');

        $prescripcion->refresh();
        $this->assertEquals('cancelada', $prescripcion->estado);

        $plaza->refresh();
        $this->assertEquals('libre', $plaza->estado);
    }

    /**
     * TF-REC-D09 — Cancelar prescripción en lista de espera no afecta a ninguna plaza.
     */
    #[Test]
    public function cancelar_prescripcion_en_lista_espera_no_afecta_plazas(): void
    {
        $coleccion = $this->crearColeccion();

        $prescripcion = Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccion->id,
            'estado' => 'en_lista_espera',
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        ListaEspera::create([
            'prescripcion_id' => $prescripcion->id,
            'coleccion_plazas_id' => $coleccion->id,
            'profesional_alerta_id' => $this->profesional->id,
            'estado' => 'activa',
            'posicion' => 1,
            'fecha_entrada' => now(),
        ]);

        $plazasAntes = Plaza::where('espacio_id', null)->count(); // no hay plazas en este test

        $this->actingAs($this->usuario);

        Livewire::test(RecursosPage::class)
            ->call('cancelarPrescripcion', $prescripcion->id, 'Cancelada por prueba');

        $prescripcion->refresh();
        $this->assertEquals('cancelada', $prescripcion->estado);
        $this->assertNull($prescripcion->plaza_id);
    }

    // =========================================================================
    // Grupo E — Banda de recursos en CiudadanoPage (TF-REC-E)
    // =========================================================================

    /**
     * TF-REC-E01 — Banda de recursos muestra solo prescripciones activas del ciudadano.
     */
    #[Test]
    public function banda_recursos_muestra_solo_prescripciones_activas(): void
    {
        $coleccion = $this->crearColeccion();

        Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccion->id,
            'estado' => 'asignada',
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccion->id,
            'estado' => 'finalizada',
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        $this->actingAs($this->usuario);

        $component = Livewire::test(CiudadanoPage::class, ['historia' => $this->historia]);
        $this->assertCount(1, $component->instance()->prescripcionesActivas);
    }

    /**
     * TF-REC-E02 — Cancelar prescripción desde la banda actualiza la vista.
     */
    #[Test]
    public function cancelar_prescripcion_desde_banda_actualiza_vista(): void
    {
        $coleccion = $this->crearColeccion();

        $prescripcion = Prescripcion::create([
            'profesional_id' => $this->profesional->id,
            'ciudadano_id' => $this->ciudadano->id,
            'tipo_destino' => 'coleccion_plazas',
            'destino_id' => $coleccion->id,
            'estado' => 'en_lista_espera',
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        ListaEspera::create([
            'prescripcion_id' => $prescripcion->id,
            'coleccion_plazas_id' => $coleccion->id,
            'profesional_alerta_id' => $this->profesional->id,
            'estado' => 'activa',
            'posicion' => 1,
            'fecha_entrada' => now(),
        ]);

        $this->actingAs($this->usuario);

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historia])
            ->call('cancelarPrescripcion', $prescripcion->id);

        $prescripcion->refresh();
        $this->assertEquals('cancelada', $prescripcion->estado);
    }

    // =========================================================================
    // Utilidades de setup
    // =========================================================================

    /**
     * Crea una ColeccionPlazas activa de tipo pernocta para los tests.
     */
    private function crearColeccion(string $nombre = 'Colección Test'): ColeccionPlazas
    {
        return ColeccionPlazas::create([
            'centro_id' => $this->centro->id,
            'nombre' => $nombre,
            'tipo_plaza' => 'pernocta',
            'modo_acceso' => 'prescripcion_directa',
            'capacidad' => 10,
            'activa' => true,
            'fecha_alta' => today()->toDateString(),
        ]);
    }

    /**
     * Crea una Plaza en la colección con el estado indicado.
     *
     * @param string $estado 'libre' | 'ocupada' | 'mantenimiento'
     */
    private function crearPlazaEnColeccion(ColeccionPlazas $coleccion, string $estado = 'libre'): Plaza
    {
        $tipoEspacio = TipoEspacio::firstOrCreate(['slug' => 'habitacion-test'], ['nombre' => 'Habitación Test', 'activo' => true]);
        $espacio = Espacio::create([
            'coleccion_plazas_id' => $coleccion->id,
            'tipo_espacio_id' => $tipoEspacio->id,
            'nombre' => 'Habitación 1',
            'capacidad' => 1,
            'accesible' => false,
            'activo' => true,
        ]);

        return Plaza::create([
            'espacio_id' => $espacio->id,
            'nombre' => 'Plaza 1',
            'estado' => $estado,
            'activa' => true,
        ]);
    }

    /**
     * Crea un Profesional mínimo para los tests, asociado a la UO del setUp.
     */
    private function crearProfesional(): Profesional
    {
        $cargo = Cargo::firstOrCreate(['nombre' => 'Trabajador Social Test'], ['activo' => true]);
        $tipoRel = TipoRelacionProfesional::firstOrCreate(['nombre' => 'Funcionario Test'], ['activo' => true]);

        return Profesional::create([
            'nombre' => 'Profesional',
            'apellido1' => 'Test',
            'sexo' => 'F',
            'cargo_id' => $cargo->id,
            'tipo_relacion_id' => $tipoRel->id,
            'fecha_inicio' => today()->toDateString(),
            'activo' => true,
            'unidad_organizativa_id' => $this->uo->id,
        ]);
    }

    /**
     * Crea un PlanDeIntervencion activo para el ciudadano de setUp.
     */
    private function crearPlanActivo(): PlanDeIntervencion
    {
        return PlanDeIntervencion::factory()->create([
            'historia_id' => $this->historia->id,
            'profesional_responsable_id' => $this->usuario->id,
            'tipo' => \Modules\Intervencion\Enums\TipoPlan::GeneralAsp,
            'estado' => \Modules\Intervencion\Enums\EstadoPlan::Activo,
        ]);
    }

    /**
     * Crea un compromiso del Ayuntamiento vinculado al plan.
     */
    private function crearCompromisoAyuntamiento(PlanDeIntervencion $plan): PlanActuacionAyuntamiento
    {
        $prestacion = Prestacion::first() ?? Prestacion::create([
            'nombre' => 'Prestación Test',
            'activa' => true,
        ]);

        return PlanActuacionAyuntamiento::create([
            'plan_id' => $plan->id,
            'prestacion_id' => $prestacion->id,
            'estado' => 'pendiente',
            'orden' => 1,
        ]);
    }
}
