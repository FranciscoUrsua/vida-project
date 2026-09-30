<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use App\Models\Ciudadano;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\ActorTipoCitaEvento;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CitaEvento;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Services\Citas\CitaPrevia\AdaptadorCitaPrevia;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo H: canal externo (TF-CIT-39 a 41).
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md
 */
class CanalExternoCitaTest extends TestCase
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
     * Recibe una petición del mock de Cita Previa.
     *
     * @param array<string, mixed> $payload
     * @return array{cita: Cita, creada: bool}
     */
    private function recibir(array $payload): array
    {
        return app(CitacionService::class)->recibirExterna(app(AdaptadorCitaPrevia::class)->interpretar($payload));
    }

    /**
     * TF-CIT-39 — La misma referencia no crea otra cita ni escribe evento.
     *
     * @return void
     */
    #[Test]
    public function idempotencia(): void
    {
        $slot = $this->slotDe($this->tsr2, '2026-10-07', '11:00');
        $primera = $this->recibir(['slot_id' => $slot->id, 'referencia_externa' => 'CP-123']);
        $eventos = CitaEvento::count();

        $segunda = $this->recibir(['slot_id' => $this->slotDe($this->tsr2, '2026-10-08', '11:00')->id, 'referencia_externa' => 'CP-123']);

        $this->assertTrue($primera['creada']);
        $this->assertFalse($segunda['creada']);
        $this->assertSame($primera['cita']->id, $segunda['cita']->id);
        $this->assertSame(1, Cita::where('referencia_externa', 'CP-123')->count());
        $this->assertSame($eventos, CitaEvento::count());
        $this->assertSame(ActorTipoCitaEvento::ApiExterna, CitaEvento::where('cita_id', $primera['cita']->id)->sole()->actor_tipo);
    }

    /**
     * TF-CIT-40 — Persona no identificada: sin ciudadano, datos cifrados, ningún ciudadano nuevo.
     *
     * @return void
     */
    #[Test]
    public function persona_no_identificada(): void
    {
        $ciudadanos = Ciudadano::withoutGlobalScopes()->count();

        $cita = $this->recibir([
            'slot_id' => $this->slotDe($this->tsr2, '2026-10-07', '11:00')->id,
            'referencia_externa' => 'CP-200',
            'tipo_documento' => 'dni',
            'numero_documento' => '99999999R',
            'nombre' => 'Lucía',
            'apellidos' => 'Ortega',
        ])['cita'];

        $this->assertNull($cita->ciudadano_id);
        $this->assertTrue($cita->pendienteDeIdentificar());
        $this->assertSame('Ortega', $cita->fresh()->datos_identificacion_externos['apellidos']);
        $this->assertStringNotContainsString('Ortega', DB::table('citas')->where('id', $cita->id)->value('datos_identificacion_externos'));
        $this->assertSame($ciudadanos, Ciudadano::withoutGlobalScopes()->count());
    }

    /**
     * Con un documento que casa con una sola persona, la cita queda identificada.
     *
     * @return void
     */
    #[Test]
    public function persona_identificada_por_documento(): void
    {
        CiudadanoIdentificador::create([
            'ciudadano_id' => $this->juan->id,
            'tipo' => 'dni',
            'valor' => '12345678Z',
            'fecha_inicio' => '2020-01-01',
            'verificado' => true,
            'fuente' => 'manual',
        ]);

        $cita = $this->recibir(['slot_id' => $this->slotDe($this->tsr2, '2026-10-07', '11:00')->id, 'referencia_externa' => 'CP-300', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678z'])['cita'];

        $this->assertSame($this->juan->id, $cita->ciudadano_id);
        $this->assertNull($cita->datos_identificacion_externos);
    }

    /**
     * TF-CIT-41 — Identificación posterior en ventanilla.
     *
     * @return void
     */
    #[Test]
    public function identificacion_posterior(): void
    {
        $cita = $this->recibir(['slot_id' => $this->slotDe($this->tsr2, '2026-10-07', '11:00')->id, 'referencia_externa' => 'CP-400', 'numero_documento' => 'X0000000T'])['cita'];

        app(AtencionCitaService::class)->identificarCiudadano($cita, $this->juan, $this->consulta);

        $this->assertSame($this->juan->id, $cita->fresh()->ciudadano_id);
        $this->assertNull($cita->fresh()->datos_identificacion_externos);
        $this->assertSame(1, CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::CiudadanoIdentificado)->count());
    }

    /**
     * El canal externo no reserva slots de urgencia (PF-05.4).
     *
     * @return void
     */
    #[Test]
    public function el_canal_externo_no_reserva_slots_de_urgencia(): void
    {
        $urgencia = $this->slotDe($this->tsr2, '2026-10-07', '13:00');

        $this->expectException(LogicException::class);
        $this->recibir(['slot_id' => $urgencia->id, 'referencia_externa' => 'CP-500']);
        $this->assertSame(EstadoSlot::BloqueadoUrgencia, $urgencia->fresh()->estado);
    }
}
