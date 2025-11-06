<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AuditsSeeder extends Seeder
{
    public function run(): void
    {
        // No insertamos datos fijos; audits se crean automáticamente via eventos Laravel
        // (e.g., al seedear social_users, audita 'created'). Para prueba manual:
        // event(new ModelEvent(AppUser::first())); // Si usas OwenIt\Auditing
    }
}
