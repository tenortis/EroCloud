/**
 * EroCloud – Public landing page interactions (vanilla JS, no dependencies besides Bootstrap bundle)
 * - Light/Dark theme toggle (shared storage key with MCP: "ec-mcp-theme")
 * - Sticky header shadow on scroll
 * - Active nav link highlighting (scroll spy)
 * - Progressive reveal animations for elements below the fold
 * - Auto-close mobile navigation after anchor click
 */
(function () {
  'use strict';

  var html = document.documentElement;
  var THEME_KEY = 'ec-mcp-theme';

  // 1. Theme toggle ---------------------------------------------------------
  var toggleBtn = document.getElementById('lp-theme-toggle');
  var themeIcon = document.getElementById('lp-theme-icon');

  function applyThemeIcon(theme) {
    if (!themeIcon) { return; }
    themeIcon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    if (toggleBtn) {
      toggleBtn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    }
  }

  applyThemeIcon(html.getAttribute('data-bs-theme') || 'light');

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      var next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-bs-theme', next);
      try { localStorage.setItem(THEME_KEY, next); } catch (e) { /* storage blocked */ }
      applyThemeIcon(next);
    });
  }

  // 2. Header shadow --------------------------------------------------------
  var header = document.getElementById('lp-header');
  function onScroll() {
    if (header) {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
    }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // 3. Mobile nav: close after clicking an anchor ---------------------------
  var navCollapse = document.getElementById('lp-nav');
  if (navCollapse) {
    navCollapse.addEventListener('click', function (ev) {
      var link = ev.target.closest('a[href^="#"]');
      if (link && navCollapse.classList.contains('show') && window.bootstrap && window.bootstrap.Collapse) {
        window.bootstrap.Collapse.getOrCreateInstance(navCollapse).hide();
      }
    });
  }

  if (!('IntersectionObserver' in window)) {
    return;
  }

  // 4. Reveal animations (only for elements that start below the fold) ----
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduceMotion) {
    var revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.remove('lp-reveal-pending');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    var viewportH = window.innerHeight || html.clientHeight;
    document.querySelectorAll('.lp-reveal').forEach(function (el) {
      if (el.getBoundingClientRect().top > viewportH) {
        el.classList.add('lp-reveal-pending');
        revealObserver.observe(el);
      }
    });
  }

  // 5. Scroll spy for header navigation -------------------------------------
  var navLinks = Array.prototype.slice.call(document.querySelectorAll('.lp-nav a.nav-link[href^="#"]'));
  var sections = navLinks
    .map(function (link) { return document.querySelector(link.getAttribute('href')); })
    .filter(Boolean);

  var spyObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) { return; }
      var id = '#' + entry.target.id;
      navLinks.forEach(function (link) {
        var active = link.getAttribute('href') === id;
        link.classList.toggle('active', active);
        if (active) { link.setAttribute('aria-current', 'location'); } else { link.removeAttribute('aria-current'); }
      });
    });
  }, { rootMargin: '-45% 0px -50% 0px' });

  sections.forEach(function (section) { spyObserver.observe(section); });
})();
