/**
 * Safari Travel — lead form enhancement
 *
 * Markup is rendered by plugins/safari-leads (see class-safari-lead-form.php).
 * This module adds the behaviour:
 *   - multi-step navigation with per-step validation
 *   - inline, accessible validation messages
 *   - AJAX submit to POST /wp-json/safari/v1/leads with a no-JS fallback
 *   - UTM + referrer capture from the first-party cookie
 *   - submission guard (double-click, in-flight)
 *
 * The form has `novalidate` on purpose: browser bubbles are ugly and
 * inconsistent. We own the messaging.
 */

import { qsa, qs, on, trapFocus } from './util.js';

/** UTM keys stashed on first touch, per plan.md §5.3. */
const UTM_KEYS = [
  'utm_source',
  'utm_medium',
  'utm_campaign',
  'utm_term',
  'utm_content',
  'gclid',
  'fbclid',
];

const COOKIE_TTL_DAYS = 30;

/* -------------------------------------------------------------------------
 * Attribution cookie
 * ---------------------------------------------------------------------- */

/**
 * Read a cookie value.
 *
 * @param {string} name Cookie name.
 * @returns {string} Value or empty string.
 */
function readCookie(name) {
  const match = document.cookie.match(
    new RegExp(`(?:^|; )${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}=([^;]*)`)
  );
  return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Write a cookie with a 30-day expiry.
 *
 * @param {string} name  Cookie name.
 * @param {string} value Cookie value.
 */
function writeCookie(name, value) {
  const expires = new Date(Date.now() + COOKIE_TTL_DAYS * 864e5).toUTCString();
  document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
}

/**
 * Capture UTM parameters on the first page view of a visit and persist them
 * so the lead form can attach them wherever the enquiry is finally made.
 */
function captureAttribution() {
  const params = new URLSearchParams(window.location.search);
  const hasNew = UTM_KEYS.some((key) => params.get(key));

  if (hasNew) {
    UTM_KEYS.forEach((key) => {
      const value = params.get(key);
      if (value) {
        writeCookie(`safari_${key}`, value);
      }
    });
    if (!readCookie('safari_referrer')) {
      writeCookie('safari_referrer', document.referrer || 'direct');
    }
    writeCookie('safari_first_touch', new Date().toISOString());
  }
}

/* -------------------------------------------------------------------------
 * Validation
 * ---------------------------------------------------------------------- */

/**
 * Validate a single control.
 *
 * @param {HTMLElement} control Form control.
 * @returns {string} Error message, or an empty string when valid.
 */
function validateControl(control) {
  if (control.disabled || control.type === 'hidden') {
    return '';
  }

  const value = String(control.value || '').trim();
  const label = control.getAttribute('data-label') || control.name || 'This field';

  if (control.hasAttribute('required') && value === '') {
    // Checkboxes must be ticked; everything else must be filled in.
    if (control.type === 'checkbox' || control.type === 'radio') {
      return `${label} is required.`;
    }
    return `${label} is required.`;
  }

  if (value === '') {
    return '';
  }

  if (control.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
    return 'Enter a valid email address, e.g. you@example.com.';
  }

  if (control.type === 'tel') {
    // Strip formatting, then require 7–15 digits (E.164 without the +).
    const digits = value.replace(/[^\d]/g, '');
    if (digits.length < 7 || digits.length > 15) {
      return 'Enter a valid phone number including the country code.';
    }
  }

  if (control.type === 'url') {
    try {
      const url = new URL(value);
      if (!/^https?:$/.test(url.protocol)) {
        return 'Enter a full URL starting with http:// or https://.';
      }
    } catch {
      return 'Enter a full URL starting with http:// or https://.';
    }
  }

  const min = control.getAttribute('minlength');
  if (min && value.length < parseInt(min, 10)) {
    return `${label} must be at least ${min} characters.`;
  }

  const max = control.getAttribute('maxlength');
  if (max && value.length > parseInt(max, 10)) {
    return `${label} must be ${max} characters or fewer.`;
  }

  if (control.type === 'number') {
    const num = parseFloat(value);
    const minNum = control.getAttribute('min');
    const maxNum = control.getAttribute('max');
    if (Number.isNaN(num)) {
      return `${label} must be a number.`;
    }
    if (minNum !== null && num < parseFloat(minNum)) {
      return `${label} must be at least ${minNum}.`;
    }
    if (maxNum !== null && num > parseFloat(maxNum)) {
      return `${label} must be ${maxNum} or less.`;
    }
  }

  return '';
}

/* -------------------------------------------------------------------------
 * Field-level error rendering
 * ---------------------------------------------------------------------- */

/**
 * Show or clear the error message for a control.
 *
 * @param {HTMLElement} control Form control.
 * @param {string}      message Error text, or '' to clear.
 */
function setFieldError(control, message) {
  const field = control.closest('.safari-lead-form__field, .field') || control.parentElement;
  const error = field ? qs('.safari-lead-form__error, .field__error', field) : null;

  if (message) {
    control.setAttribute('aria-invalid', 'true');
    if (field) {
      field.classList.add('field--invalid');
    }
    if (error) {
      error.textContent = message;
      error.classList.add('is-visible');
    }
  } else {
    control.removeAttribute('aria-invalid');
    if (field) {
      field.classList.remove('field--invalid');
    }
    if (error) {
      error.textContent = '';
      error.classList.remove('is-visible');
    }
  }
}

/**
 * Validate every control in a container, focusing the first failure.
 *
 * @param {HTMLElement} container Step or form element.
 * @returns {boolean} True when the container is valid.
 */
function validateContainer(container) {
  const controls = qsa(
    'input:not([type="hidden"]):not([disabled]), select, textarea',
    container
  );

  let firstInvalid = null;
  let errorCount = 0;

  controls.forEach((control) => {
    // A hidden field inside a closed step is not the visitor's problem.
    if (control.closest('[hidden]') && !container.matches('[hidden]')) {
      return;
    }

    const message = validateControl(control);
    setFieldError(control, message);

    if (message) {
      errorCount += 1;
      if (!firstInvalid) {
        firstInvalid = control;
      }
    }
  });

  if (firstInvalid) {
    firstInvalid.focus({ preventScroll: false });
  }

  return errorCount === 0;
}

/* -------------------------------------------------------------------------
 * Messages region
 * ---------------------------------------------------------------------- */

/**
 * Render a global banner message.
 *
 * @param {HTMLElement} form     Form element.
 * @param {string}      text     Message text.
 * @param {string}      type     'error' or 'success'.
 */
function setMessage(form, text, type) {
  const region = qs('.safari-lead-form__messages', form);
  if (!region) {
    return;
  }

  if (!text) {
    region.innerHTML = '';
    return;
  }

  const icon =
    type === 'success'
      ? '<svg viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10.5l4 4 8-9"/></svg>'
      : '<svg viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 5v6M10 14h.01M10 2.5l8 14H2z"/></svg>';

  region.innerHTML = `<p class="safari-lead-form__message safari-lead-form__message--${type}">${icon}<span>${text}</span></p>`;
}

/* -------------------------------------------------------------------------
 * Multi-step navigation
 * ---------------------------------------------------------------------- */

/**
 * Build the step controller for a multi-step form.
 *
 * @param {HTMLFormElement} form Form element.
 * @returns {object|null} Controller, or null for single-step forms.
 */
function bindSteps(form) {
  const steps = qsa('.safari-lead-form__step', form);
  if (steps.length < 2) {
    return null;
  }

  const progressBar = qs('.safari-lead-form__progress-bar', form);
  const nextButtons = qsa('.safari-lead-form__next', form);
  const prevButtons = qsa('.safari-lead-form__prev', form);
  let current = 1;

  /**
   * Show a step.
   *
   * @param {number} number Step number, 1-based.
   */
  const goTo = (number) => {
    current = Math.min(Math.max(number, 1), steps.length);

    steps.forEach((step) => {
      const isCurrent = parseInt(step.getAttribute('data-step'), 10) === current;
      step.hidden = !isCurrent;
      step.classList.toggle('safari-lead-form__step--active', isCurrent);
    });

    if (progressBar) {
      const percent = Math.round((current / steps.length) * 100);
      progressBar.style.setProperty('--progress', `${percent}%`);
      progressBar.setAttribute('aria-valuenow', String(current));
    }

    // Move focus to the first control so keyboard and screen-reader users
    // land inside the new step.
    const focusTarget = qs('input, select, textarea', steps[current - 1]);
    if (focusTarget) {
      focusTarget.focus({ preventScroll: true });
    }

    // If this is the final step, swap the next button for the submit button.
    nextButtons.forEach((btn) => {
      const target = parseInt(btn.getAttribute('data-next-step'), 10);
      btn.hidden = target > current;
    });
  };

  nextButtons.forEach((btn) => {
    on(btn, 'click', (event) => {
      event.preventDefault();

      const stepEl = steps[current - 1];
      if (stepEl && !validateContainer(stepEl)) {
        setMessage(form, 'Please check the highlighted fields before continuing.', 'error');
        return;
      }

      setMessage(form, '', 'error');
      const target = parseInt(btn.getAttribute('data-next-step'), 10);
      if (target) {
        goTo(target);
      }
    });
  });

  prevButtons.forEach((btn) => {
    on(btn, 'click', (event) => {
      event.preventDefault();
      const target = parseInt(btn.getAttribute('data-prev-step'), 10);
      if (target) {
        goTo(target);
      }
    });
  });

  // Keep the "from" date at or after the "to" date.
  const dateFrom = qs('[name="date_from"]', form);
  const dateTo = qs('[name="date_to"]', form);
  if (dateFrom && dateTo) {
    on(dateFrom, 'change', () => {
      dateTo.min = dateFrom.value || '';
      if (dateTo.value && dateFrom.value && dateTo.value < dateFrom.value) {
        dateTo.value = dateFrom.value;
      }
    });
  }

  goTo(1);
  return { goTo, get current() { return current; } };
}

/* -------------------------------------------------------------------------
 * Submit
 * ---------------------------------------------------------------------- */

/**
 * Collect the payload, including hidden attribution fields.
 *
 * @param {HTMLFormElement} form Form element.
 * @returns {FormData} Payload.
 */
function buildPayload(form) {
  const payload = new FormData(form);

  UTM_KEYS.forEach((key) => {
    const value = readCookie(`safari_${key}`);
    if (value) {
      payload.set(key, value);
    }
  });

  const referrer = readCookie('safari_referrer');
  if (referrer) {
    payload.set('referrer', referrer);
  }

  return payload;
}

/**
 * Wire the submit handler.
 *
 * @param {HTMLFormElement} form Form element.
 * @param {object|null}     steps Step controller.
 */
function bindSubmit(form, steps) {
  const button = qs('[type="submit"]', form);
  const label = button ? qs('.safari-btn__label', button) : null;
  const restUrl = form.getAttribute('data-rest-url');
  const nonce = form.getAttribute('data-nonce');
  let inFlight = false;

  const setLoading = (loading) => {
    if (!button) {
      return;
    }
    button.classList.toggle('safari-btn--loading', loading);
    button.disabled = loading;
    if (label) {
      label.textContent = loading ? 'Sending…' : label.getAttribute('data-idle') || 'Send inquiry';
    }
  };

  // Remember the idle label so we can restore it.
  if (label && !label.getAttribute('data-idle')) {
    label.setAttribute('data-idle', label.textContent);
  }

  on(form, 'submit', async (event) => {
    event.preventDefault();

    if (inFlight) {
      return;
    }

    // Validate the visible step first, then the whole form (hidden steps may
    // hold required fields the server will reject).
    if (steps) {
      const visibleStep = qs('.safari-lead-form__step:not([hidden])', form);
      if (visibleStep && !validateContainer(visibleStep)) {
        setMessage(form, 'Please check the highlighted fields before continuing.', 'error');
        return;
      }
    }

    if (!validateContainer(form)) {
      setMessage(form, 'Please check the highlighted fields.', 'error');
      return;
    }

    setMessage(form, '', 'error');
    inFlight = true;
    setLoading(true);

    // No REST endpoint (or a very old browser): fall back to a normal submit
    // so the inquiry is never lost.
    if (!restUrl || typeof window.fetch !== 'function' || !nonce) {
      form.removeAttribute('novalidate');
      form.submit();
      return;
    }

    try {
      const response = await window.fetch(restUrl, {
        method: 'POST',
        body: buildPayload(form),
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        // Field-level errors from the server take precedence.
        if (payload.data && typeof payload.data.fields === 'object') {
          Object.entries(payload.data.fields).forEach(([name, message]) => {
            const control = form.querySelector(`[name="${CSS.escape(name)}"]`);
            if (control) {
              setFieldError(control, String(message));
            }
          });
        }
        setMessage(form, payload.message || 'Something went wrong. Please try again.', 'error');
        return;
      }

      form.classList.add('safari-lead-form--done');
      setMessage(form, payload.message || 'Thank you!', 'success');

      // Fire analytics conversions before navigating away.
      document.dispatchEvent(
        new CustomEvent('safari:lead-submitted', { detail: payload })
      );
      if (typeof window.dataLayer !== 'undefined') {
        window.dataLayer.push({ event: 'safari_lead_submit', lead_id: payload.lead_id });
      }
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'generate_lead', { value: 1, currency: 'USD' });
      }

      if (payload.redirect_url) {
        window.setTimeout(() => {
          window.location.href = payload.redirect_url;
        }, 900);
      }
    } catch {
      // Network failure — submit natively so the lead is still captured.
      form.removeAttribute('novalidate');
      form.submit();
    } finally {
      inFlight = false;
      setLoading(false);
    }
  });
}

