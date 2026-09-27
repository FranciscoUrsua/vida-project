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
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Livewire\AlertaToast;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\AlertaDestinatario;
use Modules\Mensajes\Services\AlertaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Toasts persistentes de alertas (paso 5 del plan de Mensajes,
 * `modulo-mensajes.md` §4.2): solo alertas pendientes del usuario,
 * reconocimiento con confirmación y enlace al origen solo si es accesible.
 * TF-MSG-TOAST-01 a 20.
 */
class AlertaToastTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create(['nombre' => 'CSS Toast', 'tipo' => 'centro', 'activa' => true]);
        $this->usuario = $this->crearUsuario('yo@vida360.test', $this->uo);
    }

    /**
     * Crea un usuario de intervención con adscripción vigente en la UO.
     */
    private function crearUsuario(string $email, UnidadOrganizativa $uo): User
    {
        $usuario = User::factory()->create(['email' => $email, 'primer_acceso' => false]);
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
     * Crea una alerta directa al usuario por el servicio.
     *
     * @param array<string, mixed> $overrides
     */
    private function crearAlerta(User $usuario, array $overrides = []): Alerta
    {
        return app(AlertaService::class)->crear(array_merge([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => User::class,
            'origen_id' => $usuario->id,
            'titulo' => 'Alerta de prueba',
            'cuerpo' => 'Cuerpo de la alerta',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuario->id,
        ], $overrides));
    }

    /** Marca HTML del toast desplegado de una alerta. */
    private function toast(Alerta $alerta): string
    {
        return 'wire:key="alerta-toast-'.$alerta->id.'"';
    }

    /** Marca HTML de una alerta en la barra de minimizadas del pie. */
    private function enBarra(Alerta $alerta): string
    {
        return 'wire:key="alerta-minimizada-'.$alerta->id.'"';
    }

    /**
     * Crea un plan sobre una Historia Social abierta en la UO indicada.
     */
    private function crearPlan(UnidadOrganizativa $uo, bool $protegido = false): PlanDeIntervencion
    {
        $ciudadano = Ciudadano::create([
            'nombre' => 'Marta',
            'apellido1' => 'Ciudadana',
            'fecha_nacimiento' => '1950-01-01',
            'sexo' => 'F',
            'nivel_identificacion' => 'identificado',
            'activo' => true,
            'colectivo_extra_protegido' => $protegido,
        ]);

        $historia = HistoriaSocial::create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        return PlanDeIntervencion::factory()->create([
            'historia_id' => $historia->id,
            'profesional_responsable_id' => $this->usuario->id,
        ]);
    }

    /** TF-MSG-TOAST-01 — Muestra las alertas pendientes del usuario. */
    #[Test]
    public function muestra_las_alertas_pendientes_del_usuario(): void
    {
        $alerta = $this->crearAlerta($this->usuario, ['titulo' => 'Revisión vencida', 'cuerpo' => 'Actualiza el plan']);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->assertSee('Revisión vencida')
            ->assertSee('Actualiza el plan')
            ->assertSet('alertaIds', [$alerta->id]);
    }

    /** TF-MSG-TOAST-02 — Los avisos no generan toast. */
    #[Test]
    public function los_avisos_no_generan_toast(): void
    {
        $this->crearAlerta($this->usuario, ['tipo' => TipoAlerta::Aviso, 'titulo' => 'Aviso informativo']);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->assertDontSee('Aviso informativo')
            ->assertSet('alertaIds', []);
    }

    /** TF-MSG-TOAST-03 — No muestra alertas de otros usuarios. */
    #[Test]
    public function no_muestra_alertas_de_otros(): void
    {
        $otro = $this->crearUsuario('otro@vida360.test', $this->uo);
        $this->crearAlerta($otro, ['titulo' => 'Alerta ajena']);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->assertDontSee('Alerta ajena')
            ->assertSet('alertaIds', []);
    }

    /** TF-MSG-TOAST-04 — Una alerta ya reconocida no vuelve a salir. */
    #[Test]
    public function alerta_reconocida_no_sale(): void
    {
        $alerta = $this->crearAlerta($this->usuario, ['titulo' => 'Ya leída']);
        app(AlertaService::class)->reconocer($alerta, $this->usuario, '127.0.0.1');

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->assertDontSee('Ya leída');
    }

    /** TF-MSG-TOAST-05 — Los toasts se ordenan por vencimiento, el más cercano primero. */
    #[Test]
    public function ordena_por_vencimiento(): void
    {
        $tardia = $this->crearAlerta($this->usuario);
        $tardia->update(['expira_en' => now()->addHours(4)]);
        $urgente = $this->crearAlerta($this->usuario);
        $urgente->update(['expira_en' => now()->addHour()]);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->assertSet('alertaIds', [$urgente->id, $tardia->id]);
    }

    /** TF-MSG-TOAST-06 — Una alerta llegada con el toast ya montado aparece en el siguiente ciclo de polling. */
    #[Test]
    public function alerta_nueva_aparece_en_el_polling(): void
    {
        $componente = Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->assertSet('alertaIds', []);

        $alerta = $this->crearAlerta($this->usuario, ['titulo' => 'Llega ahora']);

        $componente->call('$refresh')
            ->assertSee('Llega ahora')
            ->assertSet('alertaIds', [$alerta->id]);
    }

    /** TF-MSG-TOAST-07 — Reconocer exige confirmar antes: sin confirmación no se reconoce nada. */
    #[Test]
    public function reconocer_sin_confirmar_no_hace_nada(): void
    {
        $alerta = $this->crearAlerta($this->usuario);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->call('reconocer')
            ->assertNotDispatched('alerta-reconocida');

        $this->assertSame(EstadoAlerta::Pendiente, $alerta->fresh()->estado);
    }

    /** TF-MSG-TOAST-08 — Confirmar y reconocer atiende la parte del usuario, quita el toast y avisa al menú. */
    #[Test]
    public function confirmar_y_reconocer(): void
    {
        $alerta = $this->crearAlerta($this->usuario, ['titulo' => 'Para reconocer']);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->call('confirmarReconocimiento', $alerta->id)
            ->assertSee('¿Confirmas que la has leído?')
            ->call('reconocer')
            ->assertDispatched('alerta-reconocida')
            ->assertDontSee('Para reconocer')
            ->assertSet('alertaIds', []);

        $parte = AlertaDestinatario::where('alerta_id', $alerta->id)->where('usuario_id', $this->usuario->id)->first();
        $this->assertSame(EstadoAlerta::Reconocida, $parte->estado);
    }

    /** TF-MSG-TOAST-09 — No se puede reconocer desde el toast una alerta de otro usuario. */
    #[Test]
    public function no_reconoce_alerta_ajena(): void
    {
        $otro = $this->crearUsuario('otro@vida360.test', $this->uo);
        $ajena = $this->crearAlerta($otro);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->call('confirmarReconocimiento', $ajena->id)
            ->call('reconocer')
            ->assertForbidden();

        $this->assertSame(EstadoAlerta::Pendiente, $ajena->fresh()->estado);
    }

    /** TF-MSG-TOAST-10 — El toast no reconoce avisos: esos se descartan desde la bandeja. */
    #[Test]
    public function no_reconoce_avisos(): void
    {
        $aviso = $this->crearAlerta($this->usuario, ['tipo' => TipoAlerta::Aviso]);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->call('confirmarReconocimiento', $aviso->id)
            ->call('reconocer')
            ->assertForbidden();

        $this->assertSame(EstadoAlerta::Pendiente, $aviso->fresh()->estado);
    }

    /** TF-MSG-TOAST-11 — Enlace al plan de origen solo si el usuario puede verlo. */
    #[Test]
    public function enlace_al_origen_solo_si_es_accesible(): void
    {
        $plan = $this->crearPlan($this->uo);
        $this->crearAlerta($this->usuario, ['origen_type' => PlanDeIntervencion::class, 'origen_id' => $plan->id]);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->assertSee(route('intervencion.plan.show', $plan), false)
            ->assertSee('Ver origen');

        $otraUo = UnidadOrganizativa::create(['nombre' => 'CSS Lejano', 'tipo' => 'centro', 'activa' => true]);
        $planAjeno = $this->crearPlan($otraUo, protegido: true);
        Alerta::query()->delete();
        $this->crearAlerta($this->usuario, ['origen_type' => PlanDeIntervencion::class, 'origen_id' => $planAjeno->id]);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->assertDontSee(route('intervencion.plan.show', $planAjeno), false)
            ->assertDontSee('Ver origen');
    }

    /** TF-MSG-TOAST-12 — El toast está en el layout operativo, en cualquier pantalla. */
    #[Test]
    public function el_toast_esta_en_el_layout_operativo(): void
    {
        $this->crearAlerta($this->usuario, ['titulo' => 'Alerta global']);

        $this->actingAs($this->usuario)
            ->get(route('intervencion.casos.index'))
            ->assertOk()
            ->assertSeeLivewire(AlertaToast::class)
            ->assertSee('Alerta global');
    }

    /** TF-MSG-TOAST-13 — Minimizar pliega ese toast a la barra del pie y deja los demás desplegados. */
    #[Test]
    public function minimizar_pliega_el_toast_a_la_barra(): void
    {
        $minimizada = $this->crearAlerta($this->usuario, ['titulo' => 'La minimizo']);
        $otra = $this->crearAlerta($this->usuario, ['titulo' => 'Sigue a la vista']);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->assertDontSee('alertas sin reconocer')
            ->call('minimizar', $minimizada->id)
            ->assertDontSeeHtml($this->toast($minimizada))
            ->assertSeeHtml($this->enBarra($minimizada))
            ->assertSee('La minimizo')
            ->assertSee('1 alerta sin reconocer')
            ->assertSeeHtml($this->toast($otra))
            ->assertDontSeeHtml($this->enBarra($otra));
    }

    /** TF-MSG-TOAST-14 — Minimizar no reconoce la alerta: sigue pendiente y en la bandeja. */
    #[Test]
    public function minimizar_no_reconoce(): void
    {
        $alerta = $this->crearAlerta($this->usuario);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->call('minimizar', $alerta->id)
            ->assertNotDispatched('alerta-reconocida')
            ->assertSet('alertaIds', [$alerta->id]);

        $this->assertSame(EstadoAlerta::Pendiente, $alerta->fresh()->estado);
    }

    /** TF-MSG-TOAST-15 — El toast minimizado se despliega solo a los 30 minutos, no antes. */
    #[Test]
    public function minimizado_se_despliega_a_los_30_minutos(): void
    {
        $alerta = $this->crearAlerta($this->usuario);

        $componente = Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->call('minimizar', $alerta->id)
            ->assertDontSeeHtml($this->toast($alerta));

        $this->travel(29)->minutes();
        $componente->call('$refresh')
            ->assertDontSeeHtml($this->toast($alerta))
            ->assertSeeHtml($this->enBarra($alerta));

        $this->travel(2)->minutes();
        $componente->call('$refresh')
            ->assertSeeHtml($this->toast($alerta))
            ->assertDontSeeHtml($this->enBarra($alerta));
    }

    /** TF-MSG-TOAST-16 — Minimizar una alerta de colectivo no la pliega a los demás destinatarios. */
    #[Test]
    public function minimizar_no_afecta_a_otro_usuario(): void
    {
        $otro = $this->crearUsuario('otro@vida360.test', $this->uo);
        $alerta = app(AlertaService::class)->crear([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => UnidadOrganizativa::class,
            'origen_id' => $this->uo->id,
            'titulo' => 'Alerta al equipo',
            'cuerpo' => 'Para todo el colectivo',
            'destinatario_type' => DestinatarioType::RolUo,
            'destinatario_rol' => 'intervencion',
            'destinatario_uo_id' => $this->uo->id,
        ]);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->call('minimizar', $alerta->id)
            ->assertDontSeeHtml($this->toast($alerta));

        // Misma sesión, otro usuario
        Livewire::actingAs($otro)
            ->test(AlertaToast::class)
            ->assertSeeHtml($this->toast($alerta))
            ->assertDontSeeHtml($this->enBarra($alerta));
    }

    /** TF-MSG-TOAST-17 — Con más de 3 alertas se resumen las demás, y «Minimizar todas» las lleva todas a la barra. */
    #[Test]
    public function resume_las_que_no_caben_y_minimiza_todas(): void
    {
        $alertas = collect(range(1, 5))->map(fn (int $n) => $this->crearAlerta($this->usuario, ['titulo' => "Alerta número {$n}"]));

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->assertSeeHtml($this->toast($alertas[0]))
            ->assertDontSeeHtml($this->toast($alertas[3]))
            ->assertSeeHtml('<strong>2</strong> alertas pendientes más')
            ->call('minimizarTodas')
            ->assertDontSeeHtml('wire:key="alerta-toast-')
            ->assertDontSee('pendientes más')
            ->assertSee('5 alertas sin reconocer')
            ->assertSee('y 2 más');
    }

    /** TF-MSG-TOAST-18 — Pulsar una alerta de la barra la vuelve a desplegar al momento. */
    #[Test]
    public function restaurar_despliega_la_alerta(): void
    {
        $alerta = $this->crearAlerta($this->usuario);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->call('minimizar', $alerta->id)
            ->call('restaurar', $alerta->id)
            ->assertSeeHtml($this->toast($alerta))
            ->assertDontSeeHtml($this->enBarra($alerta))
            ->assertDontSee('sin reconocer');
    }

    /** TF-MSG-TOAST-19 — «Mostrar» despliega todas las minimizadas. */
    #[Test]
    public function restaurar_todas(): void
    {
        $a = $this->crearAlerta($this->usuario);
        $b = $this->crearAlerta($this->usuario);

        Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->call('minimizarTodas')
            ->call('restaurarTodas')
            ->assertSeeHtml($this->toast($a))
            ->assertSeeHtml($this->toast($b))
            ->assertDontSee('sin reconocer');
    }

    /** TF-MSG-TOAST-20 — Una alerta minimizada reconocida desde la bandeja desaparece también de la barra. */
    #[Test]
    public function minimizada_reconocida_sale_de_la_barra(): void
    {
        $alerta = $this->crearAlerta($this->usuario);

        $componente = Livewire::actingAs($this->usuario)
            ->test(AlertaToast::class)
            ->call('minimizar', $alerta->id)
            ->assertSeeHtml($this->enBarra($alerta));

        app(AlertaService::class)->reconocer($alerta, $this->usuario, '127.0.0.1');

        $componente->dispatch('alerta-reconocida')
            ->assertDontSeeHtml($this->enBarra($alerta))
            ->assertDontSee('sin reconocer');
    }
}
