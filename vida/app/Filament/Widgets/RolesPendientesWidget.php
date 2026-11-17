<?php

namespace App\Filament\Widgets;

use App\Models\User;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Usuarios\Models\UsuarioRol;
use Modules\Usuarios\Services\ResolucionRolesService;

/**
 * Asignaciones de rol pendientes de aprobación en el escritorio del backoffice.
 *
 * Muestra a quién le toca resolver cada una. Aprobar o denegar solo se ofrece cuando
 * ResolucionRolesService lo permite: a `adm_sistema` cuando no hay ningún supervisor
 * competente, y nunca su propia solicitud ni una que haya asignado él (§2.8). Antes
 * cualquier `adm_sistema` o `adm_usuarios` aprobaba cualquiera, también la que acababa
 * de asignar.
 */
class RolesPendientesWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    protected static ?string $heading = 'Roles pendientes de aprobación';

    /**
     * Lo ven quienes gestionan usuarios.
     *
     * @return bool
     */
    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Tabla de solicitudes pendientes con quién las resuelve y las acciones permitidas.
     *
     * @param Table $table
     *
     * @return Table
     */
    public function table(Table $table): Table
    {
        $servicio = app(ResolucionRolesService::class);

        return $table
            ->query(UsuarioRol::pendientes()->with(['usuario', 'rol']))
            ->columns([
                Tables\Columns\TextColumn::make('usuario.email')
                    ->label('Usuario')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rol.name')
                    ->label('Rol solicitado')
                    ->badge(),
                Tables\Columns\TextColumn::make('resuelve')
                    ->label('La resuelve')
                    ->getStateUsing(function (UsuarioRol $record) use ($servicio): string {
                        $supervisores = $servicio->supervisoresCompetentes($record);

                        return $supervisores->isEmpty()
                            ? 'adm_sistema (no hay supervisor competente)'
                            : 'Supervisión: '.$supervisores->map(fn (User $u) => $u->email)->implode(', ');
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (UsuarioRol $record): bool => $servicio->puedeResolver(auth()->user(), $record))
                    ->action(fn (UsuarioRol $record) => $this->resolver(fn () => $servicio->aprobar($record, auth()->user()), 'Rol aprobado')),
                Action::make('denegar')
                    ->label('Denegar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->form([Textarea::make('motivo')->label('Motivo')->required()])
                    ->visible(fn (UsuarioRol $record): bool => $servicio->puedeResolver(auth()->user(), $record))
                    ->action(fn (UsuarioRol $record, array $data) => $this->resolver(fn () => $servicio->denegar($record, $data['motivo'], auth()->user()), 'Rol denegado')),
            ])
            ->emptyStateHeading('No hay roles pendientes')
            ->emptyStateDescription('Todas las asignaciones están al día.');
    }

    /**
     * Ejecuta la resolución y muestra el resultado o el rechazo del servicio.
     *
     * @param callable(): void $accion
     * @param string $exito
     *
     * @return void
     */
    private function resolver(callable $accion, string $exito): void
    {
        try {
            $accion();
            Notification::make()->success()->title($exito)->send();
        } catch (AuthorizationException|DomainException $e) {
            Notification::make()->danger()->title('No se ha resuelto')->body($e->getMessage())->send();
        }
    }
}
