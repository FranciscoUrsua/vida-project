<?php

namespace Modules\Documentos\Tests\Feature;

use App\Models\Audit;
use App\Services\CiudadanoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Enums\EstadoVersion;
use Modules\Documentos\Enums\MotivoRetencion;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Services\CicloVidaDocumentoService;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Services\RetencionService;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo E — Ciclo de vida: versiones, caducidad y purga (TF-DOC-59 a 66).
 *
 * @see docs/instrucciones-cli/documentos-custodia-tests.md
 */
class CicloVidaDocumentoTest extends TestCase
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
     * Sube una nueva versión con el fixture indicado.
     *
     * @param Documento $documento Documento que recibe la versión.
     * @param string $fichero Fixture de origen.
     *
     * @return DocumentoVersion
     */
    private function nuevaVersion(Documento $documento, string $fichero = 'valido.pdf'): DocumentoVersion
    {
        return app(CicloVidaDocumentoService::class)->nuevaVersion($documento, $this->fixture($fichero), $this->profesional);
    }

    /**
     * Indica si el objeto de la versión sigue en el disco.
     *
     * @param DocumentoVersion $version Versión.
     *
     * @return bool
     */
    private function objetoExiste(DocumentoVersion $version): bool
    {
        return app(AlmacenDocumentos::class)->existe($version->clave_almacenamiento, $version->disco);
    }

    #[Test]
    public function tf_doc_59_una_nueva_version_sustituye_a_la_vigente_para_todas_las_personas(): void
    {
        $documento = $this->alta([$this->ana, $this->luis, $this->eva, $this->pablo], $this->tipoEmpadronamiento);
        $v1 = $documento->versionVigente;

        $v2 = $this->nuevaVersion($documento);

        $this->assertSame(2, $v2->numero);
        $this->assertSame(EstadoVersion::Vigente, $v2->fresh()->estado);
        $this->assertSame(EstadoVersion::Sustituida, $v1->fresh()->estado);

        foreach ([$this->ana, $this->luis, $this->eva, $this->pablo] as $persona) {
            $suyo = Documento::vinculadosA($persona)->with('versionVigente')->sole();
            $this->assertSame($v2->id, $suyo->versionVigente->id, "{$persona->id} debe ver la versión 2.");
        }
    }

    #[Test]
    public function tf_doc_60_solo_puede_haber_una_version_vigente_por_documento(): void
    {
        $documento = $this->alta([$this->ana], $this->tipoInformeMedico, datos: ['fechaEmision' => now(), 'organoEmisor' => 'SERMAS']);
        $v2 = $this->nuevaVersion($documento);
        $v1 = $documento->versiones()->where('numero', 1)->sole();

        $this->expectException(QueryException::class);

        // Saltándose nuevaVersion: el índice parcial único lo impide.
        $v1->update(['estado' => EstadoVersion::Vigente]);
        $this->assertSame(EstadoVersion::Vigente, $v2->fresh()->estado);
    }

    #[Test]
    public function tf_doc_61_con_politica_conservar_la_version_anterior_se_mantiene_legible(): void
    {
        $documento = $this->alta([$this->ana], $this->tipoInformeMedico, datos: ['fechaEmision' => now(), 'organoEmisor' => 'SERMAS']);
        $v1 = $documento->versionVigente;

        $this->nuevaVersion($documento);
        $v1->refresh();

        $this->assertSame(EstadoVersion::Sustituida, $v1->estado);
        $this->assertNotNull($v1->clave_cifrada);
        $this->assertTrue($this->objetoExiste($v1));
        $this->assertStringStartsWith('%PDF', app(LecturaDocumentoService::class)->contenido($v1));
    }

    #[Test]
    public function tf_doc_62_con_politica_purgar_la_version_anterior_no_retenida_se_purga(): void
    {
        $documento = $this->alta([$this->ana], $this->tipoDni);
        $v1 = $documento->versionVigente;
        $hash = $v1->hash_sha256;
        $captura = $v1->fecha_captura->toIso8601String();

        $this->nuevaVersion($documento);
        $v1->refresh();

        $this->assertSame(EstadoVersion::Purgada, $v1->estado);
        $this->assertNull($v1->clave_cifrada);
        $this->assertFalse($this->objetoExiste($v1));
        // Los metadatos se conservan.
        $this->assertSame(1, $v1->numero);
        $this->assertSame($hash, $v1->hash_sha256);
        $this->assertSame($captura, $v1->fecha_captura->toIso8601String());

        $this->assertTrue(
            Audit::where('auditable_type', $v1->getMorphClass())->where('auditable_id', $v1->id)
                ->where('accion', 'borrar')->where('user_id', $this->profesional->id)->exists(),
            'La purga debe quedar en la auditoría como «borrar».'
        );
    }

    #[Test]
    public function tf_doc_63_con_politica_purgar_una_version_retenida_no_se_purga(): void
    {
        $documento = $this->alta([$this->ana], $this->tipoDni);
        $v1 = $documento->versionVigente;
        app(RetencionService::class)->retener($documento, MotivoRetencion::Manual, $this->admin);

        $this->nuevaVersion($documento);
        $v1->refresh();

        $this->assertSame(EstadoVersion::Sustituida, $v1->estado);
        $this->assertNotNull($v1->clave_cifrada);
        $this->assertTrue($this->objetoExiste($v1));
        $this->assertStringStartsWith('%PDF', app(LecturaDocumentoService::class)->contenido($v1));
    }

    #[Test]
    public function tf_doc_64_la_fecha_de_validez_se_calcula_por_defecto_y_se_recalcula_con_cada_version(): void
    {
        $hoy = today();
        $documento = $this->alta([$this->ana], $this->tipoEmpadronamiento);
        $this->assertSame($hoy->copy()->addDays(90)->toDateString(), $documento->fresh()->fecha_validez->toDateString());

        $this->travel(30)->days();
        $this->nuevaVersion($documento);

        $this->assertSame($hoy->copy()->addDays(30 + 90)->toDateString(), $documento->fresh()->fecha_validez->toDateString());
    }

    #[Test]
    public function tf_doc_65_un_documento_caducado_se_detecta_y_no_se_borra(): void
    {
        $documento = $this->alta([$this->ana], $this->tipoEmpadronamiento);
        $documento->update(['fecha_validez' => today()->subDay()]);
        $vigente = $this->alta([$this->luis], $this->tipoEmpadronamiento);

        $this->assertSame([$documento->id], Documento::caducados()->pluck('id')->all());
        $this->assertTrue($documento->fresh()->estaCaducado());
        $this->assertFalse($vigente->fresh()->estaCaducado());

        $version = $documento->fresh()->versionVigente;
        $this->assertSame(EstadoVersion::Vigente, $version->estado);
        $this->assertTrue($this->objetoExiste($version));
    }

    #[Test]
    public function tf_doc_66_dar_de_baja_un_ciudadano_no_borra_sus_documentos(): void
    {
        $propio = $this->alta([$this->ana], $this->tipoDni);
        $compartido = $this->alta([$this->ana, $this->luis], $this->tipoEmpadronamiento);

        $this->actingAs($this->admin);
        app(CiudadanoService::class)->eliminar($this->ana->id);
        $this->assertSoftDeleted($this->ana);

        // Los vínculos de Ana quedan inactivos…
        $this->assertSame(0, Documento::vinculadosA($this->ana)->count());
        $this->assertSame(2, $propio->vinculos()->count() + $compartido->vinculos()->where('activo', false)->count());
        // …pero documentos, versiones y objetos siguen existiendo.
        foreach ([$propio, $compartido] as $documento) {
            $version = $documento->fresh()->versionVigente;
            $this->assertNotNull($version);
            $this->assertTrue($this->objetoExiste($version));
        }
        // Luis sigue viendo el compartido.
        $this->assertSame([$compartido->id], Documento::vinculadosA($this->luis)->pluck('id')->all());
    }
}
