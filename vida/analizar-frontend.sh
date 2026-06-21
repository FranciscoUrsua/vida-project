#!/usr/bin/env bash
# =============================================================================
# VIDA 360 — Analizador de deuda técnica de front-end
# Uso: bash analizar-frontend.sh [ruta-raíz-proyecto]
# Por defecto analiza el directorio actual.
# Genera: INFORME_FRONTEND.md en el directorio de ejecución.
# =============================================================================

ROOT="${1:-.}"
VIEWS_DIR="$ROOT/resources/views"
CSS_DIR="$ROOT/resources/css"
JS_DIR="$ROOT/resources/js"
OUT="INFORME_FRONTEND.md"

# Colores para terminal
RED='\033[0;31m'; YELLOW='\033[1;33m'; GREEN='\033[0;32m'; NC='\033[0m'

if [ ! -d "$VIEWS_DIR" ]; then
  echo -e "${RED}ERROR: No encuentro $VIEWS_DIR. Ejecuta desde la raíz del proyecto Laravel o pasa la ruta como argumento.${NC}"
  exit 1
fi

echo -e "${GREEN}Analizando front-end VIDA 360...${NC}"
echo ""

# Fecha y cabecera del informe
cat > "$OUT" << EOF
# VIDA 360 — Informe de Deuda Técnica de Front-end

**Generado:** $(date '+%d/%m/%Y %H:%M')
**Analizado:** $VIEWS_DIR

---

> Este informe detecta cuatro categorías de problemas en las vistas Blade/Livewire:
> 1. CSS ad-hoc (bloques \`<style>\` y atributos \`style=""\` estructurales)
> 2. Colores hardcodeados (hex, rgb, rgba) fuera de los tokens VIDA
> 3. JS innecesario para navegación (donde un \`<a href>\` bastaría)
> 4. Clases Bootstrap residuales (incompatibles con el design system VIDA)

---

EOF

# ---------------------------------------------------------------------------
# Función auxiliar: añade una sección al informe
# ---------------------------------------------------------------------------
section() {
  echo -e "\n## $1\n" >> "$OUT"
}

subsection() {
  echo -e "\n### $1\n" >> "$OUT"
}

code_block() {
  echo '```' >> "$OUT"
  cat >> "$OUT"
  echo '```' >> "$OUT"
}

# Contador global de problemas
TOTAL=0

# ---------------------------------------------------------------------------
# 1. BLOQUES <style> en vistas Blade
# ---------------------------------------------------------------------------
section "1. Bloques \`<style>\` en vistas Blade"

echo "Buscando bloques <style>..." >&2

