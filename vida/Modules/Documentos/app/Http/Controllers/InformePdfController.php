<?php

namespace Modules\Documentos\Http\Controllers;

use App\Enums\AccionAuditEnum;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\ServicioGeneracionPDF;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF de un informe en borrador (fase 5, pasos 2 y 3): la vista previa del asistente y
 * el PDF para firmar que el autor descarga.
 *
 * Solo para su autor y mientras está en borrador (`InformePolicy::update`/`firmar`). No se
 * persiste nada: se generan al vuelo. Ambos accesos se auditan como «exportar» sobre el
 * informe, con la persona, porque contienen sus datos. El PDF firmado no sale de aquí:
 * se sirve desde la custodia (`DocumentoController`).
 */
class InformePdfController extends Controller
{
    /**
     * Vista previa del borrador, en línea (lleva la marca de borrador).
     *
     * @param Request $request Petición.
     * @param Informe $informe Borrador.
     *
     * @return Response
     */
    public function vistaPrevia(Request $request, Informe $informe): Response
    {
        return $this->entregar($request, $informe, 'update', borrador: true);
    }

    /**
     * PDF final sin firmar, como descarga, para firmarlo fuera de VIDA (F5-1).
     *
     * @param Request $request Petición.
     * @param Informe $informe Borrador.
     *
     * @return Response
     */
    public function paraFirmar(Request $request, Informe $informe): Response
    {
        return $this->entregar($request, $informe, 'firmar', borrador: false);
    }

    /**
     * Autoriza, genera, audita y construye la respuesta.
     *
     * @param Request $request Petición.
     * @param Informe $informe Borrador.
     * @param string $habilidad Habilidad de InformePolicy que se exige.
     * @param bool $borrador true para la vista previa.
     *
     * @return Response
     */
    private function entregar(Request $request, Informe $informe, string $habilidad, bool $borrador): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless(Gate::forUser($usuario)->allows($habilidad, $informe), 403, 'No puedes acceder a este borrador.');

        $generacion = app(ServicioGeneracionPDF::class);
        $contenido = $borrador ? $generacion->generarBorrador($informe) : $generacion->generarParaFirmar($informe);

        app(AuditService::class)->registrarAcceso(
            user: $usuario,
            modelo: $informe,
            accion: AccionAuditEnum::Exportar,
            ciudadanoId: $informe->ciudadano_id,
            contexto: ['informe_id' => $informe->id, 'pdf' => $borrador ? 'vista_previa' : 'para_firmar'],
        );

        $nombre = $informe->identificador().($borrador ? '-borrador' : '-para-firmar').'.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($borrador ? 'inline' : 'attachment').'; filename="'.$nombre.'"',
            'Content-Length' => (string) strlen($contenido),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
