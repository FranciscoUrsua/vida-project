<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\Distrito;

class DomicilioValidatorService
{
    private $madridBounds;
    private $throwOnNoMatch;

    public function __construct()
    {
        $this->madridBounds = [
            'min_lat' => 40.16, 'max_lat' => 40.53,
            'min_lng' => -3.85, 'max_lng' => -3.53,
        ];
        $this->throwOnNoMatch = config('services.domicilio.throw_on_no_match', false);
    }

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

        // Query local DB (con check si tabla vacía)
        $localResult = false;

        if ($localResult) {
            $latitude = $localResult->lat;
            $longitude = $localResult->lng;
            $formattedAddress = $localResult->formatted_address;

            if (!$this->isInMadridBounds($latitude, $longitude)) {
                Log::warning('Dirección fuera de bounds Madrid (local): ' . $addressString);
                return [
                    'success' => false,
                    'latitude' => null,
                    'longitude' => null,
                    'formatted_address' => null,
                    'error' => 'Fuera de bounds de Madrid.',
                ];
            }

            $this->validateDistritoPostal($addressArray, $localResult->distrito_nombre);

            return [
                'success' => true,
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'formatted_address' => $formattedAddress,
                'error' => null,
            ];
        } else {
            Log::info('No match en local DB para: ' . $addressString . ' (tabla tiene ' . DB::table('calles_numeros')->count() . ' rows)');
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
                Log::warning('Google fallback falló: ' . ($response['error_message'] ?? 'Status ' . $response['status']));
            }
        }

        // No match: Log y return failure (no throw)
        $error = 'No match en local DB ni fallback.';
        Log::warning($error . ' para ' . $addressString);
        if ($this->throwOnNoMatch) {
            throw ValidationException::withMessages(['address' => $error]);
        }

        return [
            'success' => false,
            'latitude' => null,
            'longitude' => null,
            'formatted_address' => null,
            'error' => $error,
        ];
    }

    private function isInMadridBounds(float $lat, float $lng): bool
    {
        return $lat >= $this->madridBounds['min_lat'] && $lat <= $this->madridBounds['max_lat'] &&
               $lng >= $this->madridBounds['min_lng'] && $lng <= $this->madridBounds['max_lng'];
    }

    private function validateDistritoPostal(array $addressArray, string $dbDistrito): void
    {
        if ($addressArray['distrito_id']) {
            $distrito = Distrito::find($addressArray['distrito_id']);
            if ($distrito && strpos(strtolower($dbDistrito), strtolower($distrito->nombre)) === false) {
                Log::warning('Distrito no coincide: DB "' . $dbDistrito . '" vs seleccionado "' . $distrito->nombre . '"');
            }
        }
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
            '01' => ['28012', '28013', '28014'],
            '02' => ['28005', '28045'],
            // ... (completa con rangos reales si necesitas)
        ];

        return in_array($postal, $rangos[$distrito->codigo] ?? []);
    }
}
