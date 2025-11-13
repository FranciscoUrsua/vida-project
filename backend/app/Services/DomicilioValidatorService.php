<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use App\Models\Distrito;

class DomicilioValidatorService
{
    private $madridBounds;

    public function __construct()
    {
        $this->madridBounds = [
            'min_lat' => 40.16, 'max_lat' => 40.53,
            'min_lng' => -3.85, 'max_lng' => -3.53,
        ];
    }

    /**
     * Valida y geocodea usando API de Datos Abiertos Madrid.
     * @param array $addressArray
     * @return array ['latitude', 'longitude', 'formatted_address']
     * @throws ValidationException
     */
    public function validate(array $addressArray): array
    {
        $addressString = $this->buildAddressString($addressArray);

        if (empty($addressString)) {
            throw ValidationException::withMessages(['address' => 'Dirección incompleta.']);
        }

        // Mock para seeds/dev
        if (app()->environment('seeding', 'testing')) {
            return [
                'latitude' => 40.4168, // Madrid centro
                'longitude' => -3.7038,
                'formatted_address' => $addressString,
            ];
        }

        // Llamada a API Datos Abiertos Madrid (dataset Calles y números)
        $response = Http::get('https://datos.madrid.es/api/accion/actuacion/search', [
            'query' => urlencode($addressString),
            'detailType' => 'ACTUACION',
            'start' => 0,
            'rows' => 1,
        ]);

        if ($response->failed() || $response['@graph'] === []) {
            throw ValidationException::withMessages([
                'address' => 'Dirección no encontrada en Base de Datos Ciudad: ' . $response['error'] ?? 'API falló',
            ]);
        }

        $firstResult = $response['@graph'][0] ?? null;
        if (!$firstResult) {
            throw ValidationException::withMessages(['address' => 'No results.']);
        }

        // Extrae lat/lng de location (formato Madrid API)
        $location = $firstResult['location'] ?? null;
        if (!$location || !isset($location['lat'], $location['lng'])) {
            throw ValidationException::withMessages(['address' => 'Geolocalización no disponible.']);
        }

        $formattedAddress = $firstResult['title'] ?? $addressString; // Title es formatted
        $postalCode = $firstResult['postal code'] ?? $addressArray['postal_code'];
        $districtName = $firstResult['district'] ?? null; // Nombre distrito para validación

        // Validar bounds Madrid
        if (
            $location['lat'] < $this->madridBounds['min_lat'] ||
            $location['lat'] > $this->madridBounds['max_lat'] ||
            $location['lng'] < $this->madridBounds['min_lng'] ||
            $location['lng'] > $this->madridBounds['max_lng']
        ) {
            throw ValidationException::withMessages(['address' => 'Dirección fuera de Madrid.']);
        }

        // Validación extra: Coincide distrito/postal si proporcionado
        if ($addressArray['postal_code'] && $addressArray['distrito_id']) {
            $distrito = Distrito::find($addressArray['distrito_id']);
            if ($distrito && $districtName && strpos($districtName, $distrito->nombre) === false) {
                throw ValidationException::withMessages(['address' => 'Distrito no coincide con postal.']);
            }
            // Opcional: Chequea rangos postales por distrito (customiza)
            if (!$this->postalMatchesDistrito($addressArray['postal_code'], $distrito)) {
                throw ValidationException::withMessages(['address' => 'Código postal inválido para distrito.']);
            }
        }

        return [
            'latitude' => (float) $location['lat'],
            'longitude' => (float) $location['lng'],
            'formatted_address' => $formattedAddress,
            'postal_code' => $postalCode, // Opcional, para update si difiere
            'district' => $districtName, // Opcional
        ];
    }

    private function buildAddressString(array $addressArray): string
    {
        return trim(implode(', ', array_filter([
            $addressArray['street_type'] . ' ' . $addressArray['street_name'],
            $addressArray['street_number'],
            $addressArray['additional_info'],
            $addressArray['postal_code'] . ' ' . $addressArray['city'],
            $addressArray['country'],
        ])));
    }

    private function postalMatchesDistrito(string $postal, ?Distrito $distrito): bool
    {
        // Rangos postales por distrito (ejemplos reales; expande con datos de Madrid)
        $rangos = [
            '02' => fn($p) => in_array(substr($p, 0, 2), ['28']), // Arganzuela: 280xx
            // Agrega más distritos...
        ];
        return $distrito && isset($rangos[$distrito->codigo]) ? $rangos[$distrito->codigo]($postal) : true;
    }
}
