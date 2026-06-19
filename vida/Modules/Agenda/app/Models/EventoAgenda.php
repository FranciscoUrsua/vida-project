<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Agenda\Database\Factories\EventoAgendaFactory;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Espacio;

/**
 * Evento en la agenda del centro.
 *
 * Bloqueo de tiempo sin ciudadano asociado (reuniones, formaciones, mesas
 * de coordinación). No genera historia social. Puede reservar un espacio
 * físico del centro y convocar a profesionales vía pivot evento_usuario.
 *
 * El tipo_evento referencia un valor de catalogos_sistema (grupo: tipo_evento_agenda).
 * No se modela como enum porque es puramente clasificatorio (Principio 3.10).
 *
 * @property int $id
 * @property int $centro_id
 * @property string $tipo_evento
 * @property string $titulo
 * @property string|null $descripcion
 * @property Carbon $fecha
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property int|null $espacio_id
 * @property int $creado_por_id
 * @property string|null $notas
 */
class EventoAgenda extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory(): EventoAgendaFactory
    {
        return EventoAgendaFactory::new();
    }

    protected $table = 'eventos_agenda';

    protected $guarded = [];

    protected $casts = [
        'fecha' => 'date',
    ];

    /**
     * Centro al que pertenece el evento.
     *
     * @return BelongsTo<Centro, self>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Espacio reservado por el evento, si existe.
     *
     * @return BelongsTo<Espacio, self>
     */
    public function espacio(): BelongsTo
    {
        return $this->belongsTo(Espacio::class);
    }

    /**
     * Usuario que creó el evento.
     *
     * @return BelongsTo<User, self>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * Profesionales convocados al evento.
     *
     * @return BelongsToMany<User>
     */
    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'evento_usuario', 'evento_agenda_id', 'usuario_id')
            ->withPivot(['confirmado', 'notas'])
            ->withTimestamps();
    }

    /**
     * Filtra eventos de una fecha concreta.
     *
     * @param Builder<EventoAgenda> $query
     * @param mixed $fecha
     *
     * @return Builder<EventoAgenda>
     */
    public function scopeDelDia(Builder $query, $fecha): Builder
    {
        return $query->where('fecha', $fecha);
    }

    /**
     * Filtra eventos de un centro.
     *
     * @param Builder<EventoAgenda> $query
     *
     * @return Builder<EventoAgenda>
     */
    public function scopeDelCentro(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId);
    }

    /**
     * Filtra eventos en los que participa un profesional.
     *
     * @param Builder<EventoAgenda> $query
     *
     * @return Builder<EventoAgenda>
     */
    public function scopeDelProfesional(Builder $query, int $usuarioId): Builder
    {
        return $query->whereHas('profesionales', fn (Builder $q) => $q->where('users.id', $usuarioId));
    }

    /**
     * Convoca a los profesionales al evento y bloquea sus slots disponibles.
     *
     * Para cada profesional, marca como 'bloqueado_evento' todos los slots
     * en estado 'disponible' cuya hora_inicio cae dentro de la franja del evento.
     * Los slots en estado 'reservado' no se bloquean; las citas confirmadas
     * afectadas se devuelven como conflictos para que el supervisor las gestione.
     *
     * @param array<int> $usuarioIds IDs de los profesionales a convocar
     *
     * @return array<int, Collection<int, Cita>> Mapa usuarioId → citas en conflicto
     */
    public function agregarProfesionales(array $usuarioIds): array
    {
        $conflictos = [];
        $fechaStr = $this->fecha->toDateString();

        foreach ($usuarioIds as $usuarioId) {
            $this->profesionales()->syncWithoutDetaching([$usuarioId]);

            Slot::where('usuario_id', $usuarioId)
                ->where('centro_id', $this->centro_id)
                ->where('fecha', $fechaStr)
                ->where('hora_inicio', '>=', $this->hora_inicio)
                ->where('hora_inicio', '<', $this->hora_fin)
                ->where('estado', EstadoSlot::Disponible->value)
                ->update(['estado' => EstadoSlot::BloqueadoEvento->value]);

            $citasAfectadas = Cita::where('profesional_id', $usuarioId)
                ->where('centro_id', $this->centro_id)
                ->where('fecha', $fechaStr)
                ->where('hora_inicio', '>=', $this->hora_inicio)
                ->where('hora_inicio', '<', $this->hora_fin)
                ->where('estado', EstadoCita::Confirmada->value)
                ->get();

            if ($citasAfectadas->isNotEmpty()) {
                $conflictos[$usuarioId] = $citasAfectadas;
            }
        }

        return $conflictos;
    }

    /**
     * Comprueba si el espacio asignado al evento ya está ocupado por otro evento simultáneo.
     *
     * Solo aplica cuando el evento tiene espacio_id. Si dos eventos comparten
     * espacio y sus franjas se solapan, el sistema debe avisar al supervisor
     * pero no bloquear la creación del evento (principio de aviso sin bloqueo).
     *
     * @return bool true si existe conflicto de espacio
     */
    public function detectarConflictoEspacio(): bool
    {
        if (! $this->espacio_id) {
            return false;
        }

        return EventoAgenda::where('espacio_id', $this->espacio_id)
            ->where('fecha', $this->fecha->toDateString())
            ->where('id', '!=', $this->id)
            ->whereNull('deleted_at')
            ->where('hora_inicio', '<', $this->hora_fin)
            ->where('hora_fin', '>', $this->hora_inicio)
            ->exists();
    }
}
