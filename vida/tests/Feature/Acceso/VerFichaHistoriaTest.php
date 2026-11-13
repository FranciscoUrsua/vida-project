<?php

namespace Tests\Feature\Acceso;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\VerFichaPage;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\Valoracion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La ficha de valoración solo se muestra bajo su propia historia (TF-ACC-17 y 18,
 * B2 de docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md).
 *
 * La ruta /intervencion/ciudadano/{historia}/ficha/{ficha} autoriza la historia;
 * una ficha de otra historia responde 404 sin datos, aunque el usuario pueda ver
 * la historia de la ruta.
 */
class VerFichaHistoriaTest extends TestCase
{
    use RefreshDatabase;

    /** Profesional de intervención de la UO. */
    private User $ts;

    /** Historia que el profesional puede ver y que figura en la ruta. */
    private HistoriaSocial $historia;

    /** Otra historia, de otra persona. */
    private HistoriaSocial $otraHistoria;

    private TipoFicha $tipoFicha;

    /**
     * Dos historias de la misma UO y un profesional de esa UO.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $uo = UnidadOrganizativa::create(['nombre' => 'CSS Fichas', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);
        $this->ts = User::create([
            'name' => 'ts.fichas',
            'email' => 'ts.fichas@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $this->ts->assignRole('intervencion');
        UsuarioUo::create([
            'usuario_id' => $this->ts->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        $this->historia = $this->crearHistoria('Ana', $uo);
        $this->otraHistoria = $this->crearHistoria('Luis', $uo);
        $this->tipoFicha = TipoFicha::factory()->create(['nombre' => 'Valoración de prueba']);
    }

    /** TF-ACC-17 — Una ficha de otra historia responde 404 y no deja ver nada de ella. */
    #[Test]
    public function tf_acc_17_una_ficha_de_otra_historia_responde_404_sin_datos(): void
    {
        $fichaAjena = $this->crearFicha(['historia_id' => $this->otraHistoria->id], 'Dato de Luis');

        $this->actingAs($this->ts)
            ->get(route('intervencion.ficha.show', [$this->historia, $fichaAjena]))
            ->assertNotFound()
            ->assertDontSee('Dato de Luis')
            ->assertDontSee('Valoración de prueba');

        Livewire::test(VerFichaPage::class, ['historia' => $this->historia, 'ficha' => $fichaAjena])
            ->assertNotFound();
    }

    /** TF-ACC-18 — La ficha de la historia de la ruta se muestra. */
    #[Test]
    public function tf_acc_18_la_ficha_de_la_historia_se_muestra(): void
    {
        $ficha = $this->crearFicha(['historia_id' => $this->historia->id], 'Dato de Ana');

        $this->actingAs($this->ts)
            ->get(route('intervencion.ficha.show', [$this->historia, $ficha]))
            ->assertOk()
            ->assertSee('Valoración de prueba')
            ->assertSee('Dato de Ana');
    }

    /** TF-ACC-18 bis — Sin historia_id, la ficha pertenece a la historia de su valoración y a ninguna otra. */
    #[Test]
    public function tf_acc_18_bis_sin_historia_id_decide_la_historia_de_la_valoracion(): void
    {
        $valoracion = Valoracion::factory()->create(['historia_id' => $this->historia->id]);
        $ficha = $this->crearFicha(['historia_id' => null, 'valoracion_id' => $valoracion->id], 'Dato por valoración');
        $huerfana = $this->crearFicha(['historia_id' => null, 'valoracion_id' => null], 'Dato huérfano');

        $this->actingAs($this->ts);

        $this->get(route('intervencion.ficha.show', [$this->historia, $ficha]))
            ->assertOk()
            ->assertSee('Dato por valoración');
        $this->get(route('intervencion.ficha.show', [$this->otraHistoria, $ficha]))
            ->assertNotFound()
            ->assertDontSee('Dato por valoración');
        $this->get(route('intervencion.ficha.show', [$this->historia, $huerfana]))
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Historia abierta de una persona no protegida.
     *
     * @param string $nombre Nombre de la persona.
     * @param UnidadOrganizativa $uo UO de la historia.
     *
     * @return HistoriaSocial
     */
    private function crearHistoria(string $nombre, UnidadOrganizativa $uo): HistoriaSocial
    {
        $ciudadano = Ciudadano::create([
            'nombre' => $nombre,
            'apellido1' => 'Prueba',
            'fecha_nacimiento' => '1980-01-01',
            'sexo' => 'F',
            'nivel_identificacion' => 'identificado',
            'activo' => true,
            'colectivo_extra_protegido' => false,
        ]);

        return HistoriaSocial::create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);
    }

    /**
     * Ficha completada del tipo de prueba, con un valor reconocible.
     *
     * @param array<string, mixed> $vinculo historia_id y/o valoracion_id.
     * @param string $valor Valor del campo de prueba.
     *
     * @return Ficha
     */
    private function crearFicha(array $vinculo, string $valor): Ficha
    {
        return Ficha::create(array_merge([
            'valoracion_id' => null,
            'tipo_ficha_id' => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'datos' => ['campo_prueba' => $valor],
            'completada' => true,
        ], $vinculo));
    }
}
