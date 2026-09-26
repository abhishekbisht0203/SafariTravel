import { defineConfig } from 'vite';
import { resolve } from 'path';
import { writeFileSync, unlinkSync } from 'fs';
import autoprefixer from 'autoprefixer';

export default defineConfig(({ command }) => ({
  root: resolve(__dirname, 'theme/assets'),
  base: '/wp-content/themes/safari-theme/assets/dist/',

  build: {
    outDir: resolve(__dirname, 'theme/assets/dist'),
    emptyOutDir: true,
    manifest: 'manifest.json',
    cssCodeSplit: false,
    sourcemap: command === 'serve',

    rollupOptions: {
      /*
       * Single entry, IIFE output.
       *
       * WordPress enqueues assets as classic scripts, so a multi-chunk ESM
       * build would emit bare `import` statements the browser cannot resolve.
       * One self-contained file is one request, no module waterfall, and works
       * on every browser in the support matrix — which is what plan §10 asks
       * for. The whole runtime is single-digit KB gzipped.
       */
      input: resolve(__dirname, 'theme/assets/src/main.js'),
      output: {
        format: 'iife',
        inlineDynamicImports: true,
        entryFileNames: 'main-[hash].js',
        assetFileNames: '[name]-[hash][extname]',
      },
    },

    // Modern targets only — keeps the bundle small.
    target: ['es2020', 'chrome90', 'firefox88', 'safari14', 'edge90'],

    // Inline tiny assets as data URIs to save requests.
    assetsInlineLimit: 2048,
  },

  css: {
    postcss: {
      // Vendors backdrop-filter, text-size-adjust, etc. per the browserslist
      // targets in package.json — the stylesheets stay prefix-free.
      plugins: [autoprefixer()],
    },
  },

  plugins: [
    {
      // Dev server writes its URL to .vite-dev for the PHP enqueue helper.
      name: 'write-vite-dev-flag',
      configureServer(server) {
        server.httpServer?.once('listening', () => {
          const info = server.resolvedUrls?.local?.[0] ?? 'http://localhost:5173/';
          writeFileSync(resolve(__dirname, 'theme/.vite-dev'), info.replace(/\/$/, ''));
        });
      },
      closeBundle() {
        try {
          unlinkSync(resolve(__dirname, 'theme/.vite-dev'));
        } catch {}
      },
    },
  ],

  server: {
    port: 5173,
    strictPort: true,
    cors: true,
    origin: 'http://localhost:5173',
  },
}));
