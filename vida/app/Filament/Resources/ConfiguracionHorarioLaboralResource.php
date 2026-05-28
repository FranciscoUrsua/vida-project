<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConfiguracionHorarioLaboralResource\Pages;
use App\Models\CatalogoSistema;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TimePicker;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Concerns\AutorizaGestion;

/**
 * Recurso de configuración del horario laboral por defecto.
 *
 * Edita el registro 'horario_laboral_defecto' en catalogos_sistema.
 * Este horario se usa para calcular los vencimientos de alertas del
 * sistema hasta que el módulo de Agenda esté disponible.
 */
class ConfiguracionHorarioLaboralResource extends Resource
{
    use AutorizaGestion;
    protected static ?string $model = CatalogoSistema::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'Horario laboral';
    protected static ?string $modelLabel = 'Configuración de horario laboral';
    protected static ?string $pluralModelLabel = 'Configuración de horario laboral';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Horario laboral por defecto')
                ->description('Este horario se usa para calcular vencimientos de alertas hasta que el módulo de Agenda esté disponible.')
                ->schema([
                    Placeholder::make('aviso')
                        ->label('')
                        ->content('⚠️ Este horario es el utilizado por el sistema para determinar si han transcurrido las 4 horas laborales de plazo de reconocimiento de alertas. Modifíquelo con precaución.'),

                    TimePicker::make('hora_inicio')
                        ->label('Hora de inicio')
                        ->required()
                        ->seconds(false),

                    TimePicker::make('hora_fin')
                        ->label('Hora de fin')
                        ->required()
                        ->seconds(false),

                    CheckboxList::make('dias_semana')
                        ->label('Días laborables')
                        ->options([
                            1 => 'Lunes',
                            2 => 'Martes',
                            3 => 'Miércoles',
                            4 => 'Jueves',
                            5 => 'Viernes',
                            6 => 'Sábado',
                            7 => 'Domingo',
                        ])
                        ->columns(4)
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clave')
                    ->label('Clave'),
                Tables\Columns\TextColumn::make('etiqueta')
                    ->label('Descripción'),
                Tables\Columns\TextColumn::make('valor')
                    ->label('Valor JSON')
                    ->limit(80),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    /**
     * Solo muestra el registro del horario laboral.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('grupo', 'sistema.configuracion')
            ->where('clave', 'horario_laboral_defecto');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConfiguracionHorarioLaboral::route('/'),
            'edit'  => Pages\EditConfiguracionHorarioLaboral::route('/{record}/edit'),
        ];
    }
}
