<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InformeResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\Action as TableAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\ServicioFirmaInforme;
use App\Filament\Concerns\AutorizaGestion;

/**
 * Supervisión y gestión operativa de informes profesionales.
 *
 * Los informes se crean desde el expediente del ciudadano (flujo Livewire, pendiente).
 * Este recurso permite a supervisores y administradores consultar el estado de todos
 * los informes y ejecutar la acción de anulación cuando proceda.
 */
class InformeResource extends Resource
{
    use AutorizaGestion;
    protected static ?string $model = Informe::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Informes';
    protected static string|\UnitEnum|null $navigationGroup = 'Informes y plantillas';
    protected static ?string $modelLabel = 'Informe';
    protected static ?string $pluralModelLabel = 'Informes';
    protected static ?int $navigationSort = 30;

    /** Los informes se crean desde el flujo operativo (Livewire), no desde el backoffice. */

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Ciudadano y plantilla')
                ->columns(2)
                ->schema([
                    TextEntry::make('ciudadano.nombre')
                        ->label('Nombre')
                        ->placeholder('—'),

                    TextEntry::make('ciudadano.apellido1')
                        ->label('Primer apellido')
                        ->placeholder('—'),

                    TextEntry::make('plantilla.nombre')
                        ->label('Plantilla')
                        ->columnSpanFull(),

                    TextEntry::make('plantilla.tipo_informe')
                        ->label('Tipo de informe')
                        ->formatStateUsing(fn (TipoInforme $state) => $state->label())
                        ->badge()
                        ->color(fn (TipoInforme $state) => match ($state) {
                            TipoInforme::InformeSocial      => 'info',
                            TipoInforme::InformePsicologico => 'warning',
                            TipoInforme::InformeJuridico    => 'primary',
                            TipoInforme::Otro               => 'gray',
                        }),
                ]),

            Section::make('Estado y firma')
                ->columns(2)
                ->schema([
                    TextEntry::make('estado')
                        ->label('Estado')
                        ->formatStateUsing(fn (EstadoInforme $state) => $state->label())
                        ->badge()
                        ->color(fn (EstadoInforme $state) => match ($state) {
                            EstadoInforme::Borrador => 'warning',
                            EstadoInforme::Firmado  => 'success',
                            EstadoInforme::Anulado  => 'danger',
                        }),

                    TextEntry::make('autor.name')
                        ->label('Autor'),

                    TextEntry::make('firmado_en')
                        ->label('Firmado el')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),

                    TextEntry::make('metodo_firma')
                        ->label('Método de firma')
                        ->formatStateUsing(fn ($state) => $state?->label() ?? '—')
                        ->placeholder('—'),

                    TextEntry::make('numero_colegiado_firmante')
                        ->label('Núm. colegiado firmante')
                        ->placeholder('—'),
                ]),

            Section::make('Anulación')
                ->hidden(fn (Informe $record) => ! $record->estaAnulado())
                ->columns(2)
                ->schema([
                    TextEntry::make('motivo_anulacion')
                        ->label('Motivo de anulación')
                        ->columnSpanFull(),

                    TextEntry::make('anulado_en')
                        ->label('Anulado el')
                        ->dateTime('d/m/Y H:i'),
                ]),

            Section::make('Contenido del informe')
                ->collapsed()
                ->schema([
                    TextEntry::make('contenido')
                        ->label('Secciones (JSON)')
                        ->columnSpanFull()
                        ->formatStateUsing(fn ($state) => is_array($state)
                            ? new HtmlString(
                                '<pre style="white-space:pre-wrap;font-size:.8em;overflow-x:auto">'
                                . e(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                . '</pre>'
                            )
                            : '—'
                        ),
                ]),
        ]);
    }

    /** supervision puede ver informes de su subtree (solo lectura); adm_* puede gestionar. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios', 'supervision']) ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user->hasAnyRole(['adm_sistema', 'adm_usuarios'])) {
                    return;
                }
                // supervision: solo informes cuya plantilla pertenece a su subtree de UO
                $uoIds = $user->uoSubtreeIds();
                if (empty($uoIds)) {
                    $query->whereRaw('1 = 0');
                    return;
                }
                $query->whereHas('plantilla', fn (Builder $q) => $q->whereIn('unidad_organizativa_id', $uoIds));
            })
            ->columns([
                Tables\Columns\TextColumn::make('ciudadano.nombre')
                    ->label('Nombre')
                    ->sortable(false)
                    ->searchable(false),

                Tables\Columns\TextColumn::make('ciudadano.apellido1')
                    ->label('Apellido')
                    ->sortable(false)
                    ->searchable(false)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('plantilla.nombre')
                    ->label('Plantilla')
                    ->limit(35)
                    ->sortable(),

                Tables\Columns\TextColumn::make('plantilla.tipo_informe')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (TipoInforme $state) => $state->label())
                    ->color(fn (TipoInforme $state) => match ($state) {
                        TipoInforme::InformeSocial      => 'info',
                        TipoInforme::InformePsicologico => 'warning',
                        TipoInforme::InformeJuridico    => 'primary',
                        TipoInforme::Otro               => 'gray',
                    }),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EstadoInforme $state) => $state->label())
                    ->color(fn (EstadoInforme $state) => match ($state) {
                        EstadoInforme::Borrador => 'warning',
                        EstadoInforme::Firmado  => 'success',
                        EstadoInforme::Anulado  => 'danger',
                    }),

                Tables\Columns\TextColumn::make('autor.name')
                    ->label('Autor')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('firmado_en')
                    ->label('Firmado')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(collect(EstadoInforme::cases())->mapWithKeys(
                        fn (EstadoInforme $e) => [$e->value => $e->label()]
                    )),

                Filter::make('tipo_informe')
                    ->label('Tipo de informe')
                    ->form([
                        Select::make('tipo_informe')
                            ->label('Tipo')
                            ->options(collect(TipoInforme::cases())->mapWithKeys(
                                fn (TipoInforme $t) => [$t->value => $t->label()]
                            ))
                            ->placeholder('Todos'),
                    ])
                    ->query(fn ($query, array $data) => $query->when(
                        $data['tipo_informe'] ?? null,
                        fn ($q, $tipo) => $q->whereHas(
                            'plantilla',
                            fn ($p) => $p->where('tipo_informe', $tipo)
                        )
                    ))
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['tipo_informe'] ?? null)) {
                            return null;
                        }
                        return 'Tipo: ' . TipoInforme::from($data['tipo_informe'])->label();
                    }),

                Tables\Filters\SelectFilter::make('autor_id')
                    ->label('Autor')
                    ->relationship('autor', 'name'),
            ])
            ->actions([
                ViewAction::make(),

                TableAction::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Informe $record) => $record->estaFirmado())
                    ->authorize(fn () => auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false)
                    ->form([
                        Textarea::make('motivo')
                            ->label('Motivo de anulación')
                            ->required()
                            ->minLength(10)
                            ->rows(3),
                    ])
                    ->action(function (Informe $record, array $data, TableAction $action): void {
                        try {
                            app(ServicioFirmaInforme::class)->anular(
                                $record,
                                auth()->id(),
                                $data['motivo']
                            );
                            $action->successNotificationTitle('Informe anulado correctamente.');
                            $action->sendSuccessNotification();
                        } catch (\DomainException $e) {
                            $action->failureNotificationTitle($e->getMessage());
                            $action->sendFailureNotification();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInformes::route('/'),
            'view'  => Pages\ViewInforme::route('/{record}'),
        ];
    }
}
