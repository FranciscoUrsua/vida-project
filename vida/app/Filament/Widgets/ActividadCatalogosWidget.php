<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Widget de actividad reciente en catálogos (últimas modificaciones).
 *
 * TODO: requiere un modelo de auditoría (App\Models\Audit o similar) que no
 * está instalado. Activar cuando se integre owen-it/laravel-auditing o similar.
 * Ver BACKLOG.md — módulo Sistema.
 */
class ActividadCatalogosWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Actividad reciente en catálogos';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }

    public function table(Table $table): Table
    {
        // TODO: sustituir por la query real cuando exista App\Models\Audit
        return $table
            ->query(\App\Models\Version::query()->whereNull('id')->limit(0))
            ->columns([
                Tables\Columns\TextColumn::make('versionable_type')
                    ->label('Entidad')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cuándo')
                    ->since(),
            ])
            ->emptyStateHeading('Sin actividad reciente')
            ->emptyStateDescription('El registro de auditoría de catálogos estará disponible próximamente.');
    }
}
