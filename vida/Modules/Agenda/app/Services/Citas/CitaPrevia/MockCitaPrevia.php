<?php

namespace Modules\Agenda\Services\Citas\CitaPrevia;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\TipoCita;

/**
 * Adaptador mock de Cita Previa, activo por defecto (principio 3.6).
 *
 * Acepta el slot, la referencia y los datos de identificación de la persona, y
 * registra las notificaciones salientes en memoria y en el log sin enviarlas.
 */
class MockCitaPrevia implements AdaptadorCitaPrevia
{
    /** @var list<array{referencia_externa: string|null, accion: string, datos: array<string, mixed>}> */
    private array $enviadas = [];

    /**
     * @param array<string, mixed> $payload slot_id, referencia_externa y, opcionales,
     *        tipo_documento, numero_documento, nombre, apellidos, telefono.
     * @return CitaExternaRecibida
     *
     * @throws InvalidArgumentException Si faltan el slot o la referencia.
     */
    public function interpretar(array $payload): CitaExternaRecibida
    {
        if (empty($payload['slot_id']) || empty($payload['referencia_externa'])) {
            throw new InvalidArgumentException('La petición de cita previa necesita slot_id y referencia_externa.');
        }

        return new CitaExternaRecibida(
            slotId: (int) $payload['slot_id'],
            referenciaExterna: (string) $payload['referencia_externa'],
            // TODO: mapeo de servicios de Cita Previa. Hasta el contrato, el tipo genérico
            tipoCitaId: TipoCita::generico()->id,
            tipoDocumento: isset($payload['tipo_documento']) ? strtolower((string) $payload['tipo_documento']) : null,
            numeroDocumento: isset($payload['numero_documento']) ? (string) $payload['numero_documento'] : null,
            identificacion: array_intersect_key($payload, array_flip(['tipo_documento', 'numero_documento', 'nombre', 'apellidos', 'telefono'])),
        );
    }

    /**
     * Registra la notificación sin enviarla.
     *
     * @param Cita $cita
     * @param string $accion
     * @param array<string, mixed> $datos
     * @return void
     */
    public function notificar(Cita $cita, string $accion, array $datos = []): void
    {
        $this->enviadas[] = ['referencia_externa' => $cita->referencia_externa, 'accion' => $accion, 'datos' => $datos];

        Log::info('Cita previa (mock): notificación no enviada', ['cita_id' => $cita->id, 'accion' => $accion]);
    }

    /**
     * Notificaciones registradas (para comprobar en los tests y en desarrollo).
     *
     * @return list<array{referencia_externa: string|null, accion: string, datos: array<string, mixed>}>
     */
    public function enviadas(): array
    {
        return $this->enviadas;
    }
}
