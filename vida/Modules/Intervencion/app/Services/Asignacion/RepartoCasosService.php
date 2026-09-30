<?php

namespace Modules\Intervencion\Services\Asignacion;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Intervencion\Enums\EstadoRepartoCasos;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\RepartoCasos;
use Modules\Intervencion\Models\RepartoCasosLinea;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;
use Random\Randomizer;

/**
 * Reparto de los casos de un profesional que deja el centro (RN-08).
 *
 * El sistema propone y el supervisor del centro revisa, cambia destinos y
 * confirma: hasta la confirmación no cambia ninguna asignación. La propuesta
 * agrupa los casos por unidad de convivencia (cada unidad va entera al mismo
 * destino), los separa en con actividad y dormidos, y reparte cada bloque por
 * separado entre el resto del reparto del centro en proporción a la jornada y
 * en orden aleatorio: así a nadie le tocan todos los casos vivos.
 *
 * Las asignaciones del reparto no cuentan como entrada en el sorteo.
 *
 * @see docs/modulo-asignacion.md §5
 */
class RepartoCasosService
{
    /**
     * @param ActividadCasosService $actividad
     * @param PoolReferenciaService $pool
     * @param CentroDeUsuario $centros
     * @param AlertaService $alertas
     * @param Randomizer $azar Inyectado para fijar la semilla en los tests.
     */
    public function __construct(
        private readonly ActividadCasosService $actividad,
        private readonly PoolReferenciaService $pool,
        private readonly CentroDeUsuario $centros,
        private readonly AlertaService $alertas,
        private readonly Randomizer $azar,
    ) {}

    /**
     * Propone el reparto de los casos vigentes de un profesional en el centro.
     * No toca ninguna asignación.
     *
     * @param Centro $centro
     * @param User $origen Profesional cuyos casos se reparten.
     * @param string $motivo Obligatorio.
     * @param User $supervisor Supervisor del centro que inicia el reparto.
     * @return RepartoCasos
     *
     * @throws AuthorizationException Si el usuario no supervisa el centro.
     * @throws InvalidArgumentException Si falta el motivo.
     * @throws LogicException Si ya hay un reparto propuesto, no hay casos o no hay a quién repartirlos.
     */
    public function proponer(Centro $centro, User $origen, string $motivo, User $supervisor): RepartoCasos
    {
        $this->exigirSupervision($supervisor, $centro);
        $motivo = $this->exigirMotivo($motivo);

        $abierto = RepartoCasos::where('centro_id', $centro->id)
            ->where('profesional_origen_id', $origen->id)
            ->where('estado', EstadoRepartoCasos::Propuesto)
            ->exists();

        if ($abierto) {
            throw new LogicException('Ya hay un reparto propuesto de los casos de este profesional: confírmalo o descártalo antes.');
        }

        $historiaIds = $this->actividad->historiasDe($centro, $origen);

        if ($historiaIds === []) {
            throw new LogicException('El profesional no tiene casos vigentes en el centro.');
        }

        $destinos = $this->destinos($centro, $origen);

        if ($destinos->isEmpty()) {
            throw new LogicException('No hay otros profesionales en el reparto del centro a los que asignar los casos.');
        }

        $grupos = $this->agrupar($centro, $historiaIds);

        $lineas = [];
        foreach ([true, false] as $conActividad) {
            $bloque = $grupos->where('con_actividad', $conActividad)->values();
            foreach ($this->repartirBloque($bloque, $destinos) as $destinoId => $suyos) {
                foreach ($suyos as $grupo) {
                    foreach ($grupo['historias'] as $historiaId) {
                        $lineas[] = [
                            'historia_id' => $historiaId,
                            'unidad_convivencia_id' => $grupo['unidad_convivencia_id'],
                            'con_actividad' => $conActividad,
                            'profesional_destino_id' => $destinoId,
                        ];
                    }
                }
            }
        }

        return DB::transaction(function () use ($centro, $origen, $motivo, $supervisor, $lineas) {
            $reparto = RepartoCasos::create([
                'centro_id' => $centro->id,
                'profesional_origen_id' => $origen->id,
                'iniciado_por_id' => $supervisor->id,
                'estado' => EstadoRepartoCasos::Propuesto,
                'motivo' => $motivo,
            ]);

            $reparto->lineas()->createMany($lineas);

            return $reparto;
        });
    }

    /**
     * Cambia el destino de un caso de un reparto propuesto. Solo mueve esa
     * línea: si el supervisor separa a una unidad de convivencia, es su decisión.
     *
     * @param RepartoCasosLinea $linea
     * @param User $destino Debe estar en el reparto del centro y no ser el profesional de origen.
     * @param User $supervisor
     * @return RepartoCasosLinea
     *
     * @throws AuthorizationException Si el usuario no supervisa el centro.
     * @throws LogicException Si el reparto ya no está propuesto o el destino no es válido.
     */
    public function modificarLinea(RepartoCasosLinea $linea, User $destino, User $supervisor): RepartoCasosLinea
    {
        $reparto = $linea->reparto;
        $this->exigirSupervision($supervisor, $reparto->centro);
        $this->exigirPropuesto($reparto);

        if (! $this->destinos($reparto->centro, $reparto->profesionalOrigen)->has($destino->id)) {
            throw new LogicException('El destino no está en el reparto de referencias del centro.');
        }

        $linea->update([
            'profesional_destino_id' => $destino->id,
            'modificada_por_supervisor' => true,
        ]);

        return $linea;
    }