STYLE_RESULTS=$(grep -rn "<style" "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null)

if [ -z "$STYLE_RESULTS" ]; then
  echo "✅ Sin ocurrencias." >> "$OUT"
else
  COUNT=$(echo "$STYLE_RESULTS" | wc -l | tr -d ' ')
  TOTAL=$((TOTAL + COUNT))
  echo "⚠️ **$COUNT ocurrencia(s) encontrada(s):**" >> "$OUT"
  echo "" >> "$OUT"
  echo "$STYLE_RESULTS" | while IFS= read -r line; do
    FILE=$(echo "$line" | cut -d: -f1 | sed "s|$ROOT/||")
    LINENO=$(echo "$line" | cut -d: -f2)
    CONTENT=$(echo "$line" | cut -d: -f3- | sed 's/^[[:space:]]*//')
    echo "- \`$FILE:$LINENO\` — \`$CONTENT\`" >> "$OUT"
  done
fi

# ---------------------------------------------------------------------------
# 2. ATRIBUTOS style="" estructurales (excluye valores dinámicos con {{ }})
# ---------------------------------------------------------------------------
section "2. Atributos \`style=\"\"\` estructurales"

echo "Buscando style=\"...\"..." >&2

# Excluimos líneas que contienen {{ o var( porque son valores dinámicos legítimos
INLINE_RESULTS=$(grep -rn 'style="' "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null \
  | grep -v '{{' \
  | grep -v 'var(' \
  | grep -v 'style=""')

if [ -z "$INLINE_RESULTS" ]; then
  echo "✅ Sin ocurrencias estructurales." >> "$OUT"
else
  COUNT=$(echo "$INLINE_RESULTS" | wc -l | tr -d ' ')
  TOTAL=$((TOTAL + COUNT))
  echo "⚠️ **$COUNT ocurrencia(s) — estilos inline estructurales (no dinámicos):**" >> "$OUT"
  echo "" >> "$OUT"
  echo "$INLINE_RESULTS" | while IFS= read -r line; do
    FILE=$(echo "$line" | cut -d: -f1 | sed "s|$ROOT/||")
    LINENO=$(echo "$line" | cut -d: -f2)
    CONTENT=$(echo "$line" | cut -d: -f3- | sed 's/^[[:space:]]*//')
    echo "- \`$FILE:$LINENO\` — \`${CONTENT:0:120}\`" >> "$OUT"
  done
fi

# ---------------------------------------------------------------------------
# 3. COLORES HARDCODEADOS (hex, rgb, rgba)
# ---------------------------------------------------------------------------
section "3. Colores hardcodeados"

echo "Buscando colores hardcodeados..." >&2

# Hex en atributos style o class (bg-[#...], text-[#...], border-[#...])
HEX_RESULTS=$(grep -rEn '(style=|bg-\[|text-\[|border-\[|fill=|stroke=)[^"]*#[0-9a-fA-F]{3,8}' \
  "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null)

RGB_RESULTS=$(grep -rEn 'rgb(a)?\([0-9]' \
  "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null)

# También en archivos CSS propios
CSS_HEX=$(grep -rEn '#[0-9a-fA-F]{3,8}' \
  "$CSS_DIR" --include="*.css" 2>/dev/null \
  | grep -v 'colors_and_type.css' \
  | grep -v 'app-operativo.css' \
  | grep -v '/\*' 2>/dev/null || true)

ALL_COLOR="$HEX_RESULTS
$RGB_RESULTS
$CSS_HEX"
ALL_COLOR=$(echo "$ALL_COLOR" | grep -v '^$')

if [ -z "$ALL_COLOR" ]; then
  echo "✅ Sin colores hardcodeados detectados." >> "$OUT"
else
  COUNT=$(echo "$ALL_COLOR" | wc -l | tr -d ' ')
  TOTAL=$((TOTAL + COUNT))
  echo "⚠️ **$COUNT ocurrencia(s) de colores fuera de tokens VIDA:**" >> "$OUT"
  echo "" >> "$OUT"

  subsection "3a. Hex / rgb en vistas Blade"
  BLADE_COLOR=$(echo "$HEX_RESULTS
$RGB_RESULTS" | grep -v '^$')
  if [ -z "$BLADE_COLOR" ]; then
    echo "✅ Sin ocurrencias en Blade." >> "$OUT"
  else
    echo "$BLADE_COLOR" | while IFS= read -r line; do
      FILE=$(echo "$line" | cut -d: -f1 | sed "s|$ROOT/||")
      LINENO=$(echo "$line" | cut -d: -f2)
      CONTENT=$(echo "$line" | cut -d: -f3- | sed 's/^[[:space:]]*//')
      echo "- \`$FILE:$LINENO\` — \`${CONTENT:0:120}\`" >> "$OUT"
    done
  fi

  subsection "3b. Hex en archivos CSS (fuera de tokens)"
  if [ -z "$CSS_HEX" ]; then
    echo "✅ Sin ocurrencias en CSS." >> "$OUT"
  else
    echo "$CSS_HEX" | while IFS= read -r line; do
      FILE=$(echo "$line" | cut -d: -f1 | sed "s|$ROOT/||")
      LINENO=$(echo "$line" | cut -d: -f2)
      CONTENT=$(echo "$line" | cut -d: -f3- | sed 's/^[[:space:]]*//')
      echo "- \`$FILE:$LINENO\` — \`${CONTENT:0:120}\`" >> "$OUT"
    done
  fi
fi

# ---------------------------------------------------------------------------
# 4. JS INNECESARIO PARA NAVEGACIÓN
# ---------------------------------------------------------------------------
section "4. JavaScript innecesario para navegación"

echo "Buscando JS de navegación innecesario..." >&2

subsection "4a. \`window.location\` en Blade / JS"

WIN_LOC=$(grep -rEn 'window\.location' \
  "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null)
WIN_LOC_JS=$(grep -rEn 'window\.location' \
  "$JS_DIR" --include="*.js" --include="*.ts" 2>/dev/null 2>/dev/null || true)

ALL_WIN="$WIN_LOC
$WIN_LOC_JS"
ALL_WIN=$(echo "$ALL_WIN" | grep -v '^$')

if [ -z "$ALL_WIN" ]; then
  echo "✅ Sin ocurrencias." >> "$OUT"
else
  COUNT=$(echo "$ALL_WIN" | wc -l | tr -d ' ')
  TOTAL=$((TOTAL + COUNT))
  echo "⚠️ **$COUNT ocurrencia(s):**" >> "$OUT"
  echo "" >> "$OUT"
  echo "$ALL_WIN" | while IFS= read -r line; do
    FILE=$(echo "$line" | cut -d: -f1 | sed "s|$ROOT/||")
    LINENO=$(echo "$line" | cut -d: -f2)
    CONTENT=$(echo "$line" | cut -d: -f3- | sed 's/^[[:space:]]*//')
    echo "- \`$FILE:$LINENO\` — \`${CONTENT:0:120}\`" >> "$OUT"
  done
fi

subsection "4b. \`@click\` con rutas o redirects (posible \`<a href>\`)"

CLICK_ROUTE=$(grep -rEn "@click[^=]*=.*route\(|@click[^=]*=.*href|@click[^=]*=.*navigate|@click[^=]*=.*push\(" \
  "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null)

if [ -z "$CLICK_ROUTE" ]; then
  echo "✅ Sin ocurrencias detectadas." >> "$OUT"
else
  COUNT=$(echo "$CLICK_ROUTE" | wc -l | tr -d ' ')
  TOTAL=$((TOTAL + COUNT))
  echo "⚠️ **$COUNT ocurrencia(s) — revisar si puede ser \`<a href>\`:**" >> "$OUT"
  echo "" >> "$OUT"
  echo "$CLICK_ROUTE" | while IFS= read -r line; do
    FILE=$(echo "$line" | cut -d: -f1 | sed "s|$ROOT/||")
    LINENO=$(echo "$line" | cut -d: -f2)
    CONTENT=$(echo "$line" | cut -d: -f3- | sed 's/^[[:space:]]*//')
    echo "- \`$FILE:$LINENO\` — \`${CONTENT:0:120}\`" >> "$OUT"
  done
fi

subsection "4c. \`wire:click\` con métodos de tipo redirect/navigate"

WIRE_NAV=$(grep -rEn 'wire:click="(redirect|navigate|goTo|irA|irA|visitUrl|openUrl|goto)' \
  "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null)

if [ -z "$WIRE_NAV" ]; then
  echo "✅ Sin ocurrencias detectadas." >> "$OUT"
else
  COUNT=$(echo "$WIRE_NAV" | wc -l | tr -d ' ')
  TOTAL=$((TOTAL + COUNT))
  echo "⚠️ **$COUNT ocurrencia(s):**" >> "$OUT"
  echo "" >> "$OUT"
  echo "$WIRE_NAV" | while IFS= read -r line; do
    FILE=$(echo "$line" | cut -d: -f1 | sed "s|$ROOT/||")
    LINENO=$(echo "$line" | cut -d: -f2)
    CONTENT=$(echo "$line" | cut -d: -f3- | sed 's/^[[:space:]]*//')
    echo "- \`$FILE:$LINENO\` — \`${CONTENT:0:120}\`" >> "$OUT"
  done
fi

subsection "4d. \`x-on:click\` con navegación (Alpine.js)"

ALPINE_NAV=$(grep -rEn 'x-on:click[^=]*=.*location|x-on:click[^=]*=.*route\(' \
  "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null)

if [ -z "$ALPINE_NAV" ]; then
  echo "✅ Sin ocurrencias detectadas." >> "$OUT"
else
  COUNT=$(echo "$ALPINE_NAV" | wc -l | tr -d ' ')
  TOTAL=$((TOTAL + COUNT))
  echo "⚠️ **$COUNT ocurrencia(s):**" >> "$OUT"
  echo "" >> "$OUT"
  echo "$ALPINE_NAV" | while IFS= read -r line; do
    FILE=$(echo "$line" | cut -d: -f1 | sed "s|$ROOT/||")
    LINENO=$(echo "$line" | cut -d: -f2)
    CONTENT=$(echo "$line" | cut -d: -f3- | sed 's/^[[:space:]]*//')
    echo "- \`$FILE:$LINENO\` — \`${CONTENT:0:120}\`" >> "$OUT"
  done
fi

# ---------------------------------------------------------------------------
# 5. CLASES BOOTSTRAP RESIDUALES
# ---------------------------------------------------------------------------
section "5. Clases Bootstrap residuales"

echo "Buscando clases Bootstrap..." >&2

# Clases Bootstrap más comunes que no deben aparecer en Livewire
BS_PATTERN='class="[^"]*\b(btn|btn-|col-|row |row"|container|container-|form-control|form-select|form-check|input-group|alert alert|card |card"|badge |badge"|modal |modal"|navbar |navbar"|d-flex|d-block|d-none|d-grid|justify-content-|align-items-|text-center|text-end|text-start|fw-bold|fw-semibold|fs-|mb-[0-9]|mt-[0-9]|ms-[0-9]|me-[0-9]|p-[0-9]|px-[0-9]|py-[0-9]|pt-[0-9]|pb-[0-9])\b'

BS_RESULTS=$(grep -rEn "$BS_PATTERN" \
  "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null)

if [ -z "$BS_RESULTS" ]; then
  echo "✅ Sin clases Bootstrap detectadas en Blade." >> "$OUT"
else
  COUNT=$(echo "$BS_RESULTS" | wc -l | tr -d ' ')
  TOTAL=$((TOTAL + COUNT))
  echo "⚠️ **$COUNT ocurrencia(s) de clases Bootstrap (incompatibles con el design system VIDA):**" >> "$OUT"
  echo "" >> "$OUT"

  # Agrupar por archivo
  echo "$BS_RESULTS" | awk -F: '{print $1}' | sort -u | while read -r filepath; do
    RELFILE=$(echo "$filepath" | sed "s|$ROOT/||")
    echo "" >> "$OUT"
    echo "**\`$RELFILE\`**" >> "$OUT"
    echo "" >> "$OUT"
    grep -n "$filepath" <<< "$BS_RESULTS" 2>/dev/null | while IFS= read -r line; do
      LINENO=$(echo "$line" | cut -d: -f2)
      CONTENT=$(echo "$line" | cut -d: -f3- | sed 's/^[[:space:]]*//')
      echo "- Línea $LINENO — \`${CONTENT:0:120}\`" >> "$OUT"
    done || true
    # Fallback directo
    grep -n "$BS_PATTERN" "$filepath" 2>/dev/null | while IFS= read -r line; do
      LINENO=$(echo "$line" | cut -d: -f1)
      CONTENT=$(echo "$line" | cut -d: -f2- | sed 's/^[[:space:]]*//')
      echo "- Línea $LINENO — \`${CONTENT:0:120}\`" >> "$OUT"
    done
  done
fi

# ---------------------------------------------------------------------------
# 6. BOTONES SIN COLOR DE TEXTO EXPLÍCITO (patrón del botón invisible)
# ---------------------------------------------------------------------------
section "6. Botones sin color de texto explícito"

echo "Buscando botones sin text-* explícito..." >&2

echo "Detecta elementos \`<button\` o \`role=\"button\"\` con clases \`bg-*\` pero sin \`text-*\`." >> "$OUT"
echo "" >> "$OUT"

# Busca líneas con <button que tengan bg- pero no text-
BTN_NO_TEXT=$(grep -rEn '<button[^>]+class="[^"]*bg-[^"]*"' \
  "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null \
  | grep -v 'text-')

if [ -z "$BTN_NO_TEXT" ]; then
  echo "✅ Sin botones detectados con \`bg-\` sin \`text-\`." >> "$OUT"
else
  COUNT=$(echo "$BTN_NO_TEXT" | wc -l | tr -d ' ')
  TOTAL=$((TOTAL + COUNT))
  echo "⚠️ **$COUNT botón/es con fondo definido pero sin color de texto — riesgo de invisibilidad:**" >> "$OUT"
  echo "" >> "$OUT"
  echo "$BTN_NO_TEXT" | while IFS= read -r line; do
    FILE=$(echo "$line" | cut -d: -f1 | sed "s|$ROOT/||")
    LINENO=$(echo "$line" | cut -d: -f2)
    CONTENT=$(echo "$line" | cut -d: -f3- | sed 's/^[[:space:]]*//')
    echo "- \`$FILE:$LINENO\` — \`${CONTENT:0:120}\`" >> "$OUT"
  done
fi

# ---------------------------------------------------------------------------
# RESUMEN EJECUTIVO
# ---------------------------------------------------------------------------
echo "" >> "$OUT"
echo "---" >> "$OUT"
echo "" >> "$OUT"
echo "## Resumen ejecutivo" >> "$OUT"
echo "" >> "$OUT"
echo "| Categoría | Ocurrencias |" >> "$OUT"
echo "|-----------|-------------|" >> "$OUT"

C1=$(grep -rn "<style" "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null | wc -l | tr -d ' ')
C2=$(grep -rn 'style="' "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null | grep -v '{{' | grep -v 'var(' | grep -v 'style=""' | wc -l | tr -d ' ')
C3HEX=$(grep -rEn '(style=|bg-\[|text-\[|border-\[).*#[0-9a-fA-F]{3,8}' "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null | wc -l | tr -d ' ')
C3RGB=$(grep -rEn 'rgb(a)?\([0-9]' "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null | wc -l | tr -d ' ')
C3CSS=$(grep -rEn '#[0-9a-fA-F]{3,8}' "$CSS_DIR" --include="*.css" 2>/dev/null | grep -v 'colors_and_type.css' | grep -v 'app-operativo.css' | wc -l | tr -d ' ' 2>/dev/null || echo 0)
C4=$(grep -rEn 'window\.location|@click.*route\(|wire:click=.*(redirect|navigate)|x-on:click.*location' "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null | wc -l | tr -d ' ')
C5=$(grep -rEn "$BS_PATTERN" "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null | wc -l | tr -d ' ')
C6=$(grep -rEn '<button[^>]+class="[^"]*bg-[^"]*"' "$VIEWS_DIR" --include="*.blade.php" 2>/dev/null | grep -v 'text-' | wc -l | tr -d ' ')

echo "| 1. Bloques \`<style>\` en Blade | $C1 |" >> "$OUT"
echo "| 2. Atributos \`style=\"\"\` estructurales | $C2 |" >> "$OUT"
echo "| 3a. Colores hex/rgb en Blade | $((C3HEX + C3RGB)) |" >> "$OUT"
echo "| 3b. Colores hex en CSS ad-hoc | $C3CSS |" >> "$OUT"
echo "| 4. JS innecesario para navegación | $C4 |" >> "$OUT"
echo "| 5. Clases Bootstrap residuales | $C5 |" >> "$OUT"
echo "| 6. Botones sin color de texto | $C6 |" >> "$OUT"
echo "| **TOTAL** | **$((C1+C2+C3HEX+C3RGB+C3CSS+C4+C5+C6))** |" >> "$OUT"
echo "" >> "$OUT"
echo "_Informe generado por \`analizar-frontend.sh\`. Revisa manualmente las ocurrencias antes de corregirlas._" >> "$OUT"

echo ""
echo -e "${GREEN}✅ Informe generado: $OUT${NC}"
echo -e "${YELLOW}   Total de problemas detectados: $((C1+C2+C3HEX+C3RGB+C3CSS+C4+C5+C6))${NC}"
echo ""
