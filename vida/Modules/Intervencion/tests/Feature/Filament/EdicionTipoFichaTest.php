<?php

namespace Modules\Intervencion\Tests\Feature\Filament;

use App\Filament\Resources\TipoFichaResource;
use App\Filament\Resources\TipoFichaResource\Pages\CreateTipoFicha;
use App\Filament\Resources\TipoFichaResource\Pages\EditTipoFicha;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Edición de una ficha de valoración en Filament: los campos añadidos en el Builder se
 * guardan (incidencia del 17 nov 2026: «Guardado» sin guardar los campos nuevos).
 */
class EdicionTipoFichaTest extends TestCase
{
    use RefreshDatabase;

    private TipoFicha $ficha;

    /**
     * Administrador y una ficha con un campo.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $admin = User::create(['email' => 'admin@vida360.test', 'password' => 'secreto', 'email_verified_at' => now(), 'primer_acceso' => false]);
        $admin->syncRoles(['adm_sistema']);
        $this->actingAs($admin);

        $this->ficha = TipoFicha::create([
            'nombre' => 'Vivienda',
            'activo' => true,
            'schema' => ['campos' => [
                ['id' => 'regimen', 'tipo' => 'texto', 'etiqueta' => 'Régimen', 'obligatorio' => false, 'orden' => 1],
            ]],
        ]);
    }

    /**
     * Un campo añadido en el Builder queda en el schema al guardar.
     *
     * @return void
     */
    #[Test]
    public function anadir_un_campo_y_guardar_lo_persiste(): void
    {
        $componente = Livewire::test(EditTipoFicha::class, ['record' => $this->ficha->getRouteKey()]);

        $bloques = $componente->get('data.schema');
        $this->assertCount(1, $bloques);

        $bloques[(string) Str::uuid()] = ['type' => 'numero', 'data' => ['etiqueta' => 'Metros', 'descripcion' => null, 'obligatorio' => true, 'unidad' => 'm²']];

        $componente->set('data.schema', $bloques)
            ->call('save')
            ->assertHasNoErrors();

        $campos = $this->ficha->fresh()->schema['campos'];
        $this->assertCount(2, $campos);
        $this->assertSame(['regimen', 'metros'], array_column($campos, 'id'));
        $this->assertSame('numero', $campos[1]['tipo']);
    }

    /**
     * Guardar sin tocar nada conserva los campos (antes los borraba todos). [negativo]
     *
     * @return void
     */
    #[Test]
    public function guardar_sin_cambios_conserva_los_campos(): void
    {
        Livewire::test(EditTipoFicha::class, ['record' => $this->ficha->getRouteKey()])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['regimen'], array_column($this->ficha->fresh()->schema['campos'], 'id'));
    }

    /**
     * Un campo de selección nuevo se guarda con sus opciones como strings, y reordenar
     * cambia el orden guardado.
     *
     * @return void
     */
    #[Test]
    public function seleccion_con_opciones_y_orden(): void
    {
        $componente = Livewire::test(EditTipoFicha::class, ['record' => $this->ficha->getRouteKey()]);
        $bloques = $componente->get('data.schema');
        $nuevo = [(string) Str::uuid() => ['type' => 'select', 'data' => [
            'etiqueta' => 'Tenencia', 'descripcion' => null, 'obligatorio' => false,
            'opciones' => [(string) Str::uuid() => ['valor' => 'Propiedad'], (string) Str::uuid() => ['valor' => 'Alquiler']],
        ]]];

        $componente->set('data.schema', $nuevo + $bloques)->call('save')->assertHasNoErrors();

        $campos = $this->ficha->fresh()->schema['campos'];
        $this->assertSame(['tenencia', 'regimen'], array_column($campos, 'id'));
        $this->assertSame(['Propiedad', 'Alquiler'], $campos[0]['opciones']);
        $this->assertSame([1, 2], array_column($campos, 'orden'));
    }

    /**
     * En una ficha con datos cumplimentados se puede añadir un campo desde Filament.
     *
     * @return void
     */
    #[Test]
    public function anadir_campo_a_ficha_con_datos(): void
    {
        $this->ficha->fichas()->create(Ficha::factory()->make(['tipo_ficha_id' => $this->ficha->id])->getAttributes());
        $this->assertTrue($this->ficha->tieneFichasAsociadas());

        $componente = Livewire::test(EditTipoFicha::class, ['record' => $this->ficha->getRouteKey()]);
        $bloques = $componente->get('data.schema');
        $bloques[(string) Str::uuid()] = ['type' => 'fecha', 'data' => ['etiqueta' => 'Fecha de desahucio', 'descripcion' => null, 'obligatorio' => false]];

        $componente->set('data.schema', $bloques)->call('save')->assertHasNoErrors();

        $this->assertSame(['regimen', 'fecha_de_desahucio'], array_column($this->ficha->fresh()->schema['campos'], 'id'));
    }

    /**
     * Crear una ficha con campos los guarda.
     *
     * @return void
     */
    #[Test]
    public function crear_ficha_con_campos(): void
    {
        Livewire::test(CreateTipoFicha::class)
            ->fillForm(['nombre' => 'Empleo', 'uso' => 'valoracion', 'activo' => true])
            ->set('data.schema', [(string) Str::uuid() => ['type' => 'booleano', 'data' => ['etiqueta' => 'Tiene empleo', 'descripcion' => null, 'obligatorio' => true]]])
            ->call('create')
            ->assertHasNoErrors();

        $campos = TipoFicha::where('nombre', 'Empleo')->sole()->schema['campos'];
        $this->assertSame(['tiene_empleo'], array_column($campos, 'id'));
    }

    /**
     * Un bloque con forma inesperada no se descarta en silencio: la conversión falla.
     *
     * @return void
     */
    #[Test]
    public function bloque_con_forma_inesperada_no_vacia_la_ficha(): void
    {
        $this->expectException(LogicException::class);

        TipoFichaResource::convertirSchemaBlocks([[['id' => 'regimen', 'tipo' => 'texto']]]);
    }
}
