# VIDA 360 — Informe de Deuda Técnica de Front-end

**Generado:** 20/06/2026 15:18
**Analizado:** ./resources/views + ./Modules

---

> Este informe detecta cuatro categorías de problemas en las vistas Blade/Livewire:
> 1. CSS ad-hoc (bloques `<style>` y atributos `style=""` estructurales)
> 2. Colores hardcodeados (hex, rgb, rgba) fuera de los tokens VIDA
> 3. JS innecesario para navegación (donde un `<a href>` bastaría)
> 4. Clases Bootstrap residuales (incompatibles con el design system VIDA)

---


## 1. Bloques `<style>` en vistas Blade

⚠️ **7 ocurrencia(s) encontrada(s):**

~~- `resources/views/errors/sin-rol.blade.php:86` — `<style>`~~
~~- `resources/views/inicio.blade.php:86` — `<style>`~~
~~- `resources/views/welcome.blade.php:86` — `<style>`~~
~~- `resources/views/auth/login.blade.php:86` — `<style>`~~
~~- `resources/views/auth/onboarding.blade.php:86` — `<style>`~~
~~- `Modules/Documentos/resources/views/informe.blade.php:86` — `<style>`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:86` — `<style>`~~

## 2. Atributos `style=""` estructurales

⚠️ **373 ocurrencia(s) — estilos inline estructurales (no dinámicos):**

~~- `resources/views/errors/sin-rol.blade.php:114` — `<i data-lucide="lock" style="width:40px;height:40px;" aria-hidden="true"></i>`~~
~~- `resources/views/livewire/centros/selector-prestaciones-centro.blade.php:114` — `style="max-height: 360px;">`~~
~~- `resources/views/livewire/centros/selector-prestaciones-centro.blade.php:114` — `style="max-height: 360px;">`~~
~~- `resources/views/welcome.blade.php:114` — `<g style="mix-blend-mode: hard-light" class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:op`~~
~~- `resources/views/welcome.blade.php:114` — `<g style="mix-blend-mode: plus-darker" class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:o`~~
~~- `resources/views/welcome.blade.php:114` — `<g style="mix-blend-mode: hard-light" class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:op`~~
~~- `resources/views/welcome.blade.php:114` — `<g style="mix-blend-mode: hard-light" class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:op`~~
~~- `resources/views/welcome.blade.php:114` — `<g class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:opacity-0 starting:translate-y-4" sty`~~
~~- `resources/views/welcome.blade.php:114` — `<g class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:opacity-0 starting:translate-y-4" sty`~~
~~- `resources/views/welcome.blade.php:114` — `<g class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:opacity-0 starting:translate-y-4" sty`~~
~~- `resources/views/layouts/operativo.blade.php:114` — `style="width:16px;height:16px;"`~~
~~- `resources/views/layouts/operativo.blade.php:114` — `:style="abierto ? 'transform:rotate(180deg)' : ''"`~~
~~- `resources/views/layouts/operativo.blade.php:114` — `style="display:none;">`~~
~~- `resources/views/layouts/operativo.blade.php:114` — `<i data-lucide="log-out" style="width:16px;height:16px;" aria-hidden="true"></i>`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="display: flex; flex-direction: column; height: 100vh; overflow: hidden;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="background: #fff; border-bottom: 1px solid #E5E3F5; padding: 0 1.25rem; display: flex; align-items: stretch;`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<h1 style="font-size: 1rem; font-weight: 700; margin: 0; color: #1D160E; align-self: center; padding: 0.75rem 1.5rem 0.7`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<i data-lucide="pencil" style="width:14px;height:14px;" aria-hidden="true"></i>`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="flex: 1; display: flex; overflow: hidden;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="width: 320px; flex-shrink: 0; border-right: 1px solid #E5E3F5; overflow-y: auto; background: #FAFAFA;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="font-size: 0.72rem; color: #993C1D; margin-top: 0.15rem;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="padding: 2rem; text-align: center; color: #9CA3AF; font-size: 0.82rem;">Sin alertas pendientes</div>`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="padding: 2rem; text-align: center; color: #9CA3AF; font-size: 0.82rem;">Sin avisos</div>`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="flex: 1; min-width: 0;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="font-size: 0.75rem; color: #6B7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-t`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="padding: 2rem; text-align: center; color: #9CA3AF; font-size: 0.82rem;">Sin mensajes</div>`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #9CA3AF; font-size: 0.875rem;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="padding: 1.25rem; overflow-y: auto; flex: 1;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="background: #FAECE7; border: 1px solid #F0997B; border-radius: 8px; padding: 0.6rem 1rem; margin-bottom: 1re`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<i class="ti ti-clock" style="color: #993C1D; font-size: 1.1rem;"></i>`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<span style="font-size: 0.82rem; color: #712B13; font-weight: 600;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `style="background: #534AB7; border: none; color: #fff; padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.8rem; font`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `style="background: #fff; border: 1px solid #E5E3F5; color: #374151; padding: 0.4rem 1rem; border-radius: 6px; font-size:`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.6rem;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="border-top: 1px solid #E5E3F5; padding: 0.75rem 1.25rem; background: #fff;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem; align-items: flex-end;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `style="flex: 1; border: 1px solid #E5E3F5; border-radius: 6px; padding: 0.5rem; font-size: 0.85rem; resize: none; box-si`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `style="background: #534AB7; border: none; color: #fff; padding: 0.55rem 1rem; border-radius: 6px; font-size: 0.8rem; fon`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content:`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="margin-bottom: 0.85rem; position: relative;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<i data-lucide="check-circle" style="width:12px;height:12px;" aria-hidden="true"></i>`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="margin-bottom: 0.85rem;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="margin-bottom: 1rem;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem; justify-content: flex-end;">`~~
~~- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:114` — `<i data-lucide="send" style="width:14px;height:14px;" aria-hidden="true"></i>`~~
~~- `Modules/Mensajes/resources/views/livewire/hilo-mensajes.blade.php:114` — `<div class="p-3 overflow-auto" style="max-height: 50vh;">`~~
~~- `Modules/Mensajes/resources/views/livewire/hilo-mensajes.blade.php:114` — `style="max-width: 70%;">`~~
~~- `Modules/Mensajes/resources/views/livewire/hilo-mensajes.blade.php:114` — `<div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">`~~
~~- `Modules/Mensajes/resources/views/livewire/bandeja-mensajes.blade.php:114` — `<div class="row g-0" style="min-height: 70vh;">`~~
~~- `Modules/Mensajes/resources/views/livewire/bandeja-mensajes.blade.php:114` — `<div class="list-group list-group-flush overflow-auto" style="max-height: 65vh;">`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `<div style="display: flex; flex-direction: column; height: calc(100vh - 56px); overflow: hidden;">`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `<button wire:click="navegarAnterior" class="btn btn-sm btn-outline-secondary" aria-label="Período anterior" style="paddi`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `<i data-lucide="chevron-left" style="width:16px;height:16px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `<button wire:click="navegarSiguiente" class="btn btn-sm btn-outline-secondary" aria-label="Período siguiente" style="pad`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `<i data-lucide="chevron-right" style="width:16px;height:16px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `<button wire:click="irAHoy" class="btn btn-sm btn-outline-primary" style="font-size: 0.8rem;">Hoy</button>`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `style="font-size: 0.8rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `style="font-size: 0.8rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `style="font-size: 0.8rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `<div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; align-items: start;">`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `<div style="overflow-x: auto;">`~~
~~- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:114` — `<table style="width: 100%; border-collapse: collapse; min-width: 600px;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ver-ficha-page.blade.php:114` — `<div style="padding: 1.5rem; max-width: 760px; margin: 0 auto;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ver-ficha-page.blade.php:114` — `<div style="margin-bottom: 1rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ver-ficha-page.blade.php:114` — `<i data-lucide="arrow-left" style="width:14px;height:14px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ver-ficha-page.blade.php:114` — `<div style="margin-bottom: 1.5rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ver-ficha-page.blade.php:114` — `<div style="display: flex; flex-direction: column; gap: 1rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; flex-direction: column; height: calc(100vh - 56px); overflow: hidden;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div class="ciudadano-layout" style="flex: 1; min-height: 0;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div class="ciudadano-header-left" style="padding: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="arrow-left" style="width:12px;height:12px;" aria-hidden="true"></i> Mis casos`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-left: auto; display: flex; align-items: center; gap: 0.3rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.3rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="chevron-right" style="width:12px;height:12px;"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<ul style="list-style: none; margin: 0 0 0.5rem; padding: 0; display: flex; flex-direction: column; gap: 0.2rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `style="color: inherit; text-decoration: none;"`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="users" style="width:14px;height:14px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="network" style="width:13px;height:13px;"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div class="ciudadano-header-right" style="padding: 1rem 1.25rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div wire:key="toolbox-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div class="ciudadano-body-left" style="padding: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; gap: 0.3rem; margin-bottom: 0.75rem; flex-wrap: wrap;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `style="display: flex; gap: 0.5rem; align-items: flex-start; margin-bottom: 0.6rem; cursor: pointer; padding: 0.4rem 0.5r`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="flex: 1; min-width: 0;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="alert-triangle" style="width:14px;height:14px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem; min-height: 0;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; gap: 1rem; margin-bottom: 0.75rem; font-size: 0.8rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<label style="display: flex; align-items: center; gap: 0.3rem; cursor: pointer;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="guardarEntrevista" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Guardar entrevista</but`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</b`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem; font-size: 0.8rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.3rem; cursor: pointer;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="guardarAnotacion" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Guardar anotación</butto`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</b`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="crearDerivacion" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Crear derivación</button>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</b`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="guardarGestion" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Guardar gestión</button>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</b`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Abrir en pantalla completa</a>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</b`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="margin-bottom: 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Abrir en pantalla completa</a>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</b`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem; margin-top: `~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; flex-direction: column; gap: 0.6rem; margin-top: 0.5rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div class="hs-modal__contenido" style="margin-top: 1rem;">{!! nl2br(e($modalApunteDatos['contenido'])) !!}</div>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem; align-items: center;">`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="external-link" style="width:13px;height:13px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="x" style="width:18px;height:18px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="check-circle" style="width:14px;height:14px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="plus" style="width:14px;height:14px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="shield-check" style="width:12px;height:12px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="shield-alert" style="width:12px;height:12px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="user-minus" style="width:13px;height:13px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="search" class="uc-modal__busqueda-icon" style="width:14px;height:14px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="x" style="width:18px;height:18px;"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="phone" style="width:13px;height:13px;"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="mail" style="width:13px;height:13px;"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="external-link" style="width:12px;height:12px;"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="x" style="width:18px;height:18px;"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="external-link" style="width:12px;height:12px;"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php:114` — `<i data-lucide="external-link" style="width:12px;height:12px;"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-escala-page.blade.php:114` — `<div style="padding: 1.5rem; max-width: 760px; margin: 0 auto;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-escala-page.blade.php:114` — `<div style="margin-bottom: 1rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-escala-page.blade.php:114` — `<i data-lucide="arrow-left" style="width:14px;height:14px;" aria-hidden="true"></i> Volver a la Historia Social`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-escala-page.blade.php:114` — `<div style="margin-bottom: 1.25rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-escala-page.blade.php:114` — `<div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-escala-page.blade.php:114` — `<button wire:click="guardar" class="btn btn-primary" style="font-size: 0.85rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<div style="padding: 1.5rem; max-width: 760px; margin: 0 auto;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<div style="margin-bottom: 1rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<i data-lucide="arrow-left" style="width:14px;height:14px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<div style="margin-bottom: 1.25rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<div style="margin-bottom: 1rem;"></div>`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<div style="margin-bottom: 1rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<div style="display: inline-flex; align-items: center; gap: 0.4rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<div style="display: flex; gap: 1rem; margin-top: 0.15rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<div style="display: inline-flex; align-items: center; gap: 0.4rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<i data-lucide="save" style="width:15px;height:15px; flex-shrink:0;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/registrar-valoracion-page.blade.php:114` — `<div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 1.25rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/sidebar.blade.php:114` — `<i data-lucide="calendar" class="op-nav-icon" style="width:18px;height:18px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/sidebar.blade.php:114` — `<i data-lucide="users" class="op-nav-icon" style="width:18px;height:18px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/sidebar.blade.php:114` — `<i data-lucide="bell" class="op-nav-icon" style="width:18px;height:18px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/sidebar.blade.php:114` — `<i data-lucide="search" class="op-nav-icon" style="width:18px;height:18px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/sidebar.blade.php:114` — `<i data-lucide="user-plus" class="op-nav-icon" style="width:18px;height:18px;" aria-hidden="true"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="arrow-left" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="file-down" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="check" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="x-circle" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="check-circle" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="user" style="width:15px;height:15px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="file-text" style="width:15px;height:15px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="database" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<div class="plan-ficha-title" @click="expandida = !expandida" style="cursor:pointer">`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="lock" style="width:11px;height:11px;opacity:.5"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="chevron-down" style="width:12px;height:12px"`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `x-bind:style="expandida ? 'transform:rotate(180deg)' : ''"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="x" style="width:12px;height:12px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="plus" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="pencil" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="list" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="target" style="width:15px;height:15px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="plus" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="edit" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="building" style="width:15px;height:15px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="plus" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<div class="plan-vacio" style="padding:16px">Ninguna actuación definida.</div>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<td><button class="plan-tb-btn"><i data-lucide="edit" style="width:13px;height:13px"></i></button></td>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="user-check" style="width:15px;height:15px"></i>`~~
- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="plus" style="width:13px;height:13px"></i>`
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="circle-check" style="width:14px;height:14px;flex-shrink:0;margin-top:1px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<button class="plan-tb-btn" style="margin-left:auto">`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="edit" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="users" style="width:15px;height:15px"></i>`~~
- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="plus" style="width:13px;height:13px"></i>`
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="x" style="width:13px;height:13px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="pen-line" style="width:15px;height:15px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<div class="plan-field" style="margin-top:12px; max-width:200px">`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="check-circle" style="width:14px;height:14px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="x" style="width:16px;height:16px"></i>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<span class="plan-chip plan-chip--on" style="font-size:10px;padding:1px 6px">Añadida</span>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<div class="plan-vacio" style="padding:16px">No hay valoraciones en el historial.</div>`~~
~~- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="check" style="width:13px;height:13px"></i>`~~
- `Modules/Intervencion/resources/views/livewire/plan-page.blade.php:114` — `<i data-lucide="check" style="width:13px;height:13px"></i>`
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<div style="display: flex; flex-direction: column; height: 100vh; overflow: hidden;">`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<form wire:submit.prevent="buscar" style="display: flex; gap: 0.5rem; align-items: flex-end;">`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<select wire:model="campoBusqueda" class="form-select form-select-sm" style="width: 140px; font-size: 0.8rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `style="flex: 1; font-size: 0.85rem;"`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<button type="submit" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<i data-lucide="search" style="width:14px;height:14px;" aria-hidden="true"></i> Buscar`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<div style="flex: 1; min-width: 0;">`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<div style="font-size: 0.9rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<div style="position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content:`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<div style="background: #fff; border-radius: 12px; padding: 1.5rem; max-width: 480px; width: 90%; box-shadow: 0 8px 32px`~~
~~- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:114` — `<div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<div style="display: flex; flex-direction: column; height: 100vh; overflow: hidden;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<div style="position: relative; display: flex; align-items: center;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<select wire:model.live="filtroSeguimiento" class="form-select form-select-sm" style="width: auto; font-size: 0.8rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<select wire:model.live="filtroPiso" class="form-select form-select-sm" style="width: auto; font-size: 0.8rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<select wire:model.live="filtroEsp" class="form-select form-select-sm" style="width: auto; font-size: 0.8rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `return '<th style="padding:0.5rem 0.75rem;">'`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `. 'style="background:none;border:none;padding:0;cursor:pointer;'`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `'<th style="padding:0.5rem 0.75rem;font-size:0.72rem;text-transform:uppercase;'`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<td style="padding: 0.6rem 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<td style="padding: 0.6rem 0.75rem;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<td style="padding: 0.6rem 0.75rem; text-align: center;">`~~
~~- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:114` — `<div style="display: flex; gap: 0.25rem;">`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:114` — `<ul style="margin: 3px 0 6px 16px;">`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:114` — `<th style="width:35%">Prestación</th>`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:114` — `<th style="width:40%">Concreción</th>`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:114` — `<th style="width:15%">Responsable</th>`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:114` — `<th style="width:10%">Inicio previsto</th>`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:114` — `<th style="width:60%">Compromiso</th>`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:114` — `<th style="width:30%">Recurso relacionado</th>`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:114` — `<th style="width:10%">Inicio previsto</th>`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:114` — `<div style="height: 40px;"></div>`~~
~~- `Modules/Intervencion/resources/views/pdf/plan.blade.php:114` — `<div style="height: 40px;"></div>`~~
~~- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="max-width: 720px; margin: 0 auto; padding: 1.5rem;">`~~
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="flex: 1; padding: 0.5rem 0.75rem; text-align: center; font-weight: 600;`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="display: flex; gap: 0.5rem; align-items: flex-end;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<select wire:model="busquedaTipoDoc" class="form-select form-select-sm" style="width: 120px; font-size: 0.82rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="flex: 1;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; text-transform: uppercase;"`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 0.75rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<input wire:model="busquedaNombre" type="text" class="form-control form-control-sm" style="font-size: 0.85rem;" autocomp`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<input wire:model="busquedaApellido1" type="text" class="form-control form-control-sm" style="font-size: 0.85rem;" autoc`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<input wire:model="busquedaApellido2" type="text" class="form-control form-control-sm" style="font-size: 0.85rem;" autoc`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<input wire:model="busquedaFechaNacimiento" type="date" class="form-control form-control-sm" style="font-size: 0.85rem;"`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<button wire:click="buscar" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<i data-lucide="search" style="width:14px;height:14px;vertical-align:-2px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="margin-top: 1.5rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `class="btn btn-outline-secondary btn-sm" style="font-size: 0.78rem; white-space: nowrap;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="margin-top: 1rem; padding: 0.75rem 1rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px;`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<button wire:click="consultarPadron" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<i data-lucide="search" style="width:14px;height:14px;vertical-align:-2px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="display: flex; flex-direction: column; gap: 0.5rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; padding: 0.6rem 1rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; padding: 0.6rem 1rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; padding: 0.6rem 1rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; padding: 0.6rem 1rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="margin-bottom: 1rem; padding: 0.5rem 0.85rem; background: #fff3cd; border: 1px solid #ffc107; border-radius:`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `placeholder="Ej: Juan el del cajero de la calle X" style="font-size: 0.85rem;" />`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 0.75rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: `
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<input wire:model="nombre" type="text" class="form-control form-control-sm @error('nombre') is-invalid @enderror" style=`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: `
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<input wire:model="apellido1" type="text" class="form-control form-control-sm @error('apellido1') is-invalid @enderror" `
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: `
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<input wire:model="apellido2" type="text" class="form-control form-control-sm" style="font-size: 0.85rem;" />`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: `
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<input wire:model="fechaNacimiento" type="date" class="form-control form-control-sm @error('fechaNacimiento') is-invalid`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: `
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<select wire:model="sexo" class="form-select form-select-sm @error('sexo') is-invalid @enderror" style="font-size: 0.85r`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="display: flex; gap: 0.5rem; align-items: flex-end;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<select wire:model="tipoDocumento" class="form-select form-select-sm" style="width: 120px; font-size: 0.82rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="flex: 1;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; text-transform: uppercase;"`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 0.75rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div class="col-span-2" style="grid-column: 1 / -1;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: `
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `style="font-size: 0.85rem;" />`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<input wire:model="telefono" type="text" class="form-control form-control-sm" style="font-size: 0.85rem;" />`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<input wire:model="email" type="email" class="form-control form-control-sm @error('email') is-invalid @enderror" style="`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="display: flex; gap: 0.75rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<button wire:click="guardar" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `style="font-size: 0.85rem; resize: vertical;"></textarea>`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.85rem;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php:114` — `<button wire:click="confirmarAlta" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<span style="`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<span style="opacity:.5;">Sin documento activo</span>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="message-square-plus" style="width:14px;height:14px" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="folder-plus" style="width:14px;height:14px" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="folder-open" style="width:14px;height:14px" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `style="font-size:.82rem;padding:.35rem .9rem;border-radius:6px;border:none;cursor:pointer;`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="pencil" style="width:14px;height:14px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="margin:1rem 1.5rem 0;padding:.75rem 1rem;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:.85rem`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<ul style="margin:0;padding-left:1.2rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div class="container-fluid" style="padding:1.25rem 1.5rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="user" style="width:16px;height:16px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="id-card" style="width:16px;height:16px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="plus" style="width:13px;height:13px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<table style="width:100%;font-size:.85rem;border-collapse:collapse;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<td style="padding:.5rem .5rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="users" style="width:16px;height:16px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="plus" style="width:13px;height:13px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="margin-bottom:.75rem;padding:.5rem .75rem;background:#dcfce7;color:#166534;font-size:.82rem;border-radius:6p`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;flex-direction:column;gap:.25rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;align-items:center;gap:.75rem;padding:.45rem .5rem;border-radius:6px;`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<span style="font-size:.72rem;font-weight:600;padding:.15rem .45rem;border-radius:999px;`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="margin-top:.75rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="margin-top:.5rem;display:flex;flex-direction:column;gap:.2rem;opacity:.6;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;align-items:center;gap:.75rem;padding:.35rem .5rem;font-size:.82rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<span style="font-size:.7rem;font-weight:600;padding:.1rem .4rem;border-radius:999px;`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<span style="font-size:.72rem;color:#9ca3af;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="home" style="width:16px;height:16px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;flex-direction:column;gap:.25rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;align-items:center;gap:.75rem;padding:.35rem .5rem;font-size:.88rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<span style="font-size:.72rem;font-weight:600;padding:.1rem .4rem;border-radius:999px;`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div class="ficha-section" id="ficha-atencion-historial" style="margin-bottom:1rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="history" style="width:14px;height:14px" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="chevron-down" style="width:12px;height:12px"`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `:style="expandido ? 'transform:rotate(180deg)' : ''"`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.6rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<span style="font-size:.8rem;font-weight:600;opacity:.4;cursor:default;"`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="layers" style="width:16px;height:16px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;flex-direction:column;gap:.5rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="shield-check" style="width:16px;height:16px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="alert-triangle" style="width:14px;height:14px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-conten`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:480px;box-shadow:0 20px 40px rgba(0,0`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<h3 style="margin:0;font-size:1rem;font-weight:700;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="x" style="width:18px;height:18px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="margin-bottom:.85rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `Tipo de relación <span style="color:#ef4444;">*</span>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="margin-bottom:.85rem;position:relative;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `Ciudadano <span style="color:#ef4444;">*</span>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `style="border:none;background:none;cursor:pointer;color:#6b7280;padding:0;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="x" style="width:14px;height:14px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `style="width:100%;text-align:left;padding:.55rem .75rem;border:none;background:none;cursor:pointer;font-size:.85rem;`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="margin-bottom:.85rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `Fecha de inicio <span style="color:#ef4444;">*</span>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="margin-bottom:1.25rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Observaciones</label>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;gap:.5rem;justify-content:space-between;align-items:center;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `style="padding:.45rem 1rem;border-radius:6px;border:1px solid #fca5a5;background:#fff;font-size:.82rem;cursor:pointer;co`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;gap:.5rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-conte`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:420px;box-shadow:0 20px 40px rgba(0,0`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<h3 style="margin:0;font-size:1rem;font-weight:700;">Añadir documento de identidad</h3>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="x" style="width:18px;height:18px;" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="margin-bottom:.85rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Tipo de documento</label>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="margin-bottom:1.25rem;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Número de documento</label>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<div style="display:flex;gap:.5rem;justify-content:flex-end;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="x" style="width:16px;height:16px" aria-hidden="true"></i>`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:114` — `<i data-lucide="check" style="width:13px;height:13px" aria-hidden="true"></i>`

