<?php

namespace Modules\Agenda\Services\Citas;

use App\Models\CatalogoSistema;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use LogicException;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CitaAcompanante;
use Modules\Agenda\Models\Slot;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Intervencion\Models\Apunte;
use Modules\Mensajes\Enums\TipoContextoMensaje;
use Modules\Mensajes\Services\MensajeriaService;

/**
 * Lo que hace el profesional con sus citas (docs/modulo-citas.md RN-06 y §3.5 a §3.7).
 *
 * Marca incomparecencias, registra acompañantes y pide cambios al supervisor.
 * La cita se completa sola al registrar el primer apunte o registro de atención
 * vinculado: no hay botón de «completar». El sistema nunca cierra una cita por
 * su cuenta. Única vía de cambio de estado de la cita en la atención.
 */
class AtencionCitaService
{
    /** Estados en los que una cita admite apuntes sin corrección de supervisión. */
    private const ESTADOS_VINCULABLES = [EstadoCita::Confirmada, EstadoCita::Completada];

    /** Estados que solo supervisión, con motivo, puede corregir vinculando un apunte. */
    private const ESTADOS_CORREGIBLES = [EstadoCita::NoShowCiudadano, EstadoCita::Cancelada];

    /** Autorización de corrección en curso: cita => [supervisor, motivo]. */
    private array $correcciones = [];

    /**
     * @param RegistroEventosCita $eventos
     * @param AvisosCitas $avisos
     * @param MensajeriaService $mensajeria
     */
    public function __construct(
        private readonly RegistroEventosCita $eventos,
        private readonly AvisosCitas $avisos,
        private readonly MensajeriaService $mensajeria,
    ) {}

    /**
     * El ciudadano no vino. La cita no desaparece del historial ni genera avisos.
     *
     * @param Cita $cita Confirmada.
     * @param User $usuario
     * @return Cita
     *
     * @throws AuthorizationException
     * @throws LogicException
     */
    public function registrarNoShow(Cita $cita, User $usuario): Cita
    {
        Gate::forUser($usuario)->authorize('atender', $cita);

        return DB::transaction(function () use ($cita, $usuario) {
            $cita = Cita::whereKey($cita->id)->lockForUpdate()->firstOrFail();

            if ($cita->estado !== EstadoCita::Confirmada || $cita->apuntes()->exists() || $cita->registrosAtencion()->exists()) {
                throw new LogicException('Solo se marca la incomparecencia de una cita confirmada y sin atender.');
            }

            $cita->noShowCiudadano();
            $cita->update(['pendiente_cierre' => false]);

            $this->eventos->registrar(AccionCitaEvento::Incomparecencia, cita: $cita, actor: $usuario,
                estadoAntes: EstadoCita::Confirmada->value, estadoDespues: EstadoCita::NoShowCiudadano->value);

            return $cita;
        });
    }

    /**
     * Cita que se propone vincular al registrar un apunte desde la ficha
     * (docs/modulo-citas.md §3.5.3): la pedida desde la agenda si es válida o,
     * si no, la primera de la persona con el profesional hoy o pendiente de cierre.
     *
     * @param int $ciudadanoId
     * @param User $profesional
     * @param int|null $citaPedida Cita con la que se abrió la herramienta desde la agenda.
     * @return Cita|null
     */
    public function citaVinculable(int $ciudadanoId, User $profesional, ?int $citaPedida = null): ?Cita
    {
        $base = Cita::where('ciudadano_id', $ciudadanoId)
            ->where('profesional_id', $profesional->id)
            ->whereIn('estado', self::ESTADOS_VINCULABLES);

        if ($citaPedida !== null && ($pedida = (clone $base)->whereKey($citaPedida)->first()) !== null) {
            return $pedida;
        }

        return $base->where('estado', EstadoCita::Confirmada)
            ->where(fn ($q) => $q->whereDate('fecha', today())->orWhere('pendiente_cierre', true))
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->first();
    }

    /**
     * Valida que un apunte nuevo pueda vincularse a su cita. Lo llama el observer
     * de Apunte antes de crearlo.
     *
     * @param Apunte $apunte Con cita_id informado.
     * @return void
     *
     * @throws LogicException Si la cita es de otra persona o está cerrada sin corrección autorizada.
     */
    public function validarVinculo(Apunte $apunte): void
    {
        $cita = Cita::findOrFail($apunte->cita_id);
        $ciudadanoApunte = HistoriaSocial::withoutGlobalScopes()->whereKey($apunte->historia_id)->value('ciudadano_id');

        if ($cita->ciudadano_id === null || $cita->ciudadano_id !== $ciudadanoApunte) {
            throw new LogicException('La cita es de otra persona: no se puede vincular a este apunte.');
        }

        $this->exigirVinculable($cita);
    }

