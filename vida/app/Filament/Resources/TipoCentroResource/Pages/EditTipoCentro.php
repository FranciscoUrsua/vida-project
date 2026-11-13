<?php

namespace App\Filament\Resources\TipoCentroResource\Pages;

use App\Filament\Acciones\CentroTipoAcciones;
use App\Filament\Resources\TipoCentroResource;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Services\TipoCentro\TipoCentroService;

/**
 * Página de edición de un tipo de centro. La configuración se guarda por
 * TipoCentroService; la denominación, el horario, los cierres y la baja tienen
 * sus propias acciones, porque van con fecha y no sobrescriben el pasado.
 */
class EditTipoCentro extends EditRecord
{
    protected static string $resource = TipoCentroResource::class;

    /**
     * Acciones con fecha: denominación, horario, cierres y baja.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CentroTipoAcciones::renombrar(),
            $this->programarHorario(),
            CentroTipoAcciones::anadirCierre(),
            CentroTipoAcciones::darDeBaja(),
        ];
    }

    /**
     * Guarda por TipoCentroService solo la configuración que cambia; el código
     * no se envía y la denominación tiene su propia acción.
     *
     * @param Model $record
     * @param array<string, mixed> $data
     *
     * @return Model
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var TipoCentro $record */
        $cambios = array_filter(
            Arr::except($data, ['codigo', 'nombre', 'siglas']),
            // Comparación laxa a propósito: el formulario manda cadenas y el modelo tiene casts
            fn ($valor, string $campo) => $record->isFillable($campo) && self::valor($record->getAttribute($campo)) != $valor,
            ARRAY_FILTER_USE_BOTH,
        );

        return $cambios === [] ? $record : app(TipoCentroService::class)->actualizar($record, $cambios, auth()->user());
    }

    /**
     * Valor comparable con el del formulario (los enums llegan como su valor).
     *
     * @param mixed $valor
     *
     * @return mixed
     */
    private static function valor(mixed $valor): mixed
    {
        return $valor instanceof \BackedEnum ? $valor->value : $valor;
    }

    /**
     * «Programar horario»: el vigente se cierra el día anterior y los centros que
     * heredan aplican el nuevo desde esa fecha (RN-07).
     *
     * @return Action
     */
    private function programarHorario(): Action
    {
        return Action::make('programarHorario')
            ->label('Programar horario')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->visible(fn (TipoCentro $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->form([
                DatePicker::make('desde')->label('En vigor desde')->required()->minDate(today()->addDay())->default(today()->addDay()),
                ...TipoCentroResource::camposHorario(),
            ])
            ->action(function (TipoCentro $record, array $data): void {
                $horario = Arr::except($data, ['desde']);
                $horario['dias_laborables'] = array_map('intval', $horario['dias_laborables']);

                try {
                    app(TipoCentroService::class)->programarHorario($record, $horario, Carbon::parse($data['desde']), auth()->user());
                    Notification::make()->success()->title('Horario programado')->send();
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title('No tienes permiso para hacer esto')->send();
                } catch (DomainException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }
}
