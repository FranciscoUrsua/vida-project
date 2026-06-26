#!/usr/bin/env bash
# =============================================================================
# security-check.sh — Revisión automática de seguridad (PHP + JS)
# Uso: ./security-check.sh           (revisión completa)
#      ./security-check.sh --quick   (solo auditorías de dependencias)
#
# Herramientas necesarias:
#   PHP:  composer (>=2.4), local-php-security-checker (~/bin/)
#   JS:   npm, eslint + eslint-plugin-security (npm install --save-dev)
#
# Como hook pre-commit:
#   cp security-check.sh .git/hooks/pre-commit && chmod +x .git/hooks/pre-commit
# =============================================================================

set -euo pipefail

# ── Directorio de la aplicación Laravel ──────────────────────────────────────
# El script puede ejecutarse desde vida/ directamente o como hook desde la raíz del repo.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "$SCRIPT_DIR/composer.json" ]]; then
  VIDA_DIR="$SCRIPT_DIR"
elif [[ -f "$(pwd)/vida/composer.json" ]]; then
  VIDA_DIR="$(pwd)/vida"
else
  VIDA_DIR="$(pwd)"
fi

# ── Colores ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

# ── Flags ────────────────────────────────────────────────────────────────────
QUICK=false
for arg in "$@"; do
  case $arg in
    --quick) QUICK=true ;;
  esac
done

# ── Contadores ───────────────────────────────────────────────────────────────
ERRORS=0
WARNINGS=0

# ── Helpers ──────────────────────────────────────────────────────────────────
header() { echo -e "\n${CYAN}${BOLD}▶ $1${RESET}"; }
ok()     { echo -e "  ${GREEN}✔ $1${RESET}"; }
warn()   { echo -e "  ${YELLOW}⚠ $1${RESET}"; WARNINGS=$((WARNINGS + 1)); }
fail()   { echo -e "  ${RED}✘ $1${RESET}"; ERRORS=$((ERRORS + 1)); }

require() {
  if ! command -v "$1" &>/dev/null; then
    warn "Herramienta no encontrada: $1 — omitiendo esta comprobación"
    return 1
  fi
  return 0
}

# ── 1. DEPENDENCIAS PHP (composer audit) ─────────────────────────────────────
header "Dependencias PHP (composer audit)"

if require composer; then
  if (cd "$VIDA_DIR" && composer audit --no-interaction 2>&1); then
    ok "Sin vulnerabilidades en dependencias PHP"
  else
    fail "Vulnerabilidades encontradas en dependencias PHP"
  fi
fi

# ── 2. ADVISORIES PHP (local-php-security-checker) ───────────────────────────
header "Advisories PHP (local-php-security-checker)"

# Comprueba composer.lock contra la base de datos de advisories de Symfony.
# Complementa a composer audit con una fuente de datos independiente.
# Instalar: https://github.com/fabpot/local-php-security-checker/releases
#   curl -L .../local-php-security-checker_linux_amd64 -o ~/bin/local-php-security-checker
#   chmod +x ~/bin/local-php-security-checker

if require local-php-security-checker; then
  if (cd "$VIDA_DIR" && local-php-security-checker 2>&1); then
    ok "Sin vulnerabilidades conocidas en composer.lock"
  else
    fail "Vulnerabilidades encontradas en composer.lock"
  fi
fi

# ── 3. DEPENDENCIAS JS (npm audit) ───────────────────────────────────────────
header "Dependencias JS (npm audit)"

if [[ -f "$VIDA_DIR/package.json" ]]; then
  if require npm; then
    # --audit-level=moderate: falla con severidad media o superior
    # --omit=dev: solo dependencias de producción
    if (cd "$VIDA_DIR" && npm audit --audit-level=moderate --omit=dev 2>&1); then
      ok "Sin vulnerabilidades en dependencias JS (producción)"
    else
      fail "Vulnerabilidades encontradas en dependencias JS"
    fi
  fi
else
  warn "No se encontró package.json — omitiendo auditoría JS"
fi

# Fin en modo --quick
if $QUICK; then
  echo ""
  echo -e "${BOLD}Modo rápido: solo dependencias. Errores: ${ERRORS} | Avisos: ${WARNINGS}${RESET}"
  exit $ERRORS
