<?php

namespace Modules\Centro\Services;

use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\ListaEspera;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\Prescripcion;

/**
 * Gestiona el ciclo de vida de las prescripciones de plazas.
 *
 * Crea prescripciones asignando plaza disponible o entrando en lista de espera.
 * Gestiona la liberación de plazas y las cancelaciones.
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

    public function __construct()
    {
        // Por defecto no hay resolución de TSR hasta que se configure.
        $this->tsrResolver = fn (int $ciudadanoId): ?int => null;
    }

    /**
     * Inyecta la función de resolución del TSR activo.
     * Uso previsto: tests y adaptadores hacia el módulo Ciudadanía.
     *
     * @param callable(int): int|null $resolver
     */
    public function setTsrResolver(callable $resolver): void
    {
        $this->tsrResolver = $resolver;
    }

    /**
     * Crea una prescripción hacia una colección de plazas.
     *
     * Si hay plaza libre y el modo lo permite, la asigna directamente.
     * Si no hay disponibilidad y el modo es prescripcion_lista_espera,
     * crea el registro de lista de espera.
     *
     * @param array<string, mixed> $datos Atributos de la Prescripcion (sin estado, plaza_id, fecha_asignacion)
     *
     * @throws \InvalidArgumentException Si el tipo_destino no es coleccion_plazas.
     */
    public function crear(array $datos): Prescripcion
    {
        if (($datos['tipo_destino'] ?? '') !== 'coleccion_plazas') {
            throw new \InvalidArgumentException(
                'PrescripcionService::crear() solo gestiona prescripciones de tipo coleccion_plazas.'
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
     * Marca una plaza como libre y actualiza el profesional de alerta en la lista de espera.
     *
     * No cambia el estado de las prescripciones en espera: la asignación es siempre
     * explícita y requiere confirmación del profesional.
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
        }
    }

    /**
     * Cancela una prescripción y libera la plaza si tenía una asignada.
     *
     * @param string|null $motivo Motivo opcional de la cancelación.
     */
    public function cancelar(Prescripcion $prescripcion, ?string $motivo = null): void
    {
        if ($prescripcion->plaza_id) {
            Plaza::find($prescripcion->plaza_id)?->update(['estado' => 'libre']);
        }

        $prescripcion->update([
            'estado' => 'cancelada',
            'plaza_id' => null,
            'motivo_cancelacion' => $motivo,
        ]);
    }
}
