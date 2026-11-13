<?php

namespace App\Filament\Resources\CentroResource\Pages;

use App\Filament\Acciones\CentroTipoAcciones;
use App\Filament\Resources\CentroResource;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Centro\Enums\ElementoCatalogo;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\ExclusionCatalogo;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Centro\Services\TipoCentro\CentroService;
use Modules\Centro\Services\TipoCentro\ExclusionCatalogoService;

/**
 * Página de edición de un centro. Las prestaciones no se editan aquí: son las
 * del catálogo de su tipo (docs/modulo-tipo-centro.md §6.1). Las operaciones
 * con fecha o con reglas propias (denominación, cierres, baja, volver al valor
 * del tipo, exclusiones) son acciones de cabecera que pasan por los servicios.
 */
class EditCentro extends EditRecord
{
    protected static string $resource = CentroResource::class;

    /**
     * Acciones del centro: denominación, cierres, valores del tipo, exclusiones y baja.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CentroTipoAcciones::renombrar(),
            CentroTipoAcciones::anadirCierre(),
            $this->volverAlProtocoloDelTipo(),
            $this->volverAlHorarioDelTipo(),
            $this->exclusiones(),
            CentroTipoAcciones::darDeBaja(),
        ];
    }

    /**
     * Guarda por CentroService solo los campos que cambian: así se aplican en el
     * servidor los límites del director y de las propiedades «solo tipo» (§9).
     *
     * @param Model $record
     * @param array<string, mixed> $data
     *
     * @return Model
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Centro $record */
        $cambios = array_filter(
            $data,
            // Comparación laxa a propósito: el formulario manda cadenas y el modelo tiene casts
            fn ($valor, string $campo) => $record->isFillable($campo) && $record->getAttribute($campo) != $valor,
            ARRAY_FILTER_USE_BOTH,
        );

        return $cambios === [] ? $record : app(CentroService::class)->actualizar($record, $cambios, auth()->user());
    }

    /**
     * «Volver al valor del tipo» del protocolo: vacía el propio (§4).
     *
     * @return Action
     */
    private function volverAlProtocoloDelTipo(): Action
    {
        return Action::make('volverProtocolo')
            ->label('Volver al protocolo del tipo')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Centro $record): bool => app(ConfiguracionEfectivaCentro::class)->origen($record, 'protocolo') === 'centro'
                && (auth()->user()?->can('update', $record) ?? false))
            ->action(function (Centro $record): void {
                $this->ejecutar(fn () => app(CentroService::class)->actualizar($record, ['protocolo' => null], auth()->user()), 'Rige el protocolo del tipo');
                $this->refreshFormData(['protocolo']);
            });
    }

    /**
     * «Volver al valor del tipo» del horario: el horario vigente del centro pasa a
     * heredar el del tipo y conserva sus parámetros de agenda (§4, RN-06).
     *
     * @return Action
     */
    private function volverAlHorarioDelTipo(): Action
    {
        return Action::make('volverHorario')
            ->label('Volver al horario del tipo')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Centro $record): bool => app(ConfiguracionEfectivaCentro::class)->origen($record, 'horario') === 'centro'
                && (auth()->user()?->can('update', $record) ?? false))
            ->action(function (Centro $record): void {
                // Mismo permiso que el horario propio: administrador o director del centro (§9)
                if (! (auth()->user()?->can('update', $record) ?? false)) {
                    Notification::make()->danger()->title('No tienes permiso para hacer esto')->send();

                    return;
                }

                HorarioCentro::vigenteDelCentro($record->id)?->volverAlTipo();
                Notification::make()->success()->title('Rige el horario del tipo')->send();
            });
    }

    /**
     * «Exclusiones de catálogo»: solo si el tipo lo permite (RN-08). Las marcadas
     * se excluyen y las desmarcadas vuelven al catálogo.
     *
     * @return Action
     */
    private function exclusiones(): Action
    {
        return Action::make('exclusiones')
            ->label('Exclusiones de catálogo')
            ->icon('heroicon-o-funnel')
            ->color('gray')
            ->visible(fn (Centro $record): bool => $record->tipoCentro->permite_restriccion_en_centro
                && (auth()->user()?->can('excluirCatalogo', $record) ?? false))
            ->fillForm(fn (Centro $record): array => collect(ElementoCatalogo::cases())->mapWithKeys(fn (ElementoCatalogo $e) => [
                $e->value => ExclusionCatalogo::where('centro_id', $record->id)->where('elemento_tipo', $e->value)->pluck('elemento_id')->all(),
            ])->all())
            ->form(fn (Centro $record): array => collect(ElementoCatalogo::cases())->map(fn (ElementoCatalogo $e) => CheckboxList::make($e->value)
                ->label(match ($e) {
                    ElementoCatalogo::Prestacion => 'Prestaciones que no ofrece el centro',
                    ElementoCatalogo::TipoPlan => 'Tipos de plan que no se abren en el centro',
                    ElementoCatalogo::TipoFicha => 'Fichas de valoración que no se usan en el centro',
                })
                ->options(CentroResource::opcionesCatalogo($record)[$e->value])
                ->columns(2))->all())
            ->action(function (Centro $record, array $data): void {
                $servicio = app(ExclusionCatalogoService::class);

                $this->ejecutar(fn () => DB::transaction(function () use ($record, $data, $servicio): void {
                    foreach (ElementoCatalogo::cases() as $elemento) {
                        $actuales = ExclusionCatalogo::where('centro_id', $record->id)->where('elemento_tipo', $elemento->value)->pluck('elemento_id')->all();
                        $marcadas = array_map('intval', $data[$elemento->value] ?? []);

                        foreach (array_diff($marcadas, $actuales) as $id) {
                            $servicio->excluir($record, $elemento, $id, auth()->user());
                        }
                        foreach (array_diff($actuales, $marcadas) as $id) {
                            $servicio->quitar($record, $elemento, $id, auth()->user());
                        }
                    }
                }), 'Exclusiones guardadas');
            });
    }

    /**
     * Ejecuta una operación de servicio y notifica el resultado o el rechazo.
     *
     * @param callable(): mixed $operacion
     * @param string $exito
     *
     * @return void
     */
    private function ejecutar(callable $operacion, string $exito): void
    {
        try {
            $operacion();
            Notification::make()->success()->title($exito)->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('No tienes permiso para hacer esto')->send();
        } catch (DomainException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
