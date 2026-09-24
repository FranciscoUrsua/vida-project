<?php

namespace Modules\Documentos\Services;

use App\Models\CatalogoSistema;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\EstiloInforme;
use Modules\Documentos\Models\Informe;
use Modules\Organizacion\Models\Configuracion;

/**
 * Genera el PDF de un informe profesional.
 *
 * Combina el estilo resuelto para la UO del autor con el contenido de
 * las secciones del informe. Usa barryvdh/laravel-dompdf.
 *
 * El PDF de borrador no se persiste (vista previa iterativa).
 * El PDF final firmado se persiste vía ServicioAlmacenamiento.
 */
class ServicioGeneracionPDF
{
    /**
     * Crea el servicio con sus dependencias de resolución y almacenamiento.
     *
     * @param ResolverEstiloInforme $resolverEstilo Resolutor de estilos.
     * @param ServicioAlmacenamiento $almacenamiento Servicio de almacenamiento.
     * @param ResolverFuentesInforme $resolverFuentes Resolutor de fuentes de informe.
     */
    public function __construct(
        private ResolverEstiloInforme $resolverEstilo,
        private ServicioAlmacenamiento $almacenamiento,
        private ResolverFuentesInforme $resolverFuentes,
    ) {}

    /**
     * Genera el PDF de borrador y retorna su contenido binario.
     *
     * No persiste ningún fichero. El profesional puede llamar a este método
     * iterativamente para previsualizar el informe antes de firmarlo.
     *
     * @param Informe $informe Informe en estado borrador
     *
     * @return string Contenido binario del PDF
     */
    public function generarBorrador(Informe $informe): string
    {
        $estilo = $this->resolverEstilo->resolver($informe->autor->unidad_organizativa_id ?? 1);
        $plantilla = $informe->plantilla;
        $contenido = $informe->contenido ?? [];

        // Un único logo por organización (docs/decisiones-tecnicas.md Sección 12):
        // el de la identidad visual de la app (Sistema → Configuración), no el
        // campo por UO de EstiloInforme.
        $estilo['logo_cabecera'] = Configuracion::logoPathAbsoluto();

        // Resuelve datos de secciones automáticas
        $datosAuto = [];
        foreach ($plantilla->secciones ?? [] as $seccion) {
            if (($seccion['tipo'] ?? '') === 'automatico' && isset($seccion['fuente'])) {
                $datosAuto[$seccion['id']] = $this->resolverFuentes->resolver(
                    $seccion['fuente'],
                    $informe->ciudadano_id
                );
            }
        }

        $tipografia = config('documentos.tipografia');

        // El supervisor puede insertar el marcador de número de página en el pie
        // (EstiloInformeResource). dompdf no sustituye texto por página dentro del
        // flujo del documento, así que se retira del HTML y se dibuja aparte tras
        // renderizar (ver dibujarNumeroPagina()).
        $incluyeNumeroPagina = str_contains((string) ($estilo['html_pie'] ?? ''), EstiloInforme::MARCADOR_NUMERO_PAGINA);
        if ($incluyeNumeroPagina) {
            $estilo['html_pie'] = trim(str_replace(EstiloInforme::MARCADOR_NUMERO_PAGINA, '', $estilo['html_pie']));
        }

        $pdf = Pdf::loadView('documentos::informe', [
            'informe' => $informe,
            'plantilla' => $plantilla,
            'estilo' => $estilo,
            'contenido' => $contenido,
            'datosAuto' => $datosAuto,
            'tipografia' => $tipografia,
        ]);

        if ($incluyeNumeroPagina) {
            $this->dibujarNumeroPagina($pdf, $tipografia);
        }

        return $pdf->output();
    }

    /**
     * Dibuja «Página X» en la esquina inferior derecha de cada página del PDF.
     *
     * dompdf solo expone el número de página real a través de la API de lienzo
     * (Canvas::page_text), nunca como sustitución de texto en el HTML — por eso
     * se hace aparte, tras el renderizado. No se usa PHP embebido en el HTML
     * (`<script type="text/php">`) porque el pie lo edita libremente el
     * supervisor y evaluarlo sería ejecución de código arbitraria.
     *
     * @param PdfDocument $pdf PDF con la vista del informe ya cargada (sin renderizar).
     * @param array<string, mixed>|null $tipografia Tipografía base configurada.
     */
    private function dibujarNumeroPagina(PdfDocument $pdf, ?array $tipografia): void
    {
        // Fuerza el renderizado aquí (marca $pdf como renderizado) para que
        // output() no vuelva a renderizar y descarte el número de página dibujado.
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont($tipografia['familia'] ?? 'DejaVu Sans', 'normal');

        $canvas->page_text(
            $canvas->get_width() - 70,
            $canvas->get_height() - 20,
            'Página {PAGE_NUM}',
            $font,
            8,
            [0.4, 0.4, 0.4]
        );
    }

    /**
     * Persiste el PDF firmado recibido desde AutoFirma como Documento.
     *
     * @param Informe $informe Informe en estado borrador
     * @param string $pdfFirmadoBase64 PDF firmado en base64
     *
     * @return Documento Documento persistido
     */
    public function generarFinal(Informe $informe, string $pdfFirmadoBase64): Documento
    {
        $contenidoPdf = base64_decode($pdfFirmadoBase64);
        $nombreFichero = "informe_{$informe->id}_firmado.pdf";

        // El tipo de documento 'informe_generado' debe existir en catalogos_sistema
        $tipoDoc = CatalogoSistema::where('grupo', 'documento.tipo')
            ->where('clave', 'informe_generado')
            ->first();

        return $this->almacenamiento->guardarGenerado(
            contenidoPdf: $contenidoPdf,
            subidoPor: $informe->autor_id,
            tipoDocId: $tipoDoc?->id ?? 1,
            documentable: $informe,
            nombreOriginal: $nombreFichero,
        );
    }
}
