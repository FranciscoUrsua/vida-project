<?php

namespace Modules\Documentos\Http\Controllers;

use App\Enums\AccionAuditEnum;
use App\Models\Ciudadano;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Documentos\Exceptions\IntegridadDocumentoException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Policies\DocumentoPolicy;
use Modules\Documentos\Services\LecturaDocumentoService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Única salida de documentos custodiados: autoriza, descifra en memoria, audita y
 * entrega el PDF con un nombre genérico.
 *
 * Ningún fichero se sirve desde el almacenamiento. La autorización va antes de
 * descifrar: a quien no tiene acceso no se le descifra nada.
 *
 * Los servicios se resuelven al atender la petición, no en el constructor:
 * `route:list` instancia los controladores y no debe exigir la clave maestra.
 */
class DocumentoController extends Controller
{
    /**
     * Muestra el PDF en el navegador (auditado como «ver»).
     *
     * @param Request $request Petición.
     * @param Documento $documento Documento solicitado.
     *
     * @return Response
     */
    public function ver(Request $request, Documento $documento): Response
    {
        return $this->entregar($request, $documento, AccionAuditEnum::Ver, 'inline');
    }

    /**
     * Descarga el PDF como fichero (auditado como «exportar»).
     *
     * @param Request $request Petición.
     * @param Documento $documento Documento solicitado.
     *
     * @return Response
     */
    public function descargar(Request $request, Documento $documento): Response
    {
        return $this->entregar($request, $documento, AccionAuditEnum::Exportar, 'attachment');
    }

    /**
     * Autoriza, descifra la versión vigente, registra el acceso y construye la respuesta.
     *
     * @param Request $request Petición.
     * @param Documento $documento Documento solicitado.
     * @param AccionAuditEnum $accion Ver o Exportar.
     * @param string $disposicion inline o attachment.
     *
     * @return Response
     */
    private function entregar(Request $request, Documento $documento, AccionAuditEnum $accion, string $disposicion): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $ciudadano = app(DocumentoPolicy::class)->ciudadanoAccesible($usuario, $documento);

        abort_if($ciudadano === null, 403, 'No tienes acceso a ninguna de las personas vinculadas a este documento.');

        $version = $documento->versionVigente;
        abort_if($version === null, 404, 'El documento no tiene una versión disponible.');

        $lectura = app(LecturaDocumentoService::class);

        try {
            $contenido = $lectura->contenido($version);
        } catch (IntegridadDocumentoException) {
            abort(500, 'No se ha podido recuperar el documento: su integridad no está garantizada.');
        }

        app(AuditService::class)->registrarAcceso(
            user: $usuario,
            modelo: $documento,
            accion: $accion,
            ciudadanoId: $ciudadano->id,
            contexto: [
                'documento_id' => $documento->id,
                'documento_version_id' => $version->id,
                'ciudadanos_vinculados' => $documento->vinculosActivos()
                    ->where('vinculable_type', (new Ciudadano)->getMorphClass())
                    ->orderBy('vinculable_id')
                    ->pluck('vinculable_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            ],
        );

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposicion.'; filename="'.$lectura->nombreDescarga($version).'"',
            'Content-Length' => (string) strlen($contenido),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
