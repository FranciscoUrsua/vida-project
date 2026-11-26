<?php

namespace Modules\Documentos\Services;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\EstiloInforme;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Support\SaneadorHtmlInforme;
use Modules\Organizacion\Models\Configuracion;

/**
 * Genera el PDF de un informe profesional.
 *
 * Combina el estilo resuelto para la UO del autor con el contenido de
 * las secciones del informe. Usa barryvdh/laravel-dompdf.
 *
 * El PDF de borrador no se persiste (vista previa iterativa). El PDF para firmar es el
 * mismo sin la marca de borrador; el autor lo firma fuera de VIDA y lo sube (F5-1).
 * El PDF final firmado se custodia vía CicloVidaDocumentoService (tubería de entrada, canal generado).
 */
class ServicioGeneracionPDF
{
    /** Marca impresa en cada página de la vista previa; un PDF que la lleve no se acepta firmado. */
    public const MARCA_BORRADOR = 'BORRADOR · sin validez';

    /**
     * Crea el servicio con sus dependencias de resolución.
     *
     * @param ResolverEstiloInforme $resolverEstilo Resolutor de estilos.
     * @param ResolverFuentesInforme $resolverFuentes Resolutor de fuentes de informe.
     */
    public function __construct(
        private ResolverEstiloInforme $resolverEstilo,
        private ResolverFuentesInforme $resolverFuentes,
    ) {}

    /**
     * Genera el PDF de borrador (vista previa) y retorna su contenido binario.
     *
     * No persiste ningún fichero. El profesional puede llamar a este método
     * iterativamente para previsualizar el informe antes de firmarlo. Lleva la marca
     * «BORRADOR · sin validez» para que no se firme por error (el validador lo rechaza).
     *
     * @param Informe $informe Informe en estado borrador
     *
     * @return string Contenido binario del PDF
     */
    public function generarBorrador(Informe $informe): string
    {
        return $this->renderizar($informe, borrador: true);
    }

    /**
     * Genera el PDF final sin firmar que el autor descarga para firmarlo (F5-1).
     *
     * Misma maquetación que el borrador, sin la marca de borrador. El validador de
     * firma lo vuelve a generar para comparar el número de páginas (F5-2).
     *
     * @param Informe $informe Informe en estado borrador
     *
     * @return string Contenido binario del PDF
     */
    public function generarParaFirmar(Informe $informe): string
    {
        return $this->renderizar($informe, borrador: false);
    }

    /**
     * Datos de las secciones automáticas de la plantilla, por id de sección.
     *
     * @param Informe $informe Informe del que se resuelven las fuentes.
     *
     * @return array<string, array<string, string>> Id de sección → etiqueta → valor.
     */
    public function datosAutomaticos(Informe $informe): array
    {
        $datos = [];

        foreach ($informe->plantilla->secciones ?? [] as $seccion) {
            if (($seccion['tipo'] ?? '') === 'automatico' && isset($seccion['fuente'], $seccion['id'])) {
                // Desde un encargo, con su contexto: rechaza las fuentes que leen la Historia (EA-9)
                $datos[$seccion['id']] = $this->resolverFuentes->resolver(
                    $seccion['fuente'],
                    $informe->ciudadano_id,
                    $informe->autor_id,
                    $informe->derivacion,
                );
            }
        }

        return $datos;
    }

    /**
     * HTML de las secciones de texto libre ya saneado y con los merge tags sustituidos.
     *
     * @param Informe $informe Informe del que se toma el contenido.
     *
     * @return array<string, string> Id de sección → HTML.
     */
    public function textosLibres(Informe $informe): array
    {
        $saneador = app(SaneadorHtmlInforme::class);
        $fecha = $informe->created_at ?? now();
        $textos = [];

        foreach ($informe->plantilla->secciones ?? [] as $seccion) {
            if (($seccion['tipo'] ?? '') !== 'texto_libre' || ! isset($seccion['id'])) {
                continue;
            }

            $html = $saneador->sanear($informe->contenido[$seccion['id']] ?? '');
            $textos[$seccion['id']] = $this->resolverFuentes->resolverMergeTags(
                $html,
                $informe->ciudadano_id,
                $informe->autor_id,
                $fecha,
                $informe->derivacion,
            );
        }

        return $textos;
    }

    /**
     * Renderiza el informe con el estilo de la UO del autor.
     *
     * @param Informe $informe Informe que se pinta.
     * @param bool $borrador true para la vista previa con marca de borrador.
     *
     * @return string Contenido binario del PDF.
     */
    private function renderizar(Informe $informe, bool $borrador): string
    {
        $estilo = $this->resolverEstilo->resolver($this->uoDelAutor($informe));

        // Un único logo por organización (docs/decisiones-tecnicas.md Sección 12):
        // el de la identidad visual de la app (Sistema → Configuración), no el
        // campo por UO de EstiloInforme.
        $estilo['logo_cabecera'] = Configuracion::logoPathAbsoluto();

        $tipografia = config('documentos.tipografia');

        // El supervisor puede insertar el marcador de número de página en el pie
        // (EstiloInformeResource). dompdf no sustituye texto por página dentro del
        // flujo del documento, así que se retira del HTML y se dibuja aparte tras
        // renderizar (ver dibujarMarcas()).
        $incluyeNumeroPagina = str_contains((string) ($estilo['html_pie'] ?? ''), EstiloInforme::MARCADOR_NUMERO_PAGINA);
        if ($incluyeNumeroPagina) {
            $estilo['html_pie'] = trim(str_replace(EstiloInforme::MARCADOR_NUMERO_PAGINA, '', $estilo['html_pie']));
        }

        $pdf = Pdf::loadView('documentos::informe', [
            'informe' => $informe,
            'plantilla' => $informe->plantilla,
            'estilo' => $estilo,
            'textos' => $this->textosLibres($informe),
            'datosAuto' => $this->datosAutomaticos($informe),
            'tipografia' => $tipografia,
        ]);

        $this->dibujarMarcas($pdf, $tipografia, $informe, $borrador, $incluyeNumeroPagina);

        return $pdf->output();
    }

