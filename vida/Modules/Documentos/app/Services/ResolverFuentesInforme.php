<?php

namespace Modules\Documentos\Services;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Modules\Documentos\Support\MergeTagsCatalogo;
use Modules\Escalas\Enums\EstadoPase;
use Modules\Escalas\Models\PaseEscala;
use Modules\Escalas\Models\TipoEscala;

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
     * @param string $fuente Referencia de fuente (ej: 'ciudadano.datos_basicos')
     * @param int $ciudadanoId ID del ciudadano
     *
     * @return array Datos estructurados para renderizar
     *
     * @todo Implementar cuando el módulo Intervención esté disponible.
     */
    public function resolver(string $fuente, int $ciudadanoId): array
    {
        return [];
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

        return [
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
            'nombre_profesional' => $profesional->name,
            'cargo_profesional' => '—',
            'numero_colegiado' => '—',

            // Centro — pendiente de relación User→Centro en módulo Usuarios (BACKLOG)
            'nombre_centro' => '—',
            'direccion_centro' => '—',
            'telefono_centro' => '—',

            // Informe
            'fecha_informe' => $fechaInforme->format('d/m/Y'),
        ];
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
