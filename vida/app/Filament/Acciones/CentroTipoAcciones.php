<?php

namespace App\Filament\Acciones;

use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use LogicException;
use Modules\Centro\Enums\TipoCierre;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\CierreCalendario;
use Modules\Centro\Models\Denominacion;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Services\TipoCentro\CentroService;
use Modules\Centro\Services\TipoCentro\CierreCalendarioService;
use Modules\Centro\Services\TipoCentro\TipoCentroService;

/**
 * Acciones y fragmentos de Filament comunes a tipos de centro y centros:
 * programar una denominación, añadir un cierre de calendario, dar de baja y
 * mostrar historiales. Todo pasa por los servicios de Tipo de Centro, que
 * aplican las policies en el servidor (docs/modulo-tipo-centro.md §9).
 */
final class CentroTipoAcciones
{
    /**
     * «Programar nueva denominación» (§8.1): la vigente se cierra el día anterior.
     *
     * @return Action
     */
    public static function renombrar(): Action
    {
        return Action::make('renombrar')
            ->label('Programar nueva denominación')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (TipoCentro|Centro $record): bool => auth()->user()?->can('renombrar', $record) ?? false)
            ->form(fn (TipoCentro|Centro $record) => [
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(200),
                TextInput::make('corto')
                    ->label($record instanceof TipoCentro ? 'Siglas' : 'Nombre corto')
                    ->required()
                    ->maxLength(50),
                DatePicker::make('desde')->label('En vigor desde')->required()->minDate(today()->addDay())->default(today()->addDay()),
            ])
            ->action(fn (TipoCentro|Centro $record, array $data) => self::ejecutar(
                fn () => $record instanceof TipoCentro
                    ? app(TipoCentroService::class)->renombrar($record, $data['nombre'], $data['corto'], Carbon::parse($data['desde']), auth()->user())
                    : app(CentroService::class)->renombrar($record, $data['nombre'], $data['corto'], Carbon::parse($data['desde']), auth()->user()),
                'Denominación programada',
            ));
    }

    /**
     * «Añadir cierre» de calendario: comunes del tipo o propios del centro (§5.2).
     * Avisa de las citas confirmadas afectadas, que no se cancelan.
     *
     * @return Action
     */
    public static function anadirCierre(): Action
    {
        return Action::make('anadirCierre')
            ->label('Añadir cierre')
            ->icon('heroicon-o-calendar-days')
            ->color('gray')
            ->visible(fn (TipoCentro|Centro $record): bool => auth()->user()?->can('gestionarCalendario', $record) ?? false)
            ->form([
                Select::make('tipo')
                    ->label('Tipo')
                    ->options(collect(TipoCierre::cases())->mapWithKeys(fn (TipoCierre $t) => [$t->value => $t->label()]))
                    ->default(TipoCierre::Cierre->value)
                    ->required()
                    ->live(),
                DatePicker::make('fecha_desde')->label('Desde')->required(),
                DatePicker::make('fecha_hasta')->label('Hasta')->required()->afterOrEqual('fecha_desde'),
                TimePicker::make('hora_inicio')->label('Abre a las')->seconds(false)
                    ->visible(fn (Get $get) => $get('tipo') === TipoCierre::HorarioReducido->value)
                    ->required(fn (Get $get) => $get('tipo') === TipoCierre::HorarioReducido->value),
                TimePicker::make('hora_fin')->label('Cierra a las')->seconds(false)
                    ->visible(fn (Get $get) => $get('tipo') === TipoCierre::HorarioReducido->value)
                    ->required(fn (Get $get) => $get('tipo') === TipoCierre::HorarioReducido->value),
                TextInput::make('motivo')->label('Motivo')->required()->maxLength(200),
            ])
            ->action(function (TipoCentro|Centro $record, array $data): void {
                try {
                    $resultado = app(CierreCalendarioService::class)->crear($record, $data, auth()->user());
                } catch (AuthorizationException $e) {
                    Notification::make()->danger()->title('No puedes añadir cierres aquí')->send();

                    return;
                }

                $aviso = Notification::make()->success()->title('Cierre añadido');
                if ($resultado['avisos'] !== []) {
                    // Un cierre no cancela citas (TF-TCE-19): se avisa para reasignarlas a mano
                    $aviso->warning()->body(implode('. ', $resultado['avisos']).'. Las citas no se cancelan: revísalas.')->persistent();
                }
                $aviso->send();
            });
    }

    /**
     * «Dar de baja» un tipo (sin centros activos, RN-11) o un centro. No borra nada.
     *
     * @return Action
     */
    public static function darDeBaja(): Action
    {
        return Action::make('darDeBaja')
            ->label('Dar de baja')
            ->icon('heroicon-o-archive-box-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Queda de baja desde la fecha indicada y se conserva con todo su historial. No se puede deshacer desde aquí.')
            ->visible(fn (TipoCentro|Centro $record): bool => $record->activo && (auth()->user()?->can('darDeBaja', $record) ?? false))
            ->form([
                DatePicker::make('fecha')->label('Fecha de baja')->required()->default(today()),
            ])
            ->action(fn (TipoCentro|Centro $record, array $data) => self::ejecutar(
                fn () => $record instanceof TipoCentro
                    ? app(TipoCentroService::class)->darDeBaja($record, Carbon::parse($data['fecha']), auth()->user())
                    : app(CentroService::class)->darDeBaja($record, Carbon::parse($data['fecha']), auth()->user()),
                'Dado de baja',
            ));
    }

    /**
     * Historial de denominaciones, de la más reciente a la más antigua.
     *
     * @param TipoCentro|Centro|null $record
     *
     * @return HtmlString|string
     */
    public static function historialDenominaciones(TipoCentro|Centro|null $record): HtmlString|string
    {
        if ($record === null) {
            return '—';
        }

        return self::lineas($record->denominaciones()->get()->reverse()->map(fn (Denominacion $d) => sprintf(
            '%s (%s) · %s',
            $d->nombre,
            $d->nombre_corto,
            self::vigencia($d->vigente_desde, $d->vigente_hasta),
        )));
    }

    /**
     * Cierres de un titular, de los próximos a los pasados recientes.
     *
     * @param TipoCentro|Centro|null $titular
     *
     * @return HtmlString|string
     */
    public static function cierres(TipoCentro|Centro|null $titular): HtmlString|string
    {
        if ($titular === null) {
            return '—';
        }

        $cierres = CierreCalendario::where('titular_type', $titular->getMorphClass())
            ->where('titular_id', $titular->getKey())
            ->whereDate('fecha_hasta', '>=', today()->subMonths(3))
            ->orderBy('fecha_desde')
            ->get();

        return self::lineas($cierres->map(fn (CierreCalendario $c) => sprintf(
            '%s · %s%s · %s',
            $c->fecha_desde->equalTo($c->fecha_hasta) ? $c->fecha_desde->format('d/m/Y') : $c->fecha_desde->format('d/m/Y').'–'.$c->fecha_hasta->format('d/m/Y'),
            $c->tipo->label(),
            $c->tipo === TipoCierre::HorarioReducido ? ' '.substr((string) $c->hora_inicio, 0, 5).'–'.substr((string) $c->hora_fin, 0, 5) : '',
            $c->motivo,
        )));
    }

    /**
     * Texto de vigencia «desde 01/01/2026» o «01/01/2026 – 31/12/2026».
     *
     * @param Carbon $desde
     * @param Carbon|null $hasta
     *
     * @return string
     */
    public static function vigencia(Carbon $desde, ?Carbon $hasta): string
    {
        return $hasta === null ? 'desde '.$desde->format('d/m/Y') : $desde->format('d/m/Y').' – '.$hasta->format('d/m/Y');
    }

    /**
     * Una línea por elemento, escapada; «—» si no hay ninguno.
     *
     * @param iterable<string> $lineas
     *
     * @return HtmlString|string
     */
    public static function lineas(iterable $lineas): HtmlString|string
    {
        $escapadas = collect($lineas)->map(fn (string $l) => e($l));

        return $escapadas->isEmpty() ? '—' : new HtmlString($escapadas->implode('<br>'));
    }

    /**
     * Ejecuta una operación de servicio y notifica el resultado o el rechazo.
     *
     * @param callable(): mixed $operacion
     * @param string $exito
     *
     * @return void
     */
    private static function ejecutar(callable $operacion, string $exito): void
    {
        try {
            $operacion();
            Notification::make()->success()->title($exito)->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('No tienes permiso para hacer esto')->send();
        } catch (DomainException|LogicException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
