@props(['usuario'])

@php
    $palabras = explode(' ', trim($usuario->name));
    $iniciales = '';
    if (isset($palabras[0])) $iniciales .= strtoupper(substr($palabras[0], 0, 1));
    if (isset($palabras[1])) $iniciales .= strtoupper(substr($palabras[1], 0, 1));

    $colores = ['bg-teal', 'bg-blue', 'bg-purple', 'bg-amber'];
    $color = $colores[$usuario->id % count($colores)];
@endphp

<div class="avatar {{ $color }}" title="{{ $usuario->name }}">
    {{ $iniciales }}
</div>
