<?php

namespace Modules\Documentos\Tests\Feature;

use App\Filament\Resources\TipoDocumentalResource\Pages\CreateTipoDocumental;
use App\Filament\Resources\TipoDocumentalResource\Pages\EditTipoDocumental;
use App\Models\CatalogoSistema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Documentos\Database\Seeders\TiposDocumentalesSeeder;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Enums\PoliticaVersiones;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo A — Tipos documentales (TF-DOC-26 a 31).
 *
 * @see docs/instrucciones-cli/documentos-custodia-tests.md
 */
class TiposDocumentalesTest extends TestCase
{
    use DocumentosTestSetup;
    use RefreshDatabase;

    /**
     * Prepara actores y tipos comunes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararDocumentos();
    }

    #[Test]
    public function tf_doc_26_crear_un_tipo_documental_desde_filament(): void
    {
        // Dado adm_sistema en el panel, cuando crea un tipo
        Livewire::actingAs($this->admin)
            ->test(CreateTipoDocumental::class)
            ->fillForm([
                'codigo' => 'libro_familia',
                'nombre' => 'Libro de familia',
                'familia' => FamiliaDocumental::AportadoCiudadano->value,
                'origen_eni' => OrigenEni::Ciudadano->value,
                'caduca' => false,
                'politica_versiones' => PoliticaVersiones::Conservar->value,
                'max_bytes' => 20,
                'max_paginas' => 30,
                'vinculables' => ['ciudadano'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Entonces existe con esos valores y hay auditoría de la creación
        $tipo = TipoDocumental::where('codigo', 'libro_familia')->firstOrFail();
        $this->assertSame(FamiliaDocumental::AportadoCiudadano, $tipo->familia);
        $this->assertSame(OrigenEni::Ciudadano, $tipo->origen_eni);
        $this->assertFalse($tipo->caduca);
        $this->assertSame(30, $tipo->max_paginas);
        $this->assertSame(20 * 1024 * 1024, $tipo->max_bytes);
        $this->assertDatabaseHas('audits', [
            'auditable_type' => TipoDocumental::class,
            'auditable_id' => $tipo->id,
            'accion' => 'crear',
            'user_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function tf_doc_27_un_profesional_no_puede_gestionar_tipos_documentales(): void
    {
        $antes = TipoDocumental::count();

        Livewire::actingAs($this->profesional)
            ->test(CreateTipoDocumental::class)
            ->assertForbidden();

        Livewire::actingAs($this->profesional)
            ->test(EditTipoDocumental::class, ['record' => $this->tipoDni->getRouteKey()])
            ->assertForbidden();

        $this->assertSame($antes, TipoDocumental::count());
        $this->assertSame('Dni', $this->tipoDni->fresh()->nombre);
    }

    #[Test]
    public function tf_doc_28_el_codigo_es_unico(): void
    {
        $antes = TipoDocumental::count();

        Livewire::actingAs($this->admin)
            ->test(CreateTipoDocumental::class)
            ->fillForm([
                'codigo' => 'dni',
                'nombre' => 'Otro DNI',
                'familia' => FamiliaDocumental::AportadoCiudadano->value,
                'origen_eni' => OrigenEni::Ciudadano->value,
                'politica_versiones' => PoliticaVersiones::Conservar->value,
                'max_bytes' => 20,
                'max_paginas' => 50,
                'vinculables' => ['ciudadano'],
            ])
            ->call('create')
            ->assertHasFormErrors(['codigo' => 'unique']);

        $this->assertSame($antes, TipoDocumental::count());
    }

    #[Test]
    public function tf_doc_29_codigo_familia_y_origen_son_inmutables_si_hay_documentos(): void
    {
        $this->actingAs($this->admin);
        $this->alta([$this->ana]);

        $cambios = [
            'codigo' => 'dni_nuevo',
            'familia' => FamiliaDocumental::InformeProfesional,
            'origen_eni' => OrigenEni::Administracion,
        ];

        foreach ($cambios as $campo => $valor) {
            $tipo = $this->tipoDni->fresh();

            try {
                $tipo->update([$campo => $valor]);
                $this->fail("Debió rechazarse el cambio de {$campo}.");
            } catch (\DomainException) {
                // esperado
            }
        }

        $tipo = $this->tipoDni->fresh();
        $this->assertSame('dni', $tipo->codigo);
        $this->assertSame(FamiliaDocumental::AportadoCiudadano, $tipo->familia);
        $this->assertSame(OrigenEni::Ciudadano, $tipo->origen_eni);

        // Negativo: en un tipo sin documentos los tres cambios se permiten
        $sinDocumentos = $this->tipoEmpadronamiento;
        $sinDocumentos->update($cambios);
        $this->assertSame('dni_nuevo', $sinDocumentos->fresh()->codigo);
        $this->assertSame(FamiliaDocumental::InformeProfesional, $sinDocumentos->fresh()->familia);
    }

    #[Test]
    public function tf_doc_30_un_tipo_con_documentos_no_se_borra_solo_se_desactiva(): void
    {
        $this->actingAs($this->admin);
        $documento = $this->alta([$this->ana]);

        // El borrado falla
        try {
            $this->tipoDni->delete();
            $this->fail('Debió rechazarse el borrado.');
        } catch (\DomainException) {
            // esperado
        }
        $this->assertModelExists($this->tipoDni);

        // La desactivación funciona y el tipo deja de ofrecerse
        $this->tipoDni->update(['activo' => false]);
        $this->assertFalse(TipoDocumental::activos()->whereKey($this->tipoDni->id)->exists());

        // Sus documentos siguen accesibles
        $documento = Documento::findOrFail($documento->id);
        $this->assertSame(
            hash_file('sha256', $this->fixture('valido.pdf')),
            hash('sha256', app(LecturaDocumentoService::class)->contenido($documento->versionVigente))
        );

        // Negativo: un tipo sin documentos sí se borra
        $this->tipoConConservacion->delete();
        $this->assertModelMissing($this->tipoConConservacion);
    }

    #[Test]
    public function tf_doc_31_el_seeder_migra_los_tipos_del_catalogo_de_forma_idempotente(): void
    {
        TipoDocumental::query()->delete();

        foreach (['certificado', 'resolucion_administrativa', 'otro'] as $orden => $clave) {
            CatalogoSistema::create(['grupo' => 'documento.tipo', 'clave' => $clave, 'etiqueta' => ucfirst($clave), 'orden' => $orden, 'activo' => true]);
        }

        $this->seed(TiposDocumentalesSeeder::class);
        $this->seed(TiposDocumentalesSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['certificado', 'resolucion_administrativa', 'otro', 'informe_profesional'],
            TipoDocumental::pluck('codigo')->all()
        );

        $certificado = TipoDocumental::where('codigo', 'certificado')->firstOrFail();
        $this->assertSame(FamiliaDocumental::AportadoCiudadano, $certificado->familia);
        $this->assertSame(OrigenEni::Ciudadano, $certificado->origen_eni);
        $this->assertFalse($certificado->caduca);
        $this->assertSame(PoliticaVersiones::Conservar, $certificado->politica_versiones);
        $this->assertNull($certificado->conservacion_anyos);

        $informe = TipoDocumental::where('codigo', 'informe_profesional')->firstOrFail();
        $this->assertSame(FamiliaDocumental::InformeProfesional, $informe->familia);
        $this->assertSame(OrigenEni::Administracion, $informe->origen_eni);
        $this->assertTrue($informe->requiere_firma);
    }
}
