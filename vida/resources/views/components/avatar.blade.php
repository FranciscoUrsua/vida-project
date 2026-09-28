{{-- Avatar con iniciales (componente de catálogo `op-avatar`). Recibe un usuario o un nombre. --}}
@props(['usuario' => null, 'nombre' => null, 'pequeno' => false])

@php
    $texto = trim((string) ($nombre ?? $usuario?->name ?? ''));
    $palabras = preg_split('/\s+/', $texto) ?: [];
    $iniciales = mb_strtoupper(mb_substr($palabras[0] ?? '', 0, 1) . mb_substr($palabras[1] ?? '', 0, 1));

    // Color estable por persona: el id del usuario o, sin usuario, el propio nombre
    $colores = [
        'bg-primary-subtle text-primary-emphasis',
        'bg-success-subtle text-success-emphasis',
        'bg-warning-subtle text-warning-emphasis',
        'bg-info-subtle text-info-emphasis',
    ];
    $semilla = $usuario?->id ?? crc32($texto);
    $color = $colores[$semilla % count($colores)];
@endphp

<span {{ $attributes->class(['op-avatar', 'op-avatar--sm' => $pequeno, $color]) }} title="{{ $texto }}">{{ $iniciales }}</span>