## 3. Colores hardcodeados

⚠️ **89 ocurrencia(s) de colores fuera de tokens VIDA:**


### 3a. Hex / rgb en vistas Blade

- `resources/views/welcome.blade.php:162` — `<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen f`
- `resources/views/welcome.blade.php:162` — `class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] da`
- `resources/views/welcome.blade.php:162` — `class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] da`
- `resources/views/welcome.blade.php:162` — `class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] da`
- `resources/views/welcome.blade.php:162` — `<div class="text-[13px] leading-[20px] flex-1 p-6 pb-12 lg:p-20 bg-white dark:bg-[#161615] dark:text-[#EDEDEC] shadow-[i`
- `resources/views/welcome.blade.php:162` — `<p class="mb-2 text-[#706f6c] dark:text-[#A1A09A]">Laravel has an incredibly rich ecosystem. <br>We suggest starting wit`
- `resources/views/welcome.blade.php:162` — `<li class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] be`
- `resources/views/welcome.blade.php:162` — `<span class="relative py-1 bg-white dark:bg-[#161615]">`
- `resources/views/welcome.blade.php:162` — `<span class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0`
- `resources/views/welcome.blade.php:162` — `<span class="rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A] w-1.5 h-1.5"></span>`
- `resources/views/welcome.blade.php:162` — `<a href="https://laravel.com/docs" target="_blank" class="inline-flex items-center space-x-1 font-medium underline under`
- `resources/views/welcome.blade.php:162` — `<li class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] be`
- `resources/views/welcome.blade.php:162` — `<span class="relative py-1 bg-white dark:bg-[#161615]">`
- `resources/views/welcome.blade.php:162` — `<span class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0`
- `resources/views/welcome.blade.php:162` — `<span class="rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A] w-1.5 h-1.5"></span>`
- `resources/views/welcome.blade.php:162` — `<a href="https://laracasts.com" target="_blank" class="inline-flex items-center space-x-1 font-medium underline underlin`
- `resources/views/welcome.blade.php:162` — `<a href="https://cloud.laravel.com" target="_blank" class="inline-block dark:bg-[#eeeeec] dark:border-[#eeeeec] dark:tex`
- `resources/views/welcome.blade.php:162` — `<div class="bg-[#fff2f2] dark:bg-[#1D0002] relative lg:-ml-px -mb-px lg:mb-0 rounded-t-lg lg:rounded-t-none lg:rounded-r`
- `resources/views/welcome.blade.php:162` — `<svg class="w-full text-[#F53003] dark:text-[#F61500] transition-all translate-y-0 opacity-100 max-w-none duration-750 s`
- `resources/views/errors/sin-rol.blade.php:162` — `.sinrol-card { background: #fff; border-radius: 12px; padding: 2.5rem 2rem; max-width: 440px; width: 100%; box-shadow: 0`
- `resources/views/welcome.blade.php:162` — `<div class="text-[13px] leading-[20px] flex-1 p-6 pb-12 lg:p-20 bg-white dark:bg-[#161615] dark:text-[#EDEDEC] shadow-[i`
- `resources/views/welcome.blade.php:162` — `<span class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0`
- `resources/views/welcome.blade.php:162` — `<span class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0`
- `resources/views/welcome.blade.php:162` — `<div class="absolute inset-0 rounded-t-lg lg:rounded-t-none lg:rounded-r-lg shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0`
- `resources/views/auth/login.blade.php:162` — `.pill { display: inline-block; background: rgba(255,255,255,0.15); border-radius: 20px; padding: 0.25rem 0.85rem; font-s`
- `resources/views/auth/login.blade.php:162` — `.access-note { font-size: 0.8rem; opacity: 0.7; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 1rem; margin-t`
- `Modules/Mensajes/resources/views/livewire/buzon-page.blade.php:162` — `<div style="position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content:`
- `Modules/Mensajes/resources/views/livewire/hilo-mensajes.blade.php:162` — `<div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">`
- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:162` — `<div style="position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content:`
- `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php:162` — `<div style="background: #fff; border-radius: 12px; padding: 1.5rem; max-width: 480px; width: 90%; box-shadow: 0 8px 32px`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:162` — `<div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-conten`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:162` — `<div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:480px;box-shadow:0 20px 40px rgba(0,0`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:162` — `box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:10;max-height:220px;overflow-y:auto;margin-top:2px;">`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:162` — `<div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-conte`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php:162` — `<div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:420px;box-shadow:0 20px 40px rgba(0,0`

