<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoCentroResource\Pages;
use Modules\Centro\Models\TipoCentro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TipoCentroResource extends Resource
{
    protected static ?string $model = TipoCentro::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Tipos de Centro';
    protected static ?string $navigationGroup = 'Centros';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('descripcion')
                    ->label('Descripción')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('tiene_plazas')
                    ->label('Tiene plazas')
                    ->reactive(),
                Forms\Components\TextInput::make('numero_plazas')
                    ->label('Número de plazas')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (Forms\Get $get) => $get('tiene_plazas')),
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
                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('tiene_plazas')
                    ->label('Plazas')
                    ->boolean(),
                Tables\Columns\TextColumn::make('numero_plazas')
                    ->label('Nº plazas')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('centros_count')
                    ->label('Centros')
                    ->counts('centros')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
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
            'index'  => Pages\ListTipoCentros::route('/'),
            'create' => Pages\CreateTipoCentro::route('/create'),
            'edit'   => Pages\EditTipoCentro::route('/{record}/edit'),
        ];
    }
}
