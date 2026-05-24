<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogAlertasResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Models\Alerta;

/**
 * Recurso de solo lectura para supervisión y auditoría de alertas.
 *
 * Permite al administrador consultar el log completo de alertas con
 * filtros por estado, tipo, fecha y UO. Especialmente útil para
 * auditar alertas vencidas y escaladas.
 */
class LogAlertasResource extends Resource
{
    protected static ?string $model = Alerta::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'Log de alertas';
    protected static ?string $modelLabel = 'Alerta';
    protected static ?string $pluralModelLabel = 'Log de alertas';
    protected static ?int $navigationSort = 70;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->colors([
                        'warning' => TipoAlerta::Alerta->value,
                        'info'    => TipoAlerta::Aviso->value,
                    ]),

                Tables\Columns\TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'warning'  => EstadoAlerta::Pendiente->value,
                        'success'  => EstadoAlerta::Reconocida->value,
                        'primary'  => EstadoAlerta::Escalada->value,
                        'danger'   => EstadoAlerta::Vencida->value,
                    ]),

                Tables\Columns\TextColumn::make('expira_en')
                    ->label('Vencía')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('escalada_en')
                    ->label('Escalada en')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('escaladaA.name')
                    ->label('Escalada a')
                    ->default('—'),

                Tables\Columns\TextColumn::make('destinatarioUo.nombre')
                    ->label('UO')
                    ->default('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        EstadoAlerta::Pendiente->value  => 'Pendiente',
                        EstadoAlerta::Reconocida->value => 'Reconocida',
                        EstadoAlerta::Escalada->value   => 'Escalada',
                        EstadoAlerta::Vencida->value    => 'Vencida',
                    ]),

                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        TipoAlerta::Alerta->value => 'Alerta',
                        TipoAlerta::Aviso->value  => 'Aviso',
                    ]),

                Tables\Filters\Filter::make('vencidas_o_escaladas')
                    ->label('Solo vencidas o escaladas')
                    ->query(fn ($query) => $query->whereIn('estado', [
                        EstadoAlerta::Vencida->value,
                        EstadoAlerta::Escalada->value,
                    ])),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])    // Solo lectura: sin acciones de edición
            ->bulkActions([]); // Sin acciones masivas
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogAlertas::route('/'),
        ];
    }

    /** Solo adm_sistema y supervision pueden ver el log de alertas. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'supervision']) ?? false;
    }

    // Recurso de solo lectura: no se permite crear, editar ni eliminar alertas.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
