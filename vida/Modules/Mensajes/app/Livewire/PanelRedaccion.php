<?php

namespace Modules\Mensajes\Livewire;

use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Mensajes\Services\ContextoMensajeService;
use Modules\Mensajes\Services\MensajeriaService;
use Modules\Usuarios\Models\Cargo;

/**
 * Panel flotante para escribir un mensaje nuevo desde cualquier pantalla
 * (`modulo-mensajes.md` §4.3). Está en el layout operativo y se abre con el
 * evento `abrir-panel-redaccion`.
 *
 * Con contexto (expediente, ficha de valoración o plan) pre-rellena el
 * elemento vinculado y el ciudadano, y sugiere al autor del elemento como
 * destinatario en un chip gris «Sin confirmar»: la sugerencia es orientativa
 * y el envío falla hasta que se confirma o se elige a otra persona.
 *
 * Todo lo que llega del navegador se vuelve a comprobar en el servidor: el
 * contexto con ContextoMensajeService y cada ciudadano con
 * `CiudadanoPolicy::view`. Contexto, destinatarios y ciudadanos van con
 * #[Locked] y solo cambian mediante métodos.
 *
 * No hay adjuntos: los documentos pertenecen a la Historia Social.
 *
 * @property-read Collection<int, User> $resultadosDestinatario
 * @property-read Collection<int, Ciudadano> $resultadosCiudadano
 * @property-read Collection<int, Ciudadano> $ciudadanosSeleccionados
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Cargo> $cargos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UnidadOrganizativa> $uos
 */
class PanelRedaccion extends Component
{
    /** Panel visible. */
    public bool $abierto = false;

    /** Elemento vinculado, resuelto en el servidor. @var array{tipo: string, id: int, etiqueta: string, url: string}|null */
    #[Locked]
    public ?array $contexto = null;

    /** Destinatario sugerido, pendiente de confirmar. @var array{id: int, nombre: string, cargo: string|null}|null */
    #[Locked]
    public ?array $destinatarioSugerido = null;

    /** Destinatario definitivo. @var array{id: int, nombre: string, cargo: string|null}|null */
    #[Locked]
    public ?array $destinatarioConfirmado = null;

    /** Texto de búsqueda de destinatario. */
    public string $busquedaDestinatario = '';

    /** Filtro por cargo, para cuando no se conoce el nombre. */
    public ?int $filtroCargoId = null;

    /** Filtro por UO. */
    public ?int $filtroUoId = null;

    /** Asunto del mensaje. */
    public string $asunto = '';

    /** Texto del mensaje. */
    public string $cuerpo = '';

    /** Ciudadanos referenciados. @var list<int> */
    #[Locked]
    public array $ciudadanoIds = [];

    /** Texto de búsqueda de ciudadano. */
    public string $busquedaCiudadano = '';

    /**
     * Abre el panel, opcionalmente con el elemento desde el que se escribe.
     *
     * Solo se usan tipo e id del contexto; lo demás se resuelve en el servidor
     * y se ignora si el usuario no puede ver el elemento.
     *
     * @param array<string, mixed> $contexto ['tipo' => 'historia'|'ficha'|'plan', 'id' => int]
     * @param int|null $sugerirDestinatarioId Sugerencia explícita; si falta, el autor del elemento.
     * @return void
     */
    #[On('abrir-panel-redaccion')]
    public function abrir(array $contexto = [], ?int $sugerirDestinatarioId = null): void
    {
        $this->reset();
        $this->resetValidation();
        $this->abierto = true;

        $resuelto = isset($contexto['tipo'], $contexto['id'])
            ? app(ContextoMensajeService::class)->resolver((string) $contexto['tipo'], (int) $contexto['id'], Auth::user())
            : null;

        if ($resuelto !== null) {
            $this->contexto = [
                'tipo' => $resuelto['tipo'],
                'id' => $resuelto['id'],
                'etiqueta' => $resuelto['etiqueta'],
                'url' => $resuelto['url'],
            ];

            if ($resuelto['ciudadano_id'] !== null) {
                $this->ciudadanoIds = [$resuelto['ciudadano_id']];
            }

            $sugerirDestinatarioId ??= $resuelto['autor_id'];
        }

        if ($sugerirDestinatarioId !== null && $sugerirDestinatarioId !== Auth::id()) {
            $this->destinatarioSugerido = $this->datosUsuario($sugerirDestinatarioId);
        }
    }

