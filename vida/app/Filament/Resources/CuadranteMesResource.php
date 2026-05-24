<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CuadranteMesResource\Pages;
use App\Filament\Resources\CuadranteMesResource\RelationManagers\LineasCuadranteRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Centro\Models\Centro;

class CuadranteMesResource extends Resource
{
    protected static ?string $model = CuadranteMes::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Cuadrantes mensuales';
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?string $navigationParentItem = 'Supervisión';
    protected static ?string $modelLabel = 'Cuadrante mensual';
    protected static ?string $pluralModelLabel = 'Cuadrantes mensuales';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Centro y período')
                ->columns(3)
                ->schema([
                    Select::make('centro_id')
                        ->label('Centro')
                        ->options(fn () => Centro::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()
                        ->required(),

                    TextInput::make('anyo')
                        ->label('Año')
                        ->numeric()
                        ->required()
                        ->default(now()->year)
                        ->minValue(2020)
                        ->maxValue(2099),

                    Select::make('mes')
                        ->label('Mes')
                        ->options([
                            1  => 'Enero',
                            2  => 'Febrero',
                            3  => 'Marzo',
                            4  => 'Abril',
                            5  => 'Mayo',
                            6  => 'Junio',
                            7  => 'Julio',
                            8  => 'Agosto',
                            9  => 'Septiembre',
                            10 => 'Octubre',
                            11 => 'Noviembre',
                            12 => 'Diciembre',
                        ])
                        ->required()
                        ->default(now()->month),
                ]),

            Section::make('Estado')
                ->schema([
                    Select::make('estado')
                        ->label('Estado')
                        ->options(collect(EstadoCuadrante::cases())->mapWithKeys(
                            fn (EstadoCuadrante $e) => [$e->value => $e->label()]
                        ))
                        ->required()
                        ->default(EstadoCuadrante::Borrador->value),

                    Textarea::make('notas')
                        ->label('Notas')
                        ->rows(3)
                        ->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $mesesEs = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('centro.nombre')
                    ->label('Centro')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('mes_anyo')
                    ->label('Período')
                    ->getStateUsing(fn (CuadranteMes $r) => ($mesesEs[$r->mes] ?? $r->mes) . ' ' . $r->anyo)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('anyo', $direction)->orderBy('mes', $direction)),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EstadoCuadrante $state) => $state->label())
                    ->color(fn (EstadoCuadrante $state) => match ($state) {
                        EstadoCuadrante::Borrador  => 'gray',
                        EstadoCuadrante::Revision  => 'warning',
                        EstadoCuadrante::Publicado => 'success',
                    }),

                Tables\Columns\IconColumn::make('generado_con_ia')
                    ->label('IA')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('generado_automaticamente')
                    ->label('Auto.')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('publicado_en')
                    ->label('Publicado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('publicadoPor.name')
                    ->label('Publicado por')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('centro_id')
                    ->label('Centro')
                    ->relationship('centro', 'nombre'),

                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(collect(EstadoCuadrante::cases())->mapWithKeys(
                        fn (EstadoCuadrante $e) => [$e->value => $e->label()]
                    )),
            ])
            ->actions([
                Action::make('publicar')
                    ->label('Publicar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CuadranteMes $record) => in_array($record->estado, [
                        EstadoCuadrante::Borrador,
                        EstadoCuadrante::Revision,
                    ]))
                    ->requiresConfirmation()
                    ->modalHeading('Publicar cuadrante')
                    ->modalDescription('Al publicar el cuadrante, quedará visible para el equipo. Esta acción no materializa los slots todavía (pendiente de implementación del SlotMaterializadorService).')
                    ->action(function (CuadranteMes $record) {
                        $record->update([
                            'estado'           => EstadoCuadrante::Publicado->value,
                            'publicado_en'     => now(),
                            'publicado_por_id' => auth()->id(),
                        ]);
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('anyo', 'desc');
    }

    public static function getRelationManagers(): array
    {
        return [
            LineasCuadranteRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCuadrantesMes::route('/'),
            'create' => Pages\CreateCuadranteMes::route('/create'),
            'edit'   => Pages\EditCuadranteMes::route('/{record}/edit'),
        ];
    }
}
