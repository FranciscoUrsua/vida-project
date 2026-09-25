<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropuestaEliminacionResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Documentos\Enums\EstadoPropuestaEliminacion;
use Modules\Documentos\Models\PropuestaEliminacion;
use Modules\Documentos\Services\DestruccionDocumentosService;

/**
 * Backoffice: aprobación o rechazo de las propuestas de destrucción de documentos.
 *
 * Solo adm_sistema. Las propuestas las genera `documentos:proponer-destruccion`;
 * aprobar destruye por crypto-shredding y levanta el acta de eliminación. No se
 * crean, editan ni borran desde aquí.
 */
class PropuestaEliminacionResource extends Resource
{
    protected static ?string $model = PropuestaEliminacion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trash';

    protected static ?string $navigationLabel = 'Propuestas de eliminación';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?string $modelLabel = 'Propuesta de eliminación';

    protected static ?string $pluralModelLabel = 'Propuestas de eliminación';

    protected static ?int $navigationSort = 7;

    /**
     * Tabla de propuestas con las acciones de aprobar y rechazar.
     *
     * @param Table $table Tabla base.
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Nº')->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EstadoPropuestaEliminacion $state): string => $state->etiqueta())
                    ->color(fn (EstadoPropuestaEliminacion $state): string => match ($state) {
                        EstadoPropuestaEliminacion::Pendiente => 'warning',
                        EstadoPropuestaEliminacion::Aprobada => 'danger',
                        EstadoPropuestaEliminacion::Rechazada => 'gray',
                    }),
                TextColumn::make('versiones')
                    ->label('Versiones')
                    ->state(fn (PropuestaEliminacion $record): int => count($record->versiones)),
                TextColumn::make('created_at')->label('Propuesta el')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('resuelta_en')->label('Resuelta el')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('acta.numero')->label('Acta')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(collect(EstadoPropuestaEliminacion::cases())
                        ->mapWithKeys(fn (EstadoPropuestaEliminacion $e): array => [$e->value => $e->etiqueta()])),
            ])
            ->actions([
                ViewAction::make(),
                self::accionAprobar(),
                self::accionRechazar(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Acción de aprobación: destruye las versiones que sigan siendo destruibles y levanta el acta.
     *
     * @return Action
     */
    public static function accionAprobar(): Action
    {
        return Action::make('aprobar')
            ->label('Aprobar y destruir')
            ->icon('heroicon-o-fire')
            ->color('danger')
            ->visible(fn (PropuestaEliminacion $record): bool => $record->estaPendiente())
            ->requiresConfirmation()
            ->modalHeading('Aprobar la destrucción')
            ->modalDescription('Las versiones se destruyen de forma irreversible y se levanta un acta. Las que hayan quedado retenidas desde la propuesta se excluyen.')
            ->form([
                Textarea::make('motivo')
                    ->label('Motivo que consta en el acta')
                    ->default(DestruccionDocumentosService::MOTIVO_DEFECTO)
                    ->required()
                    ->maxLength(2000),
            ])
            ->action(function (PropuestaEliminacion $record, array $data): void {
                $resuelta = app(DestruccionDocumentosService::class)->aprobar($record, auth()->user(), $data['motivo']);
                $excluidas = count($resuelta->excluidas ?? []);

                Notification::make()
                    ->success()
                    ->title($resuelta->acta ? "Acta {$resuelta->acta->numero} levantada" : 'No se ha destruido ninguna versión')
                    ->body($excluidas > 0 ? "{$excluidas} versiones excluidas por estar retenidas o ya sin contenido." : null)
                    ->send();
            });
    }

    /**
     * Acción de rechazo: la propuesta se cierra sin destruir nada.
     *
     * @return Action
     */
    public static function accionRechazar(): Action
    {
        return Action::make('rechazar')
            ->label('Rechazar')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->visible(fn (PropuestaEliminacion $record): bool => $record->estaPendiente())
            ->form([
                Textarea::make('observaciones')->label('Motivo del rechazo')->maxLength(2000),
            ])
            ->action(function (PropuestaEliminacion $record, array $data): void {
                app(DestruccionDocumentosService::class)->rechazar($record, auth()->user(), $data['observaciones'] ?? null);

                Notification::make()->success()->title('Propuesta rechazada')->send();
            });
    }

    /**
     * Páginas del recurso: listado y detalle.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropuestasEliminacion::route('/'),
            'view' => Pages\ViewPropuestaEliminacion::route('/{record}'),
        ];
    }

    /**
     * Solo adm_sistema ve las propuestas.
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Las propuestas las crea el comando, nunca el formulario.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Una propuesta no se edita: se aprueba o se rechaza.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Una propuesta no se borra.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
