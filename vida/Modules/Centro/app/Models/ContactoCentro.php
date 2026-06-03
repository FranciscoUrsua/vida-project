<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persona de contacto adicional de un centro.
 *
 * Complementa los datos de contacto del propio centro con personas
 * responsables de áreas específicas (coordinación, admisiones, etc.).
 *
 * @property int $id
 * @property int $centro_id
 * @property string $nombre
 * @property string|null $rol
 * @property string|null $telefono
 * @property string|null $email
 * @property bool $activo
 * @property string|null $notas
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

    /**
     * Centro al que pertenece este contacto.
     *
     * @return BelongsTo<Centro, self>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }
}
