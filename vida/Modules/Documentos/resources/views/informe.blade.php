<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="{{ base_path('Modules/Documentos/resources/css/informe-pdf.css') }}">
</head>
<body style="--informe-font-family: '{{ $tipografia['familia'] ?? 'DejaVu Sans' }}'; --informe-font-size: {{ $tipografia['tamano_base_pt'] ?? 10 }}pt;">

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
                <table class="datos-automaticos">
                    @foreach($datos as $etiqueta => $valor)
                    <tr>
                        <th>{{ $etiqueta }}</th>
                        <td>{{ $valor }}</td>
                    </tr>
                    @endforeach
                </table>
            @else
                <p><em>Sin datos disponibles.</em></p>
            @endif
        @else
            {{-- Saneado (SaneadorHtmlInforme: solo etiquetas de formato) y con merge tags sustituidos --}}
            <div class="texto-libre">{!! $textos[$seccion['id']] ?? '' !!}</div>
        @endif
    </div>
    @endforeach

    {{-- Pie --}}
    @if(!empty($estilo['html_pie']))
    <div class="pie">{!! $estilo['html_pie'] !!}</div>
    @endif

</body>
</html>
