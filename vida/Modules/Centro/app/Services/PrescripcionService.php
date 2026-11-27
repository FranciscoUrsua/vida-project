<?php

namespace Modules\Centro\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\ListaEspera;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Services\Actividades\InscripcionActividadService;
use Modules\Centro\Services\Actividades\InscripcionCentroRequerida;

/**
 * Gestiona el ciclo de vida de las prescripciones de plazas y de sesiones de actividad.
 *
 * Crea prescripciones asignando plaza disponible o entrando en lista de espera.
 * Gestiona la liberación de plazas y las cancelaciones. Una prescripción a una sesión
 * nace activa con su inscripción por vía de prescripción, o no nace si no queda plaza de
 * prescripción (fase 6, F6-3 y F6-7): las actividades no tienen lista de espera.
 *
 * La asignación nunca es automática cuando se libera una plaza: el sistema
 * actualiza el profesional de alerta y espera confirmación profesional.
 *
 * @see docs/modulo-centros.md §2.8, §3.12, §3.13
 */
class PrescripcionService
{
    /**
     * Función para resolver el TSR activo de un ciudadano.
     * Se usa al liberar plazas para actualizar profesional_alerta_id.
     *
     * @var callable(int): int|null
     */
    private $tsrResolver;

    /**
     * Aviso al profesional de una entrada de lista de espera cuando se libera una plaza.
     * Lo configura IntervencionServiceProvider con AvisoPlazasService (fase 3, D3).
     *
     * @var callable(ListaEspera): void
     */
    private $avisoPlazaLibre;

    /**
     * Qué hacer cuando una prescripción queda con plaza asignada (fase 3 bis: crear la
     * prestación de la persona). Lo configura PrestacionesServiceProvider.
     *
     * @var callable(Prescripcion): void
     */
    private $alAsignarPlaza;

    /**
     * Arranca sin resolución de TSR ni avisos hasta que se configuren.
     */
    public function __construct()
    {
        // Por defecto no hay resolución de TSR hasta que se configure.
        $this->tsrResolver = fn (int $ciudadanoId): ?int => null;
        $this->avisoPlazaLibre = fn (ListaEspera $entrada): null => null;
        $this->alAsignarPlaza = fn (Prescripcion $prescripcion): null => null;
    }

    /**
     * Inyecta lo que se hace al asignar plaza a una prescripción.
     *
     * @param callable(Prescripcion): void $accion
     *
     * @return void
     */
    public function setAlAsignarPlaza(callable $accion): void
    {
        $this->alAsignarPlaza = $accion;
    }

    /**
     * Avisa de que una prescripción ha quedado con plaza asignada (desde aquí o desde la pantalla).
     *
     * @param Prescripcion $prescripcion
     *
     * @return void
     */
    public function plazaAsignada(Prescripcion $prescripcion): void
    {
        ($this->alAsignarPlaza)($prescripcion);
    }

    /**
     * Inyecta el aviso de plaza libre a la lista de espera.
     *
     * @param callable(ListaEspera): void $aviso
     *
     * @return void
     */
    public function setAvisoPlazaLibre(callable $aviso): void
    {
        $this->avisoPlazaLibre = $aviso;
    }

    /**
     * Inyecta la función de resolución del TSR activo.
     * La configura IntervencionServiceProvider con el profesional de referencia vigente
     * (AvisoPlazasService, fase 3, D1); los tests pueden sustituirla.
     *
     * @param callable(int): int|null $resolver Devuelve el id de la ficha de profesional.
     *
     * @return void
     */
    public function setTsrResolver(callable $resolver): void
    {
        $this->tsrResolver = $resolver;
    }

