/**
 * Safari Travel - Main Frontend Entry
 *
 * Handles global theme interactivity: navigation, scroll reveals,
 * accessibility improvements, and multi-step lead forms.
 */

import './main.css';

/**
 * Initialize mobile navigation toggle with accessibility & keyboard support.
 */
function initNavigation() {
  const toggleBtn = document.querySelector('.site-nav-toggle');
  const navMenu = document.querySelector('.site-navigation');

  if (!toggleBtn || !navMenu) {
    return;
  }

  toggleBtn.addEventListener('click', () => {
    const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
    toggleBtn.setAttribute('aria-expanded', String(!isExpanded));
    navMenu.classList.toggle('site-navigation--open', !isExpanded);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && navMenu.classList.contains('site-navigation--open')) {
      toggleBtn.setAttribute('aria-expanded', 'false');
      navMenu.classList.remove('site-navigation--open');
      toggleBtn.focus();
    }
  });
}

/**
 * Reveal on scroll using IntersectionObserver.
 * Disabled if user prefers reduced motion.
 */
function initScrollReveals() {
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReduced) {
    document.querySelectorAll('.safari-reveal').forEach((el) => {
      el.classList.add('safari-reveal--visible');
    });
    return;
  }

  const revealElements = document.querySelectorAll('.safari-reveal');
  if (!revealElements.length) {
    return;
  }

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('safari-reveal--visible');
          obs.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 }
  );

  revealElements.forEach((el) => observer.observe(el));
}

/**
 * Handle header elevation on scroll.
 */
function initHeaderScroll() {
  const header = document.querySelector('.site-header');
  if (!header) {
    return;
  }

  const onScroll = () => {
    if (window.scrollY > 20) {
      header.classList.add('site-header--scrolled');
    } else {
      header.classList.remove('site-header--scrolled');
    }
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}

/**
 * Multi-step lead form navigation handler.
 */
function initMultiStepForms() {
  const forms = document.querySelectorAll('.safari-lead-form');

  forms.forEach((form) => {
    const nextBtns = form.querySelectorAll('.safari-lead-form__next');
    const prevBtns = form.querySelectorAll('.safari-lead-form__prev');
    const progressBar = form.querySelector('.safari-lead-form__progress-bar');
    const steps = form.querySelectorAll('.safari-lead-form__step');

    if (!steps.length) {
      return;
    }

    const setStep = (stepNumber) => {
      steps.forEach((step) => {
        const currentStepNum = Number(step.getAttribute('data-step'));
        if (currentStepNum === stepNumber) {
          step.hidden = false;
          step.classList.add('safari-lead-form__step--active');
          const firstInput = step.querySelector('input, select, textarea');
          if (firstInput) {
            firstInput.focus();
          }
        } else {
          step.hidden = true;
          step.classList.remove('safari-lead-form__step--active');
        }
      });

      if (progressBar) {
        const percentage = Math.round((stepNumber / steps.length) * 100);
        progressBar.style.setProperty('--progress', `${percentage}%`);
        progressBar.setAttribute('aria-valuenow', String(stepNumber));
      }
    };

    nextBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const targetStep = Number(btn.getAttribute('data-next-step'));
        if (targetStep) {
          setStep(targetStep);
        }
      });
    });

    prevBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const targetStep = Number(btn.getAttribute('data-prev-step'));
        if (targetStep) {
          setStep(targetStep);
        }
      });
    });
  });
}

/**
 * Initialize core frontend foundation on DOMContentLoaded.
 */
function init() {
  initNavigation();
  initScrollReveals();
  initHeaderScroll();
  initMultiStepForms();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
