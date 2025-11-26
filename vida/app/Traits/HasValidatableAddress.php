<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use App\Services\DomicilioValidatorService;
use Illuminate\Support\Facades\Log;

trait HasValidatableAddress
{
    protected static function bootHasValidatableAddress()
    {
        static::saving(function (Model $model) {
            $addressArray = $model->buildAddressArray();
            if (!empty($addressArray)) {
                $service = app(DomicilioValidatorService::class);
                $validated = $service->validate($addressArray);

                if ($validated['success']) {
                    $model->lat = $validated['latitude'];
                    $model->lng = $validated['longitude'];
                    $model->direccion_validada = true;
                    $model->formatted_address = $validated['formatted_address'] ?? null;
                } else {
                    $model->direccion_validada = false;
                    $model->lat = null;
                    $model->lng = null;
                    $model->formatted_address = null;
                    Log::warning('Dirección no validada: ' . ($validated['error'] ?? 'Desconocido') . ' para ' . $model->getTable() . ' ID ' . $model->id);
                    // Opcional: Si config throw_on_no_match = true, throw ValidationException::withMessages(['address' => $validated['error']]);
                }
            } else {
                $model->direccion_validada = false;
                $model->lat = null;
                $model->lng = null;
                $model->formatted_address = null;
            }
        });
    }

    // buildAddressArray y buildAddressArrayFromComponents sin cambios
    protected function buildAddressArray(): array
    {
        return [
            'street_type' => $this->street_type,
            'street_name' => $this->street_name,
            'street_number' => $this->street_number,
            'additional_info' => $this->additional_info,
            'postal_code' => $this->postal_code,
            'distrito_id' => $this->distrito_id,
            'city' => $this->city ?? 'Madrid',
            'country' => $this->country ?? 'España',
        ];
    }

    public function buildAddressArrayFromComponents(array $components): array
    {
        return [
            'street_type' => $components['street_type'] ?? null,
            'street_name' => $components['street_name'] ?? null,
            'street_number' => $components['street_number'] ?? null,
            'additional_info' => $components['additional_info'] ?? null,
            'postal_code' => $components['postal_code'] ?? null,
            'distrito_id' => $components['distrito_id'] ?? null,
            'city' => $components['city'] ?? 'Madrid',
            'country' => $components['country'] ?? 'España',
        ];
    }
}
