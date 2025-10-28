# Arquitectura de VIDA

## Introducción

La arquitectura de **VIDA: Visión Integral de Derechos y Atención Social** sigue un modelo cliente-servidor modular, diseñado para garantizar escalabilidad, seguridad y facilidad de mantenimiento en el contexto de la gestión de servicios sociales. El backend, basado en Laravel, actúa como núcleo central manejando la lógica de negocio sensible (como valoraciones de casos y prestaciones), mientras que la base de datos PostgreSQL asegura el almacenamiento robusto y compliant con normativas como RGPD. El frontend en Angular (en fase de desarrollo) proporcionará interfaces intuitivas para trabajadores sociales, fomentando una interacción proactiva y centrada en el usuario. Esta estructura permite integraciones futuras con sistemas externos (e.g., APIs autonómicas) y soporta despliegues en entornos cloud o locales. A continuación, se presenta un diagrama de alto nivel que ilustra las componentes clave y sus interacciones.

```mermaid
graph TD
    %% Definir nodos con estilos
    A[Usuario / Trabajador Social<br/>Dispositivo]:::user
    B[Frontend: Angular<br/>Dashboards & Formularios]:::frontend
    C[API Gateway / Laravel Backend<br/>Endpoints RESTful<br/>Autenticación Sanctum]:::backend
    D[Base de Datos: PostgreSQL<br/>Casos Sociales, Prestaciones<br/>Migraciones Eloquent]:::db
    E[Integraciones Externas<br/>e.g., APIs de CCAA o RGPD]:::external

    %% Flujos de conexión
    A -->|"HTTPS Requests"| B
    B -->|"API Calls (JSON)"| C
    C -->|"Queries / Inserts"| D
    C -->|"Opcional: Webhooks"| E
    D -->|"Responses"| C

    %% Estilos CSS para nodos
    classDef user fill:#e1f5fe,stroke:#01579b,stroke-width:2px,color:#000
    classDef frontend fill:#f3e5f5,stroke:#4a148c,stroke-width:2px,color:#000
    classDef backend fill:#e8f5e8,stroke:#1b5e20,stroke-width:2px,color:#000
    classDef db fill:#fff3e0,stroke:#e65100,stroke-width:2px,color:#000
    classDef external fill:#fce4ec,stroke:#880e4f,stroke-width:2px,color:#000
```
