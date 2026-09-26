import { defineConfig } from 'vite';
import { resolve } from 'path';
import { writeFileSync, unlinkSync } from 'fs';
import autoprefixer from 'autoprefixer';

/*
 * ---------------------------------------------------------------------------
 * Layout contract — the single source of truth for this file
 * ---------------------------------------------------------------------------
 * Source of truth   theme/assets/src/**          (edited by hand, never built)
 * Build output      theme/assets/dist/           (generated, git-ignored)
 * Dev server mount  /wp-content/themes/safari-theme/assets/dist/
 *                   (mirrors where the built files are served from by WordPress,
 *                    so one base string describes dev *and* production)
 *
 * theme/inc/assets.php reads the dev mount path from theme/.vite-dev, which
 * this config writes, so the two sides can never drift apart.
 */
const THEME_DIR = resolve(__dirname, 'theme');
const SRC_DIR = resolve(THEME_DIR, 'assets/src');
const DIST_DIR = resolve(THEME_DIR, 'assets/dist');
const VITE_DEV_FLAG = resolve(THEME_DIR, '.vite-dev');

const DEV_BASE = '/wp-content/themes/safari-theme/assets/dist/';
const DEV_PORT = 5173;
const DEV_HOST = 'localhost';

/* How often the dev server refreshes .vite-dev, and how long PHP waits before
 * declaring a flag stale. HEARTBEAT_MS * 4 gives plenty of slack for a slow
 * filesystem or a busy event loop. */
const HEARTBEAT_MS = 5000;
const STALE_AFTER_MS = 20000;

/**
 * Publish the dev server's mount URL to PHP, and keep it fresh.
 *
 * A static flag file is not enough: if the dev server is killed hard (closed
 * terminal, `Stop-Process`, machine sleep) the file survives and WordPress
 * keeps enqueueing a dead server, which renders the site unstyled. The
 * heartbeat lets theme/inc/assets.php detect that and fall back to the built
 * assets in theme/assets/dist instead.
 */
function viteDevFlagPlugin() {
  let clear = () => {};

  return {
    name: 'safari-vite-dev-flag',

    /* A build means "serve from dist/", so retire any dev flag first. */
    buildStart(config) {
      if (config.command !== 'build') {
        return;
      }
      try {
        unlinkSync(VITE_DEV_FLAG);
      } catch {}
    },

    configureServer(server) {
      const remove = () => {
        if (timer) {
          clearInterval(timer);
          timer = null;
        }
        try {
          unlinkSync(VITE_DEV_FLAG);
        } catch {}
      };
      clear = remove;

      let timer = null;

      const beat = () => {
        const local = server.resolvedUrls?.local?.[0];
        const port = server.config.server.port ?? DEV_PORT;
        const host =
          server.config.server.host === true
            ? DEV_HOST
            : server.config.server.host ?? DEV_HOST;
        const url = (local ?? `http://${host}:${port}${server.config.base}`).replace(
          /\/+$/,
          ''
        );
        writeFileSync(VITE_DEV_FLAG, url);
      };

      const start = () => {
        beat();
        timer = setInterval(beat, HEARTBEAT_MS);
        timer.unref?.();
      };

      if (server.httpServer && !server.httpServer.listening) {
        server.httpServer.once('listening', start);
      } else {
        start();
      }

      server.httpServer?.once('close', remove);
      process.once('exit', remove);
    },

    closeBundle() {
      clear();
    },
  };
}

export default defineConfig(({ command }) => ({
  root: resolve(THEME_DIR, 'assets'),
  base: DEV_BASE,

  build: {
    outDir: DIST_DIR,
    emptyOutDir: true,
    manifest: 'manifest.json',

    /*
     * Two entry points: the JS runtime and the stylesheet.
     *
     * Keeping the stylesheet as its own entry is what lets WordPress enqueue
     * it as a real <link> in BOTH modes. If the JS imported the CSS instead,
     * dev would only be able to inject it after the module evaluates, which
     * flashes unstyled content on every page load, and the production build
     * would hide the stylesheet inside the JS chunk.
     */
    rollupOptions: {
      input: {
        main: resolve(SRC_DIR, 'main.js'),
        style: resolve(SRC_DIR, 'main.css'),
      },
      output: {
        /*
         * One self-contained ES module. WordPress enqueues it as
         * `type="module"`, so there is a single request, no module waterfall,
         * and no bare specifiers to resolve — everything is bundled. The
         * module format is what lets the stylesheet be a separate entry; the
         * previous IIFE build forced CSS to live inside the JS chunk.
         */
        format: 'es',
        entryFileNames: '[name]-[hash].js',
        assetFileNames: '[name]-[hash][extname]',
      },
    },

    // Modern targets only — keeps the bundle small.
    target: ['es2020', 'chrome90', 'firefox88', 'safari14', 'edge90'],

    // Inline tiny assets as data URIs to save requests.
    assetsInlineLimit: 2048,

    /*
     * One stylesheet for the whole site. `main.js` imports no CSS, so the
     * single `style` entry is the only thing that can produce one — the result
     * is a single render-blocking request either way.
     */
    cssCodeSplit: true,

    sourcemap: command === 'serve',
  },

  css: {
    postcss: {
      // Vendors backdrop-filter, text-size-adjust, etc. per the browserslist
      // targets in package.json — the stylesheets stay prefix-free.
      plugins: [autoprefixer()],
    },
  },

  plugins: [viteDevFlagPlugin()],

  server: {
    host: DEV_HOST,
    port: DEV_PORT,
    strictPort: true,

    /*
     * WordPress on :8080 is the page origin, so the module scripts fetched
     * from :5173 are cross-origin. Reflecting the request origin is what lets
     * the browser execute them (and lets the HMR websocket connect).
     */
    cors: true,
  },
}));
