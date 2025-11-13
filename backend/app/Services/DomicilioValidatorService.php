<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
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

        if (app()->environment('seeding', 'testing')) {
            return [
                'latitude' => 40.4168,
                'longitude' => -3.7038,
                'formatted_address' => $addressString,
            ];
        }

        // Query local DB primero (calles_numeros)
        $localResult = DB::table('calles_numeros')
            ->where('street_name', 'like', '%' . $addressArray['street_name'] . '%')
            ->where('street_number', $addressArray['street_number'])
            ->where('postal_code', $addressArray['postal_code'])
            ->first();

        if ($localResult) {
            $latitude = $localResult->lat;
            $longitude = $localResult->lng;
            $formattedAddress = $localResult->formatted_address;

            // Validar bounds
            if (!$this->isInMadridBounds($latitude, $longitude)) {
                throw ValidationException::withMessages(['address' => 'Dirección fuera de Madrid (local DB).']);
            }

            // Validación distrito/postal
            $this->validateDistritoPostal($addressArray, $localResult->distrito_nombre);

            return [
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'formatted_address' => $formattedAddress,
            ];
        }

        // Fallback a Google si config (o error si no)
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
                    throw ValidationException::withMessages(['address' => 'Dirección fuera de Madrid (Google fallback).']);
                }

                return [
                    'latitude' => $location['lat'],
                    'longitude' => $location['lng'],
                    'formatted_address' => $formattedAddress,
                ];
            }
        }

        throw ValidationException::withMessages(['address' => 'No match en local DB ni fallback.']);
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
                throw ValidationException::withMessages(['address' => 'Distrito no coincide.']);
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
}
