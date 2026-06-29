<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoSlotResource\Pages;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Agenda\Enums\OrigenPermitidoSlot;
use Modules\Agenda\Models\TipoSlot;

/**
 * Backoffice: gestión del catálogo global de tipos de slot.
 *
 * Los tipos de slot son un catálogo del sistema compartido por todos los centros,
 * análogo a tipos de espacio, cargos o titulaciones.
 * Accesible en /admin/tipos-slot. Solo para supervisores y administradores.
 */
class TipoSlotResource extends Resource
{
    protected static ?string $model = TipoSlot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Tipos de slot';

    protected static string|\UnitEnum|null $navigationGroup = 'Agenda — Configuración';

    protected static ?string $modelLabel = 'Tipo de slot';

    protected static ?string $pluralModelLabel = 'Tipos de slot';

    protected static ?int $navigationSort = 1;

    /**
     * Indica si el usuario puede ver el listado de tipos de slot.
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['supervision', 'adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Indica si el usuario puede crear un tipo de slot.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['supervision', 'adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Indica si el usuario puede editar un tipo de slot.
     *
     * @param Model $record
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['supervision', 'adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Indica si el usuario puede eliminar un tipo de slot.
     *
     * @param Model $record
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['supervision', 'adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Define el formulario de creación y edición de tipos de slot.
     *
     * @param Schema $schema
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del tipo de slot')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100)
                        ->columnSpanFull()
                        ->placeholder('Ej: Entrevista de seguimiento'),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull(),

                    Select::make('duracion_minutos')
                        ->label('Duración')
                        ->options([
                            15  => '15 min',
                            30  => '30 min',
                            45  => '45 min',
                            60  => '1 hora',
                            90  => '1 h 30 min',
                            120 => '2 horas',
                            150 => '2 h 30 min',
                            180 => '3 horas',
                        ])
                        ->required(),

                    TextInput::make('porcentaje_urgencias')
                        ->label('% urgencias')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(100)
                        ->helperText('Slots de este tipo reservados para urgencias internas (no visibles externamente).'),

                    Select::make('origen_permitido')
                        ->label('Origen permitido')
                        ->options(collect(OrigenPermitidoSlot::cases())->mapWithKeys(
                            fn (OrigenPermitidoSlot $o) => [$o->value => $o->label()]
                        ))
                        ->required()
                        ->default(OrigenPermitidoSlot::Ambos->value),

                    Toggle::make('requiere_espacio')
                        ->label('Requiere espacio físico')
                        ->default(false),

                    Toggle::make('genera_apunte_automatico')
                        ->label('Genera apunte automático')
                        ->helperText('Al cerrar la cita se crea un apunte en la Historia Social.')
                        ->default(false),

                    Toggle::make('bloquea_todos_convocados')
                        ->label('Bloquea a todos los convocados')
                        ->helperText('Al usar este tipo en la semana tipo bloqueará el hueco en todos los profesionales del centro.')
                        ->default(false),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * Define la tabla de listado de tipos de slot.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duracion_minutos')
                    ->label('Duración')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('origen_permitido')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn (OrigenPermitidoSlot $state) => $state->label())
                    ->color(fn (OrigenPermitidoSlot $state) => match ($state) {
                        OrigenPermitidoSlot::Interno    => 'gray',
                        OrigenPermitidoSlot::ApiExterna => 'warning',
                        OrigenPermitidoSlot::Ambos      => 'success',
                    }),

                Tables\Columns\IconColumn::make('genera_apunte_automatico')
                    ->label('Apunte auto.')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('bloquea_todos_convocados')
                    ->label('Bloquea a todos')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Define las páginas del recurso de tipos de slot.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTiposSlot::route('/'),
            'create' => Pages\CreateTipoSlot::route('/create'),
            'edit'   => Pages\EditTipoSlot::route('/{record}/edit'),
        ];
    }
}
