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
use Filament\Tables\Table;
use Modules\Agenda\Enums\OrigenPermitidoSlot;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\TipoSlot;

class TipoSlotResource extends Resource
{
    protected static ?string $model = TipoSlot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Tipos de slot';
    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';
    protected static ?string $navigationParentItem = 'Configuración';
    protected static ?string $modelLabel = 'Tipo de slot';
    protected static ?string $pluralModelLabel = 'Tipos de slot';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Horario y nombre')
                ->columns(2)
                ->schema([
                    Select::make('horario_centro_id')
                        ->label('Horario de centro')
                        ->options(fn () => HorarioCentro::with('centro')
                            ->where('activo', true)
                            ->get()
                            ->mapWithKeys(fn (HorarioCentro $h) => [$h->id => "{$h->nombre} — {$h->centro->nombre}"])
                        )
                        ->searchable()
                        ->required(),

                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(200),
                ]),

            Section::make('Configuración')
                ->columns(2)
                ->schema([
                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull(),

                    TextInput::make('duracion_minutos')
                        ->label('Duración (minutos)')
                        ->numeric()
                        ->required()
                        ->minValue(1),

                    TextInput::make('porcentaje_urgencias')
                        ->label('% urgencias')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(100)
                        ->helperText(
                            'Porcentaje de slots de este tipo reservados para urgencias. ' .
                            'Los slots de urgencia son visibles internamente pero no se exponen al canal externo.'
                        ),

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
                        ->helperText('Al cerrar la cita, crea un apunte en la Historia Social del ciudadano.')
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
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('horarioCentro.centro.nombre')
                    ->label('Centro')
                    ->sortable(),

                Tables\Columns\TextColumn::make('horarioCentro.nombre')
                    ->label('Horario')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('duracion_minutos')
                    ->label('Duración')
                    ->formatStateUsing(fn (int $state) => "{$state} min")
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('porcentaje_urgencias')
                    ->label('% urgencias')
                    ->formatStateUsing(fn (int $state) => "{$state}%")
                    ->alignCenter(),

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

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('horario_centro_id')
                    ->label('Horario')
                    ->relationship('horarioCentro', 'nombre'),

                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
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
            'index'  => Pages\ListTiposSlot::route('/'),
            'create' => Pages\CreateTipoSlot::route('/create'),
            'edit'   => Pages\EditTipoSlot::route('/{record}/edit'),
        ];
    }
}
