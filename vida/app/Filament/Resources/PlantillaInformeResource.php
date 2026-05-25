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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Models\PlantillaInforme;
use App\Filament\Concerns\AutorizaGestion;

/**
 * Gestión de plantillas de informe profesional.
 *
 * Las plantillas tienen alcance jerárquico: se crean al nivel de UO adecuado
 * y son visibles para todos los profesionales de esa UO y sus descendientes.
 * Accesible solo a usuarios con rol supervisor o admin_sistema.
 */
class PlantillaInformeResource extends Resource
{
    use AutorizaGestion;
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
                        ->options(function () {
                            $user = auth()->user();
                            $base = UnidadOrganizativa::where('activa', true)->orderBy('nombre');
                            if ($user?->hasRole('adm_sistema')) {
                                return $base->pluck('nombre', 'id');
                            }
                            $uoIds = $user?->uoSubtreeIds() ?? [];
                            return $base->whereIn('id', $uoIds)->pluck('nombre', 'id');
                        })
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
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user->hasRole('adm_sistema')) {
                    return;
                }
                $uoIds = $user->uoSubtreeIds();
                if (empty($uoIds)) {
                    $query->whereRaw('1 = 0');
                    return;
                }
                $query->whereIn('unidad_organizativa_id', $uoIds);
            })
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
                DeleteAction::make()
                    ->authorize(function (Model $record) {
                        $user = auth()->user();
                        if (! $user?->hasAnyRole(['adm_sistema', 'adm_usuarios'])) {
                            return false;
                        }
                        if ($user->hasRole('adm_sistema')) {
                            return true;
                        }
                        return in_array($record->unidad_organizativa_id, $user->uoSubtreeIds());
                    }),
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

    /** supervision puede ver plantillas de su subtree (solo lectura); adm_* puede gestionar. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios', 'supervision']) ?? false;
    }

    /** adm_usuarios solo puede editar plantillas de su propio subtree de UO. */
    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (! $user?->hasAnyRole(['adm_sistema', 'adm_usuarios'])) {
            return false;
        }
        if ($user->hasRole('adm_sistema')) {
            return true;
        }
        return in_array($record->unidad_organizativa_id, $user->uoSubtreeIds());
    }

    /** adm_usuarios solo puede borrar plantillas de su propio subtree de UO. */
    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if (! $user?->hasAnyRole(['adm_sistema', 'adm_usuarios'])) {
            return false;
        }
        if ($user->hasRole('adm_sistema')) {
            return true;
        }
        return in_array($record->unidad_organizativa_id, $user->uoSubtreeIds());
    }
}
