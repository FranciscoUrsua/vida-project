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

        // Mock para seeds/dev/testing (evita API calls y errores)
        if (app()->environment('seeding', 'testing')) {
            return [
                'latitude' => 40.4168, // Madrid centro
                'longitude' => -3.7038,
                'formatted_address' => $addressString,
            ];
        }

        // Endpoint para Geonames Madrid (direcciones)
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
        if (strpos($contentType ?? '', 'application/json') === false) {
            throw ValidationException::withMessages([
                'address' => 'Respuesta no JSON de API Madrid.',
            ]);
        }

        $data = $response->json();
        if (!isset($data['@graph']) || !is_array($data['@graph']) || empty($data['@graph'])) {
            throw ValidationException::withMessages([
                'address' => 'No results en Base de Datos Ciudad para "' . $addressString . '".',
            ]);
        }

        $firstResult = $data['@graph'][0] ?? null;
        if (!$firstResult) {
            throw ValidationException::withMessages(['address' => 'No geonames result.']);
        }

        // Extrae lat/lng (formato Madrid API)
        $location = $firstResult['location'] ?? null;
        if (!$location || !isset($location['lat'], $location['lng']) || !is_numeric($location['lat']) || !is_numeric($location['lng'])) {
            throw ValidationException::withMessages(['address' => 'Geolocalización no disponible en result.']);
        }

        $formattedAddress = $firstResult['title'] ?? $addressString;
        $postalCode = $firstResult['postal code'] ?? $addressArray['postal_code'];
        $districtName = $firstResult['district']['name'] ?? ($firstResult['district'] ?? null); // district obj o string

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

    /**
     * Construye string de búsqueda para API.
     */
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

    /**
     * Valida si postal coincide con distrito (rangos reales Madrid).
     */
    private function postalMatchesDistrito(string $postal, ?Distrito $distrito): bool
    {
        if (!$distrito) return true;

        $rangos = [
            '01' => fn($p) => in_array($p, ['28012', '28013', '28014']), // Centro
            '02' => fn($p) => in_array($p, ['28005', '28045']), // Arganzuela
            '03' => fn($p) => in_array($p, ['28009', '28028']), // Retiro
            '04' => fn($p) => in_array($p, ['28006', '28001']), // Salamanca
            '05' => fn($p) => in_array($p, ['28036', '28002']), // Chamartín
            '06' => fn($p) => in_array($p, ['28020', '28046']), // Tetuán
            '07' => fn($p) => in_array($p, ['28004', '28010']), // Chamberí
            '08' => fn($p) => in_array($p, ['28035', '28039', '28048']), // Fuencarral - El Pardo
            '09' => fn($p) => in_array($p, ['28008', '28040', '28023']), // Moncloa - Aravaca
            '10' => fn($p) => in_array($p, ['28024', '28041', '28047']), // Latina
            '11' => fn($p) => in_array($p, ['28025', '28044']), // Carabanchel
            '12' => fn($p) => in_array($p, ['28026']), // Usera
            '13' => fn($p) => in_array($p, ['28018', '28031']), // Puente de Vallecas
            '14' => fn($p) => in_array($p, ['28030', '28043']), // Moratalaz
            '15' => fn($p) => in_array($p, ['28022', '28027']), // Ciudad Lineal
            '16' => fn($p) => in_array($p, ['28050', '28042']), // Hortaleza
            '17' => fn($p) => in_array($p, ['28021', '28031']), // Villaverde
            '18' => fn($p) => in_array($p, ['28031']), // Villa de Vallecas
            '19' => fn($p) => in_array($p, ['28017']), // Vicálvaro
            '20' => fn($p) => in_array($p, ['28022', '28032']), // San Blas - Canillejas
            '21' => fn($p) => in_array($p, ['28042']), // Barajas
        ];

        $matcher = $rangos[$distrito->codigo] ?? fn($p) => true;
        return $matcher($postal);
    }
}
