<?php

namespace Modules\Centro\Tests\Feature;

use App\Models\Ciudadano;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\Espacio;
use Modules\Centro\Models\ListaEspera;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\TipoEspacio;
use Modules\Centro\Services\PrescripcionService;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sección 9.5: Prescripción y lista de espera.
 *
 * @see docs/modulo-centros.md §9.5
 */
class PrescripcionTest extends TestCase
{
    use RefreshDatabase;

    private PrescripcionService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new PrescripcionService();
    }

    private function crearProfesional(): Profesional
    {
        $cargo = Cargo::firstOrCreate(['nombre' => 'Trabajador/a Social'], ['activo' => true]);
        $tipoRelacion = TipoRelacionProfesional::firstOrCreate(
            ['nombre' => 'Funcionario/a de carrera'],
            ['es_externo' => false, 'activo' => true]
        );

        return Profesional::create([
            'nombre'          => 'Test',
            'apellido1'       => 'Profesional',
            'sexo'            => 'M',
            'cargo_id'        => $cargo->id,
            'tipo_relacion_id' => $tipoRelacion->id,
            'fecha_inicio'    => today()->toDateString(),
            'activo'          => true,
        ]);
    }

    private function crearCiudadano(): Ciudadano
    {
        return Ciudadano::create([
            'nombre'           => 'Ciudadano',
            'apellido1'        => 'Prueba',
            'fecha_nacimiento' => '1980-01-01',
            'sexo'             => 'hombre',
            'activo'           => true,
        ]);
    }

    private function crearColeccionConPlazas(int $libres, int $ocupadas = 0, string $modo = 'prescripcion_directa'): ColeccionPlazas
    {
        $centro = Centro::create([
            'nombre'       => 'Centro ' . uniqid(),
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta'   => today()->toDateString(),
        ]);

        $tipoEspacio = TipoEspacio::create(['nombre' => 'Tipo ' . uniqid(), 'activo' => true]);

        $total = $libres + $ocupadas;
        $coleccion = ColeccionPlazas::create([
            'centro_id'   => $centro->id,
            'nombre'      => 'Plazas',
            'tipo_plaza'  => 'pernocta',
            'modo_acceso' => $modo,
            'capacidad'   => $total,
            'activa'      => true,
            'fecha_alta'  => today()->toDateString(),
        ]);

        $espacio = Espacio::create([
            'coleccion_plazas_id' => $coleccion->id,
            'tipo_espacio_id'     => $tipoEspacio->id,
            'nombre'              => 'Sala',
            'capacidad'           => $total,
            'accesible'           => false,
        ]);

        for ($i = 0; $i < $libres; $i++) {
            Plaza::create(['espacio_id' => $espacio->id, 'nombre' => "Libre $i", 'estado' => 'libre', 'activa' => true]);
        }
        for ($i = 0; $i < $ocupadas; $i++) {
            Plaza::create(['espacio_id' => $espacio->id, 'nombre' => "Ocupada $i", 'estado' => 'ocupada', 'activa' => true]);
        }

        return $coleccion;
    }

    // =========================================================================
    // TC-01 — Una prescripción a colección con plaza libre queda asignada
    // =========================================================================

    #[Test]
    public function una_prescripcion_a_coleccion_con_plaza_libre_queda_asignada(): void
    {
        $profesional = $this->crearProfesional();
        $ciudadano   = $this->crearCiudadano();
        $coleccion   = $this->crearColeccionConPlazas(libres: 2);

        $prescripcion = $this->servicio->crear([
            'profesional_id'     => $profesional->id,
            'ciudadano_id'       => $ciudadano->id,
            'tipo_destino'       => 'coleccion_plazas',
            'destino_id'         => $coleccion->id,
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        $this->assertEquals('asignada', $prescripcion->estado);
        $this->assertNotNull($prescripcion->plaza_id);
    }

    // =========================================================================
    // TC-02 — Una prescripción a colección sin plazas entra en lista de espera
    // =========================================================================

    #[Test]
    public function una_prescripcion_a_coleccion_sin_plazas_entra_en_lista_de_espera(): void
    {
        $profesional = $this->crearProfesional();
        $ciudadano   = $this->crearCiudadano();
        $coleccion   = $this->crearColeccionConPlazas(libres: 0, ocupadas: 2, modo: 'prescripcion_lista_espera');

        $prescripcion = $this->servicio->crear([
            'profesional_id'     => $profesional->id,
            'ciudadano_id'       => $ciudadano->id,
            'tipo_destino'       => 'coleccion_plazas',
            'destino_id'         => $coleccion->id,
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        $this->assertEquals('en_lista_espera', $prescripcion->estado);
        $this->assertNotNull(ListaEspera::where('prescripcion_id', $prescripcion->id)->first());
    }

    // =========================================================================
    // TC-03 — Al liberarse una plaza se genera alerta al TSR activo
    // =========================================================================

    #[Test]
    public function al_liberarse_una_plaza_se_genera_alerta_al_tsr_activo(): void
    {
        $tsrA        = $this->crearProfesional();
        $tsrB        = $this->crearProfesional();
        $ciudadano   = $this->crearCiudadano();
        $coleccion   = $this->crearColeccionConPlazas(libres: 0, ocupadas: 2, modo: 'prescripcion_lista_espera');

        // Prescripción inicial: alerta apunta a TSR A.
        $prescripcion = $this->servicio->crear([
            'profesional_id'     => $tsrA->id,
            'ciudadano_id'       => $ciudadano->id,
            'tipo_destino'       => 'coleccion_plazas',
            'destino_id'         => $coleccion->id,
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        $entrada = ListaEspera::where('prescripcion_id', $prescripcion->id)->firstOrFail();
        $this->assertEquals($tsrA->id, $entrada->profesional_alerta_id);

        // Simular cambio de TSR activo del ciudadano a B.
        $this->servicio->setTsrResolver(fn (int $cid) => $tsrB->id);

        // Obtener una plaza ocupada y liberarla.
        $plazaOcupada = $coleccion->plazas()->where('estado', 'ocupada')->first();
        $this->servicio->liberarPlaza($plazaOcupada);

        $this->assertEquals($tsrB->id, $entrada->fresh()->profesional_alerta_id);
    }

    // =========================================================================
    // TC-04 — La asignación no es automática al liberarse una plaza
    // =========================================================================

    #[Test]
    public function la_asignacion_no_es_automatica_al_liberarse_plaza(): void
    {
        $profesional = $this->crearProfesional();
        $ciudadano   = $this->crearCiudadano();
        $coleccion   = $this->crearColeccionConPlazas(libres: 0, ocupadas: 1, modo: 'prescripcion_lista_espera');

        $prescripcion = $this->servicio->crear([
            'profesional_id'     => $profesional->id,
            'ciudadano_id'       => $ciudadano->id,
            'tipo_destino'       => 'coleccion_plazas',
            'destino_id'         => $coleccion->id,
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        $plazaOcupada = $coleccion->plazas()->where('estado', 'ocupada')->first();
        $this->servicio->liberarPlaza($plazaOcupada);

        // El estado sigue siendo en_lista_espera, no asignada.
        $this->assertEquals('en_lista_espera', $prescripcion->fresh()->estado);

        // Existe una entrada activa en la lista de espera.
        $this->assertTrue(
            ListaEspera::where('prescripcion_id', $prescripcion->id)
                ->where('estado', 'activa')
                ->exists()
        );
    }

    // =========================================================================
    // TC-05 — Cancelar una prescripción libera la plaza
    // =========================================================================

    #[Test]
    public function cancelar_una_prescripcion_libera_la_plaza(): void
    {
        $profesional = $this->crearProfesional();
        $ciudadano   = $this->crearCiudadano();
        $coleccion   = $this->crearColeccionConPlazas(libres: 1);

        $prescripcion = $this->servicio->crear([
            'profesional_id'     => $profesional->id,
            'ciudadano_id'       => $ciudadano->id,
            'tipo_destino'       => 'coleccion_plazas',
            'destino_id'         => $coleccion->id,
            'fecha_prescripcion' => today()->toDateString(),
        ]);

        // Avanzar estado a activa y asegurar que hay plaza asignada.
        $this->assertEquals('asignada', $prescripcion->estado);
        $plazaId = $prescripcion->plaza_id;

        $this->servicio->cancelar($prescripcion, 'Motivo de prueba');

        $this->assertEquals('cancelada', $prescripcion->fresh()->estado);
        $this->assertEquals('libre', Plaza::find($plazaId)->estado);
    }
}
