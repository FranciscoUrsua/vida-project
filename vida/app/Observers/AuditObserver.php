<?php

namespace App\Observers;

use App\Contracts\AuditableModel;
use App\Enums\AccionAuditEnum;
use App\Services\AuditService;
use App\Support\Cifrado\OriginalSinDescifrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

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
     */
    public function __construct(private readonly AuditService $service) {}

    /**
     * Registra la creación de un modelo auditable.
     *
     * @param Model $model Modelo afectado.
     *
     * @phpstan-param Model&AuditableModel $model
     */
    public function created(Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $despues = $this->snapshot($model);

        $this->service->registrarAcceso(
            user: Auth::user(),
            modelo: $model,
            accion: AccionAuditEnum::Crear,
            contexto: $this->resumen($model, AccionAuditEnum::Crear, [], $despues),
            datosDespues: $despues,
        );
    }

    /**
     * Registra la edición de un modelo auditable con diff de campos cambiados.
     *
     * @param Model $model Modelo afectado.
     *
     * @phpstan-param Model&AuditableModel $model
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

        // Cifrados tal cual están en la BD, como ya llegan en getChanges() (CLAUDE.md §3)
        $originales = array_intersect_key(OriginalSinDescifrar::de($model), array_flip(array_keys($cambios)));

        $antes = $this->serializar($originales);
        $despues = $this->serializar($cambios);

        $this->service->registrarAcceso(
            user: Auth::user(),
            modelo: $model,
            accion: AccionAuditEnum::Editar,
            contexto: $this->resumen($model, AccionAuditEnum::Editar, $antes, $despues),
            datosAntes: $antes,
            datosDespues: $despues,
        );
    }

    /**
     * Registra la eliminación (soft o hard) de un modelo auditable.
     *
     * @param Model $model Modelo afectado.
     *
     * @phpstan-param Model&AuditableModel $model
     */
    public function deleting(Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $antes = $this->snapshot($model);

        $this->service->registrarAcceso(
            user: Auth::user(),
            modelo: $model,
            accion: AccionAuditEnum::Eliminar,
            contexto: $this->resumen($model, AccionAuditEnum::Eliminar, $antes, []),
            datosAntes: $antes,
        );
    }

    /**
     * Contexto con el resumen legible de la escritura (U3.1), calculado ahora y guardado: el
     * registro de auditoría es inmutable y el texto vale aunque el recurso cambie después.
     * Un fallo al componer el resumen no impide auditar (el widget cae a la etiqueta).
     *
     * @param Model $model
     * @param AccionAuditEnum $accion
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $despues
     *
     * @phpstan-param Model&AuditableModel $model
     *
     * @return array<string, string>
     */
    private function resumen(Model $model, AccionAuditEnum $accion, array $antes, array $despues): array
    {
        try {
            $resumen = $model->resumenAuditoria($accion, $antes, $despues);
        } catch (Throwable $e) {
            Log::warning('No se pudo componer el resumen de auditoría', ['modelo' => $model::class, 'error' => $e->getMessage()]);
            $resumen = null;
        }

        return $resumen !== null ? ['resumen' => $resumen] : [];
    }

    /**
     * Snapshot de todos los campos auditables del modelo.
     *
     * @param Model $model
     *
     * @phpstan-param Model&AuditableModel $model
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
