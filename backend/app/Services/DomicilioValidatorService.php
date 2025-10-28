<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomicilioValidatorService
{
    protected $mockMode;

    public function __construct()
    {
        $this->mockMode = config('app.domicilio_mock', true);  // Default true para dev
    }

    /**
     * Valida domicilio contra OSM/Nominatim.
     * Requiere al menos street_name y street_number.
     * Asume city = "Madrid" si no proporcionado.
     *
     * @param array $data [street_type, street_name, street_number, additional_info, postal_code, city]
     * @return array ['valid' => bool, 'address_data' => array (campos completos), 'coords' => ['lat' => float, 'lng' => float], 'error' => null|string]
     */
    public function validateDomicilio($data)
    {
        $streetType = trim($data['street_type'] ?? '');
        $streetName = trim($data['street_name'] ?? '');
        $streetNumber = trim($data['street_number'] ?? '');
        $additionalInfo = trim($data['additional_info'] ?? '');
        $postalCode = trim($data['postal_code'] ?? '');
        $city = trim($data['city'] ?? 'Madrid');  // Asume Madrid si no

        // Validación básica: Requiere street_name y street_number
        if (empty($streetName) || empty($streetNumber)) {
            return [
                'valid' => false,
                'address_data' => $data,
                'coords' => null,
                'error' => 'Street name y number son requeridos para validación.'
            ];
        }

        Log::info('Validación domicilio OSM', [
            'query' => "$streetType $streetName $streetNumber, $additionalInfo, $postalCode $city, Spain",
            'mock' => $this->mockMode,
        ]);

        if ($this->mockMode) {
            // Mock: Siempre válido, coords de Madrid centro, postal_code simulado si vacío
            $postalCodeMock = $postalCode ?: '28001';
            return [
                'valid' => true,
                'address_data' => array_merge($data, [
                    'postal_code' => $postalCodeMock,
                    'city' => $city,
                ]),
                'coords' => ['lat' => 40.4168, 'lng' => -3.7038],  // Madrid centro
                'error' => null
            ];
        }

        // Real: Llama Nominatim OSM (gratuito, no key)
        try {
            $query = urlencode("$streetType $streetName $streetNumber, $additionalInfo, $postalCode $city, Spain");
            $response = Http::get("https://nominatim.openstreetmap.org/search", [
                'q' => $query,
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 1,  # Top match
                'countrycodes' => 'es',  # Limita a España
                'viewbox' => '-4.0,40.0, -3.0,41.0',  # Bounding box Madrid aproximado
                'bounded' => 1,  # Solo dentro de Madrid
            ]);

            if ($response->successful() && !empty($response->json())) {
                $result = $response->json()[0];  # Top result
                $address = $result['address'] ?? [];
                $postalCodeFromOsm = $address['postcode'] ?? $postalCode;

                // Chequea si está en Madrid (display_name incluye "Madrid")
                if (stripos($result['display_name'], 'Madrid') !== false) {
                    return [
                        'valid' => true,
                        'address_data' => array_merge($data, [
                            'postal_code' => $postalCodeFromOsm,
                            'city' => $address['city'] ?? $city,
                        ]),
                        'coords' => [
                            'lat' => (float) $result['lat'],
                            'lng' => (float) $result['lon'],
                        ],
                        'error' => null
                    ];
                }
            }

            return [
                'valid' => false,
                'address_data' => $data,
                'coords' => null,
                'error' => 'Dirección no válida o fuera de Madrid.'
            ];
        } catch (\Exception $e) {
            Log::error('Error en validación OSM', ['error' => $e->getMessage(), 'query' => $query]);
            return [
                'valid' => false,
                'address_data' => $data,
                'coords' => null,
                'error' => 'Error técnico en validación de domicilio.'
            ];
        }
    }
}
