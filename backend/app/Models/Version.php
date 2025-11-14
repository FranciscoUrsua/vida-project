<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Version extends Model
{
    use HasFactory;

    protected $table = 'versions'; // Tabla central para todas entidades (polimórfica)

    protected $fillable = [
        'versionable_id',
        'versionable_type',
        'version',
        'data', // Snapshot JSON completo del modelo
        'changed_by', // FK a AppUser
        'change_reason', // Motivo opcional
    ];

    protected $casts = [
        'data' => 'encrypted:array', // Auto JSON encode/decode encriptado en DB
        'version' => 'integer',
    ];

    // Relación polimórfica inversa: a la entidad versionada (SocialUser, Centro, Profesional, etc.)
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    // Relación al usuario que hizo el cambio (AppUser)
    public function changedBy()
    {
        return $this->belongsTo(AppUser::class, 'changed_by');
    }

    // Scope para versiones de una entidad específica
    public function scopeForEntity($query, string $modelClass, int $modelId = null)
    {
        $query->where('versionable_type', $modelClass)
              ->when($modelId, fn($q) => $q->where('versionable_id', $modelId))
              ->orderBy('version', 'asc');
    }

    // Scope para última versión de una entidad
    public function scopeLatestForEntity($query, string $modelClass, int $modelId)
    {
        return $query->forEntity($modelClass, $modelId)->latest('version')->first();
    }

    // Método helper para obtener diff entre versiones (opcional, usa array_diff)
    public function getDiffFromPrevious(): ?array
    {
        $previous = static::forEntity($this->versionable_type, $this->versionable_id)
            ->where('version', '<', $this->version)
            ->latest('version')
            ->first();

        if (!$previous) return null;

        $prevData = $previous->data;
        $currentData = $this->data;

        return array_diff_assoc($currentData, $prevData); // Cambios: keys nuevos/modificados
    }
}
