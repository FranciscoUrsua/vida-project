<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\AccesoProtegido;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Models\Alerta;

/**
 * Pantalla de búsqueda de ciudadanos del interfaz operativo de Intervención.
 *
 * Implementa tres niveles de acceso a los resultados según UO y colectivo:
 *   Nivel 1 (propio): Historia Social en la UO del profesional.
 *   Nivel 2 (otra UO): Historia Social en otra UO sin colectivo protegido.
 *   Nivel 3 (protegido): Ciudadano con colectivo_extra_protegido activo.
 *
 * Nota: la búsqueda por nombre opera sobre datos cifrados. Se implementa
 * cargando un máximo de 500 registros y filtrando en PHP — es aceptable
 * para el volumen esperado en una UO. Un índice hash búsqueda-eficiente
 * es la solución de producción correcta.
 * TODO: Implementar índice hash determinista para búsqueda de nombre cifrado.
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega2.md §4
 */
#[Layout('layouts.operativo')]
class BuscarCiudadanoPage extends Component
{
    /** @var string Término de búsqueda */
    public string $query = '';

    /** @var string Campo de búsqueda: 'nombre' | 'doc' | 'hsu' | 'alias' */
    public string $campoBusqueda = 'nombre';

    /** @var array<int, array<string, mixed>> Resultados de la búsqueda */
    public array $resultados = [];

    /** @var bool Se ha ejecutado al menos una búsqueda */
    public bool $buscado = false;

    /** @var bool Modal de solicitud de acceso visible */
    public bool $modalSolicitud = false;

    /** @var int|null Ciudadano para el que se solicita acceso */
    public ?int $ciudadanoSolicitudId = null;

    /** @var string Justificación de la solicitud de acceso */
    public string $justificacion = '';

    // -------------------------------------------------------------------------
    // Búsqueda
    // -------------------------------------------------------------------------

    /**
     * Ejecuta la búsqueda y llena $resultados con los ciudadanos encontrados.
     */
    public function buscar(): void
    {
        $this->buscado = true;
        $this->resultados = [];

        if (strlen(trim($this->query)) < 2) {
            return;
        }

        $termino = trim($this->query);
        $user = Auth::user();
        $uoIds = $user->adscripcionesVigentes()->pluck('unidad_organizativa_id')->toArray();

        $ciudadanos = match ($this->campoBusqueda) {
            'alias' => $this->buscarPorAlias($termino),
            'doc', 'hsu' => $this->buscarPorIdentificador($termino, $this->campoBusqueda),
            default => $this->buscarPorNombre($termino),
        };

        foreach ($ciudadanos as $ciudadano) {
            $this->resultados[] = $this->resolverResultado($ciudadano, $uoIds);
        }
    }

    /**
     * Busca ciudadanos cuyo alias contiene el término dado.
     *
     * @return Collection<int, Ciudadano>
     */
    private function buscarPorAlias(string $termino): Collection
    {
        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->where('alias', 'ilike', '%'.$termino.'%')
            ->where('activo', true)
            ->limit(50)
            ->get();
    }

    /**
     * Busca por nombre filtrando en PHP sobre datos descifrados.
     * Carga máximo 500 registros para evitar problemas de memoria.
     *
     * TODO: reemplazar por búsqueda en índice hash cuando esté disponible.
     *
     * @return array<int, Ciudadano>
     */
    private function buscarPorNombre(string $termino): array
    {
        $terminoNorm = mb_strtolower($termino);

        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->where('activo', true)
            ->limit(500)
            ->get()
            ->filter(function (Ciudadano $c) use ($terminoNorm): bool {
                $nombreCompleto = mb_strtolower(
                    trim(($c->nombre ?? '').' '.($c->apellido1 ?? '').' '.($c->apellido2 ?? ''))
                );

                return str_contains($nombreCompleto, $terminoNorm);
            })
            ->take(50)
            ->values()
            ->all();
    }

    /**
     * Búsqueda por documento de identidad o NI-HSU-CM.
     * Requiere tabla ciudadano_identificadores (no implementada aún).
     *
     * TODO: implementar cuando exista la tabla ciudadano_identificadores.
     *
     * @param string $tipo 'doc' | 'hsu'
     *
     * @return array<int, never>
     */
    private function buscarPorIdentificador(string $termino, string $tipo): array
    {
        // TODO: implementar cuando exista la tabla ciudadano_identificadores
        return [];
    }

