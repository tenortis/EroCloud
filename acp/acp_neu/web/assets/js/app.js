/**
 * EroCloud ACP (v2.0) Application Client
 * Reines Vanilla JS (ES6+, kein jQuery)
 * SPA-Architektur mit dynamischem Fragment-Loading (analog KKC-EroCMS & MCP v2.0).
 */

const App = {
  csrfToken: '',
  basePath: '',
  remainingSeconds: 1800,
  logoutTimerInterval: null,
  searchDebounceTimer: null,

  init() {
    this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    this.basePath = document.querySelector('meta[name="app-base-path"]')?.getAttribute('content') || '';
    const secMeta = parseInt(document.querySelector('meta[name="acp-session-seconds"]')?.getAttribute('content') || '1800', 10);
    this.remainingSeconds = secMeta > 0 ? secMeta : 1800;

    this.initTheme();
    this.initLogoutCountdown();
    this.initGlobalTopbarSearch();
    this.initRouter();
  },

  // ========================================================================
  // 1. Auto-Logout Countdown (30 Minuten analog acp/index.php)
  // ========================================================================
  initLogoutCountdown() {
    const timerEl = document.getElementById('acp-logout-timer');
    if (!timerEl) return;

    const renderTime = () => {
      const s = Math.max(0, this.remainingSeconds);
      const mins = Math.floor(s / 60);
      const secs = s % 60;
      timerEl.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
      if (s <= 120) {
        timerEl.classList.add('text-danger');
      } else {
        timerEl.classList.remove('text-danger');
      }
    };

    renderTime();

    if (this.logoutTimerInterval) {
      clearInterval(this.logoutTimerInterval);
    }

    this.logoutTimerInterval = setInterval(() => {
      this.remainingSeconds--;
      if (this.remainingSeconds <= 0) {
        clearInterval(this.logoutTimerInterval);
        window.location.href = `${this.basePath}/index.php?logout=auto`;
        return;
      }
      renderTime();
    }, 1000);
  },

  resetLogoutCountdown() {
    this.remainingSeconds = 1800;
    const timerEl = document.getElementById('acp-logout-timer');
    if (timerEl) {
      timerEl.textContent = '30:00';
      timerEl.classList.remove('text-danger');
    }
  },

  // ========================================================================
  // 2. Theme Management (Standard: Hellmodus / Light Mode)
  // ========================================================================
  initTheme() {
    const html = document.documentElement;
    const toggleBtn = document.getElementById('theme-toggle');
    const savedTheme = localStorage.getItem('ec-acp-theme') || 'light';

    html.setAttribute('data-bs-theme', savedTheme);
    this.updateThemeIcon(savedTheme);

    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        const current = html.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('ec-acp-theme', next);
        this.updateThemeIcon(next);
        this.toast(`Design auf ${next === 'dark' ? 'Dunkelmodus' : 'Hellmodus'} umgestellt.`, 'info');
      });
    }
  },

  updateThemeIcon(theme) {
    const icon = document.getElementById('theme-icon');
    const toggleBtn = document.getElementById('theme-toggle');
    if (!icon) return;

    if (theme === 'dark') {
      icon.className = 'bi bi-sun-fill text-warning';
      if (toggleBtn) toggleBtn.setAttribute('title', 'Zu Hellmodus wechseln');
    } else {
      icon.className = 'bi bi-moon-stars-fill text-secondary';
      if (toggleBtn) toggleBtn.setAttribute('title', 'Zu Dunkelmodus wechseln');
    }
  },

  // ========================================================================
  // 3. SPA Router & Fragment Loading (Kein Seiten-Reload!)
  // ========================================================================
  initRouter() {
    window.addEventListener('hashchange', () => this.handleRoute());

    if (!window.location.hash || window.location.hash === '#') {
      window.location.hash = '#/startseite';
    } else {
      this.handleRoute();
    }
  },

  async handleRoute() {
    let rawHash = window.location.hash.replace(/^#\/?/, '') || 'startseite';
    if (rawHash === 'home') {
      rawHash = 'startseite';
      window.history.replaceState(null, '', '#/startseite');
    }

    const [pathPart, queryPart] = rawHash.split('?');
    const params = new URLSearchParams(queryPart || '');
    const apiRoute = pathPart || 'startseite';

    // Aktiven Sidebar-Link hervorheben & zugehöriges Submenü ermitteln
    let activeCollapseId = null;
    const routeToParentLink = {
      'movie-checking': 'movies-checking',
      'photo-album-checking': 'photo-albums-checking',
      'movie-draft': 'movies-drafts',
      'movie-edit': 'movies-online',
      'photo-album-edit': 'photo-albums-online',
      'actor': 'actors',
      'site': 'sites',
      'merchant': 'merchants',
      'user': 'users',
      'employee': 'employees'
    };
    const effectiveNavRoute = routeToParentLink[pathPart] || pathPart;

    document.querySelectorAll('.sidebar-link[data-route]').forEach(link => {
      const linkRoute = (link.getAttribute('data-route') || '').replace(/^#\/?/, '');
      const isMatch = (linkRoute === rawHash || linkRoute === pathPart || linkRoute === effectiveNavRoute);

      if (isMatch) {
        link.classList.add('active');
        const parentCollapse = link.closest('.collapse');
        if (parentCollapse) {
          activeCollapseId = parentCollapse.id;
        }
      } else {
        link.classList.remove('active');
      }
    });

    // Submenüs steuern: Nur das zur aktuellen Unterseite gehörende Submenü öffnen, alle anderen schließen
    document.querySelectorAll('.sidebar-submenu.collapse').forEach(collapseEl => {
      const trigger = document.querySelector(`[href="#${collapseEl.id}"], [data-bs-target="#${collapseEl.id}"]`);
      const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });

      if (activeCollapseId && collapseEl.id === activeCollapseId) {
        bsCollapse.show();
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
      } else {
        bsCollapse.hide();
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
      }
    });

    const contentArea = document.getElementById('app-content');
    if (!contentArea) return;

    this.hideGlobalSearchDropdown();

    contentArea.innerHTML = `
      <div class="d-flex align-items-center justify-content-center p-5">
        <div class="spinner-border ec-text-primary me-2" role="status"></div>
        <span class="text-muted">Inhalte werden geladen...</span>
      </div>
    `;

    try {
      const querySuffix = params.toString() ? `&${params.toString()}` : '';
      const url = `${this.basePath}/api.php?route=${encodeURIComponent(apiRoute)}${querySuffix}`;
      const res = await fetch(url);

      if (!res.ok) {
        if (res.status === 401) {
          window.location.reload();
          return;
        }
        const errorData = await res.json().catch(() => ({ message: 'Ladefehler' }));
        contentArea.innerHTML = `
          <div class="alert alert-danger m-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Fehler beim Laden: ${this.escapeHtml(errorData.message || res.statusText)}
          </div>
        `;
        return;
      }

      const html = await res.text();
      contentArea.innerHTML = html;

      // Auto-Logout-Timer bei jeder erfolgreichen Navigation zurücksetzen
      this.resetLogoutCountdown();

      const widthHook = contentArea.querySelector('[data-page-width]');
      const pageWidth = widthHook ? widthHook.getAttribute('data-page-width') : 'boxed';
      contentArea.classList.toggle('is-fullwidth', pageWidth === 'full');
      contentArea.classList.toggle('is-narrow', pageWidth === 'narrow');

      window.scrollTo({ top: 0, behavior: 'smooth' });
      this.initDynamicEvents();
    } catch (err) {
      contentArea.innerHTML = `
        <div class="alert alert-danger m-4">
          <i class="bi bi-exclamation-octagon-fill me-2"></i>
          Verbindungsfehler zur API: ${this.escapeHtml(err.message)}
        </div>
      `;
    }
  },

  initDynamicEvents() {
    this.initHistoryTableFilter();
    this.checkPoppersCategoryConstraint();
  },

  async refreshSidebarCounts() {
    try {
      const res = await fetch(`${this.basePath}/api.php?route=home/sidebar-counts`);
      const data = await res.json();
      if (res.ok && data.status === 'ok' && data.counts) {
        const c = data.counts;
        const setBadge = (id, val) => {
          const el = document.getElementById(id);
          if (el) el.textContent = String(val ?? 0);
        };
        setBadge('sidebar-checking-count', c.content_checking);
        setBadge('sidebar-movies-checking-count', c.movies_checking);
        setBadge('sidebar-albums-checking-count', c.photo_albums_checking);
        setBadge('sidebar-coactors-checking-count', c.co_actors_checking);
        setBadge('sidebar-movies-blocked-count', c.movies_blocked);
        setBadge('sidebar-online-count', c.content_online);
      }
    } catch (_) {}
  },

  // ========================================================================
  // 3b. Modul: Filme prüfen (#/movies-checking & #/movie-checking)
  // ========================================================================
  activeMoviesCheckingFilter: 'all',

  setMoviesCheckingFilter(btnEl) {
    if (!btnEl) return;
    this.activeMoviesCheckingFilter = btnEl.getAttribute('data-mc-filter') || 'all';
    document.querySelectorAll('.mc-filter-btn').forEach(b => {
      b.classList.remove('active', 'btn-kkc');
      b.classList.add('btn-outline-kkc');
    });
    btnEl.classList.remove('btn-outline-kkc');
    btnEl.classList.add('active', 'btn-kkc');
    this.filterMoviesCheckingList();
  },

  filterMoviesCheckingList() {
    const q = (document.getElementById('mc-search-input')?.value || '').trim().toLowerCase();
    const filter = this.activeMoviesCheckingFilter || 'all';

    document.querySelectorAll('.mc-queue-row').forEach(row => {
      const haystack = row.getAttribute('data-mc-search') || '';
      const bram = row.getAttribute('data-mc-bram') || '';
      const coactor = row.getAttribute('data-mc-coactor') || '';
      const cat = row.getAttribute('data-mc-cat') || '';

      let matchesFilter = true;
      if (filter === 'bram_ok') {
        matchesFilter = (bram === 'bram_ok');
      } else if (filter === 'bram_attention') {
        matchesFilter = (bram === 'bram_attention');
      } else if (filter === 'co_actor_open') {
        matchesFilter = (coactor === 'co_actor_open');
      } else if (filter === 'porn' || filter === 'fetish') {
        matchesFilter = (cat === filter);
      }

      const matchesSearch = (q === '' || haystack.includes(q));
      row.classList.toggle('d-none', !(matchesFilter && matchesSearch));
    });
  },

  openImagePreviewModal(src, title) {
    const modalEl = document.getElementById('mcImagePreviewModal');
    const imgEl = document.getElementById('mcImagePreviewImg');
    const titleEl = document.getElementById('mcImagePreviewTitle');
    if (!modalEl || !imgEl) return;

    imgEl.src = src;
    if (titleEl) titleEl.textContent = title || 'Vorschaubild';
    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  },

  openImageLightbox(src, title) {
    if (!src) return;
    let modalEl = document.getElementById('ecGlobalImageLightboxModal');
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.id = 'ecGlobalImageLightboxModal';
      modalEl.className = 'modal fade';
      modalEl.tabIndex = -1;
      modalEl.setAttribute('aria-hidden', 'true');
      modalEl.innerHTML = `
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content border-0 shadow-lg" style="background: var(--kkc-surface-card);">
            <div class="modal-header border-bottom py-2 px-3">
              <h6 class="modal-title fw-bold mb-0" id="ecGlobalImageLightboxTitle">Bildvorschau</h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body p-3 text-center bg-dark rounded-bottom">
              <img id="ecGlobalImageLightboxImg" src="" alt="Vorschau" class="img-fluid rounded shadow" style="max-height: 78vh; object-fit: contain;">
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modalEl);
    }
    const imgEl = document.getElementById('ecGlobalImageLightboxImg');
    const titleEl = document.getElementById('ecGlobalImageLightboxTitle');
    if (imgEl) imgEl.src = src;
    if (titleEl) titleEl.textContent = title || 'Bildvorschau';
    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  },

  seekMoviePlayer(seconds) {
    const tabBtn = document.getElementById('tab-btn-review') || document.getElementById('tab-btn-me-edit');
    if (tabBtn && !tabBtn.classList.contains('active')) {
      tabBtn.click();
    }
    const video = document.getElementById('mc-video-player');
    if (!video) return;
    video.currentTime = Math.max(0, Number(seconds) || 0);
    video.play().catch(() => {});
    video.scrollIntoView({ behavior: 'smooth', block: 'center' });
  },

  seekMoviePlayerTo(seconds) {
    this.seekMoviePlayer(seconds);
  },

  skipMoviePlayer(deltaSec) {
    const video = document.getElementById('mc-video-player');
    if (!video) return;
    video.currentTime = Math.max(0, (video.currentTime || 0) + Number(deltaSec));
  },

  setMoviePlayerSpeed(speed, btnEl) {
    const video = document.getElementById('mc-video-player');
    if (video) {
      video.playbackRate = Number(speed) || 1;
    }
    if (btnEl) {
      document.querySelectorAll('.mc-speed-btn').forEach(b => b.classList.remove('active'));
      btnEl.classList.add('active');
    }
  },

  selectMovieThumb(fskType, num) {
    const is18 = Number(fskType) === 18;
    const inputEl = document.getElementById(is18 ? 'mc-input-fsk18' : 'mc-input-fsk16');
    const badgeEl = document.getElementById(is18 ? 'mc-badge-selected-fsk18' : 'mc-badge-selected-fsk16');
    if (inputEl) inputEl.value = String(num);
    if (badgeEl) badgeEl.textContent = `FSK${is18 ? '18' : '16'}: Bild #${num}`;

    const btnSelector = is18 ? '.mc-btn-fsk18' : '.mc-btn-fsk16';
    const activeCls = is18 ? ['btn-danger', 'text-white', 'fw-bold'] : ['btn-info', 'text-dark', 'fw-bold'];

    document.querySelectorAll(btnSelector).forEach(btn => {
      const bNum = parseInt(btn.getAttribute('data-thumb-num') || '0', 10);
      if (bNum === Number(num)) {
        btn.classList.remove('btn-outline-kkc');
        btn.classList.add(...activeCls);
      } else {
        btn.classList.remove(...activeCls);
        btn.classList.add('btn-outline-kkc');
      }
    });

    // Rahmen um aktive Thumbnail-Karten aktualisieren
    const sel16 = parseInt(document.getElementById('mc-input-fsk16')?.value || '1', 10);
    const sel18 = parseInt(document.getElementById('mc-input-fsk18')?.value || '1', 10);
    document.querySelectorAll('.mc-thumb-card').forEach(card => {
      const idMatch = (card.id || '').match(/mc-thumb-card-(\d+)/);
      const cardNum = idMatch ? parseInt(idMatch[1], 10) : 0;
      card.classList.toggle('border-primary', cardNum === sel16 || cardNum === sel18);
    });
  },

  updateMovieFormCounters() {
    const titleEl = document.getElementById('mc-input-title');
    const descEl = document.getElementById('mc-input-description');
    const metaTitleEl = document.getElementById('mc-input-meta-title');
    const metaDescEl = document.getElementById('mc-input-meta-desc');

    const titleCnt = document.getElementById('mc-title-counter');
    const descCnt = document.getElementById('mc-desc-counter');
    const metaTitleCnt = document.getElementById('mc-meta-title-counter');
    const metaDescCnt = document.getElementById('mc-meta-desc-counter');

    if (titleEl && titleCnt) titleCnt.textContent = `${titleEl.value.length} Zeichen`;
    if (descEl && descCnt) descCnt.textContent = `${descEl.value.length} Zeichen`;
    if (metaTitleEl && metaTitleCnt) metaTitleCnt.textContent = `${metaTitleEl.value.length}/65`;
    if (metaDescEl && metaDescCnt) metaDescCnt.textContent = `${metaDescEl.value.length}/156`;
  },

  updateMovieStreamingPrice() {
    const selectEl = document.getElementById('mc-input-amount-second');
    const totalEl = document.getElementById('mc-live-total-coins');
    if (!selectEl || !totalEl) return;

    const playtime = parseInt(selectEl.getAttribute('data-playtime') || '0', 10) || 0;
    const perSec = parseFloat(selectEl.value || '0') || 0;
    const totalCoins = Math.round(playtime * perSec);
    totalEl.textContent = `= ${totalCoins} Coins gesamt`;
  },

  toggleMovieDownloadPrice() {
    const sel = document.getElementById('mc-input-as-download');
    const wrap = document.getElementById('mc-download-price-wrap');
    if (!sel || !wrap) return;
    wrap.style.opacity = sel.value === '1' ? '1' : '0.5';
  },

  autofillMovieSeoFields() {
    const title = (document.getElementById('mc-input-title')?.value || '').trim();
    const desc = (document.getElementById('mc-input-description')?.value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    const metaTitleEl = document.getElementById('mc-input-meta-title');
    const metaDescEl = document.getElementById('mc-input-meta-desc');
    const seoUrlEl = document.getElementById('mc-input-seo-url');

    if (metaTitleEl && title !== '') {
      metaTitleEl.value = title.substring(0, 65).trim();
    }
    if (metaDescEl && desc !== '') {
      metaDescEl.value = desc.substring(0, 156).trim();
    }
    if (seoUrlEl && title !== '') {
      const slug = title
        .toLowerCase()
        .replace(/ä/g, 'ae')
        .replace(/ö/g, 'oe')
        .replace(/ü/g, 'ue')
        .replace(/ß/g, 'ss')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
      seoUrlEl.value = slug;
    }
    this.updateMovieFormCounters();
    this.toast('SEO-Felder wurden automatisch aus Titel und Beschreibung erzeugt.', 'info');
  },

  filterMovieCategories(query) {
    const q = String(query || '').trim().toLowerCase();
    document.querySelectorAll('.mc-cat-item').forEach(item => {
      const lbl = item.getAttribute('data-cat-label') || '';
      item.classList.toggle('d-none', q !== '' && !lbl.includes(q));
    });
  },

  onMovieCategoryChange(checkboxEl) {
    if (checkboxEl) {
      const label = checkboxEl.closest('label');
      if (label) {
        label.classList.toggle('border-primary', checkboxEl.checked);
        label.classList.toggle('bg-primary', checkboxEl.checked);
        label.classList.toggle('bg-opacity-10', checkboxEl.checked);
        label.classList.toggle('fw-semibold', checkboxEl.checked);
      }
    }
    const checkedCount = document.querySelectorAll('.mc-cat-checkbox:checked').length;
    const badge = document.getElementById('mc-selected-cats-badge');
    if (badge) {
      badge.textContent = `${checkedCount} Unterkategorien gewählt`;
    }
    this.checkPoppersCategoryConstraint();
  },

  checkPoppersCategoryConstraint() {
    const poppersCb = document.querySelector('.mc-cat-checkbox[value="poppers"]');
    const pubOpt = document.getElementById('mc-visible-public-opt');
    const warnEl = document.getElementById('mc-poppers-warning');
    const visSelect = document.getElementById('mc-visible-website');
    if (!poppersCb || !pubOpt) return;

    if (poppersCb.checked) {
      pubOpt.disabled = true;
      if (warnEl) warnEl.classList.remove('d-none');
      if (visSelect && visSelect.value === 'public' && visSelect.options.length > 1) {
        visSelect.selectedIndex = 1;
      }
    } else {
      pubOpt.disabled = false;
      if (warnEl) warnEl.classList.add('d-none');
    }
  },

  onMovieRejectionReasonChange(selectEl) {
    if (!selectEl) return;
    const opt = selectEl.options[selectEl.selectedIndex];
    const longText = opt ? (opt.getAttribute('data-long-text') || '') : '';
    const textarea = document.getElementById('mc-rejection-text');
    if (textarea) {
      textarea.value = longText;
    }
  },

  prefillMovieRejection(reasonCode) {
    const selectEl = document.getElementById('mc-rejection-reason-select');
    if (!selectEl) return;
    selectEl.value = reasonCode;
    this.onMovieRejectionReasonChange(selectEl);
    document.getElementById('mc-reject-card')?.scrollIntoView({ behavior: 'smooth' });
  },

  async submitMovieApprove(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('mc-approve-form');
    const btn = document.getElementById('mc-submit-approve-btn');
    if (!form) return false;

    const formData = new FormData(form);
    formData.append('_csrf', this.csrfToken);

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Speichere & gebe frei...';
    }

    try {
      const res = await fetch(`${this.basePath}/api.php?route=movies-checking/approve`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Film konnte nicht freigegeben werden.', 'danger');
        return false;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'Film erfolgreich geprüft und freigegeben!', 'success');
      this.refreshSidebarCounts();

      const autoNext = document.getElementById('mc-auto-next-check')?.checked;
      if (autoNext && data.next_movie_id && Number(data.next_movie_id) > 0) {
        window.location.hash = `#/movie-checking?id=${Number(data.next_movie_id)}`;
      } else {
        window.location.hash = '#/movies-checking';
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Film speichern &amp; freigeben';
      }
    }
    return false;
  },

  async submitMovieReject(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('mc-reject-form');
    const btn = document.getElementById('mc-submit-reject-btn');
    if (!form) return false;

    const reasonSel = document.getElementById('mc-rejection-reason-select');
    const reasonTxt = document.getElementById('mc-rejection-text');
    if (!reasonSel || reasonSel.value === '0' || !reasonTxt || reasonTxt.value.trim() === '') {
      this.toast('Bitte wähle einen Ablehnungsgrund und gib eine Begründung an.', 'warning');
      return false;
    }

    const formData = new FormData(form);
    formData.append('_csrf', this.csrfToken);

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Lehne Film ab...';
    }

    try {
      const res = await fetch(`${this.basePath}/api.php?route=movies-checking/reject`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Film konnte nicht abgelehnt werden.', 'danger');
        return false;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'Film wurde abgelehnt.', 'warning');
      this.refreshSidebarCounts();

      const autoNext = document.getElementById('mc-auto-next-check')?.checked;
      if (autoNext && data.next_movie_id && Number(data.next_movie_id) > 0) {
        window.location.hash = `#/movie-checking?id=${Number(data.next_movie_id)}`;
      } else {
        window.location.hash = '#/movies-checking';
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-slash-circle me-1"></i> Film jetzt verbindlich ablehnen';
      }
    }
    return false;
  },

  async reviewMovieCoActor(movieId, merchantId, coActorId, decision) {
    let reason = '';
    if (decision === 'reject') {
      const r = window.prompt(
        'Grund für die Ablehnung des ID-Shots / Drehpartners:',
        'Der Personalausweis ist auf dem ID-Shot leider nicht scharf genug lesbar. Bitte nimm den ID-Shot per Smartphone-QR-Code erneut auf.'
      );
      if (r === null) return;
      reason = r.trim();
    }

    const formData = new FormData();
    formData.append('_csrf', this.csrfToken);
    formData.append('movie_id', String(movieId));
    formData.append('merchant_id', String(merchantId));
    formData.append('co_actor_id', String(coActorId));
    formData.append('decision', decision);
    formData.append('reason', reason);

    try {
      const res = await fetch(`${this.basePath}/api.php?route=movies-checking/co-actor-action`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Drehpartner-Status konnte nicht geändert werden.', 'danger');
        return;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'Drehpartner-Status aktualisiert.', decision === 'verify' ? 'success' : 'warning');
      this.refreshSidebarCounts();

      const badge = document.getElementById(`mc-coactor-status-${coActorId}`);
      if (badge) {
        if (decision === 'verify') {
          badge.className = 'badge-kkc badge-kkc-success';
          badge.textContent = 'Geprüft & Freigegeben';
        } else if (decision === 'reject') {
          badge.className = 'badge-kkc badge-kkc-danger';
          badge.textContent = 'Abgelehnt – Korrektur nötig';
        }
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    }
  },

  async runMovieAiAction(movieId, subAction) {
    const formData = new FormData();
    formData.append('_csrf', this.csrfToken);
    formData.append('movie_id', String(movieId));
    formData.append('sub_action', subAction);

    const titleEl = document.getElementById('mc-input-title');
    const descEl = document.getElementById('mc-input-description');
    const metaTitleEl = document.getElementById('mc-input-meta-title');
    const metaDescEl = document.getElementById('mc-input-meta-desc');
    const transcriptEl = document.getElementById('mc-spoken-transcript');

    if (titleEl) formData.append('title', titleEl.value);
    if (descEl) formData.append('description', descEl.value);
    if (metaTitleEl) formData.append('meta_title', metaTitleEl.value);
    if (metaDescEl) formData.append('meta_description', metaDescEl.value);
    if (transcriptEl) formData.append('spoken_transcript', transcriptEl.value);

    this.toast(
      subAction === 'run_whisper'
        ? 'Whisper Audio-Analyse läuft... Bitte einen Moment Geduld.'
        : 'BRAM-Compliance-Prüfung wird ausgeführt...',
      'info'
    );

    try {
      const res = await fetch(`${this.basePath}/api.php?route=movies-checking/ai-action`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'KI-Analyse fehlgeschlagen.', 'danger');
        return;
      }

      this.toast(data.message || 'KI-Analyse abgeschlossen.', 'success');
      this.handleRoute();
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    }
  },

  async openMovieFileMetaModal(movieId) {
    const modalEl = document.getElementById('mcFileMetaModal');
    const bodyEl = document.getElementById('mcFileMetaModalBody');
    if (!modalEl || !bodyEl) return;

    bodyEl.innerHTML = `
      <div class="text-center p-4 text-muted">
        <div class="spinner-border spinner-border-sm ec-text-primary me-2"></div>
        Lade getID3 Datei-Metadaten...
      </div>
    `;

    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    try {
      const res = await fetch(`${this.basePath}/api.php?route=movies-checking/file-meta&id=${encodeURIComponent(movieId)}`);
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        bodyEl.innerHTML = `<div class="alert alert-danger mb-0">${this.escapeHtml(data.message || 'Fehler beim Laden.')}</div>`;
        return;
      }

      const summary = data.summary || {};
      const rows = Object.entries(summary).map(([k, v]) => `
        <tr>
          <th class="text-muted small" style="width: 180px;">${this.escapeHtml(k)}</th>
          <td class="fw-semibold font-monospace small">${this.escapeHtml(v)}</td>
        </tr>
      `).join('');

      bodyEl.innerHTML = `
        <div class="table-responsive mb-3">
          <table class="table table-sm table-kkc mb-0">
            <tbody>${rows}</tbody>
          </table>
        </div>
        ${data.raw_dump ? `
          <details class="small">
            <summary class="fw-bold ec-text-primary mb-2" style="cursor:pointer;">Vollständigen getID3 Roh-Dump einblenden</summary>
            <pre class="p-3 rounded border bg-dark text-light font-monospace mb-0" style="max-height: 340px; overflow: auto; font-size: 0.75rem;">${this.escapeHtml(data.raw_dump)}</pre>
          </details>
        ` : ''}
      `;
    } catch (err) {
      bodyEl.innerHTML = `<div class="alert alert-danger mb-0">Verbindungsfehler: ${this.escapeHtml(err.message)}</div>`;
    }
  },

  // ========================================================================
  // 3c. Modul: Fotoalben prüfen (#/photo-albums-checking & #/photo-album-checking)
  // ========================================================================
  activePhotoAlbumsCheckingFilter: 'all',
  currentAlbumGalleryIndex: 0,

  setPhotoAlbumsCheckingFilter(btnEl) {
    if (!btnEl) return;
    this.activePhotoAlbumsCheckingFilter = btnEl.getAttribute('data-pac-filter') || 'all';
    document.querySelectorAll('.pac-filter-btn').forEach(b => {
      b.classList.remove('active', 'btn-kkc');
      b.classList.add('btn-outline-kkc');
    });
    btnEl.classList.remove('btn-outline-kkc');
    btnEl.classList.add('active', 'btn-kkc');
    this.filterPhotoAlbumsCheckingList();
  },

  filterPhotoAlbumsCheckingList() {
    const q = (document.getElementById('pac-search-input')?.value || '').trim().toLowerCase();
    const filter = this.activePhotoAlbumsCheckingFilter || 'all';

    document.querySelectorAll('.pac-queue-row').forEach(row => {
      const haystack = row.getAttribute('data-pac-search') || '';
      const bram = row.getAttribute('data-pac-bram') || '';
      const coactor = row.getAttribute('data-pac-coactor') || '';
      const cat = row.getAttribute('data-pac-cat') || '';

      let matchesFilter = true;
      if (filter === 'bram_ok') {
        matchesFilter = (bram === 'bram_ok');
      } else if (filter === 'bram_attention') {
        matchesFilter = (bram === 'bram_attention');
      } else if (filter === 'co_actor_open') {
        matchesFilter = (coactor === 'co_actor_open');
      } else if (filter === 'porn' || filter === 'fetish') {
        matchesFilter = (cat === filter);
      }

      const matchesSearch = (q === '' || haystack.includes(q));
      row.classList.toggle('d-none', !(matchesFilter && matchesSearch));
    });
  },

  openAlbumGalleryModal(index) {
    const cards = Array.from(document.querySelectorAll('.pac-photo-card'));
    if (cards.length === 0) return;

    this.currentAlbumGalleryIndex = ((index % cards.length) + cards.length) % cards.length;
    const card = cards[this.currentAlbumGalleryIndex];
    const modalEl = document.getElementById('pacGalleryModal');
    const imgEl = document.getElementById('pacGalleryModalImg');
    const titleEl = document.getElementById('pacGalleryModalTitle');
    if (!modalEl || !imgEl || !card) return;

    imgEl.src = card.getAttribute('data-full-src') || '';
    if (titleEl) {
      titleEl.textContent = `${card.getAttribute('data-photo-label') || 'Foto'} (${this.currentAlbumGalleryIndex + 1} / ${cards.length})`;
    }

    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  },

  stepAlbumGallery(delta) {
    this.openAlbumGalleryModal(this.currentAlbumGalleryIndex + Number(delta));
  },

  async toggleAlbumPhotoReject(photoId, btnEl) {
    if (!photoId || !btnEl) return;
    const currentlyRejected = btnEl.getAttribute('data-rejected') === '1';
    const nextRejected = currentlyRejected ? 0 : 1;

    btnEl.disabled = true;

    try {
      const formData = new FormData();
      formData.append('_csrf', this.csrfToken);
      formData.append('photo_id', photoId);
      formData.append('rejected', String(nextRejected));

      const res = await fetch(`${this.basePath}/api.php?route=photo-albums-checking/toggle-photo`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Status des Fotos konnte nicht geändert werden.', 'danger');
        return;
      }

      this.resetLogoutCountdown();
      const card = document.getElementById(`pac-photo-card-${photoId}`);
      const overlay = card?.querySelector('.pac-photo-rej-overlay');

      btnEl.setAttribute('data-rejected', String(nextRejected));
      if (nextRejected === 1) {
        card?.classList.add('border-danger', 'bg-danger', 'bg-opacity-10');
        overlay?.classList.remove('d-none');
        btnEl.className = 'btn btn-sm flex-fill py-0 pac-photo-toggle-btn btn-success';
        btnEl.innerHTML = '<i class="bi bi-check-lg me-1"></i>Zulassen';
      } else {
        card?.classList.remove('border-danger', 'bg-danger', 'bg-opacity-10');
        overlay?.classList.add('d-none');
        btnEl.className = 'btn btn-sm flex-fill py-0 pac-photo-toggle-btn btn-outline-danger';
        btnEl.innerHTML = '<i class="bi bi-trash3 me-1"></i>Ablehnen';
      }

      const rejBadge = document.getElementById('pac-rejected-photos-badge');
      if (rejBadge && typeof data.rejected_count === 'number') {
        if (data.rejected_count > 0) {
          rejBadge.className = 'badge-kkc badge-kkc-danger';
          rejBadge.textContent = `${data.rejected_count} Foto(s) abgelehnt`;
        } else {
          rejBadge.className = 'badge-kkc badge-kkc-success';
          rejBadge.textContent = 'Alle Fotos zulässig';
        }
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      btnEl.disabled = false;
    }
  },

  async batchAllowAllAlbumPhotos(albumDbId) {
    try {
      const formData = new FormData();
      formData.append('_csrf', this.csrfToken);
      formData.append('album_db_id', String(albumDbId));
      formData.append('batch_mode', 'allow_all');

      const res = await fetch(`${this.basePath}/api.php?route=photo-albums-checking/toggle-photo`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Fehler beim Freigeben der Fotos.', 'danger');
        return;
      }

      this.toast(data.message || 'Alle Fotos im Album wurden zugelassen.', 'success');

      document.querySelectorAll('.pac-photo-card').forEach(card => {
        card.classList.remove('border-danger', 'bg-danger', 'bg-opacity-10');
        card.querySelector('.pac-photo-rej-overlay')?.classList.add('d-none');
        const btn = card.querySelector('.pac-photo-toggle-btn');
        if (btn) {
          btn.setAttribute('data-rejected', '0');
          btn.className = 'btn btn-sm flex-fill py-0 pac-photo-toggle-btn btn-outline-danger';
          btn.innerHTML = '<i class="bi bi-trash3 me-1"></i>Ablehnen';
        }
      });

      const rejBadge = document.getElementById('pac-rejected-photos-badge');
      if (rejBadge) {
        rejBadge.className = 'badge-kkc badge-kkc-success';
        rejBadge.textContent = 'Alle Fotos zulässig';
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    }
  },

  updatePhotoAlbumFormCounters() {
    const titleEl = document.getElementById('pac-input-title');
    const descEl = document.getElementById('pac-input-description');
    const metaTitleEl = document.getElementById('pac-input-meta-title');
    const metaDescEl = document.getElementById('pac-input-meta-desc');

    const titleCnt = document.getElementById('pac-title-counter');
    const descCnt = document.getElementById('pac-desc-counter');
    const metaTitleCnt = document.getElementById('pac-meta-title-counter');
    const metaDescCnt = document.getElementById('pac-meta-desc-counter');

    if (titleEl && titleCnt) titleCnt.textContent = `${titleEl.value.length}/65`;
    if (descEl && descCnt) descCnt.textContent = `${descEl.value.length} Zeichen`;
    if (metaTitleEl && metaTitleCnt) metaTitleCnt.textContent = `${metaTitleEl.value.length}/65`;
    if (metaDescEl && metaDescCnt) metaDescCnt.textContent = `${metaDescEl.value.length}/156`;
  },

  autofillPhotoAlbumSeoFields() {
    const title = (document.getElementById('pac-input-title')?.value || '').trim();
    const desc = (document.getElementById('pac-input-description')?.value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    const metaTitleEl = document.getElementById('pac-input-meta-title');
    const metaDescEl = document.getElementById('pac-input-meta-desc');
    const seoUrlEl = document.getElementById('pac-input-seo-url');

    if (metaTitleEl && title !== '') {
      metaTitleEl.value = title.substring(0, 65).trim();
    }
    if (metaDescEl && desc !== '') {
      metaDescEl.value = desc.substring(0, 156).trim();
    }
    if (seoUrlEl && title !== '') {
      const slug = title
        .toLowerCase()
        .replace(/ä/g, 'ae')
        .replace(/ö/g, 'oe')
        .replace(/ü/g, 'ue')
        .replace(/ß/g, 'ss')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
      seoUrlEl.value = slug;
    }
    this.updatePhotoAlbumFormCounters();
    this.toast('SEO-Felder wurden automatisch aus Titel und Beschreibung erzeugt.', 'info');
  },

  filterPhotoAlbumCategories(query) {
    const q = String(query || '').trim().toLowerCase();
    document.querySelectorAll('.pac-cat-item').forEach(item => {
      const lbl = item.getAttribute('data-cat-label') || '';
      item.classList.toggle('d-none', q !== '' && !lbl.includes(q));
    });
  },

  onPhotoAlbumCategoryChange(checkboxEl) {
    if (checkboxEl) {
      const label = checkboxEl.closest('label');
      if (label) {
        label.classList.toggle('border-primary', checkboxEl.checked);
        label.classList.toggle('bg-primary', checkboxEl.checked);
        label.classList.toggle('bg-opacity-10', checkboxEl.checked);
        label.classList.toggle('fw-semibold', checkboxEl.checked);
      }
    }
    const checkedCount = document.querySelectorAll('.pac-cat-checkbox:checked').length;
    const badge = document.getElementById('pac-selected-cats-badge');
    if (badge) {
      badge.textContent = `${checkedCount} Unterkategorien gewählt`;
    }
  },

  onPhotoAlbumRejectionReasonChange(selectEl) {
    if (!selectEl) return;
    const opt = selectEl.options[selectEl.selectedIndex];
    const longText = opt ? (opt.getAttribute('data-long-text') || '') : '';
    const textarea = document.getElementById('pac-rejection-text');
    if (textarea) {
      textarea.value = longText;
    }
  },

  async submitPhotoAlbumApprove(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('pac-approve-form');
    const btn = document.getElementById('pac-submit-approve-btn');
    if (!form) return false;

    const formData = new FormData(form);
    formData.append('_csrf', this.csrfToken);

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Speichere & veröffentliche...';
    }

    try {
      const res = await fetch(`${this.basePath}/api.php?route=photo-albums-checking/approve`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Fotoalbum konnte nicht veröffentlicht werden.', 'danger');
        return false;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'Fotoalbum erfolgreich geprüft und veröffentlicht!', 'success');
      this.refreshSidebarCounts();

      const autoNext = document.getElementById('pac-auto-next-check')?.checked;
      if (autoNext && data.next_album_id && Number(data.next_album_id) > 0) {
        window.location.hash = `#/photo-album-checking?id=${Number(data.next_album_id)}`;
      } else {
        window.location.hash = '#/photo-albums-checking';
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Fotoalbum speichern &amp; veröffentlichen';
      }
    }
    return false;
  },

  async submitPhotoAlbumReject(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('pac-reject-form');
    const btn = document.getElementById('pac-submit-reject-btn');
    if (!form) return false;

    const reasonSel = document.getElementById('pac-rejection-reason-select');
    const reasonTxt = document.getElementById('pac-rejection-text');
    if (!reasonSel || reasonSel.value === '0' || !reasonTxt || reasonTxt.value.trim() === '') {
      this.toast('Bitte wähle einen Ablehnungsgrund und gib eine Begründung an.', 'warning');
      return false;
    }

    const formData = new FormData(form);
    formData.append('_csrf', this.csrfToken);

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Lehne Fotoalbum ab...';
    }

    try {
      const res = await fetch(`${this.basePath}/api.php?route=photo-albums-checking/reject`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Fotoalbum konnte nicht abgelehnt werden.', 'danger');
        return false;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'Fotoalbum wurde abgelehnt.', 'warning');
      this.refreshSidebarCounts();

      const autoNext = document.getElementById('pac-auto-next-check')?.checked;
      if (autoNext && data.next_album_id && Number(data.next_album_id) > 0) {
        window.location.hash = `#/photo-album-checking?id=${Number(data.next_album_id)}`;
      } else {
        window.location.hash = '#/photo-albums-checking';
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-slash-circle me-1"></i> Fotoalbum jetzt verbindlich ablehnen';
      }
    }
    return false;
  },

  async reviewAlbumCoActor(albumDbId, merchantId, coActorId, decision) {
    let reason = '';
    if (decision === 'reject') {
      const r = window.prompt(
        'Grund für die Ablehnung des ID-Shots / Drehpartners:',
        'Der Personalausweis ist auf dem ID-Shot leider nicht scharf genug lesbar. Bitte nimm den ID-Shot per Smartphone-QR-Code erneut auf.'
      );
      if (r === null) return;
      reason = r.trim();
    }

    const formData = new FormData();
    formData.append('_csrf', this.csrfToken);
    formData.append('album_id', String(albumDbId));
    formData.append('merchant_id', String(merchantId));
    formData.append('co_actor_id', String(coActorId));
    formData.append('decision', decision);
    formData.append('reason', reason);

    try {
      const res = await fetch(`${this.basePath}/api.php?route=photo-albums-checking/co-actor-action`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Drehpartner-Status konnte nicht geändert werden.', 'danger');
        return;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'Drehpartner-Status aktualisiert.', decision === 'verify' ? 'success' : 'warning');
      this.refreshSidebarCounts();

      const badge = document.getElementById(`pac-coactor-status-${coActorId}`);
      if (badge) {
        if (decision === 'verify') {
          badge.className = 'badge-kkc badge-kkc-success';
          badge.textContent = 'Geprüft & Freigegeben';
        } else if (decision === 'reject') {
          badge.className = 'badge-kkc badge-kkc-danger';
          badge.textContent = 'Abgelehnt – Korrektur nötig';
        }
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    }
  },

  async runPhotoAlbumAiAction(albumId) {
    const title = document.getElementById('pac-input-title')?.value ?? '';
    const description = document.getElementById('pac-input-description')?.value ?? '';
    const metaTitle = document.getElementById('pac-input-meta-title')?.value ?? '';
    const metaDescription = document.getElementById('pac-input-meta-desc')?.value ?? '';

    this.toast('Starte BRAM Text-Scan mit aktuellen Formularwerten...', 'info');
    try {
      const res = await fetch(`${this.basePath}/api.php?route=photo-albums-checking/ai-action`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          album_id: Number(albumId),
          title,
          description,
          meta_title: metaTitle,
          meta_description: metaDescription,
        }),
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'BRAM Text-Check fehlgeschlagen.', 'danger');
        return;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'BRAM Text-Check abgeschlossen.', 'success');

      const report = data.bram_report;
      if (!report) return;

      const cStatus = String(report.compliance_status || 'pending');
      const rScore = Math.max(0, Number(report.risk_score || 0));
      const summary = String(report.summary || '');
      const violations = Array.isArray(report.violations) ? report.violations : [];
      const engine = String(report.ai_engine || 'local_rules');
      const updatedAt = String(report.updated_at || '');

      let bgClass = 'bg-secondary bg-opacity-10 border-secondary';
      let badgeClass = 'badge-kkc-muted';
      let label = 'Prüfung ausstehend';
      let icon = 'bi-hourglass-split';

      if (cStatus === 'ok' || cStatus === 'green') {
        bgClass = 'bg-success bg-opacity-10 border-success';
        badgeClass = 'badge-kkc-success';
        label = 'BRAM Konform (Keine Treffer)';
        icon = 'bi-shield-check';
      } else if (cStatus === 'warning' || cStatus === 'yellow') {
        bgClass = 'bg-warning bg-opacity-10 border-warning';
        badgeClass = 'badge-kkc-warning';
        label = 'Manuelle Prüfung empfohlen (Hinweis)';
        icon = 'bi-exclamation-triangle-fill';
      } else if (cStatus === 'violation' || cStatus === 'red') {
        bgClass = 'bg-danger bg-opacity-10 border-danger';
        badgeClass = 'badge-kkc-danger';
        label = 'BRAM / Compliance Treffer erkannt!';
        icon = 'bi-shield-fill-exclamation';
      }

      const topBanner = document.getElementById('pac-top-bram-banner');
      if (topBanner) {
        topBanner.className = `card card-kkc mb-4 border ${bgClass}`;
      }

      const topBadge = document.getElementById('pac-top-bram-badge');
      if (topBadge) {
        topBadge.className = `badge-kkc ${badgeClass}`;
        topBadge.innerHTML = `<i class="bi ${icon} me-1"></i>${this.escapeHtml(label)}`;
      }

      const topScore = document.getElementById('pac-top-bram-score');
      if (topScore) {
        topScore.textContent = `Risk-Score: ${rScore}/100`;
      }

      const topSummary = document.getElementById('pac-top-bram-summary');
      if (topSummary) {
        topSummary.textContent = summary;
      }

      const topDetailsCnt = document.getElementById('pac-top-bram-details-cnt');
      if (topDetailsCnt) {
        topDetailsCnt.textContent = `Details (${violations.length})`;
      }

      const topPills = document.getElementById('pac-top-bram-pills');
      if (topPills) {
        if (violations.length > 0) {
          topPills.classList.remove('d-none');
          topPills.innerHTML = violations.map(v => {
            const vSev = String(v.severity || 'warning');
            const vBadge = (vSev === 'violation' || vSev === 'critical') ? 'badge-kkc-danger' : 'badge-kkc-warning';
            const vTerm = String(v.matched_term || v.matched_quote || v.term || '');
            const vCat = String(v.category || 'BRAM');
            const vSrc = String(v.source_label || v.source || 'Text');
            const vReason = String(v.reason || '');
            return `<span class="badge-kkc ${vBadge}" title="${this.escapeHtml(vReason)}"><strong>${this.escapeHtml(vCat)}:</strong> "${this.escapeHtml(vTerm)}" <span class="opacity-75">(${this.escapeHtml(vSrc)})</span></span>`;
          }).join('');
        } else {
          topPills.classList.add('d-none');
          topPills.innerHTML = '';
        }
      }

      const tabNavBadge = document.getElementById('pac-tab-bram-badge');
      if (tabNavBadge) {
        tabNavBadge.className = `badge-kkc ${badgeClass} ms-1`;
        tabNavBadge.textContent = `${rScore}/100`;
      }

      const tabKpiBadge = document.getElementById('pac-bram-tab-badge');
      if (tabKpiBadge) {
        tabKpiBadge.className = `badge-kkc ${badgeClass} fs-6`;
        tabKpiBadge.innerHTML = `<i class="bi ${icon} me-1"></i>${this.escapeHtml(label)}`;
      }

      const tabKpiScore = document.getElementById('pac-bram-tab-score');
      if (tabKpiScore) {
        const scoreColor = rScore >= 70 ? 'text-danger' : (rScore >= 30 ? 'text-warning' : 'text-success');
        tabKpiScore.className = `fs-4 fw-bold ${scoreColor}`;
        tabKpiScore.innerHTML = `${rScore} <span class="fs-6 text-muted fw-normal">/ 100</span>`;
      }

      const tabKpiEngine = document.getElementById('pac-bram-tab-engine');
      if (tabKpiEngine) {
        tabKpiEngine.textContent = engine;
      }

      const tabKpiUpdated = document.getElementById('pac-bram-tab-updated');
      if (tabKpiUpdated && updatedAt) {
        tabKpiUpdated.textContent = `Stand: ${updatedAt}`;
      }

      const tabSummary = document.getElementById('pac-bram-tab-summary');
      if (tabSummary) {
        tabSummary.textContent = summary;
      }

      const tabBody = document.getElementById('pac-bram-tab-body');
      if (tabBody) {
        if (violations.length === 0) {
          tabBody.innerHTML = `
            <div class="p-4 text-center text-success">
              <i class="bi bi-check-circle-fill fs-2 d-block mb-2"></i>
              <strong>Keine BRAM- oder Compliance-Auffälligkeiten in Titel, Beschreibung oder SEO-Feldern gefunden.</strong>
            </div>
          `;
        } else {
          const rowsHtml = violations.map(v => {
            const vSev = String(v.severity || 'warning');
            const isCrit = (vSev === 'violation' || vSev === 'critical');
            const vCat = String(v.category || 'Allgemein');
            const vSrc = String(v.source_label || v.source || 'Text');
            const vQuote = String(v.matched_quote || v.matched_term || v.term || '');
            const vReason = String(v.reason || '');
            const sevHtml = isCrit
              ? '<span class="badge-kkc badge-kkc-danger">KRITISCH (Verstoß)</span>'
              : '<span class="badge-kkc badge-kkc-warning">WARNUNG (Prüfen)</span>';
            return `
              <tr>
                <td>${sevHtml}</td>
                <td class="fw-semibold small">${this.escapeHtml(vCat)}</td>
                <td><span class="badge-kkc badge-kkc-muted">${this.escapeHtml(vSrc)}</span></td>
                <td><code class="text-danger fw-bold">"${this.escapeHtml(vQuote)}"</code></td>
                <td class="small text-muted">${this.escapeHtml(vReason)}</td>
              </tr>
            `;
          }).join('');

          tabBody.innerHTML = `
            <div class="table-responsive">
              <table class="table table-kkc align-middle mb-0">
                <thead>
                  <tr>
                    <th>Schweregrad</th>
                    <th>Kategorie</th>
                    <th>Fundort</th>
                    <th>Erkannter Begriff / Textstelle</th>
                    <th>Erklärung / Richtlinie</th>
                  </tr>
                </thead>
                <tbody>${rowsHtml}</tbody>
              </table>
            </div>
          `;
        }
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    }
  },

  // ========================================================================
  // 3c. Film- & Fotoalben-Gesamtübersicht (MoviesOnline & PhotoAlbumsOnline)
  // ========================================================================
  openLightbox(imgUrl, title = 'Bildvorschau') {
    const existingModal = document.getElementById('mcImagePreviewModal');
    if (existingModal) {
      this.openImagePreviewModal(imgUrl, title);
      return;
    }

    let modalEl = document.getElementById('ecGlobalLightboxModal');
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.id = 'ecGlobalLightboxModal';
      modalEl.className = 'modal fade';
      modalEl.tabIndex = -1;
      modalEl.setAttribute('aria-hidden', 'true');
      modalEl.innerHTML = `
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content" style="background: var(--kkc-card-bg); border: 1px solid var(--kkc-border);">
            <div class="modal-header py-2 px-3">
              <h5 class="modal-title h6 mb-0" id="ecGlobalLightboxTitle">Bildvorschau</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body p-2 text-center bg-dark">
              <img src="" id="ecGlobalLightboxImg" alt="Vorschau" class="img-fluid rounded" style="max-height: 82vh;">
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modalEl);
    }

    const titleEl = document.getElementById('ecGlobalLightboxTitle');
    const imgEl = document.getElementById('ecGlobalLightboxImg');
    if (titleEl) titleEl.textContent = title;
    if (imgEl) imgEl.src = imgUrl;

    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  },

  applyMoviesOnlineFilters(pageOverride) {
    if (pageOverride && typeof pageOverride === 'object' && typeof pageOverride.preventDefault === 'function') {
      pageOverride.preventDefault();
      pageOverride = 1;
    }
    const q = (document.getElementById('mo-filter-q')?.value || '').trim();
    const status = document.getElementById('mo-filter-status')?.value || 'all';
    const cat = document.getElementById('mo-filter-cat')?.value || 'all';
    const quality = document.getElementById('mo-filter-quality')?.value || 'all';
    const lang = document.getElementById('mo-filter-lang')?.value || 'all';
    const bram = document.getElementById('mo-filter-bram')?.value || 'all';
    const sort = document.getElementById('mo-filter-sort')?.value || 'id_desc';
    const perPage = document.getElementById('mo-filter-per-page')?.value || '100';
    const page = (typeof pageOverride === 'number' || typeof pageOverride === 'string') ? (Number(pageOverride) || 1) : 1;

    const params = new URLSearchParams();
    if (q !== '') params.set('q', q);
    if (status !== 'all') params.set('status', status);
    if (cat !== 'all') params.set('cat', cat);
    if (quality !== 'all') params.set('quality', quality);
    if (lang !== 'all') params.set('lang', lang);
    if (bram !== 'all') params.set('bram', bram);
    if (sort !== 'id_desc') params.set('sort', sort);
    if (perPage !== '100') params.set('per_page', perPage);
    if (page > 1) params.set('page', String(page));

    const qs = params.toString();
    const targetHash = `#/movies-online${qs ? '?' + qs : ''}`;
    if (window.location.hash === targetHash) {
      this.handleRoute();
    } else {
      window.location.hash = targetHash;
    }
  },

  resetMoviesOnlineFilters() {
    window.location.hash = '#/movies-online';
  },

  goToMoviesOnlinePage(page) {
    this.applyMoviesOnlineFilters(page);
  },

  quickFilterMoviesOnlineTable(query) {
    const q = String(query || '').trim().toLowerCase();
    document.querySelectorAll('.mo-movie-row').forEach(row => {
      const hay = row.getAttribute('data-search') || '';
      row.classList.toggle('d-none', q !== '' && !hay.includes(q));
    });
  },

  async recalcOnlineMoviesSize() {
    const btn = document.getElementById('mo-recalc-size-btn');
    const sizeTextEl = document.getElementById('mo-storage-size-text');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Berechne...';
    }

    try {
      const formData = new FormData();
      formData.append('_csrf', this.csrfToken);

      const res = await fetch(`${this.basePath}/api.php?route=movies-online/recalc-size`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Speicherplatz konnte nicht berechnet werden.', 'danger');
        return;
      }

      this.resetLogoutCountdown();
      if (sizeTextEl && data.formatted) {
        sizeTextEl.textContent = data.formatted;
      }
      this.toast(data.message || 'Speicherplatz der Online-Filme neu berechnet.', 'success');
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-hdd-stack me-1"></i> Speichergröße neu berechnen';
      }
    }
  },

  async submitMovieOnlineSave(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('movie-online-save-form');
    const btn = document.getElementById('me-save-btn');
    if (!form) return false;

    const statusSel = form.querySelector('select[name="movie_status"]');
    if (statusSel && statusSel.value === 'deleted') {
      const ok = window.confirm('Möchtest du diesen Film wirklich auf „Gelöscht“ setzen? Alle Videodateien auf dem Server werden unwiderruflich gelöscht und der Film wird aus movies_online entfernt!');
      if (!ok) return false;
    }

    const formData = new FormData(form);
    formData.append('_csrf', this.csrfToken);

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Speichere Änderungen...';
    }

    try {
      const res = await fetch(`${this.basePath}/api.php?route=movies-online/save`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Änderungen konnten nicht gespeichert werden.', 'danger');
        return false;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'Änderungen am Online-Film wurden erfolgreich gespeichert.', 'success');
      this.refreshSidebarCounts();

      if (data.deleted) {
        window.location.hash = '#/movies-online';
      } else {
        this.handleRoute();
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Änderungen an Online-Film speichern';
      }
    }
    return false;
  },

  filterMoviePurchasesTable(query) {
    const q = String(query || '').trim().toLowerCase();
    document.querySelectorAll('.me-purchase-row').forEach(row => {
      const hay = row.getAttribute('data-search') || '';
      row.classList.toggle('d-none', q !== '' && !hay.includes(q));
    });
  },

  setMoviesDraftsFilter(filterKey, btnEl) {
    this.moviesDraftsFilter = filterKey || 'all';
    if (btnEl && btnEl.parentElement) {
      btnEl.parentElement.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
      btnEl.classList.add('active');
    }
    this.filterMoviesDraftsList();
  },

  filterMoviesDraftsList() {
    const q = (document.getElementById('md-search-input')?.value || '').trim().toLowerCase();
    const filter = this.moviesDraftsFilter || 'all';
    let visible = 0;

    document.querySelectorAll('.md-draft-row').forEach(row => {
      const hay = row.getAttribute('data-search') || '';
      const conv = parseInt(row.getAttribute('data-conv') || '0', 10);
      const isChecked = row.getAttribute('data-checked') === '1';

      let passFilter = true;
      if (filter === 'ready') passFilter = (conv === 2);
      else if (filter === 'converting') passFilter = (conv < 2);
      else if (filter === 'error') passFilter = (conv > 2);
      else if (filter === 'checked') passFilter = isChecked;

      const passSearch = (q === '' || hay.includes(q));
      const show = passFilter && passSearch;
      row.classList.toggle('d-none', !show);
      if (show) visible++;
    });

    const cntEl = document.getElementById('md-visible-cnt');
    if (cntEl) cntEl.textContent = String(visible);
  },

  setMoviesBlockedFilter(filterKey, btnEl) {
    this.moviesBlockedFilter = filterKey || 'all';
    if (btnEl && btnEl.parentElement) {
      btnEl.parentElement.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
      btnEl.classList.add('active');
    }
    this.filterMoviesBlockedList();
  },

  filterMoviesBlockedList() {
    const q = (document.getElementById('mb-search-input')?.value || '').trim().toLowerCase();
    const filter = this.moviesBlockedFilter || 'all';
    let visible = 0;

    document.querySelectorAll('.mb-blocked-row').forEach(row => {
      const hay = row.getAttribute('data-search') || '';
      const released = parseInt(row.getAttribute('data-released') || '0', 10);
      const status = row.getAttribute('data-status') || '';

      let passFilter = true;
      if (filter === 'rejected') passFilter = (released === 2);
      else if (filter === 'blocked') passFilter = (status === 'blocked');
      else if (filter === 'deleted') passFilter = (status === 'deleted');

      const passSearch = (q === '' || hay.includes(q));
      const show = passFilter && passSearch;
      row.classList.toggle('d-none', !show);
      if (show) visible++;
    });

    const cntEl = document.getElementById('mb-visible-cnt');
    if (cntEl) cntEl.textContent = String(visible);
  },

  applyPhotoAlbumsOnlineFilters(pageOverride) {
    const q = (document.getElementById('pao-filter-q')?.value || '').trim();
    const status = document.getElementById('pao-filter-status')?.value || 'all';
    const bram = document.getElementById('pao-filter-bram')?.value || 'all';
    const cat = document.getElementById('pao-filter-cat')?.value || 'all';
    const sort = document.getElementById('pao-filter-sort')?.value || 'id_desc';
    const perPage = document.getElementById('pao-filter-per-page')?.value || '100';
    const page = pageOverride ? Number(pageOverride) : 1;

    const params = new URLSearchParams();
    if (q !== '') params.set('q', q);
    if (status !== 'all') params.set('status', status);
    if (bram !== 'all') params.set('bram', bram);
    if (cat !== 'all') params.set('cat', cat);
    if (sort !== 'id_desc') params.set('sort', sort);
    if (perPage !== '100') params.set('per_page', perPage);
    if (page > 1) params.set('page', String(page));

    const qs = params.toString();
    const targetHash = `#/photo-albums-online${qs ? '?' + qs : ''}`;
    if (window.location.hash === targetHash) {
      this.handleRoute();
    } else {
      window.location.hash = targetHash;
    }
  },

  resetPhotoAlbumsOnlineFilters() {
    window.location.hash = '#/photo-albums-online';
  },

  goToPhotoAlbumsOnlinePage(page) {
    this.applyPhotoAlbumsOnlineFilters(page);
  },

  quickFilterPhotoAlbumsOnlineTable(query) {
    const q = String(query || '').trim().toLowerCase();
    document.querySelectorAll('.pao-album-row').forEach(row => {
      const hay = row.getAttribute('data-search') || '';
      row.classList.toggle('d-none', q !== '' && !hay.includes(q));
    });
  },

  async recalcOnlineAlbumsSize() {
    const btn = document.getElementById('pao-recalc-size-btn');
    const sizeTextEl = document.getElementById('pao-storage-size-text');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Berechne...';
    }

    try {
      const formData = new FormData();
      formData.append('_csrf', this.csrfToken);

      const res = await fetch(`${this.basePath}/api.php?route=photo-albums-online/recalc-size`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Speicherplatz konnte nicht berechnet werden.', 'danger');
        return;
      }

      this.resetLogoutCountdown();
      if (sizeTextEl && data.formatted) {
        sizeTextEl.textContent = data.formatted;
      }
      this.toast(data.message || 'Speicherplatz der Online-Fotoalben neu berechnet.', 'success');
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-hdd-stack me-1"></i> Speichergröße neu berechnen';
      }
    }
  },

  async submitPhotoAlbumOnlineSave(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('pao-edit-form');
    const btn = document.getElementById('pao-save-btn');
    if (!form) return false;

    const statusSel = document.getElementById('pao-status-select');
    if (statusSel && statusSel.value === 'deleted') {
      const ok = window.confirm('Möchtest du dieses Fotoalbum wirklich auf „Löschen“ setzen? Alle Bilddateien auf dem Server werden unwiderruflich gelöscht und das Album wird aus photo_albums_online entfernt!');
      if (!ok) return false;
    }

    const formData = new FormData(form);
    formData.append('_csrf', this.csrfToken);

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Speichere Änderungen...';
    }

    try {
      const res = await fetch(`${this.basePath}/api.php?route=photo-albums-online/save`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Änderungen konnten nicht gespeichert werden.', 'danger');
        return false;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'Änderungen am Online-Fotoalbum wurden erfolgreich gespeichert.', 'success');
      this.refreshSidebarCounts();

      if (data.deleted) {
        window.location.hash = '#/photo-albums-online';
      } else {
        this.handleRoute();
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-floppy-fill me-1"></i> Änderungen speichern';
      }
    }
    return false;
  },

  // ========================================================================
  // 4. Dashboard: Gemeinsame Notizen & Persönliche Notizen (AJAX)
  // ========================================================================
  // ========================================================================
  // 3d. Drehpartner & ID-Check (CoActorsChecking)
  // ========================================================================
  setCoActorsStatusFilter(statusKey) {
    const statusInput = document.getElementById('cac-filter-status');
    if (statusInput) {
      statusInput.value = statusKey || 'under_review';
    }
    this.applyCoActorsFilters(statusKey);
  },

  applyCoActorsFilters(statusOverride) {
    const status = statusOverride || document.getElementById('cac-filter-status')?.value || 'under_review';
    const role = document.getElementById('cac-filter-role')?.value || 'all';
    const method = document.getElementById('cac-filter-method')?.value || 'all';
    const sort = document.getElementById('cac-filter-sort')?.value || 'priority';
    const q = (document.getElementById('cac-filter-q')?.value || '').trim();

    const params = new URLSearchParams();
    if (status !== 'under_review') params.set('status', status);
    if (role !== 'all') params.set('role', role);
    if (method !== 'all') params.set('method', method);
    if (sort !== 'priority') params.set('sort', sort);
    if (q !== '') params.set('q', q);

    const qs = params.toString();
    const targetHash = `#/co-actors-checking${qs ? '?' + qs : ''}`;
    if (window.location.hash === targetHash) {
      this.handleRoute();
    } else {
      window.location.hash = targetHash;
    }
  },

  resetCoActorsFilters() {
    window.location.hash = '#/co-actors-checking';
  },

  quickFilterCoActorsTable(query) {
    const q = String(query || '').trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('.cac-actor-row').forEach(row => {
      const hay = row.getAttribute('data-search') || '';
      const show = (q === '' || hay.includes(q));
      row.classList.toggle('d-none', !show);
      if (show) visible++;
    });
    const cntEl = document.getElementById('cac-visible-count');
    if (cntEl) cntEl.textContent = String(visible);
  },

  getCoActorPayload(coActorId) {
    const row = document.getElementById(`cac-row-${coActorId}`);
    if (!row) return null;
    try {
      return JSON.parse(row.getAttribute('data-actor') || '{}');
    } catch (_) {
      return null;
    }
  },

  async coActorQuickAction(coActorId, actionType, reasonText = '') {
    const formData = new FormData();
    formData.append('_csrf', this.csrfToken);
    formData.append('co_actor_id', String(coActorId));
    formData.append('action_type', actionType);
    if (actionType === 'reject') {
      formData.append('rejection_reason', reasonText);
    }

    try {
      const res = await fetch(`${this.basePath}/api.php?route=co-actors-checking/action`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Aktion konnte nicht ausgeführt werden.', 'danger');
        return false;
      }

      this.resetLogoutCountdown();
      const toastType = actionType === 'verify' ? 'success' : (actionType === 'reject' ? 'warning' : 'info');
      this.toast(data.message || 'Drehpartner-Status aktualisiert.', toastType);
      this.refreshSidebarCounts();

      const badgeEl = document.getElementById(`cac-status-badge-${coActorId}`);
      const auditEl = document.getElementById(`cac-audit-info-${coActorId}`);
      const rejBox = document.getElementById(`cac-reject-box-${coActorId}`);
      const rejTxt = document.getElementById(`cac-reject-text-${coActorId}`);
      const btnVerify = document.getElementById(`cac-btn-verify-${coActorId}`);
      const btnReject = document.getElementById(`cac-btn-reject-${coActorId}`);
      const btnReset = document.getElementById(`cac-btn-reset-${coActorId}`);
      const rowEl = document.getElementById(`cac-row-${coActorId}`);

      const newStatus = data.checked_status || actionType;
      if (badgeEl) {
        let cls = 'badge-kkc-info';
        if (newStatus === 'verified') cls = 'badge-kkc-success';
        else if (newStatus === 'rejected') cls = 'badge-kkc-danger';
        badgeEl.className = `badge-kkc ${cls}`;
        badgeEl.textContent = data.status_label || newStatus;
      }

      if (auditEl && data.checked_by && data.checked_on_de) {
        auditEl.innerHTML = `Geprüft von <strong>${this.escapeHtml(data.checked_by)}</strong> am ${this.escapeHtml(data.checked_on_de)}`;
      }

      if (rejBox && rejTxt) {
        if (newStatus === 'rejected' && data.rejection_reason) {
          rejTxt.textContent = data.rejection_reason;
          rejBox.classList.remove('d-none');
        } else {
          rejBox.classList.add('d-none');
        }
      }

      if (btnVerify) btnVerify.classList.toggle('d-none', newStatus === 'verified');
      if (btnReject) btnReject.classList.toggle('d-none', newStatus === 'rejected');
      if (btnReset) btnReset.classList.toggle('d-none', !(newStatus === 'verified' || newStatus === 'rejected'));
      if (rowEl) {
        rowEl.setAttribute('data-status', newStatus);
        rowEl.style.background = newStatus === 'under_review' ? 'rgba(245, 158, 11, 0.04)' : '';
      }

      const inspModalEl = document.getElementById('cacInspectorModal');
      if (inspModalEl && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getInstance(inspModalEl)?.hide();
      }
      return true;
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
      return false;
    }
  },

  openCoActorInspectorModal(coActorId) {
    const actor = this.getCoActorPayload(coActorId);
    const modalEl = document.getElementById('cacInspectorModal');
    if (!actor || !modalEl) return;

    document.getElementById('cacInspectorTitle').textContent = `18+ ID-Check & Dokumenten-Inspektor – ${actor.display_label}`;
    document.getElementById('cacInspectorSubtitle').textContent = `Referenz: ${actor.ref_code} (ID #${actor.id}, Merchant #${actor.merchant_id})`;

    document.getElementById('cacInspName').textContent = actor.real_full_name || '-';
    document.getElementById('cacInspBirth').textContent = (actor.birth_date_de || '-') + (actor.age ? ` (${actor.age} J.)` : '');
    document.getElementById('cacInspDoc').textContent = `${actor.id_doc_type_label || 'Ausweis'}: ${actor.id_doc_number || '-'}`;
    const validEl = document.getElementById('cacInspValid');
    if (validEl) {
      validEl.textContent = actor.id_doc_valid_label || '-';
      validEl.className = `fs-6 ${actor.is_doc_expired ? 'text-danger' : ''}`;
    }
    document.getElementById('cacInspAddress').textContent = actor.address_text || '-';

    const certBtn = document.getElementById('cacInspCertBtn');
    if (certBtn) {
      if (actor.certificate_url) {
        certBtn.href = actor.certificate_url;
        certBtn.classList.remove('d-none');
      } else {
        certBtn.classList.add('d-none');
      }
    }

    const verifyBtn = document.getElementById('cacInspectorVerifyBtn');
    const rejectBtn = document.getElementById('cacInspectorRejectBtn');
    if (verifyBtn) {
      verifyBtn.onclick = () => this.coActorQuickAction(actor.id, 'verify');
    }
    if (rejectBtn) {
      rejectBtn.onclick = () => {
        if (window.bootstrap?.Modal) {
          window.bootstrap.Modal.getInstance(modalEl)?.hide();
        }
        this.openCoActorRejectModal(actor.id);
      };
    }

    const cards = [
      { url: actor.face_ref_url, title: '1. KI-Referenz-Selfie (Frontal)', badge: 'bg-info text-dark' },
      { url: actor.id_shot_url, title: '2. ID-Shot (Gesicht + Ausweis)', badge: 'bg-primary' },
      { url: actor.id_front_url, title: '3. Ausweisdokument Vorderseite', badge: 'bg-secondary' },
      { url: actor.id_back_url, title: '4. Ausweisdokument Rückseite', badge: 'bg-secondary' }
    ];

    const gridEl = document.getElementById('cacInspectorGrid');
    if (gridEl) {
      gridEl.innerHTML = cards.map(c => {
        if (!c.url) {
          return `
            <div class="col-md-6 col-xl-3">
              <div class="p-3 rounded border h-100 d-flex flex-column align-items-center justify-content-center text-muted text-center" style="min-height: 240px; background: var(--kkc-surface-hover);">
                <i class="bi bi-image fs-2 mb-2 opacity-50"></i>
                <div class="small fw-semibold">${this.escapeHtml(c.title)}</div>
                <small>Nicht vorhanden</small>
              </div>
            </div>
          `;
        }
        return `
          <div class="col-md-6 col-xl-3">
            <div class="p-2 rounded border h-100 d-flex flex-column" style="background: var(--kkc-surface-hover);">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="badge ${c.badge}">${this.escapeHtml(c.title)}</span>
                <a href="${this.escapeHtml(c.url)}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-kkc py-0 px-1.5" title="In neuem Tab öffnen">
                  <i class="bi bi-box-arrow-up-right"></i>
                </a>
              </div>
              <div class="rounded overflow-hidden bg-dark flex-grow-1 d-flex align-items-center justify-content-center" style="min-height: 260px;">
                <a href="${this.escapeHtml(c.url)}" target="_blank" rel="noopener" class="w-100 h-100 d-block">
                  <img src="${this.escapeHtml(c.url)}" alt="${this.escapeHtml(c.title)}" class="w-100 h-100" style="object-fit: contain; max-height: 340px;">
                </a>
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  },

  openCoActorRejectModal(coActorId) {
    const actor = this.getCoActorPayload(coActorId);
    const modalEl = document.getElementById('cacRejectModal');
    if (!modalEl) return;

    document.getElementById('cacRejectActorId').value = String(coActorId);
    document.getElementById('cacRejectActorLabel').textContent = actor ? `${actor.display_label} (${actor.ref_code})` : `#${coActorId}`;
    const tplSel = document.getElementById('cacRejectTemplateSelect');
    if (tplSel) tplSel.value = '';
    const reasonInput = document.getElementById('cacRejectReasonInput');
    if (reasonInput) {
      reasonInput.value = actor?.rejection_reason || 'Der Personalausweis ist auf dem ID-Shot leider nicht scharf genug lesbar. Bitte nimm den ID-Shot per Smartphone-QR-Code bei guter Beleuchtung erneut auf.';
    }

    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  },

  async submitCoActorRejectModal(event) {
    if (event) event.preventDefault();
    const coActorId = parseInt(document.getElementById('cacRejectActorId')?.value || '0', 10);
    const reason = (document.getElementById('cacRejectReasonInput')?.value || '').trim();
    const btn = document.getElementById('cacRejectSubmitBtn');
    if (!coActorId || !reason) {
      this.toast('Bitte gib einen Ablehnungsgrund an.', 'warning');
      return false;
    }

    if (btn) btn.disabled = true;
    const ok = await this.coActorQuickAction(coActorId, 'reject', reason);
    if (btn) btn.disabled = false;

    if (ok) {
      const modalEl = document.getElementById('cacRejectModal');
      if (modalEl && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getInstance(modalEl)?.hide();
      }
    }
    return false;
  },

  openCoActorEditModal(coActorId) {
    const actor = this.getCoActorPayload(coActorId);
    const modalEl = document.getElementById('cacEditDataModal');
    if (!actor || !modalEl) return;

    document.getElementById('cacEditActorId').value = String(actor.id);
    document.getElementById('cacEditCreatorName').value = actor.creator_name || '';
    document.getElementById('cacEditFirstName').value = actor.first_name || '';
    document.getElementById('cacEditLastName').value = actor.last_name || '';
    document.getElementById('cacEditBirthDate').value = (actor.birth_date && !actor.birth_date.startsWith('0000')) ? actor.birth_date : '';
    document.getElementById('cacEditDocType').value = actor.id_doc_type || 'id_card';
    document.getElementById('cacEditDocNumber').value = actor.id_doc_number || '';
    document.getElementById('cacEditDocValid').value = (actor.id_doc_valid_until && !actor.id_doc_valid_until.startsWith('0000')) ? actor.id_doc_valid_until : '';
    document.getElementById('cacEditStreet').value = actor.street || '';
    document.getElementById('cacEditZip').value = actor.zip_code || '';
    document.getElementById('cacEditCity').value = actor.city || '';
    document.getElementById('cacEditCountry').value = actor.country || 'DE';

    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  },

  async submitCoActorEditData(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('cacEditDataForm');
    const btn = document.getElementById('cacEditSubmitBtn');
    if (!form) return false;

    const formData = new FormData(form);
    formData.append('_csrf', this.csrfToken);

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Speichere...';
    }

    try {
      const res = await fetch(`${this.basePath}/api.php?route=co-actors-checking/update-data`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Daten konnten nicht gespeichert werden.', 'danger');
        return false;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'Ausweis- und Personendaten wurden aktualisiert.', 'success');

      const modalEl = document.getElementById('cacEditDataModal');
      if (modalEl && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getInstance(modalEl)?.hide();
      }
      this.handleRoute();
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-floppy-fill me-1"></i> Änderungen verschlüsselt speichern';
      }
    }
    return false;
  },

  async openCoActorQrModal(coActorId, regenerate = false) {
    const modalEl = document.getElementById('cacQrModal');
    if (!modalEl) return;

    const svgBox = document.getElementById('cacQrSvgBox');
    const urlInput = document.getElementById('cacQrUrlInput');
    const labelEl = document.getElementById('cacQrActorLabel');
    const regenBtn = document.getElementById('cacQrRegenBtn');

    if (svgBox) svgBox.innerHTML = '<div class="spinner-border ec-text-primary m-4" role="status"></div>';
    if (regenBtn) {
      regenBtn.onclick = () => this.openCoActorQrModal(coActorId, true);
    }

    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    try {
      const formData = new FormData();
      formData.append('_csrf', this.csrfToken);
      formData.append('co_actor_id', String(coActorId));
      formData.append('action_type', regenerate ? 'regenerate_qr' : 'get_qr');

      const res = await fetch(`${this.basePath}/api.php?route=co-actors-checking/action`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'QR-Code konnte nicht geladen werden.', 'danger');
        return;
      }

      if (labelEl) labelEl.textContent = `${data.display_label} (${data.ref_code})`;
      if (svgBox) svgBox.innerHTML = data.qr_svg || '';
      if (urlInput) urlInput.value = data.qr_verify_url || '';
      if (regenerate) {
        this.toast(data.message || 'Neuer Smartphone-QR-Link erzeugt.', 'success');
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    }
  },

  async adminNotesAction(action) {
    const textarea = document.getElementById('input-admin-notes');
    const actionsWrap = document.getElementById('admin-notes-actions');
    const badgeEl = document.getElementById('admin-notes-status-badge');
    if (!textarea || !actionsWrap) return;

    const formData = new FormData();
    formData.append('_csrf', this.csrfToken);
    formData.append('action', action);
    if (action === 'save') {
      formData.append('admin_notes', textarea.value);
    }

    try {
      const res = await fetch(`${this.basePath}/api.php?route=home/admin-notes`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (!res.ok || data.status !== 'ok') {
        this.toast(data.message || 'Aktion konnte nicht ausgeführt werden.', 'danger');
        return;
      }

      this.resetLogoutCountdown();
      this.toast(data.message || 'Aktualisiert.', 'success');

      if (action === 'lock') {
        textarea.removeAttribute('readonly');
        textarea.focus();
        if (badgeEl) {
          badgeEl.className = 'badge-kkc badge-kkc-info';
          badgeEl.innerHTML = '<i class="bi bi-pencil-square"></i> Du bearbeitest gerade';
        }
        actionsWrap.innerHTML = `
          <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.adminNotesAction('cancel')">
            <i class="bi bi-x-lg me-1"></i> Abbrechen
          </button>
          <button type="button" class="btn btn-sm btn-primary-kkc" onclick="App.adminNotesAction('save')">
            <i class="bi bi-check2-circle me-1"></i> Speichern
          </button>
        `;
      } else {
        if (typeof data.admin_notes === 'string') {
          textarea.value = data.admin_notes;
        }
        textarea.setAttribute('readonly', 'readonly');
        if (badgeEl) {
          badgeEl.className = 'badge-kkc badge-kkc-neutral';
          badgeEl.innerHTML = '<i class="bi bi-lock"></i> Schreibgeschützt';
        }
        actionsWrap.innerHTML = `
          <button type="button" class="btn btn-sm btn-primary-kkc" onclick="App.adminNotesAction('lock')">
            <i class="bi bi-pencil-square me-1"></i> Bearbeiten
          </button>
        `;
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    }
  },

  async saveEmployeeNotes(event) {
    if (event) event.preventDefault();
    const textarea = document.getElementById('input-employee-notes');
    const btn = document.getElementById('btn-save-employee-notes');
    if (!textarea) return false;

    if (btn) btn.disabled = true;

    try {
      const formData = new FormData();
      formData.append('_csrf', this.csrfToken);
      formData.append('employee_notes', textarea.value);

      const res = await fetch(`${this.basePath}/api.php?route=home/employee-notes`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': this.csrfToken },
        body: formData
      });
      const data = await res.json();

      if (res.ok && data.status === 'ok') {
        this.resetLogoutCountdown();
        this.toast(data.message || 'Deine persönlichen Notizen wurden gespeichert.', 'success');
      } else {
        this.toast(data.message || 'Fehler beim Speichern.', 'danger');
      }
    } catch (err) {
      this.toast('Verbindungsfehler: ' + err.message, 'danger');
    } finally {
      if (btn) btn.disabled = false;
    }

    return false;
  },

  // ========================================================================
  // 5. Dashboard: Echtzeit-Filter für Letzte 200 Aktionen (employee_history)
  // ========================================================================
  initHistoryTableFilter() {
    const searchInput = document.getElementById('acp-history-search');
    const limitSelect = document.getElementById('acp-history-limit');
    const table = document.getElementById('acp-history-table');
    const infoEl = document.getElementById('acp-history-info');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('.acp-history-row'));
    if (rows.length === 0) return;

    const applyFilter = () => {
      const q = (searchInput?.value || '').trim().toLowerCase();
      const limit = parseInt(limitSelect?.value || '10', 10) || 10;
      let matched = 0;
      let visible = 0;

      rows.forEach(row => {
        const haystack = row.getAttribute('data-search') || '';
        if (q === '' || haystack.includes(q)) {
          matched++;
          if (visible < limit) {
            row.classList.remove('d-none');
            visible++;
          } else {
            row.classList.add('d-none');
          }
        } else {
          row.classList.add('d-none');
        }
      });

      if (infoEl) {
        infoEl.textContent = `Zeige ${visible} von ${matched} passenden Einträgen`;
      }
    };

    if (searchInput) {
      searchInput.addEventListener('input', applyFilter);
    }
    if (limitSelect) {
      limitSelect.addEventListener('change', applyFilter);
    }
    applyFilter();
  },

  // ========================================================================
  // 6. Globale Admin-Schnellsuche in der Topbar
  // ========================================================================
  hideGlobalSearchDropdown() {
    const dropdown = document.getElementById('global-search-dropdown');
    if (dropdown) {
      dropdown.classList.add('d-none');
    }
  },

  initGlobalTopbarSearch() {
    const wrapper = document.getElementById('global-search-wrapper');
    const input = document.getElementById('global-search-input');
    const clearBtn = document.getElementById('global-search-clear');
    const dropdown = document.getElementById('global-search-dropdown');
    if (!input || !dropdown) return;

    const renderResults = (items, query) => {
      if (!Array.isArray(items) || items.length === 0) {
        dropdown.innerHTML = `
          <div class="p-3 text-center text-muted small">
            Keine Treffer für „<strong>${this.escapeHtml(query)}</strong>“ gefunden.
          </div>
        `;
        dropdown.classList.remove('d-none');
        return;
      }

      const badgeColorMap = {
        actor: 'bg-primary bg-opacity-10 text-primary border-primary',
        merchant: 'bg-success bg-opacity-10 text-success border-success',
        movie: 'bg-info bg-opacity-10 text-info border-info',
        site: 'bg-secondary bg-opacity-10 text-secondary border-secondary'
      };

      const rowsHtml = items.map(item => {
        const bCls = badgeColorMap[item.type] || 'bg-secondary bg-opacity-10 text-secondary';
        return `
          <a href="${this.escapeHtml(item.route)}" class="d-flex align-items-center justify-content-between gap-2 px-3 py-2 text-decoration-none border-bottom ec-search-item" style="border-color: var(--kkc-border) !important;">
            <div class="d-flex align-items-center gap-2 overflow-hidden">
              <span class="badge ${bCls} border border-opacity-25 flex-shrink-0">
                <i class="bi ${this.escapeHtml(item.icon)} me-1"></i>${this.escapeHtml(item.type_label)}
              </span>
              <div class="text-truncate">
                <span class="fw-semibold text-body">${this.escapeHtml(item.title)}</span>
                <span class="text-muted small font-monospace ms-1">#${parseInt(item.id, 10) || 0}</span>
                <div class="text-muted" style="font-size: 0.72rem;">${this.escapeHtml(item.subtitle || '')}</div>
              </div>
            </div>
            <span class="badge-kkc badge-kkc-neutral flex-shrink-0">${this.escapeHtml(item.status_label || '')}</span>
          </a>
        `;
      }).join('');

      dropdown.innerHTML = `<div class="py-1">${rowsHtml}</div>`;
      dropdown.classList.remove('d-none');

      dropdown.querySelectorAll('.ec-search-item').forEach(link => {
        link.addEventListener('click', () => {
          this.hideGlobalSearchDropdown();
        });
      });
    };

    input.addEventListener('input', () => {
      const q = input.value.trim();
      if (clearBtn) {
        clearBtn.classList.toggle('d-none', q === '');
      }
      if (this.searchDebounceTimer) {
        clearTimeout(this.searchDebounceTimer);
      }
      if (q.length < 2 && !/^#?\d+$/.test(q)) {
        this.hideGlobalSearchDropdown();
        return;
      }

      this.searchDebounceTimer = setTimeout(async () => {
        try {
          const res = await fetch(`${this.basePath}/api.php?route=home/global-search&q=${encodeURIComponent(q)}`);
          const data = await res.json();
          if (res.ok && data.status === 'ok') {
            renderResults(data.items || [], q);
          }
        } catch (_) {}
      }, 180);
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        input.value = '';
        clearBtn.classList.add('d-none');
        this.hideGlobalSearchDropdown();
        input.focus();
      });
    }

    document.addEventListener('click', (e) => {
      if (wrapper && !wrapper.contains(e.target)) {
        this.hideGlobalSearchDropdown();
      }
    });
  },

  // ========================================================================
  // 7. Hilfsfunktionen (XSS-Escaping & Toast-Benachrichtigungen)
  // ========================================================================
  escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  },

  toast(message, type = 'info') {
    let container = document.getElementById('ec-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'ec-toast-container';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '1090';
      document.body.appendChild(container);
    }

    const iconMap = {
      success: 'bi-check-circle-fill text-success',
      danger: 'bi-exclamation-octagon-fill text-danger',
      warning: 'bi-exclamation-triangle-fill text-warning',
      info: 'bi-info-circle-fill ec-text-primary'
    };
    const iconCls = iconMap[type] || iconMap.info;

    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center shadow-lg border';
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2">
          <i class="bi ${iconCls} fs-5"></i>
          <span>${this.escapeHtml(message)}</span>
        </div>
        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Schließen"></button>
      </div>
    `;

    container.appendChild(toastEl);
    if (window.bootstrap?.Toast) {
      const bsToast = new window.bootstrap.Toast(toastEl, { delay: 3500 });
      bsToast.show();
      toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    } else {
      setTimeout(() => toastEl.remove(), 3500);
    }
  },

  // ========================================================================
  // 8. KI-Analyse & BRAM-Compliance (#/ai-compliance)
  // ========================================================================
  AiCompliance: {
    currentMovieId: 0,
    liveBatchRunning: false,
    batchStepTimer: null,
    batchStepElapsedSec: 0,
    batchAnnouncedMovieId: 0,
    lastInspectorData: null,

    ensureCurrentMovieId() {
      if (this.currentMovieId > 0) return this.currentMovieId;
      const inp = document.getElementById('aic-input-movie-id');
      if (inp && parseInt(inp.value, 10) > 0) {
        this.currentMovieId = parseInt(inp.value, 10);
      } else if (window.__aicInitialMovie && window.__aicInitialMovie.id) {
        this.currentMovieId = parseInt(window.__aicInitialMovie.id, 10);
      }
      return this.currentMovieId;
    },

    async callAjax(params = {}, method = 'GET') {
      const url = new URL(`${App.basePath}/api.php`, window.location.origin);
      url.searchParams.set('route', 'ai-compliance/ajax');

      const opts = {
        method,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': App.csrfToken
        }
      };

      if (method === 'GET') {
        Object.entries(params).forEach(([k, v]) => {
          if (v !== undefined && v !== null) url.searchParams.set(k, String(v));
        });
      } else {
        const fd = new FormData();
        Object.entries(params).forEach(([k, v]) => {
          if (v !== undefined && v !== null) fd.append(k, String(v));
        });
        opts.body = fd;
      }

      const res = await fetch(url.toString(), opts);
      return await res.json();
    },

    setText(id, val) {
      const el = document.getElementById(id);
      if (el) el.textContent = String(val ?? '');
    },

    setHtml(id, html) {
      const el = document.getElementById(id);
      if (el) el.innerHTML = html;
    },

    activateTab(tabBtnId) {
      const btn = document.getElementById(tabBtnId);
      if (btn && window.bootstrap?.Tab) {
        window.bootstrap.Tab.getOrCreateInstance(btn).show();
      } else if (btn) {
        btn.click();
      }
    },

    renderComplianceBadge(status, score) {
      const s = score !== undefined ? ` (${score}/100)` : '';
      if (status === 'ok') {
        return `<span class="badge bg-success">OK / Unbedenklich${s}</span>`;
      } else if (status === 'warning') {
        return `<span class="badge bg-warning text-dark">Gelb / Ohne KK${s}</span>`;
      } else if (status === 'violation') {
        return `<span class="badge bg-danger">BRAM-Verstoß${s}</span>`;
      }
      return `<span class="badge bg-secondary">Noch ungeprüft</span>`;
    },

    updateStatsUI(stats, workerStatus, batchCfg) {
      if (stats) {
        this.setText('aic-stat-total', stats.total_converted);
        this.setText('aic-stat-subtitled', stats.total_subtitled);
        this.setText('aic-stat-sub-pct', stats.pct_subtitles);
        this.setText('aic-stat-sub-rem', stats.remaining_subtitles);
        this.setText('aic-stat-checked', stats.total_text_checked);
        this.setText('aic-stat-txt-pct', stats.pct_text);
        this.setText('aic-stat-txt-rem', stats.remaining_text);
        this.setText('aic-stat-ok', stats.cnt_ok);
        this.setText('aic-stat-warn', stats.cnt_warn);
        this.setText('aic-stat-viol', stats.cnt_viol);

        this.setText('aic-prog-sub-text', `${stats.sub_processed_total} / ${stats.total_converted} Videos (${stats.pct_subtitles}%)`);
        const subBar = document.getElementById('aic-prog-sub-bar');
        if (subBar) subBar.style.width = `${stats.pct_subtitles}%`;
        this.setText('aic-prog-sub-label', `${stats.pct_subtitles}%`);
        this.setText('aic-det-sub-ok', stats.sub_created);
        this.setText('aic-det-sub-noaudio', stats.sub_no_audio);
        this.setText('aic-det-sub-err', stats.sub_error);
        this.setText('aic-det-sub-rem', stats.remaining_subtitles);

        this.setText('aic-prog-txt-text', `${stats.total_text_checked} / ${stats.total_converted} Videos (${stats.pct_text}%)`);
        const txtBar = document.getElementById('aic-prog-txt-bar');
        if (txtBar) txtBar.style.width = `${stats.pct_text}%`;
        this.setText('aic-prog-txt-label', `${stats.pct_text}%`);
      }

      if (workerStatus) {
        this.setText('aic-worker-last-msg', workerStatus.last_message || '-');
        this.setText('aic-worker-last-time', workerStatus.last_run_at || '-');
        if (workerStatus.state === 'running') {
          this.setHtml('aic-worker-live-badge', `<span class="badge bg-success">Verarbeitet Video #${workerStatus.current_movie_id}</span>`);
        } else if (workerStatus.state === 'waiting_for_ffmpeg') {
          this.setHtml('aic-worker-live-badge', `<span class="badge bg-warning text-dark">Wartet auf Video-Konvertierung</span>`);
        } else {
          this.setHtml('aic-worker-live-badge', `<span class="badge bg-primary">Bereit (Durchläufe: ${workerStatus.total_processed_runs || 0})</span>`);
        }
      }

      if (batchCfg) {
        const cronBadge = document.getElementById('aic-env-cron');
        if (cronBadge) {
          if (batchCfg.cron_enabled) {
            cronBadge.className = 'badge bg-success';
            cronBadge.textContent = 'Hintergrund-Cronjob: Aktiv';
          } else {
            cronBadge.className = 'badge bg-secondary';
            cronBadge.textContent = 'Hintergrund-Cronjob: Pausiert';
          }
        }
      }
    },

    async reloadDashboard() {
      try {
        const res = await this.callAjax({ action: 'init_dashboard' }, 'GET');
        if (!res || res.status !== 'ok') {
          App.toast('Fehler beim Aktualisieren des Dashboards.', 'danger');
          return;
        }
        this.updateStatsUI(res.stats, res.worker_status, res.batch_config);
        this.renderHistoryTable(res.history || []);
        if (res.rules && res.rules.parsed_categories) {
          this.renderRuleCategories(res.rules.parsed_categories);
        }
        App.toast('KI- & BRAM-Dashboard aktualisiert.', 'success');
      } catch (e) {
        App.toast('Netzwerkfehler beim Aktualisieren.', 'danger');
      }
    },

    displayMovieInLab(m) {
      if (!m) return;
      this.currentMovieId = m.id;
      const wrap = document.getElementById('aic-loaded-movie-wrap');
      if (wrap) wrap.style.display = '';

      const inpMid = document.getElementById('aic-input-movie-id');
      if (inpMid) inpMid.value = m.id;
      const selRec = document.getElementById('aic-select-recent');
      if (selRec) selRec.value = String(m.id);

      this.setText('aic-m-title-head', m.title);
      this.setText('aic-m-id', m.id);
      this.setText('aic-m-merchant', m.merchant_id);
      this.setText('aic-m-duration', `${m.playtime_fmt} (${m.playtime_seconds} Sek.)`);
      this.setText('aic-m-filepath', m.mp4_path);

      const chkLink = document.getElementById('aic-m-check-link');
      if (chkLink) chkLink.setAttribute('href', `#/movie-checking?id=${m.id}`);
      const edtLink = document.getElementById('aic-m-edit-link');
      if (edtLink) edtLink.setAttribute('href', `#/movie-edit?id=${m.id}`);

      if (m.mp4_exists) {
        this.setHtml('aic-m-mp4-status', `<span class="badge bg-success">Vorhanden (${m.mp4_size_mb} MB)</span>`);
      } else {
        this.setHtml('aic-m-mp4-status', `<span class="badge bg-info text-dark">Lokale DB-Ansicht (MP4 auf Live-Server)</span>`);
      }

      if (m.is_online) {
        this.setHtml('aic-m-online-status', `<span class="badge bg-success">Online</span>`);
      } else {
        this.setHtml('aic-m-online-status', `<span class="badge bg-secondary">Status: ${App.escapeHtml(m.status)} (Released: ${m.released})</span>`);
      }

      const edtTitle = document.getElementById('aic-edit-title');
      if (edtTitle) edtTitle.value = m.title || '';
      const edtDesc = document.getElementById('aic-edit-desc');
      if (edtDesc) edtDesc.value = m.description || '';
      this.setText('aic-m-meta-title', m.meta_title || '-');
      this.setText('aic-m-meta-desc', m.meta_description || '-');

      const edtTrans = document.getElementById('aic-edit-transcript');
      const outVtt = document.getElementById('aic-out-vtt');

      if (m.ai && m.ai.subtitle_status === 2) {
        this.setHtml('aic-step1-badge-wrap', `<span class="badge bg-success">Transkript &amp; .vtt vorhanden (${m.ai.whisper_duration_sec} Sek.)</span>`);
        this.setText('aic-out-lang', `${String(m.ai.detected_language || '').toUpperCase()} (${m.ai.language_confidence} %)`);
        this.setText('aic-out-subfile', m.ai.subtitle_filename || '-');
        if (edtTrans) edtTrans.value = m.ai.spoken_transcript || '';
        if (outVtt) outVtt.value = m.ai.vtt_preview || '';
      } else if (m.ai && m.ai.subtitle_status === 3) {
        this.setHtml('aic-step1-badge-wrap', `<span class="badge bg-secondary">Stummfilm (Keine Tonspur im Video)</span>`);
        this.setText('aic-out-lang', '-');
        this.setText('aic-out-subfile', '-');
        if (edtTrans) edtTrans.value = m.ai.spoken_transcript || '';
        if (outVtt) outVtt.value = '';
      } else {
        this.setHtml('aic-step1-badge-wrap', `<span class="badge bg-secondary">Noch kein Untertitel / Audio-Transkript erstellt</span>`);
        this.setText('aic-out-lang', 'Noch nicht ermittelt');
        this.setText('aic-out-subfile', m.ai ? (m.ai.subtitle_filename || '-') : '-');
        if (edtTrans) edtTrans.value = '';
        if (outVtt) outVtt.value = '';
      }

      if (m.ai && m.ai.text_check_status === 2) {
        this.renderComplianceReport({
          compliance_status: m.ai.text_compliance,
          risk_score: m.ai.text_risk_score,
          engine_used: m.ai.text_engine_used,
          summary_de: m.ai.text_summary,
          violations: m.ai.violations,
          duration_sec: m.ai.text_duration_sec
        });
      } else {
        const repBox = document.getElementById('aic-report-box');
        if (repBox) repBox.style.display = 'none';
      }
    },

    renderComplianceReport(rep) {
      const repBox = document.getElementById('aic-report-box');
      if (repBox) repBox.style.display = '';

      const banner = document.getElementById('aic-report-banner');
      if (banner) {
        banner.className = 'alert d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 ';
        if (rep.compliance_status === 'ok') {
          banner.className += 'alert-success';
          this.setHtml('aic-report-status-title', '&#x2705; FREIGABE-EMPFEHLUNG: UNBEDENKLICH (KEINE BRAM-VERSTÖSSE ERKANNT)');
        } else if (rep.compliance_status === 'warning') {
          banner.className += 'alert-warning';
          this.setHtml('aic-report-status-title', '&#x26A0; PRÜFHINWEIS (GELB): NUR AUF PORTALEN OHNE KREDITKARTE ZULÄSSIG / MANUELLE SICHTUNG');
        } else {
          banner.className += 'alert-danger';
          this.setHtml('aic-report-status-title', '&#x1F6D1; STOPP (ROT): KRITISCHER VISA/MASTERCARD BRAM- ODER RECHTS-VERSTOSS ERKANNT!');
        }
      }

      this.setText('aic-report-summary', rep.summary_de || '');
      this.setText('aic-report-score', rep.risk_score || 0);
      this.setText('aic-report-engine', `${rep.engine_used || 'BRAM-Engine'} (${rep.duration_sec || 0} Sek.)`);

      const viols = rep.violations || [];
      if (!viols.length) {
        this.setHtml(
          'aic-tbody-violations',
          '<tr><td colspan="5" class="text-center text-success fw-semibold py-3">&#x2714; Es wurden in keiner Textquelle (Titel, Beschreibung, SEO, gesprochenes Video-Audio) verbotene Begriffe oder Verstöße gefunden.</td></tr>'
        );
        return;
      }

      let html = '';
      viols.forEach(v => {
        const sevBadge = v.severity === 'violation'
          ? '<span class="badge bg-danger">VERSTOSS (ROT)</span>'
          : '<span class="badge bg-warning text-dark">HINWEIS (GELB)</span>';
        const tsInfo = (v.timestamp && v.timestamp !== '-')
          ? `<br><strong>Zeit im Video: <code>${App.escapeHtml(v.timestamp)}</code></strong>`
          : '';
        html += `<tr>
          <td>${sevBadge}</td>
          <td><strong>${App.escapeHtml(v.category)}</strong></td>
          <td>${App.escapeHtml(v.source_label || v.source)}${tsInfo}</td>
          <td><code class="text-danger fw-bold">${App.escapeHtml(v.matched_term || '-')}</code></td>
          <td>
            <div class="bg-danger bg-opacity-10 border-start border-danger border-3 px-2 py-1 fst-italic mb-1">&bdquo;${App.escapeHtml(v.matched_quote || '')}&ldquo;</div>
            <small class="text-muted">${App.escapeHtml(v.reason || '')}</small>
          </td>
        </tr>`;
      });
      this.setHtml('aic-tbody-violations', html);
    },

    async loadMovieIntoLab(params) {
      try {
        const res = await this.callAjax({ ...params, action: 'load_movie' }, 'GET');
        if (!res || res.status !== 'ok') {
          App.toast(res?.message || 'Fehler beim Laden des Videos.', 'danger');
          return;
        }
        this.displayMovieInLab(res.movie);
      } catch (e) {
        App.toast('Netzwerkfehler beim Laden des Videos.', 'danger');
      }
    },

    onSelectRecent(val) {
      if (val) {
        this.loadMovieIntoLab({ movie_id: val });
      }
    },

    loadMovieByInput() {
      const mid = document.getElementById('aic-input-movie-id')?.value || '';
      const merch = document.getElementById('aic-input-merchant-id')?.value || '';
      this.loadMovieIntoLab({ movie_id: mid, merchant_id: merch });
    },

    loadMovieMode(mode) {
      const merch = document.getElementById('aic-input-merchant-id')?.value || '';
      this.loadMovieIntoLab({ mode, merchant_id: merch });
    },

    openMovieInLab(movieId) {
      this.activateTab('tab-btn-aic-lab');
      this.loadMovieIntoLab({ movie_id: movieId });
    },

    async runWhisper() {
      const mid = this.ensureCurrentMovieId();
      if (!mid) {
        App.toast('Bitte wähle zuerst ein Video aus.', 'warning');
        return;
      }
      const btn = document.getElementById('btn-aic-run-whisper');
      const scenario = document.getElementById('aic-sim-scenario')?.value || 'clean';
      const audioLimit = document.getElementById('aic-audio-limit')?.value || '120';

      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Audio & Untertitel werden verarbeitet...';
      }
      this.setText('aic-whisper-timer', 'Bitte warten (Whisper transkribiert Audio)...');

      try {
        const res = await this.callAjax({
          action: 'run_whisper',
          movie_id: mid,
          sim_scenario: scenario,
          audio_limit_sec: audioLimit
        }, 'POST');

        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-mic me-1"></i>1. Audio jetzt auswerten &amp; Untertitel erzeugen';
        }

        if (!res || res.status !== 'ok') {
          App.toast(res?.message || 'Fehler bei der Audio-Auswertung.', 'danger');
          return;
        }
        this.setHtml('aic-whisper-timer', `<strong class="text-success">&#x2714; Abgeschlossen (${App.escapeHtml(res.mode_used)})</strong>`);
        this.displayMovieInLab(res.movie);
        App.toast('Audio-Analyse & .vtt erfolgreich abgeschlossen.', 'success');
      } catch (e) {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-mic me-1"></i>1. Audio jetzt auswerten &amp; Untertitel erzeugen';
        }
        App.toast('Netzwerkfehler bei Schritt 1.', 'danger');
      }
    },

    async runTextCheck() {
      const mid = this.ensureCurrentMovieId();
      if (!mid) {
        App.toast('Bitte wähle zuerst ein Video aus.', 'warning');
        return;
      }
      const btn = document.getElementById('btn-aic-run-textcheck');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Prüfe Texte & Audio-Transkript...';
      }

      try {
        const res = await this.callAjax({
          action: 'run_text_check',
          movie_id: mid,
          custom_title: document.getElementById('aic-edit-title')?.value || '',
          custom_desc: document.getElementById('aic-edit-desc')?.value || '',
          custom_transcript: document.getElementById('aic-edit-transcript')?.value || ''
        }, 'POST');

        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-shield-check me-1"></i>2. Jetzt BRAM- &amp; Rechts-Prüfung starten (Titel + Beschreibung + Audio)';
        }

        if (!res || res.status !== 'ok') {
          App.toast(res?.message || 'Fehler bei der BRAM-Prüfung.', 'danger');
          return;
        }
        this.renderComplianceReport(res.report);
        App.toast('BRAM- & Rechts-Prüfung abgeschlossen.', 'success');
      } catch (e) {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-shield-check me-1"></i>2. Jetzt BRAM- &amp; Rechts-Prüfung starten (Titel + Beschreibung + Audio)';
        }
        App.toast('Netzwerkfehler bei Schritt 2.', 'danger');
      }
    },

    // Tab 2: Batch & Cronjob
    appendBatchLog(line) {
      const box = document.getElementById('aic-live-batch-log');
      if (!box) return;
      const now = new Date().toLocaleTimeString('de-DE');
      box.value = (box.value ? box.value + '\n' : '') + `[${now}] ${line}`;
      box.scrollTop = box.scrollHeight;
    },

    stopBatchStepPoller() {
      if (this.batchStepTimer) {
        clearInterval(this.batchStepTimer);
        this.batchStepTimer = null;
      }
      this.setText('aic-live-batch-ticker', '');
    },

    startLiveBatch() {
      this.liveBatchRunning = true;
      const startBtn = document.getElementById('btn-aic-start-live-batch');
      const stopBtn = document.getElementById('btn-aic-stop-live-batch');
      if (startBtn) startBtn.style.display = 'none';
      if (stopBtn) stopBtn.style.display = '';
      this.appendBatchLog('=== LIVE-BATCH AUTO-LOOP GESTARTET ===');
      this.executeNextLiveBatchStep(true);
    },

    stopLiveBatch() {
      this.liveBatchRunning = false;
      this.appendBatchLog('Stopp angefordert – warte auf Abschluss des aktuell laufenden Videos...');
    },

    stepLiveBatch() {
      this.liveBatchRunning = false;
      this.executeNextLiveBatchStep(false);
    },

    async executeNextLiveBatchStep(autoContinue) {
      const startBtn = document.getElementById('btn-aic-start-live-batch');
      const stopBtn = document.getElementById('btn-aic-stop-live-batch');
      const stepBtn = document.getElementById('btn-aic-step-live-batch');

      if (autoContinue && !this.liveBatchRunning) {
        this.stopBatchStepPoller();
        this.appendBatchLog('Live-Batch wurde pausiert.');
        if (startBtn) { startBtn.style.display = ''; startBtn.disabled = false; }
        if (stopBtn) stopBtn.style.display = 'none';
        if (stepBtn) stepBtn.disabled = false;
        return;
      }

      this.appendBatchLog('Starte nächstes offenes Video (Whisper Audio-Extraktion + .vtt + BRAM-Check)...');
      if (stepBtn) stepBtn.disabled = true;

      this.batchStepElapsedSec = 0;
      this.batchAnnouncedMovieId = 0;
      this.stopBatchStepPoller();
      this.setText('aic-live-batch-ticker', '⏳ Whisper läuft (0 Sek.)...');

      this.batchStepTimer = setInterval(async () => {
        this.batchStepElapsedSec += 2;
        try {
          const pRes = await this.callAjax({ action: 'poll_worker_status' }, 'GET');
          if (pRes && pRes.status === 'ok' && pRes.current_movie && pRes.current_movie.id) {
            const cm = pRes.current_movie;
            this.setText('aic-live-batch-ticker', `⏳ Verarbeite #${cm.id} (Dauer: ${cm.playtime_fmt} Min.) – läuft seit ${this.batchStepElapsedSec} Sek.`);
            if (this.batchAnnouncedMovieId !== cm.id) {
              this.batchAnnouncedMovieId = cm.id;
              this.appendBatchLog(`   ↳ Aktuell in Arbeit: Video #${cm.id} "${cm.title}" (Spieldauer: ${cm.playtime_fmt} Min.)...`);
            }
          } else {
            this.setText('aic-live-batch-ticker', `⏳ Whisper läuft (${this.batchStepElapsedSec} Sek.)...`);
          }
        } catch (_) {}
      }, 2000);

      try {
        const res = await this.callAjax({ action: 'run_batch_step' }, 'POST');
        this.stopBatchStepPoller();

        if (!res || res.status !== 'ok') {
          this.appendBatchLog('FEHLER: ' + (res?.message || 'Unbekannter Fehler.'));
          this.liveBatchRunning = false;
          if (startBtn) { startBtn.style.display = ''; startBtn.disabled = false; }
          if (stopBtn) stopBtn.style.display = 'none';
          if (stepBtn) stepBtn.disabled = false;
          return;
        }

        this.appendBatchLog('✔ ' + res.message);
        this.updateStatsUI(res.stats, res.worker_status, null);

        if (!res.has_more) {
          this.appendBatchLog('FERTIG! Alle konvertierten Videos wurden verarbeitet.');
          this.liveBatchRunning = false;
          if (startBtn) { startBtn.style.display = ''; startBtn.disabled = false; }
          if (stopBtn) stopBtn.style.display = 'none';
          if (stepBtn) stepBtn.disabled = false;
          this.applyHistoryFilter();
          return;
        }

        if (autoContinue && this.liveBatchRunning) {
          setTimeout(() => this.executeNextLiveBatchStep(true), 600);
        } else {
          if (stepBtn) stepBtn.disabled = false;
          this.applyHistoryFilter();
        }
      } catch (e) {
        this.stopBatchStepPoller();
        this.appendBatchLog('Netzwerk- oder Timeout-Fehler beim Batch-Schritt. Pausiere.');
        this.liveBatchRunning = false;
        if (startBtn) { startBtn.style.display = ''; startBtn.disabled = false; }
        if (stopBtn) stopBtn.style.display = 'none';
        if (stepBtn) stepBtn.disabled = false;
      }
    },

    async runBulkPrecheck(forceRecheck, lastId = 0, totalProcessedSoFar = 0) {
      const btnPre = document.getElementById('btn-aic-bulk-precheck');
      const btnAll = document.getElementById('btn-aic-bulk-recheck-all');
      const statusEl = document.getElementById('aic-bulk-precheck-status');
      if (btnPre) btnPre.disabled = true;
      if (btnAll) btnAll.disabled = true;
      if (statusEl) {
        statusEl.className = 'small fw-bold text-primary';
        statusEl.textContent = `Prüfe Katalog-Paket... (${totalProcessedSoFar} Filme geprüft)`;
      }

      try {
        const res = await this.callAjax({
          action: 'run_bulk_text_precheck',
          chunk_size: 250,
          force_recheck: forceRecheck ? 1 : 0,
          last_id: lastId || 0
        }, 'POST');

        if (!res || res.status !== 'ok') {
          if (btnPre) btnPre.disabled = false;
          if (btnAll) btnAll.disabled = false;
          if (statusEl) {
            statusEl.className = 'small fw-bold text-danger';
            statusEl.textContent = 'Fehler beim Schnell-Scan.';
          }
          return;
        }

        const newTotal = totalProcessedSoFar + (res.processed_in_chunk || 0);
        this.updateStatsUI(res.stats, null, null);

        if (res.has_more && res.processed_in_chunk > 0) {
          this.runBulkPrecheck(forceRecheck, res.last_id, newTotal);
        } else {
          if (btnPre) btnPre.disabled = false;
          if (btnAll) btnAll.disabled = false;
          if (statusEl) {
            statusEl.className = 'small fw-bold text-success';
            statusEl.textContent = `✔ Katalog-Schnellscan abgeschlossen! (${newTotal} Videos in diesem Durchlauf bewertet)`;
          }
          this.applyHistoryFilter();
        }
      } catch (e) {
        if (btnPre) btnPre.disabled = false;
        if (btnAll) btnAll.disabled = false;
        if (statusEl) {
          statusEl.className = 'small fw-bold text-danger';
          statusEl.textContent = 'Netzwerkfehler beim Schnell-Scan.';
        }
      }
    },

    async saveBatchConfig() {
      const btn = document.getElementById('btn-aic-save-batch-cfg');
      const msgEl = document.getElementById('aic-save-batch-msg');
      if (btn) btn.disabled = true;
      if (msgEl) {
        msgEl.className = 'small fw-bold text-muted';
        msgEl.textContent = 'Speichere...';
      }

      try {
        const res = await this.callAjax({
          action: 'save_batch_config',
          cron_enabled: document.getElementById('aic-cfg-cron-enabled')?.checked ? 1 : 0,
          whisper_model_pref: document.getElementById('aic-cfg-model')?.value || 'small',
          audio_limit_sec: document.getElementById('aic-cfg-audiolimit')?.value || '0',
          whisper_threads: document.getElementById('aic-cfg-threads')?.value || '12',
          max_movies_per_run: document.getElementById('aic-cfg-maxruns')?.value || '2',
          order_direction: document.getElementById('aic-cfg-order')?.value || 'DESC',
          pause_on_ffmpeg_convert: document.getElementById('aic-cfg-pause-ffmpeg')?.checked ? 1 : 0,
          sync_empty_movie_lang: document.getElementById('aic-cfg-sync-lang')?.checked ? 1 : 0
        }, 'POST');

        if (btn) btn.disabled = false;
        if (!res || res.status !== 'ok') {
          if (msgEl) {
            msgEl.className = 'small fw-bold text-danger';
            msgEl.textContent = 'Fehler beim Speichern.';
          }
          return;
        }
        if (msgEl) {
          msgEl.className = 'small fw-bold text-success';
          msgEl.textContent = res.message;
        }
        this.updateStatsUI(null, null, res.batch_config);
        App.toast(res.message, 'success');
      } catch (e) {
        if (btn) btn.disabled = false;
        if (msgEl) {
          msgEl.className = 'small fw-bold text-danger';
          msgEl.textContent = 'Netzwerkfehler.';
        }
      }
    },

    // Tab 3: Regelwerk
    renderRuleCategories(parsed) {
      const cats = (parsed && parsed.categories) ? parsed.categories : [];
      if (!cats.length) {
        this.setHtml('aic-tbody-rule-cats', '<tr><td colspan="4" class="text-center text-muted">Keine Kategorien gefunden.</td></tr>');
        return;
      }
      let html = '';
      cats.forEach(c => {
        const badge = c.severity === 'violation'
          ? `<span class="badge bg-danger">Hard-Block (Rot)</span><div class="small text-muted mt-1">Basis-Score: ${App.escapeHtml(c.base_risk_score)}</div>`
          : `<span class="badge bg-warning text-dark">Prüfhinweis (Gelb)</span><div class="small text-muted mt-1">Basis-Score: ${App.escapeHtml(c.base_risk_score)}</div>`;
        const kws = (c.keywords || []).map(kw => `<span class="badge bg-light text-dark border fw-normal">${App.escapeHtml(kw)}</span>`).join(' ');
        html += `<tr>
          <td><strong>${App.escapeHtml(c.name)}</strong></td>
          <td>${badge}</td>
          <td>${App.escapeHtml(c.description)}</td>
          <td><div class="d-flex flex-wrap gap-1">${kws}</div></td>
        </tr>`;
      });
      this.setHtml('aic-tbody-rule-cats', html);
    },

    async saveRules() {
      const btn = document.getElementById('btn-aic-save-rules');
      const msgEl = document.getElementById('aic-save-rules-msg');
      if (btn) btn.disabled = true;
      if (msgEl) {
        msgEl.className = 'small fw-bold text-muted';
        msgEl.textContent = 'Speichere Regelwerk...';
      }

      try {
        const res = await this.callAjax({
          action: 'save_rules',
          system_prompt_md: document.getElementById('aic-rules-md-editor')?.value || '',
          keyword_rules_json: document.getElementById('aic-rules-json-editor')?.value || ''
        }, 'POST');

        if (btn) btn.disabled = false;
        if (!res || res.status !== 'ok') {
          if (msgEl) {
            msgEl.className = 'small fw-bold text-danger';
            msgEl.textContent = res?.message || 'Fehler beim Speichern.';
          }
          App.toast(res?.message || 'Fehler beim Speichern.', 'danger');
          return;
        }
        if (msgEl) {
          msgEl.className = 'small fw-bold text-success';
          msgEl.textContent = res.message;
        }
        this.renderRuleCategories(res.parsed_categories);
        App.toast(res.message, 'success');
      } catch (e) {
        if (btn) btn.disabled = false;
        if (msgEl) {
          msgEl.className = 'small fw-bold text-danger';
          msgEl.textContent = 'Netzwerkfehler beim Speichern.';
        }
      }
    },

    // Tab 4: Analyse-Historie
    renderHistoryTable(rows) {
      this.setText('aic-hist-count', rows ? rows.length : 0);
      if (!rows || !rows.length) {
        this.setHtml('aic-tbody-history', '<tr><td colspan="8" class="text-center text-muted py-4">Keine Videos für die gewählten Filterkriterien gefunden.</td></tr>');
        return;
      }
      let html = '';
      rows.forEach(r => {
        let subBadge = '';
        if (r.subtitle_status === 2) {
          subBadge = `<span class="badge bg-info text-dark">Sprache: ${App.escapeHtml(String(r.detected_language || '').toUpperCase())} (${r.whisper_duration_sec}s)</span><br><small><code>${App.escapeHtml(r.subtitle_filename)}</code></small>`;
        } else if (r.subtitle_status === 3) {
          subBadge = `<span class="badge bg-secondary">Stummfilm (Kein Audio)</span>`;
        } else if (r.subtitle_status === 4) {
          subBadge = `<span class="badge bg-danger" title="${App.escapeHtml(r.last_error)}">MP4 fehlt / Fehler</span>`;
        } else {
          subBadge = `<span class="badge bg-secondary">Audio noch offen</span>`;
        }

        html += `<tr>
          <td><strong>#${r.movie_id}</strong></td>
          <td>${r.merchant_id}</td>
          <td><strong>${App.escapeHtml(r.title)}</strong><br><small class="text-muted">Dauer: ${App.escapeHtml(r.playtime_fmt)} Min.</small></td>
          <td>${subBadge}</td>
          <td>${this.renderComplianceBadge(r.text_compliance, r.text_risk_score)}</td>
          <td>${App.escapeHtml(r.text_summary || '-')}</td>
          <td>${App.escapeHtml(r.updated_at)}</td>
          <td>
            <div class="d-flex flex-column gap-1">
              <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 small" onclick="App.AiCompliance.openMovieInLab(${r.movie_id})">Im Labor öffnen</button>
              <a href="#/movie-edit?id=${r.movie_id}" class="btn btn-xs btn-outline-secondary py-0 px-2 small">Film bearbeiten</a>
            </div>
          </td>
        </tr>`;
      });
      this.setHtml('aic-tbody-history', html);
    },

    async applyHistoryFilter() {
      try {
        const res = await this.callAjax({
          action: 'filter_history',
          compliance_filter: document.getElementById('aic-filter-comp')?.value || 'all',
          subtitle_filter: document.getElementById('aic-filter-sub')?.value || 'all',
          search_query: document.getElementById('aic-filter-search')?.value || ''
        }, 'GET');
        if (!res || res.status !== 'ok') return;
        this.renderHistoryTable(res.history || []);
        this.updateStatsUI(res.stats, null, null);
      } catch (_) {}
    },

    resetHistoryFilter() {
      const comp = document.getElementById('aic-filter-comp');
      const sub = document.getElementById('aic-filter-sub');
      const q = document.getElementById('aic-filter-search');
      if (comp) comp.value = 'all';
      if (sub) sub.value = 'all';
      if (q) q.value = '';
      this.applyHistoryFilter();
    },

    jumpToHistoryFilter(compStatus, searchQuery = '', subStatus = 'all') {
      this.activateTab('tab-btn-aic-history');
      const comp = document.getElementById('aic-filter-comp');
      const sub = document.getElementById('aic-filter-sub');
      const q = document.getElementById('aic-filter-search');
      if (comp) comp.value = compStatus || 'all';
      if (sub) sub.value = subStatus || 'all';
      if (q) q.value = searchQuery || '';
      this.applyHistoryFilter();
    },

    // Tab 5: Transkript- & VTT-Inspektor
    renderMovieSampleLinks(samples) {
      if (!samples || !samples.length) return '-';
      return samples.map(sm => {
        const mid = sm.id;
        const ttl = App.escapeHtml(sm.title || `Film #${mid}`);
        return `<button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 small me-1 mb-1" onclick="App.AiCompliance.openMovieInLab(${mid})" title="Im Testlabor öffnen: ${ttl}">#${mid}</button>`;
      }).join('');
    },

    async runInspector() {
      const btn = document.getElementById('btn-aic-run-inspector');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Analysiere alle vorhandenen .vtt-Transkripte...';
      }
      const expMsg = document.getElementById('aic-insp-export-msg');
      if (expMsg) expMsg.style.display = 'none';
      this.setHtml('aic-tbody-insp-brackets', '<tr><td colspan="5" class="text-center py-3">Werte alle gespeicherten Transkripte und Segmente aus...</td></tr>');

      try {
        const res = await this.callAjax({ action: 'inspect_transcripts' }, 'POST');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Transkript-Analyse aktualisieren';
        }
        if (!res || res.status !== 'ok') {
          App.toast(res?.message || 'Fehler bei der Transkript-Auswertung.', 'danger');
          return;
        }

        this.lastInspectorData = res;
        const copyBtn = document.getElementById('btn-aic-copy-inspector');
        const dlBtn = document.getElementById('btn-aic-download-inspector');
        if (copyBtn) copyBtn.style.display = '';
        if (dlBtn) dlBtn.style.display = '';

        const sum = res.summary || {};
        this.setText('aic-insp-total-sub', sum.total_transcribed || 0);
        this.setText('aic-insp-total-silent', sum.total_silent || 0);
        this.setText('aic-insp-real-speech', sum.total_with_real_speech || 0);
        this.setText('aic-insp-only-brackets', sum.total_only_brackets || 0);
        this.setText('aic-insp-unique-brackets', sum.unique_bracket_tags || 0);
        this.setText('aic-insp-occ-brackets', sum.total_bracket_occ || 0);

        // 1. Klammer-Ausdrücke
        const bList = res.bracket_tags || [];
        if (!bList.length) {
          this.setHtml('aic-tbody-insp-brackets', '<tr><td colspan="5" class="text-center text-muted py-3">Keine Klammer-Ausdrücke in den aktuell vorhandenen Transkripten gefunden.</td></tr>');
        } else {
          let bHtml = '';
          bList.forEach(b => {
            let badgeCls = 'bg-secondary';
            if (b.cat_type === 'keep_atmosphere') badgeCls = 'bg-success';
            else if (b.cat_type === 'convert_moan') badgeCls = 'bg-info text-dark';
            else if (b.cat_type === 'remove_artifact') badgeCls = 'bg-danger';

            const safeQ = App.escapeHtml(b.tag).replace(/'/g, "\\'");
            bHtml += `<tr>
              <td><code class="fw-bold">${App.escapeHtml(b.tag)}</code></td>
              <td><strong>${b.total_occurrences}x</strong></td>
              <td>${b.movie_count} Film(e)</td>
              <td><span class="badge ${badgeCls}">${App.escapeHtml(b.cat_label)}</span></td>
              <td>
                ${this.renderMovieSampleLinks(b.sample_movies)}
                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1 small" onclick="App.AiCompliance.jumpToHistoryFilter('all', '${safeQ}', 'has_sub')">In Historie &rarr;</button>
              </td>
            </tr>`;
          });
          this.setHtml('aic-tbody-insp-brackets', bHtml);
        }

        // 2. Sprachen
        const lList = res.languages || [];
        if (!lList.length) {
          this.setHtml('aic-tbody-insp-langs', '<tr><td colspan="6" class="text-center text-muted py-3">Noch keine transkribierten Videos vorhanden.</td></tr>');
        } else {
          const allowedWl = { de: 1, en: 1, es: 1, fr: 1, it: 1, nl: 1, pl: 1, cs: 1, ru: 1, pt: 1, tr: 1, hu: 1, sv: 1, da: 1 };
          let lHtml = '';
          lList.forEach(lg => {
            const isSuspect = (!allowedWl[lg.code_lower] || (lg.only_brackets_count > 0 && lg.real_speech_count === 0));
            const codeBadge = isSuspect
              ? `<span class="badge bg-warning text-dark">${App.escapeHtml(lg.code)}</span>`
              : `<span class="badge bg-success">${App.escapeHtml(lg.code)}</span>`;
            const safeLang = App.escapeHtml(lg.code_lower).replace(/'/g, "\\'");
            lHtml += `<tr>
              <td>${codeBadge}</td>
              <td><strong>${App.escapeHtml(lg.name)}</strong></td>
              <td><strong>${lg.movie_count}</strong></td>
              <td class="text-success fw-bold">${lg.real_speech_count}</td>
              <td class="${lg.only_brackets_count > 0 ? 'text-danger fw-bold' : 'text-muted'}">${lg.only_brackets_count}</td>
              <td>
                ${this.renderMovieSampleLinks(lg.sample_movies)}
                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1 small" onclick="App.AiCompliance.jumpToHistoryFilter('all', '${safeLang}', 'has_sub')">Alle &rarr;</button>
              </td>
            </tr>`;
          });
          this.setHtml('aic-tbody-insp-langs', lHtml);
        }

        // 3. Filme ohne echten Dialog
        const ndList = res.no_dialog_movies || [];
        if (!ndList.length) {
          this.setHtml('aic-tbody-insp-nodialog', '<tr><td colspan="6" class="text-center text-success py-3">Keine Filme gefunden, die nur aus Klammer-Geräuschen bestehen.</td></tr>');
        } else {
          let ndHtml = '';
          ndList.forEach(nd => {
            ndHtml += `<tr>
              <td><strong>#${nd.movie_id}</strong></td>
              <td>${App.escapeHtml(nd.title)}</td>
              <td><span class="badge bg-warning text-dark">${App.escapeHtml(nd.detected_language)}</span></td>
              <td>${nd.segment_count}</td>
              <td><code class="small text-muted">${App.escapeHtml(nd.preview)}</code></td>
              <td>
                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1 small" onclick="App.AiCompliance.openMovieInLab(${nd.movie_id})">Labor</button>
                <a href="#/movie-edit?id=${nd.movie_id}" class="btn btn-xs btn-outline-secondary py-0 px-1 small">Film</a>
              </td>
            </tr>`;
          });
          this.setHtml('aic-tbody-insp-nodialog', ndHtml);
        }

        // 4. Wiederholte Kurz-Phrasen
        const pList = res.repeated_phrases || [];
        if (!pList.length) {
          this.setHtml('aic-tbody-insp-phrases', '<tr><td colspan="4" class="text-center text-muted py-3">Keine mehrfach wiederholten Kurz-Phrasen gefunden.</td></tr>');
        } else {
          let pHtml = '';
          pList.forEach(ph => {
            pHtml += `<tr>
              <td><strong>${App.escapeHtml(ph.phrase)}</strong></td>
              <td>${ph.total_occurrences}x</td>
              <td>${ph.movie_count} Film(e)</td>
              <td>${this.renderMovieSampleLinks(ph.sample_movies)}</td>
            </tr>`;
          });
          this.setHtml('aic-tbody-insp-phrases', pHtml);
        }
      } catch (e) {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Transkript-Analyse starten';
        }
        App.toast('Netzwerkfehler beim Laden des Transkript-Inspektors.', 'danger');
      }
    },

    copyInspectorJson() {
      if (!this.lastInspectorData) return;
      const jsonStr = JSON.stringify(this.lastInspectorData, null, 2);
      const expMsg = document.getElementById('aic-insp-export-msg');
      navigator.clipboard?.writeText(jsonStr).then(() => {
        if (expMsg) {
          expMsg.style.display = '';
          expMsg.innerHTML = '&#x2705; Die komplette Auswertung wurde in deine Zwischenablage kopiert!';
        }
        App.toast('Auswertung in die Zwischenablage kopiert.', 'success');
      });
    },

    downloadInspectorJson() {
      if (!this.lastInspectorData) return;
      const jsonStr = JSON.stringify(this.lastInspectorData, null, 2);
      const blob = new Blob([jsonStr], { type: 'application/json;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'erocloud_vtt_analyse.json';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    },

    async cleanAllVtt() {
      const btn = document.getElementById('btn-aic-clean-all-vtt');
      const msgEl = document.getElementById('aic-insp-clean-msg');
      const origHtml = btn ? btn.innerHTML : '';
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '&#x23F3; Bereinige alle Transkripte &amp; .vtt-Dateien...';
      }
      if (msgEl) {
        msgEl.style.display = '';
        msgEl.className = 'alert alert-info py-2 px-3 mt-2 mb-0 small';
        msgEl.innerHTML = '&#x23F3; Bitte warten: Alle vorhandenen Transkripte werden normalisiert, entflackert und die .vtt-Dateien neu geschrieben...';
      }

      try {
        const res = await this.callAjax({ action: 'clean_all_vtt_transcripts' }, 'POST');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = origHtml;
        }
        if (res && res.status === 'ok') {
          if (msgEl) {
            msgEl.className = 'alert alert-success py-2 px-3 mt-2 mb-0 small';
            msgEl.textContent = '✔ ' + res.message;
          }
          this.runInspector();
        } else if (msgEl) {
          msgEl.className = 'alert alert-danger py-2 px-3 mt-2 mb-0 small';
          msgEl.textContent = 'Fehler bei der Bereinigung.';
        }
      } catch (e) {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = origHtml;
        }
        if (msgEl) {
          msgEl.className = 'alert alert-danger py-2 px-3 mt-2 mb-0 small';
          msgEl.textContent = 'Netzwerkfehler bei der Bereinigung.';
        }
      }
    },

    async requeueExotic() {
      const btn = document.getElementById('btn-aic-requeue-exotic');
      const msgEl = document.getElementById('aic-insp-clean-msg');
      const mode = document.getElementById('aic-requeue-exotic-mode')?.value || 'foreign_speech_only';
      const origHtml = btn ? btn.innerHTML : '';
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '&#x23F3; Reihe Filme ein...';
      }

      try {
        const res = await this.callAjax({
          action: 'requeue_exotic_lang_movies',
          requeue_mode: mode
        }, 'POST');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = origHtml;
        }
        if (res && res.status === 'ok') {
          if (res.stats) this.updateStatsUI(res.stats, null, null);
          if (msgEl) {
            msgEl.style.display = '';
            msgEl.className = 'alert alert-warning py-2 px-3 mt-2 mb-0 small';
            msgEl.textContent = '🔄 ' + res.message;
          }
          this.runInspector();
        }
      } catch (e) {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = origHtml;
        }
        App.toast('Netzwerkfehler beim Einreihen der Filme.', 'danger');
      }
    }
  },

  // ========================================================================
  // 9. Content-Bereinigung & Dateileichen-Suche (#/content-cleanup)
  // ========================================================================
  ContentCleanup: {
    currentPreviewRule: 'all',

    filterPreview(rule, btnEl) {
      this.currentPreviewRule = rule || 'all';
      document.querySelectorAll('.cc-filter-pill').forEach(btn => {
        btn.classList.remove('active');
      });
      if (btnEl) {
        btnEl.classList.add('active');
      }
      this.applyPreviewFilter();
    },

    applyPreviewFilter() {
      const rule = this.currentPreviewRule || 'all';
      const q = (document.getElementById('cc-preview-search')?.value || '').trim().toLowerCase();
      document.querySelectorAll('.cc-preview-row').forEach(row => {
        const rowRule = row.getAttribute('data-rule') || '';
        const isImmediate = row.getAttribute('data-immediate') === '1';
        const haystack = row.getAttribute('data-search') || '';
        const matchesRule = (rule === 'all') || (rule === 'immediate' && isImmediate) || (rowRule === rule);
        const matchesSearch = (q === '') || haystack.includes(q);
        row.style.display = (matchesRule && matchesSearch) ? '' : 'none';
      });
    },

    filterHistory(query) {
      const q = String(query ?? document.getElementById('cc-history-search')?.value ?? '').trim().toLowerCase();
      document.querySelectorAll('.cc-history-row').forEach(row => {
        const haystack = row.getAttribute('data-search') || '';
        row.style.display = (q === '' || haystack.includes(q)) ? '' : 'none';
      });
    },

    async runOrphanScan() {
      const initBox = document.getElementById('cc-orphan-init-box');
      const loadingBox = document.getElementById('cc-orphan-loading');
      const resultBox = document.getElementById('cc-orphan-result-box');
      const localMsg = document.getElementById('cc-orphan-local-msg');
      const tbody = document.getElementById('cc-tbody-orphans');

      if (initBox) initBox.style.display = 'none';
      if (resultBox) resultBox.style.display = 'none';
      if (loadingBox) loadingBox.style.display = '';

      try {
        const res = await fetch(`${App.basePath}/api.php?route=content-cleanup/orphan-scan`, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          }
        });
        const data = await res.json();

        if (loadingBox) loadingBox.style.display = 'none';

        if (!res.ok || !data || (data.status !== 'success' && data.status !== 'ok')) {
          if (initBox) initBox.style.display = '';
          App.toast(data?.message || 'Fehler beim Dateileichen-Scan.', 'danger');
          return;
        }

        if (resultBox) resultBox.style.display = '';

        if (localMsg) {
          if (data.is_local_missing && data.message) {
            localMsg.style.display = '';
            localMsg.innerHTML = `<i class="bi bi-info-circle-fill me-1"></i>${App.escapeHtml(data.message)}`;
          } else {
            localMsg.style.display = 'none';
          }
        }

        const setTxt = (id, val) => {
          const el = document.getElementById(id);
          if (el) el.textContent = String(val ?? '');
        };

        setTxt('cc-orphan-stat-count', `${data.total_count || 0} Objekte`);
        setTxt('cc-orphan-stat-size', data.total_size_formatted || '0 Bytes');
        setTxt('cc-orphan-stat-time', `${data.duration_ms || 0} ms`);

        const items = data.data || [];
        if (tbody) {
          if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-success fw-semibold">&#x2705; Hervorragend! Es wurden keine verwaisten Verzeichnisse oder Dateileichen im Cloud-Speicher gefunden.</td></tr>';
          } else {
            tbody.innerHTML = items.map(it => {
              const searchStr = `${it.path || ''} ${it.type || ''} ${it.reason || ''}`.toLowerCase();
              return `<tr class="cc-orphan-row" data-search="${App.escapeHtml(searchStr)}">
                <td><code class="small">${App.escapeHtml(it.path)}</code></td>
                <td><span class="badge bg-secondary">${App.escapeHtml(it.type)}</span></td>
                <td>${App.escapeHtml(it.reason)}</td>
                <td class="fw-bold text-danger font-monospace">${App.escapeHtml(it.size_formatted)}</td>
              </tr>`;
            }).join('');
          }
        }
      } catch (err) {
        if (loadingBox) loadingBox.style.display = 'none';
        if (initBox) initBox.style.display = '';
        App.toast('Netzwerkfehler beim Dateileichen-Scan: ' + err.message, 'danger');
      }
    },

    filterOrphans(query) {
      const q = String(query ?? '').trim().toLowerCase();
      document.querySelectorAll('.cc-orphan-row').forEach(row => {
        const haystack = row.getAttribute('data-search') || '';
        row.style.display = (q === '' || haystack.includes(q)) ? '' : 'none';
      });
    }
  },

  // ========================================================================
  // 10. Speicher & Traffic + Legacy m_* Bereinigung (#/storage-analytics)
  // ========================================================================
  StorageAnalytics: {
    currentSkip: 0,
    currentPreviewMovie: null,
    batchAutoRunning: false,
    batchStopRequested: false,
    batchCursorMovieId: 0,
    batchTotalCleanedMovies: 0,
    batchTotalDeletedFiles: 0,
    batchTotalFreedBytes: 0,
    batchTotalScanned: 0,
    batchTotalAlreadyClean: 0,
    batchTotalSkipped: 0,

    setText(id, val) {
      const el = document.getElementById(id);
      if (el) el.textContent = String(val ?? '');
    },

    formatBytes(bytes) {
      const b = Math.max(0, Number(bytes) || 0);
      if (b >= 1099511627776) return (b / 1099511627776).toFixed(2).replace('.', ',') + ' TB';
      if (b >= 1073741824) return (b / 1073741824).toFixed(2).replace('.', ',') + ' GB';
      if (b >= 1048576) return (b / 1048576).toFixed(2).replace('.', ',') + ' MB';
      if (b >= 1024) return (b / 1024).toFixed(2).replace('.', ',') + ' KB';
      return b + ' Bytes';
    },

    async refreshAnalytics() {
      const btn = document.getElementById('btn-sa-refresh');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Berechne live neu...';
      }
      try {
        const res = await fetch(`${App.basePath}/api.php?route=storage-analytics/scan&force_refresh=1`, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          }
        });
        const data = await res.json();
        if (res.ok && data && data.summary) {
          App.toast('Speicher- & Traffic-Analyse erfolgreich live neu berechnet.', 'success');
          App.handleRoute();
        } else {
          App.toast('Fehler bei der Neuberechnung.', 'danger');
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Jetzt live neu berechnen';
          }
        }
      } catch (err) {
        App.toast('Netzwerkfehler: ' + err.message, 'danger');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Jetzt live neu berechnen';
        }
      }
    },

    loadSinglePreview(resetSkip = true) {
      if (resetSkip) {
        this.currentSkip = 0;
      }
      this.fetchSinglePreview();
    },

    nextSinglePreview() {
      const midInp = document.getElementById('sa-test-movie-id');
      if (midInp) midInp.value = '';
      this.currentSkip++;
      this.fetchSinglePreview();
    },

    async fetchSinglePreview() {
      const mid = parseInt(document.getElementById('sa-test-movie-id')?.value || '0', 10) || 0;
      const merchId = parseInt(document.getElementById('sa-test-merchant-id')?.value || '0', 10) || 0;
      const msgBox = document.getElementById('sa-cleanup-msg');
      const resBox = document.getElementById('sa-cleanup-result-box');
      const card = document.getElementById('sa-cleanup-preview-card');
      const btnLoad = document.getElementById('btn-sa-load-preview');
      const btnNext = document.getElementById('btn-sa-next-preview');

      if (resBox) resBox.style.display = 'none';
      if (card) card.style.display = 'none';
      if (btnLoad) btnLoad.disabled = true;
      if (msgBox) {
        msgBox.style.display = '';
        msgBox.className = 'alert alert-info small py-2 px-3 mb-3';
        msgBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Prüfe Film-Verzeichnis im 7-Stufen-Sicherheitsmodus...';
      }

      try {
        const url = new URL(`${App.basePath}/api.php`, window.location.origin);
        url.searchParams.set('route', 'storage-analytics/legacy-cleanup');
        url.searchParams.set('action', 'preview');
        if (mid > 0) url.searchParams.set('movie_id', String(mid));
        if (merchId > 0) url.searchParams.set('merchant_id', String(merchId));
        url.searchParams.set('skip', String(this.currentSkip));

        const res = await fetch(url.toString(), {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          }
        });
        const data = await res.json();

        if (btnLoad) btnLoad.disabled = false;

        if (!res.ok || !data || !data.found || !data.movie) {
          if (msgBox) {
            msgBox.style.display = '';
            msgBox.className = 'alert alert-warning small py-2 px-3 mb-3';
            msgBox.textContent = data?.message || 'Es wurde kein passendes Test-Video mit m_*-Dateien gefunden.';
          }
          return;
        }

        if (msgBox) msgBox.style.display = 'none';
        if (btnNext) btnNext.style.display = '';
        this.renderSingleMoviePreview(data.movie);
      } catch (err) {
        if (btnLoad) btnLoad.disabled = false;
        if (msgBox) {
          msgBox.style.display = '';
          msgBox.className = 'alert alert-danger small py-2 px-3 mb-3';
          msgBox.textContent = 'Netzwerkfehler: ' + err.message;
        }
      }
    },

    renderSingleMoviePreview(m) {
      this.currentPreviewMovie = m;
      const card = document.getElementById('sa-cleanup-preview-card');
      if (card) card.style.display = '';

      this.setText('sa-prev-movie-id', `#${m.movie_id}`);
      this.setText('sa-prev-title', m.title || '-');
      this.setText('sa-prev-online-id', m.online_id > 0 ? `#${m.online_id}` : '-');
      this.setText('sa-prev-db-id', `#${m.movie_id}`);
      this.setText('sa-prev-merchant-id', `#${m.merchant_id}`);
      this.setText('sa-prev-actor', m.actor_username || '-');
      this.setText('sa-prev-file-id', m.file_id || '-');
      this.setText('sa-prev-res', m.resolution || '-');
      this.setText('sa-prev-len', m.playtime_string || '-');
      this.setText('sa-prev-folder-path', m.folder_path || '-');

      const protTbody = document.getElementById('sa-prev-protected-tbody');
      if (protTbody) {
        const protFiles = m.protected_files || [];
        if (!protFiles.length) {
          protTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2">Keine geschützten Dateien im Verzeichnis gefunden.</td></tr>';
        } else {
          protTbody.innerHTML = protFiles.map(pf => `
            <tr>
              <td><span class="badge bg-success">${App.escapeHtml(pf.role)}</span></td>
              <td><code class="fw-bold text-success">${App.escapeHtml(pf.filename)}</code></td>
              <td><code class="small text-muted">${App.escapeHtml(pf.full_path)}</code></td>
              <td class="fw-semibold font-monospace">${App.escapeHtml(pf.size_formatted)}</td>
            </tr>
          `).join('');
        }
      }

      const delFiles = m.deletable_files || [];
      this.setText('sa-prev-del-count', delFiles.length);
      this.setText('sa-prev-del-size', m.deletable_total_fmt || '0 Bytes');
      this.setText('sa-btn-movie-id', m.movie_id);
      this.setText('sa-btn-del-count', delFiles.length);

      const delTbody = document.getElementById('sa-prev-deletable-tbody');
      if (delTbody) {
        if (!delFiles.length) {
          delTbody.innerHTML = '<tr><td colspan="4" class="text-center text-success fw-semibold py-2">&#x2705; In diesem Verzeichnis befinden sich keine veralteten m_*-Dateien (mehr).</td></tr>';
        } else {
          delTbody.innerHTML = delFiles.map(df => `
            <tr>
              <td><code class="fw-bold text-danger">${App.escapeHtml(df.filename)}</code></td>
              <td><span class="badge bg-danger">${App.escapeHtml(df.format_label)}</span></td>
              <td><code class="small text-muted">${App.escapeHtml(df.full_path)}</code></td>
              <td class="fw-bold text-danger font-monospace">${App.escapeHtml(df.size_formatted)}</td>
            </tr>
          `).join('');
        }
      }

      const btnDel = document.getElementById('btn-sa-delete-single');
      if (btnDel) {
        btnDel.disabled = !m.can_delete_safely;
      }
    },

    async deleteSingleMovie() {
      const m = this.currentPreviewMovie;
      if (!m || !m.movie_id || !m.file_id) {
        App.toast('Kein gültiges Video in der Vorschau geladen.', 'warning');
        return;
      }

      const btnDel = document.getElementById('btn-sa-delete-single');
      const resBox = document.getElementById('sa-cleanup-result-box');
      if (btnDel) btnDel.disabled = true;

      try {
        const fd = new FormData();
        fd.append('_csrf', App.csrfToken);
        fd.append('action', 'delete_single');
        fd.append('movie_id', String(m.movie_id));
        fd.append('confirm_file_id', String(m.file_id));

        const res = await fetch(`${App.basePath}/api.php?route=storage-analytics/legacy-cleanup`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || !data || data.error || !data.success) {
          App.toast(data?.error || 'Fehler beim Löschen der m_*-Dateien.', 'danger');
          if (btnDel) btnDel.disabled = false;
          return;
        }

        if (resBox) {
          resBox.style.display = '';
          resBox.innerHTML = `
            <strong>&#x2705; Erfolgreich bereinigt (Video #${data.movie_id}):</strong>
            Es wurden <strong>${data.deleted_count} veraltete m_*-Dateien</strong> gelöscht und <strong>${App.escapeHtml(data.freed_formatted)}</strong> Speicherplatz freigegeben.<br>
            <small>Nachprüfung: Desktop-Hauptvideo (<code>${App.escapeHtml(data.desktop_full_path)}</code>, ${App.escapeHtml(data.desktop_size_formatted)}) sowie ${data.thumbnails_count_after} Vorschaubilder sind zu 100 % intakt.</small>
          `;
        }

        if (data.movie_after) {
          this.renderSingleMoviePreview(data.movie_after);
        }
        App.toast(`Video #${data.movie_id} bereinigt (${data.freed_formatted} freigegeben).`, 'success');
      } catch (err) {
        App.toast('Netzwerkfehler: ' + err.message, 'danger');
        if (btnDel) btnDel.disabled = false;
      }
    },

    startBatch(batchLimit = 10, autoContinue = false) {
      this.batchAutoRunning = !!autoContinue;
      this.batchStopRequested = false;

      const panel = document.getElementById('sa-batch-panel');
      if (panel) panel.style.display = '';

      const stopBtn = document.getElementById('btn-sa-batch-stop');
      if (stopBtn) stopBtn.style.display = autoContinue ? '' : 'none';

      document.querySelectorAll('.btn-sa-batch-run').forEach(b => { b.disabled = true; });
      this.executeBatchStep(batchLimit, autoContinue);
    },

    stopBatch() {
      this.batchStopRequested = true;
      this.batchAutoRunning = false;
      this.setText('sa-batch-status-text', '⏸ Stopp angefordert – aktuelles Paket wird noch sauber beendet...');
    },

    resetBatch() {
      this.batchAutoRunning = false;
      this.batchStopRequested = false;
      this.batchCursorMovieId = 0;
      this.batchTotalCleanedMovies = 0;
      this.batchTotalDeletedFiles = 0;
      this.batchTotalFreedBytes = 0;
      this.batchTotalScanned = 0;
      this.batchTotalAlreadyClean = 0;
      this.batchTotalSkipped = 0;

      this.setText('sa-batch-status-text', 'Bereit zur Stapel-Bereinigung...');
      this.setText('sa-batch-cursor-label', 'Start (neueste zuerst)');
      this.setText('sa-batch-cnt-movies', '0');
      this.setText('sa-batch-cnt-files', '0');
      this.setText('sa-batch-cnt-bytes', '0 Bytes');
      this.setText('sa-batch-cnt-scanned', '0');
      this.setText('sa-batch-cnt-clean', '0');
      this.setText('sa-batch-cnt-skipped', '0');

      const skipBox = document.getElementById('sa-batch-skip-box');
      if (skipBox) skipBox.style.display = 'none';
      const skipList = document.getElementById('sa-batch-skip-list');
      if (skipList) skipList.innerHTML = '';
      const logTbody = document.getElementById('sa-batch-log-tbody');
      if (logTbody) logTbody.innerHTML = '';
      const stopBtn = document.getElementById('btn-sa-batch-stop');
      if (stopBtn) stopBtn.style.display = 'none';
      document.querySelectorAll('.btn-sa-batch-run').forEach(b => { b.disabled = false; });
    },

    async executeBatchStep(batchLimit, autoContinue) {
      const merchId = parseInt(document.getElementById('sa-test-merchant-id')?.value || '0', 10) || 0;
      const stopBtn = document.getElementById('btn-sa-batch-stop');

      this.setText(
        'sa-batch-status-text',
        `⏳ Prüfe & bereinige nächstes Paket (Limit: ${batchLimit} Videos${merchId > 0 ? ', Händler #' + merchId : ''})...`
      );

      try {
        const fd = new FormData();
        fd.append('_csrf', App.csrfToken);
        fd.append('action', 'delete_batch');
        fd.append('batch_limit', String(batchLimit));
        if (merchId > 0) fd.append('merchant_id', String(merchId));
        if (this.batchCursorMovieId > 0) fd.append('cursor_movie_id', String(this.batchCursorMovieId));

        const res = await fetch(`${App.basePath}/api.php?route=storage-analytics/legacy-cleanup`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || !data || data.error) {
          this.setText('sa-batch-status-text', '❌ Fehler: ' + (data?.error || 'Unbekannter Fehler'));
          document.querySelectorAll('.btn-sa-batch-run').forEach(b => { b.disabled = false; });
          if (stopBtn) stopBtn.style.display = 'none';
          return;
        }

        this.batchCursorMovieId = data.next_cursor_movie_id || this.batchCursorMovieId;
        this.batchTotalCleanedMovies += (data.cleaned_movies_count || 0);
        this.batchTotalDeletedFiles += (data.deleted_files_count || 0);
        this.batchTotalFreedBytes += (data.freed_bytes || 0);
        this.batchTotalScanned += (data.scanned_in_call || 0);
        this.batchTotalAlreadyClean += (data.already_clean_count || 0);

        this.setText('sa-batch-cursor-label', this.batchCursorMovieId > 0 ? `#${this.batchCursorMovieId} (verbleibend: ${data.remaining_db_rows || 0})` : 'Ende erreicht');
        this.setText('sa-batch-cnt-movies', this.batchTotalCleanedMovies);
        this.setText('sa-batch-cnt-files', this.batchTotalDeletedFiles);
        this.setText('sa-batch-cnt-bytes', this.formatBytes(this.batchTotalFreedBytes));
        this.setText('sa-batch-cnt-scanned', this.batchTotalScanned);
        this.setText('sa-batch-cnt-clean', this.batchTotalAlreadyClean);

        const skipped = data.skipped_movies || [];
        if (skipped.length > 0) {
          this.batchTotalSkipped += skipped.length;
          this.setText('sa-batch-cnt-skipped', this.batchTotalSkipped);
          const skipBox = document.getElementById('sa-batch-skip-box');
          const skipList = document.getElementById('sa-batch-skip-list');
          if (skipBox) skipBox.style.display = '';
          if (skipList) {
            skipped.forEach(sk => {
              const li = document.createElement('li');
              li.textContent = `Video #${sk.movie_id} (${sk.title}): ${sk.reason}`;
              skipList.appendChild(li);
            });
          }
        }

        const cleaned = data.cleaned_movies || [];
        const logTbody = document.getElementById('sa-batch-log-tbody');
        if (logTbody && cleaned.length > 0) {
          const rowsHtml = cleaned.map(cm => `
            <tr>
              <td><strong>#${cm.movie_id}</strong> ${cm.online_id > 0 ? `<small class="text-muted">(Online #${cm.online_id})</small>` : ''}</td>
              <td>MID #${cm.merchant_id}<br><small class="text-muted">${App.escapeHtml(cm.actor)}</small></td>
              <td><strong>${App.escapeHtml(cm.title)}</strong><br><code class="small text-muted">${App.escapeHtml(cm.folder_path)}</code></td>
              <td><span class="badge bg-danger">${cm.deleted_count} Dateien</span><br><code class="small text-danger">${App.escapeHtml(cm.deleted_filenames_str)}</code></td>
              <td class="fw-bold text-success font-monospace">${App.escapeHtml(cm.freed_formatted)}</td>
              <td>
                ${cm.desktop_verified_intact
                  ? `<span class="badge bg-success">&#x2714; Intakt (${App.escapeHtml(cm.desktop_size_formatted)})</span>`
                  : `<span class="badge bg-danger">&#x26A0; Nachprüfung fehlgeschlagen</span>`}
              </td>
            </tr>
          `).join('');
          logTbody.insertAdjacentHTML('afterbegin', rowsHtml);
        }

        if (data.emergency_stop) {
          this.setText('sa-batch-status-text', '🛑 ' + (data.emergency_message || 'Sicherheits-Stopp ausgelöst!'));
          this.batchAutoRunning = false;
          document.querySelectorAll('.btn-sa-batch-run').forEach(b => { b.disabled = false; });
          if (stopBtn) stopBtn.style.display = 'none';
          return;
        }

        if (!data.has_more) {
          this.setText(
            'sa-batch-status-text',
            `🎉 Stapel-Lauf vollständig abgeschlossen! (${this.batchTotalCleanedMovies} Videos bereinigt, ${this.formatBytes(this.batchTotalFreedBytes)} freigegeben)`
          );
          this.batchAutoRunning = false;
          document.querySelectorAll('.btn-sa-batch-run').forEach(b => { b.disabled = false; });
          if (stopBtn) stopBtn.style.display = 'none';
          return;
        }

        if (autoContinue && this.batchAutoRunning && !this.batchStopRequested) {
          setTimeout(() => this.executeBatchStep(batchLimit, true), 400);
        } else {
          this.setText(
            'sa-batch-status-text',
            `✔ Paket abgeschlossen (${data.cleaned_movies_count || 0} bereinigt, ${data.scanned_in_call || 0} geprüft). Bereit für nächstes Paket.`
          );
          document.querySelectorAll('.btn-sa-batch-run').forEach(b => { b.disabled = false; });
          if (stopBtn) stopBtn.style.display = 'none';
        }
      } catch (err) {
        this.setText('sa-batch-status-text', '❌ Netzwerkfehler: ' + err.message);
        this.batchAutoRunning = false;
        document.querySelectorAll('.btn-sa-batch-run').forEach(b => { b.disabled = false; });
        if (stopBtn) stopBtn.style.display = 'none';
      }
    }
  },

  // ========================================================================
  // 11. Darsteller-Profile (#/actors & #/actor?id=...)
  // ========================================================================
  Actors: {
    submitFilterForm(event) {
      if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
      }
      const params = new URLSearchParams();

      const q          = (document.getElementById('act-filter-q')?.value || '').trim();
      const status     = (document.getElementById('act-filter-status')?.value || 'all').trim();
      const mainCat    = (document.getElementById('act-filter-cat')?.value || 'all').trim();
      const imgFilter  = (document.getElementById('act-filter-img')?.value || 'all').trim();
      const sort       = (document.getElementById('act-filter-sort')?.value || 'id_desc').trim();
      const merchantId = parseInt(document.getElementById('act-filter-merchant')?.value || '0', 10) || 0;
      const perPage    = parseInt(document.getElementById('act-filter-perpage')?.value || '50', 10) || 50;

      if (status !== 'all') params.set('status', status);
      if (mainCat !== 'all') params.set('main_cat', mainCat);
      if (imgFilter !== 'all') params.set('img_filter', imgFilter);
      if (merchantId > 0) params.set('merchant_id', String(merchantId));
      if (sort !== 'id_desc') params.set('sort', sort);
      if (perPage !== 50) params.set('per_page', String(perPage));
      if (q !== '') params.set('q', q);

      const qs = params.toString();
      const targetHash = `#/actors${qs ? '?' + qs : ''}`;
      if (window.location.hash === targetHash) {
        App.handleRoute();
      } else {
        window.location.hash = targetHash;
      }
      return false;
    },

    quickFilterTable(query) {
      const q = String(query || '').trim().toLowerCase();
      document.querySelectorAll('.act-table-row').forEach(row => {
        const hay = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', !(q === '' || hay.includes(q)));
      });
    },

    filterSubTable(rowClass, query) {
      const q = String(query || '').trim().toLowerCase();
      document.querySelectorAll(`.${rowClass}`).forEach(row => {
        const hay = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', !(q === '' || hay.includes(q)));
      });
    },

    onStatusChange(val) {
      const warnEl = document.getElementById('act-status-delete-warning');
      if (warnEl) {
        warnEl.style.display = (val === 'deleted') ? '' : 'none';
      }
    },

    async saveActor(event) {
      if (event) event.preventDefault();
      const form = document.getElementById('actorEditForm');
      if (!form) return false;

      const checkedRadio = form.querySelector('input[name="actor_status"]:checked');
      const statusVal = checkedRadio ? checkedRadio.value : '';
      if (statusVal === 'deleted') {
        if (!window.confirm('Achtung: Möchtest du dieses Darsteller-Profil wirklich endgültig auf GELÖSCHT setzen? Dadurch werden auch alle zugeordneten Filme und Fotoalben als gelöscht markiert.')) {
          return false;
        }
      }

      const btn = document.getElementById('btn-actor-save-submit');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Speichere...';
      }

      const fd = new FormData(form);
      fd.set('_csrf', App.csrfToken);

      try {
        const res = await fetch(`${App.basePath}/api.php?route=actors/save`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Fehler beim Speichern des Darsteller-Profils.', 'danger');
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-floppy-fill me-1"></i>Alle Profildaten &amp; Kategorien speichern';
          }
          return false;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Darsteller-Profil erfolgreich gespeichert.', 'success');
        App.handleRoute();
      } catch (err) {
        App.toast('Netzwerkfehler beim Speichern: ' + err.message, 'danger');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-floppy-fill me-1"></i>Alle Profildaten &amp; Kategorien speichern';
        }
      }
      return false;
    },

    async deleteProfileImage(actorId, fsk) {
      const fskCode = String(fsk || '').toUpperCase().includes('16') ? 'FSK16' : 'FSK18';
      if (!window.confirm(`Möchtest du das ${fskCode}-Profilbild von Profil #${actorId} wirklich löschen?`)) {
        return;
      }

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);
      fd.append('actor_id', String(actorId));
      fd.append('fsk', fskCode);

      try {
        const res = await fetch(`${App.basePath}/api.php?route=actors/delete-image`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Profilbild konnte nicht gelöscht werden.', 'danger');
          return;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || `${fskCode}-Profilbild gelöscht.`, 'success');
        App.handleRoute();
      } catch (err) {
        App.toast('Netzwerkfehler: ' + err.message, 'danger');
      }
    },

    async reassignMerchant(actorId) {
      const inp = document.getElementById('act-reassign-merchant-select');
      const newMerchantId = parseInt(inp?.value || '0', 10) || 0;
      if (newMerchantId <= 0) {
        App.toast('Bitte wähle zuerst einen gültigen Ziel-Händler (MID) aus der Liste aus.', 'warning');
        return;
      }

      if (!window.confirm(`Möchtest du das Darsteller-Profil #${actorId} samt aller Videos und Fotoalben wirklich dem Händler #${newMerchantId} zuweisen?`)) {
        return;
      }

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);
      fd.append('actor_id', String(actorId));
      fd.append('new_merchant_id', String(newMerchantId));

      try {
        const res = await fetch(`${App.basePath}/api.php?route=actors/reassign-merchant`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Händler-Zuweisung konnte nicht gestartet werden.', 'danger');
          return;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Händler-Zuweisung wurde in die Warteschlange eingetragen.', 'success');
        App.handleRoute();
      } catch (err) {
        App.toast('Netzwerkfehler: ' + err.message, 'danger');
      }
    }
  },

  // ========================================================================
  // 12. P4C-Merchants / Händler (#/merchants & #/merchant?id=...)
  // ========================================================================
  Merchants: {
    searchDebounceTimer: null,
    searchAbortController: null,

    buildFilterParams() {
      const form = document.getElementById('merchants-filter-form');
      const params = new URLSearchParams();
      if (!form) return params;

      const fd = new FormData(form);
      const rules = (fd.get('rules') || 'all').toString().trim();
      const activity = (fd.get('activity') || 'all').toString().trim();
      const sort = (fd.get('sort') || 'id_desc').toString().trim();
      const limit = parseInt((fd.get('limit') || '50').toString(), 10) || 50;
      const q = (fd.get('q') || '').toString().trim();

      if (rules !== 'all') params.set('rules', rules);
      if (activity !== 'all') params.set('activity', activity);
      if (sort !== 'id_desc') params.set('sort', sort);
      if (limit !== 50) params.set('limit', String(limit));
      if (q !== '') params.set('q', q);

      return params;
    },

    submitFilterForm(event) {
      if (event) event.preventDefault();
      if (this.searchDebounceTimer) {
        clearTimeout(this.searchDebounceTimer);
        this.searchDebounceTimer = null;
      }

      const params = this.buildFilterParams();
      const qs = params.toString();
      const targetHash = `#/merchants${qs ? '?' + qs : ''}`;
      if (window.location.hash === targetHash) {
        App.handleRoute();
      } else {
        window.location.hash = targetHash;
      }
      return false;
    },

    clearSearch() {
      const mainInp = document.getElementById('merchants-filter-q');
      const quickInp = document.getElementById('merchants-quick-filter');
      if (mainInp) mainInp.value = '';
      if (quickInp) quickInp.value = '';
      this.submitFilterForm();
    },

    onSearchInput(value, source = 'main') {
      const val = String(value ?? '');
      const mainInp = document.getElementById('merchants-filter-q');
      const quickInp = document.getElementById('merchants-quick-filter');

      if (source === 'main' && quickInp && quickInp.value !== val) {
        quickInp.value = val;
      } else if (source === 'quick' && mainInp && mainInp.value !== val) {
        mainInp.value = val;
      }

      // 1. Sofortiges Client-Side-Vorfiltern der bereits sichtbaren Zeilen
      this.quickFilterTable(val);

      // 2. Debounced Server-Suche über alle 750+ Partner in der Datenbank (ohne Fokusverlust)
      if (this.searchDebounceTimer) {
        clearTimeout(this.searchDebounceTimer);
      }
      this.searchDebounceTimer = setTimeout(() => {
        this.performLiveServerSearch(source);
      }, 300);
    },

    async performLiveServerSearch(source = 'main') {
      const form = document.getElementById('merchants-filter-form');
      if (!form) return;

      if (this.searchAbortController) {
        this.searchAbortController.abort();
      }
      this.searchAbortController = new AbortController();

      const params = this.buildFilterParams();
      const qs = params.toString();
      const targetHash = `#/merchants${qs ? '?' + qs : ''}`;
      window.history.replaceState(null, '', targetHash);

      const url = `${App.basePath}/api.php?route=merchants${qs ? '&' + qs : ''}`;

      try {
        const res = await fetch(url, { signal: this.searchAbortController.signal });
        if (!res.ok) return;
        const html = await res.text();

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newTableCard = doc.querySelector('.card-kkc.overflow-hidden');
        const currentTableCard = document.querySelector('#app-content .card-kkc.overflow-hidden');

        if (newTableCard && currentTableCard) {
          const activeId = document.activeElement?.id || '';
          const selStart = document.activeElement?.selectionStart ?? null;
          const selEnd = document.activeElement?.selectionEnd ?? null;

          currentTableCard.innerHTML = newTableCard.innerHTML;

          const currentQ = document.getElementById('merchants-filter-q')?.value || '';
          const newQuickInp = document.getElementById('merchants-quick-filter');
          if (newQuickInp && newQuickInp.value !== currentQ) {
            newQuickInp.value = currentQ;
          }

          if (activeId === 'merchants-quick-filter' || source === 'quick') {
            const focusEl = document.getElementById(activeId || 'merchants-quick-filter');
            if (focusEl) {
              focusEl.focus();
              if (selStart !== null && selEnd !== null) {
                try { focusEl.setSelectionRange(selStart, selEnd); } catch (_) {}
              }
            }
          }
        }
        App.resetLogoutCountdown();
      } catch (err) {
        if (err && err.name === 'AbortError') return;
      }
    },

    quickFilterTable(query) {
      const raw = String(query || '').trim().toLowerCase();
      const tokens = raw === '' ? [] : raw.split(/\s+/).filter(Boolean);
      let visibleCount = 0;

      document.querySelectorAll('.merchant-list-row').forEach(row => {
        const hay = (row.getAttribute('data-search') || '').toLowerCase();
        const matches = tokens.every(tok => {
          const cleanTok = tok.replace(/^(?:mid|pid|id|#)[:\s#-]*/i, '');
          return hay.includes(tok) || (cleanTok !== '' && hay.includes(cleanTok));
        });
        row.classList.toggle('d-none', !matches);
        if (matches) visibleCount++;
      });

      const emptyRow = document.getElementById('merchants-quick-empty-row');
      if (emptyRow) {
        emptyRow.classList.toggle('d-none', !(tokens.length > 0 && visibleCount === 0));
      }
    },

    filterSubTable(rowClass, query) {
      const q = String(query || '').trim().toLowerCase();
      document.querySelectorAll(`.${rowClass}`).forEach(row => {
        const hay = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', !(q === '' || hay.includes(q)));
      });
    },

    toggleApiKeyVisibility() {
      const inp = document.getElementById('merchant-api-key-input');
      const icon = document.getElementById('merchant-api-key-icon');
      if (!inp) return;
      if (inp.type === 'password') {
        inp.type = 'text';
        if (icon) icon.className = 'bi bi-eye-slash';
      } else {
        inp.type = 'password';
        if (icon) icon.className = 'bi bi-eye';
      }
    },

    async copyApiKey() {
      const inp = document.getElementById('merchant-api-key-input');
      const val = inp?.value || '';
      if (!val) {
        App.toast('Kein API-Key vorhanden.', 'warning');
        return;
      }
      try {
        await navigator.clipboard.writeText(val);
        App.toast('API-Key in die Zwischenablage kopiert.', 'success');
      } catch (_) {
        App.toast('Kopieren fehlgeschlagen.', 'danger');
      }
    },

    async saveSettings(event) {
      if (event) event.preventDefault();
      const form = document.getElementById('merchant-settings-form');
      const btn = document.getElementById('btn-save-merchant-settings');
      if (!form) return false;

      const fd = new FormData(form);
      fd.set('_csrf', App.csrfToken);

      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Speichere...';
      }

      try {
        const res = await fetch(`${App.basePath}/api.php?route=merchants/save-settings`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Einstellungen konnten nicht gespeichert werden.', 'danger');
          return false;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Händler-Einstellungen erfolgreich gespeichert.', 'success');
        const kpiEl = document.getElementById('merch-kpi-limit');
        if (kpiEl && data.minimum_number_actor_profiles) {
          kpiEl.textContent = String(data.minimum_number_actor_profiles);
        }
      } catch (err) {
        App.toast('Netzwerkfehler: ' + err.message, 'danger');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-floppy-fill me-1"></i>Einstellungen speichern';
        }
      }
      return false;
    },

    async impersonateMerchant(merchantId, username, targetRoute = 'startseite') {
      if (!window.confirm(
        `Möchtest du dich jetzt als Partner „${username}“ (#${merchantId}) im Partner-Backend (MCP) einloggen?\n\n` +
        `Das Partner-Dashboard wird in einem neuen Tab geöffnet. Der Zugriff wird im Mitarbeiter-Audit-Log protokolliert.`
      )) {
        return;
      }

      const newWin = window.open('about:blank', '_blank');

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);
      fd.append('merchant_id', String(merchantId));
      fd.append('target_route', String(targetRoute || 'startseite'));

      try {
        const res = await fetch(`${App.basePath}/api.php?route=merchants/impersonate`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok' || !data.redirect_url) {
          if (newWin && !newWin.closed) newWin.close();
          App.toast(data.message || 'Partner-Session konnte nicht übernommen werden.', 'danger');
          return;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || `Session von Partner ${username} geöffnet.`, 'success');

        if (newWin && !newWin.closed) {
          newWin.location.href = data.redirect_url;
        } else {
          window.location.href = data.redirect_url;
        }
      } catch (err) {
        if (newWin && !newWin.closed) newWin.close();
        App.toast('Netzwerkfehler bei Partner-Session-Übernahme: ' + err.message, 'danger');
      }
    }
  },

  // ========================================================================
  // 13. System & Technik (#/system-settings & #/errors)
  // ========================================================================
  SystemTech: {
    previewDebounceTimer: null,

    updateLiveFooterPreview() {
      const val = (id) => (document.getElementById(id)?.value || '').trim();
      const setTxt = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
      };

      const email = val('imp-email');
      setTxt('prev-imp-email-1', email);
      setTxt('prev-imp-email-2', email);
      setTxt('prev-imp-company', val('imp-company'));
      setTxt('prev-imp-street', val('imp-street'));
      setTxt('prev-imp-city', val('imp-city'));
      setTxt('prev-imp-court', val('imp-court'));
      setTxt('prev-imp-regdetails', val('imp-regdetails'));
      setTxt('prev-imp-vat', val('imp-vat'));
      setTxt('prev-imp-ceo', val('imp-ceo'));
      setTxt('prev-imp-phone', val('imp-phone'));

      const emailLink = document.getElementById('prev-imp-email-link');
      if (emailLink) emailLink.href = `mailto:${email}`;
      const extLink = document.getElementById('prev-imp-ext-link');
      if (extLink) extLink.href = val('imp-ext-url') || '#';
      const waBtn = document.getElementById('prev-imp-wa-btn');
      if (waBtn) waBtn.href = val('imp-wa-url') || '#';
      const webBtn = document.getElementById('prev-imp-web-btn');
      if (webBtn) webBtn.href = val('imp-web-url') || '#';
    },

    async saveImprint(event, resetDefaults = false) {
      if (event) event.preventDefault();
      const form = document.getElementById('sys-imprint-form');
      const btn = document.getElementById('btn-save-imprint');
      if (!form) return false;

      const fd = new FormData(form);
      fd.set('_csrf', App.csrfToken);
      if (resetDefaults) {
        fd.set('reset_defaults', '1');
      }

      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Speichere...';
      }

      try {
        const res = await fetch(`${App.basePath}/api.php?route=system-settings/save-imprint`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Fehler beim Speichern der Impressums-Daten.', 'danger');
          return false;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Impressums- & Support-Daten gespeichert.', 'success');

        if (resetDefaults && data.imprint) {
          App.handleRoute();
        }
      } catch (err) {
        App.toast('Netzwerkfehler: ' + err.message, 'danger');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-floppy-fill me-1"></i>Impressums- & Supportdaten speichern';
        }
      }
      return false;
    },

    resetImprintDefaults() {
      if (!window.confirm('Möchtest du alle Impressums- und Support-Felder auf die Pay4Coins-Standardwerte zurücksetzen?')) {
        return;
      }
      this.saveImprint(null, true);
    },

    debouncedPreviewMail() {
      if (this.previewDebounceTimer) {
        clearTimeout(this.previewDebounceTimer);
      }
      this.previewDebounceTimer = setTimeout(() => {
        this.sendOrPreviewTestMail(null, 'preview');
      }, 280);
    },

    switchMailPreviewMode(mode) {
      const htmlWrap = document.getElementById('sys-mail-preview-html-wrap');
      const textWrap = document.getElementById('sys-mail-preview-text-wrap');
      const btnHtml = document.getElementById('btn-view-mail-html');
      const btnText = document.getElementById('btn-view-mail-text');

      if (mode === 'text') {
        if (htmlWrap) htmlWrap.classList.add('d-none');
        if (textWrap) textWrap.classList.remove('d-none');
        if (btnHtml) btnHtml.className = 'btn btn-outline-kkc';
        if (btnText) btnText.className = 'btn btn-primary-kkc';
      } else {
        if (htmlWrap) htmlWrap.classList.remove('d-none');
        if (textWrap) textWrap.classList.add('d-none');
        if (btnHtml) btnHtml.className = 'btn btn-primary-kkc';
        if (btnText) btnText.className = 'btn btn-outline-kkc';
      }
    },

    async sendOrPreviewTestMail(event, mode = 'send') {
      if (event) event.preventDefault();
      const form = document.getElementById('sys-testmail-form');
      const btn = document.getElementById('btn-send-testmail');
      if (!form) return false;

      const fd = new FormData(form);
      fd.set('_csrf', App.csrfToken);
      fd.set('mode', mode);

      if (mode === 'send' && btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sende Test-E-Mail via SMTP...';
      }

      try {
        const res = await fetch(`${App.basePath}/api.php?route=system-settings/send-test-mail`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          if (mode === 'send') {
            App.toast(data.message || 'Test-E-Mail konnte nicht gesendet werden.', 'danger');
          }
          return false;
        }

        const subjEl = document.getElementById('sys-mail-preview-subject');
        if (subjEl && typeof data.subject === 'string') {
          subjEl.textContent = data.subject;
        }
        const iframe = document.getElementById('sys-mail-preview-iframe');
        if (iframe && typeof data.html === 'string') {
          iframe.srcdoc = data.html;
        }
        const txtWrap = document.getElementById('sys-mail-preview-text-wrap');
        if (txtWrap && typeof data.text === 'string') {
          txtWrap.textContent = data.text;
        }

        if (mode === 'send') {
          App.resetLogoutCountdown();
          App.toast(data.message || 'Test-E-Mail erfolgreich gesendet.', 'success');
        }
      } catch (err) {
        if (mode === 'send') {
          App.toast('Netzwerkfehler: ' + err.message, 'danger');
        }
      } finally {
        if (mode === 'send' && btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-send-check-fill me-1"></i>Test-E-Mail („Video konvertiert“) jetzt absenden';
        }
      }
      return false;
    },

    filterNotificationLog(query) {
      const q = String(query || '').trim().toLowerCase();
      document.querySelectorAll('.sys-notif-log-row').forEach(row => {
        const hay = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', !(q === '' || hay.includes(q)));
      });
    },

    setErrorFilterSeverity(severity) {
      const tabBtn = document.getElementById('tab-btn-err-list');
      if (tabBtn && window.bootstrap?.Tab) {
        window.bootstrap.Tab.getOrCreateInstance(tabBtn).show();
      } else if (tabBtn) {
        tabBtn.click();
      }
      const sel = document.getElementById('err-filter-severity');
      if (sel) {
        sel.value = severity;
      }
      this.applyErrorFilters();
    },

    applyErrorFilters() {
      const sev = document.getElementById('err-filter-severity')?.value || 'all';
      const ctx = document.getElementById('err-filter-context')?.value || 'all';
      const q = (document.getElementById('err-filter-q')?.value || '').trim().toLowerCase();

      document.querySelectorAll('.err-entry-row').forEach(row => {
        const rowSev = row.getAttribute('data-severity') || '';
        const rowCtx = row.getAttribute('data-context') || '';
        const rowHay = row.getAttribute('data-search') || '';

        const matchSev = (sev === 'all' || rowSev === sev);
        const matchCtx = (ctx === 'all' || rowCtx === ctx);
        const matchQ = (q === '' || rowHay.includes(q));

        row.classList.toggle('d-none', !(matchSev && matchCtx && matchQ));
      });
    },

    async copyRawErrorLog() {
      const pre = document.getElementById('err-raw-console');
      const txt = pre?.textContent || '';
      if (!txt) return;
      try {
        await navigator.clipboard.writeText(txt);
        App.toast('Log-Inhalt in die Zwischenablage kopiert.', 'success');
      } catch (_) {
        App.toast('Kopieren fehlgeschlagen.', 'danger');
      }
    },

    async errorLogAction(actionType) {
      if (actionType === 'clear_log') {
        if (!window.confirm('Möchtest du das zentrale PHP-Error-Log (.errorlog) wirklich leeren? Es wird automatisch eine Sicherungskopie (.errorlog.bak) angelegt.')) {
          return;
        }
      } else if (actionType === 'restore_backup') {
        if (!window.confirm('Möchtest du die Sicherungskopie (.errorlog.bak) wiederherstellen?')) {
          return;
        }
      }

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);
      fd.append('action_type', actionType);

      try {
        const res = await fetch(`${App.basePath}/api.php?route=errors/action`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Aktion fehlgeschlagen.', 'danger');
          return;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Aktion erfolgreich ausgeführt.', 'success');
        App.handleRoute();
      } catch (err) {
        App.toast('Netzwerkfehler: ' + err.message, 'danger');
      }
    }
  },

  // ========================================================================
  // 14. Cloud-User / Endkunden (#/users & #/user?id=...)
  // ========================================================================
  Users: {
    submitFilterForm(event) {
      if (event) event.preventDefault();
      const form = document.getElementById('users-filter-form');
      if (!form) return false;

      const fd = new FormData(form);
      const params = new URLSearchParams();

      const shopId = parseInt((fd.get('shop_id') || '0').toString(), 10) || 0;
      const coins = (fd.get('coins') || 'all').toString().trim();
      const device = (fd.get('device') || 'all').toString().trim();
      const sort = (fd.get('sort') || 'lastonline_desc').toString().trim();
      const limit = parseInt((fd.get('limit') || '50').toString(), 10) || 50;
      const q = (fd.get('q') || '').toString().trim();

      if (shopId > 0) params.set('shop_id', String(shopId));
      if (coins !== 'all') params.set('coins', coins);
      if (device !== 'all') params.set('device', device);
      if (sort !== 'lastonline_desc') params.set('sort', sort);
      if (limit !== 50) params.set('limit', String(limit));
      if (q !== '') params.set('q', q);

      const qs = params.toString();
      const targetHash = `#/users${qs ? '?' + qs : ''}`;
      if (window.location.hash === targetHash) {
        App.handleRoute();
      } else {
        window.location.hash = targetHash;
      }
      return false;
    },

    quickFilterTable(query) {
      const q = String(query || '').trim().toLowerCase();
      document.querySelectorAll('.user-list-row').forEach(row => {
        const hay = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', !(q === '' || hay.includes(q)));
      });
    },

    filterSubTable(rowClass, query) {
      const q = String(query || '').trim().toLowerCase();
      document.querySelectorAll(`.${rowClass}`).forEach(row => {
        const hay = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', !(q === '' || hay.includes(q)));
      });
    },

    async toggleLock(memberId, lockFlag) {
      const isLock = parseInt(lockFlag, 10) === 1;
      const msg = isLock
        ? `Möchtest du den Cloud-User #${memberId} wirklich sperren?`
        : `Möchtest du den Cloud-User #${memberId} wieder entsperren?`;
      if (!window.confirm(msg)) {
        return;
      }

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);
      fd.append('member_id', String(memberId));
      fd.append('locked', isLock ? '1' : '0');

      try {
        const res = await fetch(`${App.basePath}/api.php?route=users/toggle-lock`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Status konnte nicht geändert werden.', 'danger');
          return;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Konto-Status aktualisiert.', isLock ? 'warning' : 'success');
        App.handleRoute();
      } catch (err) {
        App.toast('Netzwerkfehler: ' + err.message, 'danger');
      }
    }
  },

  // ===========================================================================
  // 14. MODUL: WEBSEITEN & PARTNER-SHOPS (#/sites & #/site?id=...)
  // ===========================================================================
  Websites: {
    applyFilters(page = 1) {
      const q       = (document.getElementById('sites-filter-q')?.value || '').trim();
      const status  = document.getElementById('sites-filter-status')?.value || 'all';
      const eroads  = document.getElementById('sites-filter-eroads')?.value || 'all';
      const shopSys = document.getElementById('sites-filter-shopsys')?.value || 'all';
      const banners = document.getElementById('sites-filter-banners')?.value || 'all';
      const sort    = document.getElementById('sites-filter-sort')?.value || 'id_desc';
      const limit   = parseInt(document.getElementById('sites-filter-limit')?.value || '50', 10) || 50;

      const params = new URLSearchParams();
      if (page > 1) params.set('page', String(page));
      if (status !== 'all') params.set('status', status);
      if (eroads !== 'all') params.set('eroads', eroads);
      if (shopSys !== 'all') params.set('shop_system', shopSys);
      if (banners !== 'all') params.set('banners', banners);
      if (sort !== 'id_desc') params.set('sort', sort);
      if (limit !== 50) params.set('limit', String(limit));
      if (q !== '') params.set('q', q);

      const qs = params.toString();
      const targetHash = `#/sites${qs ? '?' + qs : ''}`;
      if (window.location.hash === targetHash) {
        App.handleRoute();
      } else {
        window.location.hash = targetHash;
      }
      return false;
    },

    quickFilter(status, eroads, shopSys, banners) {
      const stEl  = document.getElementById('sites-filter-status');
      const erEl  = document.getElementById('sites-filter-eroads');
      const sysEl = document.getElementById('sites-filter-shopsys');
      const banEl = document.getElementById('sites-filter-banners');
      if (stEl) stEl.value = status;
      if (erEl) erEl.value = eroads;
      if (sysEl) sysEl.value = shopSys;
      if (banEl) banEl.value = banners;
      this.applyFilters(1);
    },

    resetFilters() {
      const qEl   = document.getElementById('sites-filter-q');
      const stEl  = document.getElementById('sites-filter-status');
      const erEl  = document.getElementById('sites-filter-eroads');
      const sysEl = document.getElementById('sites-filter-shopsys');
      const banEl = document.getElementById('sites-filter-banners');
      const srtEl = document.getElementById('sites-filter-sort');
      const limEl = document.getElementById('sites-filter-limit');

      if (qEl) qEl.value = '';
      if (stEl) stEl.value = 'all';
      if (erEl) erEl.value = 'all';
      if (sysEl) sysEl.value = 'all';
      if (banEl) banEl.value = 'all';
      if (srtEl) srtEl.value = 'id_desc';
      if (limEl) limEl.value = '50';

      if (window.location.hash === '#/sites') {
        App.handleRoute();
      } else {
        window.location.hash = '#/sites';
      }
    },

    async syncFromP4c(btnEl) {
      if (btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Synchronisiere mit Pay4Coins...';
      }

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);

      try {
        const res = await fetch(`${App.basePath}/api.php?route=sites/sync-p4c`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Synchronisation mit Pay4Coins fehlgeschlagen.', 'danger');
          if (btnEl) {
            btnEl.disabled = false;
            btnEl.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Mit Pay4Coins synchronisieren';
          }
          return;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Webseiten erfolgreich mit Pay4Coins synchronisiert.', 'success');
        App.handleRoute();
      } catch (err) {
        App.toast('Netzwerkfehler bei Pay4Coins-Synchronisation: ' + err.message, 'danger');
        if (btnEl) {
          btnEl.disabled = false;
          btnEl.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Mit Pay4Coins synchronisieren';
        }
      }
    }
  },

  // ===========================================================================
  // 15. MODUL: PARTNERPROGRAMM / EROADS (#/webmaster-partner, #/banners, #/banners-new)
  // ===========================================================================
  Affiliate: {
    filterWebmasters(event) {
      if (event) event.preventDefault();
      const month = document.getElementById('aff-period-select')?.value || '';
      const sort  = document.getElementById('aff-sort-select')?.value || 'conversions_desc';
      const q     = (document.getElementById('aff-search-input')?.value || '').trim();

      const params = new URLSearchParams();
      if (month !== '') params.set('month', month);
      if (sort !== 'conversions_desc') params.set('sort', sort);
      if (q !== '') params.set('q', q);

      const qs = params.toString();
      const targetHash = `#/webmaster-partner${qs ? '?' + qs : ''}`;
      if (window.location.hash === targetHash) {
        App.handleRoute();
      } else {
        window.location.hash = targetHash;
      }
      return false;
    },

    async crawlConversions(event) {
      if (event) event.preventDefault();
      const dateInput = document.getElementById('aff-crawl-date');
      const btnEl     = document.getElementById('aff-crawl-btn');
      const importDate = (dateInput?.value || '').trim();

      if (!importDate) {
        App.toast('Bitte wähle ein gültiges Datum für den Conversion-Crawler aus.', 'warning');
        return false;
      }

      if (btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Crawle...';
      }

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);
      fd.append('import_date', importDate);

      try {
        const res = await fetch(`${App.basePath}/api.php?route=affiliate/import-conversions`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Conversion-Crawler konnte nicht ausgeführt werden.', 'danger');
          if (btnEl) {
            btnEl.disabled = false;
            btnEl.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Abrufen';
          }
          return false;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Conversions erfolgreich gecrawlt.', 'success');
        App.handleRoute();
      } catch (err) {
        App.toast('Netzwerkfehler beim Conversion-Crawler: ' + err.message, 'danger');
        if (btnEl) {
          btnEl.disabled = false;
          btnEl.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Abrufen';
        }
      }
      return false;
    },

    filterBanners(event, page = 1) {
      if (event) event.preventDefault();
      const status = document.getElementById('ban-status-select')?.value || 'published';
      const siteId = parseInt(document.getElementById('ban-site-select')?.value || '0', 10) || 0;
      const dim    = document.getElementById('ban-dim-select')?.value || '';
      const type   = document.getElementById('ban-type-select')?.value || '';
      const q      = (document.getElementById('ban-search-input')?.value || '').trim();

      const params = new URLSearchParams();
      if (page > 1) params.set('page', String(page));
      if (status !== 'published') params.set('status', status);
      if (siteId > 0) params.set('site_id', String(siteId));
      if (dim !== '') params.set('dim', dim);
      if (type !== '') params.set('type', type);
      if (q !== '') params.set('q', q);

      const qs = params.toString();
      const targetHash = `#/banners${qs ? '?' + qs : ''}`;
      if (window.location.hash === targetHash) {
        App.handleRoute();
      } else {
        window.location.hash = targetHash;
      }
      return false;
    },

    quickFilterBanners(status) {
      const stEl = document.getElementById('ban-status-select');
      if (stEl) stEl.value = status;
      this.filterBanners(null, 1);
    },

    goToBannersPage(page) {
      this.filterBanners(null, Math.max(1, parseInt(page, 10) || 1));
    },

    async handleBannerAction(fileId, action, isNewReview = false) {
      if (!fileId || !action) return;

      const confirmLabels = {
        unlock: `Möchtest du das neue Banner (${fileId}) jetzt freischalten und für alle Webmaster sichtbar machen?`,
        reject: `Möchtest du das neue Banner (${fileId}) wirklich ablehnen?`,
        delete: `Möchtest du das Banner (${fileId}) wirklich unwiderruflich löschen?`
      };

      if (action === 'delete' || action === 'reject') {
        if (!window.confirm(confirmLabels[action] || 'Aktion ausführen?')) {
          return;
        }
      }

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);
      fd.append('file_id', fileId);
      fd.append('action', action);

      try {
        const res = await fetch(`${App.basePath}/api.php?route=banners/action`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Banner-Aktion fehlgeschlagen.', 'danger');
          return;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Banner-Aktion erfolgreich ausgeführt.', 'success');
        if (typeof App.refreshSidebarCounts === 'function') {
          App.refreshSidebarCounts();
        }

        const targetEl = document.getElementById(isNewReview ? `pending-banner-card-${fileId}` : `banner-row-${fileId}`);
        if (targetEl) {
          targetEl.style.transition = 'opacity 0.2s ease';
          targetEl.style.opacity = '0';
          setTimeout(() => App.handleRoute(), 200);
        } else {
          App.handleRoute();
        }
      } catch (err) {
        App.toast('Netzwerkfehler bei Banner-Aktion: ' + err.message, 'danger');
      }
    }
  },

  // ===========================================================================
  // 16. MODUL: MITARBEITER, ADMINISTRATOREN & MODERATOREN (#/employees & #/employee?id=...)
  // ===========================================================================
  Employees: {
    submitFilterForm(event) {
      if (event) event.preventDefault();
      const form = document.getElementById('employees-filter-form');
      if (!form) return false;

      const fd = new FormData(form);
      const params = new URLSearchParams();

      const rule   = (fd.get('rule') || 'all').toString().trim();
      const access = (fd.get('access') || 'all').toString().trim();
      const sort   = (fd.get('sort') || 'id_asc').toString().trim();
      const q      = (fd.get('q') || '').toString().trim();

      if (rule !== 'all') params.set('rule', rule);
      if (access !== 'all') params.set('access', access);
      if (sort !== 'id_asc') params.set('sort', sort);
      if (q !== '') params.set('q', q);

      const qs = params.toString();
      const targetHash = `#/employees${qs ? '?' + qs : ''}`;
      if (window.location.hash === targetHash) {
        App.handleRoute();
      } else {
        window.location.hash = targetHash;
      }
      return false;
    },

    quickFilterTable(query) {
      const q = String(query || '').trim().toLowerCase();
      document.querySelectorAll('.employee-list-row').forEach(row => {
        const hay = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', !(q === '' || hay.includes(q)));
      });
    },

    filterHistoryTable(query) {
      const q = String(query || '').trim().toLowerCase();
      document.querySelectorAll('.emp-history-row').forEach(row => {
        const hay = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', !(q === '' || hay.includes(q)));
      });
    },

    generatePassword() {
      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*+-=';
      let pwd = '';
      const randomValues = new Uint32Array(16);
      if (window.crypto && window.crypto.getRandomValues) {
        window.crypto.getRandomValues(randomValues);
        for (let i = 0; i < 16; i++) {
          pwd += chars[randomValues[i] % chars.length];
        }
      } else {
        for (let i = 0; i < 16; i++) {
          pwd += chars[Math.floor(Math.random() * chars.length)];
        }
      }

      const pwInput  = document.getElementById('emp-password-input');
      const pwRepInp = document.getElementById('emp-password-confirm-input');
      const eyeIcon  = document.getElementById('emp-pw-eye-icon');

      if (pwInput) {
        pwInput.value = pwd;
        pwInput.type = 'text';
      }
      if (pwRepInp) {
        pwRepInp.value = pwd;
        pwRepInp.type = 'text';
      }
      if (eyeIcon) {
        eyeIcon.className = 'bi bi-eye-slash';
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(pwd).then(() => {
          App.toast('Sicheres Passwort generiert und direkt in die Zwischenablage kopiert.', 'success');
        }).catch(() => {
          App.toast('Sicheres Passwort wurde generiert.', 'info');
        });
      } else {
        App.toast('Sicheres Passwort wurde generiert.', 'info');
      }
    },

    togglePasswordVisibility() {
      const pwInput  = document.getElementById('emp-password-input');
      const pwRepInp = document.getElementById('emp-password-confirm-input');
      const eyeIcon  = document.getElementById('emp-pw-eye-icon');
      if (!pwInput) return;

      const show = pwInput.type === 'password';
      pwInput.type = show ? 'text' : 'password';
      if (pwRepInp) pwRepInp.type = show ? 'text' : 'password';
      if (eyeIcon) eyeIcon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    },

    async copyPassword() {
      const pwInput = document.getElementById('emp-password-input');
      const val = (pwInput?.value || '').trim();
      if (!val) {
        App.toast('Das Passwort-Feld ist aktuell leer. Generiere oder tippe zuerst ein Passwort ein.', 'warning');
        return;
      }
      try {
        await navigator.clipboard.writeText(val);
        App.toast('Passwort in die Zwischenablage kopiert.', 'success');
      } catch (_) {
        App.toast('Kopieren in die Zwischenablage fehlgeschlagen.', 'danger');
      }
    },

    applyPreset(rule, isAll, areas) {
      const ruleWrite = document.getElementById('emp-rule-write');
      const ruleRead  = document.getElementById('emp-rule-read');
      if (rule === 'write' && ruleWrite && !ruleWrite.disabled) {
        ruleWrite.checked = true;
      } else if (rule === 'read' && ruleRead && !ruleRead.disabled) {
        ruleRead.checked = true;
      }

      const allSwitch = document.getElementById('emp-access-all-switch');
      if (allSwitch && !allSwitch.disabled) {
        allSwitch.checked = !!isAll;
      }

      const areaSet = new Set(Array.isArray(areas) ? areas : []);
      document.querySelectorAll('.emp-area-checkbox').forEach(cb => {
        if (!cb.disabled) {
          cb.checked = !!isAll || areaSet.has(cb.value);
        }
      });

      this.onToggleAllAccess(!!isAll);
      App.toast('Rollen-Vorlage übernommen. Klicke unten auf „Speichern“, um die Berechtigungen zu aktivieren.', 'info');
    },

    onToggleAllAccess(checked) {
      const box  = document.getElementById('emp-all-access-box');
      const grid = document.getElementById('emp-individual-areas-grid');

      if (box) {
        box.classList.toggle('border-success', !!checked);
        box.classList.toggle('bg-success', !!checked);
        box.classList.toggle('bg-opacity-10', !!checked);
      }
      if (grid) {
        grid.style.opacity = checked ? '0.55' : '1';
      }
      if (checked) {
        document.querySelectorAll('.emp-area-checkbox').forEach(cb => {
          cb.checked = true;
        });
      }
    },

    selectAllIndividualAreas(checked) {
      const allSwitch = document.getElementById('emp-access-all-switch');
      if (!checked && allSwitch) {
        allSwitch.checked = false;
        this.onToggleAllAccess(false);
      }
      document.querySelectorAll('.emp-area-checkbox').forEach(cb => {
        if (!cb.disabled) {
          cb.checked = !!checked;
        }
      });
    },

    onIndividualAreaChange() {
      const allCbs = Array.from(document.querySelectorAll('.emp-area-checkbox'));
      const anyUnchecked = allCbs.some(cb => !cb.checked);
      const allSwitch = document.getElementById('emp-access-all-switch');
      if (anyUnchecked && allSwitch && allSwitch.checked) {
        allSwitch.checked = false;
        const box  = document.getElementById('emp-all-access-box');
        const grid = document.getElementById('emp-individual-areas-grid');
        if (box) {
          box.classList.remove('border-success', 'bg-success', 'bg-opacity-10');
        }
        if (grid) {
          grid.style.opacity = '1';
        }
      }
    },

    async saveEmployee(event) {
      if (event) event.preventDefault();
      const form = document.getElementById('employee-edit-form');
      const btn  = document.getElementById('btn-save-employee');
      if (!form) return false;

      const fd = new FormData(form);
      fd.set('_csrf', App.csrfToken);

      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Speichere...';
      }

      try {
        const res = await fetch(`${App.basePath}/api.php?route=employees/save`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Mitarbeiter konnte nicht gespeichert werden.', 'danger');
          return false;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Mitarbeiter erfolgreich gespeichert.', 'success');

        if (data.is_new && data.employee_id > 0) {
          window.location.hash = `#/employee?id=${data.employee_id}`;
        } else {
          App.handleRoute();
        }
      } catch (err) {
        App.toast('Netzwerkfehler beim Speichern: ' + err.message, 'danger');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-floppy-fill me-1"></i>Änderungen speichern';
        }
      }
      return false;
    },

    async deleteEmployee(employeeId, username, redirectAfter = false) {
      const empId = parseInt(employeeId, 10) || 0;
      if (empId <= 0) return;

      if (!window.confirm(`Möchtest du den Mitarbeiter-Account „${username}“ (#${empId}) wirklich dauerhaft löschen?`)) {
        return;
      }

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);
      fd.append('employee_id', String(empId));

      try {
        const res = await fetch(`${App.basePath}/api.php?route=employees/delete`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Mitarbeiter konnte nicht gelöscht werden.', 'danger');
          return;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Mitarbeiter-Account gelöscht.', 'success');

        if (redirectAfter) {
          window.location.hash = '#/employees';
        } else {
          const rowEl = document.getElementById(`employee-row-${empId}`);
          if (rowEl) {
            rowEl.style.transition = 'opacity 0.2s ease';
            rowEl.style.opacity = '0';
            setTimeout(() => App.handleRoute(), 200);
          } else {
            App.handleRoute();
          }
        }
      } catch (err) {
        App.toast('Netzwerkfehler beim Löschen: ' + err.message, 'danger');
      }
    },

    async clearEmployeeHistory(employeeId, mode = 'older_30d') {
      const empId = parseInt(employeeId, 10) || 0;
      if (empId <= 0) return;

      const msg = mode === 'all'
        ? `Möchtest du wirklich die gesamte Aktivitäts-Historie von Mitarbeiter #${empId} leeren?`
        : `Möchtest du alle Historien-Einträge von Mitarbeiter #${empId}, die älter als 30 Tage sind, bereinigen?`;

      if (!window.confirm(msg)) {
        return;
      }

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);
      fd.append('employee_id', String(empId));
      fd.append('mode', mode);

      try {
        const res = await fetch(`${App.basePath}/api.php?route=employees/clear-history`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Historie konnte nicht bereinigt werden.', 'danger');
          return;
        }

        App.resetLogoutCountdown();
        App.toast(data.message || 'Historie erfolgreich bereinigt.', 'success');
        App.handleRoute();
      } catch (err) {
        App.toast('Netzwerkfehler: ' + err.message, 'danger');
      }
    },

    async impersonateEmployee(employeeId, username) {
      const empId = parseInt(employeeId, 10) || 0;
      if (empId <= 0) return;

      if (!window.confirm(`Möchtest du jetzt die Session von „${username}“ (#${empId}) übernehmen und dich in diesen Mitarbeiter-Account einloggen?`)) {
        return;
      }

      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);
      fd.append('employee_id', String(empId));

      try {
        const res = await fetch(`${App.basePath}/api.php?route=employees/impersonate`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Session-Übernahme fehlgeschlagen.', 'danger');
          return;
        }

        App.toast(data.message || `Session von ${username} übernommen.`, 'success');
        window.location.hash = '#/startseite';
        setTimeout(() => window.location.reload(), 250);
      } catch (err) {
        App.toast('Netzwerkfehler bei der Session-Übernahme: ' + err.message, 'danger');
      }
    },

    async stopImpersonation() {
      const fd = new FormData();
      fd.append('_csrf', App.csrfToken);

      try {
        const res = await fetch(`${App.basePath}/api.php?route=employees/stop-impersonate`, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': App.csrfToken
          },
          body: fd
        });
        const data = await res.json();

        if (!res.ok || data.status !== 'ok') {
          App.toast(data.message || 'Rückkehr zur Admin-Session fehlgeschlagen.', 'danger');
          return;
        }

        App.toast(data.message || 'Deine eigene Admin-Session wurde wiederhergestellt.', 'success');
        window.location.hash = '#/employees';
        setTimeout(() => window.location.reload(), 250);
      } catch (err) {
        App.toast('Netzwerkfehler: ' + err.message, 'danger');
      }
    }
  }
};

document.addEventListener('DOMContentLoaded', () => App.init());
