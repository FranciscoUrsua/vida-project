<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PadronService
{
    protected $mockMode;

    public function __construct()
    {
        $this->mockMode = config('app.padron_mock', true);
    }

    /**
     * Busca residencia en padrón de Madrid (prioridad ID, fallback nombre + fecha).
     *
     * @param array $data [dni_nie_pasaporte (DNI/NIE/pasaporte), first_name, last_name1, last_name2, fecha_nacimiento]
     * @return array [valid: bool, matches: array, error: string|null]
     */
    public function searchResidency($data)
    {
        $id = trim($data['dni_nie_pasaporte'] ?? '');  // DNI/NIE/pasaporte
        $nombre = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name1'] ?? '') . ' ' . ($data['last_name2'] ?? ''));
        $fechaNac = $data['fecha_nacimiento'] ?? null;

        Log::info('Búsqueda padrón Ayuntamiento Madrid', [
            'id_type' => $id ? 'DNI/NIE/Pasaporte' : 'por nombre + fecha',
            'id' => $id,
            'nombre' => $nombre,
            'fecha_nac' => $fechaNac,
            'mock' => $this->mockMode,
        ]);

        if ($this->mockMode) {
            return [
                'valid' => true,
                'matches' => [],
                'error' => null
            ];



            $matches = [];

            if ($id) {
                // Mock por ID: Siempre single match
                $matches = [
                    [
                        'id' => 'mock_' . uniqid(),
                        'full_name' => $nombre ?: 'Mock User',
                        'identificacion' => $id,
                        'empadronado' => true,
                        'direccion' => ['postal_code' => '28001', 'city' => 'Madrid'],
                        'match_score' => 100,
                        'historial_id' => [$id],
                    ]
                ];
            } else {
                // Mock por nombre + fecha: Múltiples si ambigüedad
                $matches = [
                    [
                        'id' => 'mock_1',
                        'full_name' => $nombre,
                        'identificacion' => '87654321X (NIE histórico)',
                        'empadronado' => true,
                        'direccion' => ['postal_code' => '28013', 'city' => 'Madrid'],
                        'match_score' => 90,
                        'historial_id' => ['87654321X', '12345678Z'],
                    ],
                    [
                        'id' => 'mock_2',
                        'full_name' => $nombre . ' (similar)',
                        'identificacion' => '12345678Z (DNI actual)',
                        'empadronado' => true,
                        'direccion' => ['postal_code' => '28001', 'city' => 'Madrid'],
                        'match_score' => 85,
                        'historial_id' => ['12345678Z'],
                    ],
                ];
            }
            return [
                'valid' => !empty($matches),
                'matches' => $matches,
                'error' => null
            ];
        }

        // Real: Llama API (prioridad ID, fallback nombre + fecha)
        try {
            $params = [
                'nombre' => $nombre,
                'fecha_nacimiento' => $fechaNac,
            ];

            if ($id) {
                $params['identificacion'] = $id;  // DNI/NIE/pasaporte
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.padron.token'),
            ])->post(config('services.padron.url'), $params);

            if ($response->successful()) {
                $result = $response->json();
                $matches = $result['matches'] ?? [];  // Asume array con historial_id si aplica
                return [
                    'valid' => !empty($matches),
                    'matches' => $matches,
                    'error' => null
                ];
            }

            return [
                'valid' => false,
                'matches' => [],
                'error' => 'Error en API de padrón: ' . $response->body()
            ];
        } catch (\Exception $e) {
            Log::error('Error en búsqueda padrón', ['error' => $e->getMessage()]);
            return [
                'valid' => false,
                'matches' => [],
                'error' => 'Error técnico en validación de padrón'
            ];
        }
    }
}
