# Instrucciones CLI — Instalación y configuración de herramientas de calidad de código

> Stack: Laravel 12 / PHP 8.3 / nwidart v12 / Filament 5.3 / Pest
> Leer `docs/principios-vida360.md` y `docs/documentacion-proyecto.md` antes de ejecutar.
> Esta tarea no modifica lógica de negocio ni migraciones. Solo añade tooling de desarrollo.

---

## Parte 1: PHPStan + Larastan

### 1.1 Instalación

```bash
composer require --dev nunomaduro/larastan
```

Larastan incluye PHPStan como dependencia. No instalar PHPStan por separado.

### 1.2 Fichero de configuración

Crear `phpstan.neon` en la raíz del proyecto:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app/
        - Modules/

    # Nivel 6: equilibrio entre rigor y ruido en un proyecto en desarrollo activo.
    # Subir a 7 u 8 cuando los módulos principales estén estables.
    level: 6

    # Ignorar ficheros generados o de terceros dentro de módulos
    excludePaths:
        - Modules/*/database/migrations/*
        - Modules/*/database/seeders/*

    # Eloquent: permitir propiedades dinámicas de modelos
    checkModelProperties: true
    checkMissingIterableValueType: false

    # Ignorar errores conocidos y aceptados de librerías de terceros
    ignoreErrors:
        # Filament usa métodos mágicos ampliamente
        - '#Call to an undefined method Filament\\#'
        # staudenmeir/adjacency-list: métodos recursivos dinámicos
        - '#Call to an undefined method.*HasRecursiveRelationships#'

    # Bootstrapping de Laravel para que PHPStan entienda los helpers
    bootstrapFiles:
        - vendor/autoload.php
```

### 1.3 Script en composer.json

Añadir en la sección `scripts` de `composer.json`:

```json
"analyse": "vendor/bin/phpstan analyse --memory-limit=512M",
"analyse-ci": "vendor/bin/phpstan analyse --memory-limit=512M --no-progress --error-format=github"
```

### 1.4 Primera ejecución y baseline

La primera vez que se ejecuta en un proyecto existente PHPStan encontrará errores heredados.
Generar un baseline para ignorarlos y trabajar solo con errores nuevos:

```bash
vendor/bin/phpstan analyse --generate-baseline --memory-limit=512M
```

Esto crea `phpstan-baseline.neon`. Añadir al `phpstan.neon`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - phpstan-baseline.neon   # ← añadir esta línea
```

Commitear tanto `phpstan.neon` como `phpstan-baseline.neon`.
El objetivo es reducir el baseline progresivamente, nunca añadir entradas nuevas.

---

## Parte 2: Laravel Pint

### 2.1 Instalación

Pint viene incluido en Laravel 12. Verificar que está disponible:

```bash
vendor/bin/pint --version
```

Si no está disponible:

```bash
composer require --dev laravel/pint
```

### 2.2 Fichero de configuración

Crear `pint.json` en la raíz del proyecto:

```json
{
    "preset": "laravel",
    "rules": {
        "ordered_imports": {
            "sort_algorithm": "alpha"
        },
        "no_unused_imports": true,
        "single_quote": true,
        "trailing_comma_in_multiline": true,
        "array_syntax": {
            "syntax": "short"
        },
        "phpdoc_align": {
            "align": "left"
        },
        "phpdoc_separation": true,
        "no_extra_blank_lines": {
            "tokens": ["extra", "throw", "use"]
        }
    }
}
```

### 2.3 Scripts en composer.json

```json
"format": "vendor/bin/pint",
"format-check": "vendor/bin/pint --test"
```

`format` corrige el código. `format-check` solo verifica sin modificar (para CI).

---

## Parte 3: Rector

### 3.1 Instalación

```bash
composer require --dev rector/rector
```

### 3.2 Fichero de configuración

Crear `rector.php` en la raíz del proyecto:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Laravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/Modules',
    ])
    ->withSkip([
        // No tocar migraciones ni seeders: son historial inmutable
        __DIR__ . '/Modules/*/database/migrations',
        __DIR__ . '/Modules/*/database/seeders',
        // No tocar ficheros generados por Filament
        __DIR__ . '/app/Filament',
    ])
    // PHP 8.3: aplicar mejoras de sintaxis modernas
    ->withPhpSets(php83: true)
    // Conjunto de buenas prácticas generales
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        // Laravel: modernización de helpers y patrones
        LaravelSetList::LARAVEL_120,
    ])
    // NO activar NAMING: cambia nombres de variables y puede romper
    // lógica de dominio en un proyecto con términos en español
    ->withoutParallel(); // desactivar paralelismo en primera ejecución
```

### 3.3 Scripts en composer.json

```json
"rector": "vendor/bin/rector process",
"rector-dry": "vendor/bin/rector process --dry-run"
```

**Importante:** usar siempre `rector-dry` antes de `rector` para revisar los cambios propuestos.

---

## Parte 4: GitHub Actions — CI pipeline

Crear `.github/workflows/quality.yml`:

```yaml
name: Calidad de código

on:
  push:
    branches: [master]
  pull_request:
    branches: [master]

jobs:
  quality:
    runs-on: ubuntu-latest
    name: PHPStan + Pint

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.3
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pgsql, pdo_pgsql
          coverage: none

      - name: Instalar dependencias
        run: composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Verificar formato (Pint)
        run: composer format-check

      - name: Análisis estático (PHPStan)
        run: composer analyse-ci
```

**Nota:** Rector no se ejecuta en CI automáticamente porque modifica código.
Se ejecuta manualmente en sesiones de refactorización planificadas.

---

## Parte 5: Actualizar .gitignore

Verificar que `.gitignore` no excluye los ficheros de configuración generados.
Añadir si no está:

```
# Herramientas de calidad — SÍ incluir en git
# phpstan.neon          ← NO ignorar
# phpstan-baseline.neon ← NO ignorar
# pint.json             ← NO ignorar
# rector.php            ← NO ignorar

# Cache de herramientas — NO incluir en git
/.phpstan-cache/
/.rector-cache/
```

---

## Checklist de finalización

- [ ] `composer require --dev nunomaduro/larastan` ejecutado
- [ ] `phpstan.neon` creado con nivel 6 y paths correctos para módulos nwidart
- [ ] Baseline generado: `phpstan-baseline.neon` existe y está commiteado
- [ ] `pint.json` creado con preset laravel
- [ ] `composer require --dev rector/rector` ejecutado
- [ ] `rector.php` creado con paths correctos y migraciones excluidas
- [ ] Scripts añadidos a `composer.json`: `analyse`, `format`, `format-check`, `rector`, `rector-dry`
- [ ] `.github/workflows/quality.yml` creado
- [ ] Primera ejecución de Pint sobre todo el proyecto: `composer format`
- [ ] Primera ejecución de PHPStan: `composer analyse` — documentar número de errores en baseline
- [ ] `.gitignore` actualizado para excluir caches pero incluir configuraciones
- [ ] Entrada añadida a `CHANGELOG.md`
- [ ] `SESSION.md` actualizado
