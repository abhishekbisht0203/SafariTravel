/**
 * Safari Travel — UI components
 *
 * Tabs · accordion · destination showcase rows · lightbox gallery ·
 * filter drawer behaviour · horizontal rail drag-to-scroll.
 *
 * Every component follows the WAI-ARIA authoring practices pattern and stays
 * fully usable with the keyboard.
 */

import { qs, qsa, on, lockScroll, prefersReducedMotion } from './util.js';

/* -------------------------------------------------------------------------
 * Tabs
 * ---------------------------------------------------------------------- */

/**
 * Wire a tablist. Supports horizontal arrow-key navigation.
 *
 * @param {HTMLElement} root Tablist container.
 */
function bindTabs(root) {
  const tabs = qsa('[role="tab"]', root);
  if (tabs.length < 2) {
    return;
  }

  /**
   * Activate a tab by index.
   *
   * @param {number} index Tab index.
   * @param {boolean} focus Whether to move focus.
   */
  const select = (index, focus) => {
    tabs.forEach((tab, i) => {
      const active = i === index;
      const panel = document.getElementById(tab.getAttribute('aria-controls'));
      tab.setAttribute('aria-selected', String(active));
      tab.setAttribute('tabindex', active ? '0' : '-1');
      if (panel) {
        panel.hidden = !active;
      }
    });

    if (focus) {
      tabs[index].focus();
    }
  };

  tabs.forEach((tab, index) => {
    on(tab, 'click', (event) => {
      event.preventDefault();
      select(index, false);
    });

    on(tab, 'keydown', (event) => {
      switch (event.key) {
        case 'ArrowRight':
        case 'ArrowDown':
          event.preventDefault();
          select((index + 1) % tabs.length, true);
          break;
        case 'ArrowLeft':
        case 'ArrowUp':
          event.preventDefault();
          select((index - 1 + tabs.length) % tabs.length, true);
          break;
        case 'Home':
          event.preventDefault();
          select(0, true);
          break;
        case 'End':
          event.preventDefault();
          select(tabs.length - 1, true);
          break;
        default:
          break;
      }
    });
  });
}

/* -------------------------------------------------------------------------
 * Accordion
 * ---------------------------------------------------------------------- */

/**
 * Wire an accordion. `data-accordion-single` closes siblings.
 *
 * @param {HTMLElement} root Accordion container.
 */
function bindAccordion(root) {
  const single = root.hasAttribute('data-accordion-single');
  const items = qsa('.accordion__item', root);

  items.forEach((item) => {
    const trigger = qs('.accordion__trigger', item);
    if (!trigger) {
      return;
    }

    on(trigger, 'click', () => {
      const isOpen = item.classList.contains('accordion__item--open');

      if (single && !isOpen) {
        items.forEach((other) => {
          if (other === item) {
            return;
          }
          other.classList.remove('accordion__item--open');
          const otherTrigger = qs('.accordion__trigger', other);
          if (otherTrigger) {
            otherTrigger.setAttribute('aria-expanded', 'false');
          }
        });
      }

      item.classList.toggle('accordion__item--open', !isOpen);
      trigger.setAttribute('aria-expanded', String(!isOpen));
    });
  });
}

/* -------------------------------------------------------------------------
 * Filter drawer (mobile) — mirrors desktop checkboxes into the URL
 * ---------------------------------------------------------------------- */

/**
 * Keep the visible result count in sync with the checked filters.
 *
 * @param {HTMLInputElement} input Checkbox that changed.
 */
function syncCount(input) {
  const bar = qs('.filter-bar__count');
  if (!bar) {
    return;
  }

  const group = input.closest('.filter-group');
  const name = group ? group.querySelector('.filter-group__title') : null;
  const label = name ? name.textContent.trim() : '';

  const url = new URL(window.location.href);
  const params = url.searchParams;
  const key = input.name;

  const existing = params.getAll(key).filter((v) => v !== input.value);
  if (input.checked) {
    existing.push(input.value);
  }

  if (existing.length) {
    params.set(key, existing);
  } else {
    params.delete(key);
  }

  // Replace the current history entry so Back returns to the previous state.
  window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);

  const chips = qs('.filter-chips');
  if (chips) {
    // Rendering chips is the theme's job on the next server render; for the
    // instant update we just refresh the count text.
    chips.dataset.dirty = '1';
  }

  // Give the visitor immediate feedback while the page reloads.
  if (bar) {
    const label2 = label ? `${label}: ` : '';
    bar.dataset.pendingLabel = `${label2}${input.checked ? input.value : ''}`;
  }
}

/**
 * Auto-submit filter forms on change, with a short debounce so a burst of
 * taps doesn't fire four requests.
 */
function initFilters() {
  qsa('.filter-form').forEach((form) => {
    let timer = 0;

    qsa('input[type="checkbox"], input[type="radio"]', form).forEach((input) => {
      on(input, 'change', () => {
        syncCount(input);
        window.clearTimeout(timer);
        timer = window.setTimeout(() => form.submit(), 320);
      });
    });

    // "Clear all" resets the form and reloads the unfiltered archive.
    const clear = qs('[data-filter-clear]', form);
    if (clear) {
      on(clear, 'click', (event) => {
        event.preventDefault();
        qsa('input[type="checkbox"], input[type="radio"]', form).forEach((input) => {
          input.checked = false;
        });
        form.submit();
      });
    }
  });

  // Sort dropdowns submit immediately.
  qsa('.sort-select').forEach((select) => {
    on(select, 'change', () => {
      const form = select.closest('form');
      if (form) {
        form.submit();
      }
    });
  });
}

