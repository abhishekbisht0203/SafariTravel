import js from '@eslint/js';

export default [
  js.configs.recommended,
  {
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        window: 'readonly',
        document: 'readonly',
        navigator: 'readonly',
        console: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        setInterval: 'readonly',
        clearInterval: 'readonly',
        requestAnimationFrame: 'readonly',
        cancelAnimationFrame: 'readonly',
        Image: 'readonly',
        URL: 'readonly',
        URLSearchParams: 'readonly',
        FormData: 'readonly',
        Event: 'readonly',
        CustomEvent: 'readonly',
        AbortController: 'readonly',
        IntersectionObserver: 'readonly',
        matchMedia: 'readonly',
        getComputedStyle: 'readonly',
        localStorage: 'readonly',
        CSS: 'readonly',
        fetch: 'readonly',
        // WordPress globals
        wp: 'readonly',
        safariData: 'readonly',
        dataLayer: 'writable',
        gtag: 'readonly',
      },
    },
    rules: {
      'no-console': ['warn', { allow: ['error', 'warn'] }],
      'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      'prefer-const': 'error',
      'no-var': 'error',
      eqeqeq: ['error', 'always'],
      curly: 'error',
    },
  },
  {
    ignores: ['theme/assets/dist/**', 'node_modules/**', '*.config.js'],
  },
];
