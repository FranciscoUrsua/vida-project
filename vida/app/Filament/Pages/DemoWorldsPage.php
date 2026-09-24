<?php

namespace App\Filament\Pages;

use App\Console\Commands\DemoResetCommand;
use Database\Seeders\Demo\DemoWorldLoader;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

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
 * Los mundos aditivos (modo: aditivo) no ofrecen reset: se muestran con la
 * etiqueta «Aditivo» y su acción es «Cargar (aditivo)» (demo:load), con un
 * modal que enseña el resultado del dry-run antes de confirmar.
 *
 * @see DemoWorldLoader
 * @see DemoResetCommand
 */
class DemoWorldsPage extends Page implements HasActions
{
    use InteractsWithActions;

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
     * @return array{worlds: list<array{id: string, nombre: string, descripcion: string, centros: int, profesionales: int, ciudadanos: int, reset_cada: string, modo: string, etiqueta: string|null}>}
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

                $aditivo = $config['modo'] === DemoWorldLoader::MODO_ADITIVO;

                $worlds[] = [
                    'id' => $name,
                    'nombre' => $config['meta']['nombre'],
                    'descripcion' => $config['meta']['descripcion'],
                    // Un mundo aditivo no crea centros: se cuentan los referenciados.
                    'centros' => $aditivo ? count($config['existentes']['centros'] ?? []) : count($config['centros']),
                    'profesionales' => count($config['profesionales']),
                    'ciudadanos' => $totalCiudadanos,
                    'reset_cada' => $config['meta']['reset_cada'],
                    'modo' => $config['modo'],
                    'etiqueta' => $config['etiqueta'],
                ];
            } catch (\InvalidArgumentException) {
                // YAML inválido — mostrar tarjeta de error sin botón de reset
                $worlds[] = [
                    'id' => $name,
                    'nombre' => $name,
                    'descripcion' => '⚠ YAML con errores de validación',
                    'centros' => 0,
                    'profesionales' => 0,
                    'ciudadanos' => 0,
                    'reset_cada' => '-',
                    'modo' => DemoWorldLoader::MODO_RESET,
                    'etiqueta' => null,
                ];
            }
        }

        return ['worlds' => $worlds];
    }

    /**
     * Registra todas las Actions, una por mundo disponible.
     * Filament requiere que estén aquí para que Livewire las reconozca.
     * La vista las dispara con wire:click="mountAction('reset_X')" o
     * mountAction('cargar_X') para los mundos aditivos (que no tienen reset).
     *
     * @return array<Action>
     */
    protected function getActions(): array
    {
        $loader = new DemoWorldLoader;

        return collect($loader->listWorlds())
            ->map(fn (string $name) => $this->esAditivo($name)
                ? $this->buildCargarAditivoAction($name)
                : $this->buildResetAction($name))
            ->all();
    }

    /**
     * Indica si un mundo es de modo aditivo (un YAML inválido se trata como no aditivo).
     *
     * @param string $worldId Nombre del mundo (sin extensión)
     */
    private function esAditivo(string $worldId): bool
    {
        try {
            return (new DemoWorldLoader)->load($worldId)['modo'] === DemoWorldLoader::MODO_ADITIVO;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Construye la Action de carga aditiva (demo:load) para un mundo aditivo.
     *
     * El modal ejecuta primero `demo:load --dry-run` y muestra su resumen, de modo que
     * quien confirma ve qué se va a crear y qué ya existe. La carga nunca borra datos.
     *
     * @param string $worldId Nombre del mundo (sin extensión)
     */
    private function buildCargarAditivoAction(string $worldId): Action
    {
        $config = (new DemoWorldLoader)->load($worldId);
        $nombre = $config['meta']['nombre'];

        return Action::make("cargar_{$worldId}")
            ->label('Cargar (aditivo)')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading("¿Cargar el mundo aditivo «{$nombre}»?")
            ->modalDescription(function () use ($worldId, $config) {
                Artisan::call('demo:load', ['--world' => $worldId, '--dry-run' => true, '--no-interaction' => true]);

                return new HtmlString(
                    '<p>No se borra ni se modifica ningún dato existente. Todo lo creado queda etiquetado como '.
                    e($config['etiqueta']).'. Resultado del dry-run:</p>'.
                    '<pre class="mt-2 max-h-80 overflow-auto text-left text-xs">'.e(Artisan::output()).'</pre>'
                );
            })
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Sí, cargar')
            ->modalCancelActionLabel('Cancelar')
            ->action(function () use ($worldId, $nombre) {
                $exitCode = Artisan::call('demo:load', ['--world' => $worldId, '--no-interaction' => true]);
                $output = Artisan::output();

                if ($exitCode === 0) {
                    Notification::make()
                        ->title("Mundo «{$nombre}» cargado (aditivo).")
                        ->success()
                        ->send();
                } else {
                    Log::error("demo:load falló para mundo '{$worldId}'", ['output' => $output]);

                    Notification::make()
                        ->title('La carga falló. No se ha guardado nada.')
                        ->body($output ?: 'Sin output. Revisa los logs de la aplicación.')
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Suprime los botones de cabecera: los botones se renderizan en las tarjetas de la vista.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Construye el Action de reset para un mundo concreto.
     *
     * @param string $worldId Nombre del mundo (sin extensión)
     */
    private function buildResetAction(string $worldId): Action
    {
        try {
            $config = (new DemoWorldLoader)->load($worldId);
            $nombre = $config['meta']['nombre'];
        } catch (\InvalidArgumentException) {
            $nombre = $worldId;
        }

        return Action::make("reset_{$worldId}")
            ->label('Cargar mundo')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading("¿Cargar el mundo «{$nombre}»?")
            ->modalDescription(
                'Esta operación destruirá TODOS los ciudadanos, historias sociales, planes, '.
                'entrevistas y seguimientos actuales, y reconstruirá el entorno desde el YAML '.
                'seleccionado. Esta acción no se puede deshacer.'
            )
            ->modalSubmitActionLabel('Sí, resetear entorno')
            ->modalCancelActionLabel('Cancelar')
            ->action(function () use ($worldId, $nombre) {
                $exitCode = Artisan::call('demo:reset', ['--world' => $worldId, '--no-interaction' => true]);
                $output = Artisan::output();

                if ($exitCode === 0) {
                    Notification::make()
                        ->title("Mundo «{$nombre}» cargado correctamente.")
                        ->success()
                        ->send();
                } else {
                    Log::error("demo:reset falló para mundo '{$worldId}'", ['output' => $output]);

                    Notification::make()
                        ->title('El reset falló.')
                        ->body($output ?: 'Sin output. Revisa los logs de la aplicación.')
                        ->danger()
                        ->send();
                }
            });
    }
}
