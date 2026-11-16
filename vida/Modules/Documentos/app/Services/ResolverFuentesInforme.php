<?php

namespace Modules\Documentos\Services;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Modules\Documentos\Models\ParametroInforme;
use Modules\Documentos\Support\MergeTagsCatalogo;
use Modules\Documentos\Support\VariablesDinamicas;
use Modules\Escalas\Enums\EstadoPase;
use Modules\Escalas\Models\PaseEscala;
use Modules\Escalas\Models\TipoEscala;
use Modules\Prestaciones\Enums\EstadoPrestacionPersona;
use Modules\Prestaciones\Models\PrestacionPersona;

/**
 * Resuelve las fuentes de datos de las secciones de una PlantillaInforme.
 *
 * Dos responsabilidades:
 *   1. resolver(): fuentes automáticas ('automatico') — datos pre-cargados desde la Historia Social.
 *   2. resolverMergeTags(): sustitución de variables dinámicas en secciones de texto libre.
 *
 * Centraliza la lógica de extracción de datos para que las plantillas sean declarativas.
 */
class ResolverFuentesInforme
{
    /**
     * Resuelve los datos de una fuente automática para un ciudadano.
     *
     * Devuelve pares etiqueta → valor, en el orden en que se pintan en el PDF y en el
     * asistente. Reutiliza el mapa de los merge tags para que un dato se muestre igual
     * en una sección automática y en un texto libre. Una fuente que todavía no se
     * resuelve devuelve un array vacío («Sin datos disponibles»).
     *
     * @param string $fuente Referencia de fuente (ej: 'ciudadano.datos_basicos')
     * @param int $ciudadanoId ID del ciudadano
     * @param int|null $profesionalId Autor del informe (para `profesional.datos`).
     *
     * @return array<string, string> Etiqueta → valor.
     */
    public function resolver(string $fuente, int $ciudadanoId, ?int $profesionalId = null): array
    {
        $profesionalId ??= auth()->id();

        if ($profesionalId === null) {
            return [];
        }

        $v = $this->construirMapaValores($ciudadanoId, (int) $profesionalId, now());

        return match ($fuente) {
            'ciudadano.datos_basicos' => [
                'Nombre y apellidos' => $v['nombre_ciudadano'],
                'Documento de identidad' => $v['nie_nif'],
                'Fecha de nacimiento' => $v['fecha_nacimiento'],
                'Edad' => $v['edad'],
                'Dirección' => $v['direccion'],
            ],
            'ciudadano.datos_contacto' => [
                'Teléfono' => $v['telefono'],
                'Correo electrónico' => (string) (Ciudadano::find($ciudadanoId)?->email ?: '—'),
            ],
            'historia_social.resumen' => $this->resumenHistoria($ciudadanoId),
            'historia_social.prestaciones_activas' => $this->prestacionesActivas($ciudadanoId),
            'escalas.barthel_ultimo' => [
                'Fecha del último Barthel' => $v['fecha_valoracion'],
                'Puntuación' => $v['score_barthel'],
                'Interpretación' => $v['interpretacion_barthel'],
            ],
            'escalas.pfeiffer_ultimo' => [
                'Puntuación Pfeiffer' => $v['score_pfeiffer'],
                'Interpretación' => $v['interpretacion_pfeiffer'],
            ],
            'escalas.lawton_ultimo' => [
                'Puntuación Lawton-Brody' => $v['score_lawton'],
                'Interpretación' => $v['interpretacion_lawton'],
            ],
            'profesional.datos' => [
                'Profesional' => $v['nombre_profesional'],
                'Cargo' => $v['cargo_profesional'],
                'Número de colegiado' => $v['numero_colegiado'],
            ],
            default => [],
        };
    }

    /**
     * Datos de la Historia Social abierta de la persona para la fuente `historia_social.resumen`.
     *
     * @param int $ciudadanoId ID del ciudadano.
     *
     * @return array<string, string>
     */
    private function resumenHistoria(int $ciudadanoId): array
    {
        $historia = HistoriaSocial::withoutGlobalScopes()
            ->where('ciudadano_id', $ciudadanoId)
            ->latest()
            ->first();

        if ($historia === null) {
            return [];
        }

        return [
            'Historia Social' => 'HS #'.$historia->id,
            'Fecha de apertura' => $historia->created_at?->format('d/m/Y') ?? '—',
            'Estado' => ucfirst((string) $historia->estado),
            'Centro' => $historia->centro?->nombre ?? '—',
        ];
    }

