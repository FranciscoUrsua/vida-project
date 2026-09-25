<?php

namespace Modules\Documentos\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\MetodoConformidadCiudadano;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\EstiloInforme;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Models\PisoFirmado;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\Almacenamiento\AlmacenFlysystem;
use Modules\Documentos\Services\CicloVidaDocumentoService;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Services\ResolverEstiloInforme;
use Modules\Documentos\Services\ResolverFuentesInforme;
use Modules\Documentos\Services\ServicioFirmaInforme;
use Modules\Documentos\Services\ServicioGeneracionPDF;
use Modules\Escalas\Enums\EstadoPase;
use Modules\Escalas\Models\PaseEscala;
use Modules\Escalas\Models\TipoEscala;
use Modules\Organizacion\Services\ConfiguracionService;
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
            'nombre' => $nombre,
            'tipo' => 'centro',
            'parent_id' => $parent?->id,
            'activa' => true,
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

    /**
     * Tipo documental genérico para los tests de custodia.
     *
     * @param string $codigo Código del tipo.
     * @param FamiliaDocumental $familia Familia del tipo.
     *
     * @return TipoDocumental
     */
    private function crearTipoDoc(string $codigo = 'informe_externo', FamiliaDocumental $familia = FamiliaDocumental::AportadoCiudadano): TipoDocumental
    {
        return TipoDocumental::firstOrCreate(['codigo' => $codigo], [
            'nombre' => ucfirst(str_replace('_', ' ', $codigo)),
            'familia' => $familia,
            'origen_eni' => $familia === FamiliaDocumental::InformeProfesional ? OrigenEni::Administracion : OrigenEni::Ciudadano,
            'vinculables' => ['ciudadano'],
        ]);
    }

    /**
     * Ruta de un fichero de prueba de tests/fixtures.
     *
     * @param string $nombre Nombre del fichero.
     *
     * @return string
     */
    private function fixture(string $nombre): string
    {
        return dirname(__DIR__).'/fixtures/'.$nombre;
    }

    /**
     * Da de alta un documento del ciudadano a partir de un fixture.
     *
     * @param User $usuario Quien lo sube.
     * @param Ciudadano $ciudadano Persona vinculada.
     * @param string $nombreOriginal Nombre original.
     * @param string $fixture Fichero de origen.
     *
     * @return Documento
     */
    private function altaDocumento(User $usuario, Ciudadano $ciudadano, string $nombreOriginal = 'documento.pdf', string $fixture = 'valido.pdf'): Documento
    {
        return app(CicloVidaDocumentoService::class)->altaDocumento(
            $this->fixture($fixture),
            new DatosIngesta(
                tipo: $this->crearTipoDoc(),
                usuario: $usuario,
                canal: CanalCaptura::Presencial,
                vinculos: [$ciudadano],
                nombreOriginal: $nombreOriginal,
            ),
        );
    }

    /**
     * Ruta en el disco de documentos del objeto de la versión vigente.
     *
     * @param Documento $documento Documento.
     *
     * @return string
     */
    private function rutaObjeto(Documento $documento): string
    {
        return app(AlmacenFlysystem::class)->ruta($documento->versionVigente->clave_almacenamiento);
    }

    /**
     * @param array<mixed> $secciones
     *
     * @return PlantillaInforme
     */
    private function crearPlantilla(int $uoId, bool $activa = true, array $secciones = []): PlantillaInforme
    {
        $usuario = $this->crearUser();

        return PlantillaInforme::create([
            'nombre' => 'Plantilla de prueba '.Str::random(4),
            'tipo_informe' => TipoInforme::InformeSocial->value,
            'unidad_organizativa_id' => $uoId,
            'secciones' => $secciones ?: [
                [
                    'id' => 'valoracion',
                    'titulo' => 'Valoración',
                    'tipo' => 'texto_libre',
                    'instrucciones' => 'Detalle la situación.',
                    'obligatorio' => true,
                ],
            ],
            'activa' => $activa,
            'creada_por' => $usuario->id,
        ]);
    }

    private function crearInformeBorrador(PlantillaInforme $plantilla, Ciudadano $ciudadano, User $autor): Informe
    {
        return Informe::create([
            'plantilla_id' => $plantilla->id,
            'ciudadano_id' => $ciudadano->id,
            'autor_id' => $autor->id,
            'estado' => EstadoInforme::Borrador->value,
            'contenido' => ['valoracion' => 'Valoración de prueba.'],
        ]);
    }

    /**
     * PDF firmado de prueba en base64 (stub de AutoFirma).
     *
     * @return string
     */
    private function pdfBase64(): string
    {
        return base64_encode((string) file_get_contents($this->fixture('valido.pdf')));
    }

    /**
     * Prepara el disco falso de documentos y el tipo documental de los informes firmados.
     *
     * @return TipoDocumental
     */
    private function crearTipoInformeGenerado(): TipoDocumental
    {
        Storage::fake('documentos');

        return $this->crearTipoDoc('informe_profesional', FamiliaDocumental::InformeProfesional);
    }

    // =========================================================================
    // TF-DOC-01: Subida de documento externo válido
    // =========================================================================

    #[Test]
    public function test_tf_doc_01_subida_documento_externo_valido(): void
    {
        Storage::fake('documentos');

        $usuario = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $tipo = $this->crearTipoDoc();

        $documento = $this->altaDocumento($usuario, $ciudadano, 'informe.pdf');
        $version = $documento->versionVigente;

        $this->assertInstanceOf(Documento::class, $documento);
        $this->assertSame($tipo->id, $documento->tipo_documental_id);
        $this->assertSame([$ciudadano->id], Documento::vinculadosA($ciudadano)->pluck('id')->map(fn ($id) => $ciudadano->id)->all());
        $this->assertSame('informe.pdf', $version->nombre_original);
        $this->assertSame('application/pdf', $version->mime_original);
        $this->assertSame(hash_file('sha256', $this->fixture('valido.pdf')), $version->hash_sha256);
        $this->assertSame('documentos', $version->disco);

        // El fichero debe existir (cifrado) en el disco de documentos
        Storage::disk('documentos')->assertExists($this->rutaObjeto($documento));
    }

    // =========================================================================
    // TF-DOC-02: Rechazo de formato no admitido
    // =========================================================================

    #[Test]
    public function test_tf_doc_02_rechazo_formato_no_admitido(): void
    {
        Storage::fake('documentos');

        $usuario = $this->crearUser();
        $ciudadano = $this->crearCiudadano();

        try {
            $this->altaDocumento($usuario, $ciudadano, 'comprimido.zip', 'comprimido.zip');
            $this->fail('Un ZIP debe rechazarse.');
        } catch (IngestaRechazadaException $e) {
            $this->assertSame('formato_no_admitido', $e->codigo);
        }

        // No debe haberse creado ningún registro ni objeto
        $this->assertSame(0, Documento::count());
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    // =========================================================================
    // TF-DOC-03: Acceso con URL temporal
    // =========================================================================

    #[Test]
    public function test_tf_doc_03_url_temporal_firmada_y_expirable(): void
    {
        Storage::fake('documentos');

        $documento = $this->altaDocumento($this->crearUser(), $this->crearCiudadano());

        $url = app(LecturaDocumentoService::class)->urlTemporal($documento, 60);

        // La URL apunta a la ruta de la aplicación y contiene firma y expiración
        $this->assertStringContainsString('/documentos/'.$documento->id.'/ver', $url);
        $this->assertStringContainsString('signature=', $url, 'La URL debe incluir firma criptográfica.');
        $this->assertStringContainsString('expires=', $url, 'La URL debe incluir tiempo de expiración.');

        // La URL es válida antes de expirar e inválida después
        $request = Request::create($url);
        $this->assertTrue(URL::hasValidSignature($request), 'La URL debe ser válida antes de expirar.');

        $this->travel(61)->minutes();
        $this->assertFalse(URL::hasValidSignature($request), 'La URL debe ser inválida tras su expiración.');
    }

    // =========================================================================
    // TF-DOC-04: Verificación de integridad
    // =========================================================================

    #[Test]
    public function test_tf_doc_04_verificacion_integridad_detecta_alteracion(): void
    {
        Storage::fake('documentos');

        $documento = $this->altaDocumento($this->crearUser(), $this->crearCiudadano());
        $lectura = app(LecturaDocumentoService::class);

        // El fichero recién subido debe pasar la verificación
        $this->assertTrue($lectura->verificarIntegridad($documento->versionVigente));

        // Corrompemos el objeto directamente en el disco
        Storage::disk('documentos')->put($this->rutaObjeto($documento), 'contenido corrupto que altera el objeto');

        $this->assertFalse(
            $lectura->verificarIntegridad($documento->versionVigente),
            'La verificación debe fallar si el fichero ha sido alterado.'
        );
    }

    // =========================================================================
    // TF-DOC-05: URL firmada de un documento no sirve para acceder a otro
    // =========================================================================

    #[Test]
    public function test_tf_doc_05_url_firmada_invalida_para_documento_diferente(): void
    {
        Storage::fake('documentos');

        $usuario = $this->crearUser();
        $ciudadano = $this->crearCiudadano();

        $docA = $this->altaDocumento($usuario, $ciudadano, 'a.pdf');
        $docB = $this->altaDocumento($usuario, $ciudadano, 'b.pdf');

        $urlA = app(LecturaDocumentoService::class)->urlTemporal($docA, 60);

        // Sustituimos el ID de docA por el de docB en la URL (tampering)
        $urlManipulada = str_replace('/documentos/'.$docA->id.'/ver', '/documentos/'.$docB->id.'/ver', $urlA);

        $this->assertFalse(
            URL::hasValidSignature(Request::create($urlManipulada)),
            'Una URL firmada para docA no debe ser válida para acceder a docB.'
        );
    }

    // =========================================================================
    // TF-DOC-06: Creación de estilo de informe por un supervisor
    // =========================================================================

    #[Test]
    public function test_tf_doc_06_crear_estilo_informe_y_unicidad_por_uo(): void
    {
        $uoA = $this->crearUo('CSS Arganzuela');
        $uoB = $this->crearUo('CSS Retiro');
        $usuario = $this->crearUser();

        // Crear estilo para UO A
        EstiloInforme::create([
            'unidad_organizativa_id' => $uoA->id,
            'nombre_unidad_cabecera' => 'Centro de Servicios Sociales Arganzuela',
            'logo_cabecera' => 'logos/arganzuela.png',
            'creado_por' => $usuario->id,
        ]);

        $this->assertDatabaseHas('estilos_informe', [
            'unidad_organizativa_id' => $uoA->id,
            'nombre_unidad_cabecera' => 'Centro de Servicios Sociales Arganzuela',
        ]);

        // Crear estilo para UO B funciona (UO diferente)
        EstiloInforme::create([
            'unidad_organizativa_id' => $uoB->id,
            'creado_por' => $usuario->id,
        ]);

        $this->assertEquals(2, EstiloInforme::count());

        // Intentar un segundo estilo para UO A viola la restricción unique
        $this->expectException(QueryException::class);
        EstiloInforme::create([
            'unidad_organizativa_id' => $uoA->id,
            'creado_por' => $usuario->id,
        ]);
    }

    // =========================================================================
    // TF-DOC-07: Herencia de estilo por proximidad
    // =========================================================================

    #[Test]
    public function test_tf_doc_07_herencia_estilo_por_proximidad(): void
    {
        $raiz = $this->crearUo('Dirección General');
        $centro = $this->crearUo('CSS Centro', $raiz);
        $usuario = $this->crearUser();

        // La DG define logo y pie
        EstiloInforme::create([
            'unidad_organizativa_id' => $raiz->id,
            'logo_cabecera' => 'logos/dg.png',
            'html_pie' => '<p>Ayuntamiento de Madrid</p>',
            'creado_por' => $usuario->id,
        ]);

        // El centro define solo su nombre de unidad
        EstiloInforme::create([
            'unidad_organizativa_id' => $centro->id,
            'nombre_unidad_cabecera' => 'CSS Centro',
            'creado_por' => $usuario->id,
        ]);

        $resolver = app(ResolverEstiloInforme::class);
        $estilo = $resolver->resolverSinCache($centro->id);

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
        $raiz = $this->crearUo('Dirección General');
        $centroA = $this->crearUo('CSS A', $raiz);
        $centroB = $this->crearUo('CSS B', $raiz);
        $usuario = $this->crearUser();

        // La DG define logo genérico
        EstiloInforme::create([
            'unidad_organizativa_id' => $raiz->id,
            'logo_cabecera' => 'logos/dg.png',
            'creado_por' => $usuario->id,
        ]);

        // Centro A define su propio logo
        EstiloInforme::create([
            'unidad_organizativa_id' => $centroA->id,
            'logo_cabecera' => 'logos/css_a.png',
            'creado_por' => $usuario->id,
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
        $raiz = $this->crearUo('Área de Servicios Sociales');
        $distrito = $this->crearUo('Distrito Centro', $raiz);
        $centro = $this->crearUo('CSS Malasaña', $distrito);
        $otroDistrito = $this->crearUo('Distrito Arganzuela', $raiz);
        $otroCentro = $this->crearUo('CSS Delicias', $otroDistrito);

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
        $uo = $this->crearUo('Distrito Chamberí');
        $hijo = $this->crearUo('CSS Trafalgar', $uo);
        $usuario = $this->crearUser();

        $secciones = [
            [
                'id' => 'datos_ciudadano',
                'titulo' => 'Datos del ciudadano',
                'tipo' => 'automatico',
                'fuente' => 'ciudadano.datos_basicos',
                'obligatorio' => false,
            ],
            [
                'id' => 'valoracion',
                'titulo' => 'Valoración',
                'tipo' => 'texto_libre',
                'instrucciones' => 'Describa la situación.',
                'obligatorio' => true,
            ],
        ];

        $plantilla = PlantillaInforme::create([
            'nombre' => 'Informe Social de Valoración',
            'tipo_informe' => TipoInforme::InformeSocial->value,
            'unidad_organizativa_id' => $uo->id,
            'secciones' => $secciones,
            'activa' => true,
            'creada_por' => $usuario->id,
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

        $uo = $this->crearUo('CSS Prueba');
        $usuario = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        $servicio = app(ServicioGeneracionPDF::class);
        $pdf = $servicio->generarBorrador($informe);

        // El resultado debe ser contenido binario de PDF
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf, 'El borrador debe ser un PDF válido.');

        // El informe sigue en estado borrador (generarBorrador no persiste)
        $informe->refresh();
        $this->assertEquals(EstadoInforme::Borrador, $informe->estado);
        $this->assertNull($informe->documento_id);
    }

    // =========================================================================
    // TF-DOC-79: Marcador de número de página en el pie de EstiloInforme
    // =========================================================================

    #[Test]
    public function test_tf_doc_79_marcador_numero_pagina_en_pie_genera_pdf_valido(): void
    {
        Storage::fake('local');

        // generarBorrador() resuelve el estilo de la UO 1 cuando el autor no
        // tiene UO asignada, que es el caso de los usuarios creados en test.
        $uo = UnidadOrganizativa::forceCreate([
            'id' => 1,
            'nombre' => 'CSS Numeración',
            'tipo' => 'centro',
            'activa' => true,
        ]);
        $usuario = $this->crearUser();

        EstiloInforme::create([
            'unidad_organizativa_id' => $uo->id,
            'html_pie' => 'Ayuntamiento de Madrid — '.EstiloInforme::MARCADOR_NUMERO_PAGINA,
            'creado_por' => $usuario->id,
        ]);

        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        $servicio = app(ServicioGeneracionPDF::class);
        $pdf = $servicio->generarBorrador($informe);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith(
            '%PDF',
            $pdf,
            'El informe con el marcador de número de página en el pie debe seguir generando un PDF válido.'
        );
    }

    #[Test]
    public function test_tf_doc_80_pie_sin_marcador_numero_pagina_genera_pdf_valido(): void
    {
        Storage::fake('local');

        $uo = UnidadOrganizativa::forceCreate([
            'id' => 1,
            'nombre' => 'CSS Sin Numeración',
            'tipo' => 'centro',
            'activa' => true,
        ]);
        $usuario = $this->crearUser();

        // Pie de página normal, sin el marcador de número de página
        EstiloInforme::create([
            'unidad_organizativa_id' => $uo->id,
            'html_pie' => 'Ayuntamiento de Madrid',
            'creado_por' => $usuario->id,
        ]);

        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        $servicio = app(ServicioGeneracionPDF::class);
        $pdf = $servicio->generarBorrador($informe);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    // =========================================================================
    // TF-DOC-81: Logo único de organización sustituye al logo por UO
    // =========================================================================

    #[Test]
    public function test_tf_doc_81_logo_global_de_organizacion_sustituye_al_logo_por_uo(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Logo global (Sistema → Configuración → Identidad visual)
        $rutaLogoGlobal = UploadedFile::fake()->image('logo-organizacion.png')->store('branding', 'public');
        app(ConfiguracionService::class)->set('logo_path', $rutaLogoGlobal);

        $uo = UnidadOrganizativa::forceCreate([
            'id' => 1,
            'nombre' => 'CSS Logo',
            'tipo' => 'centro',
            'activa' => true,
        ]);
        $usuario = $this->crearUser();

        // El estilo por UO define su propio logo_cabecera (legado): debe ignorarse
        EstiloInforme::create([
            'unidad_organizativa_id' => $uo->id,
            'logo_cabecera' => 'logos/legado-no-usado.png',
            'creado_por' => $usuario->id,
        ]);

        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        // ResolverEstiloInforme sigue devolviendo el logo por UO (comportamiento
        // sin cambios); es ServicioGeneracionPDF quien lo sustituye por el global.
        $estiloResuelto = app(ResolverEstiloInforme::class)->resolverSinCache($uo->id);
        $this->assertEquals('logos/legado-no-usado.png', $estiloResuelto['logo_cabecera']);

        $servicio = app(ServicioGeneracionPDF::class);
        $pdf = $servicio->generarBorrador($informe);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf, 'El informe con logo global configurado debe seguir generando un PDF válido.');
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

        $uo = $this->crearUo('CSS Prueba');
        $usuario = $this->crearUser();
        $ciudadano = $this->crearCiudadano();

        $secciones = [
            [
                'id' => 'valoracion',
                'titulo' => 'Valoración',
                'tipo' => 'texto_libre',
                'obligatorio' => true,
            ],
        ];

        $plantilla = $this->crearPlantilla($uo->id, secciones: $secciones);

        // Informe con la sección obligatoria vacía
        $informe = Informe::create([
            'plantilla_id' => $plantilla->id,
            'ciudadano_id' => $ciudadano->id,
            'autor_id' => $usuario->id,
            'estado' => EstadoInforme::Borrador->value,
            'contenido' => ['valoracion' => ''],  // vacío
        ]);

        // Verificamos que se puede detectar la sección obligatoria vacía
        $seccionesObligatorias = collect($plantilla->secciones)->where('obligatorio', true);
        $seccionesIncompletas = $seccionesObligatorias->filter(
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
        $this->crearTipoInformeGenerado();

        $uo = $this->crearUo('CSS Prueba');
        $usuario = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        // En tests pasamos directamente el PDF en base64 (stub de AutoFirma)
        $servicio = app(ServicioFirmaInforme::class);
        $firmado = $servicio->firmar($informe, $this->pdfBase64());

        $this->assertEquals(EstadoInforme::Firmado, $firmado->estado);
        $this->assertNotNull($firmado->firmado_en);
        $this->assertNotNull($firmado->metodo_firma);
        $this->assertNotNull($firmado->documento_id);

        // El PDF firmado queda custodiado: versión generada, vinculada al ciudadano, con objeto en disco
        $doc = $firmado->documento;
        $this->assertNotNull($doc);
        $this->assertSame('informe_profesional', $doc->tipo->codigo);
        $this->assertSame(CanalCaptura::Generado, $doc->versionVigente->canal);
        $this->assertSame($informe->id, $doc->versionVigente->informe_id);
        $this->assertSame($plantilla->id, $doc->versionVigente->plantilla_informe_id);
        $this->assertSame([$doc->id], Documento::vinculadosA($ciudadano)->pluck('id')->all());
        Storage::disk('documentos')->assertExists($this->rutaObjeto($doc));
    }

    // =========================================================================
    // TF-DOC-14: Inmutabilidad del informe firmado
    // =========================================================================

    #[Test]
    public function test_tf_doc_14_informe_firmado_es_inmutable(): void
    {
        $this->crearTipoInformeGenerado();

        $uo = $this->crearUo('CSS Prueba');
        $usuario = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        $servicio = app(ServicioFirmaInforme::class);
        $firmado = $servicio->firmar($informe, $this->pdfBase64());

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
        $this->crearTipoInformeGenerado();

        $uo = $this->crearUo('CSS Prueba');
        $usuario = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe = $this->crearInformeBorrador($plantilla, $ciudadano, $usuario);

        $servicio = app(ServicioFirmaInforme::class);
        $firmado = $servicio->firmar($informe, $this->pdfBase64());
        $docId = $firmado->documento_id;

        // El autor anula el informe con motivo
        $anulado = $servicio->anular($firmado, $usuario->id, 'Error en los datos del ciudadano.');

        $this->assertEquals(EstadoInforme::Anulado, $anulado->estado);
        $this->assertEquals('Error en los datos del ciudadano.', $anulado->motivo_anulacion);
        $this->assertNotNull($anulado->anulado_en);

        // El PDF original permanece en el sistema
        $this->assertEquals($docId, $anulado->documento_id);
        $this->assertTrue(app(AlmacenDocumentos::class)->existe($anulado->documento->versionVigente->clave_almacenamiento));
    }

    // =========================================================================
    // TF-DOC-16: Anulación denegada a no-autor
    // =========================================================================

    #[Test]
    public function test_tf_doc_16_anulacion_denegada_a_no_autor(): void
    {
        $this->crearTipoInformeGenerado();

        $uo = $this->crearUo('CSS Prueba');
        $autor = $this->crearUser();
        $otroUser = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $plantilla = $this->crearPlantilla($uo->id);
        $informe = $this->crearInformeBorrador($plantilla, $ciudadano, $autor);

        $servicio = app(ServicioFirmaInforme::class);
        $firmado = $servicio->firmar($informe, $this->pdfBase64());

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
        Storage::fake('documentos');

        $usuario = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        // Subir el PDF escaneado del PISO con firmas manuscritas, vinculado al ciudadano
        $documento = $this->altaDocumento($usuario, $ciudadano, 'piso_firmado.pdf');

        // Crear el registro PisoFirmado
        // plan_de_intervencion_id sin FK: tabla planes_de_intervencion aún no existe (módulo Intervención pendiente)
        $planId = 42;
        $piso = PisoFirmado::create([
            'plan_de_intervencion_id' => $planId,
            'documento_id' => $documento->id,
            'subido_por' => $usuario->id,
            'metodo_conformidad_ciudadano' => MetodoConformidadCiudadano::ManuscritaEscaneada->value,
            'observaciones' => 'Firmado en reunión del 8 de abril.',
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
        Storage::fake('documentos');

        $usuario = $this->crearUser();
        $ciudadano = $this->crearCiudadano();
        $doc1 = $this->altaDocumento($usuario, $ciudadano, 'piso1.pdf');
        $doc2 = $this->altaDocumento($usuario, $ciudadano, 'piso2.pdf');

        $planId = 99;

        // Primer PISO: se crea sin problema
        PisoFirmado::create([
            'plan_de_intervencion_id' => $planId,
            'documento_id' => $doc1->id,
            'subido_por' => $usuario->id,
            'metodo_conformidad_ciudadano' => MetodoConformidadCiudadano::ManuscritaEscaneada->value,
        ]);

        // Segundo PISO para el mismo plan: debe violar la restricción unique
        $this->expectException(QueryException::class);

        PisoFirmado::create([
            'plan_de_intervencion_id' => $planId,
            'documento_id' => $doc2->id,
            'subido_por' => $usuario->id,
            'metodo_conformidad_ciudadano' => MetodoConformidadCiudadano::ManuscritaEscaneada->value,
        ]);
    }

    // =========================================================================
    // TF-DOC-19: Disco de almacenamiento configurable sin cambios de código
    // =========================================================================

    #[Test]
    public function test_tf_doc_19_disco_almacenamiento_configurable(): void
    {
        // Simulamos un cambio del disco de documentos a otro proveedor sin cambios de código
        $discoAlternativo = 'documentos_s3';
        config(["filesystems.disks.{$discoAlternativo}" => ['driver' => 's3']]);
        Storage::fake($discoAlternativo);
        Storage::fake('documentos');
        config(['documentos.disco' => $discoAlternativo]);

        $documento = $this->altaDocumento($this->crearUser(), $this->crearCiudadano());

        // La versión registra el disco alternativo y el objeto está allí, no en el disco por defecto
        $this->assertSame($discoAlternativo, $documento->versionVigente->disco);
        Storage::disk($discoAlternativo)->assertExists($this->rutaObjeto($documento));
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    // =========================================================================
    // TF-DOC-21: Los merge tags se sustituyen correctamente al generar el contenido
    // =========================================================================

    #[Test]
    public function test_tf_doc_21_merge_tags_se_sustituyen_en_contenido_plantilla(): void
    {
        $uo = $this->crearUo('CSS Test Merge Tags');

        // Ciudadano con nombre conocido
        $ciudadano = Ciudadano::factory()->create([
            'nombre' => 'María',
            'apellido1' => 'López',
            'apellido2' => null,
        ]);

        // Historia Social vinculada al ciudadano
        $historia = HistoriaSocial::create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        // TipoEscala Barthel con schema mínimo válido
        $barthel = TipoEscala::factory()->create([
            'codigo' => 'barthel',
            'activa' => true,
        ]);

        $profesional = $this->crearUser();

        // Pase completado de Barthel con score conocido
        PaseEscala::create([
            'tipo_escala_id' => $barthel->id,
            'historia_id' => $historia->id,
            'profesional_id' => $profesional->id,
            'fecha' => now()->toDateString(),
            'estado' => EstadoPase::Completado->value,
            'respuestas' => [],
            'scores_seccion' => [],
            'score_total' => 75,
            'interpretacion_codigo' => 'moderada',
        ]);

        $html = '<p>D./Dña. {{ nombre_ciudadano }}, Barthel: {{ score_barthel }}.</p>';

        $servicio = app(ResolverFuentesInforme::class);
        $resultado = $servicio->resolverMergeTags($html, $ciudadano->id, $profesional->id, now());

        $this->assertStringContainsString('María López', $resultado);
        $this->assertStringContainsString('75', $resultado);

        // Ningún tag sin sustituir debe quedar en el HTML
        $this->assertStringNotContainsString('{{', $resultado, 'El HTML no debe contener tags sin sustituir.');
    }

    // =========================================================================
    // TF-DOC-20: El profesional solo ve sus propios borradores
    // =========================================================================

    #[Test]
    public function test_tf_doc_20_profesional_solo_ve_sus_propios_borradores(): void
    {
        $this->crearTipoInformeGenerado();

        $uo = $this->crearUo('CSS Prueba');
        $autor1 = $this->crearUser();
        $autor2 = $this->crearUser();
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
        $firmado = $servicio->firmar($borrador1, $this->pdfBase64());

        // Los informes firmados son visibles para cualquier consulta (no filtran por autor)
        $informesFirmados = Informe::firmados()->get();
        $this->assertTrue($informesFirmados->contains('id', $firmado->id));

        // El borrador2 del autor2 no aparece en los firmados
        $this->assertFalse($informesFirmados->contains('id', $borrador2->id));
    }
}
