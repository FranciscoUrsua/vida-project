<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DirectorResource\Pages;
use App\Models\Profesional;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Director;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DirectorResource extends Resource
{
    protected static ?string $model = Director::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Directores';
    protected static ?string $navigationGroup = 'Centros';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('profesional_id')
                    ->label('Profesional')
                    ->options(
                        Profesional::query()
                            ->whereNull('deleted_at')
                            ->orderBy('apellido1')
                            ->get()
                            ->mapWithKeys(fn ($p) => [$p->id => "{$p->apellido1}, {$p->nombre}"])
                    )
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('centro_id')
                    ->label('Centro')
                    ->relationship('centro', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DatePicker::make('fecha_alta')
                    ->label('Fecha de alta')
                    ->required()
                    ->displayFormat('d/m/Y'),
                Forms\Components\DatePicker::make('fecha_baja')
                    ->label('Fecha de baja')
                    ->displayFormat('d/m/Y')
                    ->nullable()
                    ->helperText('Dejar vacío si sigue activo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('profesional.nombre')
                    ->label('Profesional')
                    ->formatStateUsing(fn ($state, $record) => $record->profesional
                        ? "{$record->profesional->apellido1}, {$record->profesional->nombre}"
                        : '-')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('centro.nombre')
                    ->label('Centro')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('fecha_alta')
                    ->label('Alta')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_baja')
                    ->label('Baja')
                    ->date('d/m/Y')
                    ->placeholder('Activo')
                    ->sortable(),
                Tables\Columns\IconColumn::make('es_actual')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('activos')
                    ->label('Solo activos')
                    ->query(fn (Builder $query) => $query->whereNull('fecha_baja')),
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
            'index'  => Pages\ListDirectors::route('/'),
            'create' => Pages\CreateDirector::route('/create'),
            'edit'   => Pages\EditDirector::route('/{record}/edit'),
        ];
    }
}
