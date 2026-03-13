<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConfiguracionRolResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Usuarios\Models\ConfiguracionRol;
use Spatie\Permission\Models\Role;

/**
 * Backoffice: nivel de supervisión requerido por cada rol.
 *
 * Configura si la asignación de un rol requiere aprobación previa
 * del supervisor (adm_sistema, supervision) o solo genera una alerta
 * supervisada (resto de roles).
 *
 * Accesible en /admin/configuracion-roles.
 *
 * @see docs/modulo-usuarios-permisos.md sección 2.8
 */
class ConfiguracionRolResource extends Resource
{
    protected static ?string $model = ConfiguracionRol::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Supervisión de roles';
    protected static string|\UnitEnum|null $navigationGroup = 'Organización';
    protected static ?string $modelLabel = 'Configuración de rol';
    protected static ?string $pluralModelLabel = 'Supervisión de roles';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Configuración de supervisión')
                ->description('Define qué nivel de supervisión requiere la asignación de este rol.')
                ->schema([
                    Select::make('rol_id')
                        ->label('Rol')
                        ->options(fn () => Role::orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->unique(ignoreRecord: true),

                    Select::make('nivel_supervision')
                        ->label('Nivel de supervisión')
                        ->options([
                            'aprobacion_previa'   => 'Aprobación previa — la asignación no es efectiva hasta que el supervisor la aprueba',
                            'alerta_supervisada'  => 'Alerta supervisada — efectiva inmediatamente, el supervisor recibe una alerta',
                        ])
                        ->required()
                        ->default('alerta_supervisada'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rol.name')
                    ->label('Rol')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nivel_supervision')->badge()
                    ->label('Nivel de supervisión')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'aprobacion_previa'  => 'Aprobación previa',
                        'alerta_supervisada' => 'Alerta supervisada',
                        default              => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'aprobacion_previa'  => 'danger',
                        'alerta_supervisada' => 'warning',
                        default              => 'gray',
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('rol.name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListConfiguracionRol::route('/'),
            'create' => Pages\CreateConfiguracionRol::route('/create'),
            'edit'   => Pages\EditConfiguracionRol::route('/{record}/edit'),
        ];
    }
}