    /**
     * Descarta un reparto propuesto. Ninguna asignación cambia.
     *
     * @param RepartoCasos $reparto
     * @param User $supervisor
     * @return RepartoCasos
     *
     * @throws AuthorizationException Si el usuario no supervisa el centro.
     * @throws LogicException Si el reparto ya no está propuesto.
     */
    public function descartar(RepartoCasos $reparto, User $supervisor): RepartoCasos
    {
        $this->exigirSupervision($supervisor, $reparto->centro);
        $this->exigirPropuesto($reparto);

        $reparto->update(['estado' => EstadoRepartoCasos::Descartado]);

        return $reparto;
    }

    /**
     * Confirma el reparto: cierra la asignación vigente de cada caso y crea la
     * nueva con origen reparto, que no cuenta en el sorteo. Avisa a cada
     * profesional de destino del número de casos que recibe.
     *
     * Todo o nada: si algún caso ha cambiado de referencia desde la propuesta,
     * no se aplica ninguno y hay que proponer de nuevo.
     *
     * @param RepartoCasos $reparto
     * @param User $supervisor
     * @return RepartoCasos
     *
     * @throws AuthorizationException Si el usuario no supervisa el centro.
     * @throws LogicException Si el reparto ya no está propuesto o la propuesta ha quedado desfasada.
     */
    public function confirmar(RepartoCasos $reparto, User $supervisor): RepartoCasos
    {
        $this->exigirSupervision($supervisor, $reparto->centro);

        return DB::transaction(function () use ($reparto, $supervisor) {
            // Bloqueo para que dos confirmaciones simultáneas no dupliquen asignaciones
            $reparto = RepartoCasos::whereKey($reparto->id)->lockForUpdate()->firstOrFail();
            $this->exigirPropuesto($reparto);

            $lineas = $reparto->lineas()->get();

            $vigentes = AsignacionProfesional::vigente()
                ->whereIn('historia_id', $lineas->pluck('historia_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('historia_id');

            foreach ($lineas as $linea) {
                if ($vigentes->get($linea->historia_id)?->profesional_id !== $reparto->profesional_origen_id) {
                    throw new LogicException('Algún caso ha cambiado de referencia desde la propuesta: descártala y propón de nuevo el reparto.');
                }
            }

            foreach ($lineas as $linea) {
                // Historial aditivo: se cierra la vigente sin tocar su profesional
                $vigentes[$linea->historia_id]->update(['fecha_fin' => today()]);

                AsignacionProfesional::create([
                    'historia_id' => $linea->historia_id,
                    'profesional_id' => $linea->profesional_destino_id,
                    'centro_id' => $reparto->centro_id,
                    'origen' => OrigenAsignacionReferencia::Reparto,
                    'cuenta_en_reparto' => false,
                    'asignado_por_id' => $supervisor->id,
                    'reparto_id' => $reparto->id,
                    'fecha_inicio' => today(),
                ]);
            }

            $reparto->update([
                'estado' => EstadoRepartoCasos::Confirmado,
                'confirmado_en' => now(),
            ]);

            $this->avisarDestinos($reparto, $lineas);

            return $reparto;
        });
    }

    /**
     * Profesionales a los que se puede mover un caso del reparto: los del
     * reparto de referencias del centro, salvo el de origen.
     *
     * @param RepartoCasos $reparto
     * @return Collection<int, User>
     */
    public function destinosPosibles(RepartoCasos $reparto): Collection
    {
        $ids = $this->destinos($reparto->centro, $reparto->profesionalOrigen)->keys();

        return User::with('profesional')->whereIn('id', $ids)->get()->sortBy('nombre_completo')->values()->toBase();
    }

    /**
     * Profesionales del reparto del centro, salvo el de origen, con su peso.
     *
     * @param Centro $centro
     * @param User $origen
     * @return Collection<int, float> Peso por id de usuario.
     */
    private function destinos(Centro $centro, User $origen): Collection
    {
        return $this->pool->elegibles($centro, today())
            ->reject(fn (array $e) => $e['usuario']->id === $origen->id)
            ->mapWithKeys(fn (array $e) => [$e['usuario']->id => $e['peso']]);
    }

    /**
     * Agrupa los casos por unidad de convivencia vigente. Un grupo tiene
     * actividad si la tiene alguno de sus casos: la unidad entera va al bloque
     * de casos vivos.
     *
     * @param Centro $centro
     * @param list<int> $historiaIds
     * @return Collection<int, array{historias: list<int>, unidad_convivencia_id: int|null, con_actividad: bool}>
     */
    private function agrupar(Centro $centro, array $historiaIds): Collection
    {
        $ciudadanoDe = HistoriaSocial::withoutGlobalScopes()
            ->whereIn('id', $historiaIds)
            ->pluck('ciudadano_id', 'id');

        $unidadDe = UnidadConvivenciaMiembro::query()
            ->whereIn('ciudadano_id', $ciudadanoDe->values())
            ->whereNull('fecha_fin')
            ->whereHas('unidadConvivencia', fn ($q) => $q->whereNull('fecha_disolucion'))
            ->orderBy('id')
            ->get(['ciudadano_id', 'unidad_convivencia_id'])
            ->unique('ciudadano_id')
            ->pluck('unidad_convivencia_id', 'ciudadano_id');

        $activas = $this->actividad->historiasConActividad($centro, $historiaIds);

        return collect($historiaIds)
            ->groupBy(fn (int $id) => ($u = $unidadDe->get($ciudadanoDe[$id])) !== null ? "uc{$u}" : "h{$id}")
            ->map(fn (Collection $ids) => [
                'historias' => $ids->values()->all(),
                'unidad_convivencia_id' => $unidadDe->get($ciudadanoDe[$ids->first()]),
                'con_actividad' => $ids->contains(fn (int $id) => isset($activas[$id])),
            ])
            ->values();
    }

    /**
     * Reparte los grupos de un bloque en proporción al peso. Recorre los grupos
     * en orden aleatorio y da cada uno al destino que más se ha quedado por
     * debajo de su parte contando ese grupo; los empates los decide el orden,
     * también aleatorio, de los destinos.
     *
     * @param Collection<int, array{historias: list<int>, unidad_convivencia_id: int|null, con_actividad: bool}> $grupos
     * @param Collection<int, float> $destinos Peso por id de usuario.
     * @return array<int, list<array{historias: list<int>, unidad_convivencia_id: int|null, con_actividad: bool}>>
     */
    private function repartirBloque(Collection $grupos, Collection $destinos): array
    {
        if ($grupos->isEmpty()) {
            return [];
        }

        $pesoTotal = $destinos->sum();
        $orden = $this->azar->shuffleArray($destinos->keys()->all());
        $recibidos = array_fill_keys($orden, 0);
        $resultado = [];
        $repartidos = 0;

        foreach ($this->azar->shuffleArray($grupos->all()) as $grupo) {
            $n = count($grupo['historias']);
            $elegido = null;
            $mayorDeficit = null;

            foreach ($orden as $id) {
                $deficit = $destinos[$id] / $pesoTotal * ($repartidos + $n) - $recibidos[$id];

                if ($mayorDeficit === null || $deficit > $mayorDeficit) {
                    [$elegido, $mayorDeficit] = [$id, $deficit];
                }
            }

            $resultado[$elegido][] = $grupo;
            $recibidos[$elegido] += $n;
            $repartidos += $n;
        }

        return $resultado;
    }

    /**
     * Un aviso por profesional de destino con los casos que recibe.
     *
     * @param RepartoCasos $reparto
     * @param Collection<int, RepartoCasosLinea> $lineas
     * @return void
     */
    private function avisarDestinos(RepartoCasos $reparto, Collection $lineas): void
    {
        foreach ($lineas->countBy('profesional_destino_id') as $destinoId => $casos) {
            $texto = $casos === 1 ? '1 caso' : "{$casos} casos";

            $this->alertas->crear([
                'tipo' => TipoAlerta::Aviso,
                'origen_type' => RepartoCasos::class,
                'origen_id' => $reparto->id,
                'titulo' => "Has recibido {$texto} por reparto",
                'cuerpo' => "Supervisión ha repartido los casos de un profesional del centro. Desde hoy eres el profesional de referencia de {$texto}: los tienes en «Mis casos».",
                'destinatario_type' => DestinatarioType::Usuario,
                'destinatario_usuario_id' => $destinoId,
            ]);
        }
    }

    /**
     * @param User $usuario
     * @param Centro $centro
     * @return void
     *
     * @throws AuthorizationException Si el usuario no supervisa el centro.
     */
    private function exigirSupervision(User $usuario, Centro $centro): void
    {
        if (! $this->centros->supervisa($usuario, $centro)) {
            throw new AuthorizationException('Solo la supervisión del centro puede repartir sus casos.');
        }
    }

    /**
     * @param string $motivo
     * @return string Motivo sin espacios sobrantes.
     *
     * @throws InvalidArgumentException Si está vacío.
     */
    private function exigirMotivo(string $motivo): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('El reparto de casos exige un motivo.');
        }

        return $motivo;
    }

    /**
     * @param RepartoCasos $reparto
     * @return void
     *
     * @throws LogicException Si el reparto ya se confirmó o descartó.
     */
    private function exigirPropuesto(RepartoCasos $reparto): void
    {
        if ($reparto->estado !== EstadoRepartoCasos::Propuesto) {
            throw new LogicException('El reparto ya no está pendiente de confirmar.');
        }
    }
}
