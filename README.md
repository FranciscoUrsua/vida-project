# VIDA - Visión Integral de Derechos y Atención Social

[![Laravel](https://img.shields.io/badge/Laravel-10.x-red.svg)](https://laravel.com) [![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-blue.svg)](https://www.postgresql.org) [![Livewire](https://img.shields.io/badge/Livewire-3.x-blueviolet.svg)](https://livewire.laravel.com) [![Bootstrap](https://img.shields.io/badge/Bootstrap-5-purple.svg)](https://getbootstrap.com) [![License: GPL-3.0](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

## Introducción

**VIDA: Visión Integral de Derechos y Atención Social** es una plataforma para una gestión proactiva de servicios sociales. Diseñada para empoderar a trabajadores sociales, administradores y entidades públicas, VIDA facilita la coordinación, valoración y entrega de prestaciones y recursos sociales, asegurando un enfoque centrado en los derechos de las personas y comunidades vulnerables.

### ¿Qué resuelve VIDA?
- **Coordinación integral**: Integra múltiples fuentes de datos (e.g., solicitudes de prestaciones, evaluaciones de necesidades) en un flujo unificado para una atención social eficiente y personalizada.
- **Gestión proactiva**: Soporte para valoraciones preventivas, seguimiento de intervenciones y alertas en tiempo real, reduciendo burocracia y mejorando la respuesta a demandas sociales.
- **Inclusión y equidad**: Herramientas para garantizar el acceso a derechos sociales, con énfasis en diversidad, privacidad y escalabilidad para entornos locales o autonómicos.
- **Público objetivo**: Profesionales del trabajo social, servicios de atención social de ayuntamientos o CCAA, y organizaciones no gubernamentales (ONG) que buscan una solución open-source adaptable a normativas como la Ley de Servicios Sociales.

### Historia y Milestones
- **Inicio (2025)**: Desarrollo inicial del backend con Laravel y PostgreSQL para manejar la lógica de gestión de casos y prestaciones sociales.
- **Fase actual**: Backend funcional con endpoints API para valoraciones y flujos de atención. Frontend con Livewire y Bootstrap 5 para interfaces reactivas y accesibles (en desarrollo inicial).
- **Próximos pasos**: Implementación de componentes Livewire para formularios dinámicos y dashboards, pruebas con casos reales y despliegue en entornos seguros (e.g., cloud con cumplimiento RGPD).

Este proyecto está en etapa temprana de desarrollo, pero ya cuenta con una base sólida en el backend adaptada a necesidades reales de servicios sociales. ¡Colabora con nosotros para fortalecer su impacto en la atención social!

## Requisitos y Setup

### Requisitos del Sistema
- **PHP**: Versión 8.2 o superior.
- **Composer**: Para manejar dependencias de Laravel.
- **PostgreSQL**: Versión 15 o superior (recomendado para entornos con datos sensibles, con encriptación).
- **Servidor web**: Apache/Nginx o el servidor de desarrollo de Laravel (incluido).
- **Opcional**: Docker para entornos de contenedores; herramientas de cumplimiento como RGPD-checker.

### Dependencias Principales
| Componente | Versión | Descripción |
|------------|---------|-------------|
| Laravel | 10.x | Framework backend principal para lógica de gestión social. |
| PostgreSQL | 15+ | Base de datos relacional para almacenamiento seguro de casos y prestaciones. |
| Livewire | 3.x | Framework frontend reactivo integrado con Laravel para componentes dinámicos. |
| Bootstrap 5 | 5.x | Framework CSS para interfaces responsivas y accesibles. |
| Eloquent ORM | Incluido en Laravel | Para interacciones seguras con DB, con soporte para roles de usuarios. |

### Instrucciones de Instalación
1. **Clona el repositorio**:
   ```
   git clone https://github.com/tu-usuario/vida-project.git
   cd vida-project
   ```

2. **Instala dependencias del backend**:
   ```
   composer install
   ```

3. **Configura el entorno**:
   - Copia el archivo de ejemplo: `cp .env.example .env`
   - Genera la clave de aplicación: `php artisan key:generate`
   - Configura la base de datos en `.env` (e.g., `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, etc.). Asegura credenciales seguras para datos sensibles.

4. **Ejecuta migraciones y seeds** (crea tablas para casos sociales, prestaciones, etc.):
   ```
   php artisan migrate
   php artisan db:seed  # Opcional, para datos de prueba anónimos
   ```

5. **Instala paquetes frontend (Livewire y Bootstrap)**:
   - Livewire: `composer require livewire/livewire`
   - Bootstrap: Inclúyelo via CDN en layouts Blade o instala via npm si prefieres assets compilados: `npm install bootstrap`.
   - Publica assets si usas Vite: `npm install && npm run build`.

6. **Inicia el servidor de desarrollo**:
   ```
   php artisan serve
   ```
   Accede a `http://127.0.0.1:8000` para el backend y frontend integrado.

**Notas**: Asegúrate de tener PostgreSQL corriendo localmente o en un servicio seguro como ElephantSQL. Si usas Docker, revisa el `docker-compose.yml` (por implementar). Cumple con RGPD para datos personales.

## Guía Rápida

### Correr el Proyecto Localmente
1. Sigue los pasos de "Setup" arriba.
2. Prueba un endpoint básico (ejemplo: GET `/api/health` para verificar conexión):
   ```
   curl http://127.0.0.1:8000/api/health
   ```
   Respuesta esperada: `{"status": "OK", "db_connected": true}`.

### Casos de Uso Ejemplo
- **Valoración de necesidades**: Usa el endpoint `/api/casos?filter=prioridad` para obtener casos sociales filtrados de PostgreSQL.
- **Ejemplo de código (PHP/Laravel con Livewire)**:
  ```php
  // En un componente Livewire (e.g., resources/views/livewire/caso-lista.blade.php)
  class CasoLista extends Component
  {
      public $casos;

      public function mount()
      {
          $this->casos = DB::table('casos_sociales')->where('prioridad', 'alta')->with('prestaciones')->get();
      }

      public function render()
      {
          return view('livewire.caso-lista');
      }
  }
  ```
- **Próximo: Integración con Livewire**: Componentes para formularios de valoración y dashboards interactivos, estilizados con Bootstrap 5.

Para más detalles, consulta la documentación de API (por generar con Scribe).

### Troubleshooting Común
- **Error de DB**: Verifica credenciales en `.env` y que PostgreSQL esté activo con permisos correctos.
- **Permisos**: Ejecuta `chmod -R 755 storage bootstrap/cache`.
- **Logs**: Revisa `storage/logs/laravel.log` para errores en flujos sociales.

## Licencias

Este proyecto está bajo la licencia **GPL-3.0**. Ver el archivo [LICENSE](LICENSE) para detalles completos. Esto aplica al código original de VIDA; las dependencias mantienen sus licencias respectivas (ver abajo). La GPL-3.0 asegura que cualquier modificación o distribución se comparta libremente, promoviendo la colaboración en servicios sociales.

- **Código de VIDA**: GPL-3.0 (copyleft: protege el acceso abierto y la mejora comunitaria).
- **Laravel**: MIT License.
- **PostgreSQL**: PostgreSQL License (similar a BSD).
- **Livewire**: MIT License.
- **Bootstrap 5**: MIT License.

### Terceros
- Dependencias listadas en `composer.json` y `package.json` mantienen sus licencias respectivas. Incluimos copias en la carpeta `licenses/` para cumplimiento. Asegúrate de revisarlas en producción, especialmente para entornos con datos sensibles.

---
