<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persona de contacto adicional de un centro.
 */
class ContactoCentro extends Model
{
    protected $table = 'contactos_centro';

    protected $fillable = [
        'centro_id',
        'nombre',
        'rol',
        'telefono',
        'email',
        'activo',
        'notas',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }
}
