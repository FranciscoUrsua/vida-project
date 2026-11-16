<?php

namespace Modules\Documentos\Policies;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Documentos\Contracts\AccesoDocumentoAdicional;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Models\Documento;

/**
 * Acceso a documentos custodiados.
 *
 * Tres vías de lectura, y ninguna para un documento retirado o destruido:
 * - **Carpeta completa** (`documento.ver_carpeta`): quien puede ver al menos una de las
 *   personas con vínculo activo (CiudadanoPolicy::view, con la regla de colectivo
 *   protegido). Un documento compartido lo abre quien pueda ver a cualquiera de sus
 *   personas: regla provisional de la custodia v2, pendiente de revisar (BACKLOG).
 * - **Propios del día** (`documento.gestionar_propios`): consulta básica y tramitación no
 *   ven la carpeta; solo lo que han subido ellos, durante el día natural de la subida
 *   (fase 4, docs/roles-consulta-basica-tramitacion.md §3.3). Pasado el día, desaparece.
 * - **Vías de otros módulos** (AccesoDocumentoAdicional): p. ej. tramitación ve los
 *   documentos de las personas cuyas solicitudes tramita.
 *
 * Solo cuentan los vínculos a ciudadanos: los vínculos a planes o valoraciones no
 * dan acceso por sí mismos.
 *
 * Excepción (fase 5): el PDF de un informe firmado es contenido de la Historia Social
 * y solo lo ve quien supera `InformePolicy::view`, con independencia de las vías de arriba.
 */
class DocumentoPolicy
{
    /** Etiqueta del contenedor con las vías de acceso de otros módulos. */
    public const ETIQUETA_ACCESOS = 'documentos.accesos_adicionales';

    /**
     * Decide si el usuario puede visualizar el documento.
     *
     * @param User $usuario Usuario autenticado.
     * @param Documento $documento Documento solicitado.
     *
     * @return bool
     */
    public function view(User $usuario, Documento $documento): bool
    {
        if (! $documento->estaDisponible()) {
            return false;
        }

        // El PDF de un informe es contenido de la Historia Social (fase 5): solo lo ve quien
        // puede ver el informe, aunque otra vía (carpeta, tramitación…) diera acceso al documento.
        $informe = $documento->informe;
        if ($informe !== null) {
            return Gate::forUser($usuario)->allows('view', $informe);
        }

        if ($usuario->can('documento.ver_carpeta')) {
            return $this->ciudadanoAccesible($usuario, $documento) !== null;
        }

        if ($this->gestionarPropio($usuario, $documento)) {
            return true;
        }

        foreach (app()->tagged(self::ETIQUETA_ACCESOS) as $acceso) {
            /** @var AccesoDocumentoAdicional $acceso */
            if ($acceso->permite($usuario, $documento)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decide si el usuario puede descargar el documento. Misma regla que visualizar.
     *
     * @param User $usuario Usuario autenticado.
     * @param Documento $documento Documento solicitado.
     *
     * @return bool
     */
    public function download(User $usuario, Documento $documento): bool
    {
        return $this->view($usuario, $documento);
    }

    /**
     * Decide si el usuario gestiona el documento como propio del día: verlo, sustituir el
     * fichero, cambiar sus datos y retirarlo.
     *
     * Exige `documento.gestionar_propios`, que lo haya subido él hoy, que siga vigente y
     * que pueda ver a alguna de sus personas (una persona protegida fuera de su ámbito, no).
     *
     * @param User $usuario Usuario autenticado.
     * @param Documento $documento Documento.
     *
     * @return bool
     */
    public function gestionarPropio(User $usuario, Documento $documento): bool
    {
        return $usuario->can('documento.gestionar_propios')
            && $documento->estado === EstadoDocumento::Vigente
            && $documento->esPropioDelDia($usuario)
            && $this->ciudadanoAccesible($usuario, $documento) !== null;
    }

    /**
     * Primera persona vinculada que el usuario puede ver, o null si no puede ver a ninguna.
     *
     * La cargan sin AmbitoUoScope porque la decisión es de CiudadanoPolicy, no del
     * filtro de listados (que es más estricto que el acceso de lectura).
     *
     * @param User $usuario Usuario.
     * @param Documento $documento Documento.
     *
     * @return Ciudadano|null
     */
    public function ciudadanoAccesible(User $usuario, Documento $documento): ?Ciudadano
    {
        $ids = $documento->vinculosActivos()
            ->where('vinculable_type', (new Ciudadano)->getMorphClass())
            ->orderBy('id')
            ->pluck('vinculable_id');

        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->first(fn (Ciudadano $ciudadano): bool => Gate::forUser($usuario)->allows('view', $ciudadano));
    }
}
