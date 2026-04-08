<?php

namespace Modules\Documentos\Database\Seeders;

use App\Models\CatalogoSistema;
use App\Models\UnidadOrganizativa;
use Illuminate\Database\Seeder;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Models\PlantillaInforme;

/**
 * Siembra los datos iniciales del módulo Documentos.
 *
 * Crea:
 * 1. Valores de catálogo para el grupo 'documento.tipo'.
 * 2. Una plantilla de informe de ejemplo en la UO raíz (inactiva por defecto).
 */
class DocumentosSeeder extends Seeder
{
    public function run(): void
    {
        $this->sembrarTiposDocumento();
        $this->sembrarPlantillaEjemplo();
    }

    // -------------------------------------------------------------------------
    // Tipos de documento
    // -------------------------------------------------------------------------

    private function sembrarTiposDocumento(): void
    {
        $tipos = [
            ['clave' => 'informe_medico',           'etiqueta' => 'Informe médico',              'orden' => 1],
            ['clave' => 'certificado',               'etiqueta' => 'Certificado',                 'orden' => 2],
            ['clave' => 'documento_identidad',       'etiqueta' => 'Documento de identidad',      'orden' => 3],
            ['clave' => 'resolucion_administrativa', 'etiqueta' => 'Resolución administrativa',   'orden' => 4],
            ['clave' => 'informe_externo',           'etiqueta' => 'Informe externo',             'orden' => 5],
            ['clave' => 'informe_generado',          'etiqueta' => 'Informe generado por VIDA',   'orden' => 6],
            ['clave' => 'otro',                      'etiqueta' => 'Otro',                        'orden' => 99],
        ];

        foreach ($tipos as $tipo) {
            CatalogoSistema::firstOrCreate(
                ['grupo' => 'documento.tipo', 'clave' => $tipo['clave']],
                ['etiqueta' => $tipo['etiqueta'], 'orden' => $tipo['orden'], 'activo' => true]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Plantilla de ejemplo
    // -------------------------------------------------------------------------

    private function sembrarPlantillaEjemplo(): void
    {
        // Solo crear si no existe ya una plantilla de ejemplo con este nombre
        if (PlantillaInforme::where('nombre', 'Plantilla de ejemplo — Informe Social')->exists()) {
            return;
        }

        // Buscar la UO raíz (sin parent)
        $uoRaiz = UnidadOrganizativa::whereNull('parent_id')->orderBy('id')->first();

        if (! $uoRaiz) {
            $this->command?->warn('No existe UO raíz. La plantilla de ejemplo no se crea.');
            return;
        }

        // El admin del sistema (primer usuario) o null
        $adminId = \App\Models\User::orderBy('id')->value('id') ?? null;

        if (! $adminId) {
            $this->command?->warn('No existe ningún usuario. La plantilla de ejemplo no se crea.');
            return;
        }

        PlantillaInforme::create([
            'nombre'                 => 'Plantilla de ejemplo — Informe Social',
            'descripcion'            => 'Plantilla básica de informe social con datos del ciudadano y sección de valoración libre. Inactiva — actívela y adapte las secciones antes de usar en producción.',
            'tipo_informe'           => TipoInforme::InformeSocial->value,
            'unidad_organizativa_id' => $uoRaiz->id,
            'secciones'              => [
                [
                    'id'          => 'datos_ciudadano',
                    'titulo'      => 'Datos del ciudadano',
                    'tipo'        => 'automatico',
                    'fuente'      => 'ciudadano.datos_basicos',
                    'obligatorio' => false,
                ],
                [
                    'id'          => 'valoracion',
                    'titulo'      => 'Valoración',
                    'tipo'        => 'texto_libre',
                    'instrucciones' => 'Describa la situación social del ciudadano y el resultado de la valoración profesional.',
                    'obligatorio' => true,
                ],
            ],
            'activa'    => false,
            'creada_por' => $adminId,
        ]);
    }
}
