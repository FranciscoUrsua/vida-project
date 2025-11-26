<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CacheTablesSeeder extends Seeder
{
    public function run(): void
    {
        // No se insertan datos; tablas cache y cache_locks se gestionan automáticamente por Laravel.
        // Opcional: Cachea un valor de prueba para validar.
        // cache(['test_key' => 'test_value', 'expires_at' => now()->addHour()]);
    }
}
