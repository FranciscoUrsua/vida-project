{{-- «Mis derivaciones»: encargos profesionales asignados (EP-10, 2026-11-encargo-profesional.md) --}}
<div class="op-page">
    <section class="p-3">

        <h2 class="h6 fw-semibold mb-2">En curso</h2>
        @if($this->activos->isEmpty())
            <x-op.empty icono="clipboard-document-list">No tienes encargos en curso.</x-op.empty>
        @else
            @include('intervencion::livewire.partials.tabla-encargos', ['filas' => $this->activos, 'titulo' => 'Encargos en curso', 'cerrados' => false])
        @endif

        <h2 class="h6 fw-semibold mt-4 mb-2">Histórico</h2>
        @if($this->historico->isEmpty())
            <x-op.empty icono="archive-box">Todavía no has cerrado ningún encargo.</x-op.empty>
        @else
            @include('intervencion::livewire.partials.tabla-encargos', ['filas' => $this->historico, 'titulo' => 'Encargos cerrados', 'cerrados' => true])
        @endif
    </section>
</div>