/* -------------------------------------------------------------------------
 * Public entry
 * ---------------------------------------------------------------------- */

/**
 * Enhance every lead form on the page.
 */
export function initLeadForms() {
  captureAttribution();

  qsa('form.safari-lead-form').forEach((form) => {
    // Validate on blur once a field has been touched — instant feedback
    // without shouting at someone who is still typing their email address.
    qsa('input, select, textarea', form).forEach((control) => {
      on(control, 'blur', () => {
        if (control.value.trim() !== '' || control.hasAttribute('required')) {
          setFieldError(control, validateControl(control));
        }
      });

      // Clear the error as soon as the visitor starts fixing it.
      on(control, 'input', () => {
        if (control.getAttribute('aria-invalid') === 'true') {
          setFieldError(control, validateControl(control));
        }
      });
    });

    // Keep the "travellers" total in sync on the plan form.
    const adults = qs('[name="adults"]', form);
    const children = qs('[name="children"]', form);
    const travellersOut = qs('[data-travellers-total]', form);
    if (adults && children && travellersOut) {
      const updateTotal = () => {
        const total = (parseInt(adults.value, 10) || 0) + (parseInt(children.value, 10) || 0);
        travellersOut.textContent = String(total);
      };
      on(adults, 'input', updateTotal);
      on(children, 'input', updateTotal);
      updateTotal();
    }

    const steps = bindSteps(form);
    bindSubmit(form, steps);
  });
}

/**
 * Focus-trap helper for modals that wrap a lead form.
 *
 * @param {HTMLElement} container Modal container.
 * @returns {Function} Disposer.
 */
export function trapLeadForm(container) {
  return trapFocus(container);
}
