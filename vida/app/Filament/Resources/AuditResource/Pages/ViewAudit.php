<?php

namespace App\Filament\Resources\AuditResource\Pages;

use App\Enums\AccionAuditEnum;
use App\Filament\Resources\AuditResource;
use App\Models\Audit;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Página de detalle de un registro de auditoría.
 */
class ViewAudit extends ViewRecord
{
    protected static string $resource = AuditResource::class;

    /**
     * Construye el esquema de detalle del registro de auditoría.
     *
     * @param Schema $schema Esquema base.
     */
    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Información del acceso')
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Fecha y hora')
                        ->dateTime('d/m/Y H:i:s'),

                    TextEntry::make('user.name')
                        ->label('Profesional'),

                    TextEntry::make('accion')
                        ->label('Acción')
                        ->formatStateUsing(fn (AccionAuditEnum $state): string => $state->etiqueta()),

                    TextEntry::make('auditable_type')
                        ->label('Modelo')
                        ->formatStateUsing(fn (string $state): string => class_basename($state)),

                    TextEntry::make('auditable_id')
                        ->label('ID del registro'),

                    TextEntry::make('ciudadano_id')
                        ->label('Ciudadano ID')
                        ->placeholder('—'),

                    TextEntry::make('ip')
                        ->label('IP de origen')
                        ->placeholder('—'),

                    TextEntry::make('user_agent')
                        ->label('User Agent')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make('Contexto')
                ->schema([
                    KeyValueEntry::make('contexto')
                        ->label(''),
                ])
                ->visible(fn (Audit $record): bool => ! empty($record->contexto)),

            Section::make('Datos anteriores')
                ->schema([
                    KeyValueEntry::make('datos_antes')
                        ->label(''),
                ])
                ->visible(fn (Audit $record): bool => ! empty($record->datos_antes)),

            Section::make('Datos posteriores')
                ->schema([
                    KeyValueEntry::make('datos_despues')
                        ->label(''),
                ])
                ->visible(fn (Audit $record): bool => ! empty($record->datos_despues)),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
