<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\DocumentoResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Documentos\Enums\OrigenDocumento;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Services\ServicioAlmacenamiento;

/**
 * Visor de documentos custodiados en el sistema.
 *
 * Incluye documentos externos (PDFs aportados por ciudadanos o profesionales)
 * y documentos generados internamente (PDFs de informes firmados).
 *
 * Los ficheros nunca se sirven desde rutas públicas: el acceso siempre genera
 * una URL firmada temporal a través de ServicioAlmacenamiento::urlTemporal().
 */
class DocumentoResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = Documento::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-clip';

    protected static ?string $navigationLabel = 'Documentos';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes y Plantillas';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Documentos';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Los documentos se suben desde el flujo operativo, no desde el backoffice.
     *
     * @param Schema $schema Esquema de infolist de Filament.
     *
     * @return Schema Esquema configurado para detalle de documento.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextEntry::make('nombre_original')
                        ->label('Nombre del fichero'),

                    TextEntry::make('tipo.etiqueta')
                        ->label('Tipo de documento'),

                    TextEntry::make('origen')
                        ->label('Origen')
                        ->formatStateUsing(fn (OrigenDocumento $state) => $state->label())
                        ->badge()
                        ->color(fn (OrigenDocumento $state) => match ($state) {
                            OrigenDocumento::Externo => 'info',
                            OrigenDocumento::Generado => 'success',
                        }),

                    TextEntry::make('mime_type')
                        ->label('Formato'),

                    TextEntry::make('tamano_bytes')
                        ->label('Tamaño')
                        ->formatStateUsing(fn (int $state) => self::formatearTamano($state)),

                    TextEntry::make('hash_sha256')
                        ->label('SHA-256')
                        ->fontFamily('mono')
                        ->columnSpanFull(),
                ]),

            Section::make('Entidad asociada')
                ->columns(2)
                ->schema([
                    TextEntry::make('documentable_type')
                        ->label('Tipo de entidad')
                        ->formatStateUsing(fn (string $state) => self::etiquetaTipo($state)),

                    TextEntry::make('documentable_id')
                        ->label('ID de entidad'),
                ]),

            Section::make('Auditoría')
                ->columns(2)
                ->schema([
                    TextEntry::make('subidoPor.name')
                        ->label('Subido por'),

                    TextEntry::make('created_at')
                        ->label('Fecha de subida')
                        ->dateTime('d/m/Y H:i'),

                    TextEntry::make('descripcion')
                        ->label('Descripción')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * supervision puede ver documentos de su subtree (solo lectura); adm_* puede gestionar.
     *
     * @return bool True si el rol puede consultar documentos.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios', 'supervision']) ?? false;
    }

    /**
     * Configura la tabla de documentos.
     *
     * @param Table $table Tabla Filament a configurar.
     * @return Table Tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user->hasAnyRole(['adm_sistema', 'adm_usuarios'])) {
                    return;
                }
                // supervision: solo documentos subidos por usuarios de su subtree de UO
                $uoIds = $user->uoSubtreeIds();
                if (empty($uoIds)) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                $query->whereHas('subidoPor', function (Builder $q) use ($uoIds) {
                    $q->whereHas('adscripciones', function (Builder $q2) use ($uoIds) {
                        $q2->whereIn('unidad_organizativa_id', $uoIds);
                    });
                });
            })
            ->columns([
                Tables\Columns\TextColumn::make('nombre_original')
                    ->label('Fichero')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('tipo.etiqueta')
                    ->label('Tipo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('origen')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn (OrigenDocumento $state) => $state->label())
                    ->color(fn (OrigenDocumento $state) => match ($state) {
                        OrigenDocumento::Externo => 'info',
                        OrigenDocumento::Generado => 'success',
                    }),

                Tables\Columns\TextColumn::make('documentable_type')
                    ->label('Entidad')
                    ->formatStateUsing(fn (string $state) => self::etiquetaTipo($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tamano_bytes')
                    ->label('Tamaño')
                    ->formatStateUsing(fn (int $state) => self::formatearTamano($state))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subidoPor.name')
                    ->label('Subido por')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('origen')
                    ->label('Origen')
                    ->options(collect(OrigenDocumento::cases())->mapWithKeys(
                        fn (OrigenDocumento $o) => [$o->value => $o->label()]
                    )),

                Tables\Filters\SelectFilter::make('tipo_documento_id')
                    ->label('Tipo de documento')
                    ->relationship('tipo', 'etiqueta'),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('ver_fichero')
                    ->label('Ver PDF')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Documento $record): string => app(ServicioAlmacenamiento::class)->urlTemporal($record, 60)
                    )
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Devuelve las paginas registradas para el recurso.
     *
     * @return array<string, mixed> Rutas de paginas Filament.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentos::route('/'),
            'view' => Pages\ViewDocumento::route('/{record}'),
        ];
    }

    private static function formatearTamano(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 2).' MB';
    }

    private static function etiquetaTipo(string $fqcn): string
    {
        return match (true) {
            str_contains($fqcn, 'Ciudadano') => 'Ciudadano',
            str_contains($fqcn, 'Informe') => 'Informe',
            str_contains($fqcn, 'Historia') => 'Historia social',
            default => class_basename($fqcn),
        };
    }
}
