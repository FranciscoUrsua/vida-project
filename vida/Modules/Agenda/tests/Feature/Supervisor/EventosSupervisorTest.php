<?php

namespace Modules\Agenda\Tests\Feature\Supervisor;

use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Livewire\Supervisor\EventosSupervisorPage;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Agenda\Models\Slot;
use Modules\Centro\Models\Espacio;
use Modules\Centro\Models\TipoEspacio;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales TF-AGS-30 a TF-AGS-33 — Eventos internos.
 */
class EventosSupervisorTest extends TestCase
{
    use RefreshDatabase;
    use AgendaSupervisorTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->construirFixturesSupervisor();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function crearEspacio(string $nombre = 'Sala A'): Espacio
    {
        $tipo = TipoEspacio::first() ?? TipoEspacio::create(['nombre' => 'Sala']);

        return Espacio::create([
            'centro_id'      => $this->centro->id,
            'tipo_espacio_id'=> $tipo->id,
            'nombre'         => $nombre,
            'capacidad'      => 10,
        ]);
    }

    private function crearSlotDisponible(): Slot
    {
        $tipoSlot = $this->crearTipoSlot();
        $linea    = $this->crearLineaCuadrante($this->profesional1);

        return Slot::create([
            'linea_cuadrante_id' => $linea->id,
            'usuario_id'         => $this->profesional1->id,
            'centro_id'          => $this->centro->id,
            'tipo_slot_id'       => $tipoSlot->id,
            'fecha'              => now()->toDateString(),
            'hora_inicio'        => '10:00',
            'hora_fin'           => '10:30',
            'estado'             => EstadoSlot::Disponible->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-AGS-30 — El supervisor puede crear un evento que bloquea slots de los convocados.
     */
    #[Test]
    public function supervisor_puede_crear_evento_que_bloquea_slots(): void
    {
        // Dado: profesional1 con slot disponible a las 10:00
        $slot = $this->crearSlotDisponible();

        // Cuando: el supervisor crea un evento convocando a profesional1
        Livewire::actingAs($this->supervisor)
            ->test(EventosSupervisorPage::class)
            ->set('form.nombre', 'Reunión de equipo')
            ->set('form.fecha', now()->toDateString())
            ->set('form.hora_inicio', '10:00')
            ->set('form.duracion_minutos', '60')
            ->set('form.tipo_evento', 'coordinacion')
            ->set('form.profesionales_ids', [$this->profesional1->id])
            ->call('crear');

        // Entonces: el slot pasa a bloqueado_evento
        $this->assertEquals(EstadoSlot::BloqueadoEvento, $slot->fresh()->estado);

        // Y: el evento aparece en la lista
        $this->assertDatabaseHas('eventos_agenda', [
            'titulo'    => 'Reunión de equipo',
            'centro_id' => $this->centro->id,
        ]);
    }

    /**
     * TF-AGS-31 — Un evento con conflicto de espacio muestra aviso al creador.
     */
    #[Test]
    public function evento_con_conflicto_espacio_muestra_aviso(): void
    {
        $espacio = $this->crearEspacio('Sala Conflicto');

        // Dado: un evento existente que ocupa el espacio
        EventoAgenda::create([
            'centro_id'    => $this->centro->id,
            'titulo'       => 'Evento previo',
            'fecha'        => now()->addDay()->toDateString(),
            'hora_inicio'  => '10:00',
            'hora_fin'     => '12:00',
            'tipo_evento'  => 'coordinacion',
            'espacio_id'   => $espacio->id,
            'creado_por_id'=> $this->supervisor->id,
        ]);

        // Cuando: el supervisor crea un evento en el mismo espacio y franja
        $component = Livewire::actingAs($this->supervisor)
            ->test(EventosSupervisorPage::class)
            ->set('form.nombre', 'Evento conflicto')
            ->set('form.fecha', now()->addDay()->toDateString())
            ->set('form.hora_inicio', '10:30')
            ->set('form.duracion_minutos', '60')
            ->set('form.tipo_evento', 'sesion_interna')
            ->set('form.espacio_id', (string) $espacio->id)
            ->call('crear');

        // Entonces: se detecta conflicto y se activa el aviso
        $this->assertTrue($component->instance()->hayConflictoEspacio);
    }

    /**
     * TF-AGS-32 — Un evento aparece en la lista de eventos próximos.
     */
    #[Test]
    public function evento_aparece_en_lista_proximos(): void
    {
        EventoAgenda::create([
            'centro_id'    => $this->centro->id,
            'titulo'       => 'Formación PHP',
            'fecha'        => now()->addDays(3)->toDateString(),
            'hora_inicio'  => '09:00',
            'hora_fin'     => '11:00',
            'tipo_evento'  => 'sesion_interna',
            'espacio_id'   => null,
            'creado_por_id'=> $this->supervisor->id,
        ]);

        $component = Livewire::actingAs($this->supervisor)
            ->test(EventosSupervisorPage::class);

        $this->assertCount(1, $component->instance()->eventosProximos);
    }

    /**
     * TF-AGS-33 — Eliminar un evento libera los slots bloqueados.
     */
    #[Test]
    public function eliminar_evento_libera_slots_bloqueados(): void
    {
        // Dado: dos slots bloqueados por un evento
        $tipoSlot = $this->crearTipoSlot();
        $linea    = $this->crearLineaCuadrante($this->profesional1);

        $slot1 = Slot::create(['linea_cuadrante_id' => $linea->id, 'usuario_id' => $this->profesional1->id, 'centro_id' => $this->centro->id, 'tipo_slot_id' => $tipoSlot->id, 'fecha' => now()->toDateString(), 'hora_inicio' => '10:00', 'hora_fin' => '10:30', 'estado' => EstadoSlot::BloqueadoEvento->value]);
        $slot2 = Slot::create(['linea_cuadrante_id' => $linea->id, 'usuario_id' => $this->profesional1->id, 'centro_id' => $this->centro->id, 'tipo_slot_id' => $tipoSlot->id, 'fecha' => now()->toDateString(), 'hora_inicio' => '10:30', 'hora_fin' => '11:00', 'estado' => EstadoSlot::BloqueadoEvento->value]);

        $evento = EventoAgenda::create([
            'centro_id'    => $this->centro->id,
            'titulo'       => 'Evento a eliminar',
            'fecha'        => now()->toDateString(),
            'hora_inicio'  => '10:00',
            'hora_fin'     => '11:00',
            'tipo_evento'  => 'coordinacion',
            'espacio_id'   => null,
            'creado_por_id'=> $this->supervisor->id,
        ]);

        $evento->profesionales()->sync([$this->profesional1->id]);

        // Cuando: el supervisor elimina el evento
        Livewire::actingAs($this->supervisor)
            ->test(EventosSupervisorPage::class)
            ->call('eliminar', $evento->id);

        // Entonces: los slots vuelven a disponible
        $this->assertEquals(EstadoSlot::Disponible, $slot1->fresh()->estado);
        $this->assertEquals(EstadoSlot::Disponible, $slot2->fresh()->estado);
        $this->assertSoftDeleted('eventos_agenda', ['id' => $evento->id]);
    }
}
