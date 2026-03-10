<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrestacionResource\Pages;
use App\Models\Prestacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PrestacionResource extends Resource
{
    protected static ?string $model = Prestacion::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = 'Prestaciones';
    protected static ?string $navigationGroup = 'Catálogo';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('descripcion')
                    ->label('Descripción')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('categoria')
                    ->label('Categoría')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('subcategoria')
                    ->label('Subcategoría')
                    ->maxLength(255),
                Forms\Components\TextInput::make('nivel')
                    ->label('Nivel')
                    ->maxLength(255),
                Forms\Components\Textarea::make('publico_objetivo')
                    ->label('Público objetivo')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('categoria')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subcategoria')
                    ->label('Subcategoría')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('nivel')
                    ->label('Nivel')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categoria')
                    ->label('Categoría')
                    ->options(fn () => Prestacion::query()
                        ->distinct()
                        ->orderBy('categoria')
                        ->pluck('categoria', 'categoria')
                        ->toArray()
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPrestacions::route('/'),
            'create' => Pages\CreatePrestacion::route('/create'),
            'edit'   => Pages\EditPrestacion::route('/{record}/edit'),
        ];
    }
}
