<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use LogicException;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Enums\TipoReconocimiento;
use Modules\Mensajes\Http\Livewire\BuzonPage;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\AlertaDestinatario;
use Modules\Mensajes\Services\AlertaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Reconocimiento por destinatario: cada destinatario de una alerta o aviso
 * dirigido a un colectivo (rol en una UO) debe reconocerlo o cerrarlo por
 * su cuenta. Decisión del desarrollador de 2026-09-26. TF-MSG-DEST-01 a 13.
 */
class AlertaDestinatariosTest extends TestCase
{
    use RefreshDatabase;

    private AlertaService $servicio;

    private UnidadOrganizativa $uo;

    private User $ts1;

    private User $ts2;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->servicio = app(AlertaService::class);
        $this->uo = UnidadOrganizativa::create(['nombre' => 'CSS Dest', 'tipo' => 'centro', 'activa' => true]);

        $this->ts1 = $this->crearUsuario('ts1@vida360.test', 'intervencion', $this->uo);
        $this->ts2 = $this->crearUsuario('ts2@vida360.test', 'intervencion', $this->uo);
        $this->supervisor = $this->crearUsuario('sup@vida360.test', 'supervision', $this->uo);
    }

    /**
     * Crea un usuario con un rol y adscripción vigente a una UO.
     */
    private function crearUsuario(string $email, string $rol, UnidadOrganizativa $uo): User
    {
        $usuario = User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $usuario->assignRole($rol);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->subYear()->toDateString(),
        ]);

        return $usuario;
    }

    /**
     * Crea por el servicio una alerta o aviso dirigido a un rol en la UO de prueba.
     */
    private function crearParaColectivo(TipoAlerta $tipo = TipoAlerta::Alerta, string $rol = 'intervencion'): Alerta
    {
        return $this->servicio->crear([
            'tipo' => $tipo,
            'origen_type' => User::class,
            'origen_id' => $this->supervisor->id,
            'titulo' => 'Para el colectivo',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::RolUo,
            'destinatario_rol' => $rol,
            'destinatario_uo_id' => $this->uo->id,
        ]);
    }

    /**
     * Estado del destinatario de una alerta.
     */
    private function estadoDe(Alerta $alerta, User $usuario): EstadoAlerta
    {
        return AlertaDestinatario::where('alerta_id', $alerta->id)
            ->where('usuario_id', $usuario->id)
            ->sole()
            ->estado;
    }

    /** TF-MSG-DEST-01 — Una alerta a un colectivo crea un destinatario pendiente por cada miembro, y solo por ellos. */
    #[Test]
    public function crear_para_colectivo_crea_un_destinatario_por_miembro(): void
    {
        $otraUo = UnidadOrganizativa::create(['nombre' => 'Otra', 'tipo' => 'centro', 'activa' => true]);
        $tsAjeno = $this->crearUsuario('ajeno@vida360.test', 'intervencion', $otraUo);

        $alerta = $this->crearParaColectivo();

        $ids = $alerta->destinatarios()->pluck('usuario_id');
        $this->assertEqualsCanonicalizing([$this->ts1->id, $this->ts2->id], $ids->all());
        $this->assertFalse($ids->contains($tsAjeno->id));
        $this->assertSame(0, $alerta->destinatarios()->where('estado', '!=', EstadoAlerta::Pendiente->value)->count());
    }

    /** TF-MSG-DEST-02 — Una alerta directa crea un único destinatario. */
    #[Test]
    public function crear_directa_crea_un_destinatario(): void
    {
        $alerta = $this->servicio->crear([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => User::class,
            'origen_id' => $this->supervisor->id,
            'titulo' => 'Directa',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $this->ts1->id,
        ]);

        $this->assertSame([$this->ts1->id], $alerta->destinatarios()->pluck('usuario_id')->all());
    }

    /** TF-MSG-DEST-03 — Que uno reconozca no cierra la alerta para los demás. */
    #[Test]
    public function reconocer_uno_no_cierra_para_los_demas(): void
    {
        $alerta = $this->crearParaColectivo();

        $this->servicio->reconocer($alerta, $this->ts1, '10.0.0.1');

        $this->assertSame(EstadoAlerta::Reconocida, $this->estadoDe($alerta, $this->ts1));
        $this->assertSame(EstadoAlerta::Pendiente, $this->estadoDe($alerta, $this->ts2));
        $this->assertSame(EstadoAlerta::Pendiente, $alerta->fresh()->estado);
        $this->assertFalse(Alerta::pendientesPara($this->ts1)->whereKey($alerta->id)->exists());
        $this->assertTrue(Alerta::pendientesPara($this->ts2)->whereKey($alerta->id)->exists());
    }

    /** TF-MSG-DEST-04 — Cuando todos reconocen, la alerta queda reconocida. */
    #[Test]
    public function cuando_todos_reconocen_la_alerta_queda_reconocida(): void
    {
        $alerta = $this->crearParaColectivo();

        $this->servicio->reconocer($alerta, $this->ts1, '10.0.0.1');
        $this->servicio->reconocer($alerta, $this->ts2, '10.0.0.2');

        $this->assertSame(EstadoAlerta::Reconocida, $alerta->fresh()->estado);
    }

    /** TF-MSG-DEST-05 — Descartar un aviso de colectivo no se lo quita a los demás (también desde el buzón). */
    #[Test]
    public function descartar_aviso_no_lo_quita_a_los_demas(): void
    {
        $aviso = $this->crearParaColectivo(TipoAlerta::Aviso);

        Livewire::actingAs($this->ts1)
            ->test(BuzonPage::class)
            ->call('reconocerAlerta', $aviso->id);

        $this->assertDatabaseHas('alerta_reconocimientos', [
            'alerta_id' => $aviso->id,
            'usuario_id' => $this->ts1->id,
            'tipo' => TipoReconocimiento::Descartada->value,
        ]);

        $avisosTs2 = Livewire::actingAs($this->ts2)->test(BuzonPage::class)->instance()->avisos;
        $this->assertTrue($avisosTs2->contains('id', $aviso->id));
    }

    /** TF-MSG-DEST-06 — Un destinatario no puede reconocer dos veces. */
    #[Test]
    public function no_se_puede_reconocer_dos_veces(): void
    {
        $alerta = $this->crearParaColectivo();
        $this->servicio->reconocer($alerta, $this->ts1, '10.0.0.1');

        $this->expectException(LogicException::class);

        $this->servicio->reconocer($alerta, $this->ts1, '10.0.0.1');
    }

    /** TF-MSG-DEST-07 — Quien no es destinatario no puede reconocer. */
    #[Test]
    public function quien_no_es_destinatario_no_puede_reconocer(): void
    {
        $alerta = $this->crearParaColectivo();

        try {
            $this->servicio->reconocer($alerta, $this->supervisor, '10.0.0.1');
            $this->fail('Se esperaba LogicException');
        } catch (LogicException) {
            // Rechazo esperado.
        }

        $this->assertDatabaseMissing('alerta_reconocimientos', ['alerta_id' => $alerta->id]);
    }

    /** TF-MSG-DEST-08 — Los destinatarios se fijan al crear: quien llega después a la UO no la recibe. */
    #[Test]
    public function quien_llega_despues_no_la_recibe(): void
    {
        $alerta = $this->crearParaColectivo();
        $nuevo = $this->crearUsuario('nuevo@vida360.test', 'intervencion', $this->uo);

        $this->assertFalse(Alerta::visiblesPara($nuevo)->whereKey($alerta->id)->exists());
    }

    /** TF-MSG-DEST-09 — La escalada es por destinatario: solo escala quien no reconoció, y el supervisor la ve. */
    #[Test]
    public function escalada_solo_de_quien_no_reconocio(): void
    {
        $alerta = $this->crearParaColectivo();
        $this->servicio->reconocer($alerta, $this->ts1, '10.0.0.1');

        $this->servicio->escalar($alerta->fresh());

        $this->assertSame(EstadoAlerta::Reconocida, $this->estadoDe($alerta, $this->ts1));
        $this->assertSame(EstadoAlerta::Escalada, $this->estadoDe($alerta, $this->ts2));

        $escaladas = AlertaDestinatario::escaladasA($this->supervisor)->get();
        $this->assertCount(1, $escaladas);
        $this->assertSame($this->ts2->id, $escaladas->first()->usuario_id);
        $this->assertSame(EstadoAlerta::Escalada, $alerta->fresh()->estado);
    }

    /** TF-MSG-DEST-10 — Varias escaladas de la misma alerta al mismo supervisor no chocan. */
    #[Test]
    public function varias_escaladas_al_mismo_supervisor(): void
    {
        $alerta = $this->crearParaColectivo();

        $this->servicio->escalar($alerta);

        $this->assertSame(2, AlertaDestinatario::escaladasA($this->supervisor)->count());
        $this->assertSame(2, DB::table('alerta_reconocimientos')
            ->where('alerta_id', $alerta->id)
            ->where('usuario_id', $this->supervisor->id)
            ->where('tipo', TipoReconocimiento::Escalada->value)
            ->count());
    }

    /** TF-MSG-DEST-11 — Un supervisor destinatario no se escala a sí mismo: su parte vence. */
    #[Test]
    public function supervisor_destinatario_no_se_escala_a_si_mismo(): void
    {
        $alerta = $this->crearParaColectivo(TipoAlerta::Alerta, 'supervision');

        $this->servicio->escalar($alerta);

        $this->assertSame(EstadoAlerta::Vencida, $this->estadoDe($alerta, $this->supervisor));
        $this->assertSame(0, AlertaDestinatario::escaladasA($this->supervisor)->count());
    }

    /** TF-MSG-DEST-12 — Una alerta a un colectivo vacío queda vencida y deja aviso en el log. */
    #[Test]
    public function colectivo_vacio_queda_vencida(): void
    {
        Log::spy();

        $alerta = $this->crearParaColectivo(TipoAlerta::Alerta, 'adm_sistema');

        $this->assertSame(0, $alerta->destinatarios()->count());
        $this->assertSame(EstadoAlerta::Vencida, $alerta->fresh()->estado);
        Log::shouldHaveReceived('warning')->once();
    }

    /** TF-MSG-DEST-13 — La migración crea los destinatarios de las alertas anteriores a este modelo. */
    #[Test]
    public function migracion_puebla_destinatarios_de_alertas_previas(): void
    {
        $base = [
            'origen_type' => User::class, 'origen_id' => 1, 'titulo' => 'Previa', 'cuerpo' => 'Cuerpo',
            'created_at' => now(), 'updated_at' => now(),
        ];
        // Filas como las dejaba el código anterior: sin destinatarios.
        $directa = DB::table('alertas')->insertGetId($base + [
            'tipo' => 'alerta', 'destinatario_type' => 'usuario', 'destinatario_usuario_id' => $this->ts1->id,
            'estado' => 'reconocida',
        ]);
        DB::table('alerta_reconocimientos')->insert([
            'alerta_id' => $directa, 'usuario_id' => $this->ts1->id, 'tipo' => 'reconocida', 'reconocida_en' => now(),
        ]);
        $colectivoPendiente = DB::table('alertas')->insertGetId($base + [
            'tipo' => 'aviso', 'destinatario_type' => 'rol_uo', 'destinatario_rol' => 'intervencion',
            'destinatario_uo_id' => $this->uo->id, 'estado' => 'pendiente',
        ]);
        $colectivoCerrado = DB::table('alertas')->insertGetId($base + [
            'tipo' => 'alerta', 'destinatario_type' => 'rol_uo', 'destinatario_rol' => 'intervencion',
            'destinatario_uo_id' => $this->uo->id, 'estado' => 'reconocida',
        ]);
        DB::table('alerta_reconocimientos')->insert([
            'alerta_id' => $colectivoCerrado, 'usuario_id' => $this->ts2->id, 'tipo' => 'reconocida', 'reconocida_en' => now(),
        ]);

        $migracion = require base_path('Modules/Mensajes/database/migrations/2026_09_26_100003_poblar_alerta_destinatarios.php');
        $migracion->poblar();
        $migracion->poblar(); // idempotente

        $filas = fn (int $id) => AlertaDestinatario::where('alerta_id', $id)->orderBy('usuario_id')->get();

        $this->assertSame([[$this->ts1->id, EstadoAlerta::Reconocida]], $filas($directa)->map(fn ($d) => [$d->usuario_id, $d->estado])->all());
        $this->assertNotNull($filas($directa)->first()->atendida_en);
        $this->assertEqualsCanonicalizing([$this->ts1->id, $this->ts2->id], $filas($colectivoPendiente)->pluck('usuario_id')->all());
        $this->assertSame([[$this->ts2->id, EstadoAlerta::Reconocida]], $filas($colectivoCerrado)->map(fn ($d) => [$d->usuario_id, $d->estado])->all());
        $this->assertSame(0, DB::table('alerta_reconocimientos')->whereNull('alerta_destinatario_id')->count());
    }
}
