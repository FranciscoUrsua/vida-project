{{--
    Toasts persistentes de alertas pendientes (modulo-mensajes.md §4.2).
    Sin auto-dismiss. Minimizar guarda id => instante en sessionStorage y el
    toast reaparece a los 30 minutos. Se muestran como mucho
    AlertaToast::MAXIMO_VISIBLES; el resto se resume en una línea.
--}}
<div wire:poll.60s
     x-data="{
        clave: 'vida.alertas.minimizadas',
        espera: 30 * 60 * 1000,
        maximo: {{ \Modules\Mensajes\Livewire\AlertaToast::MAXIMO_VISIBLES }},
        minimizadas: {},
        ahora: Date.now(),
        init() {
            try { this.minimizadas = JSON.parse(sessionStorage.getItem(this.clave) || '{}') || {}; } catch (e) { this.minimizadas = {}; }
            // Reloj para que los minimizados reaparezcan sin esperar al polling
            setInterval(() => { this.ahora = Date.now() }, 60 * 1000);
        },
        guardar() {
            try { sessionStorage.setItem(this.clave, JSON.stringify(this.minimizadas)); } catch (e) {}
        },
        oculta(id) {
            return this.minimizadas[id] !== undefined && this.ahora - this.minimizadas[id] < this.espera;
        },
        visibles() {
            return this.$wire.alertaIds.filter(id => ! this.oculta(id));
        },
        mostrar(id) {
            return this.visibles().slice(0, this.maximo).includes(id);
        },
        restantes() {
            return Math.max(0, this.visibles().length - this.maximo);
        },
        minimizar(id) {
            this.minimizadas[id] = Date.now();
            this.guardar();
        },
        minimizarTodas() {
            this.visibles().forEach(id => { this.minimizadas[id] = Date.now() });
            this.guardar();
        },
     }"
     class="toast-container position-fixed end-0 p-3 mensajes-toasts"
     aria-live="assertive">
    @foreach($this->alertas as $alerta)
        <div class="toast show border-danger"
             role="alert"
             aria-atomic="true"
             wire:key="alerta-toast-{{ $alerta->id }}"
             x-show="mostrar({{ $alerta->id }})"
             x-cloak>
            <div class="toast-header text-danger gap-2">
                <x-heroicon-s-exclamation-triangle class="icon-16 flex-shrink-0" aria-hidden="true"/>
                <strong class="me-auto">Alerta</strong>
                @if($alerta->expira_en)
                    <small class="{{ $alerta->expira_en->isPast() ? 'fw-semibold' : 'text-body-secondary' }}">
                        {{ $alerta->expira_en->isPast() ? 'Plazo vencido' : 'Vence '.$alerta->expira_en->diffForHumans() }}
                    </small>
                @endif
                <button type="button"
                        class="btn btn-sm btn-link text-body-secondary p-0 ms-1"
                        x-on:click="minimizar({{ $alerta->id }})"
                        title="Minimizar: volverá a aparecer en 30 minutos"
                        aria-label="Minimizar la alerta «{{ $alerta->titulo }}». Volverá a aparecer en 30 minutos">
                    <x-heroicon-o-minus class="icon-16" aria-hidden="true"/>
                </button>
            </div>

            <div class="toast-body">
                <p class="fw-semibold mb-1">{{ $alerta->titulo }}</p>
                <p class="small mb-2">{{ $alerta->cuerpo }}</p>

                @if($alertaConfirmandoId === $alerta->id)
                    <p class="small text-body-secondary mb-2">¿Confirmas que la has leído?</p>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" wire:click="cancelarReconocimiento" class="btn btn-sm btn-outline-secondary">Cancelar</button>
                        <button type="button" wire:click="reconocer" class="btn btn-sm btn-primary">Sí, reconocer</button>
                    </div>
                @else
                    <div class="d-flex justify-content-end align-items-center gap-2">
                        @if($url = $this->enlaceOrigen($alerta))
                            <a href="{{ $url }}" class="btn btn-sm btn-link">Ver origen</a>
                        @endif
                        <button type="button"
                                wire:click="confirmarReconocimiento({{ $alerta->id }})"
                                class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                            <x-heroicon-o-check class="icon-14" aria-hidden="true"/> Reconocer
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if($this->alertas->count() > \Modules\Mensajes\Livewire\AlertaToast::MAXIMO_VISIBLES)
        <div class="toast show" x-show="restantes() > 0" x-cloak>
            <div class="toast-body d-flex align-items-center gap-2">
                <span class="me-auto small">
                    Y <strong x-text="restantes()"></strong> <span x-text="restantes() === 1 ? 'alerta pendiente más' : 'alertas pendientes más'"></span>
                </span>
                @if($urlBandeja)
                    <a href="{{ $urlBandeja }}" class="btn btn-sm btn-link">Ver todas</a>
                @endif
                <button type="button" class="btn btn-sm btn-outline-secondary" x-on:click="minimizarTodas()">Minimizar todas</button>
            </div>
        </div>
    @endif
</div>
