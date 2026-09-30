<?php

namespace Modules\Agenda\Models;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Persona que acompañó a la titular de una cita (docs/modulo-citas.md §2.5).
 *
 * Solo registro: no crea citas ni vínculos para el acompañante. Se enlaza al
 * ciudadano si está en VIDA; si no, se guarda su nombre cifrado.
 *
 * @property int $id
 * @property int $cita_id
 * @property string $relacion Clave de catalogos_sistema, grupo 'cita.relacion_acompanante'.
 * @property int|null $ciudadano_id
 * @property string|null $nombre Cifrado.
 * @property int $registrado_por_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Cita $cita
 * @property-read Ciudadano|null $ciudadano
 */
class CitaAcompanante extends Model
{
    /** @var string */
    protected $table = 'cita_acompanantes';

    /** @var list<string> */
    protected $fillable = ['cita_id', 'relacion', 'ciudadano_id', 'nombre', 'registrado_por_id'];

    /** @var array<string, string> */
    protected $casts = ['nombre' => 'encrypted'];

    /**
     * Cita en la que acompañó.
     *
     * @return BelongsTo<Cita, $this>
     */
    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    /**
     * Acompañante, si está en VIDA.
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id')->withoutGlobalScope(AmbitoUoScope::class);
    }

    /**
     * Quién lo registró.
     *
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    /**
     * Nombre para mostrar: el del ciudadano enlazado o el anotado.
     *
     * @return string
     */
    public function nombreVisible(): string
    {
        return $this->ciudadano?->nombre_completo ?? (string) $this->nombre;
    }

    /**
     * Ciudadano para auditoría: la titular de la cita.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return $this->cita?->ciudadano_id;
    }
}
