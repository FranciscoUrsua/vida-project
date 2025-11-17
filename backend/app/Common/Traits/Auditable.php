<?php
namespace App\Common\Traits;

use App\Common\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::retrieved(function (Model $model) {
            app(AuditService::class)->logAudit($model, 'retrieved', [], $model->getAttributes());
        });

        static::creating(function (Model $model) {
            $model->created_by = auth()->id() ?? null;
            $model->updated_by = $model->created_by;
        });

        static::created(function (Model $model) {
            app(AuditService::class)->logAudit($model, 'created', [], $model->getAttributes());
        });

        static::updating(function (Model $model) {
            $model->updated_by = auth()->id() ?? null;
        });

        static::updated(function (Model $model) {
            $old = $model->getOriginal();
            $new = $model->getAttributes();
            app(AuditService::class)->logAudit($model, 'updated', $old, $new);
        });

        static::deleting(function (Model $model) {
            app(AuditService::class)->logAudit($model, 'deleted', $model->getAttributes(), []);
        });

        static::restored(function (Model $model) {
            app(AuditService::class)->logAudit($model, 'restored', [], $model->getAttributes());
        });
    }

    // Relación morph a audits (reutilizable)
    public function audits()
    {
        return $this->morphMany(\App\Models\Audit::class, 'auditable'); // Asume modelo Audit
    }
}
