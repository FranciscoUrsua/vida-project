<?php
// Modules/Centro/Http/Resources/TipoCentroResource.php

namespace Modules\Centro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Centro\Models\TipoCentro;

class TipoCentroResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'tiene_plazas' => $this->tiene_plazas,
            'numero_plazas' => $this->tiene_plazas ? $this->numero_plazas : null,
            'criterio_asignacion_plazas' => $this->tiene_plazas ? $this->criterio_asignacion_plazas : null,
            'prestaciones_default' => $this->prestaciones_default, // Array de IDs
            'prestaciones_efectivas' => $this->prestacionesEfectivas, // Computed from model (carga relación si needed)
            'publico_objetivo' => $this->publico_objetivo ?? [], // Array de targets
            'schema_campos_dinamicos' => $this->schema_campos_dinamicos ?? [], // Para frontend dinámico
            'centros_count' => $this->whenCounted('centros'), // Meta: número de centros asociados
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
