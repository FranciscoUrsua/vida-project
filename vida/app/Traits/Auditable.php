<?php

namespace App\Traits;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use App\Observers\AuditObserver;
use App\Services\AuditService;
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
 * @see AuditService
 * @see AuditObserver
 */
trait Auditable
{
    /**
     * Registra el observer de auditoría al arrancar el modelo.
     */
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    /**
     * Todos los registros de auditoría de este modelo.
     *
     * @return MorphMany<Audit, $this>
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
     */
    public function getCiudadanoId(): ?int
    {
        return null;
    }

    /**
     * Resumen legible por defecto de una escritura (U3): «Registró / Modificó / Eliminó» y el
     * nombre del modelo de `lang/es/auditoria.php` (`modelos`). Cada modelo lo concreta si
     * hace falta (p. ej. «Canceló la prescripción de “X”»).
     *
     * @param AccionAuditEnum $accion
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $despues
     *
     * @return string|null
     */
    public function resumenAuditoria(AccionAuditEnum $accion, array $antes, array $despues): ?string
    {
        $modelo = __('auditoria.modelos')[static::class] ?? 'un registro';

        return match ($accion) {
            AccionAuditEnum::Crear => "Registró {$modelo}",
            AccionAuditEnum::Editar => "Modificó {$modelo}",
            AccionAuditEnum::Eliminar => "Eliminó {$modelo}",
            default => null,
        };
    }
}
