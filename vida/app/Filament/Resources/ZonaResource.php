<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZonaResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Organizacion\Models\Zona;

/**
 * Backoffice: gestión del catálogo de zonas territoriales.
 *
 * Accesible en /admin/zonas.
 */
class ZonaResource extends Resource
{
    protected static ?string $model = Zona::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Zonas';
    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?string $modelLabel = 'Zona';
    protected static ?string $pluralModelLabel = 'Zonas';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos de la zona')
                ->schema([
                    Select::make('distrito_id')
                        ->label('Distrito')
                        ->relationship('distrito', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(3),

                    Toggle::make('activa')
                        ->label('Activa')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('distrito.nombre')
                    ->label('Distrito')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Zona')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('distrito_id')
                    ->label('Distrito')
                    ->relationship('distrito', 'nombre'),
                Tables\Filters\TernaryFilter::make('activa')->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('distrito.nombre');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListZonas::route('/'),
            'create' => Pages\CreateZona::route('/create'),
            'edit'   => Pages\EditZona::route('/{record}/edit'),
        ];
    }
}
