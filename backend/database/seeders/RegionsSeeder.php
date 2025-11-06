<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Region;
use App\Models\Country;

class RegionsSeeder extends Seeder
{
    public function run(): void
    {
        $spainId = Country::where('iso_code', 'ES')->firstOrFail()->id;

        $regions = [
            ['name' => 'Andalucía', 'code' => 'AN', 'country_id' => $spainId],
            ['name' => 'Aragón', 'code' => 'AR', 'country_id' => $spainId],
            ['name' => 'Principado de Asturias', 'code' => 'AS', 'country_id' => $spainId],
            ['name' => 'Illes Balears', 'code' => 'IB', 'country_id' => $spainId],
            ['name' => 'Islas Canarias', 'code' => 'CN', 'country_id' => $spainId],
            ['name' => 'Cantabria', 'code' => 'CB', 'country_id' => $spainId],
            ['name' => 'Castilla y León', 'code' => 'CL', 'country_id' => $spainId],
            ['name' => 'Castilla-La Mancha', 'code' => 'CM', 'country_id' => $spainId],
            ['name' => 'Cataluña', 'code' => 'CT', 'country_id' => $spainId],
            ['name' => 'Comunidad Valenciana', 'code' => 'VC', 'country_id' => $spainId],
            ['name' => 'Extremadura', 'code' => 'EX', 'country_id' => $spainId],
            ['name' => 'Galicia', 'code' => 'GA', 'country_id' => $spainId],
            ['name' => 'Comunidad de Madrid', 'code' => 'MD', 'country_id' => $spainId],
            ['name' => 'Región de Murcia', 'code' => 'MC', 'country_id' => $spainId],
            ['name' => 'Comunidad Foral de Navarra', 'code' => 'NC', 'country_id' => $spainId],
            ['name' => 'País Vasco', 'code' => 'PV', 'country_id' => $spainId],
            ['name' => 'La Rioja', 'code' => 'RI', 'country_id' => $spainId],
            ['name' => 'Ciudad Autónoma de Ceuta', 'code' => 'CE', 'country_id' => $spainId],
            ['name' => 'Ciudad Autónoma de Melilla', 'code' => 'ML', 'country_id' => $spainId],
        ];

        foreach ($regions as $region) {
            Region::firstOrCreate(
                ['code' => $region['code']],
                $region
            );
        }
    }
}
