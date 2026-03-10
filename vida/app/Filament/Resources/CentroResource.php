<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CentroResource\Pages;
use App\Models\Distrito;
use App\Models\UnidadOrganizativa;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Director;
use Modules\Centro\Models\TipoCentro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CentroResource extends Resource
{
    protected static ?string $model = Centro::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Centros';
    protected static ?string $navigationGroup = 'Centros';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información general')
                    ->schema([
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'activo'   => 'Activo',
                                'inactivo' => 'Inactivo',
                            ])
                            ->required()
                            ->default('activo'),
                        Forms\Components\Select::make('tipo_centro_id')
                            ->label('Tipo de centro')
                            ->relationship('tipoCentro', 'nombre')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('unidad_organizativa_id')
                            ->label('Unidad organizativa')
                            ->options(
                                UnidadOrganizativa::query()
                                    ->whereNull('deleted_at')
                                    ->orderBy('nombre_corto')
                                    ->pluck('nombre_corto', 'id')
                            )
                            ->searchable()
                            ->nullable(),
                        Forms\Components\Select::make('distrito_id')
                            ->label('Distrito')
                            ->relationship('distrito', 'nombre')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email_contacto')
                            ->label('Email de contacto')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dirección')
                    ->schema([
                        Forms\Components\TextInput::make('street_type')
                            ->label('Tipo de vía')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('street_name')
                            ->label('Nombre de vía')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('street_number')
                            ->label('Número')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('additional_info')
                            ->label('Información adicional')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('postal_code')
                            ->label('Código postal')
                            ->maxLength(10),
                        Forms\Components\TextInput::make('city')
                            ->label('Ciudad')
                            ->default('Madrid')
                            ->maxLength(255),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Forms\Components\Section::make('Director')
                    ->schema([
                        Forms\Components\Select::make('director_id')
                            ->label('Director actual')
                            ->options(
                                Director::query()
                                    ->with('profesional')
                                    ->whereNull('fecha_baja')
                                    ->whereNull('deleted_at')
                                    ->get()
                                    ->mapWithKeys(fn ($d) => [
                                        $d->id => ($d->profesional
                                            ? "{$d->profesional->nombre} {$d->profesional->apellido1}"
                                            : "Director #{$d->id}"),
                                    ])
                            )
                            ->searchable()
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'success' => 'activo',
                        'danger'  => 'inactivo',
                    ]),
                Tables\Columns\TextColumn::make('tipoCentro.nombre')
                    ->label('Tipo')
                    ->sortable(),
                Tables\Columns\TextColumn::make('distrito.nombre')
                    ->label('Distrito')
                    ->sortable(),
                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email_contacto')
                    ->label('Email')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'activo'   => 'Activo',
                        'inactivo' => 'Inactivo',
                    ]),
                Tables\Filters\SelectFilter::make('tipo_centro_id')
                    ->label('Tipo de centro')
                    ->relationship('tipoCentro', 'nombre'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCentros::route('/'),
            'create' => Pages\CreateCentro::route('/create'),
            'edit'   => Pages\EditCentro::route('/{record}/edit'),
        ];
    }
}
