<?php

namespace Modules\Documentos\Services;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Support\SaneadorHtmlInforme;

/**
 * Ciclo de vida del borrador de un informe profesional (fase 5, pasos 1 y 2).
 *
 * Crea el borrador desde una plantilla activa, guarda el texto de las secciones de
 * texto libre (solo esas: las automáticas se resuelven al generar el PDF y no se
 * pueden escribir), comprueba las obligatorias y elimina borradores. Los borradores sí
 * se pueden borrar: no son documentos custodiados. Firmar y anular, en
 * `ServicioFirmaInforme`.
 *
 * Cada método vuelve a autorizar con `InformePolicy`: el servidor no se fía de la vista.
 */
class InformeService
{
    /**
     * Crea el servicio.
     *
     * @param SaneadorHtmlInforme $saneador Saneado del HTML del texto libre.
     */
    public function __construct(private SaneadorHtmlInforme $saneador) {}

    /**
     * Crea un borrador de informe de la persona de la historia con una plantilla activa.
     *
     * El texto libre parte del `contenido_plantilla` de cada sección (con sus merge tags).
     *
     * @param PlantillaInforme $plantilla Plantilla activa.
     * @param HistoriaSocial $historia Historia Social de la persona.
     * @param User $autor Quien redacta.
     *
     * @return Informe
     *
     * @throws AuthorizationException si no puede redactar informes de esta persona
     * @throws \DomainException si la plantilla no está activa
     */
    public function crear(PlantillaInforme $plantilla, HistoriaSocial $historia, User $autor): Informe
    {
        Gate::forUser($autor)->authorize('crear', [Informe::class, $historia]);
        // R1: redactar un informe es una actuación (centro activo del autor)
        app(CentroDeUsuario::class)->exigirActuacion($autor);

        if (! $plantilla->activa) {
            throw new \DomainException('La plantilla no está activa.');
        }

        $contenido = [];
        foreach ($plantilla->secciones ?? [] as $seccion) {
            if (($seccion['tipo'] ?? '') === 'texto_libre' && isset($seccion['id'])) {
                $contenido[$seccion['id']] = $this->saneador->sanear($seccion['contenido_plantilla'] ?? '');
            }
        }

        return Informe::create([
            'plantilla_id' => $plantilla->id,
            'historia_social_id' => $historia->id,
            'ciudadano_id' => $historia->ciudadano_id,
            'autor_id' => $autor->id,
            'estado' => EstadoInforme::Borrador->value,
            'contenido' => $contenido,
        ]);
    }

    /**
     * Guarda el texto de una sección de texto libre del borrador.
     *
     * Una sección automática o inexistente se ignora: su contenido no se escribe nunca
     * desde el navegador (TF-INF-06).
     *
     * @param Informe $informe Borrador.
     * @param string $seccionId Id de la sección.
     * @param string|null $html HTML del editor.
     * @param User $actor Quien guarda (el autor).
     *
     * @return Informe
     *
     * @throws AuthorizationException si no es el autor o el informe no está en borrador
     */
    public function guardarSeccion(Informe $informe, string $seccionId, ?string $html, User $actor): Informe
    {
        Gate::forUser($actor)->authorize('update', $informe);

        if (! in_array($seccionId, $this->seccionesLibres($informe), true)) {
            return $informe;
        }

        $contenido = $informe->contenido ?? [];
        $contenido[$seccionId] = $this->saneador->sanear($html);
        $informe->update(['contenido' => $contenido]);

        return $informe;
    }

    /**
     * Títulos de las secciones obligatorias que siguen vacías.
     *
     * @param Informe $informe Borrador.
     *
     * @return list<string>
     */
    public function obligatoriasVacias(Informe $informe): array
    {
        $vacias = [];

        foreach ($informe->plantilla->secciones ?? [] as $seccion) {
            if (($seccion['tipo'] ?? '') === 'texto_libre'
                && ! empty($seccion['obligatorio'])
                && $this->saneador->estaVacio($informe->contenido[$seccion['id'] ?? ''] ?? '')) {
                $vacias[] = (string) ($seccion['titulo'] ?? $seccion['id']);
            }
        }

        return $vacias;
    }

    /**
     * Elimina un borrador (solo su autor).
     *
     * @param Informe $informe Borrador.
     * @param User $actor Quien lo elimina.
     *
     * @return void
     *
     * @throws AuthorizationException si no es el autor o el informe no está en borrador
     */
    public function eliminarBorrador(Informe $informe, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $informe);

        $informe->delete();
    }

    /**
     * Ids de las secciones de texto libre de la plantilla del informe.
     *
     * @param Informe $informe Informe.
     *
     * @return list<string>
     */
    private function seccionesLibres(Informe $informe): array
    {
        return collect($informe->plantilla->secciones ?? [])
            ->filter(fn (array $s): bool => ($s['tipo'] ?? '') === 'texto_libre' && isset($s['id']))
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }
}
