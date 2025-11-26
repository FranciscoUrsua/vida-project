<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class QueueTablesSeeder extends Seeder
{
    public function run(): void
    {
        // No se insertan datos; jobs, job_batches y failed_jobs se llenan en runtime (e.g., para procesar valoraciones).
        // Opcional: Dispara un job de prueba si tienes uno definido, como: dispatch(new TestJob());
    }
}
