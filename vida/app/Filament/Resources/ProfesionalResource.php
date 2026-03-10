<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProfesionalResource\Pages;
use App\Models\Profesional;
use App\Models\Titulacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProfesionalResource extends Resource
{
    protected static ?string $model = Profesional::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Profesionales';
    protected static ?string $navigationGroup = 'Personas';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('apellido1')
                    ->label('Primer apellido')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('apellido2')
                    ->label('Segundo apellido')
                    ->maxLength(100),
                Forms\Components\Select::make('sexo')
                    ->label('Sexo')
                    ->options([
                        'H' => 'Hombre',
                        'M' => 'Mujer',
                        'O' => 'Otro',
                    ])
                    ->nullable(),
                Forms\Components\Select::make('tipo_documento')
                    ->label('Tipo de documento')
                    ->options([
                        'DNI'       => 'DNI',
                        'NIE'       => 'NIE',
                        'PASAPORTE' => 'Pasaporte',
                    ])
                    ->nullable(),
                Forms\Components\TextInput::make('numero_id')
                    ->label('Número de documento')
                    ->maxLength(20),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('telefono')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\Select::make('titulacion_id')
                    ->label('Titulación')
                    ->relationship('titulacion', 'nombre')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\Toggle::make('identificacion_validada')
                    ->label('Identificación validada'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('apellido1')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('titulacion.nombre')
                    ->label('Titulación')
                    ->sortable(),
                Tables\Columns\IconColumn::make('identificacion_validada')
                    ->label('ID válida')
                    ->boolean(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProfesionals::route('/'),
            'create' => Pages\CreateProfesional::route('/create'),
            'edit'   => Pages\EditProfesional::route('/{record}/edit'),
        ];
    }
}
