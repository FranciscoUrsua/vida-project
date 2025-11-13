<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use App\Services\DomicilioValidatorService;

trait HasValidatableAddress
{
    protected static function bootHasValidatableAddress()
    {
        static::saving(function (Model $model) {
            $addressArray = $model->buildAddressArray();
            if (!empty($addressArray)) {
                try {
                    $service = app(DomicilioValidatorService::class);
                    $validated = $service->validate($addressArray);
                    $model->lat = $validated['latitude'] ?? null;
                    $model->lng = $validated['longitude'] ?? null;
                    $model->direccion_validada = true;
                    if (isset($validated['formatted_address'])) {
                        $model->formatted_address = $validated['formatted_address'];
                    }
                } catch (ValidationException $e) {
                    $model->direccion_validada = false;
                    $model->lat = null;
                    $model->lng = null;
                    $model->formatted_address = null;
                    throw $e;
                }
            } else {
                $model->direccion_validada = false;
                $model->lat = null;
                $model->lng = null;
                $model->formatted_address = null;
            }
        });
    }

    // buildAddressArray y buildAddressArrayFromComponents permanecen iguales (model-specific)
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

    // Método público para validación manual (e.g., desde controlador)
    public function validateAddressManually(): void
    {
        $service = app(DomicilioValidatorService::class);
        $validated = $service->validate($this->buildAddressArray());
        $this->update([
            'lat' => $validated['latitude'],
            'lng' => $validated['longitude'],
            'direccion_validada' => true,
            'formatted_address' => $validated['formatted_address'] ?? null,
        ]);
    }
}
