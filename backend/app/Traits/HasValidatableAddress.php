<?php

namespace App\Traits;

use App\Services\DomicilioValidatorService;
use Illuminate\Support\Facades\Log;

trait HasValidatableAddress
{
    /**
     * Valida y geocodea la dirección, guarda coords si válido.
     *
     * @param array $data Campos de dirección
     * @return bool true si válido, false si error (setea flags)
     */
    public function validateAndGeocodeAddress(array $data): bool
    {
        $domicilioData = [
            'street_type' => $data['street_type'] ?? '',
            'street_name' => $data['street_name'] ?? '',
            'street_number' => $data['street_number'] ?? '',
            'additional_info' => $data['additional_info'] ?? '',
            'postal_code' => $data['postal_code'] ?? '',
            'city' => $data['city'] ?? 'Madrid',
        ];

        Log::info('Validando domicilio en model', $domicilioData);

        if (empty($domicilioData['street_name']) || empty($domicilioData['street_number'])) {
            $this->direccion_validada = false;
            $this->formatted_address = null;
            $this->lat = null;
            $this->lng = null;
            Log::info('Domicilio saltado - campos insuficientes');
            return false;
        }

        $validatorService = new DomicilioValidatorService();
        $result = $validatorService->validateDomicilio($domicilioData);

        Log::info('Resultado validación domicilio', $result);

        if (!$result['valid']) {
            $this->direccion_validada = false;
            $this->formatted_address = null;
            $this->lat = null;
            $this->lng = null;
            Log::error('Domicilio inválido', ['error' => $result['error']]);
            return false;
        }

        // Guarda en el model
        $this->direccion_validada = true;
        $this->formatted_address = $result['formatted_address'] ?? implode(', ', array_filter([
            $domicilioData['street_type'] . ' ' . $domicilioData['street_name'],
            $domicilioData['street_number'],
            $domicilioData['additional_info'],
            $domicilioData['postal_code'] . ' ' . $domicilioData['city']
        ]));
        $this->lat = $result['coords']['lat'] ?? null;
        $this->lng = $result['coords']['lng'] ?? null;

        Log::info('Domicilio georeferenciado', [
            'lat' => $this->lat,
            'lng' => $this->lng,
            'formatted_address' => $this->formatted_address,
        ]);

        return true;
    }
}
