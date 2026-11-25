<?php

namespace Modules\Intervencion\Services;

use App\Models\AccesoProtegido;
use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\ModalidadDerivacion;

/**
 * Ámbito del profesional de encargo (`ciudadano.leer_por_encargo`): sus «ciudadanos
 * asignados» son las personas de los encargos profesionales en los que es o ha sido el
 * profesional asignado, con histórico (EP-6 de
 * docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * Restricción de dominio (CLAUDE.md §3, RN-17 ampliada, EP-7): una persona protegida solo
 * entra en su ámbito mientras el encargo está **aceptado**. Cerrado el encargo, deja de
 * verla salvo con un acceso protegido aprobado y vigente (RN-18, «Solicitar acceso»).
 * Un encargo pendiente, rechazado o anulado no da acceso a nadie.
 *
 * Se consulta con el query builder, sin los scopes de ámbito de UO de las historias: el
 * profesional de encargo no tiene ámbito de UO sobre la historia y aquí solo se resuelve
 * qué personas son suyas.
 */
class EncargosDeUsuario
{
    /**
     * Ids de las personas de los encargos del usuario que puede ver, sin repetir.
     *
     * Incluye las protegidas solo con un encargo aceptado o un acceso protegido vigente.
     *
     * @param User $usuario
     *
     * @return list<int>
     */
    public function personaIds(User $usuario): array
    {
        $filas = $this->filas($usuario);

        $ids = [];
        foreach ($filas as $fila) {
            if ($this->filaVisible($usuario, $fila)) {
                $ids[(int) $fila->ciudadano_id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Ids de todas las personas de sus encargos aceptados o cerrados, también las protegidas
     * que ya no puede ver: el buscador las devuelve enmascaradas con «Solicitar acceso» (EP-7).
     *
     * @param User $usuario
     *
     * @return list<int>
     */
    public function todasLasPersonaIds(User $usuario): array
    {
        return array_values(array_unique(array_map(fn ($fila) => (int) $fila->ciudadano_id, $this->filas($usuario))));
    }

    /**
     * Si el usuario ve a la persona por sus encargos.
     *
     * @param User $usuario
     * @param Ciudadano $ciudadano
     *
     * @return bool
     */
    public function ve(User $usuario, Ciudadano $ciudadano): bool
    {
        foreach ($this->filas($usuario, $ciudadano->id) as $fila) {
            if ($this->filaVisible($usuario, $fila)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Encargos aceptados o cerrados del usuario con la persona y su protección.
     *
     * @param User $usuario
     * @param int|null $ciudadanoId Limita a una persona.
     *
     * @return list<object{ciudadano_id: int, estado: string, protegido: bool}>
     */
    private function filas(User $usuario, ?int $ciudadanoId = null): array
    {
        return DB::table('derivaciones')
            ->join('historias_sociales', 'historias_sociales.id', '=', 'derivaciones.historia_id')
            ->join('ciudadanos', 'ciudadanos.id', '=', 'historias_sociales.ciudadano_id')
            ->where('derivaciones.modalidad', ModalidadDerivacion::Encargo->value)
            ->where('derivaciones.profesional_responsable_id', $usuario->id)
            ->whereIn('derivaciones.estado', [EstadoDerivacion::Aceptada->value, EstadoDerivacion::Cerrada->value])
            ->whereNull('derivaciones.deleted_at')
            ->when($ciudadanoId !== null, fn ($q) => $q->where('ciudadanos.id', $ciudadanoId))
            ->select([
                'ciudadanos.id as ciudadano_id',
                'derivaciones.estado',
                // Protegida por la persona o por la historia, como CiudadanoPolicy::consultaExternaPermitida()
                DB::raw('(ciudadanos.colectivo_extra_protegido OR historias_sociales.ciudadano_protegido) as protegido'),
            ])
            ->get()
            ->all();
    }

    /**
     * Si una fila de encargo da acceso a la persona (EP-7).
     *
     * @param User $usuario
     * @param object{ciudadano_id: int, estado: string, protegido: bool} $fila
     *
     * @return bool
     */
    private function filaVisible(User $usuario, object $fila): bool
    {
        if (! $fila->protegido || $fila->estado === EstadoDerivacion::Aceptada->value) {
            return true;
        }

        // Protegida con el encargo cerrado: solo con acceso protegido aprobado y vigente (RN-18)
        return AccesoProtegido::vigentePara($usuario->id, (int) $fila->ciudadano_id)->exists();
    }
}
