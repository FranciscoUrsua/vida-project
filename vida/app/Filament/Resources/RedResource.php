<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\RedResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Red;

class RedResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = Red::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Redes';

    protected static string|\UnitEnum|null $navigationGroup = 'Centros y Servicios';

    protected static ?string $modelLabel = 'Red';

    protected static ?string $pluralModelLabel = 'Redes';

    protected static ?int $navigationSort = 3;

    /**
     * Configura el formulario de redes.
     *
     * @param Schema $schema Schema Filament a configurar.
     * @return Schema Schema configurado.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Datos de la red')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull(),

                    TextInput::make('nombre_corto')
                        ->label('Nombre corto')
                        ->maxLength(60)
                        ->nullable()
                        ->helperText('Abreviatura usada en listados.'),

                    Toggle::make('activa')
                        ->label('Activa')
                        ->default(true),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(3)
                        ->nullable()
                        ->columnSpanFull(),
                ]),

            Section::make('Centros miembros')
                ->schema([
                    CheckboxList::make('centros')
                        ->label('Centros que forman parte de esta red')
                        ->relationship('centros', 'nombre')
                        ->options(fn () => Centro::activos()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->columns(2),
                ]),

        ]);
    }

    /**
     * supervision puede ver redes cuyo ambito incluye su subtree de UO.
     *
     * @return bool True si el rol puede consultar redes.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios', 'supervision']) ?? false;
    }

    /**
     * Configura la tabla de redes.
     *
     * @param Table $table Tabla Filament a configurar.
     * @return Table Tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->description(fn (Red $record) => $record->nombre_corto)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('centros_count')
                    ->label('Centros')
                    ->counts('centros')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activa')->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Devuelve las paginas registradas para el recurso.
     *
     * @return array<string, mixed> Rutas de paginas Filament.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedes::route('/'),
            'create' => Pages\CreateRed::route('/create'),
            'edit' => Pages\EditRed::route('/{record}/edit'),
        ];
    }
}
