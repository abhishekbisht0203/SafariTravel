/**
 * Safari Travel — global shell
 *
 * Announcement bar · sticky header state · mobile drawer · search overlay
 * (with live suggestions) · back-to-top · mobile CTA bar.
 *
 * Every panel here follows the same contract: `aria-expanded` on the trigger,
 * `hidden`/`aria-hidden` on the panel, scroll lock, focus trap, Escape to
 * close, and focus restored to the trigger on close.
 */

import {
  qs,
  qsa,
  on,
  debounce,
  lockScroll,
  trapFocus,
  prefersReducedMotion,
} from './util.js';

/* -------------------------------------------------------------------------
 * Announcement bar
 * ---------------------------------------------------------------------- */

const ANNOUNCEMENT_KEY = 'safari:announcement-dismissed';

function initAnnouncement() {
  const bar = qs('.announcement');
  if (!bar) {
    return;
  }

  // Hide if the visitor already dismissed this exact message.
  const message = bar.getAttribute('data-message') || '';
  try {
    if (window.localStorage.getItem(ANNOUNCEMENT_KEY) === message) {
      bar.hidden = true;
      return;
    }
  } catch {
    // Private mode / storage disabled — always show the bar.
  }

  const close = qs('.announcement__close', bar);
  if (!close) {
    return;
  }

  on(close, 'click', () => {
    bar.hidden = true;
    try {
      window.localStorage.setItem(ANNOUNCEMENT_KEY, message);
    } catch {
      // Ignore write failures.
    }
  });
}

/* -------------------------------------------------------------------------
 * Sticky header state
 * ---------------------------------------------------------------------- */

function initHeaderScroll() {
  const header = qs('.site-header');
  if (!header) {
    return;
  }

  // Compensate for the announcement bar when it is visible.
  const announcement = qs('.announcement:not([hidden])');
  const offset = announcement ? announcement.offsetHeight : 0;

  let lastState = null;

  const update = () => {
    const scrolled = window.scrollY > offset + 8;
    if (scrolled !== lastState) {
      header.classList.toggle('site-header--scrolled', scrolled);
      lastState = scrolled;
    }
  };

  on(window, 'scroll', update, { passive: true });
  update();
}

/* -------------------------------------------------------------------------
 * Mobile drawer (also used for the filter sheet)
 * ---------------------------------------------------------------------- */

const openDrawers = new Set();

/**
 * Wire one drawer.
 *
 * @param {HTMLElement} drawer Drawer element.
 * @param {string}      name   Debug label.
 */
function bindDrawer(drawer, name) {
  const panel = qs('.st-drawer__panel', drawer);
  if (!panel) {
    return;
  }

  let releaseFocus = null;

  const close = () => {
    drawer.classList.remove('st-drawer--open');
    drawer.setAttribute('aria-hidden', 'true');
    panel.setAttribute('aria-hidden', 'true');
    drawer.dispatchEvent(new CustomEvent('safari:drawer-closed'));
    openDrawers.delete(drawer);
    if (openDrawers.size === 0) {
      lockScroll(false);
    }
    if (releaseFocus) {
      releaseFocus();
      releaseFocus = null;
    }
  };

  const open = () => {
    drawer.classList.add('st-drawer--open');
    drawer.setAttribute('aria-hidden', 'false');
    panel.setAttribute('aria-hidden', 'false');
    openDrawers.add(drawer);
    lockScroll(true);
    releaseFocus = trapFocus(panel);
    drawer.dispatchEvent(new CustomEvent('safari:drawer-opened', { detail: { name } }));

    // Move focus into the panel once it has been painted.
    window.setTimeout(() => {
      const first = qs('button, a[href], input, select, textarea', panel);
      if (first) {
        first.focus();
      }
    }, prefersReducedMotion() ? 0 : 220);
  };

  // Triggers opt in with data-drawer-open="<drawer id>".
  qsa(`[data-drawer-open="${drawer.dataset.drawer}"]`).forEach((trigger) => {
    on(trigger, 'click', (event) => {
      event.preventDefault();
      const expanded = trigger.getAttribute('aria-expanded') === 'true';
      trigger.setAttribute('aria-expanded', String(!expanded));
      if (expanded) {
        close();
      } else {
        open();
      }
    });
  });

  // Dismissal paths.
  qsAll('.st-drawer__close', drawer).forEach((btn) => on(btn, 'click', close));
  const scrim = qs('.st-drawer__scrim', drawer);
  if (scrim) {
    on(scrim, 'click', close);
  }

  // Any drawer that is a bottom sheet can be dragged shut.
  if (drawer.classList.contains('st-drawer--bottom')) {
    bindSheetSwipe(drawer, close);
  }

  // Let templates trigger close() imperatively.
  drawer.addEventListener('safari:drawer-close', close);

  // Keep aria-expanded in sync when the drawer closes via Escape.
  on(document, 'keydown', (event) => {
    if (event.key !== 'Escape' || !drawer.classList.contains('st-drawer--open')) {
      return;
    }
    const trigger = qs(`[data-drawer-open="${drawer.dataset.drawer}"]`);
    if (trigger) {
      trigger.setAttribute('aria-expanded', 'false');
    }
    close();
  });
}