/* -------------------------------------------------------------------------
 * Lightbox gallery
 * ---------------------------------------------------------------------- */

function initLightbox() {
  const items = qsa('[data-lightbox]');
  if (!items.length) {
    return;
  }

  const lightbox = qs('.lightbox');
  if (!lightbox) {
    return;
  }

  const image = qs('.lightbox__img', lightbox);
  const caption = qs('.lightbox__caption', lightbox);
  const closeBtn = qs('.lightbox__close', lightbox);
  let current = 0;
  let lastFocused = null;

  /**
   * Show a gallery image.
   *
   * @param {number} index Index into the gallery items.
   */
  const show = (index) => {
    const item = items[index];
    if (!item) {
      return;
    }

    current = index;
    const img = item.querySelector('img');
    if (!img) {
      return;
    }

    image.src = img.currentSrc || img.src;
    image.alt = img.alt || '';
    caption.textContent = item.getAttribute('data-caption') || '';
    caption.hidden = !caption.textContent;

    lightbox.classList.add('lightbox--open');
    lightbox.setAttribute('aria-hidden', 'false');
    lockScroll(true);
    if (closeBtn) {
      closeBtn.focus();
    }
  };

  const close = () => {
    lightbox.classList.remove('lightbox--open');
    lightbox.setAttribute('aria-hidden', 'true');
    lockScroll(false);
    if (lastFocused) {
      lastFocused.focus();
    }
  };

  const step = (delta) => show((current + delta + items.length) % items.length);

  items.forEach((item, index) => {
    item.setAttribute('tabindex', '0');
    item.setAttribute('role', 'button');
    on(item, 'click', () => {
      lastFocused = item;
      show(index);
    });
    on(item, 'keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        lastFocused = item;
        show(index);
      }
    });
  });

  if (closeBtn) {
    on(closeBtn, 'click', close);
  }

  on(lightbox, 'click', (event) => {
    if (event.target === lightbox) {
      close();
    }
  });

  on(document, 'keydown', (event) => {
    if (!lightbox.classList.contains('lightbox--open')) {
      return;
    }
    if (event.key === 'Escape') {
      close();
    } else if (event.key === 'ArrowRight') {
      step(1);
    } else if (event.key === 'ArrowLeft') {
      step(-1);
    }
  });
}

/* -------------------------------------------------------------------------
 * Destination showcase — hovering a row previews its image
 * ---------------------------------------------------------------------- */

function initShowcase() {
  const showcase = qs('[data-showcase]');
  if (!showcase) {
    return;
  }

  const stage = qs('[data-showcase-stage]', showcase);
  if (!stage) {
    return;
  }

  const image = qs('img', stage);
  if (!image) {
    return;
  }

  const activate = (row) => {
    const src = row.getAttribute('data-image');
    const alt = row.getAttribute('data-image-alt') || '';
    if (!src || src === image.src) {
      return;
    }

    // Preload so the swap is instant rather than flashing empty.
    const preloader = new Image();
    preloader.onload = () => {
      image.src = src;
      image.alt = alt;
    };
    preloader.src = src;
  };

  qsa('[data-showcase-row]', showcase).forEach((row) => {
    on(row, 'pointerenter', () => activate(row));
    on(row, 'focus', () => activate(row));
  });
}

/* -------------------------------------------------------------------------
 * Horizontal rails — drag to scroll with a pointer
 * ---------------------------------------------------------------------- */

function initRails() {
  if (prefersReducedMotion()) {
    return;
  }

  qsa('[data-rail]').forEach((rail) => {
    let down = false;
    let startX = 0;
    let startScroll = 0;
    let moved = false;

    on(rail, 'pointerdown', (event) => {
      // Ignore drags that start on a control.
      if (event.target.closest('a, button, input, select')) {
        return;
      }
      down = true;
      moved = false;
      startX = event.clientX;
      startScroll = rail.scrollLeft;
    });

    on(rail, 'pointermove', (event) => {
      if (!down) {
        return;
      }
      const delta = event.clientX - startX;
      if (Math.abs(delta) > 4) {
        moved = true;
        rail.scrollLeft = startScroll - delta;
      }
    });

    const release = () => {
      down = false;
    };

    on(rail, 'pointerup', release);
    on(rail, 'pointerleave', release);
    on(rail, 'pointercancel', release);

    // Suppress the click that follows a drag.
    on(rail, 'click', (event) => {
      if (moved) {
        event.preventDefault();
        event.stopPropagation();
        moved = false;
      }
    }, true);
  });
}

/* -------------------------------------------------------------------------
 * Public entry
 * ---------------------------------------------------------------------- */

/**
 * Initialise every UI component on the page.
 */
export function initUi() {
  qsa('[data-tabs]').forEach(bindTabs);
  qsa('[data-accordion]').forEach(bindAccordion);
  initFilters();
  initLightbox();
  initShowcase();
  initRails();
}
