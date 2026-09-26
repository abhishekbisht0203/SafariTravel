/**
 * Safari Travel — reveal system
 *
 * One IntersectionObserver drives every `[data-reveal]` element on the page,
 * plus the headline split-text effect. Elements are staggered inside a
 * `[data-reveal-group]` so a row of cards cascades without per-item timers.
 *
 * Contract with CSS: the "from" state lives in motion.css behind `.js`, so if
 * this module fails to load, content is still visible.
 */

import { qsa, onceVisible, prefersReducedMotion } from './util.js';

/** Shared observer — created lazily and reused for the whole page. */
let observer = null;

/**
 * Get (or create) the shared reveal observer.
 *
 * @returns {IntersectionObserver|null} Observer, or null when unsupported.
 */
function getObserver() {
  if (observer) {
    return observer;
  }

  if (typeof IntersectionObserver !== 'function') {
    return null;
  }

  observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        const el = entry.target;

        // Optional delay carried on the element itself.
        const delay = el.getAttribute('data-reveal-delay');
        if (delay) {
          el.style.setProperty('--st-reveal-delay', `${parseInt(delay, 10) || 0}ms`);
        }

        el.classList.add('is-in');

        // Nested split lines animate after the wrapper lands.
        const lines = el.querySelectorAll('.split-line');
        if (lines.length) {
          lines.forEach((line, index) => {
            window.setTimeout(
              () => line.classList.add('split-line--in'),
              index * 90
            );
          });
        }

        // Fire a one-off event so counters/animations can hook in.
        el.dispatchEvent(new CustomEvent('safari:revealed', { bubbles: false }));

        obs.unobserve(el);
      });
    },
    {
      // Start the animation slightly before the element is fully on screen.
      rootMargin: '0px 0px -10% 0px',
      threshold: 0.01,
    }
  );

  return observer;
}

/**
 * Wrap every word of an element's text in a masked span so it can slide up.
 * Whitespace between words is preserved with a real space node.
 *
 * @param {HTMLElement} element Target heading.
 */
function splitWords(element) {
  // Never split twice, and never split inside nested markup.
  if (element.dataset.splitDone === '1' || element.children.length > 0) {
    return;
  }

  const text = element.textContent.trim();
  if (!text) {
    return;
  }

  const fragment = document.createDocumentFragment();
  const words = text.split(/\s+/);

  words.forEach((word, index) => {
    const line = document.createElement('span');
    line.className = 'split-line';

    const inner = document.createElement('span');
    inner.className = 'split-word';
    inner.textContent = word;

    line.appendChild(inner);
    fragment.appendChild(line);

    if (index < words.length - 1) {
      // A real space keeps the text selectable and copy-pasteable.
      fragment.appendChild(document.createTextNode(' '));
    }
  });

  element.textContent = '';
  element.appendChild(fragment);
  element.dataset.splitDone = '1';
}

/**
 * Stagger the children of every group by index.
 *
 * @param {HTMLElement} group  Container element.
 * @param {string}      step   CSS time step, e.g. '70ms'.
 */
function stagger(group, step) {
  const children = qsa(':scope > [data-reveal]', group);
  children.forEach((child, index) => {
    const existing = child.getAttribute('data-reveal-delay');
    if (existing === null) {
      child.style.setProperty('--st-reveal-delay', `${index * parseInt(step, 10)}ms`);
    }
  });
}

/**
 * Initialise all reveals on the page.
 */
export function initReveals() {
  // Split-text headings opt in with data-split="lines".
  qsa('[data-split="lines"]').forEach(splitWords);

  // Children of a group cascade.
  const step = document.documentElement.style.getPropertyValue('--stagger') || '70ms';
  qsa('[data-reveal-group]').forEach((group) => {
    stagger(group, group.getAttribute('data-reveal-group') || step);
  });

  const io = getObserver();

  qsa('[data-reveal]').forEach((el) => {
    // Above the fold: reveal on the next frame so the first paint is never
    // blank, and never wait for a scroll event the visitor will never make.
    if (io === null) {
      el.classList.add('is-in');
      return;
    }

    io.observe(el);
  });

  // With reduced motion we still need `.is-in`, but skip the observation.
  if (prefersReducedMotion()) {
    qsa('[data-reveal]').forEach((el) => el.classList.add('is-in'));
    qsa('.split-line').forEach((line) => line.classList.add('split-line--in'));
  }
}

/**
 * Manually reveal an element now — used by tab panels and filtered results
 * that appear after initial load.
 *
 * @param {Element} element Target element.
 */
export function revealNow(element) {
  if (!element) {
    return;
  }
  element.classList.add('is-in');
  qsa('.split-line', element).forEach((line, index) => {
    window.setTimeout(() => line.classList.add('split-line--in'), index * 90);
  });
}

/**
 * Observe elements added to the DOM after init (AJAX-filtered results).
 */
export function observeNew(root) {
  const io = getObserver();
  if (!io) {
    return;
  }
  qsa('[data-reveal]', root).forEach((el) => io.observe(el));
}

/**
 * Reveal-on-visible helper for third-party markup (lightbox, drawer contents).
 *
 * @param {Element} element Target.
 */
export function revealWhenVisible(element) {
  onceVisible(element, (el) => el.classList.add('is-in'));
}
