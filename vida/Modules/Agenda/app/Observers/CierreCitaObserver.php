<?php

namespace Modules\Agenda\Observers;

use Modules\Agenda\Models\Cita;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Intervencion\Models\Apunte;

/**
 * Cierre implícito de la cita (docs/modulo-citas.md §3.5): observa los apuntes
 * y los registros de atención que se crean con `cita_id`.
 *
 * Antes de crearlos valida el vínculo (misma persona, cita no cerrada salvo
 * corrección de supervisión); después completa la cita con el primero.
 */
class CierreCitaObserver
{
    /**
     * @param AtencionCitaService $atencion
     */
    public function __construct(private readonly AtencionCitaService $atencion) {}

    /**
     * Valida el vínculo antes de crear el apunte o el registro.
     *
     * @param Apunte|RegistroAtencion $model
     * @return void
     *
     * @throws \LogicException
     */
    public function creating(Apunte|RegistroAtencion $model): void
    {
        if ($model->cita_id === null) {
            return;
        }

        $model instanceof Apunte
            ? $this->atencion->validarVinculo($model)
            : $this->atencion->validarVinculoAtencion($model);
    }

    /**
     * Completa la cita con el primer apunte o registro vinculado.
     *
     * @param Apunte|RegistroAtencion $model
     * @return void
     */
    public function created(Apunte|RegistroAtencion $model): void
    {
        if ($model->cita_id === null) {
            return;
        }

        $cita = Cita::findOrFail($model->cita_id);

        $model instanceof Apunte
            ? $this->atencion->completarPorApunte($cita, $model)
            : $this->atencion->completarPorAtencion($cita, $model);
    }
}
