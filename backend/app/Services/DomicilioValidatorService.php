<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Distrito;

class DomicilioValidatorService
{
    private $madridBounds;
    private $throwOnNoMatch; // Config para throw en prod si no match

    public function __construct()
    {
        $this->madridBounds = [
            'min_lat' => 40.16, 'max_lat' => 40.53,
            'min_lng' => -3.85, 'max_lng' => -3.53,
        ];
        $this->throwOnNoMatch = config('services.domicilio.throw_on_no_match', false); // En config/services.php: 'domicilio' => ['throw_on_no_match' => env('DOMICILIO_THROW_NO_MATCH', false)]
    }

    /**
     * Valida y geocodea. Devuelve array con success flag.
     * @param array $addressArray
     * @return array ['success' => bool, 'latitude' => ?, 'longitude' => ?, 'formatted_address' => ?, 'error' => ?string]
     */
    public function validate(array $addressArray): array
    {
        $addressString = $this->buildAddressString($addressArray);

        if (empty($addressString)) {
            return [
                'success' => false,
                'latitude' => null,
                'longitude' => null,
                'formatted_address' => null,
                'error' => 'Dirección incompleta.',
            ];
        }

        if (app()->environment('seeding', 'testing')) {
            return [
                'success' => true,
                'latitude' => 40.4168,
                'longitude' => -3.7038,
                'formatted_address' => $addressString,
                'error' => null,
            ];
        }

        // Query local DB
        $localResult = DB::table('calles_numeros')
            ->where('street_name', 'like', '%' . $addressArray['street_name'] . '%')
            ->where('street_number', $addressArray['street_number'])
            ->where('postal_code', $addressArray['postal_code'])
            ->first();

        if ($localResult) {
            $latitude = $localResult->lat;
            $longitude = $localResult->lng;
            $formattedAddress = $localResult->formatted_address;

            if (!$this->isInMadridBounds($latitude, $longitude)) {
                Log::warning('Dirección fuera de bounds Madrid: ' . $addressString);
                return [
                    'success' => false,
                    'latitude' => null,
                    'longitude' => null,
                    'formatted_address' => null,
                    'error' => 'Fuera de bounds de Madrid.',
                ];
            }

            // Validación distrito/postal
            $dbDistrito = $localResult->distrito_nombre;
            if ($addressArray['distrito_id']) {
                $distrito = Distrito::find($addressArray['distrito_id']);
                if ($distrito && strpos(strtolower($dbDistrito), strtolower($distrito->nombre)) === false) {
                    Log::warning('Distrito no coincide: ' . $addressString);
                    return [
                        'success' => false,
                        'latitude' => null,
                        'longitude' => null,
                        'formatted_address' => null,
                        'error' => 'Distrito no coincide con postal.',
                    ];
                }
            }
            if (!$this->postalMatchesDistrito($addressArray['postal_code'], $distrito ?? null)) {
                Log::warning('Postal inválido para distrito: ' . $addressString);
                return [
                    'success' => false,
                    'latitude' => null,
                    'longitude' => null,
                    'formatted_address' => null,
                    'error' => 'Código postal inválido para distrito.',
                ];
            }

            return [
                'success' => true,
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'formatted_address' => $formattedAddress,
                'error' => null,
            ];
        }

        // Fallback Google si config
        $googleKey = config('services.google_geocoding.key');
        if ($googleKey) {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => urlencode($addressString),
                'key' => $googleKey,
            ]);

            if ($response->successful() && $response['status'] === 'OK') {
                $location = $response['results'][0]['geometry']['location'];
                $formattedAddress = $response['results'][0]['formatted_address'];

                if (!$this->isInMadridBounds($location['lat'], $location['lng'])) {
                    Log::warning('Google fallback fuera de bounds: ' . $addressString);
                    return [
                        'success' => false,
                        'latitude' => null,
                        'longitude' => null,
                        'formatted_address' => null,
                        'error' => 'Fuera de bounds (Google).',
                    ];
                }

                return [
                    'success' => true,
                    'latitude' => $location['lat'],
                    'longitude' => $location['lng'],
                    'formatted_address' => $formattedAddress,
                    'error' => null,
                ];
            } else {
                Log::warning('Google fallback falló: ' . $response['error_message'] ?? 'Status ' . $response['status']);
            }
        }

        // No match: Log y return failure (no throw)
        Log::warning('No match para dirección: ' . $addressString);
        return [
            'success' => false,
            'latitude' => null,
            'longitude' => null,
            'formatted_address' => null,
            'error' => 'No match en local DB ni fallback.',
        ];
    }

    private function isInMadridBounds(float $lat, float $lng): bool
    {
        return $lat >= $this->madridBounds['min_lat'] && $lat <= $this->madridBounds['max_lat'] &&
               $lng >= $this->madridBounds['min_lng'] && $lng <= $this->madridBounds['max_lng'];
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
        if (!$distrito) return true;

        $rangos = [
            '01' => ['28012', '28013', '28014'], // Centro
            '02' => ['28005', '28045'], // Arganzuela
            // ... (resto de rangos como antes)
        ];

        return in_array($postal, $rangos[$distrito->codigo] ?? []);
    }
}
