{{-- Estado vacío: icono Heroicons (outline, opcional) y mensaje centrados. Uso: <x-op.empty icono="users">No hay profesionales.</x-op.empty> --}}
@props(['icono' => null])
<div {{ $attributes->merge(['class' => 'op-empty']) }}>
    @if($icono)
        <x-dynamic-component :component="'heroicon-o-' . $icono" class="op-empty__icon" aria-hidden="true"/>
    @endif
    <p class="op-empty__text">{{ $slot }}</p>
</div>
