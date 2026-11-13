<?php

namespace Modules\Documentos\Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Documentos\Services\CicloVidaDocumentoService;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use Modules\Intervencion\Models\Valoracion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo B — Modelo y vínculos n:M (TF-DOC-32 a 38).
 *
 * @see docs/instrucciones-cli/documentos-custodia-tests.md
 */
class VinculosDocumentoTest extends TestCase
{
    use DocumentosTestSetup;
    use RefreshDatabase;

    /**
     * Prepara actores y tipos comunes, autenticado como profesional.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararDocumentos();
        $this->actingAs($this->profesional);
    }

    /**
     * Certificado de empadronamiento vinculado a los cuatro miembros de la unidad.
     *
     * @return Documento
     */
    private function empadronamientoFamiliar(): Documento
    {
        return $this->alta([$this->ana, $this->luis, $this->eva, $this->pablo], $this->tipoEmpadronamiento);
    }

    #[Test]
    public function tf_doc_32_un_documento_se_vincula_a_varias_personas_en_una_sola_alta(): void
    {
        $documento = $this->empadronamientoFamiliar();

        $this->assertSame(1, Documento::count());
        $this->assertSame(1, DocumentoVersion::count());
        $this->assertCount(1, $this->objetosEnDisco());
        $this->assertSame(4, $documento->vinculosActivos()->count());
    }

    #[Test]
    public function tf_doc_33_todas_las_personas_vinculadas_ven_la_misma_version_vigente(): void
    {
        $documento = $this->empadronamientoFamiliar();

        $deAna = Documento::vinculadosA($this->ana)->with('versionVigente')->get();
        $dePablo = Documento::vinculadosA($this->pablo)->with('versionVigente')->get();

        $this->assertSame([$documento->id], $deAna->pluck('id')->all());
        $this->assertSame([$documento->id], $dePablo->pluck('id')->all());
        $this->assertSame($deAna->first()->versionVigente->id, $dePablo->first()->versionVigente->id);

        // Negativo: un ciudadano sin vínculo no lo ve
        $this->assertSame(0, Documento::vinculadosA($this->ciudadanoAjeno)->count());
    }

    #[Test]
    public function tf_doc_34_no_se_puede_duplicar_un_vinculo_activo(): void
    {
        $documento = $this->alta([$this->ana]);

        try {
            // Savepoint: en PostgreSQL la violación aborta la transacción del test.
            DB::transaction(fn () => DocumentoVinculo::create([
                'documento_id' => $documento->id,
                'vinculable_type' => $this->ana->getMorphClass(),
                'vinculable_id' => $this->ana->id,
                'creado_por' => $this->profesional->id,
            ]));
            $this->fail('Debió violarse el índice único de vínculos activos.');
        } catch (UniqueConstraintViolationException) {
            // esperado
        }

        $this->assertSame(1, $documento->vinculosActivos()->count());
    }

    #[Test]
    public function tf_doc_35_un_documento_no_se_vincula_a_la_unidad_de_convivencia(): void
    {
        // Por el servicio de alta: rechazado antes de tocar el almacenamiento
        try {
            $this->alta([$this->uc], $this->tipoEmpadronamiento);
            $this->fail('Debió rechazarse el vínculo a la unidad de convivencia.');
        } catch (\DomainException) {
            // esperado
        }
        $this->assertSame(0, Documento::count());
        $this->assertSame([], $this->objetosEnDisco());

        // Directamente sobre el modelo: también rechazado
        $documento = $this->alta([$this->ana], $this->tipoEmpadronamiento);

        $this->expectException(\DomainException::class);
        DocumentoVinculo::create([
            'documento_id' => $documento->id,
            'vinculable_type' => $this->uc->getMorphClass(),
            'vinculable_id' => $this->uc->id,
            'creado_por' => $this->profesional->id,
        ]);
    }

    #[Test]
    public function tf_doc_36_solo_se_vincula_a_entidades_permitidas_por_el_tipo(): void
    {
        $documento = $this->alta([$this->ana]);
        $valoracion = (new Valoracion)->forceFill(['id' => 999]);

        try {
            DocumentoVinculo::create([
                'documento_id' => $documento->id,
                'vinculable_type' => $valoracion->getMorphClass(),
                'vinculable_id' => $valoracion->id,
                'creado_por' => $this->profesional->id,
            ]);
            $this->fail('Un DNI no se puede vincular a una valoración.');
        } catch (\DomainException) {
            // esperado
        }

        $this->assertSame(1, $documento->vinculos()->count());

        // Negativo: si el tipo admite valoraciones, el mismo vínculo sí se crea
        $this->tipoDni->update(['vinculables' => ['ciudadano', 'valoracion']]);
        DocumentoVinculo::create([
            'documento_id' => $documento->id,
            'vinculable_type' => $valoracion->getMorphClass(),
            'vinculable_id' => $valoracion->id,
            'creado_por' => $this->profesional->id,
        ]);
        $this->assertSame(2, $documento->vinculos()->count());
    }

    #[Test]
    public function tf_doc_37_los_metadatos_requeridos_por_el_tipo_son_obligatorios(): void
    {
        try {
            $this->alta([$this->ana], $this->tipoInformeMedico, datos: ['fechaEmision' => today()]);
            $this->fail('Debió fallar la validación por falta de organo_emisor.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('organo_emisor', $e->errors());
        }

        $this->assertSame(0, Documento::count());
        $this->assertSame([], $this->objetosEnDisco());

        // Negativo: con ambos metadatos el alta funciona
        $documento = $this->alta([$this->ana], $this->tipoInformeMedico, datos: [
            'fechaEmision' => today(),
            'organoEmisor' => 'Hospital de prueba',
        ]);
        $this->assertSame('Hospital de prueba', $documento->organo_emisor);
    }

    #[Test]
    public function tf_doc_38_desvincular_a_una_persona_no_afecta_a_las_demas_ni_al_fichero(): void
    {
        $documento = $this->empadronamientoFamiliar();
        $objetos = $this->objetosEnDisco();

        app(CicloVidaDocumentoService::class)->desvincular($documento, $this->luis, $this->profesional);

        $vinculoLuis = $documento->vinculos()->where('vinculable_id', $this->luis->id)->firstOrFail();
        $this->assertFalse($vinculoLuis->activo);
        $this->assertNotNull($vinculoLuis->fecha_baja);
        $this->assertSame($this->profesional->id, $vinculoLuis->baja_por);

        $this->assertSame(3, $documento->vinculosActivos()->count());
        $this->assertModelExists($documento);
        $this->assertSame(1, $documento->versiones()->count());
        $this->assertSame($objetos, $this->objetosEnDisco());
        $this->assertSame(0, Documento::vinculadosA($this->luis)->count());
    }
}
