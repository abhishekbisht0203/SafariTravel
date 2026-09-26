/**
 * Safari Travel — shared utilities
 *
 * Tiny, dependency-free helpers used across every module. Deliberately
 * conservative: no polyfills, no transpiled sugar, no global side effects.
 */

/**
 * Whether the visitor asked for reduced motion. Re-read on every call so that
 * changing the OS setting mid-session takes effect.
 *
 * @returns {boolean} True when motion should be suppressed.
 */
export function prefersReducedMotion() {
  return (
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  );
}

/**
 * querySelector shorthand.
 *
 * @param {string}      selector CSS selector.
 * @param {ParentNode}  [scope]  Search root, defaults to document.
 * @returns {Element|null} First match or null.
 */
export function qs(selector, scope = document) {
  return scope.querySelector(selector);
}

/**
 * querySelectorAll shorthand that always returns a real Array.
 *
 * @param {string}     selector CSS selector.
 * @param {ParentNode} [scope]   Search root, defaults to document.
 * @returns {Element[]} Matching elements.
 */
export function qsa(selector, scope = document) {
  return Array.from(scope.querySelectorAll(selector));
}

/**
 * addEventListener that returns its own disposer.
 *
 * @param {EventTarget} target   Target element.
 * @param {string}      type     Event name.
 * @param {Function}    handler  Callback.
 * @param {object|boolean} [options] Listener options.
 * @returns {Function} Disposer.
 */
export function on(target, type, handler, options) {
  if (!target) {
    return () => {};
  }
  target.addEventListener(type, handler, options);
  return () => target.removeEventListener(type, handler, options);
}

/**
 * Trailing-edge debounce.
 *
 * @param {Function} fn    Function to call.
 * @param {number}   wait  Milliseconds of quiet time.
 * @returns {Function} Debounced function.
 */
export function debounce(fn, wait = 250) {
  let timer = 0;
  return function debounced(...args) {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => fn.apply(this, args), wait);
  };
}

/**
 * requestAnimationFrame throttle. Guarantees at most one call per frame and
 * always fires a trailing call, which is what scroll handlers need.
 *
 * @param {Function} fn Function to call with a timestamp.
 * @returns {Function} Throttled function.
 */
export function rafThrottle(fn) {
  let queued = false;
  let lastArgs = null;

  return function throttled(...args) {
    lastArgs = args;
    if (queued) {
      return;
    }
    queued = true;
    window.requestAnimationFrame(() => {
      queued = false;
      fn(...lastArgs);
    });
  };
}

/**
 * Clamp a number between two bounds.
 *
 * @param {number} value Value to clamp.
 * @param {number} min   Lower bound.
 * @param {number} max   Upper bound.
 * @returns {number} Clamped value.
 */
export function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max);
}

/**
 * requestAnimationFrame-based linear interpolation.
 *
 * @param {number} duration Milliseconds.
 * @param {Function} step   Called with eased progress 0→1.
 * @param {(t:number)=>number} ease Easing function.
 * @returns {Function} Canceller.
 */
export function tween(duration, step, ease = easeOutCubic) {
  let raf = 0;
  let start = null;

  const frame = (now) => {
    if (start === null) {
      start = now;
    }
    const progress = clamp((now - start) / duration, 0, 1);
    step(ease(progress), progress);
    if (progress < 1) {
      raf = window.requestAnimationFrame(frame);
    }
  };

  raf = window.requestAnimationFrame(frame);
  return () => window.cancelAnimationFrame(raf);
}

/**
 * Cubic ease-out — matches the `--ease-out` CSS token.
 *
 * @param {number} t Progress 0→1.
 * @returns {number} Eased progress.
 */
export function easeOutCubic(t) {
  return 1 - Math.pow(1 - t, 3);
}

/**
 * Cubic ease-out-expo — punchier, used for count-ups.
 *
 * @param {number} t Progress 0→1.
 * @returns {number} Eased progress.
 */
export function easeOutExpo(t) {
  return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
}

/**
 * Build a CSS custom-property setter that falls back gracefully.
 *
 * @param {Element} element  Target element.
 * @param {string}  property Custom property name, with or without leading `--`.
 * @returns {(value:string|number)=>void} Setter.
 */
export function cssVar(element, property) {
  const name = property.startsWith('--') ? property : `--${property}`;
  return (value) => {
    element.style.setProperty(name, String(value));
  };
}

/**
 * Lock or unlock page scrolling without layout shift.
 *
 * @param {boolean} locked Whether scrolling should be locked.
 */
let scrollLocks = 0;
let savedScrollY = 0;

export function lockScroll(locked) {
  if (locked) {
    scrollLocks += 1;
    if (scrollLocks === 1) {
      savedScrollY = window.scrollY;
      document.body.style.position = 'fixed';
      document.body.style.top = `-${savedScrollY}px`;
      document.body.style.width = '100%';
    }
    return;
  }

  scrollLocks = Math.max(0, scrollLocks - 1);
  if (scrollLocks === 0) {
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.width = '';
    window.scrollTo(0, savedScrollY);
  }
}

/**
 * Trap Tab focus inside a container while it is open.
 *
 * @param {HTMLElement} container Element that holds the focusable children.
 * @returns {Function} Disposer that restores the previous focus.
 */
export function trapFocus(container) {
  const SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ].join(',');

  const previouslyFocused = document.activeElement;

  const onKeydown = (event) => {
    if (event.key !== 'Tab') {
      return;
    }

    const focusables = qsa(SELECTOR, container).filter(
      (el) => el.offsetParent !== null || el === document.activeElement
    );

    if (!focusables.length) {
      event.preventDefault();
      return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };

  document.addEventListener('keydown', onKeydown, true);

  return () => {
    document.removeEventListener('keydown', onKeydown, true);
    if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
      previouslyFocused.focus();
    }
  };
}

/**
 * Run a callback once the element first becomes visible.
 *
 * @param {Element}    element   Target.
 * @param {Function}   callback  Called with the element.
 * @param {object}     [options] IntersectionObserver options.
 * @param {Function}   [fallback] Called immediately if IO is unsupported.
 */
export function onceVisible(element, callback, options = {}, fallback) {
  if (typeof IntersectionObserver !== 'function') {
    if (fallback) {
      fallback(element);
    }
    return;
  }

  const observer = new IntersectionObserver((entries, obs) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        callback(entry.target);
        obs.unobserve(entry.target);
      }
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.01, ...options });

  observer.observe(element);
}

/**
 * Mark the document as JS-capable. The reveal system relies on `.js` to know
 * it is safe to hide content, so this must run before first paint.
 */
export function flagJs() {
  document.documentElement.classList.remove('no-js');
  document.documentElement.classList.add('js');
}

/**
 * Dispatch a custom event on `document`.
 *
 * @param {string} name  Event name.
 * @param {*}      [detail] Event detail payload.
 */
export function emit(name, detail) {
  document.dispatchEvent(new CustomEvent(name, { detail }));
}

/** Ensure every module runs at most once, even across re-inits. */
const initialised = new WeakSet();

/**
 * Run an init function once per element.
 *
 * @param {Element} element  Element to mark.
 * @param {Function} fn      Init function.
 * @returns {boolean} True if it ran now.
 */
export function once(element, fn) {
  if (initialised.has(element)) {
    return false;
  }
  initialised.add(element);
  fn();
  return true;
}
