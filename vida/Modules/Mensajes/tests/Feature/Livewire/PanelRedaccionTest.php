<?php

namespace Modules\Mensajes\Tests\Feature\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Mensajes\Livewire\HiloMensajes;
use Modules\Mensajes\Livewire\PanelRedaccion;
use Modules\Mensajes\Models\MensajeHilo;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Panel de redacción flotante global (paso 4 del plan de Mensajes,
 * `modulo-mensajes.md` §4.3): contexto, chip de destinatario sugerido sin
 * confirmar, búsqueda de destinatarios y referencias a ciudadanos accesibles.
 * TF-MSG-PAN-01 a 18.
 */
class PanelRedaccionTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    private UnidadOrganizativa $otraUo;

    private User $usuario;

    private User $tsr;

    private Ciudadano $ciudadano;

    private HistoriaSocial $historia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create(['nombre' => 'CSS Panel', 'tipo' => 'centro', 'activa' => true]);
        $this->otraUo = UnidadOrganizativa::create(['nombre' => 'CSS Lejano', 'tipo' => 'centro', 'activa' => true]);

        $this->usuario = $this->crearUsuario('yo@vida360.test', $this->uo, 'Remitente', 'Prueba');
        $this->tsr = $this->crearUsuario('tsr@vida360.test', $this->uo, 'Teresa', 'Referente');

        $this->ciudadano = $this->crearCiudadano('Marta', 'Ciudadana');
        $this->historia = $this->crearHistoria($this->ciudadano, $this->uo);
        AsignacionProfesional::create([
            'historia_id' => $this->historia->id,
            'profesional_id' => $this->tsr->id,
            'fecha_inicio' => today()->subMonth()->toDateString(),
        ]);
    }

    /**
     * Crea un usuario de intervención con ficha de profesional y adscripción vigente.
     */
    private function crearUsuario(string $email, UnidadOrganizativa $uo, string $nombre, string $apellido, ?Cargo $cargo = null): User
    {
        $cargo ??= Cargo::firstOrCreate(['nombre' => 'Trabajador/a social'], ['slug' => 'ts', 'activo' => true]);

        $tipoRelacion = TipoRelacionProfesional::firstOrCreate(
            ['nombre' => 'Funcionario/a de carrera'],
            ['es_externo' => false, 'activo' => true]
        );

        $profesional = Profesional::create([
            'nombre' => $nombre,
            'apellido1' => $apellido,
            'sexo' => 'F',
            'cargo_id' => $cargo->id,
            'tipo_relacion_id' => $tipoRelacion->id,
            'fecha_inicio' => today(),
            'activo' => true,
        ]);

        $usuario = User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
            'profesional_id' => $profesional->id,
        ]);
        $usuario->syncRoles(['intervencion']);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->subYear()->toDateString(),
        ]);

        return $usuario;
    }

    /**
     * Crea un ciudadano con los campos mínimos.
     */
    private function crearCiudadano(string $nombre, string $apellido, bool $protegido = false): Ciudadano
    {
        return Ciudadano::create([
            'nombre' => $nombre,
            'apellido1' => $apellido,
            'fecha_nacimiento' => '1980-01-01',
            'sexo' => 'F',
            'nivel_identificacion' => 'identificado',
            'activo' => true,
            'colectivo_extra_protegido' => $protegido,
        ]);
    }

    /**
     * Crea una Historia Social abierta del ciudadano en la UO.
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

    // -------------------------------------------------------------------------
    // Apertura y contexto
    // -------------------------------------------------------------------------

    /** TF-MSG-PAN-01 — Sin contexto, el panel se abre con todos los campos vacíos. */
    #[Test]
    public function abrir_sin_contexto_deja_campos_vacios(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->assertSet('abierto', false)
            ->dispatch('abrir-panel-redaccion')
            ->assertSet('abierto', true)
            ->assertSet('contexto', null)
            ->assertSet('destinatarioSugerido', null)
            ->assertSet('destinatarioConfirmado', null)
            ->assertSet('asunto', '')
            ->assertSet('cuerpo', '')
            ->assertSet('ciudadanoIds', []);
    }

    /** TF-MSG-PAN-02 — Con el expediente como contexto: elemento vinculado, ciudadano y TSR sugerido sin confirmar. */
    #[Test]
    public function abrir_con_expediente_prerrellena_y_sugiere_tsr(): void
    {
        $panel = Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->dispatch('abrir-panel-redaccion', contexto: ['tipo' => 'historia', 'id' => $this->historia->id]);

        $panel->assertSet('contexto.tipo', 'historia')
            ->assertSet('contexto.id', $this->historia->id)
            ->assertSet('ciudadanoIds', [$this->ciudadano->id])
            ->assertSet('destinatarioSugerido.id', $this->tsr->id)
            ->assertSet('destinatarioConfirmado', null)
            ->assertSee('Teresa Referente')
            ->assertSee('Sin confirmar');
    }

    /** TF-MSG-PAN-03 — Con una ficha de valoración, sugiere a quien la cumplimentó. */
    #[Test]
    public function abrir_con_ficha_sugiere_su_autor(): void
    {
        $autor = $this->crearUsuario('autor@vida360.test', $this->uo, 'Alberto', 'Autor');
        $ficha = Ficha::factory()->create(['historia_id' => $this->historia->id, 'profesional_id' => $autor->id]);

        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->dispatch('abrir-panel-redaccion', contexto: ['tipo' => 'ficha', 'id' => $ficha->id])
            ->assertSet('contexto.tipo', 'ficha')
            ->assertSet('destinatarioSugerido.id', $autor->id)
            ->assertSet('ciudadanoIds', [$this->ciudadano->id]);
    }

    /** TF-MSG-PAN-04 — Con un plan de intervención, sugiere a su responsable. */
    #[Test]
    public function abrir_con_plan_sugiere_responsable(): void
    {
        $responsable = $this->crearUsuario('resp@vida360.test', $this->uo, 'Rosa', 'Responsable');
        $plan = PlanDeIntervencion::factory()->create([
            'historia_id' => $this->historia->id,
            'profesional_responsable_id' => $responsable->id,
        ]);

        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->dispatch('abrir-panel-redaccion', contexto: ['tipo' => 'plan', 'id' => $plan->id])
            ->assertSet('contexto.tipo', 'plan')
            ->assertSet('destinatarioSugerido.id', $responsable->id);
    }

    /** TF-MSG-PAN-05 — Un contexto al que el usuario no tiene acceso se ignora. */
    #[Test]
    public function contexto_sin_acceso_se_ignora(): void
    {
        $protegido = $this->crearCiudadano('Oculta', 'Protegida', true);
        $historiaAjena = $this->crearHistoria($protegido, $this->otraUo);

        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->dispatch('abrir-panel-redaccion', contexto: ['tipo' => 'historia', 'id' => $historiaAjena->id])
            ->assertSet('abierto', true)
            ->assertSet('contexto', null)
            ->assertSet('ciudadanoIds', [])
            ->assertSet('destinatarioSugerido', null);
    }

    // -------------------------------------------------------------------------
    // Destinatario
    // -------------------------------------------------------------------------

    /** TF-MSG-PAN-06 — Con el destinatario solo sugerido, el envío falla la validación. */
    #[Test]
    public function sugerencia_sin_confirmar_no_se_envia(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->dispatch('abrir-panel-redaccion', contexto: ['tipo' => 'historia', 'id' => $this->historia->id])
            ->set('asunto', 'Consulta')
            ->set('cuerpo', 'Texto')
            ->call('enviar')
            ->assertHasErrors(['destinatario']);

        $this->assertSame(0, MensajeHilo::count());
    }

    /** TF-MSG-PAN-07 — Al confirmar el chip se envía, con el elemento vinculado y el ciudadano, y se cierra. */
    #[Test]
    public function confirmar_sugerencia_permite_enviar(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->dispatch('abrir-panel-redaccion', contexto: ['tipo' => 'historia', 'id' => $this->historia->id])
            ->call('confirmarSugerencia')
            ->assertSet('destinatarioConfirmado.id', $this->tsr->id)
            ->set('asunto', 'Consulta sobre el caso')
            ->set('cuerpo', 'Texto del mensaje')
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSet('abierto', false)
            ->assertDispatched('hilo-creado');

        $hilo = MensajeHilo::sole();
        $this->assertSame('historia', $hilo->contexto_tipo);
        $this->assertSame($this->historia->id, $hilo->contexto_id);
        $this->assertTrue($hilo->tieneParticipante($this->tsr->id));
        $this->assertDatabaseHas('mensajes_referencias_ciudadano', ['ciudadano_id' => $this->ciudadano->id]);
    }

    /** TF-MSG-PAN-08 — Se puede descartar la sugerencia y elegir a otra persona. */
    #[Test]
    public function sustituir_sugerencia(): void
    {
        $otro = $this->crearUsuario('otro@vida360.test', $this->uo, 'Olga', 'Otra');

        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->dispatch('abrir-panel-redaccion', contexto: ['tipo' => 'historia', 'id' => $this->historia->id])
            ->call('quitarDestinatario')
            ->assertSet('destinatarioSugerido', null)
            ->call('seleccionarDestinatario', $otro->id)
            ->assertSet('destinatarioConfirmado.id', $otro->id);
    }

    /** TF-MSG-PAN-09 — Busca destinatarios por nombre y muestra su UO; no se incluye a uno mismo. */
    #[Test]
    public function busca_destinatario_por_nombre(): void
    {
        $this->crearUsuario('candela@vida360.test', $this->uo, 'Candela', 'Buscada');

        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->dispatch('abrir-panel-redaccion')
            ->set('busquedaDestinatario', 'Candela')
            ->assertSee('Candela Buscada')
            ->assertSee('CSS Panel')
            ->set('busquedaDestinatario', 'Remitente')
            ->assertDontSee('Remitente Prueba');
    }

    /** TF-MSG-PAN-10 — Si no se conoce el nombre, se filtra por cargo y UO (sustituye a T-LW-09). */
    #[Test]
    public function filtra_destinatarios_por_cargo_y_uo(): void
    {
        $educador = Cargo::create(['nombre' => 'Educador/a social', 'slug' => 'educadorsocial', 'activo' => true]);
        $this->crearUsuario('edu1@vida360.test', $this->uo, 'Eva', 'Educadora', $educador);
        $this->crearUsuario('edu2@vida360.test', $this->otraUo, 'Elena', 'Lejana', $educador);

        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->dispatch('abrir-panel-redaccion')
            ->set('filtroCargoId', $educador->id)
            ->set('filtroUoId', $this->uo->id)
            ->assertSee('Eva Educadora')
            ->assertDontSee('Elena Lejana')
            ->assertDontSee('Teresa Referente');
    }

    /** TF-MSG-PAN-11 — No se puede enviar un mensaje a uno mismo. */
    #[Test]
    public function no_se_envia_a_uno_mismo(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->call('seleccionarDestinatario', $this->usuario->id)
            ->assertSet('destinatarioConfirmado', null);
    }

    /** TF-MSG-PAN-12 — Asunto y cuerpo son obligatorios. */
    #[Test]
    public function asunto_y_cuerpo_obligatorios(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->call('seleccionarDestinatario', $this->tsr->id)
            ->call('enviar')
            ->assertHasErrors(['asunto', 'cuerpo']);
    }

    // -------------------------------------------------------------------------
    // Ciudadanos referenciados
    // -------------------------------------------------------------------------

    /** TF-MSG-PAN-13 — La búsqueda de ciudadanos solo devuelve los que el usuario puede ver. */
    #[Test]
    public function busqueda_ciudadanos_respeta_acceso(): void
    {
        $protegida = $this->crearCiudadano('Martina', 'Protegida', true);
        $this->crearHistoria($protegida, $this->otraUo);

        $panel = Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->set('busquedaCiudadano', 'Mart');

        $ids = $panel->instance()->resultadosCiudadano->pluck('id');
        $this->assertTrue($ids->contains($this->ciudadano->id));
        $this->assertFalse($ids->contains($protegida->id));
    }

    /** TF-MSG-PAN-14 — No se puede añadir por id un ciudadano sin acceso (404: ni se revela que existe). */
    #[Test]
    public function no_se_agrega_ciudadano_sin_acceso(): void
    {
        $protegida = $this->crearCiudadano('Martina', 'Protegida', true);
        $this->crearHistoria($protegida, $this->otraUo);

        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->call('agregarCiudadano', $protegida->id)
            ->assertNotFound();
    }

    /** TF-MSG-PAN-15 — Las referencias y el contexto no se pueden cambiar desde el navegador. */
    #[Test]
    public function referencias_y_contexto_bloqueados(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->set('ciudadanoIds', [999]);
    }

    // -------------------------------------------------------------------------
    // Integración
    // -------------------------------------------------------------------------

    /** TF-MSG-PAN-16 — El panel está en el layout operativo. */
    #[Test]
    public function panel_en_el_layout(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('intervencion.mensajes.index', 'mensajes'))
            ->assertOk()
            ->assertSeeLivewire(PanelRedaccion::class);
    }

    /** TF-MSG-PAN-17 — El expediente tiene el botón «Escribir mensaje» con su contexto. */
    #[Test]
    public function expediente_tiene_boton_escribir_mensaje(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('intervencion.ciudadano.show', $this->historia))
            ->assertOk()
            ->assertSee('Escribir mensaje')
            ->assertSee("tipo: 'historia', id: {$this->historia->id}", false);
    }

    /** TF-MSG-PAN-18 — El hilo enlaza el elemento vinculado si quien lo lee puede verlo. */
    #[Test]
    public function hilo_muestra_elemento_vinculado(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(PanelRedaccion::class)
            ->dispatch('abrir-panel-redaccion', contexto: ['tipo' => 'historia', 'id' => $this->historia->id])
            ->call('confirmarSugerencia')
            ->set('asunto', 'Consulta')
            ->set('cuerpo', 'Texto')
            ->call('enviar');

        Livewire::actingAs($this->tsr)
            ->test(HiloMensajes::class, ['hiloId' => MensajeHilo::sole()->id])
            ->assertSee('Historia Social #'.$this->historia->id)
            ->assertSee(route('intervencion.ciudadano.show', $this->historia), false);
    }
}
