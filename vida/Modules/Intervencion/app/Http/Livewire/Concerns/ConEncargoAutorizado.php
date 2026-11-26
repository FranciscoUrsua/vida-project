<?php

namespace Modules\Intervencion\Http\Livewire\Concerns;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Services\PresentacionPersona;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\ActuacionesEncargo;

/**
 * Encargo profesional autorizado para las páginas del profesional de encargo (`EncargoPage` y
 * sus herramientas: valoración, escala y ficha en lectura).
 *
 * Solo el profesional asignado, con el encargo aceptado o cerrado; el resto, 403. El
 * profesional de encargo no tiene acceso a la Historia Social, así que la persona se carga sin
 * el scope de ámbito, después de autorizar el encargo.
 *
 * Restricción de dominio (EP-7, RN-17 ampliada): si la persona es protegida y el encargo ya
 * está cerrado, deja de verla (salvo acceso protegido aprobado) y la página responde 403,
 * porque el motivo, el resultado y lo registrado la identifican.
 *
 * @property-read Derivacion $encargo
 * @property-read Ciudadano $persona
 * @property-read bool $puedeActuar
 */
trait ConEncargoAutorizado
{
    /** @var int Encargo (parámetro de ruta). */
    #[Locked]
    public int $encargoId;

    /**
     * Encargo autorizado para el usuario en cada petición.
     *
     * @return Derivacion
     */
    #[Computed]
    public function encargo(): Derivacion
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        $encargo = Derivacion::query()->deProfesional($usuario)
            ->with(['centroDestino', 'area', 'derivadaPor.profesional', 'cerradaPor.profesional'])
            ->find($this->encargoId);

        abort_if($encargo === null, 403);
        abort_unless(PresentacionPersona::visible($this->cargarPersona($encargo), $usuario), 403);

        return $encargo;
    }

    /**
     * Persona del encargo (ya autorizada por encargo()).
     *
     * @return Ciudadano
     */
    #[Computed]
    public function persona(): Ciudadano
    {
        return $this->cargarPersona($this->encargo);
    }

    /**
     * Si el usuario puede registrar actuaciones en el encargo (EA-4); se vuelve a comprobar al guardar.
     *
     * @return bool
     */
    #[Computed]
    public function puedeActuar(): bool
    {
        return app(ActuacionesEncargo::class)->puedeActuar(auth()->user(), $this->encargo);
    }

    /**
     * Persona de la Historia Social del encargo, sin los scopes de ámbito.
     *
     * @param Derivacion $encargo
     *
     * @return Ciudadano
     */
    private function cargarPersona(Derivacion $encargo): Ciudadano
    {
        $ciudadanoId = (int) DB::table('historias_sociales')->where('id', $encargo->historia_id)->value('ciudadano_id');

        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($ciudadanoId);
    }
}
