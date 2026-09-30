<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Models\CitaEvento;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo I: integridad del historial (TF-CIT-42).
 * TF-CIT-43 (eventos de agenda y timeline) está en los tests de interfaz.
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md
 */
class IntegridadCitaTest extends TestCase
{
    use CitasTestSetup;
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioCitas();
    }

    /**
     * TF-CIT-42 — El historial es inmutable: modelo, SQL directo y purga de audits. [negativo]
     *
     * @return void
     */
    #[Test]
    public function el_historial_es_inmutable(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07');
        $evento = CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::CitaCreada)->sole();
        $original = $evento->getAttributes();

        foreach ([fn () => $evento->update(['estado_despues' => 'otro']), fn () => $evento->delete()] as $accion) {
            try {
                $accion();
                $this->fail('El modelo no debe permitirlo');
            } catch (LogicException) {
            }
        }

        foreach ([
            fn () => DB::table('cita_eventos')->where('id', $evento->id)->update(['estado_despues' => 'otro']),
            fn () => DB::table('cita_eventos')->where('id', $evento->id)->delete(),
        ] as $sql) {
            try {
                // Un savepoint aísla el error de PostgreSQL de la transacción del test
                DB::transaction($sql);
                $this->fail('La base de datos no debe permitirlo');
            } catch (QueryException $e) {
                $this->assertStringContainsString('solo inserción', $e->getMessage());
            }
        }

        $this->travel(10)->years();
        Artisan::call('audit:purge');

        $this->assertEquals($original, CitaEvento::findOrFail($evento->id)->getAttributes());
    }
}
