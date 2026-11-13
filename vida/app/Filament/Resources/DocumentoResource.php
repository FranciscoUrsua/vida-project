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
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Services\LecturaDocumentoService;

/**
 * Visor de documentos custodiados en el sistema (custodia v2).
 *
 * Incluye documentación aportada y PDFs de informes firmados. No muestra el
 * nombre original del fichero (puede contener datos personales). Los ficheros
 * nunca se sirven desde el almacenamiento: «Ver PDF» genera una URL firmada
 * temporal a la ruta de descarga de la aplicación.
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
     * Construye la vista de detalle de documentos custodiados.
     *
     * @param Schema $schema Esquema base del infolist.
     *
     * @return Schema
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextEntry::make('tipo.nombre')
                        ->label('Tipo documental'),

                    TextEntry::make('estado')
                        ->label('Estado')
                        ->badge()
                        ->formatStateUsing(fn (EstadoDocumento $state) => $state->etiqueta()),

                    TextEntry::make('titulo')
                        ->label('Título')
                        ->placeholder('—'),

                    TextEntry::make('fecha_validez')
                        ->label('Válido hasta')
                        ->date('d/m/Y')
                        ->placeholder('Sin caducidad')
                        ->color(fn (Documento $record) => $record->estaCaducado() ? 'danger' : null),

                    TextEntry::make('uuid')
                        ->label('Identificador')
                        ->fontFamily('mono')
                        ->columnSpanFull(),
                ]),

            Section::make('Versión vigente')
                ->columns(2)
                ->schema([
                    TextEntry::make('versionVigente.numero')
                        ->label('Versión'),

                    TextEntry::make('versionVigente.canal')
                        ->label('Canal')
                        ->formatStateUsing(fn (?CanalCaptura $state) => $state?->etiqueta()),

                    TextEntry::make('versionVigente.paginas')
                        ->label('Páginas'),

                    TextEntry::make('versionVigente.tamanyo_bytes')
                        ->label('Tamaño')
                        ->formatStateUsing(fn (?int $state) => $state === null ? '—' : self::formatearTamano($state)),

                    TextEntry::make('versionVigente.hash_sha256')
                        ->label('SHA-256')
                        ->fontFamily('mono')
                        ->columnSpanFull(),
                ]),

            Section::make('Alta')
                ->columns(2)
                ->schema([
                    TextEntry::make('creador.name')
                        ->label('Dado de alta por'),

                    TextEntry::make('created_at')
                        ->label('Fecha de alta')
                        ->dateTime('d/m/Y H:i'),
                ]),
        ]);
    }

    /**
     * Determina si el usuario puede ver el listado de documentos.
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios', 'supervision']) ?? false;
    }

    /**
     * Configura el listado de documentos.
     *
     * @param Table $table Tabla base.
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user->hasAnyRole(['adm_sistema', 'adm_usuarios'])) {
                    return;
                }
                // supervision: solo documentos dados de alta por usuarios de su subtree de UO
                $uoIds = $user->uoSubtreeIds();
                if (empty($uoIds)) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                $query->whereHas('creador', function (Builder $q) use ($uoIds) {
                    $q->whereHas('adscripciones', function (Builder $q2) use ($uoIds) {
                        $q2->whereIn('unidad_organizativa_id', $uoIds);
                    });
                });
            })
            ->columns([
                Tables\Columns\TextColumn::make('tipo.nombre')
                    ->label('Tipo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EstadoDocumento $state) => $state->etiqueta()),

                Tables\Columns\TextColumn::make('versionVigente.canal')
                    ->label('Canal')
                    ->formatStateUsing(fn (?CanalCaptura $state) => $state?->etiqueta()),

                Tables\Columns\TextColumn::make('vinculos_activos_count')
                    ->label('Vínculos')
                    ->counts('vinculosActivos'),

                Tables\Columns\TextColumn::make('creador.name')
                    ->label('Dado de alta por')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_documental_id')
                    ->label('Tipo documental')
                    ->relationship('tipo', 'nombre'),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('ver_fichero')
                    ->label('Ver PDF')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    // El controlador vuelve a autorizar; esto solo oculta el enlace a quien no tiene acceso.
                    ->visible(fn (Documento $record): bool => auth()->user()?->can('view', $record) ?? false)
                    ->url(fn (Documento $record): string => app(LecturaDocumentoService::class)->urlTemporal($record, 60))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Declara las páginas del visor de documentos.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentos::route('/'),
            'view' => Pages\ViewDocumento::route('/{record}'),
        ];
    }

    /**
     * Tamaño legible (B, KB, MB).
     *
     * @param int $bytes Tamaño en bytes.
     *
     * @return string
     */
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
}
