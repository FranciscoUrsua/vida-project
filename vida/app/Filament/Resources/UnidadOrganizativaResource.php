<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnidadOrganizativaResource\Pages;
use App\Models\UnidadOrganizativa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UnidadOrganizativaResource extends Resource
{
    protected static ?string $model = UnidadOrganizativa::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Unidades Organizativas';
    protected static ?string $navigationGroup = 'Administración';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('codigo')
                    ->label('Código')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('nombre_corto')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('descripcion')
                    ->label('Descripción')
                    ->columnSpanFull(),
                Forms\Components\Select::make('parent_id')
                    ->label('Unidad superior')
                    ->relationship('parent', 'nombre_corto')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Dejar vacío si es raíz del árbol'),
                Forms\Components\Toggle::make('activa')
                    ->label('Activa')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nombre_corto')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.nombre_corto')
                    ->label('Unidad superior')
                    ->placeholder('(raíz)')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nivel')
                    ->label('Nivel')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
            'index'  => Pages\ListUnidadOrganizativas::route('/'),
            'create' => Pages\CreateUnidadOrganizativa::route('/create'),
            'edit'   => Pages\EditUnidadOrganizativa::route('/{record}/edit'),
        ];
    }
}
