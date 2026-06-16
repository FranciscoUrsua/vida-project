<?php

namespace App\Filament\Resources;

use App\Enums\AccionAuditEnum;
use App\Filament\Resources\AuditResource\Pages;
use App\Models\Audit;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Visor de registros de auditoría para supervisores y administradores.
 *
 * Solo lectura: no hay CreateAction, EditAction ni DeleteAction.
 * El scope automático de UO limita los registros visibles al supervisor
 * a los de profesionales de su UO y descendientes.
 * El rol adm_sistema no tiene restricción de scope.
 *
 * El filtro de rango de fechas es obligatorio para evitar cargas masivas
 * (máximo 90 días por consulta).
 *
 * @see docs/modulo-auditoria.md §6
 */
class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Auditoría de accesos';

    protected static ?string $modelLabel = 'Registro de auditoría';

    protected static ?string $pluralModelLabel = 'Registros de auditoría';

    protected static ?int $navigationSort = 6;

    // -------------------------------------------------------------------------
    // Scope de UO: el supervisor solo ve registros de su UO y descendientes
    // -------------------------------------------------------------------------

    /**
     * Devuelve la consulta base del recurso filtrada por el ambito del usuario.
     *
     * @return Builder Consulta base del recurso.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->latest('created_at');
        $user = Auth::user();

        if ($user && ! $user->hasRole('adm_sistema')) {
            $uoIds = $user->unidadesOrganizativas()
                ->pluck('unidad_organizativas.id')
                ->toArray();

            // Limitar a profesionales cuya UO activa esté en el árbol del supervisor
            $query->whereHas('user', function (Builder $q) use ($uoIds): void {
                $q->whereHas('unidadesOrganizativas', function (Builder $sub) use ($uoIds): void {
                    $sub->whereIn('unidad_organizativas.id', $uoIds);
                });
            });
        }

        return $query;
    }

    // -------------------------------------------------------------------------
    // Tabla (solo lectura)
    // -------------------------------------------------------------------------

    /**
     * Configura la tabla de auditoria.
     *
     * @param Table $table Tabla Filament a configurar.
     * @return Table Tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->timezone(config('app.timezone')),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Profesional')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('accion')
                    ->label('Acción')
                    ->formatStateUsing(fn (AccionAuditEnum $state): string => $state->etiqueta())
                    ->color(fn (AccionAuditEnum $state): string => $state->color()),

                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Sobre qué')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->description(fn (Audit $record): string => 'ID: '.$record->auditable_id),

                Tables\Columns\TextColumn::make('ciudadano.id')
                    ->label('Ciudadano')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state, Audit $record): string => $record->ciudadano_id
                        ? 'CIU-'.$record->ciudadano_id
                        : '—'),
            ])
            ->filters([
                Tables\Filters\Filter::make('rango_fechas')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('desde')
                            ->label('Desde')
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta')
                            ->required()
                            ->afterOrEqual('desde'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! $data['desde'] || ! $data['hasta']) {
                            // Sin filtro de fechas, no mostrar resultados
                            return $query->whereRaw('1 = 0');
                        }
                        $desde = \Carbon\Carbon::parse($data['desde']);
                        $hasta = \Carbon\Carbon::parse($data['hasta'])->endOfDay();
                        if ($desde->diffInDays($hasta) > 90) {
                            return $query->whereRaw('1 = 0');
                        }
                        return $query->whereBetween('created_at', [$desde, $hasta]);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['desde'] && $data['hasta']) {
                            return 'Período: '.$data['desde'].' → '.$data['hasta'];
                        }
                        return null;
                    }),

                Tables\Filters\SelectFilter::make('accion')
                    ->label('Tipo de acción')
                    ->options(array_combine(
                        array_column(AccionAuditEnum::cases(), 'value'),
                        array_map(fn ($c) => $c->etiqueta(), AccionAuditEnum::cases()),
                    ))
                    ->multiple(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    // -------------------------------------------------------------------------
    // Sin crear ni editar — solo ver el listado y el detalle
    // -------------------------------------------------------------------------

    /**
     * Devuelve las paginas registradas para el recurso.
     *
     * @return array<string, mixed> Rutas de paginas Filament.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAudits::route('/'),
            'view'  => Pages\ViewAudit::route('/{record}'),
        ];
    }

    /**
     * Solo accesible para roles supervision y adm_sistema.
     *
     * @return bool True si el usuario puede acceder al recurso.
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && ($user->hasRole('supervision') || $user->hasRole('adm_sistema'));
    }

    /**
     * Registro de auditoría: inmutable. Nunca se crean desde el backoffice.
     *
     * @return bool Siempre false.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @param Audit $record Registro de auditoria.
     *
     * @return bool Siempre false.
     */
    public static function canEdit($record): bool
    {
        return false;
    }

    /**
     * @param Audit $record Registro de auditoria.
     *
     * @return bool Siempre false.
     */
    public static function canDelete($record): bool
    {
        return false;
    }
}