### 3b. Hex en archivos CSS (fuera de tokens)

- `resources/css/filament/admin/theme.css:174` — `--color-primary-50:  #EBF2F9;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-100: #C7DDEF;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-200: #9EC4E3;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-300: #6FAAD5;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-400: #4A8EC4;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-500: #2A5B8A;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-600: #214A72;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-700: #1A3A5A;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-800: #122842;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-900: #0A1828;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-950: #060D17;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-50:  #EBF2F9;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-100: #C7DDEF;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-200: #9EC4E3;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-300: #6FAAD5;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-400: #4A8EC4;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-600: #214A72;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-700: #1A3A5A;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-800: #122842;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-900: #0A1828;`
- `resources/css/filament/admin/theme.css:174` — `--color-primary-950: #060D17;`
- `resources/css/filament/admin/theme.css:174` — `--color-gray-200: #E6E1D8;`
- `resources/css/filament/admin/theme.css:174` — `--color-gray-300: #D7CFBE;`
- `resources/css/filament/admin/theme.css:174` — `--color-gray-400: #B8B0A0;`
- `resources/css/filament/admin/theme.css:174` — `--color-gray-500: #8C8070;`
- `resources/css/filament/admin/theme.css:174` — `--color-gray-600: #6B5F52;`
- `resources/css/filament/admin/theme.css:174` — `--color-gray-700: #4A3F35;`
- `resources/css/filament/admin/theme.css:174` — `--color-gray-800: #2E231A;`
- `resources/css/filament/admin/theme.css:174` — `--color-gray-950: #120D08;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #FFFFFF;`
- `resources/css/filament/admin/theme.css:174` — `border-right: 1px solid #E6E1D8;`
- `resources/css/filament/admin/theme.css:174` — `color: #8C8070;`
- `resources/css/filament/admin/theme.css:174` — `color: #6B5F52;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #FAF7F1;`
- `resources/css/filament/admin/theme.css:174` — `color: #1D160E;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #FAF7F1;`
- `resources/css/filament/admin/theme.css:174` — `color: #1D160E;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #FFFFFF;`
- `resources/css/filament/admin/theme.css:174` — `border-bottom: 1px solid #E6E1D8;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #FAF7F1;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #F2EADA;`
- `resources/css/filament/admin/theme.css:174` — `color: #6B5F52;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #FAF7F1;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #FFFFFF;`
- `resources/css/filament/admin/theme.css:174` — `border: 1px solid #E6E1D8;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #2A5B8A;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #214A72;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #1A3A5A;`
- `resources/css/filament/admin/theme.css:174` — `outline: 2px solid #2A5B8A;`
- `resources/css/filament/admin/theme.css:174` — `border: 1px solid #E6E1D8;`
- `resources/css/filament/admin/theme.css:174` — `background-color: #FFFFFF;`
- `resources/css/filament/admin/theme.css:174` — `border-color: #2A5B8A;`
- `resources/css/filament/admin/theme.css:174` — `box-shadow: 0 0 0 1px #2A5B8A inset;`
- `resources/css/filament/admin/theme.css:174` — `color: #6B3D6B;`

