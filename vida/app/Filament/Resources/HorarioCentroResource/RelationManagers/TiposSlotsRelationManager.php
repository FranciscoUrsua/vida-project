<?php

namespace App\Filament\Resources\HorarioCentroResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Agenda\Enums\OrigenPermitidoSlot;

class TiposSlotsRelationManager extends RelationManager
{
    protected static string $relationship = 'tiposSlot';

    protected static ?string $title = 'Tipos de slot';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del tipo de slot')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull(),

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
                        ->helperText('Porcentaje de slots de este tipo reservados para urgencias internas (no visibles en canal externo).'),

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
                        ->helperText('Si está activo, al cerrar la cita se crea un apunte en la Historia Social.')
                        ->default(false),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),

                Tables\Columns\TextColumn::make('duracion_minutos')
                    ->label('Duración')
                    ->formatStateUsing(fn (int $state) => "{$state} min"),

                Tables\Columns\TextColumn::make('porcentaje_urgencias')
                    ->label('% urgencias')
                    ->formatStateUsing(fn (int $state) => "{$state}%")
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('origen_permitido')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn (OrigenPermitidoSlot $state) => $state->label())
                    ->color(fn (OrigenPermitidoSlot $state) => match ($state) {
                        OrigenPermitidoSlot::Interno => 'gray',
                        OrigenPermitidoSlot::ApiExterna => 'warning',
                        OrigenPermitidoSlot::Ambos => 'success',
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
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