fi

# ── 4. ANÁLISIS ESTÁTICO JS (ESLint security) ────────────────────────────────
header "Análisis de seguridad JS (ESLint)"

# Requiere: npm install --save-dev eslint eslint-plugin-security

if [[ -f "$VIDA_DIR/package.json" ]]; then
  if [[ -f "$VIDA_DIR/node_modules/.bin/eslint" ]]; then
    ESLINT_VERSION=$("$VIDA_DIR/node_modules/.bin/eslint" --version 2>/dev/null | sed 's/v//')
    ESLINT_MAJOR="${ESLINT_VERSION%%.*}"
    if [[ "$ESLINT_MAJOR" -ge 9 ]]; then
      # ESLint v9+ usa flat config; los flags --no-eslintrc/--plugin inline fueron eliminados.
      warn "ESLint v${ESLINT_VERSION} (flat config): configura eslint-plugin-security en eslint.config.js para análisis inline"
    else
      if "$VIDA_DIR/node_modules/.bin/eslint" \
          --ext .js,.ts \
          --no-eslintrc \
          --plugin security \
          --rule 'security/detect-eval-with-expression: error' \
          --rule 'security/detect-unsafe-regex: error' \
          --rule 'security/detect-no-csrf-before-method-override: error' \
          --rule 'security/detect-buffer-noassert: error' \
          --rule 'security/detect-disable-mustache-escape: error' \
          --rule 'security/detect-new-buffer: error' \
          --rule 'security/detect-non-literal-regexp: warn' \
          --rule 'security/detect-non-literal-require: warn' \
          --rule 'security/detect-possible-timing-attacks: warn' \
          --rule 'security/detect-object-injection: warn' \
          --rule 'security/detect-child-process: warn' \
          resources/js/ 2>&1; then
        ok "ESLint: sin problemas de seguridad en JS"
      else
        fail "ESLint detectó problemas de seguridad en JS"
      fi
    fi
  else
    warn "ESLint no instalado: npm install --save-dev eslint eslint-plugin-security"
  fi
fi

# ── 5. BÚSQUEDA DE SECRETOS HARDCODEADOS ─────────────────────────────────────
header "Búsqueda de secretos hardcodeados"

SECRET_PATTERNS=(
  'password\s*=\s*["\x27][^"\x27]{4,}'    # password = "valor"
  'secret\s*=\s*["\x27][^"\x27]{4,}'      # secret = "valor"
  'api_key\s*=\s*["\x27][^"\x27]{4,}'     # api_key = "valor"
  'private_key\s*=\s*["\x27][^"\x27]{4,}' # private_key = "valor"
  'Bearer [A-Za-z0-9\-_]{20,}'             # Bearer token
  'AKIA[0-9A-Z]{16}'                       # AWS Access Key
  'ghp_[A-Za-z0-9]{36}'                   # GitHub Personal Access Token
)

EXCLUDE_ARGS=(
  --exclude-dir=.git
  --exclude-dir=vendor
  --exclude-dir=node_modules
  --exclude-dir=storage
  --exclude='*.test.*'
  --exclude='*Test*'
  --exclude='*Fake*'
  --exclude='*.env.example'
  --exclude='security-check.sh'
)

SECRETS_FOUND=false
for pattern in "${SECRET_PATTERNS[@]}"; do
  results=$(grep -rniP "$pattern" \
    --include="*.php" \
    --include="*.js" \
    --include="*.ts" \
    --include="*.env" \
    "${EXCLUDE_ARGS[@]}" \
    . 2>/dev/null | grep -v '\.env\.example' | grep -v '#\s' || true)

  if [[ -n "$results" ]]; then
    SECRETS_FOUND=true
    fail "Posible secreto hardcodeado (patrón: $pattern):"
    echo "$results" | head -5 | sed 's/^/    /'
  fi
done

if ! $SECRETS_FOUND; then
  ok "No se detectaron secretos hardcodeados evidentes"
fi

# ── 6. COMPROBACIONES ESPECÍFICAS DE LARAVEL ─────────────────────────────────
header "Comprobaciones específicas de Laravel"

