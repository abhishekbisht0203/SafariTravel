/**
 * Safari Travel - Homepage Specific Bundle
 *
 * Loaded conditionally on the front page only.
 * Provides interactive features: sliders, counters, and destination filtering.
 */

import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

/**
 * Initialize Swiper carousels for tours, hero, and testimonials if present.
 */
function initHomeSliders() {
  const heroSlider = document.querySelector('.safari-hero-slider');
  if (heroSlider) {
    new Swiper(heroSlider, {
      modules: [Pagination, Autoplay],
      slidesPerView: 1,
      loop: true,
      autoplay: {
        delay: 5000,
        disableOnInteraction: false,
      },
      pagination: {
        el: '.safari-hero-slider__pagination',
        clickable: true,
      },
    });
  }

  const tourSlider = document.querySelector('.safari-tour-slider');
  if (tourSlider) {
    new Swiper(tourSlider, {
      modules: [Navigation, Pagination],
      slidesPerView: 1,
      spaceBetween: 24,
      navigation: {
        nextEl: '.safari-tour-slider__next',
        prevEl: '.safari-tour-slider__prev',
      },
      pagination: {
        el: '.safari-tour-slider__pagination',
        clickable: true,
      },
      breakpoints: {
        640: {
          slidesPerView: 2,
        },
        1024: {
          slidesPerView: 3,
        },
      },
    });
  }

  const testimonialSlider = document.querySelector('.safari-testimonial-slider');
  if (testimonialSlider) {
    new Swiper(testimonialSlider, {
      modules: [Pagination, Autoplay],
      slidesPerView: 1,
      loop: true,
      spaceBetween: 30,
      autoplay: {
        delay: 6000,
        pauseOnMouseEnter: true,
      },
      pagination: {
        el: '.safari-testimonial-slider__pagination',
        clickable: true,
      },
      breakpoints: {
        768: {
          slidesPerView: 2,
        },
      },
    });
  }
}

/**
 * Animate numeric statistics when scrolled into view.
 */
function initStatCounters() {
  const statElements = document.querySelectorAll('.safari-stat__number[data-target]');
  if (!statElements.length) {
    return;
  }

  const countUp = (el) => {
    const target = Number(el.getAttribute('data-target')) || 0;
    const duration = 1600;
    const start = 0;
    const startTime = window.performance ? window.performance.now() : Date.now();

    const update = (now) => {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const easeOut = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(start + (target - start) * easeOut);

      el.textContent = current.toLocaleString();

      if (progress < 1) {
        requestAnimationFrame(update);
      } else {
        el.textContent = target.toLocaleString();
      }
    };

    requestAnimationFrame(update);
  };

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          countUp(entry.target);
          obs.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.3 }
  );

  statElements.forEach((el) => observer.observe(el));
}

/**
 * Quick destination tab filtering on homepage.
 */
function initDestinationTabs() {
  const tabs = document.querySelectorAll('.safari-dest-tab');
  const cards = document.querySelectorAll('.safari-dest-card');

  if (!tabs.length || !cards.length) {
    return;
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const filter = tab.getAttribute('data-filter') || 'all';

      tabs.forEach((t) => t.classList.remove('safari-dest-tab--active'));
      tab.classList.add('safari-dest-tab--active');

      cards.forEach((card) => {
        const category = card.getAttribute('data-category');
        if (filter === 'all' || category === filter) {
          card.hidden = false;
          card.classList.add('safari-reveal--visible');
        } else {
          card.hidden = true;
        }
      });
    });
  });
}

/**
 * Initialize homepage features on DOM ready.
 */
function initHome() {
  initHomeSliders();
  initStatCounters();
  initDestinationTabs();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initHome);
} else {
  initHome();
}
