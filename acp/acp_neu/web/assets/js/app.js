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
      'movie-edit': 'movies-online',
      'photo-album-edit': 'photo-albums-online',
      'actor': 'actors',
      'site': 'sites',
      'merchant': 'merchants',
      'user': 'users'
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

  seekMoviePlayer(seconds) {
    const tabBtn = document.getElementById('tab-btn-review');
    if (tabBtn && !tabBtn.classList.contains('active')) {
      tabBtn.click();
    }
    const video = document.getElementById('mc-video-player');
    if (!video) return;
    video.currentTime = Math.max(0, Number(seconds) || 0);
    video.play().catch(() => {});
    video.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
    const q = (document.getElementById('mo-filter-q')?.value || '').trim();
    const status = document.getElementById('mo-filter-status')?.value || 'all';
    const cat = document.getElementById('mo-filter-cat')?.value || 'all';
    const quality = document.getElementById('mo-filter-quality')?.value || 'all';
    const lang = document.getElementById('mo-filter-lang')?.value || 'all';
    const bram = document.getElementById('mo-filter-bram')?.value || 'all';
    const sort = document.getElementById('mo-filter-sort')?.value || 'id_desc';
    const perPage = document.getElementById('mo-filter-per-page')?.value || '100';
    const page = pageOverride ? Number(pageOverride) : 1;

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
    const cat = document.getElementById('pao-filter-cat')?.value || 'all';
    const sort = document.getElementById('pao-filter-sort')?.value || 'id_desc';
    const perPage = document.getElementById('pao-filter-per-page')?.value || '100';
    const page = pageOverride ? Number(pageOverride) : 1;

    const params = new URLSearchParams();
    if (q !== '') params.set('q', q);
    if (status !== 'all') params.set('status', status);
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
      if (q.length < 2) {
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
  }
};

document.addEventListener('DOMContentLoaded', () => App.init());
