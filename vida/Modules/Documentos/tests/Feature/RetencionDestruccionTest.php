<?php

namespace Modules\Documentos\Tests\Feature;

use App\Filament\Resources\PropuestaEliminacionResource;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\EstadoPropuestaEliminacion;
use Modules\Documentos\Enums\EstadoVersion;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\MotivoRetencion;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Models\ActaEliminacion;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Models\PropuestaEliminacion;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\CicloVidaDocumentoService;
use Modules\Documentos\Services\DestruccionDocumentosService;
use Modules\Documentos\Services\RetencionService;
use Modules\Documentos\Services\ServicioFirmaInforme;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo F — Retenciones, informes firmados y destrucción (TF-DOC-67 a 73).
 *
 * @see docs/instrucciones-cli/documentos-custodia-tests.md
 */
class RetencionDestruccionTest extends TestCase
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
     * Versión vigente de un documento nuevo con la fecha de captura desplazada al pasado.
     *
     * @param TipoDocumental $tipo Tipo del documento.
     * @param int $anyos Años de antigüedad de la captura.
     *
     * @return DocumentoVersion
     */
    private function versionCapturadaHace(TipoDocumental $tipo, int $anyos): DocumentoVersion
    {
        $version = $this->alta([$this->ana], $tipo)->versionVigente;
        $version->update(['fecha_captura' => now()->subYears($anyos)]);

        return $version->fresh();
    }

    /**
     * Escenario de TF-DOC-70: A vencida, B vencida y retenida, C dentro de plazo, D sin plazo.
     *
     * @return array{a: DocumentoVersion, b: DocumentoVersion, c: DocumentoVersion, d: DocumentoVersion}
     */
    private function escenarioDestruccion(): array
    {
        $sinPlazo = $this->tipo('sin_plazo', ['conservacion_anyos' => null]);

        $versiones = [
            'a' => $this->versionCapturadaHace($this->tipoConConservacion, 6),
            'b' => $this->versionCapturadaHace($this->tipoConConservacion, 6),
            'c' => $this->versionCapturadaHace($this->tipoConConservacion, 2),
            'd' => $this->versionCapturadaHace($sinPlazo, 20),
        ];
        app(RetencionService::class)->retener($versiones['b']->documento, MotivoRetencion::Manual, $this->admin);

        return $versiones;
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

    /**
     * Informe firmado con el mecanismo de TF-DOC-11 a 16 (stub de AutoFirma con valido.pdf).
     *
     * @return Informe
     */
    private function informeFirmado(): Informe
    {
        $this->tipo('informe_profesional', [
            'familia' => FamiliaDocumental::InformeProfesional,
            'origen_eni' => OrigenEni::Administracion,
            'requiere_firma' => true,
        ]);
        $uo = UnidadOrganizativa::create(['nombre' => 'CSS Informes', 'tipo' => 'centro', 'activa' => true]);
        $plantilla = PlantillaInforme::create([
            'nombre' => 'Plantilla de prueba',
            'tipo_informe' => TipoInforme::InformeSocial->value,
            'unidad_organizativa_id' => $uo->id,
            'secciones' => [['id' => 'valoracion', 'titulo' => 'Valoración', 'tipo' => 'texto_libre', 'obligatorio' => true]],
            'activa' => true,
            'creada_por' => $this->profesional->id,
        ]);
        $informe = Informe::create([
            'plantilla_id' => $plantilla->id,
            'ciudadano_id' => $this->ana->id,
            'autor_id' => $this->profesional->id,
            'estado' => EstadoInforme::Borrador->value,
            'contenido' => ['valoracion' => 'Valoración de prueba.'],
        ]);

        // Como en TF-DOC-11 a 16, sin sesión: el ámbito de acceso a ciudadanos llega con el grupo G.
        auth()->forgetGuards();
        $firmado = app(ServicioFirmaInforme::class)->firmar($informe, base64_encode((string) file_get_contents($this->fixture('valido.pdf'))));
        $this->actingAs($this->profesional);

        return $firmado;
    }

    #[Test]
    public function tf_doc_67_una_retencion_con_fecha_fin_pasada_ya_no_retiene(): void
    {
        $documento = $this->alta([$this->ana]);
        $retenciones = app(RetencionService::class);

        $retenciones->retener($documento, MotivoRetencion::Manual, $this->admin, hasta: now()->subDay());
        $this->assertFalse($documento->estaRetenido());
        $this->assertFalse($documento->versionVigente->estaRetenida());

        $retenciones->retener($documento, MotivoRetencion::Manual, $this->admin, hasta: now()->addDay());
        $this->assertTrue($documento->estaRetenido());

        $indefinida = $this->alta([$this->luis]);
        $retenciones->retener($indefinida, MotivoRetencion::Manual, $this->admin);
        $this->assertTrue($indefinida->estaRetenido());
    }

    #[Test]
    public function tf_doc_68_el_documento_de_un_informe_firmado_no_admite_nuevas_versiones(): void
    {
        $documento = $this->informeFirmado()->documento;

        try {
            app(CicloVidaDocumentoService::class)->nuevaVersion($documento, $this->fixture('valido.pdf'), $this->profesional);
            $this->fail('El documento de un informe firmado no debe admitir versiones nuevas.');
        } catch (\DomainException) {
            // esperado
        }

        $this->assertSame(1, $documento->versiones()->count());
        $this->assertCount(1, $this->objetosEnDisco());
    }

    #[Test]
    public function tf_doc_69_firmar_un_informe_genera_un_documento_cifrado_con_canal_generado(): void
    {
        $informe = $this->informeFirmado();
        $documento = $informe->documento;
        $version = $documento->versionVigente;

        $this->assertSame(FamiliaDocumental::InformeProfesional, $documento->tipo->familia);
        $this->assertSame(CanalCaptura::Generado, $version->canal);
        $this->assertSame($informe->id, $version->informe_id);
        $this->assertSame($informe->plantilla_id, $version->plantilla_informe_id);
        $this->assertSame([$documento->id], Documento::vinculadosA($this->ana)->pluck('id')->all());
        $this->assertTrue($this->objetoExiste($version));
        $this->assertStringStartsNotWith('%PDF', \Illuminate\Support\Facades\Storage::disk('documentos')->get($this->objetosEnDisco()[0]));
    }

    #[Test]
    public function tf_doc_70_la_propuesta_solo_incluye_versiones_vencidas_y_sin_retenciones(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d] = $this->escenarioDestruccion();

        Artisan::call('documentos:proponer-destruccion');

        $propuesta = PropuestaEliminacion::sole();
        $this->assertSame(EstadoPropuestaEliminacion::Pendiente, $propuesta->estado);
        $this->assertSame([$a->id], $propuesta->versiones);

        foreach ([$a, $b, $c, $d] as $version) {
            $this->assertSame(EstadoVersion::Vigente, $version->fresh()->estado);
            $this->assertTrue($this->objetoExiste($version));
        }

        // Volver a ejecutarlo no duplica versiones ya propuestas.
        Artisan::call('documentos:proponer-destruccion');
        $this->assertSame(1, PropuestaEliminacion::count());
    }

    #[Test]
    public function tf_doc_71_aprobar_una_propuesta_destruye_por_crypto_shredding_y_levanta_acta(): void
    {
        ['a' => $a, 'b' => $b] = $this->escenarioDestruccion();
        $a->documento->update(['metadatos' => []]);
        $a->forceFill(['nombre_original' => 'dni_ana_secreto.pdf'])->save();
        $propuesta = app(DestruccionDocumentosService::class)->proponer();

        $resuelta = app(DestruccionDocumentosService::class)->aprobar($propuesta, $this->admin);

        $a->refresh();
        $this->assertSame(EstadoVersion::Destruida, $a->estado);
        $this->assertNull($a->clave_cifrada);
        $this->assertFalse($this->objetoExiste($a));
        $this->assertSame(EstadoDocumento::Destruido, $a->documento->fresh()->estado);
        // B no estaba en la propuesta: intacta.
        $this->assertTrue($this->objetoExiste($b));

        $acta = $resuelta->acta;
        $this->assertInstanceOf(ActaEliminacion::class, $acta);
        $this->assertSame(EstadoPropuestaEliminacion::Aprobada, $resuelta->estado);
        // jsonb no conserva el orden de las claves: se compara el contenido.
        $this->assertEquals([[
            'documento_uuid' => $a->documento->uuid,
            'numero_version' => 1,
            'tipo_documental_codigo' => 'con_conservacion',
            'fecha_captura' => $a->fecha_captura->toIso8601String(),
            'hash_sha256' => $a->hash_sha256,
            'ids_ciudadanos_vinculados' => [$this->ana->id],
        ]], $acta->detalle);

        $enBruto = json_encode($acta->getAttributes());
        $this->assertStringNotContainsString('dni_ana_secreto', $enBruto);
        $this->assertStringNotContainsString('%PDF', $enBruto);
    }

    #[Test]
    public function tf_doc_72_una_retencion_creada_despues_de_la_propuesta_impide_la_destruccion(): void
    {
        ['a' => $a] = $this->escenarioDestruccion();
        $propuesta = app(DestruccionDocumentosService::class)->proponer();
        $this->assertSame([$a->id], $propuesta->versiones);

        app(RetencionService::class)->retener($a->documento, MotivoRetencion::Manual, $this->admin);
        $resuelta = app(DestruccionDocumentosService::class)->aprobar($propuesta, $this->admin);

        $a->refresh();
        $this->assertSame(EstadoVersion::Vigente, $a->estado);
        $this->assertNotNull($a->clave_cifrada);
        $this->assertTrue($this->objetoExiste($a));
        $this->assertSame([$a->id], $resuelta->excluidas);
        $this->assertNull($resuelta->acta, 'Sin versiones destruidas no se levanta acta.');
        $this->assertSame(0, ActaEliminacion::count());
    }

    #[Test]
    public function tf_doc_73_las_actas_son_inmutables_y_la_aprobacion_es_solo_de_administrador(): void
    {
        $acta = ActaEliminacion::create([
            'numero' => ActaEliminacion::siguienteNumero((int) now()->year),
            'aprobada_por' => $this->admin->id,
            'aprobada_en' => now(),
            'motivo' => 'Prueba',
            'detalle' => [],
        ]);

        try {
            $acta->update(['motivo' => 'Cambiado']);
            $this->fail('Un acta no se puede modificar.');
        } catch (\LogicException) {
            // esperado
        }

        try {
            $acta->delete();
            $this->fail('Un acta no se puede borrar.');
        } catch (\LogicException) {
            // esperado
        }
        $this->assertSame('Prueba', $acta->fresh()->motivo);

        ['a' => $a] = $this->escenarioDestruccion();
        $propuesta = app(DestruccionDocumentosService::class)->proponer();

        try {
            app(DestruccionDocumentosService::class)->aprobar($propuesta, $this->profesional);
            $this->fail('Un profesional no puede aprobar una propuesta de destrucción.');
        } catch (AuthorizationException) {
            // esperado
        }

        $this->assertSame(EstadoPropuestaEliminacion::Pendiente, $propuesta->fresh()->estado);
        $this->assertSame(EstadoVersion::Vigente, $a->fresh()->estado);
        $this->assertTrue($this->objetoExiste($a));
    }

    #[Test]
    public function el_backoffice_de_propuestas_solo_lo_ve_el_administrador(): void
    {
        $this->escenarioDestruccion();
        $propuesta = app(DestruccionDocumentosService::class)->proponer();

        $this->actingAs($this->admin)->get(PropuestaEliminacionResource::getUrl('index'))->assertOk();
        $this->actingAs($this->admin)->get(PropuestaEliminacionResource::getUrl('view', ['record' => $propuesta]))
            ->assertOk()
            ->assertSee($propuesta->versionesPropuestas()->first()->documento->uuid);

        // Intervención no entra en el panel; supervisión entra, pero no en este recurso.
        $this->actingAs($this->profesional)->get(PropuestaEliminacionResource::getUrl('index'))->assertRedirect('/admin/login');
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervision');
        $this->actingAs($supervisor)->get(PropuestaEliminacionResource::getUrl('index'))->assertForbidden();
    }
}
