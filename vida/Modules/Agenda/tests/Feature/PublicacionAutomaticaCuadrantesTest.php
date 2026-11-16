<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Publicación automática del cuadrante mensual (`agenda:publicar-cuadrantes`): sin
 * cuadrante publicado no hay huecos para citar (incidencia del 2026-11-16).
 */
class PublicacionAutomaticaCuadrantesTest extends TestCase
{
    use CitasTestSetup;
    use RefreshDatabase;

    /**
     * Prepara el escenario de citas (hoy, 6 oct 2026) con un perfil horario del TSR.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioCitas();
        $this->perfil($this->tsr);
    }

    #[Test]
    public function publica_el_mes_siguiente_con_sus_slots(): void
    {
        $this->artisan('agenda:publicar-cuadrantes')
            ->expectsOutputToContain('Cuadrantes de 11/2026')
            ->expectsOutputToContain('publicado')
            ->assertSuccessful();

        $cuadrante = CuadranteMes::delCentro($this->centro->id)->delMes(2026, 11)->sole();
        $this->assertSame(EstadoCuadrante::Publicado, $cuadrante->estado);
        $this->assertGreaterThan(0, Slot::whereIn('linea_cuadrante_id', $cuadrante->lineas()->select('id'))->where('usuario_id', $this->tsr->id)->count());
    }

    #[Test]
    public function no_toca_un_centro_que_ya_tiene_cuadrante_ese_mes(): void
    {
        $borrador = CuadranteMes::create([
            'centro_id' => $this->centro->id, 'anyo' => 2026, 'mes' => 11,
            'estado' => EstadoCuadrante::Borrador->value, 'generado_con_ia' => false, 'generado_automaticamente' => false,
        ]);

        $this->artisan('agenda:publicar-cuadrantes', ['--mes' => '2026-11'])
            ->expectsOutputToContain('ya tiene cuadrante')
            ->assertSuccessful();

        $this->assertSame(EstadoCuadrante::Borrador, $borrador->fresh()->estado);
        $this->assertSame(0, $borrador->lineas()->count());
    }

    #[Test]
    public function no_publica_un_cuadrante_vacio(): void
    {
        // Horario sin tipos de slot: se publicaría sin huecos y ocuparía el mes
        $this->horario->tiposSlot()->detach();

        $this->artisan('agenda:publicar-cuadrantes', ['--mes' => '2026-12'])
            ->expectsOutputToContain('el horario no tiene tipos de slot')
            ->assertSuccessful();

        $this->assertFalse(CuadranteMes::delCentro($this->centro->id)->delMes(2026, 12)->exists());
    }

    #[Test]
    public function un_mes_mal_escrito_se_rechaza(): void
    {
        $this->artisan('agenda:publicar-cuadrantes', ['--mes' => '2026-13'])->assertFailed();
        $this->assertSame(0, CuadranteMes::delMes(2026, 1)->count());
    }

    #[Test]
    public function esta_programado_el_dia_20_de_cada_mes(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'agenda:publicar-cuadrantes'));

        $this->assertNotNull($evento, 'La tarea debe estar en el planificador');
        $this->assertSame('30 2 20 * *', $evento->expression);
    }

    /**
     * Perfil horario activo en el centro del escenario: el que se crea al adscribir o,
     * si no lo hay, uno de mañanas L-V.
     *
     * @param User $usuario
     *
     * @return PerfilHorarioProfesional
     */
    private function perfil(User $usuario): PerfilHorarioProfesional
    {
        $existente = PerfilHorarioProfesional::where('usuario_id', $usuario->id)->where('centro_id', $this->centro->id)->where('activo', true)->first();
        if ($existente !== null) {
            return $existente;
        }

        $franja = [['inicio' => '09:00', 'fin' => '14:00']];

        return PerfilHorarioProfesional::create([
            'usuario_id' => $usuario->id,
            'centro_id' => $this->centro->id,
            'jornada_semanal_horas' => 35,
            'horario_habitual' => ['1' => $franja, '2' => $franja, '3' => $franja, '4' => $franja, '5' => $franja],
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'activo' => true,
        ]);
    }
}
