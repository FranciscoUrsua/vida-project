<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CargoResource\Pages;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Usuarios\Models\Cargo;

/**
 * Backoffice: gestión del catálogo de cargos profesionales.
 *
 * Accesible en /admin/cargos.
 */
class CargoResource extends Resource
{
    protected static ?string $model = Cargo::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Cargos';
    protected static string|\UnitEnum|null $navigationGroup = 'Profesionales';
    protected static ?string $modelLabel = 'Cargo';
    protected static ?string $pluralModelLabel = 'Cargos';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del cargo')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre del cargo')
                        ->required()
                        ->maxLength(150),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->nullable(),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('profesionales_count')
                    ->label('Profesionales')
                    ->counts('profesionales')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCargos::route('/'),
            'create' => Pages\CreateCargo::route('/create'),
            'edit'   => Pages\EditCargo::route('/{record}/edit'),
        ];
    }
}
