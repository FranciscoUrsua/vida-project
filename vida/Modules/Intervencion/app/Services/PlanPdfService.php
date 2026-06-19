<?php

namespace Modules\Intervencion\Services;

use Barryvdh\DomPDF\Facade\Pdf;
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
     * Genera el PDF del plan con todos sus datos listos para impresión y firma.
     * Devuelve el contenido del PDF como string binario.
     *
     *
     * @return string Binario del PDF
     */
    public function generar(PlanDeIntervencion $plan): string
    {
        $plan->load([
            'tipoPlan',
            'historia.ciudadano',
            'unidadConvivencia.miembrosActivos.ciudadano',
            'objetivosGenerales.objetivosEspecificos',
            'actuacionesAyuntamiento.prestacion',
            'actuacionesAyuntamiento.responsable',
            'actuacionesCiudadano.prestacion',
            'participantesActivos.profesional',
            'profesionalResponsable',
        ]);

        $html = view('intervencion::pdf.plan', ['plan' => $plan])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isRemoteEnabled' => false,
            ]);

        return $pdf->output();
    }

    /**
     * Devuelve el nombre de fichero sugerido para la descarga del PDF.
     */
    public function nombre(PlanDeIntervencion $plan): string
    {
        $ciudadanoId = $plan->historia->ciudadano_id ?? 'sin-id';

        return "plan_{$ciudadanoId}_v{$plan->version}_".now()->format('Ymd').'.pdf';
    }
}
