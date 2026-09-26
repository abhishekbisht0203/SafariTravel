/**
 * Safari Travel — main frontend entry
 *
 * Bundled on every page. Boots the design system's behaviour in a single
 * ordered pass so that reveal observers are registered before any layout
 * measurement happens.
 *
 * Order matters:
 *   1. flagJs()      — unlocks the CSS reveal states
 *   2. reveals       — registers IntersectionObservers
 *   3. shell         — drawers/overlays (changes layout when opened)
 *   4. interactive   — counters, tilt, dust
 *   5. parallax      — last, because it measures the settled layout
 */

import './main.css';

import { flagJs } from './js/util.js';
import { initReveals } from './js/reveal.js';
import { initScrollEffects, refreshParallax } from './js/parallax.js';
import { initInteractive } from './js/interactive.js';
import { initShell } from './js/shell.js';
import { initUi } from './js/ui.js';
import { initLeadForms } from './js/lead-form.js';
import { initHome } from './js/home.js';

/**
 * Boot the site.
 */
function boot() {
  flagJs();
  initReveals();
  initShell();
  initUi();
  initInteractive();
  initLeadForms();
  initScrollEffects();
  initHome();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}

// Re-measure parallax after late-loading content shifts the page, and again
// when the visitor changes orientation.
window.addEventListener('load', refreshParallax, { once: true });
window.addEventListener('orientationchange', refreshParallax, { passive: true });
