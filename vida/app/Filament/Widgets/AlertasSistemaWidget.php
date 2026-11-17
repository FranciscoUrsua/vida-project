<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UsuarioResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use LogicException;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Alertas y avisos de backoffice (origen sistema o asignación de roles) que el usuario
 * tiene pendientes, con su texto y la acción de reconocerlos o descartarlos.
 *
 * Solo las suyas: «alertas del usuario» es `Alerta::pendientesPara()` (la misma regla que
 * el buzón y la bandeja operativa). Antes listaba todas las pendientes de esos orígenes, de
 * cualquier destinatario (también avisos personales de otros), sin texto y sin poder
 * abrirlas (incidencia del 17 nov 2026).
 */
class AlertasSistemaWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 2;

    protected static ?string $heading = 'Alertas del sistema';

    /**
     * Lo ven administración y supervisión.
     *
     * @return bool
     */
    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'supervision']) ?? false;
    }

    /**
     * Tabla de las alertas pendientes del usuario con «Ver» y «Reconocer».
     *
     * @param Table $table Tabla base.
     *
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Alerta::query()
                    ->whereIn('origen_type', ['sistema', UsuarioRol::class])
                    ->pendientesPara(auth()->user())
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (TipoAlerta $state): string => $state === TipoAlerta::Alerta ? 'Alerta' : 'Aviso')
                    ->color(fn (TipoAlerta $state): string => $state === TipoAlerta::Alerta ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('titulo')
                    ->label('Descripción')
                    ->description(fn (Alerta $record): string => (string) $record->cuerpo)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Generada')
                    ->since()
                    ->sortable(),
            ])
            ->recordAction('ver')
            ->actions([
                Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Alerta $record): string => (string) $record->titulo)
                    ->modalDescription(fn (Alerta $record): string => (string) $record->cuerpo)
                    ->modalSubmitActionLabel(fn (Alerta $record): string => $record->tipo === TipoAlerta::Alerta ? 'Reconocer' : 'Descartar')
                    ->modalCancelActionLabel('Cerrar')
                    ->action(fn (Alerta $record) => $this->reconocer($record)),
                Action::make('usuario')
                    ->label('Ver usuario')
                    ->icon('heroicon-o-user')
                    ->visible(fn (Alerta $record): bool => $record->origen_type === UsuarioRol::class && $this->usuarioDeOrigen($record) !== null)
                    ->url(fn (Alerta $record): ?string => ($id = $this->usuarioDeOrigen($record)) === null ? null : UsuarioResource::getUrl('edit', ['record' => $id])),
            ])
            ->emptyStateHeading('Sin alertas pendientes');
    }

    /**
     * Reconoce (alerta) o descarta (aviso) la parte del usuario.
     *
     * @param Alerta $alerta
     *
     * @return void
     */
    private function reconocer(Alerta $alerta): void
    {
        try {
            app(AlertaService::class)->reconocer($alerta, auth()->user(), request()->ip() ?? '');
            Notification::make()->success()->title($alerta->tipo === TipoAlerta::Alerta ? 'Alerta reconocida' : 'Aviso descartado')->send();
        } catch (LogicException $e) {
            Notification::make()->danger()->title('No se ha podido reconocer')->body($e->getMessage())->send();
        }
    }

    /**
     * Usuario al que se refiere una alerta de asignación de rol.
     *
     * @param Alerta $alerta
     *
     * @return int|null
     */
    private function usuarioDeOrigen(Alerta $alerta): ?int
    {
        return UsuarioRol::whereKey($alerta->origen_id)->value('usuario_id');
    }
}
