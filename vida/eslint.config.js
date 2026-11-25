// Configuración de ESLint (flat config, ESLint 9+) para el análisis de seguridad del JS propio.
// La usan el hook de pre-commit (security-check.sh) y el CI (.github/workflows/security.yml):
// un único sitio para las reglas, en lugar de los flags en línea que ESLint 9 eliminó.
import security from 'eslint-plugin-security';
import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        // Solo el JS de la aplicación: lo compilado y las dependencias no se analizan
        ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**', 'bootstrap/cache/**'],
    },
    {
        files: ['resources/js/**/*.js', 'Modules/*/resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser },
        },
        plugins: { security },
        rules: {
            // Errores básicos de JavaScript (variables sin declarar, código inalcanzable…)
            ...js.configs.recommended.rules,

            // Seguridad: lo que bloquea el commit y el CI
            'security/detect-eval-with-expression': 'error',
            'security/detect-unsafe-regex': 'error',
            'security/detect-no-csrf-before-method-override': 'error',
            'security/detect-buffer-noassert': 'error',
            'security/detect-disable-mustache-escape': 'error',
            'security/detect-new-buffer': 'error',
            'security/detect-pseudoRandomBytes': 'error',
            'security/detect-bidi-characters': 'error',
            'security/detect-invisible-characters': 'error',

            // Seguridad: avisos para revisar a mano (muchos falsos positivos)
            'security/detect-non-literal-regexp': 'warn',
            'security/detect-non-literal-require': 'warn',
            'security/detect-non-literal-fs-filename': 'warn',
            'security/detect-possible-timing-attacks': 'warn',
            'security/detect-object-injection': 'warn',
            'security/detect-child-process': 'warn',
        },
    },
];