## 4. JavaScript innecesario para navegación


### 4a. `window.location` en Blade / JS

⚠️ **1 ocurrencia(s):**

- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:208` — `onclick="event.target.closest('a') || (window.location.href='{{ route('intervencion.ciudadano.show', $caso->historia_id)`

### 4b. `@click` con rutas o redirects (posible `<a href>`)

✅ Sin ocurrencias detectadas.

### 4c. `wire:click` con métodos de tipo redirect/navigate

⚠️ **2 ocurrencia(s):**

- `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php:248` — `<button wire:click="irAHoy" class="btn btn-sm btn-outline-primary" style="font-size: 0.8rem;">Hoy</button>`
- `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php:248` — `<button wire:click="gotoPage({{ $p }})"`

### 4d. `x-on:click` con navegación (Alpine.js)

✅ Sin ocurrencias detectadas.

## 5. Clases Bootstrap residuales

⚠️ **326 ocurrencia(s) de clases Bootstrap (incompatibles con el design system VIDA):**


**`Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php`**

- Línea 302 — `31:                    <select wire:model="busquedaTipoDoc" class="form-select form-select-sm" style="width: 120px; font`
- Línea 302 — `39:                    <input wire:model="busquedaValorDoc" type="text" class="form-control form-control-sm"`
- Línea 302 — `53:                    <input wire:model="busquedaNombre" type="text" class="form-control form-control-sm" style="font-s`
- Línea 302 — `57:                    <input wire:model="busquedaApellido1" type="text" class="form-control form-control-sm" style="fon`
- Línea 302 — `61:                    <input wire:model="busquedaApellido2" type="text" class="form-control form-control-sm" style="fon`
- Línea 302 — `65:                    <input wire:model="busquedaFechaNacimiento" type="date" class="form-control form-control-sm" styl`
- Línea 302 — `71:            <div class="alert alert-warning py-2" style="font-size: 0.82rem;">{{ $message }}</div>`
- Línea 302 — `74:        <button wire:click="buscar" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">`
- Línea 302 — `108:                                    class="btn btn-outline-secondary btn-sm" style="font-size: 0.78rem; white-space:`
- Línea 302 — `121:                                class="btn btn-sm"`
- Línea 302 — `145:            <button wire:click="consultarPadron" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">`
- Línea 302 — `162:                                class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; paddi`
- Línea 302 — `166:                                class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; paddi`
- Línea 302 — `176:                            class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; padding: `
- Línea 302 — `180:                            class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; padding: `
- Línea 302 — `203:                <div class="mb-3">`
- Línea 302 — `205:                    <input wire:model="alias" type="text" class="form-control form-control-sm @error('alias') is-inv`
- Línea 302 — `217:                    <input wire:model="nombre" type="text" class="form-control form-control-sm @error('nombre') is-i`
- Línea 302 — `225:                    <input wire:model="apellido1" type="text" class="form-control form-control-sm @error('apellido1'`
- Línea 302 — `233:                    <input wire:model="apellido2" type="text" class="form-control form-control-sm" style="font-size:`
- Línea 302 — `240:                    <input wire:model="fechaNacimiento" type="date" class="form-control form-control-sm @error('fech`
- Línea 302 — `248:                    <select wire:model="sexo" class="form-select form-select-sm @error('sexo') is-invalid @enderror"`
- Línea 302 — `264:                    <select wire:model="tipoDocumento" class="form-select form-select-sm" style="width: 120px; font-`
- Línea 302 — `272:                    <input wire:model="valorDocumento" type="text" class="form-control form-control-sm"`
- Línea 302 — `283:                    <div class="col-span-2" style="grid-column: 1 / -1;">`
- Línea 302 — `288:                        <input wire:model="direccionTexto" type="text" class="form-control form-control-sm"`
- Línea 302 — `295:                    <input wire:model="telefono" type="text" class="form-control form-control-sm" style="font-size: `
- Línea 302 — `299:                    <input wire:model="email" type="email" class="form-control form-control-sm @error('email') is-in`
- Línea 302 — `306:            <button wire:click="guardar" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">`
- Línea 302 — `338:            <textarea wire:model="primeraDemanda" rows="3" class="form-control form-control-sm"`
- Línea 302 — `358:        <button wire:click="confirmarAlta" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">`

