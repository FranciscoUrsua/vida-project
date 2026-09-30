<?php

namespace App\Filament\Resources\CentroResource\Pages;

use App\Filament\Resources\CentroResource;
use App\Filament\Resources\Pages\ListRecords;
use App\Models\CatalogoSistema;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Modules\Centro\Services\Asignacion\ResolucionCentroService;
use Modules\Organizacion\Models\SeccionCensal;

/**
 * Página de listado de centros.
 */
class ListCentros extends ListRecords
{
    protected static string $resource = CentroResource::class;

    /** Secciones sin centro que se nombran en el aviso; el resto, con `centros:comprobar-cobertura`. */
    private const MAX_SECCIONES_EN_AVISO = 20;

    /**
     * Alta de centro y comprobación de cobertura territorial.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            // Los huecos se ven al configurar, no al dar de alta a una persona (docs/modulo-asignacion.md §3.3)
            Action::make('comprobar_cobertura')
                ->label('Comprobar cobertura')
                ->icon('heroicon-o-map')
                ->color('gray')
                ->modalDescription('Lista las secciones censales que no cubre ningún centro del tipo elegido.')
                ->modalSubmitActionLabel('Comprobar')
                ->form([
                    Select::make('tipo_centro')
                        ->label('Tipo de centro')
                        ->options(fn () => CatalogoSistema::opcionesParaSelect('centro.tipo'))
                        ->required(),
                ])
                ->action(fn (array $data) => $this->notificarCobertura($data['tipo_centro'])),
        ];
    }

    /**
     * Muestra el resultado de la comprobación de cobertura de un tipo de centro.
     *
     * @param string $tipoCentro
     * @return void
     */
    private function notificarCobertura(string $tipoCentro): void
    {
        $huecos = app(ResolucionCentroService::class)->seccionesSinCobertura($tipoCentro);

        if ($huecos->isEmpty()) {
            Notification::make()->success()->title('Todas las secciones censales tienen centro de este tipo.')->send();

            return;
        }

        $lista = $huecos->load('barrio')
            ->take(self::MAX_SECCIONES_EN_AVISO)
            ->map(fn (SeccionCensal $s) => $s->codigo_ine.($s->barrio ? " ({$s->barrio->nombre})" : ''))
            ->implode(', ');

        $resto = $huecos->count() - self::MAX_SECCIONES_EN_AVISO;

        Notification::make()
            ->warning()
            ->title("{$huecos->count()} secciones censales sin centro")
            ->body($lista.($resto > 0 ? " y {$resto} más. Lista completa: php artisan centros:comprobar-cobertura {$tipoCentro}" : ''))
            ->persistent()
            ->send();
    }
}
