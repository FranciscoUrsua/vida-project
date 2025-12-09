<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class UnidadOrganizativa extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'codigo', 'nombre_corto', 'descripcion', 'parent_id', 'nivel', 'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'nivel' => 'integer',
    ];

    public function parent() { return $this->belongsTo(UnidadOrganizativa::class, 'parent_id'); }
    public function hijos() { return $this->hasMany(UnidadOrganizativa::class, 'parent_id'); }
    public function appUsers() { return $this->hasMany(AppUser::class); }

    // Nueva: Centros asignados directamente a esta OA (e.g., departamento → centros)
    public function centros() { return $this->hasMany(Centro::class); }

    // Descendientes (OAs hijas/nietas) + centros en toda la rama
    public function descendientesConCentros()
    {
        $descOAs = $this->descendientes();
        $centros = Centro::whereIn('unidad_organizativa_id', $descOAs->pluck('id')->concat([$this->id]))->get();
        return ['oas' => $descOAs, 'centros' => $centros];
    }

    public function descendientes()
    {
        return self::whereRaw('
            id IN (
                WITH RECURSIVE descendientes AS (
                    SELECT id FROM unidades_organizativas WHERE id = ?
                    UNION ALL
                    SELECT u.id FROM unidades_organizativas u
                    INNER JOIN descendientes d ON u.parent_id = d.id
                    WHERE u.deleted_at IS NULL
                )
                SELECT id FROM descendientes
            )
        ', [$this->id])->get();
    }

    public function esDescendienteDe(UnidadOrganizativa $padre)
    {
        return $padre->descendientes()->pluck('id')->contains($this->id);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) $model->id = (string) Str::uuid();
        });

        static::saving(function ($model) {
            $model->nivel = $model->parent_id ? self::find($model->parent_id)->nivel + 1 : 1;

            if ($model->parent_id) {
                $ancestros = self::find($model->parent_id)->descendientes()->pluck('id');
                if ($ancestros->contains($model->id)) {
                    throw new \Exception('No se permiten ciclos en la jerarquía.');
                }
            }
        });
    }

    public function scopeActivas($query) { return $query->where('activa', true)->whereNull('deleted_at'); }
}
