<?php

namespace Modules\Documentos\Policies;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Documentos\Models\Documento;

/**
 * Acceso a documentos custodiados: los ve quien puede ver al menos una de las
 * personas con vínculo activo.
 *
 * Aplica CiudadanoPolicy::view a cada persona, igual que la ficha del ciudadano:
 * acceso amplio de lectura (principio de acceso: «acceso amplio, auditoría total»),
 * salvo colectivos especialmente protegidos, que exigen un acceso protegido aprobado
 * y vigente. Un documento compartido lo abre quien pueda ver a cualquiera de sus
 * personas: es la regla provisional de la custodia v2, pendiente de revisar (BACKLOG).
 *
 * Solo cuentan los vínculos a ciudadanos: los vínculos a planes o valoraciones no
 * dan acceso por sí mismos.
 */
class DocumentoPolicy
{
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
        return $this->ciudadanoAccesible($usuario, $documento) !== null;
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
