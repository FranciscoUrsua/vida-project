<?php

namespace Modules\Agenda\Livewire\Citas\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Modules\Agenda\Models\TipoCita;
use Modules\Centro\Models\Centro;
use Modules\Usuarios\Models\Cargo;

/**
 * Campos comunes de una solicitud de cita (tipo, urgencia, destino, ventana y
 * observaciones) para las pantallas que la crean: la cita directa en
 * ventanilla y «Solicitar cita» desde la ficha. Pinta con el parcial
 * `agenda::livewire.citas.partials.formulario-solicitud`. La validación y las
 * reglas (destino, fecha límite, Historia Social) son del servicio.
 */
trait FormularioSolicitudCita
{
    /** @var array{tipo_cita_id: string, urgencia: string, destino: string, profesional_destino_id: string, servicio_destino: string, no_antes_de: string, no_despues_de: string, motivo: string, observaciones_citacion: string} */
    public array $formSolicitud = [
        'tipo_cita_id' => '',
        'urgencia' => 'ordinaria',
        'destino' => 'referencia',
        'profesional_destino_id' => '',
        'servicio_destino' => '',
        'no_antes_de' => '',
        'no_despues_de' => '',
        'motivo' => '',
        'observaciones_citacion' => '',
    ];

    /**
     * Tipos de cita activos, con el nombre que puede ver el usuario.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function opcionesTiposCita(): array
    {
        return TipoCita::activos()->orderBy('nombre')->get()
            ->mapWithKeys(fn (TipoCita $t) => [$t->id => $t->nombreParaUsuario(Auth::user())])
            ->all();
    }

    /**
     * Profesionales del centro que atienden citas, para el destino «profesional concreto».
     *
     * @return array<int, string>
     */
    #[Computed]
    public function opcionesProfesionalesCita(): array
    {
        $centro = $this->centroDeLaSolicitud();

        if ($centro === null) {
            return [];
        }

        return User::permission('citas.atender')
            ->with('profesional')
            ->whereHas('adscripcionesVigentes', fn ($q) => $q->where('unidad_organizativa_id', $centro->unidad_organizativa_id))
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->nombre_completo])
            ->sort()
            ->all();
    }

    /**
     * Perfiles (cargos) para el destino «servicio».
     *
     * @return array<string, string>
     */
    #[Computed]
    public function opcionesPerfilesCita(): array
    {
        return Cargo::where('activo', true)->orderBy('nombre')->pluck('nombre', 'slug')->all();
    }

    /**
     * Datos para SolicitudCitaService a partir del formulario.
     *
     * @param int $ciudadanoId
     * @param int $centroId
     * @param string $canal Valor de CanalSolicitudCita.
     * @return array<string, mixed>
     */
    protected function datosSolicitud(int $ciudadanoId, int $centroId, string $canal): array
    {
        $f = $this->formSolicitud;

        return [
            'ciudadano_id' => $ciudadanoId,
            'centro_id' => $centroId,
            'canal' => $canal,
            'tipo_cita_id' => $f['tipo_cita_id'] !== '' ? (int) $f['tipo_cita_id'] : null,
            'urgencia' => $f['urgencia'],
            'destino' => $f['destino'],
            'profesional_destino_id' => $f['profesional_destino_id'] !== '' ? (int) $f['profesional_destino_id'] : null,
            'servicio_destino' => $f['servicio_destino'] ?: null,
            'no_antes_de' => $f['no_antes_de'] ?: null,
            'no_despues_de' => $f['no_despues_de'] ?: null,
            'motivo' => $f['motivo'] ?: null,
            'observaciones_citacion' => $f['observaciones_citacion'] ?: null,
        ];
    }

    /**
     * Vacía el formulario.
     *
     * @return void
     */
    protected function reiniciarFormularioSolicitud(): void
    {
        $this->reset('formSolicitud');
    }

    /**
     * Centro en el que se pedirá la cita.
     *
     * @return Centro|null
     */
    abstract protected function centroDeLaSolicitud(): ?Centro;
}