    /**
     * Cierra el panel sin enviar.
     *
     * @return void
     */
    public function cerrar(): void
    {
        $this->abierto = false;
    }

    /**
     * Confirma el destinatario sugerido (el chip pasa de gris a normal).
     *
     * @return void
     */
    public function confirmarSugerencia(): void
    {
        if ($this->destinatarioSugerido !== null) {
            $this->destinatarioConfirmado = $this->destinatarioSugerido;
            $this->destinatarioSugerido = null;
        }
    }

    /**
     * Quita el destinatario, sugerido o confirmado, para buscar otro.
     *
     * @return void
     */
    public function quitarDestinatario(): void
    {
        $this->destinatarioSugerido = null;
        $this->destinatarioConfirmado = null;
    }

    /**
     * Elige un destinatario de los resultados de búsqueda.
     *
     * @param int $usuarioId ID del usuario.
     * @return void
     */
    public function seleccionarDestinatario(int $usuarioId): void
    {
        if ($usuarioId === Auth::id()) {
            return;
        }

        $datos = $this->datosUsuario($usuarioId);

        if ($datos !== null) {
            $this->destinatarioConfirmado = $datos;
            $this->destinatarioSugerido = null;
            $this->reset(['busquedaDestinatario', 'filtroCargoId', 'filtroUoId']);
        }
    }

    /**
     * Quita el elemento vinculado.
     *
     * @return void
     */
    public function quitarContexto(): void
    {
        $this->contexto = null;
    }

    /**
     * Añade un ciudadano a las referencias, si el usuario puede verlo.
     *
     * @param int $ciudadanoId ID del ciudadano.
     * @return void
     */
    public function agregarCiudadano(int $ciudadanoId): void
    {
        $ciudadano = Ciudadano::findOrFail($ciudadanoId);
        Gate::authorize('view', $ciudadano);

        if (! in_array($ciudadanoId, $this->ciudadanoIds, true)) {
            $this->ciudadanoIds[] = $ciudadanoId;
        }

        $this->busquedaCiudadano = '';
    }

    /**
     * Quita un ciudadano de las referencias.
     *
     * @param int $ciudadanoId ID del ciudadano.
     * @return void
     */
    public function quitarCiudadano(int $ciudadanoId): void
    {
        $this->ciudadanoIds = array_values(array_diff($this->ciudadanoIds, [$ciudadanoId]));
    }

    /**
     * Envía el mensaje: crea el hilo con su elemento vinculado y cierra el panel.
     *
     * @param MensajeriaService $mensajeriaService Servicio de mensajería.
     * @return void
     *
     * @throws ValidationException Si falta el destinatario confirmado, el asunto o el cuerpo.
     */
    public function enviar(MensajeriaService $mensajeriaService): void
    {
        $this->validate([
            'asunto' => ['required', 'string', 'max:255'],
            'cuerpo' => ['required', 'string', 'max:10000'],
        ]);

        if ($this->destinatarioConfirmado === null) {
            throw ValidationException::withMessages([
                'destinatario' => $this->destinatarioSugerido !== null
                    ? 'Confirma el destinatario sugerido o elige a otra persona.'
                    : 'Elige a quién va el mensaje.',
            ]);
        }

        $destinatario = User::findOrFail($this->destinatarioConfirmado['id']);

        // Las referencias se vuelven a autorizar al enviar: el acceso puede haber cambiado.
        $ciudadanoIds = Ciudadano::whereIn('id', $this->ciudadanoIds)->get()
            ->filter(fn (Ciudadano $c) => Gate::allows('view', $c))
            ->pluck('id')
            ->all();

        $hilo = $mensajeriaService->crearHilo(
            remitente: Auth::user(),
            destinatario: $destinatario,
            asunto: $this->asunto,
            cuerpo: $this->cuerpo,
            ciudadanoIds: $ciudadanoIds,
            contexto: $this->contexto === null ? null : ['tipo' => $this->contexto['tipo'], 'id' => $this->contexto['id']],
        );

        $this->reset();
        $this->dispatch('hilo-creado', hiloId: $hilo->id);
    }

