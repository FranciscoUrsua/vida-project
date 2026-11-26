<?php

namespace Modules\Intervencion\Services\Actuaciones;

use App\Models\HistoriaSocial;
use App\Models\User;
use DomainException;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\ActuacionesEncargo;

/**
 * Dónde y para qué se registra una actuación (anotación, ficha de valoración, pase de escala):
 * la Historia Social, quien la registra, el plan al que se vincula, la cita que atiende y, si
 * se registra desde un encargo profesional, el encargo (bloque B de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Desde la Historia o el plan, el plan y la cita los resuelve la pantalla, como hasta ahora.
 * Desde un encargo, el contexto los fija él: sin plan (EA-5, el encargo no pertenece al plan
 * aunque nazca de él) y solo con una cita vinculable del propio profesional con la persona
 * (EA-10). El centro de la operación (RN-15) es el de la cita; si no hay, el del encargo
 * (D1.6); si no, el centro activo del usuario.
 */
final class ContextoActuacion
{
    /**
     * @param HistoriaSocial $historia Historia Social sobre la que se actúa.
     * @param User $autor Quien registra la actuación.
     * @param int|null $planId Plan al que se vincula el apunte; null desde un encargo.
     * @param Cita|null $cita Cita que atiende la actuación, ya validada.
     * @param Derivacion|null $encargo Encargo profesional desde el que se registra.
     */
    private function __construct(
        public readonly HistoriaSocial $historia,
        public readonly User $autor,
        public readonly ?int $planId,
        public readonly ?Cita $cita,
        public readonly ?Derivacion $encargo,
    ) {}

    /**
     * Actuación registrada desde la Historia Social o el plan.
     *
     * @param HistoriaSocial $historia Historia ya autorizada por la pantalla.
     * @param User $autor
     * @param int|null $planId Plan en curso al que se vincula el apunte, si lo hay.
     * @param int|null $citaId Cita vinculable que ha resuelto la pantalla, si la hay.
     *
     * @return self
     */
    public static function desdeHistoria(HistoriaSocial $historia, User $autor, ?int $planId = null, ?int $citaId = null): self
    {
        return new self($historia, $autor, $planId, $citaId !== null ? Cita::find($citaId) : null, null);
    }

    /**
     * Actuación registrada desde un encargo profesional.
     *
     * Exige que el autor pueda actuar en el encargo (EA-4) y que la cita, si se indica, sea una
     * cita vinculable suya con la persona del encargo: ni cancelada, ni con incomparecencia, ni
     * de otro profesional (EA-10).
     *
     * @param Derivacion $encargo
     * @param User $autor
     * @param int|null $citaId Cita que propone la pantalla, si la hay.
     *
     * @return self
     *
     * @throws DomainException Si el autor no puede actuar en el encargo o la cita no es vinculable.
     */
    public static function desdeEncargo(Derivacion $encargo, User $autor, ?int $citaId = null): self
    {
        app(ActuacionesEncargo::class)->exigirActuacion($autor, $encargo);

        // Sin los scopes de ámbito: el profesional de encargo no tiene ámbito de UO sobre la historia
        $historia = HistoriaSocial::withoutGlobalScopes()->findOrFail($encargo->historia_id);

        $cita = null;
        if ($citaId !== null) {
            // citaVinculable() cae en la cita del día si la pedida no vale: aquí tiene que ser la pedida
            $cita = app(AtencionCitaService::class)->citaVinculable($historia->ciudadano_id, $autor, $citaId);
            if ($cita?->id !== $citaId) {
                throw new DomainException('Esa cita no se puede vincular: tiene que ser una cita tuya con esta persona, ni cancelada ni con incomparecencia.');
            }
        }

        return new self($historia, $autor, null, $cita, $encargo);
    }

    /**
     * Centro de la operación para RN-15: el de la cita, el del encargo o el activo del usuario.
     *
     * @return Centro|null
     */
    public function centro(): ?Centro
    {
        return $this->cita?->centro
            ?? $this->encargo?->centroDestino
            ?? app(CentroDeUsuario::class)->centroActivo($this->autor);
    }

    /**
     * Exige que el autor pueda actuar en el centro de la operación (R1, RN-15).
     *
     * @return void
     *
     * @throws DomainException Con el mensaje de RN-15.
     */
    public function exigirActuacion(): void
    {
        app(CentroDeUsuario::class)->exigirActuacion($this->autor, $this->centro());
    }

    /**
     * Columnas comunes del apunte que deja la actuación en la Historia Social.
     *
     * @return array{historia_id: int, plan_id: int|null, autor_id: int, fecha: string, cita_id: int|null, derivacion_id: int|null}
     */
    public function datosApunte(): array
    {
        return [
            'historia_id' => $this->historia->id,
            'plan_id' => $this->planId,
            'autor_id' => $this->autor->id,
            'fecha' => today()->toDateString(),
            'cita_id' => $this->cita?->id,
            'derivacion_id' => $this->encargo?->id,
        ];
    }
}
