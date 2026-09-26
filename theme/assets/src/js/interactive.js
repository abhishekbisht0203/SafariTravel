/**
 * Safari Travel — interactive micro-interactions
 *
 *   Count-up numbers · 3D card tilt · magnetic buttons · hero pointer glow
 *   floating dust motes · gold rule draw-on
 *
 * All pointer effects are pointer:fine only — a magnetic button on a phone is
 * dead weight, and a tilt that fires on scroll is a bug.
 */

import { qsa, onceVisible, on, clamp, tween, easeOutExpo, cssVar, prefersReducedMotion } from './util.js';

/** True when the primary pointer can hover precisely (mouse / trackpad). */
let finePointer = false;

/**
 * Detect a precise pointer and set `data-pointer="fine"` on <html> so CSS can
 * respond too.
 */
function detectPointer() {
  finePointer =
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  document.documentElement.setAttribute('data-pointer', finePointer ? 'fine' : 'coarse');
}

/* -------------------------------------------------------------------------
 * Count-up numbers
 * ---------------------------------------------------------------------- */

/**
 * Read the suffix and decimals off the element so "4.9" or "12k" works.
 *
 * @param {HTMLElement} el Counter element.
 * @returns {{target:number, decimals:number, prefix:string, suffix:string}}
 */
function readTarget(el) {
  const raw = el.getAttribute('data-target') || '0';
  const suffix = el.getAttribute('data-suffix') || '';
  const prefix = el.getAttribute('data-prefix') || '';
  const decimals = el.hasAttribute('data-decimals')
    ? parseInt(el.getAttribute('data-decimals'), 10) || 0
    : (raw.split('.')[1] || '').length;

  return { target: parseFloat(raw) || 0, decimals, prefix, suffix };
}

/**
 * Render a formatted number into the element.
 *
 * @param {HTMLElement} el    Target.
 * @param {number}      value Numeric value.
 * @param {object}      meta  Output from readTarget.
 */
function paint(el, value, meta) {
  const body = value.toLocaleString(undefined, {
    minimumFractionDigits: meta.decimals,
    maximumFractionDigits: meta.decimals,
  });
  el.textContent = `${meta.prefix}${body}${meta.suffix}`;
}

/**
 * Animate a number from zero to its target when it scrolls into view.
 *
 * @param {HTMLElement} el Counter element.
 */
function countUp(el) {
  const meta = readTarget(el);

  if (prefersReducedMotion() || meta.target === 0) {
    paint(el, meta.target, meta);
    return;
  }

  tween(
    1800,
    (eased) => {
      paint(el, meta.target * eased, meta);
    },
    easeOutExpo
  );
}

/**
 * Initialise all count-up elements.
 */
export function initCounters() {
  qsa('[data-target]').forEach((el) => {
    // Paint the real value immediately so no-JS and reduced-motion users are
    // never shown a zero.
    paint(el, 0, readTarget(el));

    onceVisible(el, countUp, { threshold: 0.4 });
  });
}

/* -------------------------------------------------------------------------
 * 3D tilt
 * ---------------------------------------------------------------------- */

/**
 * Attach a subtle 3D tilt to an element.
 *
 * @param {HTMLElement} el Target element.
 */
function bindTilt(el) {
  const max = parseFloat(el.getAttribute('data-tilt')) || 6;
  const glare = el.hasAttribute('data-tilt-glare');
  let rect = null;

  const onMove = (event) => {
    if (prefersReducedMotion()) {
      return;
    }

    if (!rect) {
      rect = el.getBoundingClientRect();
    }

    const px = (event.clientX - rect.left) / rect.width - 0.5;
    const py = (event.clientY - rect.top) / rect.height - 0.5;

    el.classList.add('is-tilting');
    el.style.transform = `perspective(900px) rotateX(${(-py * max).toFixed(2)}deg) rotateY(${(px * max).toFixed(2)}deg) translateZ(0)`;

    if (glare) {
      el.style.setProperty('--tilt-x', `${((px + 0.5) * 100).toFixed(1)}%`);
      el.style.setProperty('--tilt-y', `${((py + 0.5) * 100).toFixed(1)}%`);
    }
  };

  const onLeave = () => {
    el.classList.remove('is-tilting');
    el.style.transform = '';
    rect = null;
  };

  on(el, 'pointermove', onMove);
  on(el, 'pointerleave', onLeave);
}

/**
 * Initialise 3D tilt cards.
 */
export function initTilt() {
  if (!finePointer || prefersReducedMotion()) {
    return;
  }
  qsa('[data-tilt]').forEach(bindTilt);
}

/* -------------------------------------------------------------------------
 * Magnetic buttons
 * ---------------------------------------------------------------------- */

/**
 * Pull an element slightly toward the cursor.
 *
 * @param {HTMLElement} el Target element.
 */
