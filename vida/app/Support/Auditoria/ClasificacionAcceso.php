<?php

namespace App\Support\Auditoria;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use App\Models\HistoriaSocial;
use Illuminate\Support\Facades\Auth;

/**
 * Cómo se presenta un acceso al expediente en «Últimos accesos» y en su detalle (U3, punto 7):
 * propio, normal, sospechoso (lectura desde otra UO) o anómalo (modificación desde otra UO).
 */
final class ClasificacionAcceso
{
    /** Acciones que modifican el expediente. */
    public const ACCIONES_CAMBIO = ['crear', 'editar', 'eliminar', 'borrar'];

    /**
     * @param bool $esPropio
     * @param bool $esOtraUo
     * @param bool $esCambio
     */
    private function __construct(
        public readonly bool $esPropio,
        public readonly bool $esOtraUo,
        public readonly bool $esCambio,
    ) {}

    /**
     * Clasifica un acceso respecto de la Historia Social consultada.
     *
     * @param Audit $acceso
     * @param HistoriaSocial $historia
     *
     * @return self
     */
    public static function de(Audit $acceso, HistoriaSocial $historia): self
    {
        $uo = $acceso->contexto['unidad_organizativa_id'] ?? $acceso->user?->profesional?->unidad_organizativa_id;
        $accion = $acceso->accion instanceof AccionAuditEnum ? $acceso->accion->value : (string) $acceso->accion;

        return new self(
            esPropio: $acceso->user_id === Auth::id(),
            esOtraUo: $uo !== null && $uo !== $historia->unidad_organizativa_id,
            esCambio: in_array($accion, self::ACCIONES_CAMBIO, true),
        );
    }

    /**
     * Modificación desde otra UO: anomalía grave que hay que revisar.
     *
     * @return bool
     */
    public function esAnomalo(): bool
    {
        return $this->esOtraUo && $this->esCambio;
    }

    /**
     * Lectura desde otra UO: sospechosa.
     *
     * @return bool
     */
    public function esSospechoso(): bool
    {
        return $this->esOtraUo && ! $this->esCambio;
    }

    /**
     * Tipo para el atributo `data-acceso` del widget.
     *
     * @return string
     */
    public function tipo(): string
    {
        return match (true) {
            $this->esAnomalo() => 'anomalo',
            $this->esSospechoso() => 'sospechoso',
            $this->esPropio => 'propio',
            default => 'normal',
        };
    }

    /**
     * Texto de la segunda línea: el resumen guardado al escribir (U3.1) o la etiqueta de la acción.
     *
     * @param Audit $acceso
     *
     * @return string
     */
    public static function texto(Audit $acceso): string
    {
        $resumen = $acceso->contexto['resumen'] ?? null;

        if (is_string($resumen) && $resumen !== '') {
            return $resumen;
        }

        return $acceso->accion instanceof AccionAuditEnum ? $acceso->accion->etiqueta() : '—';
    }
}
