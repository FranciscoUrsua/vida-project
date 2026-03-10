<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SocialUserResource\Pages;
use App\Models\Country;
use App\Models\Distrito;
use App\Models\Profesional;
use App\Models\Region;
use App\Models\SocialUser;
use Modules\Centro\Models\Centro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SocialUserResource extends Resource
{
    protected static ?string $model = SocialUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Beneficiarios';
    protected static ?string $navigationGroup = 'Personas';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos personales')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name1')
                            ->label('Primer apellido')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name2')
                            ->label('Segundo apellido')
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('fecha_nacimiento')
                            ->label('Fecha de nacimiento')
                            ->displayFormat('d/m/Y'),
                        Forms\Components\Select::make('sexo')
                            ->label('Sexo')
                            ->options([
                                'H' => 'Hombre',
                                'M' => 'Mujer',
                                'O' => 'Otro / No especificado',
                            ])
                            ->nullable(),
                        Forms\Components\Select::make('estado_civil')
                            ->label('Estado civil')
                            ->options([
                                'soltero'   => 'Soltero/a',
                                'casado'    => 'Casado/a',
                                'divorciado'=> 'Divorciado/a',
                                'viudo'     => 'Viudo/a',
                                'separado'  => 'Separado/a',
                                'otro'      => 'Otro',
                            ])
                            ->nullable(),
                        Forms\Components\Select::make('pais_origen_id')
                            ->label('País de origen')
                            ->relationship('paisOrigen', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\Select::make('region_id')
                            ->label('Región de origen')
                            ->relationship('region', 'name')
                            ->searchable()
                            ->nullable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Identificación')
                    ->schema([
                        Forms\Components\Toggle::make('identificacion_desconocida')
                            ->label('Identidad desconocida')
                            ->reactive(),
                        Forms\Components\Select::make('tipo_documento')
                            ->label('Tipo de documento')
                            ->options([
                                'DNI'       => 'DNI',
                                'NIE'       => 'NIE',
                                'PASAPORTE' => 'Pasaporte',
                                'OTRO'      => 'Otro',
                            ])
                            ->hidden(fn (Forms\Get $get) => $get('identificacion_desconocida'))
                            ->nullable(),
                        Forms\Components\TextInput::make('numero_id')
                            ->label('Número de documento')
                            ->maxLength(50)
                            ->hidden(fn (Forms\Get $get) => $get('identificacion_desconocida')),
                        Forms\Components\TextInput::make('numero_tarjeta_sanitaria')
                            ->label('Tarjeta sanitaria')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('correo')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(50),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dirección')
                    ->schema([
                        Forms\Components\TextInput::make('street_type')
                            ->label('Tipo de vía')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('street_name')
                            ->label('Nombre de vía')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('street_number')
                            ->label('Número')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('additional_info')
                            ->label('Info adicional')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('postal_code')
                            ->label('Código postal')
                            ->maxLength(10),
                        Forms\Components\Select::make('distrito_id')
                            ->label('Distrito')
                            ->relationship('distrito', 'nombre')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\TextInput::make('lugar_empadronamiento')
                            ->label('Lugar de empadronamiento')
                            ->maxLength(255),
                        Forms\Components\Select::make('situacion_administrativa')
                            ->label('Situación administrativa')
                            ->options([
                                'activa'    => 'Activa',
                                'inactiva'  => 'Inactiva',
                                'pendiente' => 'Pendiente',
                            ])
                            ->default('activa'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Asignación')
                    ->schema([
                        Forms\Components\Select::make('centro_adscripcion_id')
                            ->label('Centro de adscripción')
                            ->relationship('centroAdscripcion', 'nombre')
                            ->searchable()
                            ->nullable(),
                        Forms\Components\Select::make('profesional_referencia_id')
                            ->label('Profesional de referencia')
                            ->options(
                                Profesional::query()
                                    ->whereNull('deleted_at')
                                    ->orderBy('apellido1')
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [$p->id => "{$p->apellido1}, {$p->nombre}"])
                            )
                            ->searchable()
                            ->nullable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Representante legal')
                    ->schema([
                        Forms\Components\Toggle::make('tiene_representante_legal')
                            ->label('Tiene representante legal')
                            ->reactive(),
                        Forms\Components\Select::make('representante_legal_id')
                            ->label('Representante legal')
                            ->relationship('representanteLegal', 'first_name')
                            ->searchable()
                            ->hidden(fn (Forms\Get $get) => ! $get('tiene_representante_legal'))
                            ->nullable(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Acceso especial')
                    ->schema([
                        Forms\Components\Toggle::make('requiere_permiso_especial')
                            ->label('Requiere permiso especial')
                            ->helperText('Menores, víctimas de violencia doméstica u otros casos protegidos'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Nota: first_name, last_name1, last_name2 están cifrados — no se puede hacer
                // búsqueda a nivel de BD; el cifrado es transparente para mostrar datos.
                Tables\Columns\TextColumn::make('last_name1')
                    ->label('Apellido 1')
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Nombre')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_nacimiento')
                    ->label('F. Nacimiento')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tipo_documento')
                    ->label('Documento')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('distrito.nombre')
                    ->label('Distrito')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('requiere_permiso_especial')
                    ->label('Protegido')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('situacion_administrativa')
                    ->label('Situación')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('distrito_id')
                    ->label('Distrito')
                    ->relationship('distrito', 'nombre'),
                Tables\Filters\Filter::make('requiere_permiso_especial')
                    ->label('Solo casos protegidos')
                    ->query(fn (Builder $query) => $query->where('requiere_permiso_especial', true)),
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
            'index'  => Pages\ListSocialUsers::route('/'),
            'create' => Pages\CreateSocialUser::route('/create'),
            'edit'   => Pages\EditSocialUser::route('/{record}/edit'),
        ];
    }
}
