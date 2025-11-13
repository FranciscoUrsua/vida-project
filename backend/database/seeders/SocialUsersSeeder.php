<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SocialUser;
use App\Models\Region;
use App\Models\Centro;
use App\Models\Profesional;
use App\Models\Distrito; // Para distrito_id
use Illuminate\Support\Facades\Hash; // Para DNI hash si usas en modelo

class SocialUsersSeeder extends Seeder
{
    public function run(): void
    {
        $madridId = Region::where('code', 'MD')->firstOrFail()->id;
        $centroIds = Centro::pluck('id')->toArray();
        $profIds = Profesional::pluck('id')->toArray();
        $distritoIds = Distrito::pluck('id', 'codigo')->toArray(); // Map código a ID

        $socialUsers = [
            [
                'first_name' => 'María',
                'last_name1' => 'González',
                'last_name2' => 'Hernández',
                'situacion_administrativa' => 'activa',
                'numero_tarjeta_sanitaria' => '1234567890123',
                'pais_origen_id' => 1, // España
                'region_id' => $madridId,
                'fecha_nacimiento' => '1985-03-15',
                'sexo' => 'F',
                'estado_civil' => 'divorced',
                'lugar_empadronamiento' => 'Madrid, España',
                // Georeferenciación split
                'street_type' => 'Calle',
                'street_name' => 'Piedra', // Limpiado
                'street_number' => '5',
                'additional_info' => '2ºA',
                'postal_code' => '28005',
                'distrito_id' => $distritoIds['02'] ?? 2, // Arganzuela
                'city' => 'Madrid',
                'country' => 'España',
                'correo' => 'maria.gonzalez@example.com',
                'telefono' => '+34 699 123 456',
                'centro_adscripcion_id' => $centroIds[0] ?? 1, // Arganzuela
                'profesional_referencia_id' => $profIds[0] ?? 1, // Ana García
                'tiene_representante_legal' => false,
                'requiere_permiso_especial' => false,
                'identificacion_desconocida' => false,
                'tipo_documento' => 'dni',
                'numero_id' => '12345678Z', // VÁLIDO DNI (checksum Z)
                'lat' => 40.4025,
                'lng' => -3.6914,
                'direccion_validada' => true,
                'formatted_address' => 'Calle Piedra 5, 28005 Madrid',
                'identificacion_historial' => json_encode(['2024-01' => 'DNI verificado']),
            ],
            [
                'first_name' => 'Juan',
                'last_name1' => 'López',
                'last_name2' => null,
                'situacion_administrativa' => 'activa',
                'numero_tarjeta_sanitaria' => '9876543210987',
                'pais_origen_id' => 157, // México
                'region_id' => $madridId,
                'fecha_nacimiento' => '1990-07-22',
                'sexo' => 'M',
                'estado_civil' => 'single',
                'lugar_empadronamiento' => 'Madrid, España',
                // Georeferenciación split
                'street_type' => 'Calle',
                'street_name' => 'Almagro', // Sin "de "
                'street_number' => '3',
                'additional_info' => null,
                'postal_code' => '28010',
                'distrito_id' => $distritoIds['07'] ?? 7, // Chamberí
                'city' => 'Madrid',
                'country' => 'España',
                'correo' => 'juan.lopez@example.com',
                'telefono' => '+34 699 789 012',
                'centro_adscripcion_id' => $centroIds[1] ?? 2, // Chamberí
                'profesional_referencia_id' => $profIds[1] ?? 2, // Carlos Martínez
                'tiene_representante_legal' => false,
                'requiere_permiso_especial' => true,
                'identificacion_desconocida' => false,
                'tipo_documento' => 'nie',
                'numero_id' => 'Y1234567T', // VÁLIDO NIE (checksum T)
                'lat' => 40.4319,
                'lng' => -3.7003,
                'direccion_validada' => false,
                'formatted_address' => 'Calle Almagro 3, 28010 Madrid',
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
                // Georeferenciación split
                'street_type' => 'Calle',
                'street_name' => 'Remonta', // Limpiado
                'street_number' => '8',
                'additional_info' => 'Esc. 1',
                'postal_code' => '28039',
                'distrito_id' => $distritoIds['08'] ?? 8, // Fuencarral - El Pardo
                'city' => 'Madrid',
                'country' => 'España',
                'correo' => 'elena.martin@example.com',
                'telefono' => '+34 699 345 678',
                'centro_adscripcion_id' => $centroIds[2] ?? 3, // Fuencarral
                'profesional_referencia_id' => $profIds[2] ?? 3, // María Pérez
                'tiene_representante_legal' => true,
                'requiere_permiso_especial' => false,
                'identificacion_desconocida' => false,
                'tipo_documento' => 'pasaporte',
                'numero_id' => 'ABC123456', // VÁLIDO Pasaporte (3 letras + 6 dígitos)
                'lat' => 40.4890,
                'lng' => -3.6906,
                'direccion_validada' => true,
                'formatted_address' => 'Calle Remonta 8, 28039 Madrid',
                'identificacion_historial' => json_encode(['2024-06' => 'Pasaporte expirado']),
            ],
            [
                'first_name' => 'Pedro',
                'last_name1' => 'Ramírez',
                'last_name2' => 'Ortega',
                'situacion_administrativa' => 'inactiva',
                'numero_tarjeta_sanitaria' => '7891234567891',
                'pais_origen_id' => 170, // Perú
                'region_id' => $madridId,
                'fecha_nacimiento' => '2000-05-05',
                'sexo' => 'M',
                'estado_civil' => 'single',
                'lugar_empadronamiento' => 'Madrid, España',
                // Georeferenciación split
                'street_type' => 'Calle',
                'street_name' => 'Poveda', // Sin "de la "
                'street_number' => '2',
                'additional_info' => null,
                'postal_code' => '28047',
                'distrito_id' => $distritoIds['10'] ?? 10, // Latina
                'city' => 'Madrid',
                'country' => 'España',
                'correo' => null,
                'telefono' => '+34 699 901 234',
                'centro_adscripcion_id' => $centroIds[3] ?? 4, // Latina
                'profesional_referencia_id' => $profIds[3] ?? 4, // David López
                'tiene_representante_legal' => false,
                'requiere_permiso_especial' => false,
                'identificacion_desconocida' => false,
                'tipo_documento' => 'dni',
                'numero_id' => '87654321R', // VÁLIDO DNI (checksum R para 87654321 % 23 = 17)
                'lat' => 40.3856,
                'lng' => -3.7471,
                'direccion_validada' => false,
                'formatted_address' => 'Calle Poveda 2, 28047 Madrid',
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
                // Georeferenciación split (caso desconocido)
                'street_type' => null,
                'street_name' => 'dirección desconocida',
                'street_number' => null,
                'additional_info' => null,
                'postal_code' => null,
                'distrito_id' => null,
                'city' => 'Madrid',
                'country' => 'España',
                'correo' => 'sofia.torres@example.com',
                'telefono' => '+34 699 567 890',
                'centro_adscripcion_id' => $centroIds[4] ?? 5, // Usera
                'profesional_referencia_id' => $profIds[4] ?? 5, // Laura Gómez
                'tiene_representante_legal' => false,
                'requiere_permiso_especial' => true,
                'identificacion_desconocida' => true, // Skip validación ID
                'tipo_documento' => 'dni',
                'numero_id' => null, // Null si desconocida
                'lat' => null,
                'lng' => null,
                'direccion_validada' => false,
                'formatted_address' => null,
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
