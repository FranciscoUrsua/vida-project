<?php

namespace App\Traits;

use App\Models\Audit;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait para modelos Eloquent que manejan datos de ciudadanos.
 *
 * Activa el AuditObserver para registrar automáticamente operaciones
 * de escritura (crear, editar, eliminar). Las lecturas deben registrarse
 * explícitamente mediante AuditService::registrarAcceso().
 *
 * Cada modelo que use este trait debe implementar getCiudadanoId() si
 * el ciudadano_id no es un atributo directo del modelo.
 *
 * @see docs/modulo-auditoria.md §3.1
 * @see \App\Services\AuditService
 * @see \App\Observers\AuditObserver
 */
trait Auditable
{
    /**
     * Registra el observer de auditoría al arrancar el modelo.
     *
     * @return void
     */
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    /**
     * Todos los registros de auditoría de este modelo.
     *
     * @return MorphMany<Audit>
     */
    public function audits(): MorphMany
    {
        return $this->morphMany(Audit::class, 'auditable');
    }

    /**
     * Campos incluidos en los snapshots datos_antes / datos_despues.
     *
     * Por defecto, todos los fillables. Cada modelo puede sobrescribir
     * este método para excluir campos sensibles (tokens internos, etc.).
     *
     * @return list<string>
     */
    public function camposAuditables(): array
    {
        return $this->getFillable();
    }

    /**
     * Devuelve el ciudadano_id asociado a este registro.
     *
     * Cada modelo debe sobrescribir este método si el ciudadano_id
     * no es un atributo directo (p.ej.: llega a través de historia_id).
     * Los modelos que representan directamente a un ciudadano devuelven $this->id.
     *
     * @return int|null ID del ciudadano asociado o null si no aplica.
     */
    public function getCiudadanoId(): ?int
    {
        return null;
    }
}
