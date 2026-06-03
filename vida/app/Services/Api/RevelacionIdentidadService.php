<?php

namespace App\Services\Api;

use App\Models\Ciudadano;
use App\Models\RevelacionIdentidad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de reversión controlada de seudonimización.
 *
 * Implementa la 'tabla de correspondencias' alias → ciudadano_id
 * sin almacenarla: recorre los ciudadanos activos calculando el alias
 * en vuelo hasta encontrar coincidencia. La operación requiere el permiso
 * atómico 'ciudadano.revelar_identidad' y queda registrada en auditoría
 * con justificación obligatoria.
 *
 * Ver docs/anonimizacion.md § 6.3.
 */
class RevelacionIdentidadService
{
    /**
     * Resuelve un alias seudonimizado al ciudadano real.
     *
     * @param string $alias Alias con formato CIU-{8 hex}
     * @param int $usuarioId ID del usuario que solicita la revelación
     * @param string $justificacion Motivo obligatorio — queda en auditoría
     *
     * @throws ValidationException Si justificacion está vacía
     * @throws AuthorizationException Si el usuario no tiene permiso ciudadano.revelar_identidad
     * @throws ModelNotFoundException Si ningún ciudadano activo coincide con el alias
     */
    public function revelarPorAlias(string $alias, int $usuarioId, string $justificacion): Ciudadano
    {
        if (trim($justificacion) === '') {
            throw ValidationException::withMessages([
                'justificacion' => 'La justificación es obligatoria para revelar una identidad',
            ]);
        }

        $usuario = User::findOrFail($usuarioId);

        if (! $usuario->can('ciudadano.revelar_identidad')) {
            throw new AuthorizationException(
                "El usuario {$usuarioId} no tiene el permiso 'ciudadano.revelar_identidad'"
            );
        }

        $ciudadano = $this->buscarPorAlias($alias);

        RevelacionIdentidad::create([
            'usuario_id' => $usuarioId,
            'accion' => 'revelar_identidad',
            'alias' => $alias,
            'ciudadano_id' => $ciudadano->id,
            'justificacion' => $justificacion,
        ]);

        return $ciudadano;
    }

    /**
     * Itera sobre los ciudadanos activos y computa el alias en vuelo para cada uno.
     *
     * La búsqueda es O(n) sobre los ciudadanos activos — apropiada para la operación
     * de revelación que es excepcional y privilegiada, no un hot path.
     *
     * @throws ModelNotFoundException Si ningún ciudadano produce el alias buscado
     */
    private function buscarPorAlias(string $alias): Ciudadano
    {
        $clave = config('app.pseudonym_key');

        $encontrado = Ciudadano::where('activo', true)
            ->get()
            ->first(function (Ciudadano $ciudadano) use ($alias, $clave): bool {
                $aliasComputado = 'CIU-'.substr(
                    hash_hmac('sha256', (string) $ciudadano->id, $clave),
                    0,
                    8
                );

                return $aliasComputado === $alias;
            });

        if ($encontrado === null) {
            throw (new ModelNotFoundException)->setModel(Ciudadano::class);
        }

        return $encontrado;
    }
}
