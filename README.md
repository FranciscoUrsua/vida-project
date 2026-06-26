# VIDA 360 — Visión Integral de la Persona en Atención Social

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com) [![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://www.php.net) [![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15+-blue.svg)](https://www.postgresql.org) [![Livewire](https://img.shields.io/badge/Livewire-4.x-blueviolet.svg)](https://livewire.laravel.com) [![Filament](https://img.shields.io/badge/Filament-5.x-orange.svg)](https://filamentphp.com) [![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple.svg)](https://getbootstrap.com) [![License: Apache-2.0](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](LICENSE.md)

## Introducción

**VIDA 360** es una plataforma de gestión de servicios sociales diseñada para Ayuntamientos y Comunidades Autónomas. Centraliza la historia social de los ciudadanos, los planes de intervención, las prestaciones y recursos disponibles, y la coordinación entre profesionales del trabajo social.

### ¿Qué resuelve VIDA 360?

- **Historia social única**: cada ciudadano tiene un expediente central que integra valoraciones, intervenciones activas, prestaciones reconocidas y documentación adjunta.
- **Coordinación por equipos**: los profesionales trabajan desde su centro de servicios sociales con visibilidad restringida a su unidad organizativa, respetando la privacidad de colectivos protegidos.
- **Gestión de prestaciones**: catálogo de prestaciones y recursos con seguimiento del estado de cada solicitud y reconocimiento.
- **Backoffice de configuración**: administración de catálogos, roles, centros, perfiles horarios y cuadrantes a través de un panel Filament.
- **Público objetivo**: servicios sociales municipales y autonómicos que buscan una solución open-source adaptable a la Ley de Servicios Sociales.

### Estado del proyecto (junio 2026)

El proyecto tiene una base sólida con doce módulos funcionales, autenticación completa, backoffice Filament, y una suite de tests con más de 300 casos cubriendo los flujos principales.

| Módulo | Estado |
|---|---|
| Organización (UO, distritos, zonas, redes) | ✅ Completo |
| Usuarios y Permisos | ✅ Completo |
| Prestaciones | ✅ Completo |
| Centro (centros, salas, tipos de actividad) | ✅ Completo |
| Mensajes | ✅ Completo |
| Escalas de valoración | ✅ Fase 1 |
| Documentos e informes | ✅ Completo |
| Agenda | ✅ Dominio completo |
| Ciudadanía (expedientes, fichas) | 🚧 En desarrollo |
| Intervención (planes, objetivos, compromisos) | 🚧 En desarrollo |
| Supervisión | ✅ Completo |
| Auditoría | ✅ Completo |

---

## Stack tecnológico

| Componente | Tecnología |
|---|---|
| Framework | Laravel 12 |
| PHP | 8.2 o superior |
| Base de datos | PostgreSQL 15+ |
| Frontend operativo | Blade + Livewire 4 + Alpine.js |
| Backoffice | Filament 5.3 |
| Estilos | Bootstrap 5.3 (vía npm + Vite) |
| Módulos | nwidart/laravel-modules v12 |
| Roles y permisos | spatie/laravel-permission |
| Adjuntos | spatie/laravel-medialibrary |
| Jerarquía organizativa | staudenmeir/laravel-adjacency-list |
| PDFs | barryvdh/laravel-dompdf |
| Análisis estático | PHPStan nivel 6 + Rector + Pint |

---

## Requisitos del sistema

- **PHP** 8.2 o superior (8.3 recomendado)
- **Composer** 2.x
- **Node.js** 20+ y **npm** (para compilar assets con Vite)
- **PostgreSQL** 15 o superior
- **Servidor web** Apache/Nginx o el servidor de desarrollo de Laravel

---

## Instalación

> El código fuente de Laravel está en el subdirectorio `vida/`. Los documentos de proyecto (`docs/`, `CHANGELOG-*.md`, etc.) están en la raíz del repositorio.

### 1. Clonar el repositorio

```bash
git clone https://github.com/FranciscoUrsua/vida-project.git
cd vida-project/vida
```

### 2. Instalar dependencias PHP

```bash
composer install
```

### 3. Configurar el entorno

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` con tus credenciales de base de datos:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=vida
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

### 4. Ejecutar migraciones

```bash
php artisan migrate
```

### 5. Compilar assets (Bootstrap + Vite)

```bash
npm install
npm run build
```

Para desarrollo con recarga automática:

```bash
npm run dev
```

### 6. Datos de demo (opcional)

El proyecto incluye un sistema de mundos YAML para cargar entornos de demostración completos con usuarios, centros, salas, actividades y escenarios:

```bash
php artisan demo:reset --world=demo_ciam
```

Los mundos disponibles están en `database/seeders/worlds/`.

### 7. Iniciar el servidor de desarrollo

```bash
php artisan serve
```

Accede a:
- **Aplicación**: `http://127.0.0.1:8000/login`
- **Backoffice Filament**: `http://127.0.0.1:8000/admin`

---

## Estructura del repositorio

```
vida-project/
├── vida/                    # Aplicación Laravel
│   ├── app/
│   │   ├── Filament/        # Recursos y páginas del backoffice
│   │   ├── Http/            # Controladores (auth, onboarding)
│   │   └── Livewire/        # Componentes operativos
│   ├── Modules/             # Módulos nwidart
│   │   ├── Agenda/
│   │   ├── Atencion/
│   │   ├── Centro/
│   │   ├── Ciudadania/
│   │   ├── Documentos/
│   │   ├── Escalas/
│   │   ├── Intervencion/
│   │   ├── Mensajes/
│   │   ├── Organizacion/
│   │   ├── Prestaciones/
│   │   ├── Supervision/
│   │   └── Usuarios/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   │       └── worlds/      # Mundos YAML para demo
│   └── resources/views/
├── docs/                    # Documentación técnica y de dominio
├── CHANGELOG-052026.md
├── CHANGELOG-062026.md
└── SESSION.md               # Estado actual y próximo paso
```

---

## Tests

El proyecto usa PostgreSQL para los tests (`vida_testing`). No se usa SQLite.

```bash
# Tests de un módulo concreto
php artisan test --filter=Centro
php artisan test tests/Feature/Modules/Supervision/

# Suite completa (antes de merge a main)
php artisan test
```

---

## Troubleshooting

- **Error de conexión a BD**: verifica credenciales en `.env` y que PostgreSQL esté activo.
- **Permisos de storage**: `chmod -R 755 storage bootstrap/cache`
- **Assets no cargados**: ejecuta `npm run build` desde `vida/`.
- **Logs**: `storage/logs/laravel.log`

---

## Documentación técnica

En la carpeta `docs/` encontrarás:

- `docs/principios-vida360.md` — decisiones de diseño y restricciones de dominio
- `docs/documentacion-proyecto.md` — referencia técnica por módulo
- `docs/decisiones-tecnicas.md` — decisiones de arquitectura tomadas
- `docs/design-system/` — sistema de diseño (Bootstrap + tokens VIDA)

---

## Licencia

Este proyecto está bajo la licencia **Apache 2.0**. Ver `LICENSE.md` para los términos completos.

Las dependencias mantienen sus licencias respectivas (Laravel: MIT; PostgreSQL: PostgreSQL License; Livewire, Filament, Bootstrap, librerías Spatie: MIT).