**`Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php`**

- Línea 302 — `64:        <button wire:click="abrirModalAtencion" type="button" class="ficha-btn">`
- Línea 302 — `75:            class="ficha-btn ficha-btn--primary"`
- Línea 302 — `84:            class="ficha-btn ficha-btn--primary"`
- Línea 302 — `124:<div class="container-fluid" style="padding:1.25rem 1.5rem;">`
- Línea 302 — `125:    <div class="row g-3">`
- Línea 302 — `128:        <div class="col-lg-8">`
- Línea 302 — `137:                <div class="row g-3">`
- Línea 302 — `139:                    <div class="col-sm-4">`
- Línea 302 — `149:                    <div class="col-sm-4">`
- Línea 302 — `159:                    <div class="col-sm-4">`
- Línea 302 — `169:                    <div class="col-sm-4">`
- Línea 302 — `181:                    <div class="col-sm-4">`
- Línea 302 — `198:                    <div class="col-sm-4">`
- Línea 302 — `212:                <div class="row g-3">`
- Línea 302 — `214:                    <div class="col-12">`
- Línea 302 — `225:                    <div class="col-sm-6">`
- Línea 302 — `235:                    <div class="col-sm-6">`
- Línea 302 — `518:        <div class="col-lg-4">`
- Línea 302 — `884:            <button wire:click="cerrarModalAtencion" class="ficha-btn" type="button">Cancelar</button>`
- Línea 302 — `885:            <button wire:click="guardarAtencion" class="ficha-btn ficha-btn--primary" type="button">`

**`Modules/Intervencion/resources/views/filament/tipo-plan/gestionar-objetivos.blade.php`**

- Línea 302 — `2:    <div class="mb-4 text-sm text-gray-500">`

**`Modules/Intervencion/resources/views/livewire/agenda-page.blade.php`**

- Línea 302 — `47:        <button wire:click="navegarAnterior" class="btn btn-sm btn-outline-secondary" aria-label="Período anterior" s`
- Línea 302 — `50:        <button wire:click="navegarSiguiente" class="btn btn-sm btn-outline-secondary" aria-label="Período siguiente"`
- Línea 302 — `53:        <button wire:click="irAHoy" class="btn btn-sm btn-outline-primary" style="font-size: 0.8rem;">Hoy</button>`
- Línea 302 — `56:        <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Vista de agenda">`
- Línea 302 — `58:                    class="btn {{ $vista === 'dia' ? 'btn-primary' : 'btn-outline-primary' }}"`
- Línea 302 — `63:                    class="btn {{ $vista === 'semana' ? 'btn-primary' : 'btn-outline-primary' }}"`
- Línea 302 — `68:                    class="btn {{ $vista === 'mes' ? 'btn-primary' : 'btn-outline-primary' }}"`

**`Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php`**

- Línea 302 — `9:            <select wire:model="campoBusqueda" class="form-select form-select-sm" style="width: 140px; font-size: 0.8r`
- Línea 302 — `16:            <input wire:model="query" type="text" class="form-control form-control-sm"`
- Línea 302 — `21:            <button type="submit" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">`

**`Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php`**

