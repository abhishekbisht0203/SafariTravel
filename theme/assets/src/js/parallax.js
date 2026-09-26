/**
 * Safari Travel — scroll effects
 *
 * A single requestAnimationFrame loop services every scroll-driven effect so
 * the page never runs more than one layout read per frame:
 *   - `[data-parallax]`        translate by a fraction of its offset
 *   - `[data-scroll-progress]` scaleX the reading-progress bar
 *   - `[data-sticky-progress]` width of a section's own progress bar
 *
 * Everything is a no-op when the visitor prefers reduced motion.
 */

import { qsa, rafThrottle, clamp, on, prefersReducedMotion } from './util.js';

const PARALLAX_ATTR = 'data-parallax';
const PROGRESS_ATTR = 'data-scroll-progress';

let layers = [];
let progressBars = [];
let rafId = 0;
let running = false;

/**
 * Cache the geometry of every registered layer.
 */
function measure() {
  layers = qsa(PARALLAX_ATTR).map((el) => ({
    el,
    // data-parallax="0.2" → moves 20% of its travel distance.
    speed: parseFloat(el.getAttribute(PARALLAX_ATTR)) || 0.15,
    // Optional axis limit so a tall image doesn't fly off screen.
    axis: el.getAttribute('data-parallax-axis') || 'y',
    top: 0,
    height: 0,
  }));

  progressBars = qsa(PROGRESS_ATTR).map((el) => ({
    el,
    // data-scroll-progress="document" → whole page; otherwise the nearest
    // positioned ancestor's box.
    mode: el.getAttribute(PROGRESS_ATTR),
    section: el.getAttribute(PROGRESS_ATTR) === 'section' ? findSection(el) : null,
  }));
}

/**
 * Walk up to the first ancestor that establishes a positioning context.
 *
 * @param {Element} el Starting element.
 * @returns {Element|null} The section element, or null.
 */
function findSection(el) {
  let node = el.parentElement;
  while (node && node !== document.body) {
    const position = window.getComputedStyle(node).position;
    if (position === 'relative' || position === 'absolute' || position === 'sticky') {
      return node;
    }
    node = node.parentElement;
  }
  return null;
}

/**
 * Refresh cached measurements. Called on init, on resize and after fonts load.
 */
export function refreshParallax() {
  measure();
  update(true);
}

/**
 * Per-frame work.
 *
 * @param {boolean} force Skip the short-circuit and write values anyway.
 */
function update(force = false) {
  const scrollY = window.scrollY;
  const viewport = window.innerHeight;
  const docHeight = document.documentElement.scrollHeight - viewport;

  layers.forEach((layer) => {
    const rect = layer.el.getBoundingClientRect();

    if (rect.bottom < -200 || rect.top > viewport + 200) {
      return;
    }

    // Offset of the element's centre from the viewport centre.
    const centreOffset = rect.top + rect.height / 2 - viewport / 2;

    if (layer.axis === 'x') {
      layer.el.style.setProperty(
        '--st-parallax',
        `${(-centreOffset * layer.speed).toFixed(2)}px`
      );
      return;
    }

    // Pure translate — no scale, so the compositor does the work.
    const travel = clamp(centreOffset * layer.speed, -viewport * 0.4, viewport * 0.4);
    layer.el.style.setProperty('--st-parallax', `${travel.toFixed(2)}px`);
  });

  progressBars.forEach((bar) => {
    let ratio;

    if (bar.section) {
      const rect = bar.section.getBoundingClientRect();
      const total = rect.height + viewport;
      ratio = clamp((viewport - rect.top) / total, 0, 1);
    } else {
      ratio = docHeight > 0 ? clamp(scrollY / docHeight, 0, 1) : 0;
    }

    bar.el.style.setProperty('--st-progress', ratio.toFixed(4));
  });

  if (force) {
    return;
  }
}

/**
 * Start the rAF loop, but only while something is actually on screen.
 */
function start() {
  if (running) {
    return;
  }
  running = true;
  rafId = window.requestAnimationFrame(tick);
}

function stop() {
  if (!running) {
    return;
  }
  running = false;
  window.cancelAnimationFrame(rafId);
}

function tick() {
  if (!running) {
    return;
  }
  update();
  rafId = window.requestAnimationFrame(tick);
}

/**
 * Wire up scroll listeners and measure on demand.
 */
export function initScrollEffects() {
  if (prefersReducedMotion()) {
    // Zero out every layer so nothing is left half-offset.
    qsa(PARALLAX_ATTR).forEach((el) => el.style.setProperty('--st-parallax', '0px'));
    return;
  }

  measure();
  update(true);

  const onScroll = rafThrottle(() => {
    update();
    // Idle the loop when nothing is parallaxing or reading progress.
    const needsWork =
      layers.some((l) => {
        const rect = l.el.getBoundingClientRect();
        return rect.bottom > -200 && rect.top < window.innerHeight + 200;
      }) || progressBars.length > 0;

    if (!needsWork) {
      stop();
    }
  });

  on(window, 'scroll', onScroll, { passive: true });
  on(window, 'resize', () => measure(), { passive: true });

  // Re-measure once webfonts have settled, since layout height may change.
  if (document.fonts && typeof document.fonts.ready?.then === 'function') {
    document.fonts.ready.then(() => {
      measure();
      update(true);
    });
  }

  start();
}

/**
 * Recompute after a dynamic layout change (drawer open, filter applied).
 */
export function remeasureScrollEffects() {
  measure();
  update(true);
  start();
}
