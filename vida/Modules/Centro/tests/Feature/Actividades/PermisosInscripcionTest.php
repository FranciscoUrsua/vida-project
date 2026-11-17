<?php

namespace Modules\Centro\Tests\Feature\Actividades;

use App\Services\PresentacionPersona;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Services\Actividades\InscripcionActividadService;
use Modules\Centro\Tests\Concerns\ActividadesTestSetup;
use Modules\Ciudadania\Http\Livewire\ActividadesPersona;
use Modules\Supervision\Http\Livewire\ActividadesPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo E — Permisos de la inscripción y personas protegidas en la lista de inscritos
 * (TF-ACT-22 y 24; fase 6, paso 5, RN-15 y RN-17).
 *
 * Restricción de dominio crítica (CLAUDE.md §3): una persona protegida no se identifica
 * fuera de quien la gestiona; en la lista de inscritos sale como «Persona con protección
 * especial».
 */
class PermisosInscripcionTest extends TestCase
{
    use ActividadesTestSetup;
    use RefreshDatabase;

    /**
     * Escenario de actividades.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarActividades();
    }

    /**
     * TF-ACT-22 — Sin adscripción al centro de la actividad no se inscribe por acceso libre,
     * ni por el servicio ni por la acción Livewire. [negativo]
     *
     * @return void
     */
    #[Test]
    public function tf_act_22_sin_adscripcion_no_se_inscribe(): void
    {
        $persona = $this->persona($this->tallerCiam);

        foreach ([$this->tsNorte, $this->coordIgualdad] as $usuario) {
            try {
                app(InscripcionActividadService::class)->inscribir($this->sesion9, $persona, $usuario);
                $this->fail("{$usuario->email} ha inscrito sin estar adscrito al centro.");
            } catch (AuthorizationException) {
                // Esperado
            }
        }

        // El TS del CSS Norte, en la ficha: el formulario no le ofrece el taller y la acción lo rechaza
        Livewire::actingAs($this->tsNorte)->test(ActividadesPersona::class, ['ciudadanoId' => $this->ana->id])
            ->assertDontSee('Taller de autonomía')
            ->set('sesionId', $this->sesion9->id)
            ->call('inscribir', true)
            ->assertHasErrors('sesionId');

        $this->assertSame(0, InscripcionSesion::count());
    }

    /**
     * TF-ACT-24 — Con una persona protegida inscrita, un supervisor que llega al centro por
     * una UO superior ve «Persona con protección especial»; la dirección del centro la ve
     * con identidad.
     *
     * @return void
     */
    #[Test]
    public function tf_act_24_persona_protegida_en_la_lista_de_inscritos(): void
    {
        // Persona protegida con su historia en el CIAM de Vallecas
        [$protegida] = $this->personaEnCentro($this->ciamVallecas, protegida: true, referencia: $this->psicologaVallecas);
        app(InscripcionActividadService::class)->inscribir($this->sesion9, $protegida, $this->psicologaVallecas, inscribirEnCentro: true);

        $nombres = fn ($usuario) => collect(
            Livewire::actingAs($usuario)->test(ActividadesPage::class)
                ->call('abrirSesiones', $this->tallerCiam->id)
                ->call('verInscritos', $this->sesion9->id)
                ->get('inscritos')
        )->pluck('persona.nombre')->all();

        $this->assertSame([PresentacionPersona::NOMBRE_PROTEGIDO], $nombres($this->jefaJuntaVallecas));
        $this->assertSame([$protegida->nombre_completo], $nombres($this->directoraVallecas));
    }

    /**
     * La lista de inscritos no la ve quien no es del centro de la actividad. [negativo]
     *
     * @return void
     */
    #[Test]
    public function inscritos_solo_para_el_centro(): void
    {
        $this->assertFalse($this->tsNorte->can('verInscritos', [InscripcionSesion::class, $this->sesion9]));
        $this->assertTrue($this->auxVallecas->can('verInscritos', [InscripcionSesion::class, $this->sesion9]));
        $this->assertTrue($this->directoraVallecas->can('verInscritos', [InscripcionSesion::class, $this->sesion9]));
    }
}
