{{--
    Avisos breves de la superficie operativa: escucha el evento de navegador `toast`
    ({ message, type }) que lanzan los componentes con `$this->dispatch('toast', …)`, incluidas
    las reglas de negocio rechazadas en una acción (App\Support\Errores\AvisoExcepcionDominio).
    Tipos: `danger`, `success`; cualquier otro se pinta como informativo.
    También pinta el aviso que deja en la sesión una acción con redirección (`with('toast', …)`),
    como guardar la página de inicio.
--}}
<div class="toast-container position-fixed bottom-0 end-0 p-3"
     x-data="{ avisos: [] }"
     @if(session('toast'))
     x-init="avisos.push({ id: 0, mensaje: @js(session('toast')), tipo: 'success' }); setTimeout(() => avisos = avisos.filter(a => a.id !== 0), 8000)"
     @endif
     x-on:toast.window="
        const aviso = { id: Date.now() + Math.random(), mensaje: $event.detail.message, tipo: $event.detail.type ?? 'info' };
        avisos.push(aviso);
        setTimeout(() => avisos = avisos.filter(a => a.id !== aviso.id), 8000);
     ">
    <template x-for="aviso in avisos" x-bind:key="aviso.id">
        <div>
            <div x-show="aviso.tipo === 'danger'" class="toast show text-bg-danger border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body" x-text="aviso.mensaje"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Cerrar"
                            x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)"></button>
                </div>
            </div>
            <div x-show="aviso.tipo === 'success'" class="toast show text-bg-success border-0 mb-2" role="status" aria-live="polite" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body" x-text="aviso.mensaje"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Cerrar"
                            x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)"></button>
                </div>
            </div>
            <div x-show="aviso.tipo !== 'danger' && aviso.tipo !== 'success'" class="toast show border-0 mb-2" role="status" aria-live="polite" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body" x-text="aviso.mensaje"></div>
                    <button type="button" class="btn-close me-2 m-auto" aria-label="Cerrar"
                            x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)"></button>
                </div>
            </div>
        </div>
    </template>
</div>
