<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\SegmentoPoblacionResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Centro\Models\SegmentoPoblacion;

/**
 * Recurso Filament para la gestión de segmentos de población.
 */
class SegmentoPoblacionResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = SegmentoPoblacion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Segmentos de población';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Segmento de población';

    protected static ?string $pluralModelLabel = 'Segmentos de población';

    protected static ?int $navigationSort = 1;

    /**
     * Define el formulario de segmentos de población.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del segmento')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(150)
                        ->helperText('Ej: Personas sin hogar, Infancia y adolescencia, Personas mayores...'),

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

    /**
     * Configura el listado de segmentos de población.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Segmento')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('centros_count')
                    ->label('Centros')
                    ->counts('centros')
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

    /**
     * Declara las páginas del catálogo de segmentos de población.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSegmentosPoblacion::route('/'),
            'create' => Pages\CreateSegmentoPoblacion::route('/create'),
            'edit' => Pages\EditSegmentoPoblacion::route('/{record}/edit'),
        ];
    }
}
