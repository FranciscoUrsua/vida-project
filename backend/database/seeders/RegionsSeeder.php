<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Region;
use App\Models\Country;

class RegionsSeeder extends Seeder
{
    public function run(): void
    {
        $spain = Country::where('iso_code', 'ES')->first();

        if (!$spain) {
            // Si no existe España, créala o salta
            throw new \Exception('País España no encontrado. Ejecuta CountriesSeeder primero.');
        }

        $regions = [
            ['name' => 'Andalucía', 'code' => 'AN'],
            ['name' => 'Aragón', 'code' => 'AR'],
            ['name' => 'Principado de Asturias', 'code' => 'AS'],
            ['name' => 'Illes Balears', 'code' => 'IB'],
            ['name' => 'Canarias', 'code' => 'CN'],
            ['name' => 'Cantabria', 'code' => 'CB'],
            ['name' => 'Castilla y León', 'code' => 'CL'],
            ['name' => 'Castilla-La Mancha', 'code' => 'CM'],
            ['name' => 'Catalunya', 'code' => 'CT'],
            ['name' => 'Comunitat Valenciana', 'code' => 'VC'],
            ['name' => 'Extremadura', 'code' => 'EX'],
            ['name' => 'Galicia', 'code' => 'GA'],
            ['name' => 'Comunidad de Madrid', 'code' => 'MD'],
            ['name' => 'Región de Murcia', 'code' => 'MC'],
            ['name' => 'Comunidad Foral de Navarra', 'code' => 'NC'],
            ['name' => 'País Vasco', 'code' => 'PV'],
            ['name' => 'La Rioja', 'code' => 'RI'],
            ['name' => 'Ceuta', 'code' => 'CE'],
            ['name' => 'Melilla', 'code' => 'ML'],
        ];

        foreach ($regions as $region) {
            Region::updateOrCreate(
                ['code' => $region['code']],  // Unique por code
                [
                    'name' => $region['name'],
                    'country_id' => $spain->id,
                ]
            );
        }
    }
}
