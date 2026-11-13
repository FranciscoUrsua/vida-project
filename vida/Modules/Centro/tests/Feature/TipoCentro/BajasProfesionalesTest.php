<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use App\Filament\Resources\ProfesionalResource\Pages\ListProfesionales;
use App\Filament\Resources\UsuarioResource\Pages\EditUsuario;
use App\Filament\Resources\UsuarioResource\Pages\ListUsuarios;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LogicException;
use Modules\Agenda\Database\Factories\CitaFactory;
use Modules\Agenda\Database\Factories\SlotFactory;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Services\Asignacion\ActividadCasosService;
use Modules\Intervencion\Services\Asignacion\RepartoCasosService;
use Modules\Intervencion\Services\Asignacion\SalidaCentroBloqueada;
use Modules\Intervencion\Services\Asignacion\SalidaCentroService;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo K — Bajas de profesionales (TF-TCE-55 a 59; addenda D5, RN-20 a 23).
 *
 * Restricción de dominio: los casos son del centro, no del profesional. Nadie
 * se borra, nadie sale de un centro con casos o citas pendientes en él y ningún
 * cambio de adscripción mueve casos.
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class BajasProfesionalesTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /**
     * Escenario de Tipo de Centro con la fecha de referencia de los tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 10:00');
        $this->montarTipoCentro();
    }

    /**
     * Historia en la UO de un centro con la referencia vigente de un profesional.
     *
     * @param Centro $centro
     * @param User $profesional
     *
     * @return HistoriaSocial
     */
    private function casoDe(Centro $centro, User $profesional): HistoriaSocial
    {
        $persona = Ciudadano::factory()->create();
        $historia = HistoriaSocial::create([
            'ciudadano_id' => $persona->id,
            'unidad_organizativa_id' => $centro->unidad_organizativa_id,
            'estado' => 'abierta',
        ]);

        AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $profesional->id,
            'centro_id' => $centro->id,
            'fecha_inicio' => '2026-01-01',
        ]);

        return $historia;
    }

    /**
     * Adscripción vigente de la psicóloga a una UO.
     *
     * @param int $uoId
     *
     * @return UsuarioUo
     */
    private function adscripcionDeLaPsicologa(int $uoId): UsuarioUo
    {
        return UsuarioUo::where('usuario_id', $this->psicologa->id)->where('unidad_organizativa_id', $uoId)->firstOrFail();
    }

    /**
     * Profesional del centro que puede recibir casos en el reparto (cargo de
     * referencia y perfil horario activo).
     *
     * @param Centro $centro
     *
     * @return User
     */
    private function destinoDelReparto(Centro $centro): User
    {
        $profesional = Profesional::create([
            'nombre' => 'Destino',
            'apellido1' => 'Prueba',
            'sexo' => 'F',
            'cargo_id' => Cargo::create(['nombre' => 'Psicólogo/a', 'slug' => 'psicologo_bajas', 'activo' => true, 'puede_ser_referencia' => true])->id,
            'tipo_relacion_id' => TipoRelacionProfesional::firstOrCreate(['nombre' => 'Funcionario/a de carrera'], ['es_externo' => false, 'activo' => true])->id,
            'fecha_inicio' => '2020-01-01',
            'activo' => true,
        ]);
        $destino = $this->usuario('destino', 'intervencion', $centro->unidadOrganizativa, $profesional);

        // La adscripción puede haberle dado ya el perfil por defecto del centro
        PerfilHorarioProfesional::where('usuario_id', $destino->id)->where('centro_id', $centro->id)->where('activo', true)->exists()
            || PerfilHorarioProfesional::factory()->create([
                'usuario_id' => $destino->id,
                'centro_id' => $centro->id,
                'jornada_semanal_horas' => 35,
                'vigente_desde' => '2025-01-01',
            ]);

        return $destino;
    }

    /** TF-TCE-55 — Ni usuarios ni profesionales se borran; «Desactivar» quita el acceso. */
    #[Test]
    public function tf_tce_55_no_se_borra_y_desactivar_quita_el_acceso(): void
    {
        $profesional = Profesional::create([
            'nombre' => 'Sin',
            'apellido1' => 'Cuenta',
            'sexo' => 'F',
            'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Auxiliar'], ['activo' => true])->id,
            'tipo_relacion_id' => TipoRelacionProfesional::firstOrCreate(['nombre' => 'Funcionario/a de carrera'], ['es_externo' => false, 'activo' => true])->id,
            'fecha_inicio' => '2020-01-01',
            'activo' => true,
            'unidad_organizativa_id' => $this->uoArea->id,
        ]);
        $usuario = $this->tecnicaArea;

        // Por Filament: no hay acción de borrar (ni individual ni masiva)
        Livewire::actingAs($this->admin)->test(ListUsuarios::class)
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete');
        Livewire::actingAs($this->admin)->test(ListProfesionales::class)
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete');

        // Por código: el modelo lo rechaza
        foreach ([$usuario, $profesional] as $registro) {
            foreach (['delete', 'forceDelete'] as $metodo) {
                try {
                    $registro->{$metodo}();
                    $this->fail("{$metodo}() de ".$registro::class.' debe rechazarse.');
                } catch (LogicException) {
                }
            }
        }

        $this->assertNotSoftDeleted('users', ['id' => $usuario->id]);
        $this->assertNotSoftDeleted('profesionales', ['id' => $profesional->id]);

        // «Desactivar» sí funciona y deja al usuario sin acceso
        $this->assertTrue(Auth::validate(['email' => $usuario->email, 'password' => 'secreto']));

        Livewire::actingAs($this->admin)->test(ListUsuarios::class)->callTableAction('desactivar', $usuario);
        Livewire::actingAs($this->admin)->test(ListProfesionales::class)->callTableAction('desactivar', $profesional);

        $this->assertTrue($usuario->fresh()->estaDesactivado());
        $this->assertFalse($profesional->fresh()->activo);
        $this->assertNotSoftDeleted('users', ['id' => $usuario->id]);
        $this->assertNotSoftDeleted('profesionales', ['id' => $profesional->id]);

        Auth::logout();
        $this->post(route('login.post'), ['email' => $usuario->email, 'password' => 'secreto'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** TF-TCE-56 — Con casos de referencia en el centro, cerrar la adscripción se rechaza con el recuento y el enlace al reparto. */
    #[Test]
    public function tf_tce_56_la_salida_con_casos_se_rechaza(): void
    {
        foreach (range(1, 3) as $i) {
            $this->casoDe($this->ciamVallecas, $this->psicologa);
        }

        $adscripcion = $this->adscripcionDeLaPsicologa($this->uoCiamVallecas->id);

        try {
            app(SalidaCentroService::class)->cerrarAdscripcion($adscripcion);
            $this->fail('La salida con casos pendientes debe rechazarse.');
        } catch (SalidaCentroBloqueada $e) {
            $this->assertStringContainsString('CIAM Puente de Vallecas: 3 casos de referencia', $e->getMessage());
            $this->assertArrayHasKey('Repartir casos', $e->enlaces());
            $this->assertArrayNotHasKey('Reasignar citas', $e->enlaces());
        }

        // Tampoco por cualquier otro camino que cambie la fecha de fin (repeater de Filament)
        try {
            $adscripcion->fresh()->update(['fecha_fin' => today()]);
            $this->fail('El modelo debe exigir RN-21.');
        } catch (SalidaCentroBloqueada) {
        }

        $this->assertNull($adscripcion->fresh()->fecha_fin);
        $this->assertTrue($this->psicologa->perteneceAUo($this->uoCiamVallecas));
    }

    /** TF-TCE-56 bis — En el formulario de usuario de Filament, cerrar la adscripción con casos no guarda nada. */
    #[Test]
    public function tf_tce_56_bis_el_formulario_de_usuario_no_cierra_la_adscripcion_con_casos(): void
    {
        $this->casoDe($this->ciamVallecas, $this->psicologa);
        $adscripcion = $this->adscripcionDeLaPsicologa($this->uoCiamVallecas->id);

        Livewire::actingAs($this->admin)
            ->test(EditUsuario::class, ['record' => $this->psicologa->getKey()])
            ->set("data.adscripciones.record-{$adscripcion->id}.fecha_fin", '2026-11-02')
            ->set('data.email', 'cambiado@vida360.test')
            ->call('save')
            ->assertNotified('Hay casos o citas pendientes');

        $this->assertNull($adscripcion->fresh()->fecha_fin);
        $this->assertSame('psicologa@vida360.test', $this->psicologa->fresh()->email);
    }

    /** TF-TCE-57 — Tras repartir sus casos, la adscripción se cierra; lo de Usera no cambia. */
    #[Test]
    public function tf_tce_57_la_salida_tras_el_reparto(): void
    {
        $casosVallecas = collect(range(1, 3))->map(fn () => $this->casoDe($this->ciamVallecas, $this->psicologa));
        $casoUsera = $this->casoDe($this->ciamUsera, $this->psicologa);
        $destino = $this->destinoDelReparto($this->ciamVallecas);
        $directora = $this->directora('directora.vallecas', $this->ciamVallecas);

        $repartos = app(RepartoCasosService::class);
        $repartos->confirmar($repartos->proponer($this->ciamVallecas, $this->psicologa, 'Traslado a otro centro', $directora), $directora);

        app(SalidaCentroService::class)->cerrarAdscripcion($this->adscripcionDeLaPsicologa($this->uoCiamVallecas->id));

        $this->assertNotNull($this->adscripcionDeLaPsicologa($this->uoCiamVallecas->id)->fecha_fin);
        $this->assertNull($this->adscripcionDeLaPsicologa($this->uoCiamUsera->id)->fecha_fin);

        foreach ($casosVallecas as $historia) {
            $vigente = AsignacionProfesional::vigente()->where('historia_id', $historia->id)->sole();
            $this->assertSame($destino->id, $vigente->profesional_id);
            $this->assertSame($this->ciamVallecas->id, $vigente->centro_id);
        }

        $deUsera = AsignacionProfesional::vigente()->where('historia_id', $casoUsera->id)->sole();
        $this->assertSame($this->psicologa->id, $deUsera->profesional_id);
        $this->assertSame($this->ciamUsera->id, $deUsera->centro_id);

        // Deja de entrar en el sorteo y el reparto del centro que deja, no en el otro
        $this->assertFalse(PerfilHorarioProfesional::where('usuario_id', $this->psicologa->id)->where('centro_id', $this->ciamVallecas->id)->where('activo', true)->exists());
        PerfilHorarioProfesional::where('usuario_id', $this->psicologa->id)->where('centro_id', $this->ciamUsera->id)->get()
            ->each(fn (PerfilHorarioProfesional $p) => $this->assertTrue($p->activo));
    }

    /** TF-TCE-58 — Sin casos, una cita confirmada la semana próxima en el centro impide la salida hasta reasignarla. */
    #[Test]
    public function tf_tce_58_la_salida_con_citas_se_rechaza_hasta_reasignarlas(): void
    {
        $slot = SlotFactory::new()->reservado()->create([
            'usuario_id' => $this->psicologa->id,
            'centro_id' => $this->ciamVallecas->id,
            'fecha' => '2026-11-10',
        ]);
        $cita = CitaFactory::new()->create([
            'slot_id' => $slot->id,
            'profesional_id' => $this->psicologa->id,
            'tipo_slot_id' => $slot->tipo_slot_id,
            'centro_id' => $this->ciamVallecas->id,
            'fecha' => '2026-11-10',
        ]);
        // Una cita en el otro centro no cuenta para esta salida
        $otraEnUsera = SlotFactory::new()->reservado()->create(['usuario_id' => $this->psicologa->id, 'centro_id' => $this->ciamUsera->id, 'fecha' => '2026-11-10']);
        CitaFactory::new()->create([
            'slot_id' => $otraEnUsera->id, 'profesional_id' => $this->psicologa->id, 'tipo_slot_id' => $otraEnUsera->tipo_slot_id,
            'centro_id' => $this->ciamUsera->id, 'fecha' => '2026-11-10',
        ]);

        $salida = app(SalidaCentroService::class);

        try {
            $salida->cerrarAdscripcion($this->adscripcionDeLaPsicologa($this->uoCiamVallecas->id));
            $this->fail('La salida con citas confirmadas futuras debe rechazarse.');
        } catch (SalidaCentroBloqueada $e) {
            $this->assertStringContainsString('CIAM Puente de Vallecas: 1 cita confirmada', $e->getMessage());
            $this->assertArrayHasKey('Reasignar citas', $e->enlaces());
            $this->assertArrayNotHasKey('Repartir casos', $e->enlaces());
        }

        $this->assertNull($this->adscripcionDeLaPsicologa($this->uoCiamVallecas->id)->fecha_fin);

        // Reasignada la cita (flujo de ausencia), ya puede salir
        $cita->update(['profesional_id' => $this->destinoDelReparto($this->ciamVallecas)->id]);

        $salida->cerrarAdscripcion($this->adscripcionDeLaPsicologa($this->uoCiamVallecas->id));

        $this->assertNotNull($this->adscripcionDeLaPsicologa($this->uoCiamVallecas->id)->fecha_fin);
        $this->assertSame(EstadoCita::Confirmada, Cita::find($cita->id)->estado);
    }

    /** TF-TCE-58 bis — Desactivar exige lo mismo en todos sus centros (RN-22). */
    #[Test]
    public function tf_tce_58_bis_desactivar_exige_rn_21_en_todos_sus_centros(): void
    {
        $this->casoDe($this->ciamUsera, $this->psicologa);

        try {
            app(SalidaCentroService::class)->desactivarUsuario($this->psicologa, $this->admin);
            $this->fail('La desactivación con casos pendientes debe rechazarse.');
        } catch (SalidaCentroBloqueada $e) {
            $this->assertStringContainsString('CIAM Usera: 1 caso de referencia', $e->getMessage());
        }

        $this->assertFalse($this->psicologa->fresh()->estaDesactivado());
        $this->assertNull($this->adscripcionDeLaPsicologa($this->uoCiamVallecas->id)->fecha_fin);
    }

    /** TF-TCE-59 — Adscribirse a un tercer centro no mueve sus casos. */
    #[Test]
    public function tf_tce_59_los_casos_no_viajan(): void
    {
        $this->casoDe($this->ciamVallecas, $this->psicologa);
        $this->casoDe($this->ciamUsera, $this->psicologa);
        $antes = DB::table('asignaciones_profesional')->orderBy('id')->get()->toArray();

        $this->adscribir($this->psicologa, $this->uoCssNorte);

        $this->assertEquals($antes, DB::table('asignaciones_profesional')->orderBy('id')->get()->toArray());
        $this->assertFalse(AsignacionProfesional::where('centro_id', $this->cssNorte->id)->exists());
        $this->assertSame([], app(ActividadCasosService::class)->historiasDe($this->cssNorte, $this->psicologa));
        $this->assertCount(1, app(ActividadCasosService::class)->historiasDe($this->ciamVallecas, $this->psicologa));
        $this->assertCount(1, app(ActividadCasosService::class)->historiasDe($this->ciamUsera, $this->psicologa));
    }
}
