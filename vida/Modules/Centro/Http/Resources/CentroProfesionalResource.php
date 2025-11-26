<?php
// Modules/Centro/Http/Resources/CentroProfesionalResource.php

namespace Modules\Centro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Centro\Models\CentroProfesional;

class CentroProfesionalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profesional' => $this->whenLoaded('profesional', fn() => [
                'id' => $this->profesional->id,
                'nombre' => $this->profesional->nombre ?? 'N/A',
                'rol' => $this->profesional->rol ?? 'Profesional',
            ]),
            'centro' => new CentroResource($this->whenLoaded('centro')),
            'fecha_alta' => $this->fecha_alta->format('Y-m-d'),
            'fecha_baja' => $this->fecha_baja?->format('Y-m-d'),
            'es_actual' => $this->es_actual, // Computed
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
