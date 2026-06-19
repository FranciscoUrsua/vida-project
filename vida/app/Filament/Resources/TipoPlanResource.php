<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\TipoPlanResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Intervencion\Models\TipoPlan;

/**
 * Recurso Filament para gestionar los tipos de plan del sistema.
 *
 * Los tipos del seeder (eliminable=false) no pueden borrarse desde la UI.
 * El slug es inmutable una vez creado el tipo.
 */
class TipoPlanResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = TipoPlan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Tipos de plan';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Tipo de plan';

    protected static ?string $pluralModelLabel = 'Tipos de plan';

    protected static ?int $navigationSort = 10;

    /**
     * Formulario de creación y edición de tipos de plan.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->maxLength(60)
                        ->helperText('Identificador estable. No editable una vez creado.')
                        ->disabled(fn ($record) => $record !== null),

                    TextInput::make('nombre')
                        ->label('Nombre del plan')
                        ->required()
                        ->maxLength(120),

                    Select::make('ambito')
                        ->label('Ámbito')
                        ->options([
                            'asp' => 'Atención Social Primaria',
                            'especializado' => 'Atención especializada',
                        ])
                        ->required(),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->columnSpanFull()
                        ->nullable(),
                ]),
        ]);
    }

    /**
     * Tabla de listado de tipos de plan.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->label('Slug')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('ambito')
                    ->label('Ámbito')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'asp' ? 'ASP' : 'Especializado')
                    ->color(fn ($state) => $state === 'asp' ? 'primary' : 'warning'),

                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),

                IconColumn::make('eliminable')
                    ->label('Del sistema')
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil')
                    ->trueColor('gray')
                    ->falseColor('success'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('objetivos')
                    ->label('Objetivos')
                    ->icon('heroicon-o-list-bullet')
                    ->url(fn ($record) => static::getUrl('objetivos', ['record' => $record])),
                DeleteAction::make()
                    ->visible(fn ($record) => $record->eliminable),
            ]);
    }

    /**
     * Restringe el borrado a tipos eliminables con rol adm_sistema.
     *
     * @param mixed $record
     */
    public static function canDelete($record): bool
    {
        return $record->eliminable && (auth()->user()?->hasRole('adm_sistema') ?? false);
    }

    /**
     * Páginas del recurso.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoPlanes::route('/'),
            'create' => Pages\CreateTipoPlan::route('/create'),
            'edit' => Pages\EditTipoPlan::route('/{record}/edit'),
            'objetivos' => Pages\GestionarObjetivos::route('/{record}/objetivos'),
        ];
    }
}
