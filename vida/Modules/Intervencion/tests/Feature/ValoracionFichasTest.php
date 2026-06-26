<?php

namespace Modules\Intervencion\Tests\Feature;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Enums\EstadoValoracion;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\TipoValoracion;
use Modules\Intervencion\Models\TipoValoracionFicha;
use Modules\Intervencion\Models\Valoracion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo D: Valoración y fichas.
 *
 * @see docs/modulo-intervencion.md §4, §10 Grupo D
 */
class ValoracionFichasTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    private User $profesional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Test Valoracion',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->profesional = User::factory()->create();
    }

    private function crearHistoria(): HistoriaSocial
    {
        return HistoriaSocial::create([
            'ciudadano_id' => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);
    }

    private function crearTipoValoracion(): TipoValoracion
    {
        return TipoValoracion::create([
            'nombre' => 'Valoración Test',
            'contexto' => 'ASP',
            'activo' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-INT-D01: Ficha almacena y recupera datos como array.
     */
    #[Test]
    public function ficha_almacena_y_recupera_datos_como_array(): void
    {
        $tipoFicha = TipoFicha::factory()->create();
        $tipoValoracion = $this->crearTipoValoracion();

        $valoracion = Valoracion::create([
            'historia_id' => $this->crearHistoria()->id,
            'profesional_id' => $this->profesional->id,
            'tipo_valoracion_id' => $tipoValoracion->id,
            'fecha' => today()->toDateString(),
            'estado' => EstadoValoracion::Borrador,
        ]);

        $datos = ['ingresos_mensuales_hogar' => 850, 'numero_personas_dependientes' => 3];

        $ficha = Ficha::create([
            'valoracion_id' => $valoracion->id,
            'tipo_ficha_id' => $tipoFicha->id,
            'datos' => $datos,
        ]);

        $fichaRecargada = Ficha::find($ficha->id);

        $this->assertSame($datos, $fichaRecargada->datos);
    }

    /**
     * TF-INT-D02: Ficha con notas y sin datos es válida.
     */
    #[Test]
    public function ficha_con_notas_y_sin_datos_es_valida(): void
    {
        $tipoFicha = TipoFicha::factory()->create();
        $tipoValoracion = $this->crearTipoValoracion();

        $valoracion = Valoracion::create([
            'historia_id' => $this->crearHistoria()->id,
            'profesional_id' => $this->profesional->id,
            'tipo_valoracion_id' => $tipoValoracion->id,
            'fecha' => today()->toDateString(),
            'estado' => EstadoValoracion::Borrador,
        ]);

        $ficha = Ficha::create([
            'valoracion_id' => $valoracion->id,
            'tipo_ficha_id' => $tipoFicha->id,
            'notas' => 'El ciudadano comenta que vive solo desde hace dos años.',
            'datos' => null,
            'completada' => false,
        ]);

        $this->assertNotNull($ficha->id, 'La ficha debe guardarse sin errores');
        $this->assertFalse($ficha->completada, 'Una ficha sin datos estructurados no está completada');
        $this->assertNull($ficha->datos);
    }

    /**
     * TF-INT-D03: TipoValoracion devuelve sus fichas en orden configurado.
     */
    #[Test]
    public function tipo_valoracion_devuelve_fichas_en_orden_configurado(): void
    {
        $tipoValoracion = $this->crearTipoValoracion();

        $ficha0 = TipoFicha::factory()->create(['nombre' => 'Ficha orden 0']);
        $ficha1 = TipoFicha::factory()->create(['nombre' => 'Ficha orden 1']);
        $ficha2 = TipoFicha::factory()->create(['nombre' => 'Ficha orden 2']);

        // Crear con orden desordenado intencionalmente
        TipoValoracionFicha::create(['tipo_valoracion_id' => $tipoValoracion->id, 'tipo_ficha_id' => $ficha2->id, 'orden' => 2]);
        TipoValoracionFicha::create(['tipo_valoracion_id' => $tipoValoracion->id, 'tipo_ficha_id' => $ficha0->id, 'orden' => 0]);
        TipoValoracionFicha::create(['tipo_valoracion_id' => $tipoValoracion->id, 'tipo_ficha_id' => $ficha1->id, 'orden' => 1]);

        $fichasOrdenadas = $tipoValoracion->tipoValoracionFichas()->orderBy('orden')->get();

        $this->assertEquals(0, $fichasOrdenadas[0]->orden);
        $this->assertEquals(1, $fichasOrdenadas[1]->orden);
        $this->assertEquals(2, $fichasOrdenadas[2]->orden);
    }

    /**
     * TF-INT-D04: Valoración en borrador puede tener fichas incompletas.
     */
    #[Test]
    public function valoracion_borrador_puede_tener_fichas_incompletas(): void
    {
        $fichaObligatoria = TipoFicha::factory()->create(['nombre' => 'Ficha obligatoria']);
        $fichaNoObligatoria = TipoFicha::factory()->create(['nombre' => 'Ficha no obligatoria']);
        $tipoValoracion = $this->crearTipoValoracion();

        TipoValoracionFicha::create([
            'tipo_valoracion_id' => $tipoValoracion->id,
            'tipo_ficha_id' => $fichaObligatoria->id,
            'orden' => 0,
            'obligatoria' => true,
        ]);

        TipoValoracionFicha::create([
            'tipo_valoracion_id' => $tipoValoracion->id,
            'tipo_ficha_id' => $fichaNoObligatoria->id,
            'orden' => 1,
            'obligatoria' => false,
        ]);

        $valoracion = Valoracion::create([
            'historia_id' => $this->crearHistoria()->id,
            'profesional_id' => $this->profesional->id,
            'tipo_valoracion_id' => $tipoValoracion->id,
            'fecha' => today()->toDateString(),
            'estado' => EstadoValoracion::Borrador,
        ]);

        // Solo la ficha no obligatoria
        Ficha::create([
            'valoracion_id' => $valoracion->id,
            'tipo_ficha_id' => $fichaNoObligatoria->id,
            'notas' => 'Solo la no obligatoria',
        ]);

        $this->assertDatabaseHas('valoraciones', [
            'id' => $valoracion->id,
            'estado' => 'borrador',
        ]);
    }

    /**
     * TF-INT-D05: Una entrevista puede existir sin valoración asociada.
     */
    #[Test]
    public function entrevista_puede_existir_sin_valoracion(): void
    {
        $historia = $this->crearHistoria();

        $entrevista = Entrevista::create([
            'historia_id' => $historia->id,
            'profesional_id' => $this->profesional->id,
            'cita_id' => null,
            'fecha_hora' => now()->toDateTimeString(),
            'modalidad' => 'presencial',
            'tipo' => 'informativa',
            'estado' => 'realizada',
        ]);

        $this->assertNull($entrevista->valoracion, 'Una entrevista informativa sin valoración debe devolver null');
        $this->assertNotNull($entrevista->id, 'La entrevista debe haberse guardado correctamente');
    }
}