# 6a. APP_DEBUG en producción
if [[ -f .env ]]; then
  if grep -qiP '^APP_DEBUG\s*=\s*true' .env; then
    if grep -qiP '^APP_ENV\s*=\s*production' .env; then
      fail ".env: APP_DEBUG=true con APP_ENV=production"
    else
      warn ".env: APP_DEBUG=true — asegúrate de que no llega a producción"
    fi
  else
    ok ".env: APP_DEBUG desactivado"
  fi
fi

# 6b. APP_KEY configurada
if [[ -f .env ]]; then
  if ! grep -qP '^APP_KEY\s*=\s*base64:.{40,}' .env; then
    fail ".env: APP_KEY no configurada o inválida"
  else
    ok ".env: APP_KEY configurada"
  fi
fi

# 6c. Uso de eval() en PHP
EVAL_FOUND=$(grep -rn '\beval\s*(' \
  --include="*.php" \
  --exclude-dir=vendor \
  --exclude-dir=.git \
  . 2>/dev/null | grep -v '^\s*//' || true)

if [[ -n "$EVAL_FOUND" ]]; then
  fail "Uso de eval() detectado en PHP:"
  echo "$EVAL_FOUND" | head -5 | sed 's/^/    /'
else
  ok "Sin uso de eval() en PHP"
fi

# 6d. Funciones de ejecución de sistema
SHELL_FOUND=$(grep -rn '\b\(shell_exec\|exec\|system\|passthru\|proc_open\)\s*(' \
  --include="*.php" \
  --exclude-dir=vendor \
  --exclude-dir=.git \
  . 2>/dev/null | grep -v '^\s*//' || true)

if [[ -n "$SHELL_FOUND" ]]; then
  warn "Funciones de ejecución de sistema encontradas (revisar manualmente):"
  echo "$SHELL_FOUND" | head -10 | sed 's/^/    /'
else
  ok "Sin llamadas a funciones de ejecución de sistema"
fi

# 6e. Consultas SQL crudas
SQL_RAW=$(grep -rn '\bDB::\(select\|statement\|unprepared\)\s*(' \
  --include="*.php" \
  --exclude-dir=vendor \
  --exclude-dir=.git \
  . 2>/dev/null | grep -v '^\s*//' || true)

if [[ -n "$SQL_RAW" ]]; then
  warn "Consultas SQL crudas encontradas (verificar que usan binding de parámetros):"
  echo "$SQL_RAW" | head -10 | sed 's/^/    /'
else
  ok "Sin consultas SQL crudas"
fi

# 6f. Rutas sin middleware de autenticación (auth o auth:sanctum)
# Busca grupos de rutas o rutas individuales sin middleware auth
UNPROTECTED=$(grep -rn "Route::\(get\|post\|put\|patch\|delete\)" \
  --include="*.php" \
  routes/ 2>/dev/null | grep -v 'middleware' | grep -v '^\s*//' || true)

if [[ -n "$UNPROTECTED" ]]; then
  warn "Rutas sin middleware explícito (verificar si requieren autenticación):"
  echo "$UNPROTECTED" | head -10 | sed 's/^/    /'
fi

# ── 7. RESUMEN FINAL ──────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}════════════════════════════════════════${RESET}"
echo -e "${BOLD}  Revisión de seguridad completada${RESET}"
echo -e "${BOLD}════════════════════════════════════════${RESET}"

if [[ $ERRORS -eq 0 && $WARNINGS -eq 0 ]]; then
  echo -e "  ${GREEN}${BOLD}✔ Todo correcto — sin problemas detectados${RESET}"
elif [[ $ERRORS -eq 0 ]]; then
  echo -e "  ${YELLOW}${BOLD}⚠ ${WARNINGS} aviso(s) — revisar antes de subir a producción${RESET}"
else
  echo -e "  ${RED}${BOLD}✘ ${ERRORS} error(es) + ${WARNINGS} aviso(s)${RESET}"
  echo -e "  ${RED}Corrige los errores antes de hacer commit${RESET}"
fi
echo ""

# Código de salida: 0 si no hay errores, 1 si los hay
# Los warnings no bloquean el commit
exit $ERRORS
