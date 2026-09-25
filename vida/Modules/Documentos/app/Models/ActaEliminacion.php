<?php

namespace Modules\Documentos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Acta de eliminación: constancia de las versiones de documento destruidas.
 *
 * Es inmutable (no se edita ni se borra): es la única prueba de qué se destruyó,
 * cuándo y con qué aprobación. El detalle guarda solo metadatos, nunca contenido
 * ni nombres originales.
 *
 * @property int $id
 * @property string $numero
 * @property int $aprobada_por
 * @property Carbon $aprobada_en
 * @property string $motivo
 * @property list<array<string, mixed>> $detalle
 */
class ActaEliminacion extends Model
{
    /** @var string */
    protected $table = 'actas_eliminacion';

    /** @var list<string> */
    protected $fillable = [
        'numero',
        'aprobada_por',
        'aprobada_en',
        'motivo',
        'detalle',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'aprobada_en' => 'datetime',
        'detalle' => 'array',
    ];

    /**
     * Impide modificar o borrar un acta.
     *
     *
     * @throws \LogicException al intentar actualizar o borrar
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Un acta de eliminación no se puede modificar.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Un acta de eliminación no se puede borrar.');
        });
    }

    /**
     * Siguiente número de acta del año indicado (formato AAAA/NNNN).
     *
     * @param int $anyo Año del acta.
     *
     * @return string
     */
    public static function siguienteNumero(int $anyo): string
    {
        $ultimo = self::where('numero', 'like', "{$anyo}/%")->orderByDesc('numero')->value('numero');
        $siguiente = $ultimo === null ? 1 : ((int) substr($ultimo, 5)) + 1;

        return sprintf('%d/%04d', $anyo, $siguiente);
    }

    /**
     * Usuario que aprobó la eliminación.
     *
     * @return BelongsTo<User, $this>
     */
    public function aprobadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }
}
