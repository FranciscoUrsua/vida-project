<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConfiguracionOrganizacionResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Organizacion\Models\Configuracion;
use App\Filament\Concerns\AutorizaGestion;

/**
 * Backoffice: gestión de la configuración general de la organización.
 *
 * Almacena pares clave-valor que controlan el comportamiento de la aplicación.
 *
 * Accesible en /admin/configuracion-organizacion.
 */
class ConfiguracionOrganizacionResource extends Resource
{
    use AutorizaGestion;
    protected static ?string $model = Configuracion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Configuración';
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?string $modelLabel = 'Parámetro';
    protected static ?string $pluralModelLabel = 'Configuración';
    protected static ?string $slug = 'configuracion-organizacion';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Parámetro de configuración')
                ->schema([
                    TextInput::make('clave')
                        ->label('Clave')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true),

                    Select::make('tipo')
                        ->label('Tipo de dato')
                        ->options([
                            'texto'    => 'Texto',
                            'numero'   => 'Número',
                            'booleano' => 'Booleano',
                            'json'     => 'JSON',
                        ])
                        ->required()
                        ->default('texto'),

                    TextInput::make('valor')
                        ->label('Valor')
                        ->required(),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clave')
                    ->label('Clave')
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'texto'    => 'gray',
                        'numero'   => 'info',
                        'booleano' => 'warning',
                        'json'     => 'success',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('valor')
                    ->label('Valor')
                    ->limit(50),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'texto'    => 'Texto',
                        'numero'   => 'Número',
                        'booleano' => 'Booleano',
                        'json'     => 'JSON',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('clave');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListConfiguracion::route('/'),
            'create' => Pages\CreateConfiguracion::route('/create'),
            'edit'   => Pages\EditConfiguracion::route('/{record}/edit'),
        ];
    }
}
