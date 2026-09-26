{{-- Bandeja unificada: una pestaña por entrada de menú (Alertas, Avisos, Mensajes) --}}
<div class="op-page">

    <ul class="nav nav-tabs px-3 pt-2">
        @foreach(\Modules\Mensajes\Http\Livewire\BandejaAlertasYMensajes::PESTANAS as $clave => $etiqueta)
            <li class="nav-item">
                <a href="{{ route($ruta, $clave) }}"
                   class="nav-link d-inline-flex align-items-center gap-2 {{ $pestana === $clave ? 'active' : '' }}"
                   @if($pestana === $clave) aria-current="page" @endif>
                    {{ $etiqueta }}
                    @if($this->contadores[$clave] > 0)
                        <span class="badge rounded-pill {{ $clave === 'alertas' ? 'text-bg-danger' : 'text-bg-secondary' }}">
                            {{ $this->contadores[$clave] }}
                        </span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <section class="p-3">
        @if($pestana === 'mensajes')
            <livewire:mensajes-bandeja-mensajes :key="'bandeja-mensajes'" />
        @else
            <livewire:mensajes-bandeja-alertas :tipo="$pestana === 'alertas' ? 'alerta' : 'aviso'" :key="'bandeja-'.$pestana" />
        @endif
    </section>

</div>
