import { defineConfig } from 'vite';
import { resolve } from 'path';
import { writeFileSync, unlinkSync } from 'fs';

export default defineConfig(({ command }) => ({
  root: resolve(__dirname, 'theme/assets'),
  base: '/wp-content/themes/safari-theme/assets/dist/',

  build: {
    outDir: resolve(__dirname, 'theme/assets/dist'),
    emptyOutDir: true,
    manifest: 'manifest.json',
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'theme/assets/src/main.js'),
        home: resolve(__dirname, 'theme/assets/src/home.js'),
      },
      output: {
        // Hashed filenames for cache busting
        entryFileNames: '[name]-[hash].js',
        chunkFileNames: '[name]-[hash].js',
        assetFileNames: '[name]-[hash][extname]',
      },
    },
    // Target modern browsers only — keep bundle small
    target: ['es2020', 'chrome90', 'firefox88', 'safari14', 'edge90'],
    cssCodeSplit: false,
    sourcemap: command === 'serve',
  },

  css: {
    postcss: {
      plugins: [],
    },
  },

  // Dev server writes its URL to .vite-dev for the PHP enqueue helper
  plugins: [
    {
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
    // Allow requests from the WordPress Docker container
    origin: 'http://localhost:5173',
  },
}));
