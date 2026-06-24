<?php

namespace App\Filament\Resources\TipoPlanResource\Pages;

use App\Filament\Resources\TipoPlanResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Modules\Intervencion\Models\IndicadorCatalogo;
use Modules\Intervencion\Models\ObjetivoCatalogo;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\TipoPlan;

/**
 * Página de gestión del catálogo de objetivos de un tipo de plan.
 *
 * Permite crear, editar y eliminar objetivos (generales y específicos)
 * para un tipo de plan concreto, incluyendo el indicador de medición asociado.
 */
class GestionarObjetivos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = TipoPlanResource::class;

    protected string $view = 'intervencion::filament.tipo-plan.gestionar-objetivos';

    public TipoPlan $record;

    /**
     * Título dinámico con el nombre del tipo de plan.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return "Objetivos: {$this->record->nombre}";
    }

    /**
     * Configuración completa de la tabla de objetivos del catálogo.
     *
     * @param  Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        $tipoPlanId = $this->record->id;

        return $table
            ->query(
                ObjetivoCatalogo::where('tipo_plan_id', $tipoPlanId)
                    ->orderBy('nivel')
                    ->orderBy('orden')
            )
            ->columns([
                TextColumn::make('nivel')
                    ->label('Nivel')
                    ->badge()
                    ->color(fn ($state) => $state === 'general' ? 'primary' : 'gray'),

                TextColumn::make('tipoFicha.nombre')
                    ->label('Área temática')
                    ->placeholder('—')
                    ->description(fn ($record) => $record->nivel === 'especifico'
                        ? 'Área de la ficha'
                        : 'Sin área (objetivo general)'
                    ),

                TextColumn::make('texto')
                    ->label('Texto')
                    ->limit(60)
                    ->searchable(),

                TextColumn::make('indicador.descripcion')
                    ->label('Indicador')
                    ->limit(40)
                    ->placeholder('Sin indicador'),

                TextColumn::make('indicador.tipo_valoracion')
                    ->label('Tipo valoración')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'conseguido_proceso_no'           => 'C/P/N',
                        'favorable_mantiene_desfavorable' => 'F/M/D',
                        'si_no'                           => 'Sí/No',
                        default                           => '—',
                    })
                    ->placeholder('—'),

                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Crear objetivo')
                    ->model(ObjetivoCatalogo::class)
                    ->form([
                        Select::make('nivel')
                            ->options(['general' => 'General', 'especifico' => 'Específico'])
                            ->required()
                            ->live(),

                        Select::make('tipo_ficha_id')
                            ->label('Área temática (tipo de ficha)')
                            ->options(fn () => TipoFicha::activos()->pluck('nombre', 'id'))
                            ->searchable()
                            ->nullable()
                            ->visible(fn (Get $get) => $get('nivel') === 'especifico')
                            ->helperText('El área determina qué fichas activan la propuesta de este objetivo.'),

                        Textarea::make('texto')
                            ->label('Texto del objetivo')
                            ->required()
                            ->rows(2),

                        TextInput::make('orden')->numeric()->default(0),
                        Toggle::make('activo')->default(true),

                        Section::make('Indicador asociado')
                            ->schema([
                                Textarea::make('indicador_descripcion')
                                    ->label('Qué se mide / indicador')
                                    ->required()
                                    ->rows(2),
                                Select::make('indicador_tipo_valoracion')
                                    ->label('Tipo de valoración')
                                    ->options([
                                        'conseguido_proceso_no'           => 'Conseguido / En proceso / No conseguido',
                                        'favorable_mantiene_desfavorable' => 'Favorable / Se mantiene / Desfavorable',
                                        'si_no'                           => 'Sí / No',
                                    ])
                                    ->default('conseguido_proceso_no')
                                    ->required(),
                            ])
                            ->columns(1),
                    ])
                    ->using(function (array $data) use ($tipoPlanId) {
                        $indicadorDesc = $data['indicador_descripcion'];
                        $indicadorTipo = $data['indicador_tipo_valoracion'];
                        unset($data['indicador_descripcion'], $data['indicador_tipo_valoracion']);

                        $objetivo = ObjetivoCatalogo::create(array_merge(
                            $data,
                            ['tipo_plan_id' => $tipoPlanId]
                        ));

                        IndicadorCatalogo::create([
                            'objetivo_catalogo_id' => $objetivo->id,
                            'descripcion'          => $indicadorDesc,
                            'tipo_valoracion'      => $indicadorTipo,
                        ]);

                        return $objetivo;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        Textarea::make('texto')
                            ->required()
                            ->rows(2),

                        TextInput::make('orden')
                            ->numeric(),

                        Toggle::make('activo'),
                    ]),

                DeleteAction::make(),
            ]);
    }
}
