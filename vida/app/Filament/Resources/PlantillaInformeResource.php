<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlantillaInformeResource\Pages;
use App\Models\UnidadOrganizativa;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Models\PlantillaInforme;

/**
 * Gestión de plantillas de informe profesional.
 *
 * Las plantillas tienen alcance jerárquico: se crean al nivel de UO adecuado
 * y son visibles para todos los profesionales de esa UO y sus descendientes.
 * Accesible solo a usuarios con rol supervisor o admin_sistema.
 */
class PlantillaInformeResource extends Resource
{
    protected static ?string $model = PlantillaInforme::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Plantillas de informe';
    protected static string|\UnitEnum|null $navigationGroup = 'Informes y plantillas';
    protected static ?string $modelLabel = 'Plantilla de informe';
    protected static ?string $pluralModelLabel = 'Plantillas de informe';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre de la plantilla')
                        ->required()
                        ->maxLength(200)
                        ->placeholder('Ej: Informe Social de Valoración')
                        ->columnSpanFull(),

                    Select::make('tipo_informe')
                        ->label('Tipo de informe')
                        ->options(collect(TipoInforme::cases())->mapWithKeys(
                            fn (TipoInforme $t) => [$t->value => $t->label()]
                        ))
                        ->required(),

                    Select::make('unidad_organizativa_id')
                        ->label('Unidad Organizativa de alcance')
                        ->options(fn () => UnidadOrganizativa::where('activa', true)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText('La plantilla estará disponible para esta UO y todas sus descendientes.'),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull()
                        ->helperText('Texto de ayuda que verá el profesional en el selector de plantillas.'),
                ]),

            Section::make('Secciones del informe')
                ->schema([
                    Textarea::make('secciones')
                        ->label('Secciones (JSON)')
                        ->rows(12)
                        ->required()
                        ->helperText(
                            'Array JSON de secciones. Tipos: "automatico" (con "fuente") y "texto_libre" (con "instrucciones" y "obligatorio"). ' .
                            'Ejemplo: [{"id":"valoracion","titulo":"Valoración","tipo":"texto_libre","instrucciones":"...","obligatorio":true}]'
                        ),
                ]),

            Section::make('Estado')
                ->schema([
                    Toggle::make('activa')
                        ->label('Activa')
                        ->default(false)
                        ->helperText('Solo las plantillas activas aparecen en el selector operativo.'),
                ]),
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

                Tables\Columns\TextColumn::make('tipo_informe')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (TipoInforme $state) => $state->label())
                    ->color(fn (TipoInforme $state) => match ($state) {
                        TipoInforme::InformeSocial      => 'info',
                        TipoInforme::InformePsicologico => 'warning',
                        TipoInforme::InformeJuridico    => 'primary',
                        TipoInforme::Otro               => 'gray',
                    }),

                Tables\Columns\TextColumn::make('unidadOrganizativa.nombre')
                    ->label('UO de alcance')
                    ->sortable(),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_informe')
                    ->label('Tipo')
                    ->options(collect(TipoInforme::cases())->mapWithKeys(
                        fn (TipoInforme $t) => [$t->value => $t->label()]
                    )),

                Tables\Filters\SelectFilter::make('unidad_organizativa_id')
                    ->label('UO')
                    ->relationship('unidadOrganizativa', 'nombre'),

                Tables\Filters\TernaryFilter::make('activa')->label('Estado'),
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
            'index'  => Pages\ListPlantillasInforme::route('/'),
            'create' => Pages\CreatePlantillaInforme::route('/create'),
            'edit'   => Pages\EditPlantillaInforme::route('/{record}/edit'),
        ];
    }
}
