<?php

namespace App\Filament\Resources;

use App\Filament\Acciones\DesactivarAction;
use App\Filament\Resources\UsuarioResource\Pages;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Services\RolesSugeridosService;
use Spatie\Permission\Models\Role;

/**
 * Resource Filament para gestionar Usuarios del sistema.
 *
 * Permite crear usuarios, asignarles roles globales y adscribirlos
 * a Unidades Organizativas con rol y tipo de vínculo.
 *
 * En el alta, el selector de roles se pre-rellena con los roles sugeridos del
 * cargo del profesional (sección 2.9). La sugerencia no otorga permisos: los
 * roles que se guardan son los que adm deja marcados, y se asignan por el flujo
 * supervisado de AsignacionRolesService.
 *
 * Los usuarios no se borran (RN-20): «Desactivar» les quita el acceso y exige
 * que no tengan casos ni citas pendientes (RN-22). Las adscripciones tampoco se
 * borran: se cierran con fecha de fin, y cerrar la de un centro exige RN-21.
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

            Callout::make('El cargo ha cambiado')
                ->description(fn (?User $record): string => self::textoAvisoCambioCargo($record))
                ->warning()
                ->visible(fn (?User $record, string $operation): bool => $operation === 'edit'
                    && $record !== null
                    && app(RolesSugeridosService::class)->avisoCambioCargo($record) !== null)
                ->columnSpanFull(),

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
                        ->helperText('Solo los perfiles técnicos sin función asistencial (adm_sistema) pueden no tener profesional. Si aún no existe, créalo con el botón «+».')
                        // Alta rápida del profesional sin salir del formulario; al crearlo queda
                        // seleccionado y dispara afterStateUpdated (pre-relleno de roles sugeridos).
                        ->createOptionForm(ProfesionalResource::camposFormulario())
                        ->createOptionUsing(fn (array $data): int => Profesional::create($data)->getKey())
                        ->createOptionModalHeading('Nuevo profesional')
                        ->createOptionAction(fn (Action $action): Action => $action
                            ->tooltip('Crear profesional')
                            ->visible(ProfesionalResource::canCreate()))
                        ->live()
                        // Solo en el alta: los roles sugeridos del cargo sustituyen la selección
                        // actual. En la edición cambiar el profesional nunca toca los roles.
                        ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                            if ($operation !== 'create') {
                                return;
                            }

                            $sugeridos = app(RolesSugeridosService::class)->paraProfesional($state !== null ? (int) $state : null);
                            $set('roles', Role::whereIn('name', $sugeridos)->orderBy('id')->pluck('id')->all());
                        }),
                ]),

            Section::make('Datos de acceso')
                ->schema([
                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->required()
                        // El índice único de BD excluye a los usuarios con soft
                        // delete (ver migración); la validación debe hacer lo mismo,
                        // si no un email libre por borrado seguiría bloqueado aquí.
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
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
                    // Sin ->relationship(): los cambios se guardan en las páginas de alta y
                    // edición a través de AsignacionRolesService, para que dejen historial en
                    // usuario_rol y sigan la supervisión de 2.8 (aprobación previa o alerta).
                    CheckboxList::make('roles')
                        ->label('Roles asignados')
                        ->options(fn () => Role::orderBy('id')->pluck('name', 'id'))
                        ->descriptions(fn (?User $record) => $record === null ? [] : $record->rolesPendientes()
                            ->pluck('rol_id')
                            ->mapWithKeys(fn (int $rolId) => [$rolId => 'Pendiente de aprobación'])
                            ->all())
                        ->columns(2)
                        ->required()
                        ->validationMessages([
                            'required' => 'El usuario debe tener al menos un rol asignado.',
                        ])
                        ->helperText('Al elegir el profesional se proponen los roles sugeridos para su cargo; revísalos antes de guardar. Supervisión y administración del sistema requieren aprobación previa.'),
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
                        // El historial de adscripciones no se borra: se cierra con fecha de fin
                        ->deletable(false)
                        ->defaultItems(0),
                ]),
        ]);
    }

    /**
     * Texto del aviso de cambio de cargo con los roles sugeridos del nuevo cargo.
     *
     * @param User|null $record Usuario en edición.
     */
    private static function textoAvisoCambioCargo(?User $record): string
    {
        $aviso = $record === null ? null : app(RolesSugeridosService::class)->avisoCambioCargo($record);

        if ($aviso === null) {
            return '';
        }

        $roles = $aviso['roles'] === [] ? 'ninguno' : implode(', ', $aviso['roles']);

        return "Nuevo cargo: {$aviso['cargo']}. Roles sugeridos para el nuevo cargo: {$roles}. "
            .'Revisa si procede ajustarlos. Los roles actuales no se han modificado.';
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

                Tables\Columns\TextColumn::make('desactivado_en')
                    ->label('Desactivado')
                    ->dateTime('d/m/Y')
                    ->badge()
                    ->color('danger')
                    ->placeholder('—')
                    ->sortable(),

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

                Tables\Filters\TernaryFilter::make('desactivado')
                    ->label('Desactivados')
                    ->placeholder('Solo activos')
                    ->trueLabel('Solo desactivados')
                    ->falseLabel('Todos')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('desactivado_en'),
                        false: fn ($query) => $query,
                        blank: fn ($query) => $query->whereNull('desactivado_en'),
                    ),
            ])
            ->actions([
                EditAction::make(),
                // Sustituye al borrado (RN-20); no se ofrece sobre el propio usuario
                DesactivarAction::usuario(),
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
     * Nadie puede editar su propio usuario desde el backoffice: podría darse o
     * quitarse roles sin supervisión (sección 2.8) o dejarse sin acceso.
     *
     * @param Model $record Registro objetivo.
     */
    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (! $user?->hasAnyRole(['adm_sistema', 'adm_usuarios'])) {
            return false;
        }
        if (self::esElPropioUsuario($record)) {
            return false;
        }
        if ($user->hasRole('adm_sistema')) {
            return true;
        }
        // adm_usuarios: solo puede editar usuarios adscritos a su subtree de UO
        if (! $record instanceof User) {
            return false;
        }

        $uoIds = $user->uoSubtreeIds();

        return $record->adscripcionesVigentes()->whereIn('unidad_organizativa_id', $uoIds)->exists();
    }

    /**
     * Los usuarios no se borran, ni siquiera con borrado lógico (RN-20): se
     * desactivan con DesactivarAction.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Tampoco el borrado masivo (RN-20).
     *
     * @return bool
     */
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Respuesta de autorización para editar, usada por la página y las acciones de Filament.
     *
     * En Filament 5 las acciones consultan este método, no canEdit(): se delega
     * en canEdit() para que ambas vías apliquen las mismas reglas.
     *
     * @param Model $record Registro objetivo.
     */
    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (self::esElPropioUsuario($record)) {
            return Response::deny('No puedes modificar tu propio usuario desde el backoffice.');
        }

        return static::canEdit($record) ? Response::allow() : Response::deny();
    }

    /**
     * Respuesta de autorización para borrar: siempre denegada (RN-20).
     *
     * @param Model $record Registro objetivo.
     *
     * @return Response
     */
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Los usuarios no se borran: se desactivan.');
    }

    /**
     * Indica si el registro es el usuario autenticado.
     *
     * @param Model $record Registro objetivo.
     */
    private static function esElPropioUsuario(Model $record): bool
    {
        return $record instanceof User && $record->getKey() === auth()->id();
    }
}