- Línea 302 — `363:                                <select wire:model="formEntrevista.tipo" class="form-select form-select-sm">`
- Línea 302 — `372:                                <select wire:model="formEntrevista.modalidad" class="form-select form-select-sm">`
- Línea 302 — `382:                            <textarea wire:model="formEntrevista.notas" rows="3" class="form-control form-control-sm`
- Línea 302 — `392:                                <input type="date" wire:model="formEntrevista.fecha_siguiente_seguimiento" class="fo`
- Línea 302 — `396:                            <button wire:click="guardarEntrevista" class="btn btn-primary btn-sm" style="font-size: `
- Línea 302 — `397:                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style=`
- Línea 302 — `405:                            <textarea wire:model="formAnotacion.contenido" rows="4" class="form-control form-control`
- Línea 302 — `416:                            <button wire:click="guardarAnotacion" class="btn btn-primary btn-sm" style="font-size: 0`
- Línea 302 — `417:                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style=`
- Línea 302 — `426:                            <select wire:model="formDerivacion.urgencia" class="form-select form-select-sm">`
- Línea 302 — `434:                            <textarea wire:model="formDerivacion.motivo" rows="3" class="form-control form-control-s`
- Línea 302 — `437:                            <button wire:click="crearDerivacion" class="btn btn-primary btn-sm" style="font-size: 0.`
- Línea 302 — `438:                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style=`
- Línea 302 — `447:                            <select wire:model="formGestion.tipo_gestion" class="form-select form-select-sm">`
- Línea 302 — `458:                            <input type="text" wire:model="formGestion.recurso_interlocutor" class="form-control for`
- Línea 302 — `462:                            <textarea wire:model="formGestion.descripcion" rows="3" class="form-control form-control`
- Línea 302 — `465:                            <button wire:click="guardarGestion" class="btn btn-primary btn-sm" style="font-size: 0.8`
- Línea 302 — `466:                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style=`
- Línea 302 — `476:                            <select wire:model.live="formValoracion.tipo_ficha_id" class="form-select form-select-sm`
- Línea 302 — `486:                                   class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Abrir en pantalla compl`
- Línea 302 — `488:                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style=`
- Línea 302 — `498:                            <select wire:model.live="formEscala.tipo_escala_id" class="form-select form-select-sm">`
- Línea 302 — `508:                                   class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Abrir en pantalla compl`
- Línea 302 — `510:                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style=`
- Línea 302 — `519:                        <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="fon`
- Línea 302 — `568:                <button wire:click="cerrarModalApunte" class="btn btn-outline-secondary btn-sm">Cerrar</button>`
- Línea 302 — `658:                    <button wire:click="cerrarModalApunte" class="btn btn-outline-secondary btn-sm">Cerrar</button>`
- Línea 302 — `738:                                        <span class="uc-badge uc-badge--verificado" title="Residencia verificada">`
- Línea 302 — `745:                                            class="uc-badge uc-badge--sin-verificar"`
- Línea 302 — `756:                                            <button wire:click="confirmarBajaMiembro" class="uc-btn uc-btn--danger-s`
- Línea 302 — `757:                                            <button wire:click="cancelarBajaMiembro" class="uc-btn uc-btn--ghost-sm"`
- Línea 302 — `762:                                            class="uc-btn uc-btn--ghost-sm"`
- Línea 302 — `788:                                    <button wire:click="confirmarAnadirMiembro" class="uc-btn uc-btn--primary-sm">Co`
- Línea 302 — `789:                                    <button wire:click="cancelarSeleccionUc" class="uc-btn uc-btn--ghost-sm">Cancela`
- Línea 302 — `817:                                        <span class="uc-badge uc-badge--sin-verificar uc-badge--sm">Sin verificar</s`
- Línea 302 — `839:                <button wire:click="cerrarModalUc" class="uc-btn uc-btn--ghost">Cerrar</button>`
- Línea 302 — `858:        <div class="uc-modal uc-modal--sm">`
- Línea 302 — `914:                <button wire:click="cerrarModalRepresentante" class="uc-btn uc-btn--ghost">`
- Línea 302 — `976:                                    class="uc-btn uc-btn--ghost-sm"`
- Línea 302 — `1011:                <button wire:click="cerrarModalRelaciones" class="uc-btn uc-btn--ghost">`

**`Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php`**

- Línea 302 — `45:        <select wire:model.live="filtroSeguimiento" class="form-select form-select-sm" style="width: auto; font-size:`
- Línea 302 — `54:        <select wire:model.live="filtroPiso" class="form-select form-select-sm" style="width: auto; font-size: 0.8rem`
- Línea 302 — `62:        <select wire:model.live="filtroEsp" class="form-select form-select-sm" style="width: auto; font-size: 0.8rem;`

**`Modules/Intervencion/resources/views/livewire/plan-page.blade.php`**

- Línea 302 — `32:        <span class="plan-badge plan-badge--{{ $this->plan->estado->value }}">`
- Línea 302 — `35:        <span class="plan-badge plan-badge--version">v{{ $this->plan->version }}</span>`
- Línea 302 — `41:        <button wire:click="generarPdf" class="plan-btn">`
- Línea 302 — `50:            class="plan-btn plan-btn--primary"`
- Línea 302 — `59:        <button class="plan-btn">`
- Línea 302 — `135:            <button wire:click="abrirDrawer" class="plan-btn">`
- Línea 302 — `185:                <button wire:click="abrirDrawer" class="plan-add-ficha-btn">`
- Línea 302 — `199:                    <button class="plan-tb-btn" onclick="document.execCommand('bold')"`
- Línea 302 — `201:                    <button class="plan-tb-btn" onclick="document.execCommand('italic')"`
- Línea 302 — `203:                    <button class="plan-tb-btn" onclick="document.execCommand('insertUnorderedList')"`
- Línea 302 — `226:            <button class="plan-btn">`
- Línea 302 — `247:                        <span class="plan-estado-badge plan-estado-{{ $og->estado }}">`
- Línea 302 — `250:                        <button class="plan-tb-btn">`
- Línea 302 — `268:            <button class="plan-btn">`
- Línea 302 — `303:                        <td><span class="plan-estado-badge plan-estado-{{ $act->estado }}">{{ ucfirst($act->estado) `
- Línea 302 — `304:                        <td><button class="plan-tb-btn"><i data-lucide="edit" style="width:13px;height:13px"></i></b`
- Línea 302 — `320:            <button class="plan-btn">`
- Línea 302 — `339:                    <button class="plan-tb-btn" style="margin-left:auto">`
- Línea 302 — `356:            <button class="plan-btn">`
- Línea 302 — `376:                    <button class="plan-tb-btn">`
- Línea 302 — `565:            <button wire:click="cerrarDrawer" class="plan-btn">Cancelar</button>`
- Línea 302 — `568:                class="plan-btn plan-btn--primary"`
- Línea 302 — `597:            <button wire:click="cancelarCambio" class="plan-btn">Cancelar</button>`
- Línea 302 — `600:                class="plan-btn plan-btn--primary"`

**`Modules/Intervencion/resources/views/livewire/registrar-escala-page.blade.php`**

- Línea 302 — `45:        <button wire:click="guardar" class="btn btn-primary" style="font-size: 0.85rem;">`

**`Modules/Intervencion/resources/views/livewire/sidebar.blade.php`**

- Línea 302 — `31:                <span class="op-nav-badge alerta">{{ $this->datos['notificaciones'] }}</span>`

**`Modules/Mensajes/resources/views/livewire/badge-notificaciones.blade.php`**

- Línea 302 — `5:        <span class="badge rounded-pill bg-danger">`
- Línea 302 — `11:        <a href="{{ url('/mensajes/alertas') }}" class="text-decoration-none me-2" title="Alertas pendientes">`
- Línea 302 — `13:            <span class="badge bg-warning text-dark">{{ $this->totalAlertas }}</span>`
- Línea 302 — `20:            <span class="badge bg-primary">{{ $this->totalMensajes }}</span>`

**`Modules/Mensajes/resources/views/livewire/bandeja-alertas.blade.php`**

- Línea 302 — `3:    <h2 class="mb-4">Alertas pendientes</h2>`
- Línea 302 — `6:        <div class="alert alert-success">`
- Línea 302 — `7:            <i class="bi bi-check-circle me-2"></i>No tienes alertas pendientes.`
- Línea 302 — `15:                    <div class="d-flex justify-content-between align-items-start">`
- Línea 302 — `19:                                <span class="badge bg-warning text-dark me-2">`
- Línea 302 — `23:                                <span class="badge bg-info me-2">`
- Línea 302 — `32:                                <small class="text-muted ms-2">`
- Línea 302 — `37:                            <p class="mt-2 mb-1">{{ $alerta->cuerpo }}</p>`
- Línea 302 — `40:                        <div class="ms-3 d-flex flex-column gap-2">`
- Línea 302 — `44:                                    <button wire:click="reconocer" class="btn btn-sm btn-success">`
- Línea 302 — `47:                                    <button wire:click="cancelarReconocimiento" class="btn btn-sm btn-secondary">`
- Línea 302 — `52:                                            class="btn btn-sm btn-outline-success">`
- Línea 302 — `58:                                        class="btn btn-sm btn-outline-secondary">`

**`Modules/Mensajes/resources/views/livewire/bandeja-mensajes.blade.php`**

