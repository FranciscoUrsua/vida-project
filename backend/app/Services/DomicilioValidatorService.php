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

    public function validate(array $addressArray): array
    {
        $addressString = $this->buildAddressString($addressArray);

        if (empty($addressString)) {
            throw ValidationException::withMessages(['address' => 'Dirección incompleta.']);
        }

        // Mock para seeds/dev/testing (evita API calls)
        if (app()->environment('seeding', 'testing')) {
            return [
                'latitude' => 40.4168,
                'longitude' => -3.7038,
                'formatted_address' => $addressString,
            ];
        }

        // Endpoint correcto para Geonames Madrid (direcciones)
        $response = Http::get('https://datos.madrid.es/api/v1/datos/geonames/search', [
            'query' => urlencode($addressString),
            'start' => 0,
            'rows' => 1,
        ]);

        // Check response success and JSON
        if (!$response->successful()) {
            throw ValidationException::withMessages([
                'address' => 'Error de conexión a API Madrid: ' . $response->status() . ' - ' . $response->body(),
            ]);
        }

        $contentType = $response->header('Content-Type');
        if (strpos($contentType, 'application/json') === false) {
            throw ValidationException::withMessages([
                'address' => 'Respuesta no JSON de API Madrid. Posible endpoint inválido.',
            ]);
        }

        $data = $response->json();
        if (!isset($data['@graph']) || !is_array($data['@graph']) || empty($data['@graph'])) {
            throw ValidationException::withMessages([
                'address' => 'No results en Base de Datos Ciudad para "' . $addressString . '".',
            ]);
        }

        $firstResult = $data['@graph'][0];
        if (!$firstResult) {
            throw ValidationException::withMessages(['address' => 'No geonames result.']);
        }

        // Extrae lat/lng (formato Madrid: 'location' array con 'lat', 'lng')
        $location = $firstResult['location'] ?? null;
        if (!$location || !isset($location['lat'], $location['lng']) || !is_numeric($location['lat']) || !is_numeric($location['lng'])) {
            throw ValidationException::withMessages(['address' => 'Geolocalización no disponible en result.']);
        }

        $formattedAddress = $firstResult['title'] ?? $addressString; // Title es formatted
        $postalCode = $firstResult['postal code'] ?? $addressArray['postal_code'];
        $districtName = $firstResult['district']['name'] ?? $firstResult['district'] ?? null; // district obj o string

        // Validar bounds Madrid
        if (
            $location['lat'] < $this->madridBounds['min_lat'] ||
            $location['lat'] > $this->madridBounds['max_lat'] ||
            $location['lng'] < $this->madridBounds['min_lng'] ||
            $location['lng'] > $this->madridBounds['max_lng']
        ) {
            throw ValidationException::withMessages(['address' => 'Dirección fuera de bounds de Madrid.']);
        }

        // Validación distrito/postal si proporcionado
        if ($addressArray['postal_code'] && $addressArray['distrito_id']) {
            $distrito = Distrito::find($addressArray['distrito_id']);
            if ($distrito && $districtName && strpos(strtolower($districtName), strtolower($distrito->nombre)) === false) {
                throw ValidationException::withMessages(['address' => 'Distrito en API no coincide con seleccionado.']);
            }
            if (!$this->postalMatchesDistrito($addressArray['postal_code'], $distrito)) {
                throw ValidationException::withMessages(['address' => 'Código postal inválido para distrito.']);
            }
        }

        return [
            'latitude' => (float) $location['lat'],
            'longitude' => (float) $location['lng'],
            'formatted_address' => $formattedAddress,
            'postal_code' => $postalCode,
            'district' => $districtName,
        ];
    }

    // ... (buildAddressString y postalMatchesDistrito sin cambios, como en propuesta anterior)
}
