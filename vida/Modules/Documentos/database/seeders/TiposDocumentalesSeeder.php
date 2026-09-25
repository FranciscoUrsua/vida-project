<?php

namespace Modules\Documentos\Database\Seeders;

use App\Models\CatalogoSistema;
use Illuminate\Database\Seeder;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Enums\PoliticaVersiones;
use Modules\Documentos\Models\TipoDocumental;

/**
 * Crea los tipos documentales a partir del catálogo antiguo documento.tipo (custodia v2, paso 1).
 *
 * Un tipo por cada clave del grupo, con valores por defecto conservadores, más el tipo
 * genérico informe_profesional que usan los informes firmados. Idempotente: un tipo
 * que ya existe solo actualiza su nombre, para no pisar la configuración hecha en
 * Filament. No borra el grupo del catálogo.
 */
class TiposDocumentalesSeeder extends Seeder
{
    /**
     * Crea o actualiza los tipos documentales.
     *
     * @return void
     */
    public function run(): void
    {
        $catalogo = CatalogoSistema::where('grupo', 'documento.tipo')->orderBy('orden')->get();

        foreach ($catalogo as $entrada) {
            $this->crearOActualizar($entrada->clave, $entrada->etiqueta, [
                'familia' => FamiliaDocumental::AportadoCiudadano,
                'origen_eni' => OrigenEni::Ciudadano,
                'caduca' => false,
                'politica_versiones' => PoliticaVersiones::Conservar,
                'conservacion_anyos' => null,
                'requiere_firma' => false,
                'activo' => (bool) $entrada->activo,
            ]);
        }

        $this->crearOActualizar('informe_profesional', 'Informe profesional', [
            'familia' => FamiliaDocumental::InformeProfesional,
            'origen_eni' => OrigenEni::Administracion,
            'caduca' => false,
            'politica_versiones' => PoliticaVersiones::Conservar,
            'conservacion_anyos' => null,
            'requiere_firma' => true,
            'activo' => true,
        ]);
    }

    /**
     * Crea el tipo con sus valores por defecto o, si existe, solo actualiza el nombre.
     *
     * @param string $codigo Código del tipo.
     * @param string $nombre Nombre visible.
     * @param array<string, mixed> $defectos Valores para un tipo nuevo.
     *
     * @return void
     */
    private function crearOActualizar(string $codigo, string $nombre, array $defectos): void
    {
        $tipo = TipoDocumental::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, ...$defectos]);

        if (! $tipo->wasRecentlyCreated && $tipo->nombre !== $nombre) {
            $tipo->update(['nombre' => $nombre]);
        }
    }
}
