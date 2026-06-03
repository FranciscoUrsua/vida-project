<?php

namespace Modules\Documentos\Services;

use App\Models\CatalogoSistema;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\Informe;

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

        $pdf = Pdf::loadView('documentos::informe', [
            'informe' => $informe,
            'plantilla' => $plantilla,
            'estilo' => $estilo,
            'contenido' => $contenido,
            'datosAuto' => $datosAuto,
            'tipografia' => config('documentos.tipografia'),
        ]);

        return $pdf->output();
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
