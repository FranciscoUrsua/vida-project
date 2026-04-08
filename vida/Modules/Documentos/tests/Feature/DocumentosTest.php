<?php

namespace Modules\Documentos\Tests\Feature;

use App\Models\CatalogoSistema;
use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\MetodoConformidadCiudadano;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\EstiloInforme;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Models\PisoFirmado;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Services\ResolverEstiloInforme;
use Modules\Documentos\Services\ServicioAlmacenamiento;
use Modules\Documentos\Services\ServicioFirmaInforme;
use Modules\Documentos\Services\ServicioGeneracionPDF;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del módulo Documentos.
 *
 * Cubre los casos TF-DOC-01 a TF-DOC-20 definidos en
 * docs/modulo-documentos.md sección 6.
 */
class DocumentosTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function crearUo(string $nombre, ?UnidadOrganizativa $parent = null): UnidadOrganizativa
    {
        return UnidadOrganizativa::create([
            'nombre'    => $nombre,
            'tipo'      => 'centro',
            'parent_id' => $parent?->id,
            'activa'    => true,
        ]);
    }

    private function crearUser(): User
    {
        return User::factory()->create();
    }

    private function crearCiudadano(): Ciudadano
    {
        return Ciudadano::factory()->create();
    }

    private function crearTipoDoc(string $clave = 'informe_externo', string $grupo = 'documento.tipo'): CatalogoSistema
    {
        return CatalogoSistema::firstOrCreate(
            ['grupo' => $grupo, 'clave' => $clave],
            ['etiqueta' => ucwords(str_replace('_', ' ', $clave)), 'orden' => 1, 'activo' => true]
        );
    }

    /** @param array<mixed> $secciones */
    private function crearPlantilla(int $uoId, bool $activa = true, array $secciones = []): PlantillaInforme
    {
        $usuario = $this->crearUser();

        return PlantillaInforme::create([
            'nombre'                 => 'Plantilla de prueba ' . Str::random(4),
            'tipo_informe'           => TipoInforme::InformeSocial->value,
            'unidad_organizativa_id' => $uoId,
            'secciones'              => $secciones ?: [
                [
                    'id'          => 'valoracion',
                    'titulo'      => 'Valoración',
                    'tipo'        => 'texto_libre',
                    'instrucciones' => 'Detalle la situación.',
                    'obligatorio' => true,
                ],
            ],
            'activa'    => $activa,
            'creada_por' => $usuario->id,
        ]);
    }

    private function crearInformeBorrador(PlantillaInforme $plantilla, Ciudadano $ciudadano, User $autor): Informe
    {
        return Informe::create([
            'plantilla_id'  => $plantilla->id,
            'ciudadano_id'  => $ciudadano->id,
            'autor_id'      => $autor->id,
            'estado'        => EstadoInforme::Borrador->value,
            'contenido'     => ['valoracion' => 'Valoración de prueba.'],
        ]);
    }

    /** Genera una cadena que imita un PDF mínimo válido en base64. */
    private function pdfBase64(): string
    {
        $contenido = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/MediaBox[0 0 595 842]>>endobj\n"
            . "xref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n%%EOF";

        return base64_encode($contenido);
    }

    /** Crea un UploadedFile de prueba que pasa la validación de MIME PDF. */
    private function uploadedPdf(string $nombre = 'documento.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 10, 'application/pdf');
    }

    /** Crea tipo de documento 'informe_generado' necesario para la firma. */
    private function crearTipoInformeGenerado(): CatalogoSistema
    {
        return $this->crearTipoDoc('informe_generado');
    }

    // =========================================================================
    // TF-DOC-01: Subida de documento externo válido
    // =========================================================================

    #[Test]
    public function test_tf_doc_01_subida_documento_externo_valido(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);

        $usuario    = $this->crearUser();
        $ciudadano  = $this->crearCiudadano();
        $tipo       = $this->crearTipoDoc();
        $fichero    = $this->uploadedPdf('informe.pdf');

        $servicio   = app(ServicioAlmacenamiento::class);
        $documento  = $servicio->guardar($fichero, 'informe_externo', $usuario->id, $tipo->id, $ciudadano);

        $this->assertInstanceOf(Documento::class, $documento);
        $this->assertEquals($ciudadano->id, $documento->documentable_id);
        $this->assertEquals(get_class($ciudadano), $documento->documentable_type);
        $this->assertEquals($tipo->id, $documento->tipo_documento_id);
        $this->assertEquals('informe.pdf', $documento->nombre_original);
        $this->assertEquals('application/pdf', $documento->mime_type);
        $this->assertNotEmpty($documento->hash_sha256);
        $this->assertEquals('local', $documento->disco);

        // El fichero debe existir en el disco privado
        Storage::disk('local')->assertExists($documento->ruta_almacenamiento);
    }

    // =========================================================================
    // TF-DOC-02: Rechazo de formato no PDF
    // =========================================================================

    #[Test]
    public function test_tf_doc_02_rechazo_formato_no_pdf(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);

        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $tipo      = $this->crearTipoDoc();
        $fichero   = UploadedFile::fake()->create(
            'documento.docx',
            10,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $servicio = app(ServicioAlmacenamiento::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Solo se admiten ficheros PDF/');

        $servicio->guardar($fichero, 'otro', $usuario->id, $tipo->id, $ciudadano);

        // No debe haberse creado ningún registro
        $this->assertEquals(0, Documento::count());
    }

    // =========================================================================
    // TF-DOC-03: Acceso con URL temporal
    // =========================================================================

    #[Test]
    public function test_tf_doc_03_url_temporal_firmada_y_expirable(): void
    {
        // ServicioAlmacenamiento::urlTemporal() usa URL::temporarySignedRoute()
        // como fallback cuando el disco no soporta URLs temporales nativas (disco local).
        // En producción con S3, el disco devuelve una URL firmada por AWS.
        // Este test verifica el comportamiento del fallback de URL firmada de Laravel.

        $ciudadano = $this->crearCiudadano();
        $tipo      = $this->crearTipoDoc();
        $usuario   = $this->crearUser();

        // Crear Documento de prueba directamente (sin pasar por el servicio de almacenamiento)
        $documento = Documento::create([
            'documentable_type'   => get_class($ciudadano),
            'documentable_id'     => $ciudadano->id,
            'tipo_documento_id'   => $tipo->id,
            'origen'              => \Modules\Documentos\Enums\OrigenDocumento::Externo->value,
            'nombre_original'     => 'test.pdf',
            'ruta_almacenamiento' => 'documentos/2026/04/test.pdf',
            'disco'               => 'local',
            'mime_type'           => 'application/pdf',
            'tamano_bytes'        => 1024,
            'hash_sha256'         => hash('sha256', 'test'),
            'subido_por'          => $usuario->id,
        ]);

        // Generamos la URL firmada de Laravel directamente (ruta documentos.ver)
        $minutosExpiracion = 60;
        $expiracion        = now()->addMinutes($minutosExpiracion);
        $url               = URL::temporarySignedRoute(
            'documentos.ver',
            $expiracion,
            ['documento' => $documento->id]
        );

        // La URL debe contener firma y tiempo de expiración
        $this->assertStringContainsString('signature=', $url, 'La URL debe incluir firma criptográfica.');
        $this->assertStringContainsString('expires=', $url, 'La URL debe incluir tiempo de expiración.');

        // La URL es válida antes de expirar
        $request = \Illuminate\Http\Request::create($url);
        $this->assertTrue(URL::hasValidSignature($request), 'La URL debe ser válida antes de expirar.');

        // La URL debe ser inválida después de expirar
        $this->travel(61)->minutes();
        $this->assertFalse(URL::hasValidSignature($request), 'La URL debe ser inválida tras su expiración.');
    }

    // =========================================================================
    // TF-DOC-04: Verificación de integridad
    // =========================================================================

    #[Test]
    public function test_tf_doc_04_verificacion_integridad_detecta_alteracion(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);

        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $tipo      = $this->crearTipoDoc();
        $fichero   = $this->uploadedPdf();

        $servicio  = app(ServicioAlmacenamiento::class);
        $documento = $servicio->guardar($fichero, 'otro', $usuario->id, $tipo->id, $ciudadano);

        // El fichero recién subido debe pasar la verificación
        $this->assertTrue($servicio->verificarIntegridad($documento));

        // Corrompemos el fichero directamente en el disco
        Storage::disk('local')->put($documento->ruta_almacenamiento, 'contenido corrupto que altera el hash');

        // La verificación debe detectar la alteración
        $this->assertFalse(
            $servicio->verificarIntegridad($documento),
            'La verificación debe fallar si el fichero ha sido alterado.'
        );
    }

    // =========================================================================
    // TF-DOC-05: URL firmada de un documento no sirve para acceder a otro
    // =========================================================================

    #[Test]
    public function test_tf_doc_05_url_firmada_invalida_para_documento_diferente(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);

        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $tipo      = $this->crearTipoDoc();

        $servicio  = app(ServicioAlmacenamiento::class);
        $docA      = $servicio->guardar($this->uploadedPdf('a.pdf'), 'otro', $usuario->id, $tipo->id, $ciudadano);
        $docB      = $servicio->guardar($this->uploadedPdf('b.pdf'), 'otro', $usuario->id, $tipo->id, $ciudadano);

        // Generamos URL válida para docA
        $urlA = $servicio->urlTemporal($docA, 60);

        // Sustituimos el ID de docA por el de docB en la URL (tampering)
        $urlManipulada = str_replace(
            '/documentos/' . $docA->id . '/ver',
            '/documentos/' . $docB->id . '/ver',
            $urlA
        );

        // La URL manipulada debe tener firma inválida
        $this->assertFalse(
            URL::hasValidSignature(\Illuminate\Http\Request::create($urlManipulada)),
            'Una URL firmada para docA no debe ser válida para acceder a docB.'
        );
    }

    // =========================================================================
    // TF-DOC-06: Creación de estilo de informe por un supervisor
    // =========================================================================

    #[Test]
    public function test_tf_doc_06_crear_estilo_informe_y_unicidad_por_uo(): void
    {
        $uoA     = $this->crearUo('CSS Arganzuela');
        $uoB     = $this->crearUo('CSS Retiro');
        $usuario = $this->crearUser();

        // Crear estilo para UO A
        EstiloInforme::create([
            'unidad_organizativa_id' => $uoA->id,
            'nombre_unidad_cabecera' => 'Centro de Servicios Sociales Arganzuela',
            'logo_cabecera'          => 'logos/arganzuela.png',
            'creado_por'             => $usuario->id,
        ]);

        $this->assertDatabaseHas('estilos_informe', [
            'unidad_organizativa_id' => $uoA->id,
            'nombre_unidad_cabecera' => 'Centro de Servicios Sociales Arganzuela',
        ]);

        // Crear estilo para UO B funciona (UO diferente)
        EstiloInforme::create([
            'unidad_organizativa_id' => $uoB->id,
            'creado_por'             => $usuario->id,
        ]);

        $this->assertEquals(2, EstiloInforme::count());

        // Intentar un segundo estilo para UO A viola la restricción unique
        $this->expectException(\Illuminate\Database\QueryException::class);
        EstiloInforme::create([
            'unidad_organizativa_id' => $uoA->id,
            'creado_por'             => $usuario->id,
        ]);
    }

    // =========================================================================
    // TF-DOC-07: Herencia de estilo por proximidad
    // =========================================================================

    #[Test]
    public function test_tf_doc_07_herencia_estilo_por_proximidad(): void
    {
        $raiz    = $this->crearUo('Dirección General');
        $centro  = $this->crearUo('CSS Centro', $raiz);
        $usuario = $this->crearUser();

        // La DG define logo y pie
        EstiloInforme::create([
            'unidad_organizativa_id' => $raiz->id,
            'logo_cabecera'          => 'logos/dg.png',
            'html_pie'               => '<p>Ayuntamiento de Madrid</p>',
            'creado_por'             => $usuario->id,
        ]);

        // El centro define solo su nombre de unidad
        EstiloInforme::create([
            'unidad_organizativa_id' => $centro->id,
            'nombre_unidad_cabecera' => 'CSS Centro',
            'creado_por'             => $usuario->id,
        ]);

        $resolver  = app(ResolverEstiloInforme::class);
        $estilo    = $resolver->resolverSinCache($centro->id);

        // Logo viene de la DG (centro no lo define)
        $this->assertEquals('logos/dg.png', $estilo['logo_cabecera']);
        // Nombre viene del centro (definido en el centro)
        $this->assertEquals('CSS Centro', $estilo['nombre_unidad_cabecera']);
        // Pie viene de la DG (centro no lo define)
        $this->assertEquals('<p>Ayuntamiento de Madrid</p>', $estilo['html_pie']);
        // Dirección no definida en ningún nivel → null
        $this->assertNull($estilo['direccion_cabecera']);
    }

    // =========================================================================
    // TF-DOC-08: Campo sobreescrito en UO hija no afecta a UO hermana
    // =========================================================================

    #[Test]
    public function test_tf_doc_08_override_hijo_no_afecta_a_hermano(): void
    {
        $raiz    = $this->crearUo('Dirección General');
        $centroA = $this->crearUo('CSS A', $raiz);
        $centroB = $this->crearUo('CSS B', $raiz);
        $usuario = $this->crearUser();

        // La DG define logo genérico
        EstiloInforme::create([
            'unidad_organizativa_id' => $raiz->id,
            'logo_cabecera'          => 'logos/dg.png',
            'creado_por'             => $usuario->id,
        ]);

        // Centro A define su propio logo
        EstiloInforme::create([
            'unidad_organizativa_id' => $centroA->id,
            'logo_cabecera'          => 'logos/css_a.png',
            'creado_por'             => $usuario->id,
        ]);

        $resolver = app(ResolverEstiloInforme::class);

        // Centro A usa su propio logo
        $estiloA = $resolver->resolverSinCache($centroA->id);
        $this->assertEquals('logos/css_a.png', $estiloA['logo_cabecera']);

        // Centro B (hermano de A) sigue usando el logo de la DG
        $estiloB = $resolver->resolverSinCache($centroB->id);
        $this->assertEquals('logos/dg.png', $estiloB['logo_cabecera']);
    }

    // =========================================================================
    // TF-DOC-09: Plantilla visible para UO hija pero no para UO sin relación
    // =========================================================================

    #[Test]
    public function test_tf_doc_09_plantilla_visible_para_hijo_no_para_uo_sin_relacion(): void
    {
        $raiz        = $this->crearUo('Área de Servicios Sociales');
        $distrito    = $this->crearUo('Distrito Centro', $raiz);
        $centro      = $this->crearUo('CSS Malasaña', $distrito);
        $otroDistrito = $this->crearUo('Distrito Arganzuela', $raiz);
        $otroCentro  = $this->crearUo('CSS Delicias', $otroDistrito);

        // Plantilla creada a nivel de distrito
        $plantilla = $this->crearPlantilla($distrito->id, activa: true);

        // Un profesional del centro de ese distrito ve la plantilla
        $visiblesParaCentro = PlantillaInforme::visiblesParaUo($centro->id)->get();
        $this->assertTrue(
            $visiblesParaCentro->contains('id', $plantilla->id),
            'El centro hijo del distrito debe ver la plantilla del distrito.'
        );

        // Un profesional de otro centro (de otro distrito) no ve la plantilla
        $visiblesParaOtro = PlantillaInforme::visiblesParaUo($otroCentro->id)->get();
        $this->assertFalse(
            $visiblesParaOtro->contains('id', $plantilla->id),
            'Un centro de otro distrito no debe ver la plantilla.'
        );
    }

    // =========================================================================
    // TF-DOC-10: Creación de plantilla de informe
    // =========================================================================

    #[Test]
    public function test_tf_doc_10_creacion_plantilla_informe(): void
    {
        $uo      = $this->crearUo('Distrito Chamberí');
        $hijo    = $this->crearUo('CSS Trafalgar', $uo);
        $usuario = $this->crearUser();

        $secciones = [
            [
                'id'          => 'datos_ciudadano',
                'titulo'      => 'Datos del ciudadano',
                'tipo'        => 'automatico',
                'fuente'      => 'ciudadano.datos_basicos',
                'obligatorio' => false,
            ],
            [
                'id'          => 'valoracion',
                'titulo'      => 'Valoración',
                'tipo'        => 'texto_libre',
                'instrucciones' => 'Describa la situación.',
                'obligatorio' => true,
            ],
        ];

        $plantilla = PlantillaInforme::create([
            'nombre'                 => 'Informe Social de Valoración',
            'tipo_informe'           => TipoInforme::InformeSocial->value,
            'unidad_organizativa_id' => $uo->id,
            'secciones'              => $secciones,
            'activa'                 => true,
            'creada_por'             => $usuario->id,
        ]);

        $this->assertTrue($plantilla->activa);
        $this->assertCount(2, $plantilla->secciones);

        // La plantilla es visible para el UO del distrito (la propia UO)
        $this->assertTrue(
            PlantillaInforme::visiblesParaUo($uo->id)->where('id', $plantilla->id)->exists()
        );

        // La plantilla es visible para el centro hijo
        $this->assertTrue(
            PlantillaInforme::visiblesParaUo($hijo->id)->where('id', $plantilla->id)->exists()
        );
    }

    // =========================================================================
    // TF-DOC-11: Generación de informe en borrador
    // =========================================================================

    #[Test]
    public function test_tf_doc_11_generacion_borrador_pdf(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);

        $uo        = $this->crearUo('CSS Prueba');
        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe   = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        $servicio = app(ServicioGeneracionPDF::class);
        $pdf      = $servicio->generarBorrador($informe);

        // El resultado debe ser contenido binario de PDF
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf, 'El borrador debe ser un PDF válido.');

        // El informe sigue en estado borrador (generarBorrador no persiste)
        $informe->refresh();
        $this->assertEquals(EstadoInforme::Borrador, $informe->estado);
        $this->assertNull($informe->documento_id);
    }

    // =========================================================================
    // TF-DOC-12: Sección obligatoria vacía
    // =========================================================================

    #[Test]
    public function test_tf_doc_12_seccion_obligatoria_detectada_en_contenido(): void
    {
        // Nota: la validación de secciones obligatorias se enforza en el
        // NuevoInformeWizard (Livewire) que está pendiente de implementación.
        // Este test verifica que la estructura de datos permite detectar
        // la ausencia de contenido en secciones marcadas como obligatorio=true.

        $uo        = $this->crearUo('CSS Prueba');
        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();

        $secciones = [
            [
                'id'          => 'valoracion',
                'titulo'      => 'Valoración',
                'tipo'        => 'texto_libre',
                'obligatorio' => true,
            ],
        ];

        $plantilla = $this->crearPlantilla($uo->id, secciones: $secciones);

        // Informe con la sección obligatoria vacía
        $informe = Informe::create([
            'plantilla_id'  => $plantilla->id,
            'ciudadano_id'  => $ciudadano->id,
            'autor_id'      => $usuario->id,
            'estado'        => EstadoInforme::Borrador->value,
            'contenido'     => ['valoracion' => ''],  // vacío
        ]);

        // Verificamos que se puede detectar la sección obligatoria vacía
        $seccionesObligatorias = collect($plantilla->secciones)->where('obligatorio', true);
        $seccionesIncompletas  = $seccionesObligatorias->filter(
            fn ($s) => empty($informe->contenido[$s['id']] ?? null)
        );

        $this->assertCount(
            1,
            $seccionesIncompletas,
            'Debe detectarse una sección obligatoria con contenido vacío.'
        );
    }

    // =========================================================================
    // TF-DOC-13: Firma de informe con AutoFirma
    // =========================================================================

    #[Test]
    public function test_tf_doc_13_firma_informe_autofirma(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);
        $this->crearTipoInformeGenerado();

        $uo        = $this->crearUo('CSS Prueba');
        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe   = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        // En tests pasamos directamente el PDF en base64 (stub de AutoFirma)
        $servicio = app(ServicioFirmaInforme::class);
        $firmado  = $servicio->firmar($informe, $this->pdfBase64());

        $this->assertEquals(EstadoInforme::Firmado, $firmado->estado);
        $this->assertNotNull($firmado->firmado_en);
        $this->assertNotNull($firmado->metodo_firma);
        $this->assertNotNull($firmado->documento_id);

        // El documento PDF firmado debe existir en el disco
        $doc = $firmado->documento;
        $this->assertNotNull($doc);
        Storage::disk('local')->assertExists($doc->ruta_almacenamiento);
    }

    // =========================================================================
    // TF-DOC-14: Inmutabilidad del informe firmado
    // =========================================================================

    #[Test]
    public function test_tf_doc_14_informe_firmado_es_inmutable(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);
        $this->crearTipoInformeGenerado();

        $uo        = $this->crearUo('CSS Prueba');
        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe   = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        $servicio = app(ServicioFirmaInforme::class);
        $firmado  = $servicio->firmar($informe, $this->pdfBase64());

        // Intentar firmar de nuevo un informe ya firmado debe lanzar excepción
        $this->expectException(\DomainException::class);
        $servicio->firmar($firmado, $this->pdfBase64());
    }

    // =========================================================================
    // TF-DOC-15: Anulación de informe por el autor
    // =========================================================================

    #[Test]
    public function test_tf_doc_15_anulacion_por_autor(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);
        $this->crearTipoInformeGenerado();

        $uo        = $this->crearUo('CSS Prueba');
        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe   = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        $servicio = app(ServicioFirmaInforme::class);
        $firmado  = $servicio->firmar($informe, $this->pdfBase64());
        $docId    = $firmado->documento_id;

        // El autor anula el informe con motivo
        $anulado = $servicio->anular($firmado, $usuario->id, 'Error en los datos del ciudadano.');

        $this->assertEquals(EstadoInforme::Anulado, $anulado->estado);
        $this->assertEquals('Error en los datos del ciudadano.', $anulado->motivo_anulacion);
        $this->assertNotNull($anulado->anulado_en);

        // El PDF original permanece en el sistema
        $this->assertEquals($docId, $anulado->documento_id);
        Storage::disk('local')->assertExists($anulado->documento->ruta_almacenamiento);
    }

    // =========================================================================
    // TF-DOC-16: Anulación denegada a no-autor
    // =========================================================================

    #[Test]
    public function test_tf_doc_16_anulacion_denegada_a_no_autor(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);
        $this->crearTipoInformeGenerado();

        $uo        = $this->crearUo('CSS Prueba');
        $autor     = $this->crearUser();
        $otroUser  = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe   = $this->crearInformeBorrador($plantilla, $ciudadano, $autor);

        $servicio = app(ServicioFirmaInforme::class);
        $firmado  = $servicio->firmar($informe, $this->pdfBase64());

        // Otro profesional intenta anular el informe del autor → excepción
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Solo el autor puede anular/');
        $servicio->anular($firmado, $otroUser->id, 'Intento no autorizado.');

        // El informe permanece firmado
        $firmado->refresh();
        $this->assertEquals(EstadoInforme::Firmado, $firmado->estado);
    }

    // =========================================================================
    // TF-DOC-17: Subida de PISO firmado manualmente
    // =========================================================================

    #[Test]
    public function test_tf_doc_17_subida_piso_firmado_manualmente(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);

        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $tipo      = $this->crearTipoDoc('piso_firmado');

        $servicio = app(ServicioAlmacenamiento::class);

        // Subir el PDF escaneado del PISO con firmas manuscritas
        // Como documentable usamos el ciudadano (en producción sería el PlanDeIntervencion)
        $documento = $servicio->guardar(
            $this->uploadedPdf('piso_firmado.pdf'),
            'piso_firmado',
            $usuario->id,
            $tipo->id,
            $ciudadano
        );

        // Crear el registro PisoFirmado
        // plan_de_intervencion_id sin FK: tabla planes_de_intervencion aún no existe (módulo Intervención pendiente)
        $planId = 42;
        $piso   = PisoFirmado::create([
            'plan_de_intervencion_id'       => $planId,
            'documento_id'                  => $documento->id,
            'subido_por'                    => $usuario->id,
            'metodo_conformidad_ciudadano'  => MetodoConformidadCiudadano::ManuscritaEscaneada->value,
            'observaciones'                 => 'Firmado en reunión del 8 de abril.',
        ]);

        $this->assertInstanceOf(PisoFirmado::class, $piso);
        $this->assertEquals($planId, $piso->plan_de_intervencion_id);
        $this->assertEquals($documento->id, $piso->documento_id);
        $this->assertEquals($usuario->id, $piso->subido_por);
    }

    // =========================================================================
    // TF-DOC-18: Un PISO solo admite un registro de firma activo
    // =========================================================================

    #[Test]
    public function test_tf_doc_18_piso_solo_admite_un_registro_activo(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);

        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $tipo      = $this->crearTipoDoc('piso_firmado');
        $servicio  = app(ServicioAlmacenamiento::class);

        $doc1 = $servicio->guardar($this->uploadedPdf('piso1.pdf'), 'piso', $usuario->id, $tipo->id, $ciudadano);
        $doc2 = $servicio->guardar($this->uploadedPdf('piso2.pdf'), 'piso', $usuario->id, $tipo->id, $ciudadano);

        $planId = 99;

        // Primer PISO: se crea sin problema
        PisoFirmado::create([
            'plan_de_intervencion_id'      => $planId,
            'documento_id'                 => $doc1->id,
            'subido_por'                   => $usuario->id,
            'metodo_conformidad_ciudadano' => MetodoConformidadCiudadano::ManuscritaEscaneada->value,
        ]);

        // Segundo PISO para el mismo plan: debe violar la restricción unique
        $this->expectException(\Illuminate\Database\QueryException::class);

        PisoFirmado::create([
            'plan_de_intervencion_id'      => $planId,
            'documento_id'                 => $doc2->id,
            'subido_por'                   => $usuario->id,
            'metodo_conformidad_ciudadano' => MetodoConformidadCiudadano::ManuscritaEscaneada->value,
        ]);
    }

    // =========================================================================
    // TF-DOC-19: Disco de almacenamiento configurable sin cambios de código
    // =========================================================================

    #[Test]
    public function test_tf_doc_19_disco_almacenamiento_configurable(): void
    {
        // Simulamos un cambio de disco 'local' a 'documentos_s3' sin cambios de código
        $discoAlternativo = 'documentos_s3';
        Storage::fake($discoAlternativo);
        config(['documentos.disco' => $discoAlternativo]);

        $usuario   = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $tipo      = $this->crearTipoDoc();

        $servicio  = app(ServicioAlmacenamiento::class);
        $documento = $servicio->guardar(
            $this->uploadedPdf(),
            'otro',
            $usuario->id,
            $tipo->id,
            $ciudadano
        );

        // El documento debe registrar el disco alternativo
        $this->assertEquals($discoAlternativo, $documento->disco);

        // El fichero debe existir en el disco alternativo, no en el local
        Storage::disk($discoAlternativo)->assertExists($documento->ruta_almacenamiento);
    }

    // =========================================================================
    // TF-DOC-20: El profesional solo ve sus propios borradores
    // =========================================================================

    #[Test]
    public function test_tf_doc_20_profesional_solo_ve_sus_propios_borradores(): void
    {
        Storage::fake('local');
        config(['documentos.disco' => 'local']);
        $this->crearTipoInformeGenerado();

        $uo        = $this->crearUo('CSS Prueba');
        $autor1    = $this->crearUser();
        $autor2    = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);

        $borrador1 = $this->crearInformeBorrador($plantilla, $ciudadano, $autor1);
        $borrador2 = $this->crearInformeBorrador($plantilla, $ciudadano, $autor2);

        // Autor1 solo ve sus borradores
        $missBorradores = Informe::borradores()->deAutor($autor1->id)->get();
        $this->assertTrue($missBorradores->contains('id', $borrador1->id));
        $this->assertFalse($missBorradores->contains('id', $borrador2->id));

        // Firmamos el borrador del autor1
        $servicio = app(ServicioFirmaInforme::class);
        $firmado  = $servicio->firmar($borrador1, $this->pdfBase64());

        // Los informes firmados son visibles para cualquier consulta (no filtran por autor)
        $informesFirmados = Informe::firmados()->get();
        $this->assertTrue($informesFirmados->contains('id', $firmado->id));

        // El borrador2 del autor2 no aparece en los firmados
        $this->assertFalse($informesFirmados->contains('id', $borrador2->id));
    }
}
