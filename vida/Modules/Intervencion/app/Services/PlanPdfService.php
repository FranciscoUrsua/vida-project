<?php

namespace Modules\Intervencion\Services;

use App\Models\Scopes\AmbitoUoScope;
use App\Services\PresentacionPersona;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Documentos\Services\ResolverEstiloInforme;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Servicio de generación del PDF del Plan de Intervención Social (PISO).
 *
 * Usa barryvdh/laravel-dompdf para renderizar la vista Blade del plan
 * y devolver el binario del PDF listo para descarga o almacenamiento.
 */
class PlanPdfService
{
    /**
     * @param ResolverEstiloInforme $resolverEstilo Resolutor jerárquico del estilo institucional.
     */
    public function __construct(
        private ResolverEstiloInforme $resolverEstilo,
    ) {}

    /**
     * Genera el PDF del plan con todos sus datos listos para impresión y firma.
     * Devuelve el contenido del PDF como string binario.
     *
     * @param PlanDeIntervencion $plan Plan de intervención a exportar.
     *
     * @return string Binario del PDF
     */
    public function generar(PlanDeIntervencion $plan): string
    {
        $plan->load([
            'tipoPlan',
            'historia.ciudadano',
            // Sin AmbitoUoScope: un conviviente puede ser de otra UO; su nombre lo decide PresentacionPersona.
            'unidadConvivencia.miembrosActivos.ciudadano' => fn ($q) => $q->withoutGlobalScope(AmbitoUoScope::class),
            'objetivosGenerales',
            'objetivosEspecificos.tipoFicha',
            'fichasDiagnostico.ficha.tipoFicha',
            'actuacionesAyuntamiento.prestacion',
            'actuacionesAyuntamiento.responsable.profesional',
            'actuacionesCiudadano.prestacion',
            'participantesActivos.profesional.profesional',
            'profesionalResponsable.profesional',
        ]);

        $uoId = $plan->historia->unidad_organizativa_id ?? 1;
        $estilo = $this->resolverEstilo->resolver($uoId);

        // Convivientes: un colectivo protegido que el usuario no puede ver sale sin nombre (CLAUDE.md §3).
        $convivientes = $plan->unidadConvivencia?->miembrosActivos
            ->map(fn ($m) => PresentacionPersona::paraListado($m->ciudadano, auth()->user())['nombre'])
            ->all() ?? [];

        $html = view('intervencion::pdf.plan', [
            'plan' => $plan,
            'estilo' => $estilo,
            'convivientes' => $convivientes,
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'Source Sans Pro',
                'isRemoteEnabled' => false,
            ]);

        return $pdf->output();
    }

    /**
     * Devuelve el nombre de fichero sugerido para la descarga del PDF.
     *
     * @param PlanDeIntervencion $plan Plan de intervención.
     *
     * @return string Nombre de fichero.
     */
    public function nombre(PlanDeIntervencion $plan): string
    {
        $ciudadanoId = $plan->historia->ciudadano_id ?? 'sin-id';

        return "plan_{$ciudadanoId}_v{$plan->version}_".now()->format('Ymd').'.pdf';
    }
}
