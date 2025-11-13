<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\CalleNumero; // Asume modelo

class CallesNumerosSeeder extends Seeder
{
    public function run(): void
    {
        $url = 'https://datos.madrid.es/egob/catalogo/300300000023-0-calles-numeros-portales.json';
        $response = Http::get($url);

        if (!$response->successful()) {
            // Fallback: Descarga manual o seed vacío
            $this->log('Error descargando dataset; carga manual desde https://datos.madrid.es');
            return;
        }

        $data = $response->json();
        $graph = $data['@graph'] ?? [];

        foreach ($graph as $item) {
            CalleNumero::firstOrCreate(
                ['street_name' => $item['street_name'] ?? '', 'street_number' => $item['street_number'] ?? '', 'postal_code' => $item['postal_code'] ?? ''],
                [
                    'distrito_nombre' => $item['district']['name'] ?? '',
                    'lat' => $item['location']['lat'] ?? null,
                    'lng' => $item['location']['lng'] ?? null,
                    'formatted_address' => $item['title'] ?? '', // Formatted
                ]
            );
        }

        $this->command->info('Cargados ' . count($graph) . ' registros de calles/números.');
    }
}
