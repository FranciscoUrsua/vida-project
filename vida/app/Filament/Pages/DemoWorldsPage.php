<?php

namespace App\Filament\Pages;

use App\Console\Commands\DemoResetCommand;
use Database\Seeders\Demo\DemoWorldLoader;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

/**
 * Página de gestión de entornos de demo en el backoffice.
 *
 * Permite a los administradores cargar mundos YAML predefinidos
 * para resetear el entorno de demostración con datos ficticios
 * pero realistas.
 *
 * Solo visible en entornos no productivos (canAccess() devuelve false
 * en producción).
 *
 * @see DemoWorldLoader
 * @see DemoResetCommand
 */
class DemoWorldsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Entornos Demo';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Entornos de Demo';

    /** @var string Vista Blade de la página */
    protected string $view = 'filament.pages.demo-worlds-page';

    /**
     * La página solo es accesible en entornos no productivos.
     *
     * @return bool True si el entorno no es producción
     */
    public static function canAccess(): bool
    {
        return ! app()->isProduction();
    }

    /**
     * Datos para la vista Blade: lista de mundos disponibles con metadatos.
     *
     * @return array{worlds: list<array{id: string, nombre: string, descripcion: string, centros: int, profesionales: int, ciudadanos: int, reset_cada: string}>}
     */
    public function getViewData(): array
    {
        $loader = new DemoWorldLoader;
        $worldNames = $loader->listWorlds();

        $worlds = [];

        foreach ($worldNames as $name) {
            try {
                $config = $loader->load($name);

                $totalCiudadanos = 0;

                foreach ($config['escenarios'] as $esc) {
                    foreach ($esc['ciudadanos'] as $c) {
                        $totalCiudadanos += $c['cantidad'];
                    }
                }

                $worlds[] = [
                    'id' => $name,
                    'nombre' => $config['meta']['nombre'],
                    'descripcion' => $config['meta']['descripcion'],
                    'centros' => count($config['centros']),
                    'profesionales' => count($config['profesionales']),
                    'ciudadanos' => $totalCiudadanos,
                    'reset_cada' => $config['meta']['reset_cada'],
                ];
            } catch (\InvalidArgumentException) {
                // YAML inválido — omitir sin romper la página
                $worlds[] = [
                    'id' => $name,
                    'nombre' => $name,
                    'descripcion' => '⚠ YAML con errores de validación',
                    'centros' => 0,
                    'profesionales' => 0,
                    'ciudadanos' => 0,
                    'reset_cada' => '-',
                ];
            }
        }

        return ['worlds' => $worlds];
    }

    /**
     * Genera el Action de reset para un mundo concreto.
     *
     * @param string $worldId Nombre del mundo (sin extensión)
     *
     * @return Action Action configurado con confirmación y ejecución del comando
     */
    public function getResetAction(string $worldId): Action
    {
        return Action::make("reset_{$worldId}")
            ->label('Cargar mundo')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading("¿Cargar el mundo '{$worldId}'?")
            ->modalDescription(
                'Esta operación destruirá TODOS los ciudadanos, historias sociales, planes, '.
                'entrevistas y seguimientos actuales, y reconstruirá el entorno desde el YAML seleccionado. '.
                'Esta acción no se puede deshacer.'
            )
            ->modalSubmitActionLabel('Sí, resetear entorno')
            ->action(function () use ($worldId) {
                $exitCode = Artisan::call('demo:reset', ['--world' => $worldId]);

                if ($exitCode === 0) {
                    Notification::make()
                        ->title("Mundo '{$worldId}' cargado correctamente.")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('El reset falló.')
                        ->body('Revisa los logs de la aplicación para más detalles.')
                        ->danger()
                        ->send();
                }
            });
    }
}
