<?php

namespace Modules\Prestaciones\Tests\Feature;

use App\Filament\Resources\PrestacionResource\Pages\CreatePrestacion;
use App\Filament\Resources\PrestacionResource\Pages\EditPrestacion;
use App\Filament\Resources\PrestacionResource\Pages\ListPrestaciones;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Prestaciones\Models\Prestacion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del recurso Filament para la gestión del catálogo de prestaciones.
 *
 * Validan el ciclo CRUD, validaciones del formulario, filtros de tabla y
 * el historial de versiones desde la interfaz de administración.
 */
class PrestacionFilamentResourceTest extends TestCase
{
    use RefreshDatabase;

    private function prestacionBase(array $overrides = []): array
    {
        return array_merge([
            'codigo'          => '010101',
            'nombre'          => 'Servicio de información y asesoramiento',
            'tipo_prestacion' => 'servicio',
            'nivel_garantia'  => 'garantizada',
            'activa'          => true,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Listado
    // -------------------------------------------------------------------------

    #[Test]
    public function un_admin_puede_listar_prestaciones_en_filament(): void
    {
        $admin = User::factory()->create();

        $prestaciones = collect(range(1, 5))->map(fn ($i) => Prestacion::create(
            $this->prestacionBase(['codigo' => "P000{$i}", 'nombre' => "Prestación {$i}"])
        ));

        Livewire::actingAs($admin)
            ->test(ListPrestaciones::class)
            ->assertCanSeeTableRecords($prestaciones);
    }

    #[Test]
    public function el_listado_de_filament_filtra_correctamente_por_tipo_prestacion(): void
    {
        $admin = User::factory()->create();

        Prestacion::create($this->prestacionBase(['codigo' => 'SRV01', 'tipo_prestacion' => 'servicio']));
        Prestacion::create($this->prestacionBase(['codigo' => 'SRV02', 'tipo_prestacion' => 'servicio']));
        Prestacion::create($this->prestacionBase(['codigo' => 'SRV03', 'tipo_prestacion' => 'servicio']));
        Prestacion::create($this->prestacionBase(['codigo' => 'ECO01', 'tipo_prestacion' => 'economica']));
        Prestacion::create($this->prestacionBase(['codigo' => 'ECO02', 'tipo_prestacion' => 'economica']));

        Livewire::actingAs($admin)
            ->test(ListPrestaciones::class)
            ->filterTable('tipo_prestacion', 'economica')
            ->assertCountTableRecords(2);
    }

    #[Test]
    public function el_listado_de_filament_filtra_correctamente_por_activa(): void
    {
        $admin = User::factory()->create();

        Prestacion::create($this->prestacionBase(['codigo' => 'ACT01', 'activa' => true]));
        Prestacion::create($this->prestacionBase(['codigo' => 'ACT02', 'activa' => true]));
        Prestacion::create($this->prestacionBase(['codigo' => 'INA01', 'activa' => false]));

        Livewire::actingAs($admin)
            ->test(ListPrestaciones::class)
            ->filterTable('activa', false)
            ->assertCountTableRecords(1);
    }

    // -------------------------------------------------------------------------
    // Creación
    // -------------------------------------------------------------------------

    #[Test]
    public function un_admin_puede_crear_una_prestacion_desde_filament(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreatePrestacion::class)
            ->fillForm([
                'codigo'          => '010101',
                'nombre'          => 'Servicio de información',
                'tipo_prestacion' => 'servicio',
                'nivel_garantia'  => 'garantizada',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('prestaciones', [
            'codigo' => '010101',
            'nombre' => 'Servicio de información',
        ]);
    }

    #[Test]
    public function el_formulario_de_filament_rechaza_una_prestacion_sin_nombre(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreatePrestacion::class)
            ->fillForm([
                'codigo'          => '010101',
                'nombre'          => '',
                'tipo_prestacion' => 'servicio',
                'nivel_garantia'  => 'garantizada',
            ])
            ->call('create')
            ->assertHasFormErrors(['nombre' => 'required']);

        $this->assertDatabaseCount('prestaciones', 0);
    }

    #[Test]
    public function el_formulario_de_filament_rechaza_un_codigo_duplicado(): void
    {
        $admin = User::factory()->create();
        Prestacion::create($this->prestacionBase(['codigo' => '010101']));

        Livewire::actingAs($admin)
            ->test(CreatePrestacion::class)
            ->fillForm([
                'codigo'          => '010101',
                'nombre'          => 'Otro servicio',
                'tipo_prestacion' => 'servicio',
                'nivel_garantia'  => 'garantizada',
            ])
            ->call('create')
            ->assertHasFormErrors(['codigo']);

        $this->assertDatabaseCount('prestaciones', 1);
    }

    // -------------------------------------------------------------------------
    // Edición
    // -------------------------------------------------------------------------

    #[Test]
    public function un_admin_puede_editar_una_prestacion_desde_filament(): void
    {
        $admin      = User::factory()->create();
        $prestacion = Prestacion::create($this->prestacionBase(['nombre' => 'Nombre original']));

        Livewire::actingAs($admin)
            ->test(EditPrestacion::class, ['record' => $prestacion->id])
            ->fillForm(['nombre' => 'Nombre actualizado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('Nombre actualizado', $prestacion->fresh()->nombre);
    }

    #[Test]
    public function editar_desde_filament_genera_una_version_en_versiones(): void
    {
        $admin      = User::factory()->create();
        $prestacion = Prestacion::create($this->prestacionBase());

        $this->assertDatabaseCount('versiones', 0);

        Livewire::actingAs($admin)
            ->test(EditPrestacion::class, ['record' => $prestacion->id])
            ->fillForm(['nombre' => 'Nombre tras edición'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('versiones', 1);
        $this->assertDatabaseHas('versiones', [
            'versionable_type' => Prestacion::class,
            'versionable_id'   => $prestacion->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Toggle inline de activa
    // -------------------------------------------------------------------------

    #[Test]
    public function el_toggle_de_activa_en_el_listado_cambia_el_estado_de_la_prestacion(): void
    {
        $admin      = User::factory()->create();
        $prestacion = Prestacion::create($this->prestacionBase(['activa' => true]));

        // ToggleColumn usa updateTableColumnState (no callTableColumnAction)
        Livewire::actingAs($admin)
            ->test(ListPrestaciones::class)
            ->call('updateTableColumnState', 'activa', (string) $prestacion->id, false);

        $this->assertFalse($prestacion->fresh()->activa);
    }
}
