<?php

namespace Tests\Feature\Acceso;

use App\Enums\AccionAuditEnum;
use App\Models\AccesoProtegido;
use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Modules\Agenda\Livewire\Citas\CitaDirectaPage;
use Modules\Centro\Enums\EstadoAsignacionPendiente;
use Modules\Centro\Enums\MotivoAsignacionPendiente;
use Modules\Centro\Enums\TipoAsignacionPendiente;
use Modules\Centro\Models\AsignacionPendiente;
use Modules\Centro\Models\Centro;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Supervision\Http\Livewire\AsignacionesPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Acceso y auditoría de lectura de expedientes (TF-ACC-01 a TF-ACC-12).
 *
 * Toda apertura de ficha, expediente o plan pasa por la policy del ciudadano
 * y deja una fila en `audits`; un colectivo protegido de otra UO sin acceso
 * aprobado da 403 sin datos personales (CLAUDE.md §3). Nivel 2 (consulta
 * libre de no protegidos fuera de la UO) se mantiene: docs/modulo-usuarios-permisos.md §1.5.
 *
 * TF-ACC-11 (la descarga de documentos sigue con una fila) está en
 * Modules/Documentos/tests/Feature/AccesoDocumentoTest.php, que ya tiene la
 * preparación de la custodia.
 *
 * @see docs/instrucciones-cli/instrucciones-cli-acceso-auditoria.md
 */
class AccesoExpedienteTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uoA;

    private UnidadOrganizativa $uoB;

    /** Profesional de intervención adscrito a la UO A. */
    private User $tsA;

    /** Persona de colectivo protegido con historia solo en la UO B. */
    private Ciudadano $protegido;

    private HistoriaSocial $historiaProtegido;

    /** Persona no protegida con historia en la UO B. */
    private Ciudadano $ajeno;

    private HistoriaSocial $historiaAjeno;

    /** Persona no protegida con historia en la UO A. */
    private Ciudadano $propio;

    private HistoriaSocial $historiaPropio;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uoA = UnidadOrganizativa::create(['nombre' => 'CSS Acceso A', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);
        $this->uoB = UnidadOrganizativa::create(['nombre' => 'CSS Acceso B', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);

        $this->tsA = $this->usuarioEnUo('ts.a', 'intervencion', $this->uoA);

        // El indicador real de protección es el del ciudadano; el de la historia
        // se queda en false, como en los datos reales, para que la policy no dependa de él.
        $this->protegido = $this->crearCiudadano('Remedios', 'Escondida', ['colectivo_extra_protegido' => true]);
        $this->historiaProtegido = $this->crearHistoria($this->protegido, $this->uoB);
        $this->documento($this->protegido, '11111111H');

        $this->ajeno = $this->crearCiudadano('Pedro', 'Sáez');
        $this->historiaAjeno = $this->crearHistoria($this->ajeno, $this->uoB);

        $this->propio = $this->crearCiudadano('María', 'García');
        $this->historiaPropio = $this->crearHistoria($this->propio, $this->uoA);
    }

    // -------------------------------------------------------------------------
    // Ficha del ciudadano
    // -------------------------------------------------------------------------

    /** TF-ACC-01 — La ficha de una persona de la UO propia se abre y deja una fila `ver`. */
    #[Test]
    public function tf_acc_01_ficha_propia_se_abre_y_se_audita_como_ver(): void
    {
        $this->actingAs($this->tsA)->get($this->rutaFicha($this->propio))->assertOk();

        $filas = $this->filas($this->propio);
        $this->assertCount(1, $filas);
        $this->assertSame(AccionAuditEnum::Ver, $filas[0]->accion);
        $this->assertSame($this->tsA->id, $filas[0]->user_id);
        $this->assertSame(Ciudadano::class, $filas[0]->auditable_type);
        $this->assertSame($this->propio->id, $filas[0]->auditable_id);
        $this->assertNotNull($filas[0]->ip);
        $this->assertSame('ciudadania/ciudadano/'.$this->propio->id, $filas[0]->contexto['ruta']);
        $this->assertTrue($filas[0]->contexto['autorizado']);
    }

    /** TF-ACC-02 — Protegido de otra UO sin acceso aprobado: 403 sin datos y fila `acceso_restringido` denegada. */
    #[Test]
    public function tf_acc_02_ficha_de_protegido_ajeno_sin_aprobacion_da_403_sin_datos(): void
    {
        $this->actingAs($this->tsA)->get($this->rutaFicha($this->protegido))
            ->assertForbidden()
            ->assertDontSee('Remedios')
            ->assertDontSee('Escondida')
            ->assertDontSee('11111111H');

        $fila = $this->filaUnica($this->protegido);
        $this->assertSame(AccionAuditEnum::AccesoRestringido, $fila->accion);
        $this->assertFalse($fila->contexto['autorizado']);
        $this->assertSame('denegado', $fila->contexto['motivo']);
    }

    /** TF-ACC-03 — Con acceso aprobado y vigente: 200 y fila `acceso_restringido` autorizada con su solicitud. */
    #[Test]
    public function tf_acc_03_ficha_de_protegido_con_aprobacion_vigente_se_abre(): void
    {
        $acceso = $this->aprobarAcceso(now()->addDay());

        $this->actingAs($this->tsA)->get($this->rutaFicha($this->protegido))->assertOk()->assertSee('Remedios');

        $fila = $this->filaUnica($this->protegido);
        $this->assertSame(AccionAuditEnum::AccesoRestringido, $fila->accion);
        $this->assertTrue($fila->contexto['autorizado']);
        $this->assertSame($acceso->id, $fila->contexto['acceso_protegido_id']);
    }

    /** TF-ACC-04 — Una aprobación caducada no abre la ficha y la fila queda denegada. */
    #[Test]
    public function tf_acc_04_ficha_de_protegido_con_aprobacion_caducada_da_403(): void
    {
        $this->aprobarAcceso(now()->subDay());

        $this->actingAs($this->tsA)->get($this->rutaFicha($this->protegido))
            ->assertForbidden()
            ->assertDontSee('Remedios');

        $fila = $this->filaUnica($this->protegido);
        $this->assertSame(AccionAuditEnum::AccesoRestringido, $fila->accion);
        $this->assertFalse($fila->contexto['autorizado']);
    }

    // -------------------------------------------------------------------------
    // Expediente y plan
    // -------------------------------------------------------------------------

    /**
     * TF-ACC-05 — Sin permiso de lectura de historias, el expediente de otra UO da 403 sin datos.
     *
     * Decisión del desarrollador (2026-10-02): Nivel 2 se mantiene, así que el
     * 403 de este caso viene de la falta de `historia.leer` (tramitación), no de la UO.
     */
    #[Test]
    public function tf_acc_05_expediente_de_otra_uo_sin_permiso_de_historia_da_403(): void
    {
        $tramitador = $this->usuarioEnUo('tram.a', 'tramitacion', $this->uoA);

        $this->actingAs($tramitador)->get($this->rutaExpediente($this->historiaAjeno))
            ->assertForbidden()
            ->assertDontSee('Pedro')
            ->assertDontSee('Sáez');
    }

    /** TF-ACC-05 (Nivel 2) — Intervención abre el expediente de un no protegido de otra UO, y queda auditado. */
    #[Test]
    public function tf_acc_05_nivel_2_expediente_de_no_protegido_de_otra_uo_se_abre_y_se_audita(): void
    {
        $this->actingAs($this->tsA)->get($this->rutaExpediente($this->historiaAjeno))->assertOk();

        $fila = $this->filaUnica($this->ajeno);
        $this->assertSame(AccionAuditEnum::Ver, $fila->accion);
        $this->assertSame(HistoriaSocial::class, $fila->auditable_type);
        $this->assertSame($this->historiaAjeno->id, $fila->auditable_id);
    }

    /** TF-ACC-06 — El expediente propio deja exactamente una fila `ver`; los repintados no añaden más. */
    #[Test]
    public function tf_acc_06_expediente_propio_deja_una_sola_fila(): void
    {
        $this->actingAs($this->tsA)->get($this->rutaExpediente($this->historiaPropio))->assertOk();

        $fila = $this->filaUnica($this->propio);
        $this->assertSame(AccionAuditEnum::Ver, $fila->accion);
        $this->assertSame('intervencion/ciudadano/'.$this->historiaPropio->id, $fila->contexto['ruta']);

        // Las acciones Livewire posteriores recargan los computed, pero no son una nueva apertura
        Livewire::actingAs($this->tsA)
            ->test(CiudadanoPage::class, ['historia' => $this->historiaPropio])
            ->call('$refresh')
            ->set('filtroHS', 'plan');

        $this->assertCount(2, $this->filas($this->propio), 'El montaje es una apertura; los repintados no cuentan.');
    }

    /** TF-ACC-07 — Expediente y plan de un protegido de otra UO sin aprobación: 403 sin nombre, una fila por intento. */
    #[Test]
    public function tf_acc_07_expediente_y_plan_de_protegido_ajeno_dan_403(): void
    {
        $plan = PlanDeIntervencion::factory()->create(['historia_id' => $this->historiaProtegido->id]);

        $this->actingAs($this->tsA)->get($this->rutaExpediente($this->historiaProtegido))
            ->assertForbidden()
            ->assertDontSee('Remedios');
        $this->actingAs($this->tsA)->get(route('intervencion.plan.show', $plan))
            ->assertForbidden()
            ->assertDontSee('Remedios');

        $filas = $this->filas($this->protegido);
        $this->assertCount(2, $filas);
        foreach ($filas as $fila) {
            $this->assertSame(AccionAuditEnum::AccesoRestringido, $fila->accion);
            $this->assertFalse($fila->contexto['autorizado']);
        }
    }

    // -------------------------------------------------------------------------
    // Buscadores y bandeja
    // -------------------------------------------------------------------------

    /** TF-ACC-08 — Sin permiso de consulta de historias, «Ver Historia Social» no redirige y deja la denegación. */
    #[Test]
    public function tf_acc_08_acceso_nivel_2_sin_permiso_no_redirige(): void
    {
        $tramitador = $this->usuarioEnUo('tram.a', 'tramitacion', $this->uoA);

        Livewire::actingAs($tramitador)
            ->test(BuscarCiudadanoPage::class)
            ->call('registrarAccesoNivel2', $this->historiaAjeno->id)
            ->assertNoRedirect()
            ->assertForbidden();

        $fila = $this->filaUnica($this->ajeno);
        $this->assertSame(AccionAuditEnum::Ver, $fila->accion);
        $this->assertFalse($fila->contexto['autorizado']);
    }

    /** TF-ACC-09 — Buscar a un protegido de otra UO no devuelve su nombre ni su documento; sí la restricción. */
    #[Test]
    public function tf_acc_09_los_buscadores_no_muestran_al_protegido_ajeno(): void
    {
        // Buscador de ciudadanos (por nombre: la búsqueda por documento no está implementada en esta pantalla)
        $buscador = Livewire::actingAs($this->tsA)
            ->test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'nombre')
            ->set('query', 'Remedios')
            ->call('buscar')
            ->assertDontSee('Remedios')
            ->assertDontSee('Escondida')
            ->assertSee('Solicitar acceso');

        $resultado = $buscador->get('resultados')[0];
        $this->assertSame(3, $resultado['nivel']);
        $this->assertNull($resultado['nombre']);
        $this->assertNull($resultado['alias']);

        // Buscador de citación, por documento exacto
        $citador = $this->usuarioEnUo('cita.a', 'consulta_basica', $this->uoA);
        Centro::create([
            'nombre' => 'Centro Acceso A',
            'tipo_gestion' => 'municipal_directo',
            'unidad_organizativa_id' => $this->uoA->id,
            'fecha_alta' => now()->toDateString(),
        ]);

        $cita = Livewire::actingAs($citador)
            ->test(CitaDirectaPage::class)
            ->set('busquedaPersona', '11111111H')
            ->call('buscarPersona')
            ->assertDontSee('Remedios')
            ->assertDontSee('Escondida')
            ->assertSee('protección especial');

        $persona = $cita->get('personas')[0];
        $this->assertNull($persona['nombre']);
        $this->assertNull($persona['documento']);
    }

    /** TF-ACC-10 — La bandeja de asignaciones no muestra el nombre de un protegido fuera del ámbito del supervisor. */
    #[Test]
    public function tf_acc_10_la_bandeja_de_asignaciones_oculta_al_protegido_ajeno(): void
    {
        $supervisor = $this->usuarioEnUo('sup.a', 'supervision', $this->uoA);
        $centro = Centro::create([
            'nombre' => 'Centro Acceso A',
            'tipo_gestion' => 'municipal_directo',
            'unidad_organizativa_id' => $this->uoA->id,
            'fecha_alta' => now()->toDateString(),
        ]);

        foreach ([$this->protegido, $this->propio] as $persona) {
            AsignacionPendiente::create([
                'ciudadano_id' => $persona->id,
                'tipo' => TipoAsignacionPendiente::SinCentro,
                'tipo_centro' => 'css_general',
                'motivo' => MotivoAsignacionPendiente::SinCobertura,
                'centro_id' => $centro->id,
                'estado' => EstadoAsignacionPendiente::Pendiente,
            ]);
        }

        Livewire::actingAs($supervisor)
            ->test(AsignacionesPage::class)
            ->assertSee('María')
            ->assertDontSee('Remedios')
            ->assertDontSee('Escondida')
            ->assertSee('protección especial');
    }

    // -------------------------------------------------------------------------
    // Sin rol operativo
    // -------------------------------------------------------------------------

    /** TF-ACC-12 — Sin rol operativo la ficha da 403 sin datos y deja una fila `ver` denegada. */
    #[Test]
    public function tf_acc_12_sin_rol_operativo_la_ficha_da_403_y_se_audita(): void
    {
        $gestor = $this->usuarioEnUo('usu.a', 'adm_usuarios', $this->uoA);

        $this->actingAs($gestor)->get($this->rutaFicha($this->propio))
            ->assertForbidden()
            ->assertDontSee('María')
            ->assertDontSee('García');

        $fila = $this->filaUnica($this->propio);
        $this->assertSame(AccionAuditEnum::Ver, $fila->accion);
        $this->assertFalse($fila->contexto['autorizado']);
        $this->assertSame('denegado', $fila->contexto['motivo']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Usuario con un rol, adscrito a una UO.
     *
     * @param string $alias
     * @param string $rol
     * @param UnidadOrganizativa $uo
     * @return User
     */
    private function usuarioEnUo(string $alias, string $rol, UnidadOrganizativa $uo): User
    {
        $usuario = User::create([
            'name' => $alias,
            'email' => "{$alias}@vida360.test",
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $usuario->assignRole($rol);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        return $usuario;
    }

    /**
     * @param string $nombre
     * @param string $apellido
     * @param array<string, mixed> $attrs
     * @return Ciudadano
     */
    private function crearCiudadano(string $nombre, string $apellido, array $attrs = []): Ciudadano
    {
        return Ciudadano::create(array_merge([
            'nombre' => $nombre,
            'apellido1' => $apellido,
            'fecha_nacimiento' => '1980-01-01',
            'sexo' => 'F',
            'nivel_identificacion' => 'identificado',
            'activo' => true,
            'colectivo_extra_protegido' => false,
        ], $attrs));
    }

    /**
     * @param Ciudadano $ciudadano
     * @param UnidadOrganizativa $uo
     * @return HistoriaSocial
     */
    private function crearHistoria(Ciudadano $ciudadano, UnidadOrganizativa $uo): HistoriaSocial
    {
        return HistoriaSocial::create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);
    }

    /**
     * @param Ciudadano $ciudadano
     * @param string $valor
     * @return void
     */
    private function documento(Ciudadano $ciudadano, string $valor): void
    {
        CiudadanoIdentificador::create([
            'ciudadano_id' => $ciudadano->id,
            'tipo' => 'dni',
            'valor' => $valor,
            'fecha_inicio' => '2020-01-01',
            'verificado' => true,
            'fuente' => 'manual',
        ]);
    }

    /**
     * Acceso aprobado del profesional de la UO A a la persona protegida.
     *
     * @param \DateTimeInterface $hasta
     * @return AccesoProtegido
     */
    private function aprobarAcceso(\DateTimeInterface $hasta): AccesoProtegido
    {
        $supervisorB = $this->usuarioEnUo('sup.b', 'supervision', $this->uoB);

        return AccesoProtegido::create([
            'usuario_id' => $this->tsA->id,
            'ciudadano_id' => $this->protegido->id,
            'solicitante_id' => $this->tsA->id,
            'justificacion' => 'Coordinación por derivación del caso',
            'estado' => 'aprobado',
            'aprobado_por' => $supervisorB->id,
            'fecha_resolucion' => now()->subDays(2),
            'acceso_valido_hasta' => $hasta,
        ]);
    }

    /**
     * @param Ciudadano $ciudadano
     * @return string
     */
    private function rutaFicha(Ciudadano $ciudadano): string
    {
        return route('ciudadania.ciudadano.ficha', $ciudadano->id);
    }

    /**
     * @param HistoriaSocial $historia
     * @return string
     */
    private function rutaExpediente(HistoriaSocial $historia): string
    {
        return route('intervencion.ciudadano.show', $historia->id);
    }

    /**
     * Filas de lectura (ver o acceso restringido) de una persona, en orden.
     *
     * @param Ciudadano $ciudadano
     * @return Collection<int, Audit>
     */
    private function filas(Ciudadano $ciudadano): Collection
    {
        return Audit::where('ciudadano_id', $ciudadano->id)
            ->whereIn('accion', [AccionAuditEnum::Ver->value, AccionAuditEnum::AccesoRestringido->value])
            ->orderBy('id')
            ->get()
            ->values();
    }

    /**
     * La única fila de lectura de una persona.
     *
     * @param Ciudadano $ciudadano
     * @return Audit
     */
    private function filaUnica(Ciudadano $ciudadano): Audit
    {
        $filas = $this->filas($ciudadano);
        $this->assertCount(1, $filas, 'Se esperaba exactamente una fila de lectura en audits.');

        return $filas[0];
    }
}