function bindMagnetic(el) {
  const strength = parseFloat(el.getAttribute('data-magnetic')) || 0.28;

  const onMove = (event) => {
    if (prefersReducedMotion()) {
      return;
    }

    const rect = el.getBoundingClientRect();
    const x = event.clientX - (rect.left + rect.width / 2);
    const y = event.clientY - (rect.top + rect.height / 2);

    el.style.transform = `translate(${x * strength}px, ${y * strength}px)`;
  };

  const onLeave = () => {
    el.style.transform = '';
  };

  on(el, 'pointermove', onMove);
  on(el, 'pointerleave', onLeave);
  on(el, 'blur', onLeave);
}

/**
 * Initialise magnetic elements.
 */
export function initMagnetic() {
  if (!finePointer || prefersReducedMotion()) {
    return;
  }
  qsa('[data-magnetic]').forEach(bindMagnetic);
}

/* -------------------------------------------------------------------------
 * Hero pointer glow
 * ---------------------------------------------------------------------- */

/**
 * Follow the pointer with a soft radial highlight inside a hero.
 *
 * @param {HTMLElement} hero Hero element.
 */
function bindHeroGlow(hero) {
  const glow = hero.querySelector('.hero__glow');
  if (!glow) {
    return;
  }

  const setX = cssVar(glow, 'left');
  const setY = cssVar(glow, 'top');

  on(hero, 'pointermove', (event) => {
    if (prefersReducedMotion()) {
      return;
    }
    const rect = hero.getBoundingClientRect();
    setX(event.clientX - rect.left);
    setY(event.clientY - rect.top);
  });
}

/* -------------------------------------------------------------------------
 * Dust motes
 * ---------------------------------------------------------------------- */

/**
 * Scatter decorative motes inside a container.
 *
 * @param {HTMLElement} container Target (usually `.dust`).
 */
function scatterDust(container) {
  if (prefersReducedMotion()) {
    return;
  }

  // Density scales with area so mobile doesn't get a haze of 60 nodes.
  const area = container.getBoundingClientRect();
  const count = Math.round(clamp((area.width * area.height) / 26000, 8, 34));

  const fragment = document.createDocumentFragment();

  for (let i = 0; i < count; i += 1) {
    const mote = document.createElement('span');
    mote.className = 'dust__mote';
    mote.style.left = `${(Math.random() * 100).toFixed(2)}%`;
    mote.style.top = `${(Math.random() * 100).toFixed(2)}%`;
    mote.style.setProperty('--mote-duration', `${(6 + Math.random() * 8).toFixed(1)}s`);
    mote.style.setProperty('--mote-delay', `${(-Math.random() * 10).toFixed(1)}s`);
    mote.style.opacity = (0.25 + Math.random() * 0.6).toFixed(2);
    mote.style.transform = `scale(${(0.6 + Math.random() * 1.1).toFixed(2)})`;
    fragment.appendChild(mote);
  }

  container.appendChild(fragment);
}

/* -------------------------------------------------------------------------
 * SVG line draw
 * ---------------------------------------------------------------------- */

/**
 * Animate SVG strokes when the shape scrolls into view.
 *
 * @param {SVGElement} svg Target SVG carrying the `draw` class.
 */
function bindDraw(svg) {
  const shapes = svg.querySelectorAll('path, line, circle, rect, polyline');

  shapes.forEach((shape) => {
    if (typeof shape.getTotalLength !== 'function') {
      return;
    }
    let length = 0;
    try {
      length = shape.getTotalLength();
    } catch {
      // Some shapes (e.g. <rect> in older engines) throw — bail out on that
      // shape only rather than the whole illustration.
      return;
    }
    if (length > 0) {
      shape.style.setProperty('--st-dash', String(Math.ceil(length)));
    }
  });

  onceVisible(svg, (el) => el.classList.add('is-in'), { threshold: 0.25 });
}

/* -------------------------------------------------------------------------
 * Frame image settle
 * ---------------------------------------------------------------------- */

/**
 * Add a `frame--loaded` class once an image inside `.frame` has decoded, so the
 * CSS can drop the initial 1.04 scale.
 */
export function initImageSettle() {
  qsa('.frame > img, .frame picture > img').forEach((img) => {
    const frame = img.closest('.frame');
    if (!frame) {
      return;
    }

    if (img.complete && img.naturalWidth > 0) {
      frame.classList.add('frame--loaded');
      return;
    }

    const mark = () => frame.classList.add('frame--loaded');
    on(img, 'load', mark, { once: true });
    on(img, 'error', mark, { once: true });
  });
}

/* -------------------------------------------------------------------------
 * Public entry
 * ---------------------------------------------------------------------- */

/**
 * Initialise every micro-interaction on the page.
 */
export function initInteractive() {
  detectPointer();

  initCounters();
  initImageSettle();

  qsa('.hero').forEach(bindHeroGlow);
  qsa('.dust').forEach(scatterDust);
  qsa('.draw').forEach(bindDraw);

  // Tilt and magnetic need to wait one tick: the reveal transition may still
  // be applying a transform, and the two would fight.
  window.requestAnimationFrame(() => {
    initTilt();
    initMagnetic();
  });
}
