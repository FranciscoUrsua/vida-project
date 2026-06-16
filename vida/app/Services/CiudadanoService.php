<?php

namespace App\Services;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Policies\CiudadanoPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio de dominio para el Ciudadano.
 *
 * Centraliza las operaciones de escritura sobre Ciudadano garantizando que
 * todas las mutaciones pasen por el par (GlobalScope + Policy) definido en la
 * estrategia de seguridad en profundidad.
 *
 * Patrón de cada método de escritura:
 *   1. Recuperar el registro (el GlobalScope ya filtra por ámbito de UO).
 *   2. Verificar la Policy (Gate::authorize).
 *   3. Ejecutar la lógica de negocio.
 *
 * La implementación completa corresponde al módulo Ciudadania (pendiente).
 * Este servicio es la capa de autorización mínima para el stub actual.
 *
 * @see AmbitoUoScope
 * @see CiudadanoPolicy
 *
 * @todo Mover a Modules\Ciudadania\Services\CiudadanoService cuando se implemente el módulo.
 */
class CiudadanoService
{
    /**
     * Da de alta un nuevo ciudadano en el sistema.
     *
     * @param array<string, mixed> $datos Datos del ciudadano a crear
     *
     * @return Ciudadano Ciudadano creado.
     *
     * @throws AuthorizationException Si el usuario no tiene permiso de crear ciudadanos
     */
    public function crear(array $datos): Ciudadano
    {
        Gate::authorize('create', Ciudadano::class);

        // TODO: mover la lógica de negocio aquí (deduplicación por documento, niveles de identificación, etc.)
        return Ciudadano::create($datos);
    }

    /**
     * Actualiza los datos de un ciudadano existente.
     *
     * El GlobalScope garantiza que findOrFail solo encuentra el ciudadano
     * si el usuario tiene acceso al ámbito de UO (vía Historia Social).
     * La Policy verifica adicionalmente el permiso de edición.
     *
     * @param int $id ID del ciudadano
     * @param array<string, mixed> $datos Campos a actualizar
     *
     * @return Ciudadano Ciudadano actualizado.
     *
     * @throws AuthorizationException Si el usuario no tiene permiso de editar
     * @throws ModelNotFoundException Si el ciudadano no existe o no está en el ámbito del usuario
     */
    public function actualizar(int $id, array $datos): Ciudadano
    {
        $ciudadano = Ciudadano::findOrFail($id); // GlobalScope ya filtra por ámbito
        Gate::authorize('update', $ciudadano);    // Policy verifica permiso atómico + UO

        // TODO: mover la lógica de negocio aquí (historial de cambios, cifrado de campos sensibles, etc.)
        $ciudadano->update($datos);

        return $ciudadano;
    }

    /**
     * Elimina (baja lógica) un ciudadano del sistema.
     *
     * Solo aplica soft delete. Los datos históricos se conservan
     * por principio de inmutabilidad del pasado (principio 4.2).
     *
     * @param int $id ID del ciudadano
     *
     * @return void
     *
     * @throws AuthorizationException Si el usuario no tiene permiso de eliminar
     * @throws ModelNotFoundException Si el ciudadano no existe o no está en el ámbito del usuario
     */
    public function eliminar(int $id): void
    {
        $ciudadano = Ciudadano::findOrFail($id); // GlobalScope ya filtra por ámbito
        Gate::authorize('delete', $ciudadano);    // Policy verifica permiso atómico + UO

        // TODO: mover la lógica de negocio aquí (verificar Historias activas, baja lógica en cascada, etc.)
        $ciudadano->delete();
    }
}