    /**
     * Valida que un registro de atención nuevo pueda vincularse a su cita.
     *
     * @param RegistroAtencion $registro Con cita_id informado.
     * @return void
     *
     * @throws LogicException
     */
    public function validarVinculoAtencion(RegistroAtencion $registro): void
    {
        $cita = Cita::findOrFail($registro->cita_id);

        if ($cita->ciudadano_id === null || $cita->ciudadano_id !== $registro->ciudadano_id) {
            throw new LogicException('La cita es de otra persona: no se puede vincular a este registro.');
        }

        $this->exigirVinculable($cita);
    }

    /**
     * Completa la cita al registrar un apunte vinculado. Idempotente: si ya está
     * completada, no hace nada ni escribe evento.
     *
     * @param Cita $cita
     * @param Apunte $apunte
     * @return void
     */
    public function completarPorApunte(Cita $cita, Apunte $apunte): void
    {
        $this->completar($cita, $apunte->autor_id, ['apunte_id' => $apunte->id]);
    }

    /**
     * Completa la cita al registrar un registro de atención vinculado. Idempotente.
     *
     * @param Cita $cita
     * @param RegistroAtencion $registro
     * @return void
     */
    public function completarPorAtencion(Cita $cita, RegistroAtencion $registro): void
    {
        $this->completar($cita, $registro->profesional_id, ['registro_atencion_id' => $registro->id]);
    }

    /**
     * Supervisión vincula un apunte a una cita con incomparecencia o cancelada,
     * para corregir un error de marcado. La cita queda completada.
     *
     * @param Cita $cita
     * @param User $supervisor
     * @param string $motivo Obligatorio.
     * @param callable(): Apunte $crearApunte Crea el apunte con cita_id (dentro de la corrección).
     * @return Apunte
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function vincularConCorreccion(Cita $cita, User $supervisor, string $motivo, callable $crearApunte): Apunte
    {
        Gate::forUser($supervisor)->authorize('corregirCierre', $cita);
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('Indica el motivo de la corrección.');
        }

        return DB::transaction(function () use ($cita, $supervisor, $motivo, $crearApunte) {
            $this->correcciones[$cita->id] = [$supervisor, $motivo];

            try {
                return $crearApunte();
            } finally {
                unset($this->correcciones[$cita->id]);
            }
        });
    }

    /**
     * Registra quién acompañó a la persona. Solo registro: no crea citas ni vínculos.
     *
     * @param Cita $cita
     * @param list<array{relacion: string, ciudadano_id?: int|null, nombre?: string|null}> $acompanantes
     * @param User $usuario
     * @return void
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function registrarAcompanantes(Cita $cita, array $acompanantes, User $usuario): void
    {
        Gate::forUser($usuario)->authorize('atender', $cita);

        $relaciones = array_keys(CatalogoSistema::opcionesParaSelect('cita.relacion_acompanante'));

        foreach ($acompanantes as $a) {
            if (! in_array($a['relacion'] ?? null, $relaciones, true)) {
                throw new InvalidArgumentException('Relación de acompañante no válida.');
            }

            if (empty($a['ciudadano_id']) && blank($a['nombre'] ?? null)) {
                throw new InvalidArgumentException('Indica la persona acompañante o su nombre.');
            }
        }

        DB::transaction(function () use ($cita, $acompanantes, $usuario) {
            foreach ($acompanantes as $a) {
                CitaAcompanante::create([
                    'cita_id' => $cita->id,
                    'relacion' => $a['relacion'],
                    'ciudadano_id' => $a['ciudadano_id'] ?? null,
                    'nombre' => empty($a['ciudadano_id']) ? trim($a['nombre']) : null,
                    'registrado_por_id' => $usuario->id,
                ]);
            }

            $this->eventos->registrar(AccionCitaEvento::AcompanantesRegistrados, cita: $cita, actor: $usuario,
                datos: ['relaciones' => array_column($acompanantes, 'relacion')]);
        });
    }

    /**
     * Asocia una cita externa pendiente de identificar a la persona, tras buscarla
     * o darla de alta en ventanilla. Borra los datos de identificación recibidos.
     *
     * @param Cita $cita
     * @param Ciudadano $ciudadano
     * @param User $usuario
     * @return Cita
     *
     * @throws AuthorizationException
     * @throws LogicException
     */
    public function identificarCiudadano(Cita $cita, Ciudadano $ciudadano, User $usuario): Cita
    {
        Gate::forUser($usuario)->authorize('identificar', $cita);

        return DB::transaction(function () use ($cita, $ciudadano, $usuario) {
            $cita = Cita::whereKey($cita->id)->lockForUpdate()->firstOrFail();

            if (! $cita->pendienteDeIdentificar()) {
                throw new LogicException('La cita ya tiene persona identificada.');
            }

            $cita->update(['ciudadano_id' => $ciudadano->id, 'datos_identificacion_externos' => null]);

            $this->eventos->registrar(AccionCitaEvento::CiudadanoIdentificado, cita: $cita, actor: $usuario);

            return $cita;
        });
    }

