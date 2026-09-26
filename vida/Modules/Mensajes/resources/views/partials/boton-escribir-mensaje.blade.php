{{--
    Botón discreto «Escribir mensaje» que abre el panel de redacción con el
    elemento que se está viendo como contexto. Debe ir dentro de un componente
    Livewire. El panel resuelve y autoriza el contexto en el servidor.

    @param string $tipo  historia | ficha | plan (TipoContextoMensaje)
    @param int    $id    ID del elemento
--}}
<button type="button"
        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
        wire:click="$dispatch('abrir-panel-redaccion', { contexto: { tipo: '{{ $tipo }}', id: {{ (int) $id }} } })">
    <x-heroicon-o-chat-bubble-left-ellipsis class="icon-14" aria-hidden="true"/>
    Escribir mensaje
</button>