    /**
     * Crea una prescripción hacia una colección de plazas o una sesión de actividad.
     *
     * Colección: si hay plaza libre y el modo lo permite, la asigna directamente; si no hay
     * disponibilidad, crea el registro de lista de espera.
     * Sesión: ver crearEnSesion().
     *
     * @param array<string, mixed> $datos Atributos de la Prescripcion (sin estado, plaza_id, fecha_asignacion)
     * @param User|null $actor Quien prescribe; obligatorio para las sesiones.
     * @param bool $inscribirEnCentro Consentimiento para inscribir a la persona en el centro de la actividad (F6-4).
     *
     * @return Prescripcion
     *
     * @throws \InvalidArgumentException Si el tipo_destino no es coleccion_plazas ni sesion_actividad.
     * @throws InscripcionCentroRequerida|DomainException En una sesión sin plaza o que no la admite.
     */
    public function crear(array $datos, ?User $actor = null, bool $inscribirEnCentro = false): Prescripcion
    {
        if (($datos['tipo_destino'] ?? '') === 'sesion_actividad') {
            return $this->crearEnSesion($datos, $actor ?? throw new \InvalidArgumentException('Prescribir una sesión exige el usuario que prescribe.'), $inscribirEnCentro);
        }

        if (($datos['tipo_destino'] ?? '') !== 'coleccion_plazas') {
            throw new \InvalidArgumentException(
                'PrescripcionService::crear() solo gestiona prescripciones de tipo coleccion_plazas o sesion_actividad.'
            );
        }

        $coleccion = ColeccionPlazas::findOrFail($datos['destino_id']);
        $plaza = $coleccion->plazas()->where('estado', 'libre')->first();

        if ($plaza) {
            $datos['estado'] = 'asignada';
            $datos['plaza_id'] = $plaza->id;
            $datos['fecha_asignacion'] = today()->toDateString();
            $plaza->update(['estado' => 'reservada']);
        } else {
            $datos['estado'] = 'en_lista_espera';
        }

        $prescripcion = Prescripcion::create($datos);

        if ($prescripcion->estado === 'asignada') {
            $this->plazaAsignada($prescripcion);
        }

        if ($prescripcion->estado === 'en_lista_espera') {
            $posicion = ListaEspera::where('coleccion_plazas_id', $coleccion->id)
                ->where('estado', 'activa')
                ->max('posicion') ?? 0;

            ListaEspera::create([
                'prescripcion_id' => $prescripcion->id,
                'coleccion_plazas_id' => $coleccion->id,
                'profesional_alerta_id' => $datos['profesional_id'],
                'estado' => 'activa',
                'posicion' => $posicion + 1,
                'fecha_entrada' => now(),
            ]);
        }

        return $prescripcion;
    }

    /**
     * Prescripción a una sesión de actividad: nace activa y con su inscripción por vía de
     * prescripción, en la misma transacción. Si no queda plaza de prescripción, no se crea
     * nada (F6-3).
     *
     * @param array<string, mixed> $datos
     * @param User $actor
     * @param bool $inscribirEnCentro
     *
     * @return Prescripcion
     *
     * @throws InscripcionCentroRequerida|DomainException
     */
    private function crearEnSesion(array $datos, User $actor, bool $inscribirEnCentro): Prescripcion
    {
        return DB::transaction(function () use ($datos, $actor, $inscribirEnCentro): Prescripcion {
            $prescripcion = Prescripcion::create(array_merge($datos, [
                'estado' => 'activa',
                'plaza_id' => null,
                'fecha_asignacion' => today()->toDateString(),
            ]));

            app(InscripcionActividadService::class)->inscribirPorPrescripcion($prescripcion, $actor, $inscribirEnCentro);

            return $prescripcion;
        });
    }

    /**
     * Marca una plaza como libre y actualiza el profesional de alerta en la lista de espera.
     *
     * No cambia el estado de las prescripciones en espera: la asignación es siempre
     * explícita y requiere confirmación del profesional.
     *
     * @param Plaza $plaza Plaza que queda disponible.
     */
    public function liberarPlaza(Plaza $plaza): void
    {
        $plaza->update(['estado' => 'libre']);

        $espacio = $plaza->espacio;
        $coleccion = $espacio->coleccionPlazas;

        // Actualizar el profesional de alerta para cada entrada activa en la lista de espera.
        $entradas = ListaEspera::where('coleccion_plazas_id', $coleccion->id)
            ->where('estado', 'activa')
            ->with('prescripcion')
            ->get();

        foreach ($entradas as $entrada) {
            $tsrId = ($this->tsrResolver)($entrada->prescripcion->ciudadano_id);
            if ($tsrId !== null) {
                $entrada->update(['profesional_alerta_id' => $tsrId]);
            }

            ($this->avisoPlazaLibre)($entrada);
        }
    }

    /**
     * Cancela una prescripción y libera la plaza si tenía una asignada. Cancela su entrada
     * activa en la lista de espera y, en una sesión de actividad, da de baja su inscripción
     * vigente (la plaza), con el mismo motivo. Todo en una transacción.
     *
     * @param Prescripcion $prescripcion Prescripción que se cancela.
     * @param string|null $motivo Motivo opcional de la cancelación.
     *
     * @return void
     */
    public function cancelar(Prescripcion $prescripcion, ?string $motivo = null): void
    {
        DB::transaction(function () use ($prescripcion, $motivo): void {
            if ($prescripcion->plaza_id) {
                Plaza::find($prescripcion->plaza_id)?->update(['estado' => 'libre']);
            }

            // Solo la entrada en curso: una ya asignada o cancelada es historia (principio 4.2)
            $prescripcion->listaEspera()->where('estado', 'activa')->first()?->update(['estado' => 'cancelada']);

            $prescripcion->update([
                'estado' => 'cancelada',
                'plaza_id' => null,
                'motivo_cancelacion' => $motivo,
            ]);

            // Si la cancelación viene de la baja de la inscripción, esta ya no está vigente
            $inscripcion = InscripcionSesion::where('prescripcion_id', $prescripcion->id)->vigentes()->first();

            if ($inscripcion !== null) {
                app(InscripcionActividadService::class)->registrarBaja($inscripcion, $motivo ?? 'Prescripción cancelada', auth()->user());
            }
        });
    }
}
