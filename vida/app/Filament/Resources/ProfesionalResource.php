<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProfesionalResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\Titulacion;
use Modules\Usuarios\Models\TipoRelacionProfesional;

/**
 * Backoffice: gestión de profesionales.
 *
 * Un Profesional es la entidad raíz del sistema de usuarios:
 * el perfil organizativo de una persona con independencia de si
 * tiene cuenta de acceso.
 *
 * Accesible en /admin/profesionales.
 */
class ProfesionalResource extends Resource
{
    protected static ?string $model = Profesional::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Profesionales';
    protected static string|\UnitEnum|null $navigationGroup = 'Organización';
    protected static ?string $modelLabel = 'Profesional';
    protected static ?string $pluralModelLabel = 'Profesionales';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Datos personales')
                ->columns(3)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('apellido1')
                        ->label('Primer apellido')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('apellido2')
                        ->label('Segundo apellido')
                        ->maxLength(100)
                        ->nullable(),

                    Select::make('sexo')
                        ->label('Sexo')
                        ->options([
                            'M' => 'Masculino',
                            'F' => 'Femenino',
                            'D' => 'No especificado',
                        ])
                        ->required(),
                ]),

            Section::make('Datos profesionales')
                ->columns(2)
                ->schema([
                    Select::make('cargo_id')
                        ->label('Cargo')
                        ->options(fn () => Cargo::activos()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()
                        ->required(),

                    Select::make('categoria_profesional')
                        ->label('Categoría profesional')
                        ->options([
                            'A1' => 'A1 — Técnico Superior',
                            'A2' => 'A2 — Técnico Medio',
                            'C1' => 'C1 — Administrativo',
                            'C2' => 'C2 — Auxiliar Administrativo',
                            'E'  => 'E — Operario',
                        ])
                        ->nullable()
                        ->placeholder('Sin categoría asignada'),

                    Select::make('titulacion_id')
                        ->label('Titulación académica')
                        ->options(fn () => Titulacion::activas()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()
                        ->nullable()
                        ->placeholder('Sin titulación registrada'),

                    Select::make('tipo_relacion_id')
                        ->label('Tipo de relación')
                        ->options(fn () => TipoRelacionProfesional::activos()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()
                        ->required()
                        ->live(),

                    TextInput::make('organizacion')
                        ->label('Organización externa')
                        ->helperText('Nombre de la empresa o entidad contratada.')
                        ->maxLength(200)
                        ->nullable()
                        ->visible(function (Get $get): bool {
                            $tipoId = $get('tipo_relacion_id');
                            if (! $tipoId) {
                                return false;
                            }
                            $tipo = TipoRelacionProfesional::find($tipoId);
                            return $tipo?->es_externo ?? false;
                        }),
                ]),

            Section::make('Contacto profesional')
                ->columns(3)
                ->schema([
                    TextInput::make('email_profesional')
                        ->label('Correo profesional')
                        ->email()
                        ->maxLength(255)
                        ->nullable(),

                    TextInput::make('telefono_profesional')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(20)
                        ->nullable(),

                    TextInput::make('extension')
                        ->label('Extensión')
                        ->maxLength(10)
                        ->nullable(),
                ]),

            Section::make('Vigencia')
                ->columns(3)
                ->schema([
                    DatePicker::make('fecha_inicio')
                        ->label('Fecha de incorporación')
                        ->required()
                        ->default(now()),

                    DatePicker::make('fecha_fin')
                        ->label('Fecha de baja')
                        ->placeholder('En activo')
                        ->nullable(),

                    Toggle::make('activo')
                        ->label('En activo')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->getStateUsing(fn (Profesional $record) => $record->nombre_completo)
                    ->searchable(['nombre', 'apellido1', 'apellido2'])
                    ->sortable(['apellido1', 'nombre']),

                Tables\Columns\TextColumn::make('cargo.nombre')
                    ->label('Cargo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipoRelacion.nombre')
                    ->label('Vínculo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Cuenta de acceso')
                    ->placeholder('Sin cuenta')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),

                Tables\Filters\SelectFilter::make('cargo_id')
                    ->label('Cargo')
                    ->relationship('cargo', 'nombre'),

                Tables\Filters\SelectFilter::make('tipo_relacion_id')
                    ->label('Tipo de relación')
                    ->relationship('tipoRelacion', 'nombre'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('apellido1');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProfesionales::route('/'),
            'create' => Pages\CreateProfesional::route('/create'),
            'edit'   => Pages\EditProfesional::route('/{record}/edit'),
        ];
    }
}
