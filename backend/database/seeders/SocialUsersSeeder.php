<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SocialUser;
use App\Models\Region;
use App\Models\Centro;
use App\Models\Profesional;
use Illuminate\Support\Facades\Hash; // Para DNI hash si usas en modelo

class SocialUsersSeeder extends Seeder
{
    public function run(): void
    {
        $madridId = Region::where('code', 'MD')->firstOrFail()->id;
        $centroIds = Centro::pluck('id')->toArray();
        $profIds = Profesional::pluck('id')->toArray();

        $socialUsers = [
            [
                'first_name' => 'María',
                'last_name1' => 'González',
                'last_name2' => 'Hernández',
                'situacion_administrativa' => 'activa',
                'numero_tarjeta_sanitaria' => '1234567890123',
                'pais_origen_id' => 1, // España (asumiendo ID de ES)
                'region_id' => $madridId,
                'fecha_nacimiento' => '1985-03-15',
                'sexo' => 'F',
                'estado_civil' => 'divorced',
                'lugar_empadronamiento' => 'Madrid, España',
                'city' => 'Madrid',
                'correo' => 'maria.gonzalez@example.com',
                'telefono' => '+34 699 123 456',
                'centro_adscripcion_id' => $centroIds[0] ?? 1, // Arganzuela
                'profesional_referencia_id' => $profIds[0] ?? 1, // Ana García
                'tiene_representante_legal' => false,
                'requiere_permiso_especial' => false,
                'identificacion_desconocida' => false,
                'tipo_documento' => 'dni',
                'numero_id' => '12345678A', // Hash en modelo para privacidad
                'lat' => 40.4168,
                'lng' => -3.7038,
                'direccion_validada' => true,
                'formatted_address' => 'Calle Ficticia 123, Madrid',
                'identificacion_historial' => json_encode(['2024-01' => 'DNI verificado']),
            ],
            [
                'first_name' => 'Juan',
                'last_name1' => 'López',
                'last_name2' => null,
                'situacion_administrativa' => 'activa',
                'numero_tarjeta_sanitaria' => '9876543210987',
                'pais_origen_id' => 157, // México (ID aproximado de lista ISO)
                'region_id' => $madridId,
                'fecha_nacimiento' => '1990-07-22',
                'sexo' => 'M',
                'estado_civil' => 'single',
                'lugar_empadronamiento' => 'Madrid, España',
                'city' => 'Madrid',
                'correo' => 'juan.lopez@example.com',
                'telefono' => '+34 699 789 012',
                'centro_adscripcion_id' => $centroIds[1] ?? 2, // Chamberí
                'profesional_referencia_id' => $profIds[1] ?? 2, // Carlos Martínez
                'tiene_representante_legal' => false,
                'requiere_permiso_especial' => true, // Ej: menor
                'identificacion_desconocida' => false,
                'tipo_documento' => 'nie',
                'numero_id' => 'Y1234567',
                'lat' => 40.4319,
                'lng' => -3.7003,
                'direccion_validada' => false,
                'formatted_address' => 'Av. Ficticia 456, Madrid',
                'identificacion_historial' => json_encode(['2025-01' => 'NIE renovado']),
            ],
            [
                'first_name' => 'Elena',
                'last_name1' => 'Martín',
                'last_name2' => 'Vázquez',
                'situacion_administrativa' => 'seguimiento',
                'numero_tarjeta_sanitaria' => '4567891234567',
                'pais_origen_id' => 1, // España
                'region_id' => $madridId,
                'fecha_nacimiento' => '1978-11-10',
                'sexo' => 'F',
                'estado_civil' => 'widowed',
                'lugar_empadronamiento' => 'Madrid, España',
                'city' => 'Madrid',
                'correo' => 'elena.martin@example.com',
                'telefono' => '+34 699 345 678',
                'centro_adscripcion_id' => $centroIds[2] ?? 3, // Fuencarral
                'profesional_referencia_id' => $profIds[2] ?? 3, // María Pérez
                'tiene_representante_legal' => true,
                'requiere_permiso_especial' => false,
                'identificacion_desconocida' => true,
                'tipo_documento' => 'pasaporte',
                'numero_id' => 'AB123456',
                'lat' => 40.4890,
                'lng' => -3.6906,
                'direccion_validada' => true,
                'formatted_address' => 'Calle Mayor 789, Madrid',
                'identificacion_historial' => json_encode(['2024-06' => 'Pasaporte expirado']),
            ],
            [
                'first_name' => 'Pedro',
                'last_name1' => 'Ramírez',
                'last_name2' => 'Ortega',
                'situacion_administrativa' => 'inactiva',
                'numero_tarjeta_sanitaria' => '7891234567891',
                'pais_origen_id' => 170, // Perú (ID aproximado)
                'region_id' => $madridId,
                'fecha_nacimiento' => '2000-05-05',
                'sexo' => 'M',
                'estado_civil' => 'single',
                'lugar_empadronamiento' => 'Madrid, España',
                'city' => 'Madrid',
                'correo' => null,
                'telefono' => '+34 699 901 234',
                'centro_adscripcion_id' => $centroIds[3] ?? 4, // Latina
                'profesional_referencia_id' => $profIds[3] ?? 4, // David López
                'tiene_representante_legal' => false,
                'requiere_permiso_especial' => false,
                'identificacion_desconocida' => false,
                'tipo_documento' => 'dni',
                'numero_id' => '44556677B',
                'lat' => 40.3856,
                'lng' => -3.7471,
                'direccion_validada' => false,
                'formatted_address' => 'Plaza Ficticia 1, Madrid',
                'identificacion_historial' => json_encode([]),
            ],
            [
                'first_name' => 'Sofía',
                'last_name1' => 'Torres',
                'last_name2' => 'Díaz',
                'situacion_administrativa' => 'activa',
                'numero_tarjeta_sanitaria' => '3216549870123',
                'pais_origen_id' => 1, // España
                'region_id' => $madridId,
                'fecha_nacimiento' => '1965-09-18',
                'sexo' => 'F',
                'estado_civil' => 'married',
                'lugar_empadronamiento' => 'Madrid, España',
                'city' => 'Madrid',
                'correo' => 'sofia.torres@example.com',
                'telefono' => '+34 699 567 890',
                'centro_adscripcion_id' => $centroIds[4] ?? 5, // Usera
                'profesional_referencia_id' => $profIds[4] ?? 5, // Laura Gómez
                'tiene_representante_legal' => false,
                'requiere_permiso_especial' => true, // Dependencia
                'identificacion_desconocida' => false,
                'tipo_documento' => 'dni',
                'numero_id' => '99887766C',
                'lat' => 40.3880,
                'lng' => -3.7222,
                'direccion_validada' => true,
                'formatted_address' => 'Calle Usera 101, Madrid',
                'identificacion_historial' => json_encode(['2025-02' => 'DNI actualizado']),
            ],
        ];

        foreach ($socialUsers as $user) {
            SocialUser::firstOrCreate(
                ['numero_id' => $user['numero_id']],
                $user
            );
        }
    }
}