/**
 * querySelectorAll that tolerates a null parent.
 *
 * @param {string}     selector CSS selector.
 * @param {ParentNode} [scope]   Search root.
 * @returns {Element[]} Matches.
 */
function qsAll(selector, scope) {
  return Array.from((scope || document).querySelectorAll(selector));
}

/**
 * Drag-to-dismiss for bottom sheets.
 *
 * @param {HTMLElement} drawer Drawer element.
 * @param {Function}    close  Close callback.
 */
function bindSheetSwipe(drawer, close) {
  const handle = qs('.st-drawer__head', drawer);
  if (!handle || prefersReducedMotion()) {
    return;
  }

  let startY = 0;
  let delta = 0;
  let dragging = false;

  on(handle, 'pointerdown', (event) => {
    dragging = true;
    startY = event.clientY;
    delta = 0;
    handle.setPointerCapture(event.pointerId);
  });

  on(handle, 'pointermove', (event) => {
    if (!dragging) {
      return;
    }
    delta = Math.max(0, event.clientY - startY);
    const panel = qs('.st-drawer__panel', drawer);
    if (panel) {
      panel.style.transform = `translate3d(0, ${delta}px, 0)`;
      panel.style.transition = 'none';
    }
  });

  const end = () => {
    if (!dragging) {
      return;
    }
    dragging = false;

    const panel = qs('.st-drawer__panel', drawer);
    if (panel) {
      panel.style.transition = '';
      panel.style.transform = '';
    }

    if (delta > 90) {
      close();
    }
  };

  on(handle, 'pointerup', end);
  on(handle, 'pointercancel', end);
}

/* -------------------------------------------------------------------------
 * Search overlay
 * ---------------------------------------------------------------------- */