    /**
     * Resuelve el nivel de acceso y construye los datos de un resultado.
     *
     * @param array<int> $uoIds UOs vigentes del profesional
     *
     * @return array<string, mixed>
     */
    private function resolverResultado(Ciudadano $ciudadano, array $uoIds): array
    {
        // Ciudadano con colectivo extra protegido → nivel 3
        if ($ciudadano->colectivo_extra_protegido) {
            return [
                'ciudadano_id' => $ciudadano->id,
                'nombre' => $ciudadano->nombre.' '.$ciudadano->apellido1,
                'alias' => $ciudadano->alias,
                'nivel' => 3,
                'historia_id' => null,
                // No se expone domicilio para ciudadanos protegidos (principio 3.7)
                'domicilio' => null,
            ];
        }

        // Buscar Historia Social más reciente
        $historia = HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)
            ->where('ciudadano_id', $ciudadano->id)
            ->where('estado', '!=', 'cerrada')
            ->latest()
            ->first();

        $nivel = 1;
        if ($historia !== null && ! in_array($historia->unidad_organizativa_id, $uoIds)) {
            $nivel = 2;
        }

        return [
            'ciudadano_id' => $ciudadano->id,
            'nombre' => $ciudadano->nombre.' '.$ciudadano->apellido1.($ciudadano->apellido2 ? ' '.$ciudadano->apellido2 : ''),
            'alias' => $ciudadano->alias,
            'nivel' => $nivel,
            'historia_id' => $historia?->id,
            'domicilio' => $ciudadano->colectivo_extra_protegido ? null : null, // cifrado, TODO: mostrar si nivel 1
        ];
    }

    // -------------------------------------------------------------------------
    // Acceso nivel 2
    // -------------------------------------------------------------------------

    /**
     * Registra el acceso de nivel 2 en el log de auditoría y navega a la Historia Social.
     * El campo audits no existe aún — se registra en el log de la aplicación.
     *
     * TODO: conectar con tabla audits cuando esté disponible.
     */
    public function registrarAccesoNivel2(int $historiaId): void
    {
        // TODO: conectar con tabla audits cuando esté disponible
        Log::info('acceso_nivel2', [
            'auditable_type' => 'HistoriaSocial',
            'auditable_id' => $historiaId,
            'event' => 'acceso_nivel2',
            'user_id' => Auth::id(),
        ]);

        // La navegación a la Historia Social es Entrega 3
        // TODO: redirect()->route('intervencion.ciudadano.show', ...)
    }

    // -------------------------------------------------------------------------
    // Solicitud de acceso (nivel 3)
    // -------------------------------------------------------------------------

    /**
     * Abre el modal de solicitud de acceso para un ciudadano protegido.
     */
    public function abrirModalSolicitud(int $ciudadanoId): void
    {
        $this->ciudadanoSolicitudId = $ciudadanoId;
        $this->justificacion = '';
        $this->modalSolicitud = true;
    }

    /**
     * Cierra el modal sin enviar.
     */
    public function cerrarModalSolicitud(): void
    {
        $this->modalSolicitud = false;
        $this->ciudadanoSolicitudId = null;
        $this->justificacion = '';
    }

    /**
     * Crea la solicitud de acceso a un ciudadano protegido.
     *
     * Registra el AccesoProtegido y envía una alerta al supervisor de la UO
     * responsable de la Historia Social del ciudadano.
     */
    public function solicitarAcceso(int $ciudadanoId, string $justificacion): void
    {
        if (strlen(trim($justificacion)) < 20) {
            $this->addError('justificacion', 'La justificación debe tener al menos 20 caracteres.');

            return;
        }

        $historia = HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)
            ->where('ciudadano_id', $ciudadanoId)
            ->where('estado', '!=', 'cerrada')
            ->latest()
            ->first();

        // Registrar la solicitud
        AccesoProtegido::create([
            'usuario_id' => Auth::id(),
            'ciudadano_id' => $ciudadanoId,
            'solicitante_id' => Auth::id(),
            'justificacion' => $justificacion,
            'estado' => 'pendiente',
        ]);

        // Enviar alerta al supervisor de la UO responsable
        if ($historia !== null) {
            Alerta::create([
                'tipo' => TipoAlerta::Alerta,
                'origen_type' => AccesoProtegido::class,
                'origen_id' => 0,
                'titulo' => 'Solicitud de acceso a ciudadano protegido',
                'cuerpo' => 'El profesional '.Auth::user()->name.' solicita acceso. Justificación: '.$justificacion,
                'destinatario_type' => DestinatarioType::RolUo,
                'destinatario_rol' => 'supervision',
                'destinatario_uo_id' => $historia->unidad_organizativa_id,
                'estado' => 'pendiente',
            ]);
        }

        $this->modalSolicitud = false;
        $this->ciudadanoSolicitudId = null;
        $this->justificacion = '';
    }

    public function render(): View
    {
        return view('intervencion::livewire.buscar-ciudadano-page');
    }
}
