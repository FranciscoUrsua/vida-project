<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UsuarioResource\Pages;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Usuarios\Models\Profesional;
use Spatie\Permission\Models\Role;

/**
 * Resource Filament para gestionar Usuarios del sistema.
 *
 * Permite crear usuarios, asignarles roles globales y adscribirlos
 * a Unidades Organizativas con rol y tipo de vínculo.
 *
 * Accesible en /admin/usuarios.
 */
class UsuarioResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static string|\UnitEnum|null $navigationGroup = 'Usuarios y Profesionales';

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?int $navigationSort = 2;

    /**
     * Define el formulario de usuarios.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Perfil profesional')
                ->description('Vincula esta cuenta con el perfil organizativo del profesional. Déjalo vacío para perfiles estrictamente técnicos sin función asistencial.')
                ->schema([
                    Select::make('profesional_id')
                        ->label('Profesional vinculado')
                        ->options(
                            fn () => Profesional::activos()
                                ->orderBy('apellido1')
                                ->get()
                                ->mapWithKeys(fn (Profesional $p) => [
                                    $p->id => trim("{$p->apellido1} {$p->apellido2}, {$p->nombre}"),
                                ])
                        )
                        ->searchable()
                        ->nullable()
                        ->placeholder('Sin profesional vinculado (perfil técnico)')
                        ->helperText('Solo los perfiles técnicos sin función asistencial (adm_sistema) pueden no tener profesional.'),
                ]),

            Section::make('Datos de acceso')
                ->schema([
                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->hint(fn (string $operation) => $operation === 'edit' ? 'Deja en blanco para no cambiarla' : ''),
                ]),

            Section::make('Roles globales')
                ->description('Los roles globales se aplican sin restricción de UO. Para ámbitos concretos, usa la sección de adscripciones.')
                ->schema([
                    CheckboxList::make('roles')
                        ->label('Roles asignados')
                        ->relationship('roles', 'name')
                        ->columns(2)
                        ->required()
                        ->validationMessages([
                            'required' => 'El usuario debe tener al menos un rol asignado.',
                        ])
                        ->helperText('Si no estás seguro, asigna "Consulta básica" como mínimo.')
                        ->default(fn () => Role::where('name', 'consulta_basica')->pluck('id')->toArray()),
                ]),

            Section::make('Adscripciones a Unidades Organizativas')
                ->description('Cada adscripción vincula al usuario con una UO y un tipo de vínculo laboral. Los roles del usuario aplican globalmente; la UO define dónde tiene acceso completo.')
                ->schema([
                    Repeater::make('adscripciones')
                        ->relationship('adscripciones')
                        ->label('')
                        ->schema([
                            Select::make('unidad_organizativa_id')
                                ->label('Unidad Organizativa')
                                ->options(fn () => UnidadOrganizativa::activas()->orderBy('nombre')->pluck('nombre', 'id'))
                                ->searchable()
                                ->required(),

                            Select::make('tipo_vinculo')
                                ->label('Tipo de vínculo')
                                ->options([
                                    'interno' => 'Interno (funcionario / laboral)',
                                    'contratado' => 'Contratado (empresa externa)',
                                    'externo' => 'Externo (colaboración puntual)',
                                ])
                                ->default('interno')
                                ->required(),

                            DatePicker::make('fecha_inicio')
                                ->label('Fecha de inicio')
                                ->required()
                                ->default(now()),

                            DatePicker::make('fecha_fin')
                                ->label('Fecha de fin')
                                ->placeholder('Sin fecha de fin (vigente)')
                                ->nullable(),
                        ])
                        ->columns(2)
                        ->addActionLabel('+ Añadir adscripción')
                        ->defaultItems(0),
                ]),
        ]);
    }

    /**
     * Configura el listado de usuarios.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('profesional.nombre_completo')
                    ->label('Profesional')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),

                Tables\Columns\TextColumn::make('adscripcionesVigentes_count')
                    ->label('UO activas')
                    ->counts('adscripcionesVigentes')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->authorize(fn () => auth()->user()?->hasRole('adm_sistema') ?? false),
            ])
            ->defaultSort('name');
    }

    /**
     * Declara las páginas del catálogo de usuarios.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsuarios::route('/'),
            'create' => Pages\CreateUsuario::route('/create'),
            'edit' => Pages\EditUsuario::route('/{record}/edit'),
        ];
    }

    /**
     * Determina si el usuario puede ver el listado de usuarios.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Determina si el usuario puede crear usuarios.
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Determina si el usuario puede editar el registro.
     *
     * @param Model $record Registro objetivo.
     */
    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (! $user?->hasAnyRole(['adm_sistema', 'adm_usuarios'])) {
            return false;
        }
        if ($user->hasRole('adm_sistema')) {
            return true;
        }
        // adm_usuarios: solo puede editar usuarios adscritos a su subtree de UO
        $uoIds = $user->uoSubtreeIds();

        return $record->adscripcionesVigentes()->whereIn('unidad_organizativa_id', $uoIds)->exists();
    }

    /**
     * Determina si el usuario puede eliminar usuarios.
     *
     * @param Model $record Registro objetivo.
     */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }
}
