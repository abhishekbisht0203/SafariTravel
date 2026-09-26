/**
 * Safari Travel — homepage extras
 *
 * Everything here is decorative; the page must be fully readable and
 * navigable with this module absent. It is bundled into the single main
 * runtime (total cost < 1 KB gzipped), so no conditional loading is needed.
 *
 * Replaces the previous Swiper carousel: tours, destinations and testimonials
 * use CSS scroll-snap rails (zero JS, better INP) and the lead-generation
 * counter / hero bits are already in the main runtime.
 */

import { qs, on, prefersReducedMotion } from './util.js';
import { revealNow } from './reveal.js';

/**
 * Scroll a rail by one "page" when its arrow is clicked.
 *
 * @param {HTMLElement} rail Rail element.
 * @param {number}      dir  -1 for previous, 1 for next.
 */
function scrollRail(rail, dir) {
  const card = rail.firstElementChild;
  const step = card ? card.getBoundingClientRect().width + 24 : rail.clientWidth * 0.8;
  rail.scrollBy({
    left: step * dir,
    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
  });
}

/**
 * Wire every rail's previous/next controls and keep them in sync with the
 * scroll position.
 */
function initRailControls() {
  document.querySelectorAll('[data-rail]').forEach((rail) => {
    const prev = qs('[data-rail-prev]', rail);
    const next = qs('[data-rail-next]', rail);

    if (prev) {
      on(prev, 'click', () => scrollRail(rail, -1));
    }
    if (next) {
      on(next, 'click', () => scrollRail(rail, 1));
    }

    const sync = () => {
      const max = rail.scrollWidth - rail.clientWidth - 2;
      if (prev) {
        prev.disabled = rail.scrollLeft <= 2;
      }
      if (next) {
        next.disabled = rail.scrollLeft >= max;
      }
    };

    on(rail, 'scroll', sync, { passive: true });
    sync();
  });
}

/**
 * Fade the hero content out as the visitor scrolls past it, so the first
 * section below feels like a reveal rather than a jump.
 */
function initHeroExit() {
  const hero = qs('.hero');
  const content = hero ? qs('.hero__inner', hero) : null;
  if (!content || prefersReducedMotion()) {
    return;
  }

  const update = () => {
    const height = hero.getBoundingClientRect().height;
    if (height <= 0) {
      return;
    }
    const progress = Math.min(window.scrollY / height, 1);
    content.style.opacity = String(1 - progress * 0.85);
    content.style.transform = `translate3d(0, ${(progress * 40).toFixed(1)}px, 0)`;
  };

  on(window, 'scroll', update, { passive: true });
}

/**
 * Countdown to the next fixed departure listed in the offers strip.
 * Uses `data-countdown="YYYY-MM-DD"`.
 */
function initCountdown() {
  const target = qs('[data-countdown]');
  if (!target || prefersReducedMotion()) {
    return;
  }

  const deadline = new Date(`${target.getAttribute('data-countdown')}T00:00:00`);
  if (Number.isNaN(deadline.getTime())) {
    return;
  }

  const render = () => {
    const diff = deadline.getTime() - Date.now();
    if (diff <= 0) {
      target.textContent = 'Departing now';
      return;
    }

    const days = Math.floor(diff / 864e5);
    const hours = Math.floor((diff % 864e5) / 36e5);
    target.textContent = days > 0 ? `${days} day${days === 1 ? '' : 's'} to go` : `${hours}h to go`;
  };

  render();
  window.setInterval(render, 6e5);
}

/**
 * Fade the showcase stage back in whenever the previewed image swaps.
 */
function initShowcasePreview() {
  const stage = qs('[data-showcase-stage]');
  if (!stage) {
    return;
  }

  on(stage, 'transitionend', () => revealNow(stage));
}

/**
 * Boot homepage extras. Safe to call on any page — every hook is a no-op when
 * its markup is absent.
 */
export function initHome() {
  initRailControls();
  initHeroExit();
  initCountdown();
  initShowcasePreview();
}