- Línea 302 — `2:<div class="row g-0" style="min-height: 70vh;">`
- Línea 302 — `5:    <div class="col-md-4 border-end">`
- Línea 302 — `6:        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">`
- Línea 302 — `7:            <h5 class="mb-0">Mensajes</h5>`
- Línea 302 — `8:            <button wire:click="nuevaMensaje" class="btn btn-sm btn-primary">`
- Línea 302 — `19:                    <div class="d-flex justify-content-between">`
- Línea 302 — `20:                        <span class="{{ $noLeidos > 0 ? 'fw-bold' : '' }}">`
- Línea 302 — `24:                            <span class="badge bg-primary rounded-pill">{{ $noLeidos }}</span>`
- Línea 302 — `32:                    <div class="mt-1">`
- Línea 302 — `34:                                class="btn btn-link btn-sm p-0 text-muted"`
- Línea 302 — `41:                <div class="p-3 text-muted small">Sin conversaciones.</div>`
- Línea 302 — `47:    <div class="col-md-8">`
- Línea 302 — `53:            <div class="d-flex align-items-center justify-content-center h-100 text-muted">`

**`Modules/Mensajes/resources/views/livewire/buzon-page.blade.php`**

- Línea 302 — `120:                                <i class="bi bi-check-circle me-1"></i> Reconocer alerta`

**`Modules/Mensajes/resources/views/livewire/hilo-mensajes.blade.php`**

- Línea 302 — `3:    <div class="p-3 border-bottom">`
- Línea 302 — `4:        <h6 class="mb-0">{{ $this->hilo->asunto }}</h6>`
- Línea 302 — `8:    <div class="p-3 overflow-auto" style="max-height: 50vh;">`
- Línea 302 — `12:            <div class="mb-3 d-flex {{ $esMio ? 'justify-content-end' : 'justify-content-start' }}">`
- Línea 302 — `15:                    <div class="card-body py-2 px-3">`
- Línea 302 — `16:                        <div class="small {{ $esMio ? 'text-white-50' : 'text-muted' }} mb-1">`
- Línea 302 — `19:                        <p class="mb-1">{{ $mensaje->cuerpo }}</p>`
- Línea 302 — `23:                            <div class="mt-1">`
- Línea 302 — `24:                                <span class="badge bg-secondary">`
- Línea 302 — `30:                                        class="btn btn-sm btn-link p-0 ms-1`
- Línea 302 — `41:                            <div class="mt-1">`
- Línea 302 — `56:    <div class="p-3 border-top">`
- Línea 302 — `58:            <div class="mb-2">`
- Línea 302 — `60:                          class="form-control"`
- Línea 302 — `65:            <div class="d-flex justify-content-between align-items-center">`
- Línea 302 — `67:                    <input type="file" wire:model="adjuntos" multiple class="form-control form-control-sm" />`
- Línea 302 — `69:                <button type="submit" class="btn btn-primary btn-sm">`
- Línea 302 — `78:        <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">`
- Línea 302 — `83:                        <button wire:click="cerrarModalHistoria" type="button" class="btn-close"></button>`
- Línea 302 — `90:                        <div class="mb-3">`
- Línea 302 — `92:                            <textarea wire:model="cuerpoEditado" class="form-control" rows="5"></textarea>`
- Línea 302 — `95:                        <div class="mb-3">`
- Línea 302 — `97:                            <select wire:model="visibilidadSeleccionada" class="form-select">`
- Línea 302 — `105:                        <button wire:click="cerrarModalHistoria" type="button" class="btn btn-secondary">`
- Línea 302 — `108:                        <button wire:click="confirmarRegistroHistoria" type="button" class="btn btn-primary">`

**`Modules/Mensajes/resources/views/livewire/nuevo-mensaje.blade.php`**

- Línea 302 — `2:<div class="p-3">`
- Línea 302 — `3:    <h6 class="mb-3">Nuevo mensaje</h6>`
- Línea 302 — `8:        <div class="mb-3">`
- Línea 302 — `12:                <div class="d-flex align-items-center gap-2">`
- Línea 302 — `13:                    <span class="badge bg-secondary fs-6">{{ $dest?->name }}</span>`
- Línea 302 — `15:                            class="btn btn-sm btn-outline-secondary">`
- Línea 302 — `22:                       class="form-control"`
- Línea 302 — `26:                <div class="row mt-2">`
- Línea 302 — `27:                    <div class="col-6">`
- Línea 302 — `29:                               class="form-control form-control-sm"`
- Línea 302 — `32:                    <div class="col-6">`
- Línea 302 — `33:                        <select wire:model.live="filtroUoId" class="form-select form-select-sm">`
- Línea 302 — `44:                    <div class="list-group mt-1">`
- Línea 302 — `48:                                    class="list-group-item list-group-item-action py-2">`
- Línea 302 — `50:                                <small class="text-muted ms-2">`
- Línea 302 — `62:        <div class="mb-3">`
- Línea 302 — `64:            <input type="text" wire:model="asunto" class="form-control" maxlength="255" />`
- Línea 302 — `69:        <div class="mb-3">`
- Línea 302 — `71:            <textarea wire:model="cuerpo" class="form-control" rows="5"></textarea>`
- Línea 302 — `76:        <div class="mb-3">`
- Línea 302 — `80:                <div class="badge bg-secondary me-1 mb-1">`
- Línea 302 — `83:                            class="btn-close btn-close-white btn-sm ms-1"></button>`
- Línea 302 — `89:                   class="form-control form-control-sm mt-1"`
- Línea 302 — `93:                <div class="list-group mt-1">`
- Línea 302 — `97:                                class="list-group-item list-group-item-action py-1 small">`
- Línea 302 — `106:        <div class="mb-3">`
- Línea 302 — `108:            <input type="file" wire:model="adjuntos" multiple class="form-control" />`
- Línea 302 — `112:        <div class="d-flex gap-2">`
- Línea 302 — `113:            <button type="submit" class="btn btn-primary">`

**`resources/views/auth/login.blade.php`**

- Línea 302 — `19:<div class="container-fluid p-0">`
- Línea 302 — `20:    <div class="row g-0">`
- Línea 302 — `23:        <div class="col-lg-5 login-left d-none d-lg-flex">`
- Línea 302 — `25:                <div class="mb-4">`
- Línea 302 — `26:                    <h1 class="fw-bold fs-3 mb-1">VIDA 360</h1>`
- Línea 302 — `27:                    <p class="mb-0 opacity-75">Plataforma integrada de servicios sociales</p>`
- Línea 302 — `29:                <div class="mb-4">`
- Línea 302 — `45:        <div class="col-12 col-lg-7 login-right">`
- Línea 302 — `49:                <div class="text-end mb-3">`
- Línea 302 — `50:                    <span class="badge bg-secondary env-badge">{{ config('app.env_label') }}</span>`
- Línea 302 — `53:                <h2 class="fw-semibold mb-1 fs-5">{{ saludo() }}</h2>`
- Línea 302 — `54:                <p class="text-muted small mb-4">Introduce tus credenciales para acceder</p>`
- Línea 302 — `57:                    <div class="alert alert-danger py-2 small" role="alert">`
- Línea 302 — `65:                    <div class="mb-3">`
- Línea 302 — `71:                            class="form-control @error('email') is-invalid @enderror"`
- Línea 302 — `83:                    <div class="mb-4">`
- Línea 302 — `89:                            class="form-control @error('password') is-invalid @enderror"`
- Línea 302 — `99:                    <div class="d-grid mb-3">`
- Línea 302 — `100:                        <button type="submit" class="btn btn-primary">Entrar</button>`
- Línea 302 — `106:                <div class="text-center small">`
- Línea 302 — `110:                <div class="text-center small mt-3 text-muted">`

**`resources/views/auth/onboarding.blade.php`**

- Línea 302 — `14:<div class="onboarding-card card shadow-sm p-4 p-md-5">`
- Línea 302 — `15:    <div class="text-center mb-4">`
- Línea 302 — `16:        <span class="fs-1">👋</span>`
- Línea 302 — `18:    <h2 class="fw-bold mb-1">`
- Línea 302 — `21:    <p class="text-muted mb-4">Tu cuenta está lista. Estos son tus datos de acceso configurados:</p>`
- Línea 302 — `23:    <ul class="list-unstyled mb-4">`
- Línea 302 — `24:        <li class="mb-2">`
- Línea 302 — `29:        <li class="mb-2">`
- Línea 302 — `38:        <div class="d-grid">`
- Línea 302 — `39:            <button type="submit" class="btn btn-primary btn-lg">Empezar</button>`

