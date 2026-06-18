<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\ColectivoProtegidoResource\Pages;
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
use Modules\Organizacion\Models\ColectivoProtegido;

/**
 * Backoffice: gestión del catálogo de colectivos especialmente protegidos.
 *
 * Accesible en /admin/colectivos-protegidos.
 */
class ColectivoProtegidoResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = ColectivoProtegido::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Colectivos protegidos';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Colectivo protegido';

    protected static ?string $pluralModelLabel = 'Colectivos protegidos';

    protected static ?string $slug = 'colectivos-protegidos';

    protected static ?int $navigationSort = 2;

    /**
     * Define el formulario de colectivos protegidos.
     *
     * @param Schema $schema Esquema base.
     *
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del colectivo')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(150),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(3),

                    Toggle::make('requiere_aprobacion_previa')
                        ->label('Requiere aprobación previa de acceso')
                        ->helperText('Si está activado, cualquier acceso desde fuera de la UO responsable requerirá aprobación del supervisor.')
                        ->default(true),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * Define la tabla de colectivos protegidos.
     *
     * @param Table $table Tabla base.
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Colectivo')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('requiere_aprobacion_previa')
                    ->label('Aprobación previa')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
                Tables\Filters\TernaryFilter::make('requiere_aprobacion_previa')->label('Aprobación previa'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Define las páginas del recurso de colectivos protegidos.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListColectivosProtegidos::route('/'),
            'create' => Pages\CreateColectivoProtegido::route('/create'),
            'edit' => Pages\EditColectivoProtegido::route('/{record}/edit'),
        ];
    }
}
