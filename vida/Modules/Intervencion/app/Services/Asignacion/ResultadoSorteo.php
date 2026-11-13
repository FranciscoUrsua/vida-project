<?php

namespace Modules\Intervencion\Services\Asignacion;

use App\Models\User;

/**
 * Resultado de un sorteo de profesional de referencia, con todo lo necesario
 * para auditarlo: cada profesional del reparto con su peso, lo que le
 * correspondía, lo que había recibido y si era candidato, y el elegido.
 */
final class ResultadoSorteo
{
    /**
     * @param User|null $elegido Profesional elegido; null si no había elegibles.
     * @param list<array{usuario_id: int, peso: float, esperado: float, recibido: int, candidato: bool}> $profesionales
     */
    public function __construct(
        public readonly ?User $elegido,
        public readonly array $profesionales,
    ) {}

    /**
     * Resultado sin elegibles: no se asigna a nadie.
     *
     * @return self
     */
    public static function vacio(): self
    {
        return new self(null, []);
    }

    /**
     * Si hubo a quién asignar.
     *
     * @return bool
     */
    public function hayElegido(): bool
    {
        return $this->elegido !== null;
    }

    /**
     * Datos que se guardan en el campo `sorteo` de la asignación.
     *
     * @return array{profesionales: list<array{usuario_id: int, peso: float, esperado: float, recibido: int, candidato: bool}>, elegido: int|null}
     */
    public function paraAuditoria(): array
    {
        return ['profesionales' => $this->profesionales, 'elegido' => $this->elegido?->id];
    }
}