**`resources/views/errors/sin-rol.blade.php`**

- Línea 302 — `67:    <form method="POST" action="{{ route('logout') }}" class="text-center">`
- Línea 302 — `69:        <button type="submit" class="btn btn-outline-secondary btn-sm px-4">`

**`resources/views/filament/pages/demo-worlds-page.blade.php`**

- Línea 302 — `24:                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">`
- Línea 302 — `45:                    <dl class="mb-4 grid grid-cols-3 gap-3 text-center">`
- Línea 302 — `50:                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">`
- Línea 302 — `58:                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">`
- Línea 302 — `66:                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">`

**`resources/views/filament/prestaciones/snapshot-modal.blade.php`**

- Línea 302 — `7:                        <th class="py-2 pr-4 font-semibold text-gray-600 dark:text-gray-400">Campo</th>`
- Línea 302 — `8:                        <th class="py-2 font-semibold text-gray-600 dark:text-gray-400">Valor</th>`
- Línea 302 — `14:                            <td class="py-1 pr-4 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap`
- Línea 302 — `17:                            <td class="py-1 text-gray-800 dark:text-gray-200">`

**`resources/views/inicio.blade.php`**

- Línea 302 — `20:    <span class="fw-semibold">{{ config('app.name') }}</span>`
- Línea 302 — `21:    <div class="d-flex align-items-center gap-2">`
- Línea 302 — `27:<div class="container py-5 text-center">`

**`resources/views/layouts/operativo.blade.php`**

- Línea 302 — `80:                    class="topbar__user-btn"`

**`resources/views/livewire/admin/gestor-unidades-organizativas.blade.php`**

- Línea 302 — `10:<div class="p-6">`
- Línea 302 — `13:    <div class="flex items-center justify-between mb-6">`
- Línea 302 — `16:            <p class="mt-1 text-sm text-gray-500">`
- Línea 302 — `22:            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"`
- Línea 302 — `30:        <div class="p-4 mb-4 text-green-800 bg-green-100 rounded-md">`
- Línea 302 — `36:    <div class="mb-4">`
- Línea 302 — `41:            class="w-full px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:r`
- Línea 302 — `50:            <div class="p-8 text-center text-gray-500">`
- Línea 302 — `59:            <div class="w-full max-w-lg p-6 bg-white rounded-lg shadow-xl">`
- Línea 302 — `61:                <h2 class="mb-4 text-lg font-semibold text-gray-900">`
- Línea 302 — `69:                        <label class="block mb-1 text-sm font-medium text-gray-700">`
- Línea 302 — `75:                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focu`
- Línea 302 — `79:                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>`
- Línea 302 — `85:                        <label class="block mb-1 text-sm font-medium text-gray-700">`
- Línea 302 — `90:                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focu`
- Línea 302 — `98:                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>`
- Línea 302 — `104:                        <label class="block mb-1 text-sm font-medium text-gray-700">`
- Línea 302 — `109:                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none foc`
- Línea 302 — `117:                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>`
- Línea 302 — `122:                    <div class="flex justify-end gap-3 pt-2">`
- Línea 302 — `126:                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-`
- Línea 302 — `132:                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700`

**`resources/views/livewire/admin/partials/uo-nodo.blade.php`**

- Línea 302 — `10:    <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50"`
- Línea 302 — `23:                <span class="ml-2 px-1.5 py-0.5 text-xs rounded bg-gray-100 text-gray-500">`
- Línea 302 — `27:                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded bg-yellow-100 text-yellow-700">`
- Línea 302 — `38:                class="px-3 py-1 text-xs text-blue-700 bg-blue-50 rounded hover:bg-blue-100"`
- Línea 302 — `48:                    class="px-3 py-1 text-xs text-red-700 bg-red-50 rounded hover:bg-red-100"`
- Línea 302 — `56:                    class="px-3 py-1 text-xs text-green-700 bg-green-50 rounded hover:bg-green-100"`

**`resources/views/livewire/centros/selector-prestaciones-centro-modal.blade.php`**

- Línea 302 — `1:<div class="p-1">`

**`resources/views/livewire/centros/selector-prestaciones-centro.blade.php`**

- Línea 302 — `15:                class="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm`
- Línea 302 — `29:                        class="rounded-full border px-3 py-1 text-xs font-medium transition-colors`
- Línea 302 — `46:        <div class="col-span-2 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700"`
- Línea 302 — `52:                    <div class="sticky top-0 bg-gray-50 px-4 py-2 dark:bg-gray-900">`
- Línea 302 — `60:                        <div class="flex items-start gap-3 border-b border-gray-50 px-4 py-3`
- Línea 302 — `64:                            <div class="pt-0.5">`
- Línea 302 — `96:                                <p class="mt-0.5 font-mono text-xs text-gray-400">`
- Línea 302 — `106:                <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">`
- Línea 302 — `118:                <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium`
- Línea 302 — `127:                    <p class="px-3 py-6 text-center text-xs text-gray-400 dark:text-gray-500">`
- Línea 302 — `137:                        <div class="flex items-start gap-2 border-b border-gray-50 px-3 py-2.5`
- Línea 302 — `165:    <div class="flex items-center justify-between border-t border-gray-200 pt-3 dark:border-gray-700">`
- Línea 302 — `174:            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white`
- Línea 302 — `187:            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">`
- Línea 302 — `188:                <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6`
- Línea 302 — `191:                    <div class="mb-4 flex items-start justify-between gap-3">`
- Línea 302 — `194:                            <h3 class="mt-1 text-base font-medium text-gray-900 dark:text-white">`
- Línea 302 — `228:                    <div class="mt-5 text-right">`
- Línea 302 — `232:                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600`

**`resources/views/welcome.blade.php`**

- Línea 302 — `22:    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-s`
- Línea 302 — `23:        <header class="w-full lg:max-w-4xl max-w-[335px] text-sm mb-6 not-has-[nav]:hidden">`
- Línea 302 — `29:                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#191`
- Línea 302 — `36:                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transpar`
- Línea 302 — `44:                                class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[`
- Línea 302 — `53:            <main class="flex max-w-[335px] w-full flex-col-reverse lg:max-w-4xl lg:flex-row">`
- Línea 302 — `54:                <div class="text-[13px] leading-[20px] flex-1 p-6 pb-12 lg:p-20 bg-white dark:bg-[#161615] dark:text-`
- Línea 302 — `55:                    <h1 class="mb-1 font-medium">Let's get started</h1>`
- Línea 302 — `56:                    <p class="mb-2 text-[#706f6c] dark:text-[#A1A09A]">Laravel has an incredibly rich ecosystem. <br>`
- Línea 302 — `57:                    <ul class="flex flex-col mb-4 lg:mb-6">`
- Línea 302 — `58:                        <li class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark`
- Línea 302 — `59:                            <span class="relative py-1 bg-white dark:bg-[#161615]">`
- Línea 302 — `85:                        <li class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark`
- Línea 302 — `86:                            <span class="relative py-1 bg-white dark:bg-[#161615]">`
- Línea 302 — `115:                            <a href="https://cloud.laravel.com" target="_blank" class="inline-block dark:bg-[#eeeeec`
- Línea 302 — `121:                <div class="bg-[#fff2f2] dark:bg-[#1D0002] relative lg:-ml-px -mb-px lg:mb-0 rounded-t-lg lg:rounded`

## 6. Botones sin color de texto explícito

Detecta elementos `<button` o `role="button"` con clases `bg-*` pero sin `text-*`.

✅ Sin botones detectados con `bg-` sin `text-`.

---

## Resumen ejecutivo

| Categoría | Ocurrencias |
|-----------|-------------|
| 1. Bloques `<style>` en Blade | 7 |
| 2. Atributos `style=""` estructurales | 373 |
| 3a. Colores hex/rgb en Blade | 229 |
| 3b. Colores hex en CSS ad-hoc | 58 |
| 4. JS innecesario para navegación | 1 |
| 5. Clases Bootstrap residuales | 326 |
| 6. Botones sin color de texto | 0 |
| **TOTAL** | **994** |

_Informe generado por `analizar-frontend.sh`. Revisa manualmente las ocurrencias antes de corregirlas._