    /**
     * Prestaciones concedidas o activas de la persona para `historia_social.prestaciones_activas`.
     *
     * @param int $ciudadanoId ID del ciudadano.
     *
     * @return array<string, string> Nombre de la prestación → estado y fecha.
     */
    private function prestacionesActivas(int $ciudadanoId): array
    {
        return PrestacionPersona::query()
            ->with('prestacion')
            ->where('ciudadano_id', $ciudadanoId)
            ->whereIn('estado', [EstadoPrestacionPersona::Concedida->value, EstadoPrestacionPersona::Activa->value])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (PrestacionPersona $p): array => [
                (string) $p->prestacion?->nombre => $p->estado->label()
                    .(($p->fecha_inicio ?? $p->fecha_concesion) ? ' desde el '.($p->fecha_inicio ?? $p->fecha_concesion)->format('d/m/Y') : ''),
            ])
            ->all();
    }

    /**
     * Sustituye los merge tags en el contenido HTML de una sección de plantilla,
     * devolviendo el HTML con los valores reales del informe.
     *
     * @param string $html HTML con tags {{ clave }} de TipTap
     * @param int $ciudadanoId ID del ciudadano del informe.
     * @param int $profesionalId ID del profesional emisor.
     * @param Carbon $fechaInforme Fecha del informe.
     *
     * @return string HTML con valores sustituidos
     */
    public function resolverMergeTags(
        string $html,
        int $ciudadanoId,
        int $profesionalId,
        Carbon $fechaInforme
    ): string {
        $valores = $this->construirMapaValores($ciudadanoId, $profesionalId, $fechaInforme);

        foreach ($valores as $clave => $valor) {
            $html = str_replace('{{ '.$clave.' }}', e((string) $valor), $html);
            $html = str_replace('{{'.$clave.'}}', e((string) $valor), $html);
        }

        return $html;
    }

    /**
     * Construye el mapa completo de clave → valor para un informe concreto.
     * Todas las claves de MergeTagsCatalogo::claves() tienen entrada aquí.
     * Las variables cuya relación no está disponible aún devuelven '—'.
     *
     * @return array<string, string>
     */
    private function construirMapaValores(
        int $ciudadanoId,
        int $profesionalId,
        Carbon $fechaInforme
    ): array {
        $ciudadano = Ciudadano::findOrFail($ciudadanoId);
        $profesional = User::findOrFail($profesionalId);

        // HistoriaSocial: query directa para evitar dependencia del módulo Intervención
        $historia = HistoriaSocial::withoutGlobalScopes()
            ->where('ciudadano_id', $ciudadanoId)
            ->latest()
            ->first();

        $ultimoBarthel = $this->ultimoPaseCompletado($historia, 'barthel');
        $ultimoPfeiffer = $this->ultimoPaseCompletado($historia, 'pfeiffer_spmsq');
        $ultimoLawton = $this->ultimoPaseCompletado($historia, 'lawton_brody');

        $nombreCompleto = trim(
            $ciudadano->nombre.' '.$ciudadano->apellido1.' '.($ciudadano->apellido2 ?? '')
        );

        $fechaNac = $ciudadano->fecha_nacimiento
            ? Carbon::parse($ciudadano->fecha_nacimiento)
            : null;

        $valores = [
            // Ciudadano
            'nombre_ciudadano' => $nombreCompleto ?: '—',
            'fecha_nacimiento' => $fechaNac?->format('d/m/Y') ?? '—',
            'edad' => $fechaNac ? (string) $fechaNac->age : '—',
            'nie_nif' => ($doc = $ciudadano->documentoVigente) ? strtoupper($doc->tipo).' '.$doc->valor : '—',
            'direccion' => $ciudadano->direccion_texto ?? '—',
            'telefono' => $ciudadano->telefono ?? '—',

            // Expediente — pendiente hasta módulo Intervención (BACKLOG)
            'numero_expediente' => '—',
            'fecha_apertura' => $historia?->created_at->format('d/m/Y') ?? '—',
            'motivo_demanda' => '—',

            // Valoración — Barthel
            'fecha_valoracion' => $ultimoBarthel?->fecha->format('d/m/Y') ?? '—',
            'score_barthel' => $ultimoBarthel !== null ? (string) $ultimoBarthel->score_total : '—',
            'interpretacion_barthel' => $ultimoBarthel?->interpretacion_codigo ?? '—',

            // Valoración — Pfeiffer
            'score_pfeiffer' => $ultimoPfeiffer !== null ? (string) $ultimoPfeiffer->score_total : '—',
            'interpretacion_pfeiffer' => $ultimoPfeiffer?->interpretacion_codigo ?? '—',

            // Valoración — Lawton-Brody
            'score_lawton' => $ultimoLawton !== null ? (string) $ultimoLawton->score_total : '—',
            'interpretacion_lawton' => $ultimoLawton?->interpretacion_codigo ?? '—',

            // Plan de intervención — pendiente hasta módulo Intervención (BACKLOG)
            'lista_prestaciones' => '—',
            'fecha_inicio_plan' => '—',
            'objetivos_plan' => '—',

            // Profesional — campos extendidos pendientes de módulo Usuarios completo (BACKLOG)
            'nombre_profesional' => $profesional->profesional?->nombre_completo ?: $profesional->name,
            'cargo_profesional' => $profesional->profesional?->cargo?->nombre ?? '—',
            'numero_colegiado' => '—',

            // Centro — pendiente de relación User→Centro en módulo Usuarios (BACKLOG)
            'nombre_centro' => '—',
            'direccion_centro' => '—',
            'telefono_centro' => '—',

            // Informe
            'fecha_informe' => $fechaInforme->format('d/m/Y'),
        ];

        // Variables auxiliares (documentos-variables-auxiliares.md §6). array_merge deja
        // ganar a la derecha: contextuales > dinámicas de sistema > parámetros configurables,
        // así un parámetro `nombre_ciudadano` no puede romper un informe.
        $valores = array_merge(VariablesDinamicas::resolver(), $valores);

        return array_merge(ParametroInforme::comoMapa(), $valores);
    }

    /**
     * Devuelve el pase completado más reciente de una escala dada para una Historia Social.
     * Devuelve null si no hay historia, si no existe la escala, o si no hay pases completados.
     */
    private function ultimoPaseCompletado(?HistoriaSocial $historia, string $codigoEscala): ?PaseEscala
    {
        if (! $historia) {
            return null;
        }

        try {
            $tipoId = TipoEscala::codigoId($codigoEscala);
        } catch (ModelNotFoundException) {
            return null;
        }

        return $historia->pasesEscala()
            ->where('tipo_escala_id', $tipoId)
            ->where('estado', EstadoPase::Completado->value)
            ->latest('fecha')
            ->first();
    }
}
