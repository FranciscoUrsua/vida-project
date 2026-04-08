<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: '{{ $tipografia['familia'] ?? 'DejaVu Sans' }}', sans-serif;
            font-size: {{ $tipografia['tamano_base_pt'] ?? 10 }}pt;
            margin: 0;
            padding: 0;
        }
        .cabecera {
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .cabecera table { width: 100%; }
        .cabecera .logo { width: 120px; }
        .cabecera .datos-unidad { padding-left: 15px; }
        .cabecera .nombre-unidad { font-size: 14pt; font-weight: bold; }
        .seccion { margin-bottom: 20px; }
        .seccion h3 { font-size: 11pt; border-bottom: 1px solid #999; padding-bottom: 4px; }
        .pie {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #ccc;
            padding-top: 6px;
            font-size: 8pt;
            color: #666;
        }
    </style>
</head>
<body>

    {{-- Cabecera --}}
    <div class="cabecera">
        <table>
            <tr>
                @if(!empty($estilo['logo_cabecera']))
                <td class="logo">
                    <img src="{{ $estilo['logo_cabecera'] }}" style="max-height:60px;" alt="Logo">
                </td>
                @endif
                <td class="datos-unidad">
                    @if(!empty($estilo['nombre_unidad_cabecera']))
                        <div class="nombre-unidad">{{ $estilo['nombre_unidad_cabecera'] }}</div>
                    @endif
                    @if(!empty($estilo['direccion_cabecera']))
                        <div>{{ $estilo['direccion_cabecera'] }}</div>
                    @endif
                    @if(!empty($estilo['telefono_cabecera']))
                        <div>Tel: {{ $estilo['telefono_cabecera'] }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Secciones --}}
    @foreach($plantilla->secciones ?? [] as $seccion)
    <div class="seccion">
        <h3>{{ $seccion['titulo'] ?? '' }}</h3>
        @if(($seccion['tipo'] ?? '') === 'automatico')
            @php $datos = $datosAuto[$seccion['id']] ?? [] @endphp
            @if(!empty($datos))
                <pre>{{ json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @else
                <p><em>Sin datos disponibles.</em></p>
            @endif
        @else
            <p>{{ $contenido[$seccion['id']] ?? '' }}</p>
        @endif
    </div>
    @endforeach

    {{-- Pie --}}
    @if(!empty($estilo['html_pie']))
    <div class="pie">{!! $estilo['html_pie'] !!}</div>
    @endif

</body>
</html>
