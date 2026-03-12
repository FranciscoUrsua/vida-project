<?php

namespace Modules\Organizacion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de Colectivo Especialmente Protegido.
 *
 * Define los colectivos cuyos ciudadanos requieren aprobación previa
 * para el acceso desde fuera de la UO responsable (principio 3.6).
 * Actualmente: menores y víctimas de violencia de género.
 *
 * @property int $id
 * @property string $nombre
 * @property string $descripcion
 * @property bool $requiere_aprobacion_previa
 * @property bool $activo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 3
 */
class ColectivoProtegido extends Model
{
    /** @var string */
    protected $table = 'colectivos_protegidos';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'descripcion',
        'requiere_aprobacion_previa',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'requiere_aprobacion_previa' => 'boolean',
        'activo'                     => 'boolean',
    ];

    /**
     * Filtra únicamente los colectivos activos.
     *
     * @param Builder<ColectivoProtegido> $consulta
     * @return Builder<ColectivoProtegido>
     */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }

    /**
     * Filtra colectivos que requieren aprobación previa de acceso.
     *
     * @param Builder<ColectivoProtegido> $consulta
     * @return Builder<ColectivoProtegido>
     */
    public function scopeRequierenAprobacion(Builder $consulta): Builder
    {
        return $consulta->where('requiere_aprobacion_previa', true);
    }
}
