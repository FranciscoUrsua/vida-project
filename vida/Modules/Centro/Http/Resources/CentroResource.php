<?php
// Modules/Centro/Http/Resources/CentroResource.php

namespace Modules\Centro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Centro\Models\Centro;

class CentroResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'tipo_centro' => new TipoCentroResource($this->whenLoaded('tipoCentro')), // Relación anidada
            'distrito' => $this->whenLoaded('distrito', fn() => [
                'id' => $this->distrito->id,
                'codigo' => $this->distrito->codigo,
                'nombre' => $this->distrito->nombre,
            ]),
            'direccion' => [ // Agrupado para georeferenciación
                'street_type' => $this->street_type,
                'street_name' => $this->street_name,
                'street_number' => $this->street_number,
                'additional_info' => $this->additional_info,
                'postal_code' => $this->postal_code,
                'city' => $this->city,
                'country' => $this->country,
                'formatted_address' => $this->formatted_address,
                'direccion_validada' => $this->direccion_validada,
                'lat' => $this->lat,
                'lng' => $this->lng,
            ],
            'contacto' => [
                'telefono' => $this->telefono,
                'email_contacto' => $this->email_contacto,
            ],
            'director' => $this->whenLoaded('director', fn() => new DirectorResource($this->director)),
            'profesionales_actuales' => CentroProfesionalResource::collection($this->whenLoaded('profesionales', fn() => $this->profesionales->where('es_actual', true))), // Solo actuales
            'datos_especificos' => $this->datos_especificos ?? [], // Overrides dinámicos
            'prestaciones_efectivas' => $this->prestacionesEfectivas, // Computed from model
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