    /**
     * El profesional pide al supervisor del centro un cambio en una cita o un slot
     * de su agenda (RN-05): mensaje uno a uno con enlace, y evento en la cita.
     *
     * @param Cita|Slot $elemento
     * @param string $texto
     * @param User $usuario
     * @return void
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws LogicException Si el centro no tiene supervisor.
     */
    public function solicitarCambio(Cita|Slot $elemento, string $texto, User $usuario): void
    {
        $texto = trim($texto);

        if ($texto === '') {
            throw new InvalidArgumentException('Explica qué cambio necesitas.');
        }

        if ($elemento instanceof Cita) {
            Gate::forUser($usuario)->authorize('pedirCambio', $elemento);
        } elseif ($elemento->usuario_id !== $usuario->id || ! $usuario->can('citas.atender')) {
            throw new AuthorizationException('Solo puedes pedir cambios sobre tu propia agenda.');
        }

        $supervisor = $this->avisos->supervisorDelCentro($elemento->centro) ?? throw new LogicException('El centro no tiene supervisor al que pedir el cambio.');
        $cuando = $elemento->fecha->format('d/m/Y').' '.substr((string) $elemento->hora_inicio, 0, 5);

        DB::transaction(function () use ($elemento, $texto, $usuario, $supervisor, $cuando) {
            $esCita = $elemento instanceof Cita;

            $this->mensajeria->crearHilo(
                $usuario,
                $supervisor,
                ($esCita ? 'Cambio en una cita del ' : 'Cambio en un hueco del ').$cuando,
                $texto,
                $esCita && $elemento->ciudadano_id ? [$elemento->ciudadano_id] : [],
                ['tipo' => ($esCita ? TipoContextoMensaje::Cita : TipoContextoMensaje::Slot)->value, 'id' => $elemento->id],
            );

            if ($esCita) {
                $this->eventos->registrar(AccionCitaEvento::CambioSolicitado, cita: $elemento, actor: $usuario, motivo: $texto);
            }
        });
    }

    /**
     * @param Cita $cita
     * @param int|null $actorId
     * @param array<string, mixed> $datos
     * @return void
     */
    private function completar(Cita $cita, ?int $actorId, array $datos): void
    {
        DB::transaction(function () use ($cita, $actorId, $datos) {
            $cita = Cita::whereKey($cita->id)->lockForUpdate()->firstOrFail();

            if ($cita->estado === EstadoCita::Completada) {
                return;
            }

            $antes = $cita->estado;
            $correccion = $this->correcciones[$cita->id] ?? null;

            $cita->completar();
            $cita->update(['pendiente_cierre' => false]);

            $this->eventos->registrar(AccionCitaEvento::CitaCompletada, cita: $cita,
                actor: $correccion[0] ?? ($actorId ? User::find($actorId) : null),
                estadoAntes: $antes->value, estadoDespues: EstadoCita::Completada->value,
                motivo: $correccion[1] ?? null, datos: $datos);
        });
    }

    /**
     * @param Cita $cita
     * @return void
     *
     * @throws LogicException Si la cita está cerrada y no hay corrección autorizada en curso.
     */
    private function exigirVinculable(Cita $cita): void
    {
        if (in_array($cita->estado, self::ESTADOS_VINCULABLES, true)) {
            return;
        }

        if (in_array($cita->estado, self::ESTADOS_CORREGIBLES, true) && isset($this->correcciones[$cita->id])) {
            return;
        }

        throw new LogicException("No se puede vincular un apunte a una cita en estado «{$cita->estado->label()}»: solo supervisión, con motivo, puede corregirlo.");
    }
}