    /**
     * Profesionales que coinciden con la búsqueda por nombre o con los filtros
     * de cargo y UO. Excluye al propio usuario.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function resultadosDestinatario(): Collection
    {
        $termino = trim($this->busquedaDestinatario);

        if (mb_strlen($termino) < 2 && ! $this->filtroCargoId && ! $this->filtroUoId) {
            return collect();
        }

        $consulta = User::query()
            ->where('id', '!=', Auth::id())
            ->with(['profesional.cargo', 'adscripcionesVigentes.unidadOrganizativa']);

        if (mb_strlen($termino) >= 2) {
            $like = '%'.$termino.'%';
            $consulta->where(function (Builder $q) use ($like) {
                $q->where('email', 'ilike', $like)
                    ->orWhereHas('profesional', fn (Builder $p) => $p
                        ->whereRaw("concat_ws(' ', nombre, apellido1, apellido2) ilike ?", [$like]));
            });
        }

        if ($this->filtroCargoId) {
            $consulta->whereHas('profesional', fn (Builder $p) => $p->where('cargo_id', $this->filtroCargoId));
        }

        if ($this->filtroUoId) {
            $consulta->whereHas('adscripciones', fn (Builder $a) => $a
                ->where('unidad_organizativa_id', $this->filtroUoId)
                ->vigentes());
        }

        return $consulta->limit(20)->get();
    }

    /**
     * Ciudadanos que coinciden con la búsqueda y que el usuario puede ver.
     *
     * El nombre está cifrado: se filtra en PHP sobre un máximo de 500
     * registros, como en la búsqueda de ciudadanos de Intervención.
     *
     * @return Collection<int, Ciudadano>
     */
    #[Computed]
    public function resultadosCiudadano(): Collection
    {
        $termino = mb_strtolower(trim($this->busquedaCiudadano));

        if (mb_strlen($termino) < 2) {
            return collect();
        }

        return Ciudadano::query()
            ->where('activo', true)
            ->whereNotIn('id', $this->ciudadanoIds)
            ->limit(500)
            ->get()
            ->filter(fn (Ciudadano $c) => str_contains(mb_strtolower($this->nombreCiudadano($c).' '.($c->alias ?? '')), $termino))
            ->filter(fn (Ciudadano $c) => Gate::allows('view', $c))
            ->take(10)
            ->values();
    }

    /**
     * Ciudadanos referenciados, para mostrarlos como chips.
     *
     * @return Collection<int, Ciudadano>
     */
    #[Computed]
    public function ciudadanosSeleccionados(): Collection
    {
        return Ciudadano::whereIn('id', $this->ciudadanoIds)->get();
    }

    /**
     * Cargos activos para el filtro.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Cargo>
     */
    #[Computed]
    public function cargos(): \Illuminate\Database\Eloquent\Collection
    {
        return Cargo::where('activo', true)->orderBy('nombre')->get();
    }

    /**
     * UO activas para el filtro.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, UnidadOrganizativa>
     */
    #[Computed]
    public function uos(): \Illuminate\Database\Eloquent\Collection
    {
        return UnidadOrganizativa::where('activa', true)->orderBy('nombre')->get();
    }

    /**
     * Nombre completo del ciudadano para listas y chips.
     *
     * @param Ciudadano $ciudadano Ciudadano.
     * @return string
     */
    public function nombreCiudadano(Ciudadano $ciudadano): string
    {
        return trim(($ciudadano->nombre ?? '').' '.($ciudadano->apellido1 ?? '').' '.($ciudadano->apellido2 ?? ''));
    }

    /**
     * Renderiza el panel.
     *
     * @return View
     */
    public function render(): View
    {
        return view('mensajes::livewire.panel-redaccion');
    }

    /**
     * Datos que muestra el chip de destinatario, o null si el usuario no existe.
     *
     * @return array{id: int, nombre: string, cargo: string|null}|null
     */
    private function datosUsuario(int $usuarioId): ?array
    {
        $usuario = User::with('profesional.cargo')->find($usuarioId);

        return $usuario === null ? null : [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre_completo,
            'cargo' => $usuario->profesional?->cargo?->nombre,
        ];
    }
}
