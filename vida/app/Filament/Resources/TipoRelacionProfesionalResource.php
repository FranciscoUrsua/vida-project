<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoRelacionProfesionalResource\Pages;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Usuarios\Models\TipoRelacionProfesional;

/**
 * Backoffice: gestión del catálogo de tipos de relación profesional.
 *
 * Accesible en /admin/tipos-relacion-profesional.
 */
class TipoRelacionProfesionalResource extends Resource
{
    protected static ?string $model = TipoRelacionProfesional::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Tipos de relación';
    protected static string|\UnitEnum|null $navigationGroup = 'Profesionales';
    protected static ?string $modelLabel = 'Tipo de relación';
    protected static ?string $pluralModelLabel = 'Tipos de relación';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del tipo de relación')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),

                    Toggle::make('es_externo')
                        ->label('Es personal externo')
                        ->helperText('Si está activo, el campo "Organización" del profesional será relevante.')
                        ->default(false),

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
                    ->label('Tipo de relación')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('es_externo')
                    ->label('Externo')
                    ->boolean()
                    ->alignCenter(),

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
                Tables\Filters\TernaryFilter::make('es_externo')->label('Externo'),
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
            'index'  => Pages\ListTiposRelacion::route('/'),
            'create' => Pages\CreateTipoRelacion::route('/create'),
            'edit'   => Pages\EditTipoRelacion::route('/{record}/edit'),
        ];
    }
}