function initSearch() {
  const overlay = qs('.search-overlay');
  if (!overlay) {
    return;
  }

  const input = qs('.search-overlay__input', overlay);
  const results = qs('.search-overlay__results', overlay);
  const form = qs('.search-overlay__form', overlay);
  const closeBtn = qs('.search-overlay__close', overlay);
  const triggers = qsa('[data-search-open]');

  if (!input || !results) {
    return;
  }

  const restRoot =
    (window.safariData && window.safariData.restUrl) || '/wp-json/safari/v1';
  const perPage =
    (window.safariData && window.safariData.searchPerPage) || 8;

  let releaseFocus = null;
  let controller = null;
  let lastQuery = '';

  const open = (trigger) => {
    overlay.classList.add('search-overlay--open');
    overlay.setAttribute('aria-hidden', 'false');
    lockScroll(true);
    releaseFocus = trapFocus(overlay);

    if (trigger) {
      trigger.setAttribute('aria-expanded', 'true');
    }

    window.setTimeout(() => input.focus(), prefersReducedMotion() ? 0 : 200);
  };

  const close = () => {
    overlay.classList.remove('search-overlay--open');
    overlay.setAttribute('aria-hidden', 'true');
    lockScroll(false);
    if (controller) {
      controller.abort();
      controller = null;
    }
    if (releaseFocus) {
      releaseFocus();
      releaseFocus = null;
    }
    triggers.forEach((t) => t.setAttribute('aria-expanded', 'false'));
  };

  triggers.forEach((trigger) => {
    on(trigger, 'click', (event) => {
      event.preventDefault();
      open(trigger);
    });
  });

  if (closeBtn) {
    on(closeBtn, 'click', close);
  }

  on(document, 'keydown', (event) => {
    if (event.key === 'Escape' && overlay.classList.contains('search-overlay--open')) {
      close();
    }

    // ⌘K / Ctrl-K opens search from anywhere.
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      if (overlay.classList.contains('search-overlay--open')) {
        close();
      } else {
        open(null);
      }
    }
  });

  /**
   * Escape text for safe insertion into the results container.
   *
   * @param {string} value Raw text.
   * @returns {string} Escaped text.
   */
  const esc = (value) =>
    String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

  /**
   * Render grouped results.
   *
   * @param {Array<{type:string,label:string,items:Array}>} groups Result groups.
   */
  const render = (groups) => {
    if (!groups.length) {
      results.innerHTML = `
        <div class="empty-state">
          <p class="empty-state__title">No matches for “${esc(input.value)}”</p>
          <p class="empty-state__text">
            Try a destination like <em>Mara</em>, a safari type such as
            <em>gorilla trekking</em>, or browse all destinations.
          </p>
        </div>`;
      return;
    }

    results.innerHTML = groups
      .map(
        (group) => `
        <div class="search-group">
          <h3 class="search-group__title">${esc(group.label)}</h3>
          ${group.items
            .map(
              (item) => `
            <a class="search-hit" href="${esc(item.url)}">
              <span class="search-hit__title">${esc(item.title)}</span>
              <span class="search-hit__type">${esc(item.type || '')}</span>
            </a>`
            )
            .join('')}
        </div>`
      )
      .join('');
  };

  const search = debounce(async (query) => {
    if (query === lastQuery) {
      return;
    }
    lastQuery = query;

    if (query.length < 2) {
      results.innerHTML = '';
      return;
    }

    results.innerHTML = '<p class="skeleton" style="height:3rem"></p>'.repeat(3);

    if (controller) {
      controller.abort();
    }
    controller = typeof AbortController === 'function' ? new AbortController() : null;

    try {
      const url = `${restRoot}/search?q=${encodeURIComponent(query)}&per_page=${perPage}`;
      const response = await window.fetch(url, {
        signal: controller ? controller.signal : undefined,
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`Search failed: ${response.status}`);
      }

      const payload = await response.json();
      render(Array.isArray(payload) ? payload : payload.groups || []);
    } catch (error) {
      if (error && error.name === 'AbortError') {
        return;
      }
      // The overlay is a progressive enhancement — the form still works.
      results.innerHTML = `
        <p class="text-muted">
          Live search is unavailable right now. Press Enter to see all results.
        </p>`;
    }
  }, 300);

  on(input, 'input', () => search(input.value.trim()));

  if (form) {
    // No-JS and no-endpoint fallback: a real GET to ?s=
    on(form, 'submit', (event) => {
      if (input.value.trim() === '') {
        event.preventDefault();
        return;
      }
      const action = form.getAttribute('action') || '/';
      window.location.href = `${action}?s=${encodeURIComponent(input.value.trim())}`;
    });
  }
}

/* -------------------------------------------------------------------------
 * Back to top
 * ---------------------------------------------------------------------- */

function initToTop() {
  const button = qs('.to-top');
  if (!button) {
    return;
  }

  const update = () => {
    button.classList.toggle('to-top--visible', window.scrollY > window.innerHeight * 0.8);
  };

  on(window, 'scroll', update, { passive: true });

  on(button, 'click', () => {
    window.scrollTo({ top: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
  });

  update();
}

/* -------------------------------------------------------------------------
 * Mobile CTA bar
 * ---------------------------------------------------------------------- */

function initMobileCta() {
  const bar = qs('.st-mobile-cta');
  if (!bar) {
    return;
  }

  const hideAfter = parseFloat(bar.getAttribute('data-hide-after')) || 600;
  const main = qs('.site-main');

  // Never float a CTA over the lead form itself.
  if (qs('.safari-lead-form', bar.closest('body'))) {
    bar.hidden = true;
    if (main) {
      main.classList.remove('has-mobile-cta');
    }
    return;
  }

  const update = () => {
    // Hide once the visitor reaches the real CTA in the page flow.
    const shouldHide = window.scrollY > hideAfter;
    bar.classList.toggle('st-mobile-cta--hidden', shouldHide);
  };

  on(window, 'scroll', update, { passive: true });
  update();
}

/* -------------------------------------------------------------------------
 * Public entry
 * ---------------------------------------------------------------------- */

/**
 * Initialise the global shell.
 */
export function initShell() {
  initAnnouncement();
  initHeaderScroll();
  initSearch();
  initToTop();
  initMobileCta();

  qsa('.st-drawer').forEach((drawer) => bindDrawer(drawer, drawer.dataset.drawer || 'drawer'));
}
