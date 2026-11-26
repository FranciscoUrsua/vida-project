<?php

namespace Modules\Documentos\Services;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Documentos\Contracts\FirmadorInforme;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\MetodoFirma;
use Modules\Documentos\Exceptions\FirmaRechazadaException;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\Firma\FirmaAutoFirma;
use Modules\Documentos\Services\Firma\FirmaExternaSubida;
use Modules\Documentos\Services\Firma\ValidadorFirmaPdf;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;

/**
 * Coordina la firma y la anulación de un informe profesional (fase 5).
 *
 * Flujo de firma:
 * 1. El autor obtiene el PDF firmado con uno de los firmadores (`FirmadorInforme`):
 *    subida del PDF firmado fuera de VIDA (activo) o AutoFirma integrada (preparada,
 *    inactiva por configuración). F5-1.
 * 2. `ValidadorFirmaPdf` comprueba firma íntegra, páginas, identificador y que no es la
 *    vista previa (F5-2). Si no pasa, el informe sigue en borrador.
 * 3. El PDF se custodia sin reescribirlo (canal `generado`), el informe pasa a firmado
 *    con el método y el titular del certificado, y queda un apunte en la historia.
 *
 * La responsabilidad de autoría es personal: solo el autor firma y anula su informe
 * (F5-3) y no existe fallback de firma manuscrita.
 */
class ServicioFirmaInforme
{
    /**
     * Crea el servicio.
     *
     * @param ServicioGeneracionPDF $generacionPdf Generador y custodia de PDFs.
     * @param ValidadorFirmaPdf $validador Validación mínima de la firma.
     */
    public function __construct(
        private ServicioGeneracionPDF $generacionPdf,
        private ValidadorFirmaPdf $validador,
    ) {}

    /**
     * Firmador que corresponde a un método de firma.
     *
     * @param MetodoFirma $metodo Método de firma.
     *
     * @return FirmadorInforme
     */
    public function firmador(MetodoFirma $metodo): FirmadorInforme
    {
        return match ($metodo) {
            MetodoFirma::PdfFirmadoExternamente => app(FirmaExternaSubida::class),
            MetodoFirma::AutofirmaCertificadoEmpleadoPublico => app(FirmaAutoFirma::class),
        };
    }

    /**
     * Firma un informe en borrador con el PDF firmado que aporta su autor.
     *
     * @param Informe $informe Informe en estado borrador.
     * @param MetodoFirma $metodo Método con el que se ha obtenido el PDF firmado.
     * @param string $pdfFirmado Contenido binario del PDF firmado.
     * @param User $actor Quien firma (debe ser el autor).
     *
     * @return Informe Informe actualizado a estado firmado.
     *
     * @throws \DomainException si el informe no está en borrador o el actor no es el autor
     * @throws FirmaRechazadaException si el método no está activo o el PDF no supera la validación
     */
    public function firmar(Informe $informe, MetodoFirma $metodo, string $pdfFirmado, User $actor): Informe
    {
        $this->exigirBorradorDelAutor($informe, $actor);

        if (! $this->firmador($metodo)->activo()) {
            throw new FirmaRechazadaException('metodo_inactivo');
        }

        // Fuera de la transacción: lanza procesos externos (pdfsig, pdfinfo, pdftotext).
        $titular = $this->validador->validar($pdfFirmado, $informe);

        return DB::transaction(function () use ($informe, $metodo, $pdfFirmado, $actor, $titular): Informe {
            $bloqueado = Informe::query()->lockForUpdate()->findOrFail($informe->id);
            $this->exigirBorradorDelAutor($bloqueado, $actor);

            $documento = $this->generacionPdf->generarFinal($bloqueado, $pdfFirmado);

            $bloqueado->update([
                'estado' => EstadoInforme::Firmado->value,
                'documento_id' => $documento->id,
                'firmado_en' => now(),
                'metodo_firma' => $metodo->value,
                'firmante_certificado' => $titular,
            ]);

            $this->apuntarEnHistoria($bloqueado, $actor);

            return $bloqueado->fresh();
        });
    }

    /**
     * Anula un informe firmado. Solo puede ejecutarlo el autor, con motivo.
     *
     * El documento firmado sigue en la custodia. Un informe anulado no se reabre.
     *
     * @param Informe $informe Informe firmado que se anula.
     * @param int $usuarioId ID del usuario que solicita la anulación.
     * @param string $motivo Motivo de anulación (obligatorio).
     *
     * @return Informe
     *
     * @throws \DomainException si el informe no está firmado, el usuario no es el autor o falta el motivo
     */
    public function anular(Informe $informe, int $usuarioId, string $motivo): Informe
    {
        if (! $informe->estaFirmado()) {
            throw new \DomainException('Solo se pueden anular informes en estado firmado.');
        }

        if ($informe->autor_id !== $usuarioId) {
            throw new \DomainException('Solo el autor puede anular su propio informe.');
        }

        if (trim($motivo) === '') {
            throw new \DomainException('El motivo de anulación es obligatorio.');
        }

        $informe->update([
            'estado' => EstadoInforme::Anulado->value,
            'motivo_anulacion' => trim($motivo),
            'anulado_en' => now(),
        ]);

        return $informe->fresh();
    }

    /**
     * Comprueba que el informe está en borrador y que quien actúa es su autor.
     *
     * @param Informe $informe Informe.
     * @param User $actor Usuario que actúa.
     *
     * @return void
     *
     * @throws \DomainException si no se cumple
     */
    private function exigirBorradorDelAutor(Informe $informe, User $actor): void
    {
        if ($informe->estado !== EstadoInforme::Borrador) {
            throw new \DomainException(
                'Solo se puede firmar un informe en estado borrador. '.
                "El informe #{$informe->id} está en estado '{$informe->estado->label()}'."
            );
        }

        if ($informe->autor_id !== $actor->id) {
            throw new \DomainException('Solo el autor puede firmar su informe.');
        }
    }

    /**
     * Deja en la Historia Social un apunte que enlaza el informe firmado.
     *
     * @param Informe $informe Informe recién firmado.
     * @param User $actor Autor.
     *
     * @return void
     */
    private function apuntarEnHistoria(Informe $informe, User $actor): void
    {
        $historiaId = $informe->historia_social_id
            ?? HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $informe->ciudadano_id)->latest()->value('id');

        if ($historiaId === null) {
            return;
        }

        Apunte::create([
            'historia_id' => $historiaId,
            'autor_id' => $actor->id,
            'fecha' => today()->toDateString(),
            'tipo' => TipoApunte::Documento,
            'apuntable_type' => $informe->getMorphClass(),
            'apuntable_id' => $informe->id,
            'contenido' => 'Informe firmado: '.$informe->plantilla->nombre.' ('.$informe->identificador().').',
            'visibilidad' => VisibilidadApunte::Profesionales,
            // Desde un encargo, el apunte lleva el vínculo (EA-5) y RN-15 mira el centro del encargo
            'derivacion_id' => $informe->derivacion_id,
        ]);
    }
}
