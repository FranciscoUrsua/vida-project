<?php

namespace App\Observers;

use App\Enums\AccionAuditEnum;
use App\Services\AuditService;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Observer que registra automáticamente escrituras sobre modelos Auditable.
 *
 * Las lecturas NO las registra este observer — deben registrarse
 * explícitamente desde el componente Livewire / Resource Filament
 * mediante AuditService::registrarAcceso().
 *
 * El observer omite el registro si no hay usuario autenticado
 * (procesos de consola, seeds, migraciones).
 *
 * @see docs/modulo-auditoria.md §3.2
 * @see AuditService
 */
class AuditObserver
{
    /**
     * Inyecta el servicio de auditoría.
     *
     * @param AuditService $service Servicio de auditoría.
     */
    public function __construct(private readonly AuditService $service) {}

    /**
     * Registra la creación de un modelo auditable.
     *
     * @param Model&Auditable $model Modelo afectado.
     */
    public function created(Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $this->service->registrarAcceso(
            user: Auth::user(),
            modelo: $model,
            accion: AccionAuditEnum::Crear,
            datosDespues: $this->snapshot($model),
        );
    }

    /**
     * Registra la edición de un modelo auditable con diff de campos cambiados.
     *
     * @param Model&Auditable $model Modelo afectado.
     */
    public function updated(Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $auditables = array_flip($model->camposAuditables());
        $cambios = array_intersect_key($model->getChanges(), $auditables);

        if (empty($cambios)) {
            return;
        }

        $originales = array_intersect_key($model->getOriginal(), array_flip(array_keys($cambios)));

        $this->service->registrarAcceso(
            user: Auth::user(),
            modelo: $model,
            accion: AccionAuditEnum::Editar,
            datosAntes: $this->serializar($originales),
            datosDespues: $this->serializar($cambios),
        );
    }

    /**
     * Registra la eliminación (soft o hard) de un modelo auditable.
     *
     * @param Model&Auditable $model Modelo afectado.
     */
    public function deleted(Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $this->service->registrarAcceso(
            user: Auth::user(),
            modelo: $model,
            accion: AccionAuditEnum::Eliminar,
            datosAntes: $this->snapshot($model),
        );
    }

    /**
     * Snapshot de todos los campos auditables del modelo.
     *
     * @param Model&Auditable $model
     *
     * @return array<string, mixed>
     */
    private function snapshot(Model $model): array
    {
        $auditables = array_flip($model->camposAuditables());

        return $this->serializar(array_intersect_key($model->getAttributes(), $auditables));
    }

    /**
     * Convierte valores no serializables (BackedEnum, Carbon) a tipos primitivos.
     *
     * En Laravel 12, getOriginal() y getChanges() devuelven valores casteados,
     * por lo que los enums llegan como instancias BackedEnum, no como strings.
     *
     * @param array<string, mixed> $valores
     *
     * @return array<string, mixed>
     */
    private function serializar(array $valores): array
    {
        return array_map(function (mixed $v): mixed {
            if ($v instanceof \BackedEnum) {
                return $v->value;
            }
            if ($v instanceof \DateTimeInterface) {
                return $v->format('Y-m-d H:i:s');
            }

            return $v;
        }, $valores);
    }
}