    /**
     * UO cuyo estilo se aplica: la primera adscripción vigente del autor.
     *
     * `users` no tiene UO propia: hasta la fase 5 se leía un atributo inexistente y
     * siempre se aplicaba el estilo de la UO 1. Se conserva la UO 1 como último recurso
     * (autor sin adscripciones).
     *
     * @param Informe $informe Informe.
     *
     * @return int
     */
    private function uoDelAutor(Informe $informe): int
    {
        return (int) ($informe->autor?->adscripcionesVigentes()->orderBy('id')->value('unidad_organizativa_id') ?? 1);
    }

    /**
     * Dibuja en cada página el identificador del informe, la marca de borrador y,
     * si el estilo lo pide, «Página X».
     *
     * El identificador va siempre, con o sin estilo de UO: el validador de firma lo
     * busca en todas las páginas del PDF subido (F5-2).
     *
     * dompdf solo expone el número de página real a través de la API de lienzo
     * (Canvas::page_text), nunca como sustitución de texto en el HTML — por eso
     * se hace aparte, tras el renderizado. No se usa PHP embebido en el HTML
     * (`<script type="text/php">`) porque el pie lo edita libremente el
     * supervisor y evaluarlo sería ejecución de código arbitraria.
     *
     * @param PdfDocument $pdf PDF con la vista del informe ya cargada (sin renderizar).
     * @param array<string, mixed>|null $tipografia Tipografía base configurada.
     * @param Informe $informe Informe (para el identificador).
     * @param bool $borrador Si se añade la marca de borrador.
     * @param bool $numeroPagina Si se añade «Página X».
     *
     * @return void
     */
    private function dibujarMarcas(PdfDocument $pdf, ?array $tipografia, Informe $informe, bool $borrador, bool $numeroPagina): void
    {
        // Fuerza el renderizado aquí (marca $pdf como renderizado) para que
        // output() no vuelva a renderizar y descarte lo dibujado.
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont($tipografia['familia'] ?? 'DejaVu Sans', 'normal');
        $gris = [0.4, 0.4, 0.4];

        $canvas->page_text(36, $canvas->get_height() - 20, 'Ref. '.$informe->identificador(), $font, 8, $gris);

        if ($numeroPagina) {
            $canvas->page_text($canvas->get_width() - 70, $canvas->get_height() - 20, 'Página {PAGE_NUM}', $font, 8, $gris);
        }

        if ($borrador) {
            $canvas->page_text($canvas->get_width() / 2 - 70, 14, self::MARCA_BORRADOR, $font, 9, [0.6, 0.2, 0.15]);
        }
    }

    /**
     * Custodia el PDF firmado como documento del ciudadano del informe.
     *
     * Usa el tipo documental «informe_profesional» y guarda en la versión el informe y
     * la plantilla de origen. Entra por el canal `generado`: la tubería no lo reescribe
     * (no se normaliza a PDF/A), porque eso invalidaría la firma.
     *
     * @param Informe $informe Informe en estado borrador
     * @param string $pdfFirmado Contenido binario del PDF firmado ya validado
     *
     * @return Documento Documento custodiado
     *
     * @throws \DomainException si no existe el tipo documental «informe_profesional»
     */
    public function generarFinal(Informe $informe, string $pdfFirmado): Documento
    {
        $tipo = TipoDocumental::where('codigo', 'informe_profesional')->first()
            ?? throw new \DomainException('Falta el tipo documental «informe_profesional» (TiposDocumentalesSeeder).');

        // Se resuelve aquí y no en el constructor: la vista previa de borradores no
        // necesita la clave maestra de documentos y no debe fallar si falta.
        return app(CicloVidaDocumentoService::class)->altaDocumento(
            $pdfFirmado,
            new DatosIngesta(
                tipo: $tipo,
                usuario: $informe->autor,
                canal: CanalCaptura::Generado,
                // Desde un encargo, también vinculado a él (EA-9): el profesional lo abre por AccesoDocumentosEncargo
                // La persona sin el scope de ámbito: el informe ya está autorizado y el autor puede ser
                // un profesional de encargo, sin ámbito sobre ella; si faltara, falla en vez de omitirla
                vinculos: array_values(array_filter([
                    Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($informe->ciudadano_id),
                    $informe->derivacion,
                ])),
                nombreOriginal: "informe_{$informe->id}_firmado.pdf",
                informeId: $informe->id,
                plantillaInformeId: $informe->plantilla_id,
            ),
            esContenido: true,
        );
    }
}
