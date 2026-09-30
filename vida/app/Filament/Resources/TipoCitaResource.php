<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoCitaResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Agenda\Enums\HerramientaCita;
use Modules\Agenda\Enums\ModalidadCita;
use Modules\Agenda\Models\TipoCita;

/**
 * Backoffice: catálogo global de tipos de cita (docs/modulo-citas.md §2.1).
 *
 * Solo adm_sistema. El código no se puede cambiar cuando hay citas del tipo, y
 * un tipo con citas no se borra: se desactiva. El modelo aplica la misma regla
 * aunque se salte el formulario. Los cambios quedan versionados y auditados.
 */
class TipoCitaResource extends Resource
{
    protected static ?string $model = TipoCita::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Tipos de cita';

    protected static string|\UnitEnum|null $navigationGroup = 'Agenda — Configuración';

    protected static ?string $modelLabel = 'Tipo de cita';

    protected static ?string $pluralModelLabel = 'Tipos de cita';

    protected static ?int $navigationSort = 2;

    /**
     * Formulario del tipo de cita.
     *
     * @param Schema $schema
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        $conCitas = fn (?TipoCita $record): bool => $record !== null && $record->citas()->withTrashed()->exists();

        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(100)
                        ->regex('/^[a-z][a-z0-9_]*$/')
                        ->unique(ignoreRecord: true)
                        ->disabled($conCitas)
                        ->helperText('Minúsculas, números y guiones bajos. No se puede cambiar cuando hay citas del tipo.'),
                    TextInput::make('nombre')
                        ->label('Nombre interno')
                        ->required()
                        ->maxLength(200)
                        ->helperText('Solo lo ven los roles con acceso a la Historia Social.'),
                    TextInput::make('etiqueta_publica')
                        ->label('Etiqueta pública')
                        ->required()
                        ->maxLength(100)
                        ->helperText('Neutra: la ven quien da la cita, el canal externo y los avisos a la persona.'),
                ]),
            Section::make('Atención')
                ->columns(2)
                ->schema([
                    Select::make('herramienta')
                        ->label('Herramienta al atender')
                        ->options(collect(HerramientaCita::cases())->mapWithKeys(fn (HerramientaCita $h) => [$h->value => $h->label()]))
                        ->required(),
                    Select::make('modalidad_defecto')
                        ->label('Modalidad por defecto')
                        ->options(collect(ModalidadCita::cases())->mapWithKeys(fn (ModalidadCita $m) => [$m->value => $m->label()]))
                        ->default(ModalidadCita::Presencial->value)
                        ->required(),
                    Select::make('tiposSlot')
                        ->label('Tipos de slot en los que puede darse')
                        ->relationship('tiposSlot', 'nombre')
                        ->multiple()
                        ->preload(),
                    Toggle::make('requiere_historia_social')->label('Requiere Historia Social'),
                    Toggle::make('activo')->label('Activo')->default(true),
                ]),
        ]);
    }

    /**
     * Listado de tipos de cita.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->sortable()->fontFamily('mono'),
                TextColumn::make('nombre')->label('Nombre interno')->searchable(),
                TextColumn::make('etiqueta_publica')->label('Etiqueta pública'),
                TextColumn::make('herramienta')->label('Herramienta')
                    ->formatStateUsing(fn (HerramientaCita $state): string => $state->label()),
                TextColumn::make('citas_count')->label('Citas')->counts('citas'),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->filters([TernaryFilter::make('activo')->label('Activo')])
            ->actions([
                EditAction::make(),
                DeleteAction::make()->authorize(fn (Model $record) => static::canDelete($record)),
            ])
            ->defaultSort('codigo');
    }

    /**
     * Páginas del recurso.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiposCita::route('/'),
            'create' => Pages\CreateTipoCita::route('/create'),
            'edit' => Pages\EditTipoCita::route('/{record}/edit'),
        ];
    }

    /**
     * Solo adm_sistema ve el catálogo.
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        return self::esAdmSistema();
    }

    /**
     * Solo adm_sistema crea tipos.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return self::esAdmSistema();
    }

    /**
     * Solo adm_sistema edita tipos.
     *
     * @param Model $record
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return self::esAdmSistema();
    }

    /**
     * Solo adm_sistema borra, y solo tipos sin citas (con citas, se desactivan).
     *
     * @param Model $record
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return self::esAdmSistema() && $record instanceof TipoCita && ! $record->citas()->withTrashed()->exists();
    }

    /**
     * @return bool
     */
    private static function esAdmSistema(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }
}
