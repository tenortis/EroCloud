/**
 * EroCloud MCP (v2.0) Application Client
 * Reines Vanilla JS (ES6+, kein jQuery)
 * SPA-Architektur mit dynamischem Fragment-Loading (analog KKC-EroCMS).
 */

const App = {
  csrfToken: '',
  basePath: '',
  usernameCheckTimer: null,

  init() {
    this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    this.basePath = document.querySelector('meta[name="app-base-path"]')?.getAttribute('content') || '';
    this.initTheme();
    this.initRouter();
    this.initApiKeyCopy();
    this.initNotificationsModal();
    this.initGlobalTopbarSearch();
    this.initSessionKeepAlive();
  },

  initNotificationsModal() {
    const form = document.getElementById('form-mcp-notifications');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btn-save-mcp-notifications');
      if (btn) btn.disabled = true;

      try {
        const formData = new FormData(form);
        formData.append('_csrf', this.csrfToken);

        const res = await fetch(`${this.basePath}/api.php?route=home/save-notifications`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrfToken },
          body: formData
        });
        const data = await res.json();

        if (res.ok && data.status === 'ok') {
          this.toast(data.message || 'Deine Benachrichtigungs-Einstellungen wurden gespeichert.', 'success');
          const modalEl = document.getElementById('modal-notifications');
          if (modalEl && window.bootstrap?.Modal) {
            window.bootstrap.Modal.getInstance(modalEl)?.hide();
          }
          // Falls gerade Schritt 2 des Video-Uploads geöffnet ist, Schalter synchronisieren
          const uploadSwitch = document.getElementById('movie-notify-on-convert');
          const movieConvEmail = document.getElementById('notif_email_movie_converted');
          if (uploadSwitch && movieConvEmail) {
            uploadSwitch.checked = movieConvEmail.checked;
          }
        } else {
          this.toast(data.message || 'Fehler beim Speichern der Einstellungen.', 'danger');
        }
      } catch (err) {
        this.toast('Verbindungsfehler: ' + err.message, 'danger');
      } finally {
        if (btn) btn.disabled = false;
      }
    });
  },

  // ========================================================================
  // 0. Session Keep-Alive (Heartbeat alle 4 Minuten gegen GC-Timeout)
  // ========================================================================
  initSessionKeepAlive() {
    setInterval(() => {
      fetch(`${this.basePath}/api.php?route=ping`, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store'
      }).catch(() => {});
    }, 4 * 60 * 1000);
  },

  // ========================================================================
  // 1. Theme Management (Standard: Hellmodus / Light Mode)
  // ========================================================================
  initTheme() {
    const html = document.documentElement;
    const toggleBtn = document.getElementById('theme-toggle');
    const savedTheme = localStorage.getItem('ec-mcp-theme') || 'light';

    html.setAttribute('data-bs-theme', savedTheme);
    this.updateThemeIcon(savedTheme);

    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        const current = html.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('ec-mcp-theme', next);
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
  // 2. SPA Router & Fragment Loading (Kein Seiten-Reload!)
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
    const rawHash = window.location.hash.replace(/^#\/?/, '') || 'startseite';
    const [pathPart, queryPart] = rawHash.split('?');
    const params = new URLSearchParams(queryPart || '');
    const apiRoute = pathPart || 'startseite';

    // Veraltete Prototyp-Parameter (wie &name=LadyAyse) automatisch aus der Adresszeile bereinigen
    if (pathPart === 'actor' && params.has('name')) {
      params.delete('name');
      const cleanQuery = params.toString();
      const cleanHash = `#/actor${cleanQuery ? '?' + cleanQuery : ''}`;
      window.history.replaceState(null, '', cleanHash);
    }

    // Aktiven Sidebar-Link hervorheben & nur das zugehörige Submenü öffnen
    let activeCollapseId = null;
    const targetActorId = pathPart === 'actor' ? params.get('id') : null;

    document.querySelectorAll('.sidebar-link[data-route]').forEach(link => {
      const linkRoute = (link.getAttribute('data-route') || '').replace(/^#\/?/, '');
      const linkActorId = link.getAttribute('data-actor-id');

      const isMatch = (targetActorId && linkActorId === targetActorId)
        || (!targetActorId && (linkRoute === rawHash || linkRoute === pathPart));

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

    // Falls eine Unterseite direkt aufgerufen wird, das passende Submenü öffnen
    if (!activeCollapseId) {
      if (['actors', 'new-actor', 'actor-new', 'actor'].includes(pathPart)) {
        activeCollapseId = 'submenu-actors';
      } else if (['movies', 'movie', 'photo-albums', 'photo-album'].includes(pathPart)) {
        activeCollapseId = 'submenu-cloud';
      } else if (['movie-upload', 'photo-album-upload'].includes(pathPart)) {
        activeCollapseId = 'submenu-upload';
      }
    }

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

    this.hideThumbZoomPopover();
    if (typeof this.hideGlobalSearchDropdown === 'function') {
      this.hideGlobalSearchDropdown();
    }

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
        // Bei abgelaufener Sitzung (401) einmalig aktuellen Pfad neu laden (triggert Auto-Recovery in bootstrap.php)
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

      // Seitenbreite steuern: Standardmäßig begrenzt (1440px), optional volle Breite via data-page-width="full"
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

  // ========================================================================
  // 3. API-Key Kopierfunktion & Dynamische Fragment-Events
  // ========================================================================
  initApiKeyCopy() {
    const copyBtn = document.getElementById('btn-copy-api-key');
    const input = document.getElementById('input-api-key');
    if (!copyBtn || !input) return;

    copyBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(input.value);
        this.toast('Dein API-Key wurde in die Zwischenablage kopiert.', 'success');
      } catch (_) {
        input.select();
        document.execCommand('copy');
        this.toast('Dein API-Key wurde in die Zwischenablage kopiert.', 'success');
      }
    });
  },

  initDynamicEvents() {
    this.initContentRulesEvents();
    this.initActorsEvents();
    this.initSmartContentTable();
    this.initThumbnailHoverZoom();
    this.initMoviesEvents();
    this.initPhotoAlbumsEvents();
    this.initCreatorFeedbackEvents();
  },

  searchCatalog: null,
  searchCatalogPromise: null,

  invalidateSearchCatalog() {
    this.searchCatalog = null;
    this.searchCatalogPromise = null;
  },

  hideGlobalSearchDropdown() {
    const dropdown = document.getElementById('global-search-dropdown');
    if (dropdown) {
      dropdown.classList.add('d-none');
    }
  },

  // Globale Schnellsuche in der Topbar: Lädt den kompletten Katalog (Filme & Fotoalben) einmalig in den
  // Browser-Speicher und filtert anschließend jeden Tastendruck verzögerungsfrei (< 1 ms, 0 Netzwerk-Latenz)
  initGlobalTopbarSearch() {
    const wrapper = document.getElementById('global-search-wrapper');
    const globalInput = document.getElementById('global-search-input');
    const clearBtn = document.getElementById('global-search-clear');
    const dropdown = document.getElementById('global-search-dropdown');
    if (!globalInput || !dropdown) return;

    let lastData = null;
    let activeFilter = 'all'; // 'all' | 'movie' | 'photo_album'
    let activeIdx = -1;

    const prepareCatalogItems = (rawItems) => {
      if (!Array.isArray(rawItems)) return [];
      return rawItems.map(item => {
        const idNum = parseInt(item.id, 10) || 0;
        const idStr = String(idNum);
        const titleLower = String(item.title || '').toLowerCase();
        const actorLower = String(item.actor_name || '').toLowerCase();
        const codeLower = String(item.code || '').toLowerCase();
        const statusLower = String(item.status_label || '').toLowerCase();
        const onlineStr = String(item.online_at || '').toLowerCase();
        const typeWords = item.type === 'movie'
          ? 'film filme video'
          : 'fotoalbum foto-album fotoalben fotos bilder';

        return {
          ...item,
          id: idNum,
          _idStr: idStr,
          _titleLower: titleLower,
          _actorLower: actorLower,
          _codeLower: codeLower,
          _searchable: `#${idStr} ${idStr} ${codeLower} ${titleLower} ${actorLower} ${statusLower} ${onlineStr} ${typeWords}`
        };
      });
    };

    const ensureCatalogLoaded = async () => {
      if (Array.isArray(this.searchCatalog)) {
        return this.searchCatalog;
      }
      if (this.searchCatalogPromise) {
        return this.searchCatalogPromise;
      }

      this.searchCatalogPromise = (async () => {
        try {
          const res = await fetch(`${this.basePath}/api.php?route=home/global-search&mode=catalog`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });
          if (!res.ok) {
            this.searchCatalogPromise = null;
            return null;
          }
          const data = await res.json();
          if (data && data.status === 'ok' && Array.isArray(data.items)) {
            this.searchCatalog = prepareCatalogItems(data.items);
            return this.searchCatalog;
          }
        } catch (_) {
          // Stiller Fallback
        }
        this.searchCatalogPromise = null;
        return null;
      })();

      return this.searchCatalogPromise;
    };

    const filterCatalogLocally = (query, catalog) => {
      const qLower = query.toLowerCase().trim();
      const idQuery = qLower.replace(/^#+/, '');
      const isDigits = idQuery !== '' && /^\d+$/.test(idQuery);
      const tokens = qLower.split(/\s+/).filter(Boolean);

      const matchedMovies = [];
      const matchedAlbums = [];

      for (let i = 0; i < catalog.length; i++) {
        const item = catalog[i];
        let allMatched = true;
        for (let t = 0; t < tokens.length; t++) {
          const tok = tokens[t];
          const cleanTok = tok.replace(/^#+/, '');
          if (
            !item._searchable.includes(tok) &&
            (cleanTok === '' || !item._searchable.includes(cleanTok))
          ) {
            allMatched = false;
            break;
          }
        }
        if (!allMatched) continue;

        let score = 30;
        if (item._idStr === idQuery || item._codeLower === qLower) {
          score = 100;
        } else if (isDigits && item._idStr.startsWith(idQuery)) {
          score = 85;
        } else if (item._titleLower.startsWith(qLower)) {
          score = 75;
        } else if (item._titleLower.includes(qLower)) {
          score = 60;
        } else if (item._actorLower.includes(qLower)) {
          score = 45;
        }

        const scored = { ...item, score };
        if (item.type === 'movie') {
          matchedMovies.push(scored);
        } else {
          matchedAlbums.push(scored);
        }
      }

      const sortFn = (a, b) => (b.score - a.score) || (b.id - a.id);
      matchedMovies.sort(sortFn);
      matchedAlbums.sort(sortFn);

      const combined = [
        ...matchedMovies.slice(0, 25),
        ...matchedAlbums.slice(0, 25)
      ].sort(sortFn);

      return {
        status: 'ok',
        query,
        counts: {
          total: matchedMovies.length + matchedAlbums.length,
          movies: matchedMovies.length,
          albums: matchedAlbums.length
        },
        items: combined
      };
    };

    const getFilteredItems = () => {
      if (!lastData || !Array.isArray(lastData.items)) return [];
      if (activeFilter === 'movie') {
        return lastData.items.filter(i => i.type === 'movie');
      }
      if (activeFilter === 'photo_album') {
        return lastData.items.filter(i => i.type === 'photo_album');
      }
      return lastData.items;
    };

    const updateActiveHighlight = () => {
      const rows = Array.from(dropdown.querySelectorAll('.ec-search-item'));
      rows.forEach((row, idx) => {
        const isCurrent = idx === activeIdx;
        row.classList.toggle('is-active', isCurrent);
        if (isCurrent) {
          row.scrollIntoView({ block: 'nearest' });
        }
      });
    };

    const renderDropdown = () => {
      if (!lastData) return;

      const counts = lastData.counts || { total: 0, movies: 0, albums: 0 };
      const filtered = getFilteredItems();

      let listHtml = '';
      if (filtered.length === 0) {
        const filterName = activeFilter === 'movie'
          ? 'Filme'
          : (activeFilter === 'photo_album' ? 'Fotoalben' : 'Filme oder Fotoalben');
        listHtml = `
          <div class="p-4 text-center text-muted small">
            <i class="bi bi-search d-block fs-4 mb-1 opacity-50"></i>
            Keine passenden ${filterName} für „<strong>${this.escapeHtml(lastData.query || '')}</strong>“ gefunden.
          </div>
        `;
      } else {
        listHtml = filtered.map((item, idx) => {
          const isMovie = item.type === 'movie';
          const typeBadge = isMovie
            ? `<span class="ec-search-type-badge is-movie" title="Das ist ein Film"><i class="bi bi-film"></i> Film</span>`
            : `<span class="ec-search-type-badge is-album" title="Das ist ein Fotoalbum"><i class="bi bi-images"></i> Fotoalbum</span>`;

          const fallbackIcon = isMovie ? 'bi-film' : 'bi-images';
          const metaIcon = isMovie ? 'bi-clock' : 'bi-camera';
          const metaHtml = item.meta_info
            ? `<span>&bull;</span><span><i class="bi ${metaIcon} me-1"></i>${this.escapeHtml(item.meta_info)}</span>`
            : '';

          return `
            <a href="${this.escapeHtml(item.route)}"
               class="ec-search-item ${idx === activeIdx ? 'is-active' : ''}"
               data-search-idx="${idx}"
               data-target-route="${this.escapeHtml(item.route)}">
              <div class="ec-search-thumb">
                <img src="${this.escapeHtml(item.poster_url)}"
                     alt=""
                     loading="lazy"
                     onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.classList.remove('d-none');">
                <i class="bi ${fallbackIcon} text-muted d-none"></i>
              </div>
              <div class="ec-search-body">
                <div class="ec-search-title-row mb-1">
                  ${typeBadge}
                  <span class="font-monospace fw-semibold text-muted flex-shrink-0" style="font-size: 0.75rem;">#${ parseInt(item.id, 10) }</span>
                  <span class="ec-search-title" title="${this.escapeHtml(item.title)}">${this.escapeHtml(item.title)}</span>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2 text-muted text-truncate" style="font-size: 0.73rem;">
                  <span><i class="bi bi-person-circle me-1"></i>${this.escapeHtml(item.actor_name || '-')}</span>
                  ${metaHtml}
                </div>
              </div>
              <span class="badge-kkc ${this.escapeHtml(item.status_badge || 'badge-kkc-neutral')} flex-shrink-0" style="font-size: 0.68rem;">
                <i class="bi ${this.escapeHtml(item.status_icon || 'bi-circle')}"></i>
                ${this.escapeHtml(item.status_label || '')}
              </span>
            </a>
          `;
        }).join('');
      }

      let footerHtml = '';
      if (counts.movies > 0 || counts.albums > 0) {
        footerHtml = `
          <div class="ec-global-search-footer">
            <div class="d-flex align-items-center gap-3 flex-wrap">
              ${counts.movies > 0 ? `
                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 ec-text-primary fw-semibold js-global-search-jump" data-jump-route="#/movies">
                  <i class="bi bi-film me-1"></i>Alle ${counts.movies} ${counts.movies === 1 ? 'Film' : 'Filme'} in der Übersicht filtern
                </button>
              ` : ''}
              ${counts.albums > 0 ? `
                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 fw-semibold js-global-search-jump" style="color: #7C3AED;" data-jump-route="#/photo-albums">
                  <i class="bi bi-images me-1"></i>Alle ${counts.albums} ${counts.albums === 1 ? 'Fotoalbum' : 'Fotoalben'} in der Übersicht filtern
                </button>
              ` : ''}
            </div>
            <span class="text-muted ms-auto d-none d-md-inline" style="font-size: 0.68rem;">
              <kbd class="bg-transparent text-muted border px-1">↑</kbd> <kbd class="bg-transparent text-muted border px-1">↓</kbd> &amp; <kbd class="bg-transparent text-muted border px-1">Enter</kbd>
            </span>
          </div>
        `;
      }

      dropdown.innerHTML = `
        <div class="ec-global-search-header">
          <div class="d-flex align-items-center gap-1 flex-wrap">
            <button type="button" class="ec-search-filter-pill ${activeFilter === 'all' ? 'active' : ''}" data-search-filter="all">
              Alle (${counts.total})
            </button>
            <button type="button" class="ec-search-filter-pill ${activeFilter === 'movie' ? 'active' : ''}" data-search-filter="movie">
              <i class="bi bi-film"></i> Filme (${counts.movies})
            </button>
            <button type="button" class="ec-search-filter-pill ${activeFilter === 'photo_album' ? 'active' : ''}" data-search-filter="photo_album">
              <i class="bi bi-images"></i> Fotoalben (${counts.albums})
            </button>
          </div>
          <span class="text-muted d-none d-sm-inline" style="font-size: 0.68rem;">ESC schließen</span>
        </div>
        <div class="ec-global-search-list">
          ${listHtml}
        </div>
        ${footerHtml}
      `;

      dropdown.classList.remove('d-none');

      // Filter-Pills im Dropdown-Header
      dropdown.querySelectorAll('[data-search-filter]').forEach(btn => {
        btn.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          activeFilter = btn.getAttribute('data-search-filter') || 'all';
          activeIdx = -1;
          renderDropdown();
          globalInput.focus();
        });
      });

      // Klick auf einen Treffer
      dropdown.querySelectorAll('.ec-search-item').forEach(link => {
        link.addEventListener('click', (ev) => {
          const targetRoute = link.getAttribute('data-target-route') || '';
          this.hideGlobalSearchDropdown();
          if (targetRoute && window.location.hash === targetRoute) {
            ev.preventDefault();
            this.handleRoute();
          }
        });
      });

      // Fußzeilen-Buttons: In #/movies oder #/photo-albums komplett filtern
      dropdown.querySelectorAll('.js-global-search-jump').forEach(btn => {
        btn.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          const jumpRoute = btn.getAttribute('data-jump-route') || '#/movies';
          const q = globalInput.value.trim();
          this.hideGlobalSearchDropdown();

          const currentBase = '#' + (window.location.hash.replace(/^#/, '').split('?')[0] || '/startseite');
          const smartSearch = document.getElementById('smart-search-input');
          if (currentBase === jumpRoute && smartSearch) {
            smartSearch.value = q;
            smartSearch.dispatchEvent(new Event('input', { bubbles: true }));
          } else {
            this.pendingGlobalSearch = q;
            window.location.hash = jumpRoute;
          }
        });
      });
    };

    const runInstantSearch = async (query) => {
      if (Array.isArray(this.searchCatalog)) {
        lastData = filterCatalogLocally(query, this.searchCatalog);
        activeIdx = -1;
        renderDropdown();
        return;
      }

      if (dropdown.classList.contains('d-none')) {
        dropdown.innerHTML = `
          <div class="p-3 text-center text-muted small">
            <span class="spinner-border spinner-border-sm ec-text-primary me-2" role="status"></span>
            Suche nach Filmen &amp; Fotoalben...
          </div>
        `;
        dropdown.classList.remove('d-none');
      }

      const catalog = await ensureCatalogLoaded();
      const currentVal = globalInput.value.trim();
      if (currentVal === '') {
        this.hideGlobalSearchDropdown();
        return;
      }

      if (Array.isArray(catalog)) {
        lastData = filterCatalogLocally(currentVal, catalog);
        activeIdx = -1;
        renderDropdown();
      }
    };

    // Katalog im Hintergrund nach dem initialen Seitenaufbau vorladen, damit der 1. Tastendruck bereits 0ms braucht
    setTimeout(() => {
      ensureCatalogLoaded();
    }, 900);

    globalInput.addEventListener('input', () => {
      const val = globalInput.value.trim();
      if (clearBtn) {
        clearBtn.classList.toggle('d-none', val === '');
      }

      // Falls gerade die Film- oder Fotoalben-Tabelle offen ist, lokal mitfiltern
      const smartSearch = document.getElementById('smart-search-input');
      if (smartSearch) {
        smartSearch.value = globalInput.value;
        smartSearch.dispatchEvent(new Event('input', { bubbles: true }));
      }

      if (val === '') {
        lastData = null;
        this.hideGlobalSearchDropdown();
        return;
      }

      runInstantSearch(val);
    });

    globalInput.addEventListener('focus', () => {
      ensureCatalogLoaded();
      const val = globalInput.value.trim();
      if (val !== '') {
        runInstantSearch(val);
      }
    });

    globalInput.addEventListener('keydown', (e) => {
      const filtered = getFilteredItems();

      if (e.key === 'Escape') {
        this.hideGlobalSearchDropdown();
        return;
      }

      if (e.key === 'ArrowDown') {
        if (!dropdown.classList.contains('d-none') && filtered.length > 0) {
          e.preventDefault();
          activeIdx = (activeIdx + 1) % filtered.length;
          updateActiveHighlight();
        }
        return;
      }

      if (e.key === 'ArrowUp') {
        if (!dropdown.classList.contains('d-none') && filtered.length > 0) {
          e.preventDefault();
          activeIdx = activeIdx <= 0 ? filtered.length - 1 : activeIdx - 1;
          updateActiveHighlight();
        }
        return;
      }

      if (e.key === 'Enter') {
        e.preventDefault();
        if (!dropdown.classList.contains('d-none') && filtered.length > 0) {
          const chosen = filtered[activeIdx >= 0 ? activeIdx : 0];
          if (chosen && chosen.route) {
            this.hideGlobalSearchDropdown();
            if (window.location.hash === chosen.route) {
              this.handleRoute();
            } else {
              window.location.hash = chosen.route;
            }
            return;
          }
        }
        const smartSearch = document.getElementById('smart-search-input');
        if (!smartSearch && globalInput.value.trim() !== '') {
          this.pendingGlobalSearch = globalInput.value.trim();
          this.hideGlobalSearchDropdown();
          window.location.hash = '#/movies';
        }
      }
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        globalInput.value = '';
        clearBtn.classList.add('d-none');
        lastData = null;
        this.hideGlobalSearchDropdown();
        const smartSearch = document.getElementById('smart-search-input');
        if (smartSearch) {
          smartSearch.value = '';
          smartSearch.dispatchEvent(new Event('input', { bubbles: true }));
        }
        globalInput.focus();
      });
    }

    document.addEventListener('click', (e) => {
      if (wrapper && !wrapper.contains(e.target)) {
        this.hideGlobalSearchDropdown();
      }
    });
  },

  // ========================================================================
  // 3a. Universelle Smart-Table (Live-Suche, Sortierung, KPI-Filter & Pagination)
  // ========================================================================
  initSmartContentTable() {
    const container = document.getElementById('smart-table-container');
    const tbody = document.getElementById('smart-table-body');
    if (!container || !tbody) return;

    const entityLabel = container.getAttribute('data-entity-label') || 'Einträgen';
    const allRows = Array.from(tbody.querySelectorAll('.js-smart-row'));
    const totalCount = allRows.length;
    if (totalCount === 0) return;

    const statusBtns = Array.from(document.querySelectorAll('.js-smart-status-btn'));
    const kpiCards = Array.from(document.querySelectorAll('.js-smart-kpi-filter'));
    const actorFilterSelect = document.getElementById('smart-actor-filter');
    const sortSelect = document.getElementById('smart-sort-select');
    const searchInput = document.getElementById('smart-search-input');
    const searchClearBtn = document.getElementById('smart-search-clear');
    const resetFiltersBtn = document.getElementById('smart-reset-filters-btn');
    const emptyStateEl = document.getElementById('smart-table-empty');
    const paginationBar = document.getElementById('smart-pagination-bar');
    const paginationInfo = document.getElementById('smart-pagination-info');
    const paginationBtns = document.getElementById('smart-pagination-buttons');
    const pageSizeSelect = document.getElementById('smart-page-size');
    const sortableHeaders = Array.from(container.querySelectorAll('.kkc-sortable-th'));

    let state = {
      status: 'all',
      actorId: 'all',
      query: '',
      sortCol: 'id',
      sortDir: 'desc',
      page: 1,
      pageSize: 25
    };

    // Falls eine Suche aus der Topbar übergeben wurde, direkt übernehmen
    if (this.pendingGlobalSearch && searchInput) {
      searchInput.value = this.pendingGlobalSearch;
      state.query = this.pendingGlobalSearch.toLowerCase();
      this.pendingGlobalSearch = '';
      if (searchClearBtn) {
        searchClearBtn.classList.remove('d-none');
      }
    }

    const syncStatusUI = () => {
      statusBtns.forEach(btn => {
        const isActive = (btn.getAttribute('data-filter') === state.status);
        btn.classList.toggle('active', isActive);
        btn.classList.toggle('btn-primary-kkc', isActive);
        btn.classList.toggle('btn-outline-kkc', !isActive);
      });
      kpiCards.forEach(card => {
        const isActive = (card.getAttribute('data-filter') === state.status);
        card.classList.toggle('active-kpi-filter', isActive);
      });
    };

    const syncSortUI = () => {
      const combined = `${state.sortCol}:${state.sortDir}`;
      if (sortSelect && Array.from(sortSelect.options).some(o => o.value === combined)) {
        sortSelect.value = combined;
      }
      sortableHeaders.forEach(th => {
        const col = th.getAttribute('data-sort-col');
        const icon = th.querySelector('.sort-icon');
        if (col === state.sortCol) {
          th.classList.add('is-sorted');
          if (icon) {
            if (col === 'id' || col === 'online') {
              icon.className = state.sortDir === 'asc'
                ? 'bi bi-sort-numeric-down sort-icon'
                : 'bi bi-sort-numeric-down-alt sort-icon';
            } else {
              icon.className = state.sortDir === 'asc'
                ? 'bi bi-sort-alpha-down sort-icon'
                : 'bi bi-sort-alpha-down-alt sort-icon';
            }
          }
        } else {
          th.classList.remove('is-sorted');
          if (icon) {
            icon.className = 'bi bi-arrow-down-up sort-icon';
          }
        }
      });
    };

    const compareRows = (a, b) => {
      const dir = state.sortDir === 'asc' ? 1 : -1;
      const col = state.sortCol;

      if (col === 'id' || col === 'online') {
        const valA = parseInt(a.getAttribute(`data-${col}`) || '0', 10);
        const valB = parseInt(b.getAttribute(`data-${col}`) || '0', 10);
        if (valA !== valB) return (valA - valB) * dir;
      } else {
        const strA = a.getAttribute(`data-${col}`) || '';
        const strB = b.getAttribute(`data-${col}`) || '';
        const cmp = strA.localeCompare(strB, 'de', { numeric: true, sensitivity: 'base' });
        if (cmp !== 0) return cmp * dir;
      }

      // Fallback Tie-Breaker: ID absteigend
      const idA = parseInt(a.getAttribute('data-id') || '0', 10);
      const idB = parseInt(b.getAttribute('data-id') || '0', 10);
      return idB - idA;
    };

    const renderPaginationButtons = (totalPages) => {
      if (!paginationBtns) return;
      paginationBtns.innerHTML = '';
      if (totalPages <= 1) return;

      const createBtn = (labelHtml, targetPage, disabled = false, active = false, title = '') => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `kkc-page-btn${active ? ' active' : ''}`;
        btn.disabled = disabled;
        if (title) btn.title = title;
        btn.innerHTML = labelHtml;
        if (!disabled && !active) {
          btn.addEventListener('click', () => {
            state.page = targetPage;
            updateTable(false);
            container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          });
        }
        return btn;
      };

      // Zurück-Button
      paginationBtns.appendChild(
        createBtn('<i class="bi bi-chevron-left"></i>', state.page - 1, state.page <= 1, false, 'Vorherige Seite')
      );

      // Seitenzahlen mit kompakter Ellipse (max. 7 Buttons)
      const pages = [];
      if (totalPages <= 7) {
        for (let i = 1; i <= totalPages; i++) pages.push(i);
      } else {
        pages.push(1);
        if (state.page > 3) pages.push('...');
        const start = Math.max(2, state.page - 1);
        const end = Math.min(totalPages - 1, state.page + 1);
        for (let i = start; i <= end; i++) pages.push(i);
        if (state.page < totalPages - 2) pages.push('...');
        pages.push(totalPages);
      }

      pages.forEach(p => {
        if (p === '...') {
          const span = document.createElement('span');
          span.className = 'px-1 text-muted small';
          span.textContent = '…';
          paginationBtns.appendChild(span);
        } else {
          paginationBtns.appendChild(
            createBtn(String(p), p, false, p === state.page, `Seite ${p}`)
          );
        }
      });

      // Weiter-Button
      paginationBtns.appendChild(
        createBtn('<i class="bi bi-chevron-right"></i>', state.page + 1, state.page >= totalPages, false, 'Nächste Seite')
      );
    };

    const updateTable = (resortDom = false) => {
      if (searchClearBtn) {
        searchClearBtn.classList.toggle('d-none', state.query === '');
      }

      if (resortDom) {
        allRows.sort(compareRows);
        const frag = document.createDocumentFragment();
        allRows.forEach(r => frag.appendChild(r));
        tbody.appendChild(frag);
      }

      // 1. Filtern nach Status, Darsteller & Suchbegriff (inkl. #ID)
      const cleanQuery = state.query.trim();
      const matchedRows = allRows.filter(row => {
        const rStatus = row.getAttribute('data-status') || '';
        const rActorId = row.getAttribute('data-actor-id') || '';
        const rSearch = row.getAttribute('data-search') || '';

        if (state.status !== 'all' && rStatus !== state.status) return false;
        if (state.actorId !== 'all' && rActorId !== state.actorId) return false;
        if (cleanQuery !== '' && !rSearch.includes(cleanQuery)) return false;
        return true;
      });

      const matchedCount = matchedRows.length;
      const totalPages = Math.max(1, Math.ceil(matchedCount / state.pageSize));
      if (state.page > totalPages) state.page = totalPages;
      if (state.page < 1) state.page = 1;

      const startIdx = (state.page - 1) * state.pageSize;
      const endIdx = Math.min(startIdx + state.pageSize, matchedCount);
      const visibleSet = new Set(matchedRows.slice(startIdx, endIdx));

      allRows.forEach(row => {
        row.style.display = visibleSet.has(row) ? '' : 'none';
      });

      // Leerzustand bei 0 Treffern
      if (emptyStateEl) {
        emptyStateEl.classList.toggle('d-none', matchedCount > 0);
      }
      if (paginationBar) {
        paginationBar.classList.toggle('d-none', matchedCount === 0);
      }

      if (paginationInfo && matchedCount > 0) {
        const filterHint = matchedCount < totalCount ? ` (gefiltert aus ${totalCount})` : '';
        paginationInfo.textContent = `Zeige ${startIdx + 1} bis ${endIdx} von ${matchedCount} ${entityLabel}${filterHint}`;
      }

      renderPaginationButtons(totalPages);
    };

    // Event: Status-Buttons & KPI-Kacheln
    const setStatusFilter = (newStatus) => {
      state.status = newStatus || 'all';
      state.page = 1;
      syncStatusUI();
      updateTable(false);
    };

    statusBtns.forEach(btn => {
      btn.addEventListener('click', () => setStatusFilter(btn.getAttribute('data-filter')));
    });

    kpiCards.forEach(card => {
      card.addEventListener('click', () => setStatusFilter(card.getAttribute('data-filter')));
    });

    // Event: Darsteller-Filter
    if (actorFilterSelect) {
      actorFilterSelect.addEventListener('change', () => {
        state.actorId = actorFilterSelect.value || 'all';
        state.page = 1;
        updateTable(false);
      });
    }

    // Event: Sortier-Dropdown
    if (sortSelect) {
      sortSelect.addEventListener('change', () => {
        const [col, dir] = (sortSelect.value || 'id:desc').split(':');
        state.sortCol = col || 'id';
        state.sortDir = dir || 'desc';
        state.page = 1;
        syncSortUI();
        updateTable(true);
      });
    }

    // Event: Klickbare Tabellen-Spaltenköpfe zum Sortieren
    sortableHeaders.forEach(th => {
      th.addEventListener('click', () => {
        const col = th.getAttribute('data-sort-col');
        if (!col) return;
        if (state.sortCol === col) {
          state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
          state.sortCol = col;
          state.sortDir = (col === 'id' || col === 'online') ? 'desc' : 'asc';
        }
        state.page = 1;
        syncSortUI();
        updateTable(true);
      });
    });

    // Event: Echtzeit-Live-Suche
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        state.query = searchInput.value.toLowerCase();
        state.page = 1;
        updateTable(false);
      });
    }

    if (searchClearBtn && searchInput) {
      searchClearBtn.addEventListener('click', () => {
        searchInput.value = '';
        state.query = '';
        state.page = 1;
        searchInput.focus();
        updateTable(false);
      });
    }

    // Event: Filter & Suche zurücksetzen
    if (resetFiltersBtn) {
      resetFiltersBtn.addEventListener('click', () => {
        state.status = 'all';
        state.actorId = 'all';
        state.query = '';
        state.page = 1;
        if (searchInput) searchInput.value = '';
        if (actorFilterSelect) actorFilterSelect.value = 'all';
        syncStatusUI();
        updateTable(false);
      });
    }

    // Event: Einträge pro Seite (25 / 50 / 100 / Alle)
    if (pageSizeSelect) {
      pageSizeSelect.addEventListener('change', () => {
        state.pageSize = parseInt(pageSizeSelect.value || '25', 10) || 25;
        state.page = 1;
        updateTable(false);
      });
    }

    // Event: Ganze Tabellenzeile klickbar machen
    allRows.forEach(row => {
      row.addEventListener('click', (e) => {
        if (e.target.closest('a, button, input, select, label, .js-stop-row-click')) {
          return;
        }
        const editRoute = row.getAttribute('data-edit-route');
        if (editRoute) {
          this.hideThumbZoomPopover();
          window.location.hash = editRoute;
        }
      });
    });

    // Initiales Rendering
    syncStatusUI();
    syncSortUI();
    updateTable(false);
  },

  // ========================================================================
  // 3a-2. Schwebende Großbild-Vorschau (Hover-Lupe für FSK16 & FSK18 Cover)
  // ========================================================================
  initThumbnailHoverZoom() {
    const thumbs = document.querySelectorAll('.js-hover-zoom-thumb');
    if (thumbs.length === 0) return;

    let popover = document.getElementById('ec-thumb-zoom-popover');
    if (!popover) {
      popover = document.createElement('div');
      popover.id = 'ec-thumb-zoom-popover';
      popover.innerHTML = `
        <div class="zoom-img-box mb-2">
          <img id="ec-zoom-popover-img" src="" alt="Vorschau">
          <span id="ec-zoom-popover-badge" class="badge position-absolute top-0 start-0 m-2 shadow-sm">FSK 16</span>
        </div>
        <div id="ec-zoom-popover-title" class="small fw-semibold text-truncate px-1"></div>
      `;
      document.body.appendChild(popover);
    }

    const imgEl = document.getElementById('ec-zoom-popover-img');
    const badgeEl = document.getElementById('ec-zoom-popover-badge');
    const titleEl = document.getElementById('ec-zoom-popover-title');

    const positionPopover = (ev) => {
      const popWidth = 340;
      const popHeight = 235;
      const offset = 18;
      let left = ev.clientX + offset;
      let top = ev.clientY - Math.round(popHeight / 2);

      if (left + popWidth + 16 > window.innerWidth) {
        left = ev.clientX - popWidth - offset;
      }
      if (top < 12) {
        top = 12;
      } else if (top + popHeight + 12 > window.innerHeight) {
        top = window.innerHeight - popHeight - 12;
      }

      popover.style.left = `${left}px`;
      popover.style.top = `${top}px`;
    };

    thumbs.forEach(thumb => {
      thumb.addEventListener('mouseenter', (ev) => {
        if (thumb.getAttribute('data-studio-ready') === '0') return;
        const src = thumb.getAttribute('data-zoom-src');
        const fsk = thumb.getAttribute('data-zoom-fsk') || '16';
        const title = thumb.getAttribute('data-zoom-title') || '';
        if (!src || !imgEl) return;

        imgEl.src = src;
        if (badgeEl) {
          badgeEl.textContent = `FSK ${fsk} Vorschau`;
          badgeEl.className = `badge ${fsk === '18' ? 'bg-danger' : 'bg-success'} position-absolute top-0 start-0 m-2 shadow-sm`;
        }
        if (titleEl) {
          titleEl.textContent = title;
        }
        positionPopover(ev);
        popover.classList.add('is-visible');
      });

      thumb.addEventListener('mousemove', (ev) => {
        if (popover.classList.contains('is-visible')) {
          positionPopover(ev);
        }
      });

      thumb.addEventListener('mouseleave', () => {
        this.hideThumbZoomPopover();
      });

      thumb.addEventListener('click', () => {
        this.hideThumbZoomPopover();
      });
    });
  },

  hideThumbZoomPopover() {
    const popover = document.getElementById('ec-thumb-zoom-popover');
    if (popover) {
      popover.classList.remove('is-visible');
    }
  },

  // Startseite: Content-Richtlinien bestätigen
  initContentRulesEvents() {
    const submitRulesBtn = document.getElementById('btn-submit-content-rules');
    const chkRules = document.getElementById('chk-accept-content-rules');
    if (!submitRulesBtn || !chkRules) return;

    submitRulesBtn.addEventListener('click', async () => {
      if (!chkRules.checked) {
        this.toast('Bitte setze zuerst das Häkchen zur Bestätigung der Nutzungsrichtlinien.', 'warning');
        return;
      }
      submitRulesBtn.disabled = true;
      try {
        const formData = new FormData();
        formData.append('upload_content', '1');
        formData.append('csrf_token', this.csrfToken);

        const res = await fetch(`${this.basePath}/api.php?route=home/accept-rules`, {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (res.ok && data.status === 'success') {
          const modalEl = document.getElementById('modal-content-rules');
          if (modalEl) {
            const bsModal = bootstrap.Modal.getInstance(modalEl);
            if (bsModal) bsModal.hide();
          }
          this.toast(data.message || 'Nutzungsrichtlinien erfolgreich bestätigt.', 'success');
          this.handleRoute();
        } else {
          this.toast(data.message || 'Fehler beim Speichern.', 'danger');
          submitRulesBtn.disabled = false;
        }
      } catch (err) {
        this.toast('Verbindungsfehler: ' + err.message, 'danger');
        submitRulesBtn.disabled = false;
      }
    });
  },

  // Profile (Actors): Live-Username-Check, Steckbrief speichern & FSK16/FSK18 Bild-Upload
  initActorsEvents() {
    // 1. Live-Verfügbarkeitsprüfung für Darsteller-Benutzernamen
    const usernameInput = document.getElementById('actor-username-input');
    const checkBtn = document.getElementById('btn-check-actor-username');
    const feedbackEl = document.getElementById('actor-username-feedback');

    const runUsernameCheck = async () => {
      if (!usernameInput || !feedbackEl) return;
      const val = usernameInput.value.trim();
      if (val.length < 3) {
        feedbackEl.className = 'small mt-1 text-muted';
        feedbackEl.textContent = 'Bitte gib mindestens 3 Zeichen ein.';
        return;
      }

      try {
        const formData = new FormData();
        formData.append('username', val);
        const res = await fetch(`${this.basePath}/api.php?route=actors/check-username`, {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.sanitized && data.sanitized !== val) {
          usernameInput.value = data.sanitized;
        }
        if (data.available) {
          feedbackEl.className = 'small mt-1 text-success fw-medium';
          feedbackEl.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>${this.escapeHtml(data.message)}`;
        } else {
          feedbackEl.className = 'small mt-1 text-danger fw-medium';
          feedbackEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>${this.escapeHtml(data.message)}`;
        }
      } catch (_) {
        // Silent ignore on network hiccup during typing
      }
    };

    if (usernameInput) {
      usernameInput.addEventListener('input', () => {
        clearTimeout(this.usernameCheckTimer);
        this.usernameCheckTimer = setTimeout(runUsernameCheck, 400);
      });
    }
    if (checkBtn) {
      checkBtn.addEventListener('click', runUsernameCheck);
    }

    // 2. Sexuelle Vorlieben: Alle auswählen / Auswahl aufheben
    const btnSelectAll = document.getElementById('btn-prefs-select-all');
    const btnDeselectAll = document.getElementById('btn-prefs-deselect-all');
    if (btnSelectAll) {
      btnSelectAll.addEventListener('click', () => {
        document.querySelectorAll('.sexual-pref-checkbox').forEach(cb => { cb.checked = true; });
      });
    }
    if (btnDeselectAll) {
      btnDeselectAll.addEventListener('click', () => {
        document.querySelectorAll('.sexual-pref-checkbox').forEach(cb => { cb.checked = false; });
      });
    }

    // 3. Profil-Formular absenden (Neuanlage oder Aktualisierung)
    const profileForm = document.getElementById('form-actor-profile');
    if (profileForm) {
      profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const mode = profileForm.getAttribute('data-mode') || 'edit';
        const endpoint = mode === 'create' ? 'actors/create' : 'actors/update';

        const submitBtns = document.querySelectorAll('[form="form-actor-profile"], #btn-submit-actor-profile');
        submitBtns.forEach(b => { b.disabled = true; });

        try {
          const formData = new FormData(profileForm);
          formData.append('csrf_token', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=${endpoint}`, {
            method: 'POST',
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'success') {
            this.toast(data.message || 'Profildaten erfolgreich gespeichert.', 'success');

            if (mode === 'create' && data.actor_id) {
              // Neues Profil in die linke Sidebar einfügen und direkt zur Bearbeitungsansicht wechseln
              this.appendActorToSidebar(data.actor_id, data.username || '');
              window.location.hash = `#/actor?id=${data.actor_id}`;
            } else {
              submitBtns.forEach(b => { b.disabled = false; });
            }
          } else {
            this.toast(data.message || 'Fehler beim Speichern des Profils.', 'danger');
            submitBtns.forEach(b => { b.disabled = false; });
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
          submitBtns.forEach(b => { b.disabled = false; });
        }
      });
    }

    // 4. Direkt-Upload für FSK16- und FSK18-Profilbilder
    document.querySelectorAll('.actor-image-upload-input').forEach(fileInput => {
      fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        if (!file) return;

        const actorId = fileInput.getAttribute('data-actor-id');
        const fsk = fileInput.getAttribute('data-fsk');

        if (file.size > 5 * 1024 * 1024) {
          this.toast('Die ausgewählte Bilddatei ist größer als 5 MB.', 'warning');
          fileInput.value = '';
          return;
        }

        const btnTextEl = document.getElementById(`actor-img-btn-text-${fsk}`);
        const originalBtnText = btnTextEl ? btnTextEl.textContent : '';
        if (btnTextEl) {
          btnTextEl.textContent = 'Wird hochgeladen...';
        }
        fileInput.disabled = true;

        try {
          const formData = new FormData();
          formData.append('actor_id', actorId);
          formData.append('fsk', fsk);
          formData.append('image', file);
          formData.append('csrf_token', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=actors/upload-image`, {
            method: 'POST',
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'success') {
            this.toast(data.message || `FSK${fsk}-Profilbild gespeichert.`, 'success');

            const previewImg = document.getElementById(`actor-img-preview-${fsk}`);
            const placeholderEl = document.getElementById(`actor-img-placeholder-${fsk}`);
            const cacheBustedUrl = `${data.image_url}?t=${Date.now()}`;

            if (placeholderEl) {
              placeholderEl.classList.add('d-none');
            }
            if (previewImg) {
              previewImg.src = cacheBustedUrl;
              previewImg.classList.remove('d-none');
            }

            // Falls FSK16-Bild aktualisiert wurde, auch das kleine Avatar-Icon in der Sidebar aktualisieren
            if (String(fsk) === '16') {
              this.updateSidebarActorAvatar(actorId, cacheBustedUrl);
            }
          } else {
            this.toast(data.message || 'Fehler beim Bild-Upload.', 'danger');
          }
        } catch (err) {
          this.toast('Upload-Fehler: ' + err.message, 'danger');
        } finally {
          if (btnTextEl) {
            btnTextEl.textContent = originalBtnText;
          }
          fileInput.disabled = false;
          fileInput.value = '';
        }
      });
    });
  },

  appendActorToSidebar(actorId, username) {
    const submenu = document.getElementById('submenu-actors');
    if (!submenu) return;
    if (submenu.querySelector(`[data-actor-id="${actorId}"]`)) return;

    const initials = String(username || 'EC').substring(0, 2).toUpperCase();
    const route = `#/actor?id=${actorId}`;
    const link = document.createElement('a');
    link.href = route;
    link.className = 'sidebar-link';
    link.setAttribute('data-route', route);
    link.setAttribute('data-actor-id', String(actorId));
    link.innerHTML = `
      <span class="sidebar-actor-avatar">${this.escapeHtml(initials)}</span>
      <span class="text-truncate">${this.escapeHtml(username)}</span>
    `;
    submenu.appendChild(link);
  },

  updateSidebarActorAvatar(actorId, imageUrl) {
    const sidebarLink = document.querySelector(`.sidebar-link[data-actor-id="${actorId}"]`);
    if (!sidebarLink) return;

    const existingImg = sidebarLink.querySelector('img.sidebar-actor-img');
    if (existingImg) {
      existingImg.src = imageUrl;
      return;
    }

    const badgeSpan = sidebarLink.querySelector('.sidebar-actor-avatar');
    if (badgeSpan) {
      const img = document.createElement('img');
      img.src = imageUrl;
      img.alt = '';
      img.className = 'sidebar-actor-img rounded-circle object-fit-cover flex-shrink-0 border';
      img.style.width = '22px';
      img.style.height = '22px';
      badgeSpan.replaceWith(img);
    }
  },

  // ========================================================================
  // 3b. Filme (Movies): Detail-Bearbeitung & 3-Step-Upload
  // ========================================================================
  initMoviesEvents() {
    // 1. Film löschen (in Übersicht oder Detailansicht)
    const deleteBtns = document.querySelectorAll('.js-delete-movie-btn, #btn-delete-movie-detail');
    deleteBtns.forEach(btn => {
      btn.addEventListener('click', async () => {
        const movieId = btn.getAttribute('data-movie-id');
        const movieTitle = btn.getAttribute('data-movie-title') || (`#${movieId}`);
        if (!movieId) return;

        if (!window.confirm(`Möchtest du den Film „${movieTitle}" wirklich unwiderruflich löschen?`)) {
          return;
        }

        btn.disabled = true;
        try {
          const formData = new FormData();
          formData.append('id', movieId);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=movies/delete`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();
          if (res.ok && data.status === 'ok') {
            this.toast(data.message || 'Film wurde gelöscht.', 'success');
            if (window.location.hash.startsWith('#/movie?')) {
              window.location.hash = '#/movies';
            } else if (window.location.hash.startsWith('#/movie-upload')) {
              if (window.location.hash === '#/movie-upload') {
                this.handleRoute();
              } else {
                window.location.hash = '#/movie-upload';
              }
            } else {
              this.handleRoute();
            }
          } else {
            this.toast(data.message || 'Der Film konnte nicht gelöscht werden.', 'danger');
            btn.disabled = false;
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
          btn.disabled = false;
        }
      });
    });

    // 3. Live-Berechnung Streaming-Coins, Download-Switch & Trailer-Schalter
    const amountSecSelect = document.getElementById('movie-amount-second');
    const calcCoinsSpan = document.getElementById('movie-calc-coins');
    const editForm = document.getElementById('kkc-movie-edit-form');
    const previewSwitch = document.getElementById('movie-preview-switch');
    const pricingWrap = document.getElementById('movie-pricing-fields-wrap');
    const trailerBox = document.getElementById('movie-trailer-box');
    const asDownloadSwitch = document.getElementById('movie-as-download');
    const downloadWrap = document.getElementById('movie-download-price-wrap');
    const downloadSelect = document.getElementById('movie-amount-download');

    const recalcStreamingCoins = () => {
      if (!calcCoinsSpan || !editForm) return;
      if (previewSwitch && previewSwitch.checked) {
        calcCoinsSpan.textContent = '0';
        return;
      }
      const playtimeSec = parseFloat(editForm.getAttribute('data-playtime-seconds') || '0');
      const rate = parseFloat(amountSecSelect ? (amountSecSelect.value || '0.8') : '0.8');
      calcCoinsSpan.textContent = String(Math.round(playtimeSec * rate));
    };

    if (amountSecSelect) {
      amountSecSelect.addEventListener('change', recalcStreamingCoins);
    }

    if (asDownloadSwitch && downloadWrap && downloadSelect) {
      asDownloadSwitch.addEventListener('change', () => {
        const isTrailer = previewSwitch ? previewSwitch.checked : false;
        const enabled = asDownloadSwitch.checked && !isTrailer;
        downloadSelect.disabled = !enabled;
        downloadWrap.style.opacity = asDownloadSwitch.checked ? '1' : '0.5';
      });
    }

    if (previewSwitch) {
      previewSwitch.addEventListener('change', () => {
        const isTrailer = previewSwitch.checked;
        if (pricingWrap) {
          pricingWrap.style.opacity = isTrailer ? '0.45' : '1';
          pricingWrap.style.pointerEvents = isTrailer ? 'none' : '';
        }
        if (trailerBox) {
          trailerBox.className = isTrailer
            ? 'p-3 rounded-3 border bg-primary bg-opacity-10 border-primary border-opacity-25'
            : 'p-3 rounded-3 border bg-light';
        }
        if (amountSecSelect) {
          amountSecSelect.disabled = isTrailer;
        }
        if (asDownloadSwitch) {
          asDownloadSwitch.disabled = isTrailer;
        }
        if (downloadSelect) {
          downloadSelect.disabled = isTrailer || !(asDownloadSwitch && asDownloadSwitch.checked);
        }
        recalcStreamingCoins();
      });
    }

    // 4. Kategorien-Zähler pro Akkordeon-Gruppe aktualisieren
    document.querySelectorAll('.js-movie-cat-checkbox').forEach(cb => {
      cb.addEventListener('change', () => {
        const groupId = cb.getAttribute('data-group-id');
        if (!groupId) return;
        const checkedCount = document.querySelectorAll(`.js-movie-cat-checkbox[data-group-id="${groupId}"]:checked`).length;
        const badge = document.querySelector(`.js-cat-group-count[data-group-id="${groupId}"]`);
        if (badge) {
          badge.textContent = `${checkedCount} gewählt`;
        }
      });
    });

    // Hover-Lupe auch auf den Vorschaubildern der Film-Detailseite aktivieren
    this.initThumbnailHoverZoom();

    // Zentrale Hilfsfunktion: Vorschaubild (Szene #1..10 oder Eigenes Bild #11/#12) für FSK16 / FSK18 setzen
    const applyMovieThumbSelection = (fsk, num, url, zoomUrl, editorUrl, isNewCustomUpload = false) => {
      const fskStr = String(fsk);
      const numStr = String(num);
      const customNum = fskStr === '16' ? '11' : '12';
      const isCustom = (numStr === customNum);

      const hiddenInput = document.getElementById(`input-preview-fsk${fskStr}`);
      const previewImg = document.getElementById(`current-fsk${fskStr}-preview`);
      const emptyPlaceholder = document.getElementById(`empty-fsk${fskStr}-placeholder`);
      const previewWrap = document.getElementById(`current-fsk${fskStr}-wrap`);
      const badgeNum = document.getElementById(`badge-fsk${fskStr}-num`);
      const restoreBtn = document.getElementById(`btn-restore-custom-fsk${fskStr}`);
      const studioBtn = document.getElementById(`btn-open-studio-fsk${fskStr}`);
      const studioOverlayBadge = document.getElementById(`badge-fsk${fskStr}-studio-overlay`);
      const missingHintEl = document.getElementById(`missing-fsk${fskStr}-hint`);
      const missingWarningBox = document.getElementById('missing-covers-warning');

      if (hiddenInput) hiddenInput.value = numStr;
      if (emptyPlaceholder) emptyPlaceholder.classList.add('d-none');
      if (previewImg && url) {
        previewImg.src = url;
        previewImg.classList.remove('d-none');
      }
      if (badgeNum) {
        badgeNum.textContent = isCustom ? `Eigenes Bild (#${numStr})` : `Szene #${numStr}`;
        badgeNum.classList.remove('text-danger');
        badgeNum.classList.add('text-muted');
      }
      if (missingHintEl) {
        missingHintEl.classList.add('d-none');
      }
      if (missingWarningBox) {
        const hint16 = document.getElementById('missing-fsk16-hint');
        const hint18 = document.getElementById('missing-fsk18-hint');
        const done16 = !hint16 || hint16.classList.contains('d-none');
        const done18 = !hint18 || hint18.classList.contains('d-none');
        if (done16 && done18) {
          missingWarningBox.classList.add('d-none');
        }
      }

      if (previewWrap) {
        previewWrap.setAttribute('data-num', numStr);
        previewWrap.setAttribute('data-studio-ready', '1');
        previewWrap.style.cursor = 'pointer';
        previewWrap.setAttribute('title', `Klicken, um das FSK ${fskStr} Cover im Studio zu bearbeiten`);
        if (url) previewWrap.setAttribute('data-thumb-url', url);
        if (zoomUrl || url) previewWrap.setAttribute('data-zoom-src', zoomUrl || url);
        if (editorUrl || zoomUrl || url) previewWrap.setAttribute('data-editor-url', editorUrl || zoomUrl || url);
      }

      if (studioOverlayBadge) {
        studioOverlayBadge.classList.remove('d-none');
      }

      if (studioBtn) {
        studioBtn.setAttribute('data-studio-ready', '1');
        studioBtn.style.cursor = 'pointer';
        studioBtn.setAttribute('title', `FSK ${fskStr} Cover im Studio bearbeiten`);
        studioBtn.classList.remove('btn-secondary', 'opacity-50');
        studioBtn.classList.add(fskStr === '16' ? 'btn-success' : 'btn-danger');
      }

      // Falls neues eigenes Bild hochgeladen oder aus dem Milchglas-Editor gespeichert wurde, Kachel #11/#12 aktualisieren
      if (isNewCustomUpload) {
        const customSection = document.getElementById('custom-uploaded-thumbs-section');
        const customCard = document.getElementById(`custom-thumb-card-${customNum}`);
        const customImg = document.getElementById(`custom-thumb-img-${customNum}`);
        const customWrap = document.getElementById(`custom-thumb-wrap-${customNum}`);
        const customSelectBtn = document.getElementById(`btn-select-custom-${customNum}`);

        if (customSection) customSection.classList.remove('d-none');
        if (customCard) customCard.classList.remove('d-none');
        if (customImg && url) customImg.src = url;
        if (customWrap) {
          if (url) customWrap.setAttribute('data-thumb-url', url);
          if (zoomUrl || url) customWrap.setAttribute('data-zoom-src', zoomUrl || url);
          if (editorUrl || zoomUrl || url) customWrap.setAttribute('data-editor-url', editorUrl || zoomUrl || url);
        }
        if (customSelectBtn) {
          if (url) customSelectBtn.setAttribute('data-url', url);
          if (zoomUrl || url) customSelectBtn.setAttribute('data-zoom-url', zoomUrl || url);
          if (editorUrl || zoomUrl || url) customSelectBtn.setAttribute('data-editor-url', editorUrl || zoomUrl || url);
        }
        if (restoreBtn) {
          restoreBtn.setAttribute('data-has-custom', '1');
          if (url) restoreBtn.setAttribute('data-url', url);
          if (zoomUrl || url) restoreBtn.setAttribute('data-zoom-url', zoomUrl || url);
          if (editorUrl || zoomUrl || url) restoreBtn.setAttribute('data-editor-url', editorUrl || zoomUrl || url);
        }

        const stripItem = document.getElementById(`studio-filmstrip-custom-${customNum}`);
        const stripImg = document.getElementById(`studio-filmstrip-img-${customNum}`);
        if (stripItem) {
          stripItem.classList.remove('d-none');
          if (url) stripItem.setAttribute('data-thumb-url', url);
          if (zoomUrl || url) stripItem.setAttribute('data-zoom-url', zoomUrl || url);
          if (editorUrl || zoomUrl || url) stripItem.setAttribute('data-editor-url', editorUrl || zoomUrl || url);
        }
        if (stripImg && url) {
          stripImg.src = url;
        }
      }

      // „Wieder eigenes FSK16/18 nutzen"-Button einblenden, sobald ein eigenes Bild existiert, aber aktuell eine Szene #1..10 aktiv ist
      if (restoreBtn) {
        const hasCustom = restoreBtn.getAttribute('data-has-custom') === '1';
        if (hasCustom && !isCustom) {
          restoreBtn.classList.remove('d-none');
        } else {
          restoreBtn.classList.add('d-none');
        }
      }

      // Aktiven Status aller Auswahl-Buttons für dieses FSK aktualisieren
      const activeClass = fskStr === '16' ? 'btn-success' : 'btn-danger';
      document.querySelectorAll(`.js-select-movie-thumb[data-fsk="${fskStr}"]`).forEach(b => {
        if (b.id === `btn-restore-custom-fsk${fskStr}`) return;
        const bNum = b.getAttribute('data-num');
        if (bNum === numStr) {
          b.classList.remove('btn-outline-secondary');
          b.classList.add(activeClass);
        } else {
          b.classList.remove(activeClass);
          b.classList.add('btn-outline-secondary');
        }
      });
    };

    // 5. Automatisch generiertes Vorschaubild (1..10) oder eigenes Bild (#11/#12) für FSK16 / FSK18 auswählen
    document.querySelectorAll('.js-select-movie-thumb').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const fsk = btn.getAttribute('data-fsk');
        const num = btn.getAttribute('data-num');
        const url = btn.getAttribute('data-url');
        const zoomUrl = btn.getAttribute('data-zoom-url') || url;
        const editorUrl = btn.getAttribute('data-editor-url') || zoomUrl;
        if (!fsk || !num) return;

        applyMovieThumbSelection(fsk, num, url, zoomUrl, editorUrl, false);
        const label = (num === '11' || num === '12') ? `Eigenes Bild (#${num})` : `Szene #${num}`;
        if (String(fsk) === '16' && num !== '11') {
          this.toast(`${label} für FSK 16 ausgewählt. Wichtig: Falls intime Stellen sichtbar sind, bearbeite das Bild bitte im Cover-Studio (Verpixeln / Milchglas)!`, 'warning');
        } else {
          this.toast(`${label} für FSK ${fsk} ausgewählt. Bitte speichere anschließend deine Änderungen.`, 'info');
        }
      });
    });

    // 6. Eigenes FSK16 / FSK18 Poster für Film hochladen
    document.querySelectorAll('.js-movie-poster-upload').forEach(input => {
      input.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) return;

        const movieId = input.getAttribute('data-movie-id');
        const fsk = input.getAttribute('data-fsk');

        if (file.size > 6 * 1024 * 1024) {
          this.toast('Das Coverbild darf maximal 6 MB groß sein.', 'warning');
          input.value = '';
          return;
        }

        input.disabled = true;
        try {
          const formData = new FormData();
          formData.append('movie_id', movieId);
          formData.append('fsk', fsk);
          formData.append('poster', file);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=movies/upload-poster`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || `Eigenes FSK${fsk}-Coverbild gespeichert.`, 'success');
            const thumbNum = data.thumb_number || (String(fsk) === '16' ? 11 : 12);
            const previewUrl = data.preview_url || data.thumb_url || '';
            const zoomUrl = data.zoom_url || previewUrl;
            const editorUrl = data.editor_url || zoomUrl;
            applyMovieThumbSelection(fsk, thumbNum, previewUrl, zoomUrl, editorUrl, true);
          } else {
            this.toast(data.message || 'Fehler beim Hochladen des Coverbilds.', 'danger');
          }
        } catch (err) {
          this.toast('Upload-Fehler: ' + err.message, 'danger');
        } finally {
          input.disabled = false;
          input.value = '';
        }
      });
    });

    // 6b. Interaktiver FSK16 Milchglas-Editor & Großansicht-Modal initialisieren
    this.initMovieThumbEditorModal(applyMovieThumbSelection);

    // 7. Film-Bearbeitungsformular speichern (#/movie?id=...)
    if (editForm) {
      editForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const inpFsk16 = document.getElementById('input-preview-fsk16');
        const inpFsk18 = document.getElementById('input-preview-fsk18');
        const val16 = parseInt(inpFsk16 ? (inpFsk16.value || '0') : '0', 10);
        const val18 = parseInt(inpFsk18 ? (inpFsk18.value || '0') : '0', 10);
        const hasSceneThumbs = document.querySelectorAll('.js-select-movie-thumb').length > 0;

        if (hasSceneThumbs && (val16 <= 0 || val18 <= 0)) {
          const warnBox = document.getElementById('missing-covers-warning') || document.getElementById('current-fsk16-wrap');
          if (warnBox) {
            warnBox.classList.remove('d-none');
            warnBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          if (val16 <= 0 && val18 <= 0) {
            this.toast('Bitte wähle zuerst ein jugendfreies (ggf. verpixeltes) FSK 16 Cover sowie ein FSK 18 Cover aus.', 'warning');
          } else if (val16 <= 0) {
            this.toast('Bitte wähle noch ein FSK 16 Cover aus (Wichtig: muss jugendfrei / verpixelt sein!).', 'warning');
          } else {
            this.toast('Bitte wähle noch ein FSK 18 Cover aus.', 'warning');
          }
          return;
        }

        const btns = document.querySelectorAll('#btn-save-movie-top, #btn-save-movie-bottom');
        btns.forEach(b => { b.disabled = true; });

        try {
          const formData = new FormData(editForm);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=movies/update`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || 'Änderungen erfolgreich gespeichert.', 'success');
            this.handleRoute();
          } else {
            this.toast(data.message || 'Fehler beim Speichern des Films.', 'danger');
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
        } finally {
          btns.forEach(b => { b.disabled = false; });
        }
      });
    }

    // 8. Film-Upload Schritt 1 speichern (#/movie-upload)
    const step1Form = document.getElementById('kkc-movie-step1-form');
    if (step1Form) {
      step1Form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('btn-submit-movie-step1');
        if (submitBtn) submitBtn.disabled = true;

        try {
          const formData = new FormData(step1Form);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=movies/save-step1`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || 'Basisdaten gespeichert.', 'success');
            if (data.redirect) {
              window.location.hash = data.redirect;
            }
          } else {
            this.toast(data.message || 'Bitte überprüfe deine Eingaben.', 'danger');
            if (submitBtn) submitBtn.disabled = false;
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
          if (submitBtn) submitBtn.disabled = false;
        }
      });
    }

    // 9. Film-Upload Schritt 2: Videodatei mit Live-Fortschrittsbalken hochladen + Konvertierungs-/Cover-Editor-Auswahl
    const btnStartUpload = document.getElementById('btn-start-movie-file-upload');
    const btnCancelUpload = document.getElementById('btn-cancel-movie-file-upload');
    const videoFileInput = document.getElementById('movie-video-file-input');
    const btnReplaceFile = document.getElementById('btn-replace-movie-file');
    const fileUploadedBox = document.getElementById('movie-file-uploaded-box');
    const fileSelectWrap = document.getElementById('movie-file-select-wrap');
    const decisionWrap = document.getElementById('movie-step2-decision-wrap');
    const decisionBadge = document.getElementById('movie-step2-decision-badge');
    const footerHint = document.getElementById('movie-step2-footer-hint');
    const btnCompleteStep2 = document.getElementById('btn-complete-movie-step2');
    const btnCompleteStep2Label = document.getElementById('btn-complete-movie-step2-label');
    const btnCompleteStep2Icon = document.getElementById('btn-complete-movie-step2-icon');
    const postUploadCard = document.getElementById('movie-step2-post-upload-card');

    if (btnReplaceFile && fileUploadedBox && fileSelectWrap) {
      btnReplaceFile.addEventListener('click', () => {
        fileSelectWrap.classList.remove('d-none');
      });
    }

    // Auswahl-Karten für Freigabe-Modus (Erst Cover-Editor vs. Direkt einreichen)
    const releaseRadios = document.querySelectorAll('.js-release-mode-radio');
    const cardLater = document.getElementById('card-release-mode-later');
    const cardDirect = document.getElementById('card-release-mode-direct');

    const syncReleaseModeUi = () => {
      const checkedRadio = document.querySelector('.js-release-mode-radio:checked');
      const mode = checkedRadio ? checkedRadio.value : 'later';

      if (cardLater && cardDirect) {
        if (mode === 'direct') {
          cardDirect.className = 'd-block p-3 rounded-3 border border-2 border-primary bg-primary bg-opacity-10 h-100 cursor-pointer js-release-mode-card';
          cardLater.className = 'd-block p-3 rounded-3 border bg-white h-100 cursor-pointer js-release-mode-card';
        } else {
          cardLater.className = 'd-block p-3 rounded-3 border border-2 border-primary bg-primary bg-opacity-10 h-100 cursor-pointer js-release-mode-card';
          cardDirect.className = 'd-block p-3 rounded-3 border bg-white h-100 cursor-pointer js-release-mode-card';
        }
      }

      if (btnCompleteStep2Label && btnCompleteStep2Icon) {
        if (mode === 'direct') {
          btnCompleteStep2Icon.className = 'bi bi-send-check me-2';
          btnCompleteStep2Label.textContent = 'Film jetzt direkt zur Prüfung einreichen & abschließen';
        } else {
          btnCompleteStep2Icon.className = 'bi bi-magic me-2';
          btnCompleteStep2Label.textContent = 'Weiter: Konvertierung starten & später Vorschaubilder gestalten';
        }
      }
    };

    releaseRadios.forEach(r => {
      r.addEventListener('change', syncReleaseModeUi);
    });

    if (btnStartUpload && videoFileInput) {
      btnStartUpload.addEventListener('click', () => {
        const file = videoFileInput.files?.[0];
        if (!file) {
          this.toast('Bitte wähle zuerst eine Videodatei auf deinem Gerät aus.', 'warning');
          return;
        }

        const maxBytes = 2 * 1024 * 1024 * 1024;
        if (file.size > maxBytes) {
          this.toast('Die Videodatei überschreitet das Limit von 2,0 GB.', 'danger');
          return;
        }

        const movieId = btnStartUpload.getAttribute('data-movie-id') || '0';
        const progressWrap = document.getElementById('movie-upload-progress-wrap');
        const progressBar = document.getElementById('movie-upload-progress-bar');
        const percentBadge = document.getElementById('movie-upload-percent-badge');
        const bytesInfo = document.getElementById('movie-upload-bytes-info');
        const statusText = document.getElementById('movie-upload-status-text');

        if (progressWrap) progressWrap.classList.remove('d-none');
        if (progressBar) progressBar.style.width = '0%';
        if (percentBadge) percentBadge.textContent = '0 %';
        if (statusText) statusText.textContent = 'Videodatei wird hochgeladen...';
        btnStartUpload.disabled = true;
        videoFileInput.disabled = true;
        if (btnCancelUpload) btnCancelUpload.classList.remove('d-none');

        const formData = new FormData();
        formData.append('movie_id', movieId);
        formData.append('video', file);
        formData.append('released', '0');
        formData.append('_csrf', this.csrfToken);

        const xhr = new XMLHttpRequest();
        this.activeMovieUploadXhr = xhr;

        xhr.upload.addEventListener('progress', (ev) => {
          if (!ev.lengthComputable) return;
          const pct = Math.min(100, Math.round((ev.loaded / ev.total) * 100));
          const loadedMb = (ev.loaded / (1024 * 1024)).toFixed(1);
          const totalMb = (ev.total / (1024 * 1024)).toFixed(1);
          if (progressBar) progressBar.style.width = `${pct}%`;
          if (percentBadge) percentBadge.textContent = `${pct} %`;
          if (bytesInfo) bytesInfo.textContent = `${loadedMb} MB / ${totalMb} MB`;
          if (pct === 100 && statusText) {
            statusText.textContent = 'Datei übertragen – Server prüft Videodaten...';
          }
        });

        xhr.addEventListener('load', () => {
          this.activeMovieUploadXhr = null;
          try {
            const data = JSON.parse(xhr.responseText || '{}');
            if (xhr.status >= 200 && xhr.status < 300 && data.status === 'ok') {
              this.toast(data.message || 'Videodatei erfolgreich hochgeladen.', 'success');

              // Erfolgsbox in Schritt 2 aktualisieren & Auswahl-Bereich freischalten
              const fnEl = document.getElementById('uploaded-movie-filename');
              const ptEl = document.getElementById('uploaded-movie-playtime');
              const resEl = document.getElementById('uploaded-movie-resolution');
              if (fnEl) fnEl.textContent = data.filename || file.name;
              if (ptEl) ptEl.textContent = data.playtime_string || '-';
              if (resEl) resEl.textContent = data.resolution || '-';

              if (fileUploadedBox) fileUploadedBox.classList.remove('d-none');
              if (fileSelectWrap) fileSelectWrap.classList.add('d-none');
              if (progressWrap) progressWrap.classList.add('d-none');

              if (decisionWrap) {
                decisionWrap.style.opacity = '1';
                decisionWrap.style.pointerEvents = '';
              }
              if (decisionBadge) {
                decisionBadge.className = 'badge bg-success';
                decisionBadge.textContent = 'Bereit für deine Auswahl';
              }
              if (footerHint) {
                footerHint.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>Videodatei ist bereit – bitte bestätige deine Auswahl.';
              }
              if (btnCompleteStep2) {
                btnCompleteStep2.disabled = false;
              }
              if (postUploadCard) {
                postUploadCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
              }

              btnStartUpload.disabled = false;
              videoFileInput.disabled = false;
              videoFileInput.value = '';
              if (btnCancelUpload) btnCancelUpload.classList.add('d-none');
              return;
            }
            this.toast(data.message || 'Fehler beim Video-Upload.', 'danger');
          } catch (_) {
            this.toast('Unerwartete Antwort vom Server beim Video-Upload.', 'danger');
          }
          btnStartUpload.disabled = false;
          videoFileInput.disabled = false;
          if (btnCancelUpload) btnCancelUpload.classList.add('d-none');
        });

        xhr.addEventListener('error', () => {
          this.activeMovieUploadXhr = null;
          this.toast('Netzwerkfehler während der Dateiübertragung.', 'danger');
          btnStartUpload.disabled = false;
          videoFileInput.disabled = false;
          if (btnCancelUpload) btnCancelUpload.classList.add('d-none');
        });

        xhr.addEventListener('abort', () => {
          this.activeMovieUploadXhr = null;
          this.toast('Der Video-Upload wurde abgebrochen.', 'warning');
          btnStartUpload.disabled = false;
          videoFileInput.disabled = false;
          if (progressBar) progressBar.style.width = '0%';
          if (btnCancelUpload) btnCancelUpload.classList.add('d-none');
        });

        xhr.open('POST', `${this.basePath}/api.php?route=movies/upload-file`, true);
        xhr.setRequestHeader('X-CSRF-Token', this.csrfToken);
        xhr.send(formData);
      });
    }

    if (btnCancelUpload) {
      btnCancelUpload.addEventListener('click', () => {
        if (this.activeMovieUploadXhr) {
          this.activeMovieUploadXhr.abort();
        }
      });
    }

    // 9b. Schritt 2 abschließen (Vorschaubilder später im Cover-Editor gestalten vs. Direkt zur Prüfung einreichen)
    if (btnCompleteStep2) {
      btnCompleteStep2.addEventListener('click', async () => {
        const movieId = btnCompleteStep2.getAttribute('data-movie-id') || '0';
        const checkedRadio = document.querySelector('.js-release-mode-radio:checked');
        const releaseMode = checkedRadio ? checkedRadio.value : 'later';
        const notifySwitch = document.getElementById('movie-notify-on-convert');
        const notifyOnConvert = (!notifySwitch || notifySwitch.checked) ? '1' : '0';

        btnCompleteStep2.disabled = true;
        try {
          const formData = new FormData();
          formData.append('movie_id', movieId);
          formData.append('release_mode', releaseMode);
          formData.append('notify_on_convert', notifyOnConvert);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=movies/complete-upload`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || 'Schritt 2 erfolgreich abgeschlossen.', 'success');
            if (data.redirect) {
              window.location.hash = data.redirect;
            }
          } else {
            this.toast(data.message || 'Fehler beim Abschließen von Schritt 2.', 'danger');
            btnCompleteStep2.disabled = false;
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
          btnCompleteStep2.disabled = false;
        }
      });
    }
  },

  // ========================================================================
  // 3b-2. EroCloud Fullscreen Cover-Studio (FSK 16 & FSK 18)
  // ========================================================================
  initMovieThumbEditorModal(applyMovieThumbSelection) {
    const modalEl = document.getElementById('modalMovieThumbEditor');
    const canvas = document.getElementById('movie-thumb-editor-canvas');
    if (!modalEl || !canvas) return;

    const ctx = canvas.getContext('2d');
    const loadingEl = document.getElementById('thumb-editor-loading');
    const canvasContainer = document.getElementById('studio-canvas-container');
    const titleEl = document.getElementById('thumb-editor-modal-title');
    const sourceBadgeEl = document.getElementById('studio-source-badge');
    const summaryTextEl = document.getElementById('studio-edit-summary-text');

    // Ziel-Modus Buttons (FSK 16 vs FSK 18)
    const btnModeFsk16 = document.getElementById('btn-studio-mode-fsk16');
    const btnModeFsk18 = document.getElementById('btn-studio-mode-fsk18');
    const btnSavePrimary = document.getElementById('btn-studio-save-primary');
    const icoSavePrimary = document.getElementById('ico-studio-save-primary');
    const lblSavePrimary = document.getElementById('lbl-studio-save-primary');

    // Zensur-Werkzeuge
    const shapesBadge = document.getElementById('thumb-editor-shapes-badge');
    const censorActionsWrap = document.getElementById('studio-censor-actions-wrap');
    const shapesListEl = document.getElementById('thumb-editor-shapes-list');
    const btnUndo = document.getElementById('btn-thumb-editor-undo');
    const btnClear = document.getElementById('btn-thumb-editor-clear');
    const btnResetAll = document.getElementById('btn-studio-reset-all');
    const toolBtns = modalEl.querySelectorAll('.js-editor-tool-btn');
    const effectBtns = modalEl.querySelectorAll('.js-editor-effect-btn');
    const intensityBtns = modalEl.querySelectorAll('.js-editor-intensity-btn');
    const brushSizeWrap = document.getElementById('studio-brush-size-wrap');
    const rngBrushSize = document.getElementById('rng-studio-brush-size');
    const lblBrushSize = document.getElementById('lbl-studio-brush-size');

    // Bild-Optimierung (Licht & Farbe)
    const rngBright = document.getElementById('rng-studio-bright');
    const rngContrast = document.getElementById('rng-studio-contrast');
    const rngSat = document.getElementById('rng-studio-sat');
    const rngVignette = document.getElementById('rng-studio-vignette');
    const lblBright = document.getElementById('lbl-studio-bright');
    const lblContrast = document.getElementById('lbl-studio-contrast');
    const lblSat = document.getElementById('lbl-studio-sat');
    const lblVignette = document.getElementById('lbl-studio-vignette');
    const btnResetAdjust = document.getElementById('btn-studio-reset-adjust');
    const presetBtns = modalEl.querySelectorAll('.js-studio-preset-btn');

    // Objekt-Inspektor (Sticker, Badges, Text, Logo)
    const selectedObjPanel = document.getElementById('studio-selected-object-panel');
    const btnDeleteSelectedObj = document.getElementById('btn-studio-delete-selected-obj');
    const rngObjScale = document.getElementById('rng-studio-obj-scale');
    const lblObjScale = document.getElementById('lbl-studio-obj-scale');
    const rngObjRotate = document.getElementById('rng-studio-obj-rotate');
    const lblObjRotate = document.getElementById('lbl-studio-obj-rotate');
    const rngObjOpacity = document.getElementById('rng-studio-obj-opacity');
    const lblObjOpacity = document.getElementById('lbl-studio-obj-opacity');

    // Text & Logo Inputs
    const inpCustomText = document.getElementById('inp-studio-custom-text');
    const selTextColor = document.getElementById('sel-studio-text-color');
    const selTextBg = document.getElementById('sel-studio-text-bg');
    const btnAddText = document.getElementById('btn-studio-add-text');
    const inpLogoUpload = document.getElementById('inp-studio-logo-upload');
    const btnInsertSavedLogo = document.getElementById('btn-studio-insert-saved-logo');
    const btnClearSavedLogo = document.getElementById('btn-studio-clear-saved-logo');
    const inpLocalImage = document.getElementById('studio-local-image-input');

    // Video-Schnappschuss Drawer
    const videoDrawer = document.getElementById('studio-video-capture-drawer');
    const studioVideoEl = document.getElementById('studio-capture-video');
    const btnToggleVideoDrawer = document.getElementById('btn-studio-toggle-video-drawer');
    const btnCloseVideoDrawer = document.getElementById('btn-studio-close-video-drawer');
    const btnCancelVideoDrawer = document.getElementById('btn-studio-cancel-video-drawer');
    const btnGrabVideoFrame = document.getElementById('btn-studio-grab-video-frame');

    const LOGO_STORAGE_KEY = 'erocloud_creator_logo_v1';
    const studioContext = modalEl.getAttribute('data-studio-context') || 'movie';
    const entityId = modalEl.getAttribute('data-entity-id') || modalEl.getAttribute('data-movie-id') || '0';

    const state = {
      studioContext,
      entityId,
      movieId: modalEl.getAttribute('data-movie-id') || entityId,
      targetFsk: '16', // '16' | '18'
      activeTab: 'censor', // 'censor' | 'adjust' | 'badges' | 'logo'
      num: studioContext === 'photo-album' ? 'cover_16' : '1',
      sourceLabel: studioContext === 'photo-album' ? 'Album-Foto' : 'Szene #1',
      isCustomSource: false, // true wenn lokales Bild oder Video-Frame geladen wurde
      thumbUrl: '',
      zoomUrl: '',
      editorUrl: '',
      currentTool: 'rect', // 'rect' | 'circle' | 'brush'
      currentEffect: 'frosted', // 'frosted' | 'pixel'
      currentIntensity: 2, // 1 | 2 | 3
      brushSize: 45,
      adjust: {
        bright: 0,
        contrast: 0,
        sat: 0,
        vignette: 0
      },
      baseImage: null,
      adjustedBaseCanvas: null,
      effectCache: {}, // key: `${effect}_${intensity}`
      shapes: [], // { type: 'rect'|'circle'|'brush', effect: 'frosted'|'pixel', intensity: 1|2|3, x, y, w, h, points: [], brushSize: 45 }
      objects: [], // { id, type: 'sticker'|'stamp'|'text'|'logo', x, y, w, h, scale, rotate, opacity, text, bg, color, img }
      selectedObjId: null,
      nextObjId: 1,
      interaction: null // null | { mode: 'draw-shape'|'drag-obj'|'scale-obj', ... }
    };

    // ----------------------------------------------------------------------
    // 1. Tab-Umschaltung & Ziel-FSK-Modus
    // ----------------------------------------------------------------------
    const switchStudioTab = (tabName) => {
      state.activeTab = tabName;
      modalEl.querySelectorAll('.js-studio-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-tab') === tabName);
      });
      modalEl.querySelectorAll('.js-studio-tab-pane').forEach(pane => {
        pane.classList.toggle('d-none', pane.id !== `studio-tab-${tabName}`);
      });
      canvas.style.cursor = (tabName === 'censor') ? 'crosshair' : 'default';
    };

    modalEl.querySelectorAll('.js-studio-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        switchStudioTab(btn.getAttribute('data-tab') || 'censor');
      });
    });

    const setTargetFskMode = (fsk, autoSwitchTab = false) => {
      state.targetFsk = String(fsk) === '18' ? '18' : '16';
      const is16 = state.targetFsk === '16';

      if (btnModeFsk16) {
        btnModeFsk16.classList.toggle('active-fsk16', is16);
      }
      if (btnModeFsk18) {
        btnModeFsk18.classList.toggle('active-fsk18', !is16);
      }
      if (btnSavePrimary) {
        btnSavePrimary.classList.toggle('btn-success', is16);
        btnSavePrimary.classList.toggle('btn-danger', !is16);
      }
      if (icoSavePrimary) {
        icoSavePrimary.className = is16 ? 'bi bi-shield-check me-1' : 'bi bi-fire me-1';
      }
      if (autoSwitchTab) {
        switchStudioTab(is16 ? 'censor' : 'adjust');
      }
      syncStudioUI();
    };

    // ----------------------------------------------------------------------
    // 2. Bild-Puffer: Licht/Farbe + Milchglas + Pixel-Mosaik
    // ----------------------------------------------------------------------
    const invalidateCaches = () => {
      state.adjustedBaseCanvas = null;
      state.effectCache = {};
    };

    const hasColorAdjustments = () => {
      const a = state.adjust;
      return a.bright !== 0 || a.contrast !== 0 || a.sat !== 0 || a.vignette > 0;
    };

    const hasAnyEdits = () => {
      return state.isCustomSource || state.shapes.length > 0 || state.objects.length > 0 || hasColorAdjustments();
    };

    const getAdjustedBaseCanvas = () => {
      if (!state.baseImage) return null;
      if (state.adjustedBaseCanvas) return state.adjustedBaseCanvas;

      const w = canvas.width;
      const h = canvas.height;
      const off = document.createElement('canvas');
      off.width = w;
      off.height = h;
      const octx = off.getContext('2d');

      const { bright, contrast, sat, vignette } = state.adjust;
      const bPct = 100 + bright;
      const cPct = 100 + contrast;
      const sPct = Math.max(0, 100 + sat * 1.5);

      if (bright !== 0 || contrast !== 0 || sat !== 0) {
        try {
          octx.filter = `brightness(${bPct}%) contrast(${cPct}%) saturate(${sPct}%)`;
        } catch (_) {}
      }
      octx.drawImage(state.baseImage, 0, 0, w, h);
      octx.filter = 'none';

      if (vignette > 0) {
        const cx = w / 2;
        const cy = h / 2;
        const outerR = Math.hypot(cx, cy);
        const innerR = outerR * 0.38;
        const grad = octx.createRadialGradient(cx, cy, innerR, cx, cy, outerR);
        const alpha = Math.min(0.88, (vignette / 100) * 0.82);
        grad.addColorStop(0, 'rgba(0, 0, 0, 0)');
        grad.addColorStop(1, `rgba(0, 0, 0, ${alpha})`);
        octx.fillStyle = grad;
        octx.fillRect(0, 0, w, h);
      }

      state.adjustedBaseCanvas = off;
      return off;
    };

    const getEffectBuffer = (effect, intensity) => {
      const baseLayer = getAdjustedBaseCanvas();
      if (!baseLayer) return null;

      const key = `${effect}_${intensity}`;
      if (state.effectCache[key]) {
        return state.effectCache[key];
      }

      const w = canvas.width;
      const h = canvas.height;
      const outCanvas = document.createElement('canvas');
      outCanvas.width = w;
      outCanvas.height = h;
      const outCtx = outCanvas.getContext('2d');

      if (effect === 'pixel') {
        // Echter Pixel-Mosaik-Effekt (ohne Weichzeichnung beim Hochskalieren)
        const blockDiv = intensity === 1 ? 26 : (intensity === 2 ? 42 : 64);
        const smallW = Math.max(8, Math.round(w / blockDiv));
        const smallH = Math.max(6, Math.round(h / blockDiv));
        const tmp = document.createElement('canvas');
        tmp.width = smallW;
        tmp.height = smallH;
        const tctx = tmp.getContext('2d');
        tctx.imageSmoothingEnabled = true;
        tctx.drawImage(baseLayer, 0, 0, smallW, smallH);

        outCtx.imageSmoothingEnabled = false;
        outCtx.drawImage(tmp, 0, 0, smallW, smallH, 0, 0, w, h);
      } else {
        // Milchglas-Effekt (Frosted Glass)
        const scaleFactor = intensity === 1 ? 14 : (intensity === 2 ? 22 : 34);
        const blurPx = intensity === 1 ? 12 : (intensity === 2 ? 20 : 30);
        const tintAlpha = intensity === 1 ? 0.18 : (intensity === 2 ? 0.25 : 0.32);

        const smallW = Math.max(12, Math.round(w / scaleFactor));
        const smallH = Math.max(8, Math.round(h / scaleFactor));
        const tmp = document.createElement('canvas');
        tmp.width = smallW;
        tmp.height = smallH;
        const tctx = tmp.getContext('2d');
        tctx.imageSmoothingEnabled = true;
        tctx.imageSmoothingQuality = 'high';
        tctx.drawImage(baseLayer, 0, 0, smallW, smallH);

        outCtx.imageSmoothingEnabled = true;
        outCtx.imageSmoothingQuality = 'high';
        try {
          outCtx.filter = `blur(${blurPx}px) brightness(1.06) saturate(0.9)`;
        } catch (_) {}
        outCtx.drawImage(tmp, 0, 0, smallW, smallH, 0, 0, w, h);
        outCtx.filter = 'none';

        outCtx.globalAlpha = 0.65;
        outCtx.drawImage(tmp, 0, 0, smallW, smallH, 0, 0, w, h);
        outCtx.globalAlpha = 1.0;

        outCtx.fillStyle = `rgba(255, 255, 255, ${tintAlpha})`;
        outCtx.fillRect(0, 0, w, h);
      }

      state.effectCache[key] = outCanvas;
      return outCanvas;
    };

    // ----------------------------------------------------------------------
    // 3. Rendering: Basisbild + Zensur-Formen + Overlay-Objekte
    // ----------------------------------------------------------------------
    const traceShapePath = (targetCtx, sh) => {
      targetCtx.beginPath();
      if (sh.type === 'circle') {
        const cx = sh.x + sh.w / 2;
        const cy = sh.y + sh.h / 2;
        const rx = Math.max(1, Math.abs(sh.w / 2));
        const ry = Math.max(1, Math.abs(sh.h / 2));
        targetCtx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
      } else {
        const r = Math.min(10, Math.abs(sh.w) / 4, Math.abs(sh.h) / 4);
        if (typeof targetCtx.roundRect === 'function') {
          targetCtx.roundRect(sh.x, sh.y, sh.w, sh.h, r);
        } else {
          targetCtx.rect(sh.x, sh.y, sh.w, sh.h);
        }
      }
    };

    const drawCensorShape = (sh, idx, showGuides) => {
      const w = canvas.width;
      const h = canvas.height;
      const effBuf = getEffectBuffer(sh.effect || 'frosted', sh.intensity || 2);
      if (!effBuf) return;

      if (sh.type === 'brush') {
        if (!sh.points || sh.points.length === 0) return;
        // Für Freihand-Pinsel erzeugen wir eine Maske auf einem Hilfs-Canvas
        const maskCanvas = document.createElement('canvas');
        maskCanvas.width = w;
        maskCanvas.height = h;
        const mctx = maskCanvas.getContext('2d');
        mctx.lineCap = 'round';
        mctx.lineJoin = 'round';
        mctx.lineWidth = sh.brushSize || 45;
        mctx.strokeStyle = '#000';
        mctx.beginPath();
        mctx.moveTo(sh.points[0].x, sh.points[0].y);
        for (let i = 1; i < sh.points.length; i++) {
          mctx.lineTo(sh.points[i].x, sh.points[i].y);
        }
        if (sh.points.length === 1) {
          mctx.lineTo(sh.points[0].x + 0.5, sh.points[0].y + 0.5);
        }
        mctx.stroke();

        mctx.globalCompositeOperation = 'source-in';
        mctx.drawImage(effBuf, 0, 0, w, h);

        ctx.save();
        ctx.drawImage(maskCanvas, 0, 0, w, h);
        ctx.restore();
        return;
      }

      if (sh.w < 4 || sh.h < 4) return;
      ctx.save();
      traceShapePath(ctx, sh);
      ctx.clip();
      ctx.drawImage(effBuf, 0, 0, w, h);
      ctx.restore();

      if (showGuides) {
        ctx.save();
        traceShapePath(ctx, sh);
        ctx.lineWidth = Math.max(2, Math.round(w / 460));
        ctx.strokeStyle = 'rgba(56, 189, 248, 0.9)';
        ctx.setLineDash([6, 4]);
        ctx.stroke();

        const labelText = `#${idx + 1}`;
        ctx.font = `bold ${Math.max(12, Math.round(w / 68))}px sans-serif`;
        const textW = ctx.measureText(labelText).width + 10;
        const badgeH = Math.max(20, Math.round(w / 46));
        const bx = Math.max(4, Math.min(w - textW - 4, sh.x));
        const by = Math.max(4, Math.min(h - badgeH - 4, sh.y));

        ctx.fillStyle = 'rgba(2, 132, 199, 0.92)';
        ctx.fillRect(bx, by, textW, badgeH);
        ctx.fillStyle = '#ffffff';
        ctx.fillText(labelText, bx + 5, by + badgeH - 6);
        ctx.restore();
      }
    };

    // Berechnet die aktuelle Breite/Höhe eines Overlay-Objekts (inkl. Skalierung)
    const getObjectMetrics = (obj) => {
      const scale = (obj.scale || 100) / 100;
      const baseRef = Math.max(480, canvas.width);

      if (obj.type === 'sticker') {
        const size = Math.round(baseRef * 0.12 * scale);
        return { w: size, h: size, fontSize: size };
      }
      if (obj.type === 'logo' && obj.img) {
        const aspect = (obj.img.naturalWidth || 200) / Math.max(1, obj.img.naturalHeight || 100);
        const w = Math.round(baseRef * 0.22 * scale);
        const h = Math.round(w / aspect);
        return { w, h, fontSize: 16 };
      }

      // 'stamp' oder 'text'
      const fontSize = Math.max(14, Math.round(baseRef * (obj.type === 'stamp' ? 0.042 : 0.048) * scale));
      ctx.save();
      ctx.font = `900 ${fontSize}px system-ui, -apple-system, Segoe UI, Roboto, sans-serif`;
      const textWidth = ctx.measureText(obj.text || '').width;
      ctx.restore();

      const padX = Math.round(fontSize * 0.75);
      const padY = Math.round(fontSize * 0.42);
      return {
        w: Math.round(textWidth + padX * 2),
        h: Math.round(fontSize + padY * 2),
        fontSize
      };
    };

    const drawOverlayObject = (obj, showGuides) => {
      const m = getObjectMetrics(obj);
      obj.w = m.w;
      obj.h = m.h;

      ctx.save();
      ctx.translate(obj.x, obj.y);
      if (obj.rotate) {
        ctx.rotate((obj.rotate * Math.PI) / 180);
      }
      ctx.globalAlpha = Math.max(0.1, Math.min(1, (obj.opacity || 100) / 100));

      if (obj.type === 'sticker') {
        ctx.font = `${m.fontSize}px "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.shadowColor = 'rgba(0,0,0,0.45)';
        ctx.shadowBlur = 10;
        ctx.fillText(obj.text, 0, 0);
      } else if (obj.type === 'logo' && obj.img) {
        ctx.shadowColor = 'rgba(0,0,0,0.35)';
        ctx.shadowBlur = 8;
        ctx.drawImage(obj.img, -m.w / 2, -m.h / 2, m.w, m.h);
      } else if (obj.type === 'stamp' || obj.type === 'text') {
        const rx = -m.w / 2;
        const ry = -m.h / 2;
        const radius = obj.type === 'stamp' ? Math.min(10, m.h / 4) : Math.min(12, m.h / 3);

        if (obj.bg && obj.bg !== 'shadow') {
          ctx.fillStyle = obj.bg;
          ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
          ctx.shadowBlur = 12;
          ctx.shadowOffsetY = 3;
          ctx.beginPath();
          if (typeof ctx.roundRect === 'function') {
            ctx.roundRect(rx, ry, m.w, m.h, radius);
          } else {
            ctx.rect(rx, ry, m.w, m.h);
          }
          ctx.fill();

          if (obj.type === 'stamp') {
            ctx.lineWidth = Math.max(2, Math.round(m.fontSize * 0.08));
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
            ctx.stroke();
          }
        }

        ctx.font = `900 ${m.fontSize}px system-ui, -apple-system, Segoe UI, Roboto, sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        if (!obj.bg || obj.bg === 'shadow') {
          ctx.shadowColor = 'rgba(0, 0, 0, 0.9)';
          ctx.shadowBlur = 10;
          ctx.lineWidth = Math.max(3, Math.round(m.fontSize * 0.14));
          ctx.strokeStyle = 'rgba(0, 0, 0, 0.85)';
          ctx.strokeText(obj.text || '', 0, 0);
        } else {
          ctx.shadowColor = 'transparent';
          ctx.shadowBlur = 0;
        }

        ctx.fillStyle = obj.color || '#FFFFFF';
        ctx.fillText(obj.text || '', 0, 0);
      }

      // Auswahlrahmen + Skalier-Griff (↘) wenn ausgewählt
      if (showGuides && state.selectedObjId === obj.id) {
        ctx.globalAlpha = 1;
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;
        const pad = 6;
        const bx = -m.w / 2 - pad;
        const by = -m.h / 2 - pad;
        const bw = m.w + pad * 2;
        const bh = m.h + pad * 2;

        ctx.lineWidth = 2;
        ctx.strokeStyle = '#38BDF8';
        ctx.setLineDash([5, 4]);
        ctx.strokeRect(bx, by, bw, bh);
        ctx.setLineDash([]);

        // Resize-Handle unten rechts
        const handleR = Math.max(8, Math.round(canvas.width / 85));
        ctx.fillStyle = '#0284C7';
        ctx.beginPath();
        ctx.arc(bx + bw, by + bh, handleR, 0, Math.PI * 2);
        ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#FFFFFF';
        ctx.stroke();
      }

      ctx.restore();
    };

    const renderCanvas = (showGuides = true) => {
      if (!state.baseImage || !ctx) return;
      const w = canvas.width;
      const h = canvas.height;

      ctx.clearRect(0, 0, w, h);

      // 1. Basisbild inkl. Farb-/Helligkeits-/Vignette-Optimierung
      const baseLayer = getAdjustedBaseCanvas();
      if (baseLayer) {
        ctx.drawImage(baseLayer, 0, 0, w, h);
      }

      // 2. Zensur-Formen (Milchglas / Pixel-Mosaik / Pinsel)
      const allShapes = (state.interaction && state.interaction.mode === 'draw-shape' && state.interaction.shape)
        ? [...state.shapes, state.interaction.shape]
        : state.shapes;

      allShapes.forEach((sh, idx) => {
        drawCensorShape(sh, idx, showGuides);
      });

      // 3. Interaktive Overlay-Objekte (Sticker, Badges, Text, Logo)
      state.objects.forEach(obj => {
        drawOverlayObject(obj, showGuides);
      });
    };

    // ----------------------------------------------------------------------
    // 4. UI-Synchronisation (Badges, Listen, Objekt-Inspektor, Footer-Summary)
    // ----------------------------------------------------------------------
    const syncStudioUI = () => {
      const shapeCount = state.shapes.length;
      const objCount = state.objects.length;

      if (sourceBadgeEl) {
        sourceBadgeEl.textContent = state.sourceLabel;
      }

      if (shapesBadge) {
        shapesBadge.textContent = shapeCount === 1 ? '1 Bereich' : `${shapeCount} Bereiche`;
        shapesBadge.className = shapeCount > 0 ? 'badge bg-info text-dark' : 'badge bg-secondary';
      }
      if (censorActionsWrap) {
        censorActionsWrap.classList.toggle('d-none', shapeCount === 0);
      }

      // Liste der gezeichneten Zensur-Bereiche
      if (shapesListEl) {
        if (shapeCount === 0) {
          shapesListEl.innerHTML = '';
        } else {
          shapesListEl.innerHTML = state.shapes.map((sh, i) => {
            const tName = sh.type === 'circle' ? 'Kreis' : (sh.type === 'brush' ? 'Pinsel' : 'Viereck');
            const eName = sh.effect === 'pixel' ? 'Pixel' : 'Milchglas';
            return `
              <span class="badge bg-dark text-light border border-secondary d-inline-flex align-items-center gap-1 py-1 px-2">
                <span>#${i + 1} ${tName} (${eName})</span>
                <button type="button" class="btn-close btn-close-white js-remove-single-shape" data-shape-idx="${i}" style="font-size: 0.55rem;" title="Diesen Bereich löschen"></button>
              </span>
            `;
          }).join('');

          shapesListEl.querySelectorAll('.js-remove-single-shape').forEach(rmBtn => {
            rmBtn.addEventListener('click', () => {
              const idx = parseInt(rmBtn.getAttribute('data-shape-idx') || '-1', 10);
              if (idx >= 0 && idx < state.shapes.length) {
                state.shapes.splice(idx, 1);
                syncStudioUI();
                renderCanvas(true);
              }
            });
          });
        }
      }

      // Ausgewähltes Overlay-Objekt im Inspektor anzeigen
      const selObj = state.objects.find(o => o.id === state.selectedObjId) || null;
      if (selectedObjPanel) {
        if (!selObj) {
          selectedObjPanel.classList.add('d-none');
        } else {
          selectedObjPanel.classList.remove('d-none');
          if (rngObjScale) rngObjScale.value = String(selObj.scale || 100);
          if (lblObjScale) lblObjScale.textContent = `${selObj.scale || 100}%`;
          if (rngObjRotate) rngObjRotate.value = String(selObj.rotate || 0);
          if (lblObjRotate) lblObjRotate.textContent = `${selObj.rotate || 0}°`;
          if (rngObjOpacity) rngObjOpacity.value = String(selObj.opacity || 100);
          if (lblObjOpacity) lblObjOpacity.textContent = `${selObj.opacity || 100}%`;
        }
      }

      // Zusammenfassung im Footer & Primärer Speichern-Button
      const parts = [];
      if (state.isCustomSource) parts.push(state.sourceLabel);
      if (shapeCount > 0) parts.push(`${shapeCount}× Zensur`);
      if (hasColorAdjustments()) parts.push('Licht/Farbe optimiert');
      if (objCount > 0) parts.push(`${objCount}× Sticker/Badge/Logo`);

      if (summaryTextEl) {
        summaryTextEl.textContent = parts.length > 0
          ? `Aktive Bearbeitungen: ${parts.join(' • ')}`
          : `Unverändertes Originalbild (${state.sourceLabel})`;
      }

      if (lblSavePrimary) {
        const fskLabel = state.targetFsk === '18' ? 'FSK 18 Cover' : 'FSK 16 Cover';
        if (state.studioContext === 'photo-album') {
          lblSavePrimary.textContent = hasAnyEdits()
            ? `Bearbeitetes Bild als ${fskLabel} speichern`
            : `${state.sourceLabel} als ${fskLabel} speichern`;
        } else {
          lblSavePrimary.textContent = hasAnyEdits()
            ? `Bearbeitetes Bild als ${fskLabel} speichern & aktivieren`
            : `${state.sourceLabel} als ${fskLabel} übernehmen`;
        }
      }
    };

    // ----------------------------------------------------------------------
    // 5. Event-Handler für Werkzeuge (Zensur, Licht/Farbe, Sticker, Text, Logo)
    // ----------------------------------------------------------------------
    toolBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        state.currentTool = btn.getAttribute('data-shape-tool') || 'rect';
        toolBtns.forEach(b => {
          const active = b === btn;
          b.classList.toggle('btn-info', active);
          b.classList.toggle('text-dark', active);
          b.classList.toggle('fw-semibold', active);
          b.classList.toggle('active', active);
          b.classList.toggle('btn-outline-light', !active);
        });
        if (brushSizeWrap) {
          brushSizeWrap.classList.toggle('d-none', state.currentTool !== 'brush');
        }
      });
    });

    if (rngBrushSize) {
      rngBrushSize.addEventListener('input', () => {
        state.brushSize = parseInt(rngBrushSize.value || '45', 10);
        if (lblBrushSize) lblBrushSize.textContent = `${state.brushSize} px`;
      });
    }

    effectBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        state.currentEffect = btn.getAttribute('data-effect') || 'frosted';
        effectBtns.forEach(b => {
          const active = b === btn;
          b.classList.toggle('btn-info', active);
          b.classList.toggle('text-dark', active);
          b.classList.toggle('fw-semibold', active);
          b.classList.toggle('active', active);
          b.classList.toggle('btn-outline-light', !active);
        });
      });
    });

    intensityBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        state.currentIntensity = parseInt(btn.getAttribute('data-intensity') || '2', 10);
        intensityBtns.forEach(b => {
          const active = b === btn;
          b.classList.toggle('btn-info', active);
          b.classList.toggle('text-dark', active);
          b.classList.toggle('fw-semibold', active);
          b.classList.toggle('active', active);
          b.classList.toggle('btn-outline-light', !active);
        });
      });
    });

    if (btnUndo) {
      btnUndo.addEventListener('click', () => {
        if (state.shapes.length > 0) {
          state.shapes.pop();
          syncStudioUI();
          renderCanvas(true);
        }
      });
    }

    if (btnClear) {
      btnClear.addEventListener('click', () => {
        state.shapes = [];
        syncStudioUI();
        renderCanvas(true);
      });
    }

    const applyAdjustmentValues = (bright, contrast, sat, vignette) => {
      state.adjust.bright = bright;
      state.adjust.contrast = contrast;
      state.adjust.sat = sat;
      state.adjust.vignette = vignette;

      if (rngBright) rngBright.value = String(bright);
      if (rngContrast) rngContrast.value = String(contrast);
      if (rngSat) rngSat.value = String(sat);
      if (rngVignette) rngVignette.value = String(vignette);

      if (lblBright) lblBright.textContent = bright > 0 ? `+${bright}` : String(bright);
      if (lblContrast) lblContrast.textContent = contrast > 0 ? `+${contrast}` : String(contrast);
      if (lblSat) lblSat.textContent = sat > 0 ? `+${sat}` : String(sat);
      if (lblVignette) lblVignette.textContent = `${vignette}%`;

      invalidateCaches();
      syncStudioUI();
      renderCanvas(true);
    };

    modalEl.querySelectorAll('.js-studio-adjust-slider').forEach(slider => {
      slider.addEventListener('input', () => {
        applyAdjustmentValues(
          parseInt(rngBright?.value || '0', 10),
          parseInt(rngContrast?.value || '0', 10),
          parseInt(rngSat?.value || '0', 10),
          parseInt(rngVignette?.value || '0', 10)
        );
      });
    });

    presetBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        presetBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        applyAdjustmentValues(
          parseInt(btn.getAttribute('data-bright') || '0', 10),
          parseInt(btn.getAttribute('data-contrast') || '0', 10),
          parseInt(btn.getAttribute('data-sat') || '0', 10),
          parseInt(btn.getAttribute('data-vignette') || '0', 10)
        );
      });
    });

    if (btnResetAdjust) {
      btnResetAdjust.addEventListener('click', () => {
        applyAdjustmentValues(0, 0, 0, 0);
      });
    }

    if (btnResetAll) {
      btnResetAll.addEventListener('click', () => {
        state.shapes = [];
        state.objects = [];
        state.selectedObjId = null;
        applyAdjustmentValues(0, 0, 0, 0);
        this.toast('Alle Bearbeitungen auf der Leinwand wurden zurückgesetzt.', 'info');
      });
    }

    // ----------------------------------------------------------------------
    // 6. Sticker, Verkaufs-Badges, Text & Creator-Logo hinzufügen
    // ----------------------------------------------------------------------
    const addOverlayObject = (objConfig) => {
      const w = canvas.width || 960;
      const h = canvas.height || 540;
      const offset = (state.objects.length % 5) * 18;
      const newObj = {
        id: state.nextObjId++,
        type: objConfig.type || 'sticker',
        x: objConfig.x ?? Math.round(w / 2 + offset),
        y: objConfig.y ?? Math.round(h / 2 + offset),
        w: 80,
        h: 80,
        scale: objConfig.scale ?? 100,
        rotate: objConfig.rotate ?? 0,
        opacity: objConfig.opacity ?? 100,
        text: objConfig.text || '',
        bg: objConfig.bg || '',
        color: objConfig.color || '#FFFFFF',
        img: objConfig.img || null
      };
      state.objects.push(newObj);
      state.selectedObjId = newObj.id;
      syncStudioUI();
      renderCanvas(true);
    };

    modalEl.querySelectorAll('.js-studio-add-sticker').forEach(btn => {
      btn.addEventListener('click', () => {
        const emoji = btn.getAttribute('data-sticker') || '❤️';
        addOverlayObject({ type: 'sticker', text: emoji, scale: 110 });
      });
    });

    modalEl.querySelectorAll('.js-studio-add-stamp').forEach(btn => {
      btn.addEventListener('click', () => {
        const stamp = btn.getAttribute('data-stamp') || 'FULL HD';
        const bg = btn.getAttribute('data-bg') || '#0284C7';
        const color = btn.getAttribute('data-color') || '#FFFFFF';
        const isCornerBadge = !stamp.includes('CENSORED') && !stamp.includes('FSK 16');
        const w = canvas.width || 960;
        const h = canvas.height || 540;
        addOverlayObject({
          type: 'stamp',
          text: stamp,
          bg,
          color,
          scale: 100,
          x: isCornerBadge ? Math.round(w * 0.82) : Math.round(w * 0.5),
          y: isCornerBadge ? Math.round(h * 0.14) : Math.round(h * 0.5)
        });
      });
    });

    if (btnAddText && inpCustomText) {
      btnAddText.addEventListener('click', () => {
        const raw = (inpCustomText.value || '').trim();
        if (!raw) {
          this.toast('Bitte gib zuerst einen kurzen Cover-Text ein.', 'warning');
          return;
        }
        // Schutz vor URLs / E-Mail-Adressen gemäß EroCloud-Richtlinien
        if (/https?:\/\/|www\.|[a-z0-9.-]+\.(de|com|net|org|io|tv|sex|xxx|cam|at|ch)\b|@/i.test(raw)) {
          this.toast('Internetadressen (URLs) oder E-Mail-Adressen sind auf Vorschaubildern nicht erlaubt.', 'danger');
          return;
        }
        addOverlayObject({
          type: 'text',
          text: raw,
          color: selTextColor?.value || '#FFFFFF',
          bg: selTextBg?.value || 'shadow',
          scale: 100,
          x: Math.round((canvas.width || 960) * 0.5),
          y: Math.round((canvas.height || 540) * 0.84)
        });
        inpCustomText.value = '';
      });
    }

    // Gespeichertes Creator-Logo aus localStorage verwalten
    const syncSavedLogoButtons = () => {
      let hasSaved = false;
      try {
        hasSaved = !!localStorage.getItem(LOGO_STORAGE_KEY);
      } catch (_) {}
      if (btnInsertSavedLogo) btnInsertSavedLogo.classList.toggle('d-none', !hasSaved);
      if (btnClearSavedLogo) btnClearSavedLogo.classList.toggle('d-none', !hasSaved);
    };
    syncSavedLogoButtons();

    const insertLogoFromDataUrl = (dataUrl) => {
      const logoImg = new Image();
      logoImg.onload = () => {
        const w = canvas.width || 960;
        const h = canvas.height || 540;
        addOverlayObject({
          type: 'logo',
          img: logoImg,
          scale: 100,
          opacity: 90,
          x: Math.round(w * 0.84),
          y: Math.round(h * 0.86)
        });
      };
      logoImg.src = dataUrl;
    };

    if (inpLogoUpload) {
      inpLogoUpload.addEventListener('change', () => {
        const file = inpLogoUpload.files?.[0];
        if (!file) return;
        if (file.size > 4 * 1024 * 1024) {
          this.toast('Deine Logo-Datei darf maximal 4 MB groß sein.', 'warning');
          inpLogoUpload.value = '';
          return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
          const dataUrl = String(ev.target?.result || '');
          if (!dataUrl) return;
          try {
            localStorage.setItem(LOGO_STORAGE_KEY, dataUrl);
            syncSavedLogoButtons();
          } catch (_) {}
          insertLogoFromDataUrl(dataUrl);
          this.toast('Dein Creator-Logo wurde eingefügt und für künftige Filme gemerkt!', 'success');
        };
        reader.readAsDataURL(file);
        inpLogoUpload.value = '';
      });
    }

    if (btnInsertSavedLogo) {
      btnInsertSavedLogo.addEventListener('click', () => {
        try {
          const saved = localStorage.getItem(LOGO_STORAGE_KEY);
          if (saved) insertLogoFromDataUrl(saved);
        } catch (_) {}
      });
    }

    if (btnClearSavedLogo) {
      btnClearSavedLogo.addEventListener('click', () => {
        try {
          localStorage.removeItem(LOGO_STORAGE_KEY);
        } catch (_) {}
        syncSavedLogoButtons();
        this.toast('Gespeichertes Browser-Logo wurde entfernt.', 'info');
      });
    }

    // Objekt-Inspektor Regler (Größe, Drehung, Deckkraft, Löschen, Eck-Positionierung)
    if (rngObjScale) {
      rngObjScale.addEventListener('input', () => {
        const sel = state.objects.find(o => o.id === state.selectedObjId);
        if (!sel) return;
        sel.scale = parseInt(rngObjScale.value || '100', 10);
        if (lblObjScale) lblObjScale.textContent = `${sel.scale}%`;
        renderCanvas(true);
      });
    }
    if (rngObjRotate) {
      rngObjRotate.addEventListener('input', () => {
        const sel = state.objects.find(o => o.id === state.selectedObjId);
        if (!sel) return;
        sel.rotate = parseInt(rngObjRotate.value || '0', 10);
        if (lblObjRotate) lblObjRotate.textContent = `${sel.rotate}°`;
        renderCanvas(true);
      });
    }
    if (rngObjOpacity) {
      rngObjOpacity.addEventListener('input', () => {
        const sel = state.objects.find(o => o.id === state.selectedObjId);
        if (!sel) return;
        sel.opacity = parseInt(rngObjOpacity.value || '100', 10);
        if (lblObjOpacity) lblObjOpacity.textContent = `${sel.opacity}%`;
        renderCanvas(true);
      });
    }
    if (btnDeleteSelectedObj) {
      btnDeleteSelectedObj.addEventListener('click', () => {
        if (!state.selectedObjId) return;
        state.objects = state.objects.filter(o => o.id !== state.selectedObjId);
        state.selectedObjId = null;
        syncStudioUI();
        renderCanvas(true);
      });
    }

    modalEl.querySelectorAll('.js-studio-snap-corner').forEach(btn => {
      btn.addEventListener('click', () => {
        const sel = state.objects.find(o => o.id === state.selectedObjId) || state.objects[state.objects.length - 1];
        if (!sel) {
          this.toast('Bitte füge zuerst ein Logo, Badge oder Text hinzu.', 'warning');
          return;
        }
        state.selectedObjId = sel.id;
        const m = getObjectMetrics(sel);
        const w = canvas.width || 960;
        const h = canvas.height || 540;
        const padX = Math.round(w * 0.035) + Math.round(m.w / 2);
        const padY = Math.round(h * 0.045) + Math.round(m.h / 2);
        const corner = btn.getAttribute('data-corner') || 'br';

        if (corner === 'tl') { sel.x = padX; sel.y = padY; }
        if (corner === 'tr') { sel.x = w - padX; sel.y = padY; }
        if (corner === 'bl') { sel.x = padX; sel.y = h - padY; }
        if (corner === 'br') { sel.x = w - padX; sel.y = h - padY; }

        syncStudioUI();
        renderCanvas(true);
      });
    });

    // ----------------------------------------------------------------------
    // 7. Canvas Maus- & Touch-Interaktion (Objekte ziehen/skalieren + Zensur zeichnen)
    // ----------------------------------------------------------------------
    const getCanvasCoords = (clientX, clientY) => {
      const rect = canvas.getBoundingClientRect();
      const scaleX = canvas.width / Math.max(1, rect.width);
      const scaleY = canvas.height / Math.max(1, rect.height);
      return {
        x: Math.max(0, Math.min(canvas.width, (clientX - rect.left) * scaleX)),
        y: Math.max(0, Math.min(canvas.height, (clientY - rect.top) * scaleY))
      };
    };

    const hitTestObjects = (pt) => {
      // Von oben nach unten prüfen
      for (let i = state.objects.length - 1; i >= 0; i--) {
        const obj = state.objects[i];
        const m = getObjectMetrics(obj);
        const pad = 8;
        const halfW = m.w / 2 + pad;
        const halfH = m.h / 2 + pad;

        const dx = pt.x - obj.x;
        const dy = pt.y - obj.y;

        // Prüfen, ob der Resize-Griff unten rechts getroffen wurde
        if (state.selectedObjId === obj.id) {
          const handleDist = Math.hypot(dx - halfW, dy - halfH);
          const handleR = Math.max(14, Math.round(canvas.width / 55));
          if (handleDist <= handleR) {
            return { hit: 'resize', obj };
          }
        }

        if (Math.abs(dx) <= halfW && Math.abs(dy) <= halfH) {
          return { hit: 'move', obj };
        }
      }
      return null;
    };

    const onPointerDown = (clientX, clientY) => {
      if (!state.baseImage) return;
      const pt = getCanvasCoords(clientX, clientY);

      // 1. Zuerst prüfen, ob ein Sticker / Badge / Text / Logo angeklickt wurde
      const hitResult = hitTestObjects(pt);
      if (hitResult) {
        state.selectedObjId = hitResult.obj.id;
        if (hitResult.hit === 'resize') {
          state.interaction = {
            mode: 'scale-obj',
            obj: hitResult.obj,
            startScale: hitResult.obj.scale || 100,
            startDist: Math.max(10, Math.hypot(pt.x - hitResult.obj.x, pt.y - hitResult.obj.y))
          };
        } else {
          state.interaction = {
            mode: 'drag-obj',
            obj: hitResult.obj,
            offsetX: pt.x - hitResult.obj.x,
            offsetY: pt.y - hitResult.obj.y
          };
        }
        syncStudioUI();
        renderCanvas(true);
        return;
      }

      // Klick ins Leere hebt Objekt-Auswahl auf
      if (state.selectedObjId !== null) {
        state.selectedObjId = null;
        syncStudioUI();
        renderCanvas(true);
      }

      // 2. Falls im Zensur-Tab aktiv: Neue Zensur-Form zeichnen
      if (state.activeTab === 'censor') {
        if (state.currentTool === 'brush') {
          state.interaction = {
            mode: 'draw-shape',
            startX: pt.x,
            startY: pt.y,
            shape: {
              type: 'brush',
              effect: state.currentEffect,
              intensity: state.currentIntensity,
              brushSize: state.brushSize,
              points: [{ x: pt.x, y: pt.y }]
            }
          };
        } else {
          state.interaction = {
            mode: 'draw-shape',
            startX: pt.x,
            startY: pt.y,
            shape: {
              type: state.currentTool,
              effect: state.currentEffect,
              intensity: state.currentIntensity,
              x: pt.x,
              y: pt.y,
              w: 0,
              h: 0
            }
          };
        }
      }
    };

    const onPointerMove = (clientX, clientY) => {
      if (!state.interaction) return;
      const pt = getCanvasCoords(clientX, clientY);
      const act = state.interaction;

      if (act.mode === 'drag-obj' && act.obj) {
        act.obj.x = Math.max(0, Math.min(canvas.width, pt.x - act.offsetX));
        act.obj.y = Math.max(0, Math.min(canvas.height, pt.y - act.offsetY));
        renderCanvas(true);
        return;
      }

      if (act.mode === 'scale-obj' && act.obj) {
        const curDist = Math.max(10, Math.hypot(pt.x - act.obj.x, pt.y - act.obj.y));
        const newScale = Math.max(30, Math.min(280, Math.round((act.startScale * curDist) / act.startDist)));
        act.obj.scale = newScale;
        if (rngObjScale) rngObjScale.value = String(newScale);
        if (lblObjScale) lblObjScale.textContent = `${newScale}%`;
        renderCanvas(true);
        return;
      }

      if (act.mode === 'draw-shape' && act.shape) {
        if (act.shape.type === 'brush') {
          act.shape.points.push({ x: pt.x, y: pt.y });
        } else {
          act.shape.x = Math.min(act.startX, pt.x);
          act.shape.y = Math.min(act.startY, pt.y);
          act.shape.w = Math.abs(pt.x - act.startX);
          act.shape.h = Math.abs(pt.y - act.startY);
        }
        renderCanvas(true);
      }
    };

    const onPointerUp = () => {
      if (!state.interaction) return;
      const act = state.interaction;
      state.interaction = null;

      if (act.mode === 'draw-shape' && act.shape) {
        if (act.shape.type === 'brush' && act.shape.points.length >= 1) {
          state.shapes.push(act.shape);
          syncStudioUI();
        } else if (act.shape.w >= 10 && act.shape.h >= 10) {
          state.shapes.push({ ...act.shape });
          syncStudioUI();
        }
      }
      renderCanvas(true);
    };

    canvas.addEventListener('mousedown', (ev) => {
      if (ev.button !== 0) return;
      onPointerDown(ev.clientX, ev.clientY);
    });
    window.addEventListener('mousemove', (ev) => {
      if (state.interaction) {
        onPointerMove(ev.clientX, ev.clientY);
      }
    });
    window.addEventListener('mouseup', () => {
      if (state.interaction) {
        onPointerUp();
      }
    });

    canvas.addEventListener('touchstart', (ev) => {
      if (ev.touches.length === 1) {
        ev.preventDefault();
        onPointerDown(ev.touches[0].clientX, ev.touches[0].clientY);
      }
    }, { passive: false });
    canvas.addEventListener('touchmove', (ev) => {
      if (state.interaction && ev.touches.length === 1) {
        ev.preventDefault();
        onPointerMove(ev.touches[0].clientX, ev.touches[0].clientY);
      }
    }, { passive: false });
    canvas.addEventListener('touchend', () => {
      if (state.interaction) {
        onPointerUp();
      }
    });

    // ----------------------------------------------------------------------
    // 8. Quellen laden: Szenenbild, Lokales Foto oder Live-Video-Frame
    // ----------------------------------------------------------------------
    const setVideoStageMode = (showVideo) => {
      if (!videoDrawer) return;
      if (showVideo) {
        videoDrawer.classList.remove('d-none');
        if (canvasContainer) canvasContainer.classList.add('d-none');
        if (loadingEl) loadingEl.classList.add('d-none');
        if (btnToggleVideoDrawer) btnToggleVideoDrawer.classList.add('active');
      } else {
        if (studioVideoEl) studioVideoEl.pause();
        videoDrawer.classList.add('d-none');
        if (state.baseImage && canvasContainer) {
          canvasContainer.classList.remove('d-none');
        }
        if (btnToggleVideoDrawer) btnToggleVideoDrawer.classList.remove('active');
      }
    };

    const highlightFilmstripItem = (numStr) => {
      modalEl.querySelectorAll('.js-studio-filmstrip-thumb').forEach(el => {
        el.classList.toggle('active', el.getAttribute('data-num') === String(numStr));
      });
    };

    const getSceneLabel = (numStr, customLabel = '') => {
      if (customLabel) return customLabel;
      const s = String(numStr);
      if (state.studioContext === 'photo-album') {
        if (s === 'cover_16' || s === '11') return 'FSK 16 Cover';
        if (s === 'cover_18' || s === '12') return 'FSK 18 Cover';
        const filmItem = modalEl.querySelector(`.js-studio-filmstrip-thumb[data-num="${s}"]`);
        if (filmItem && filmItem.getAttribute('data-label')) {
          return filmItem.getAttribute('data-label');
        }
        return 'Album-Foto';
      }
      if (s === '11') return 'Eigenes FSK16 (#11)';
      if (s === '12') return 'Eigenes FSK18 (#12)';
      return `Szene #${s}`;
    };

    const loadBaseImageElement = (img, label, isCustomSource) => {
      const natW = img.naturalWidth || img.width || 960;
      const natH = img.naturalHeight || img.height || 540;
      canvas.width = natW;
      canvas.height = natH;

      state.baseImage = img;
      state.sourceLabel = label;
      state.isCustomSource = !!isCustomSource;
      invalidateCaches();

      setVideoStageMode(false);
      if (loadingEl) loadingEl.classList.add('d-none');
      if (canvasContainer) canvasContainer.classList.remove('d-none');
      syncStudioUI();
      renderCanvas(true);
    };

    const loadSceneIntoCanvas = (num, thumbUrl, zoomUrl, editorUrl, customLabel = '') => {
      state.num = String(num || (state.studioContext === 'photo-album' ? 'cover_16' : '1'));
      state.thumbUrl = thumbUrl || '';
      state.zoomUrl = zoomUrl || thumbUrl || '';
      state.editorUrl = editorUrl || zoomUrl || thumbUrl || '';
      highlightFilmstripItem(state.num);
      setVideoStageMode(false);

      const labelName = getSceneLabel(state.num, customLabel);
      state.sourceLabel = labelName;
      syncStudioUI();

      if (!state.editorUrl) {
        state.baseImage = null;
        if (canvasContainer) canvasContainer.classList.add('d-none');
        if (loadingEl) {
          loadingEl.innerHTML = `
            <div class="text-info small px-3">
              <i class="bi bi-images fs-2 d-block mb-2"></i>
              Bitte wähle unten im Filmstreifen ein Foto aus oder klicke links auf <strong>„Eigenes Foto laden"</strong>, um ein Bild vom Gerät zu öffnen.
            </div>
          `;
          loadingEl.classList.remove('d-none');
        }
        return;
      }

      if (loadingEl) {
        loadingEl.innerHTML = `
          <div class="spinner-border text-info mb-2" role="status"></div>
          <div class="ec-studio-subtext">Hochauflösendes Coverbild wird im Studio geladen...</div>
        `;
        loadingEl.classList.remove('d-none');
      }
      if (canvasContainer) canvasContainer.classList.add('d-none');

      const sep = state.editorUrl.includes('?') ? '&' : '?';
      const targetUrl = `${state.editorUrl}${sep}_cb=${Date.now()}`;

      const img = new Image();
      try {
        const parsedUrl = new URL(targetUrl, window.location.href);
        if (parsedUrl.origin !== window.location.origin) {
          img.crossOrigin = 'anonymous';
        }
      } catch (_) {
        img.crossOrigin = 'anonymous';
      }

      img.onload = () => {
        loadBaseImageElement(img, labelName, false);
      };
      img.onerror = () => {
        if (loadingEl) {
          loadingEl.innerHTML = '<div class="text-danger small">Das Vorschaubild konnte nicht geladen werden. Bitte wähle unten im Filmstreifen ein Foto oder lade ein eigenes Bild vom Gerät.</div>';
        }
      };
      img.src = targetUrl;
    };

    const openStudioWithScene = (movieId, num, thumbUrl, zoomUrl, editorUrl, targetFsk = '16', customLabel = '', defaultTab = '') => {
      this.hideThumbZoomPopover();
      if (typeof this.hideGlobalSearchDropdown === 'function') {
        this.hideGlobalSearchDropdown();
      }
      state.movieId = String(movieId || state.entityId || state.movieId || '0');
      state.shapes = [];
      state.objects = [];
      state.selectedObjId = null;
      state.interaction = null;
      applyAdjustmentValues(0, 0, 0, 0);

      setTargetFskMode(targetFsk, true);
      if (defaultTab) {
        switchStudioTab(defaultTab);
      }

      const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
      bsModal.show();

      loadSceneIntoCanvas(num, thumbUrl, zoomUrl, editorUrl, customLabel);
    };

    // Wenn im Header zwischen FSK 16 und FSK 18 umgeschaltet wird (und noch keine Bearbeitung aktiv ist),
    // automatisch das aktuell aktive Bild des gewählten FSK-Modus laden
    if (btnModeFsk16) {
      btnModeFsk16.addEventListener('click', () => {
        const was18 = state.targetFsk === '18';
        setTargetFskMode('16', true);
        if (was18 && !hasAnyEdits()) {
          if (state.studioContext === 'photo-album') {
            const custom11 = document.getElementById('studio-filmstrip-custom-11');
            if (custom11 && !custom11.classList.contains('d-none')) {
              loadSceneIntoCanvas(
                custom11.getAttribute('data-num') || 'cover_16',
                custom11.getAttribute('data-thumb-url'),
                custom11.getAttribute('data-zoom-url'),
                custom11.getAttribute('data-editor-url'),
                custom11.getAttribute('data-label') || 'FSK 16 Cover'
              );
            }
          } else {
            const wrap16 = document.getElementById('current-fsk16-wrap');
            if (wrap16 && wrap16.getAttribute('data-studio-ready') !== '0') {
              loadSceneIntoCanvas(
                wrap16.getAttribute('data-num'),
                wrap16.getAttribute('data-thumb-url'),
                wrap16.getAttribute('data-zoom-src'),
                wrap16.getAttribute('data-editor-url')
              );
            }
          }
        }
      });
    }
    if (btnModeFsk18) {
      btnModeFsk18.addEventListener('click', () => {
        const was16 = state.targetFsk === '16';
        setTargetFskMode('18', true);
        if (was16 && !hasAnyEdits()) {
          if (state.studioContext === 'photo-album') {
            const custom12 = document.getElementById('studio-filmstrip-custom-12');
            if (custom12 && !custom12.classList.contains('d-none')) {
              loadSceneIntoCanvas(
                custom12.getAttribute('data-num') || 'cover_18',
                custom12.getAttribute('data-thumb-url'),
                custom12.getAttribute('data-zoom-url'),
                custom12.getAttribute('data-editor-url'),
                custom12.getAttribute('data-label') || 'FSK 18 Cover'
              );
            }
          } else {
            const wrap18 = document.getElementById('current-fsk18-wrap');
            if (wrap18 && wrap18.getAttribute('data-studio-ready') !== '0') {
              loadSceneIntoCanvas(
                wrap18.getAttribute('data-num'),
                wrap18.getAttribute('data-thumb-url'),
                wrap18.getAttribute('data-zoom-src'),
                wrap18.getAttribute('data-editor-url')
              );
            }
          }
        }
      });
    }

    // Filmstreifen-Klick im Studio
    modalEl.querySelectorAll('.js-studio-filmstrip-thumb').forEach(item => {
      item.addEventListener('click', () => {
        const num = item.getAttribute('data-num') || '1';
        const tUrl = item.getAttribute('data-thumb-url') || '';
        const zUrl = item.getAttribute('data-zoom-url') || tUrl;
        const eUrl = item.getAttribute('data-editor-url') || zUrl;
        const lbl = item.getAttribute('data-label') || '';
        loadSceneIntoCanvas(num, tUrl, zUrl, eUrl, lbl);
      });
    });

    // Lokales Bild vom Gerät in das Studio laden
    if (inpLocalImage) {
      inpLocalImage.addEventListener('change', () => {
        const file = inpLocalImage.files?.[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) {
          this.toast('Das ausgewählte Foto darf maximal 10 MB groß sein.', 'warning');
          inpLocalImage.value = '';
          return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
          const dataUrl = String(ev.target?.result || '');
          if (!dataUrl) return;
          const img = new Image();
          img.onload = () => {
            highlightFilmstripItem('');
            loadBaseImageElement(img, 'Eigenes Foto vom Gerät', true);
            this.toast('Dein eigenes Foto wurde auf die Studio-Leinwand geladen!', 'success');
          };
          img.src = dataUrl;
        };
        reader.readAsDataURL(file);
        inpLocalImage.value = '';
      });
    }

    // Live-Schnappschuss aus einem <video>-Element auf die Studio-Leinwand übernehmen
    const captureVideoFrameToStudio = (videoEl, openModalIfClosed = false, targetFsk = '16') => {
      if (!videoEl || videoEl.readyState < 2 || !videoEl.videoWidth) {
        this.toast('Bitte starte das Video kurz und pausiere an deiner Wunschstelle, damit ein Bild aufgenommen werden kann.', 'warning');
        return;
      }
      videoEl.pause();

      try {
        const vw = videoEl.videoWidth || 1280;
        const vh = videoEl.videoHeight || 720;
        const snapCanvas = document.createElement('canvas');
        snapCanvas.width = vw;
        snapCanvas.height = vh;
        const sctx = snapCanvas.getContext('2d');
        sctx.drawImage(videoEl, 0, 0, vw, vh);

        const dataUrl = snapCanvas.toDataURL('image/jpeg', 0.95);
        const snapImg = new Image();
        snapImg.onload = () => {
          if (openModalIfClosed) {
            state.shapes = [];
            state.objects = [];
            state.selectedObjId = null;
            applyAdjustmentValues(0, 0, 0, 0);
            setTargetFskMode(targetFsk, true);
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
          }
          setVideoStageMode(false);
          highlightFilmstripItem('');
          const sec = Math.floor(videoEl.currentTime || 0);
          const minStr = `${Math.floor(sec / 60)}:${String(sec % 60).padStart(2, '0')}`;
          loadBaseImageElement(snapImg, `Video-Schnappschuss (${minStr})`, true);
          this.toast(`Live-Schnappschuss bei ${minStr} Min. in das Cover-Studio übernommen!`, 'success');
        };
        snapImg.src = dataUrl;
      } catch (err) {
        this.toast('Schnappschuss konnte nicht erstellt werden: ' + err.message, 'danger');
      }
    };

    if (btnToggleVideoDrawer && videoDrawer) {
      btnToggleVideoDrawer.addEventListener('click', () => {
        const isCurrentlyHidden = videoDrawer.classList.contains('d-none');
        setVideoStageMode(isCurrentlyHidden);
      });
    }
    if (btnCloseVideoDrawer) {
      btnCloseVideoDrawer.addEventListener('click', () => setVideoStageMode(false));
    }
    if (btnCancelVideoDrawer) {
      btnCancelVideoDrawer.addEventListener('click', () => setVideoStageMode(false));
    }
    if (btnGrabVideoFrame && studioVideoEl) {
      btnGrabVideoFrame.addEventListener('click', () => {
        captureVideoFrameToStudio(studioVideoEl, false, state.targetFsk);
      });
    }

    // Direkt-Buttons unter dem Haupt-Videoplayer auf der Film-Detailseite
    document.querySelectorAll('.js-capture-video-to-studio').forEach(btn => {
      btn.addEventListener('click', () => {
        const mainVideo = document.getElementById('movie-detail-video-player');
        const targetFsk = btn.getAttribute('data-target-fsk') || '16';
        captureVideoFrameToStudio(mainVideo, true, targetFsk);
      });
    });

    // Klick auf ein Vorschaubild oder auf „FSK16 / FSK18" an einem Album-Foto öffnet das Fullscreen Cover-Studio
    document.querySelectorAll('.js-open-thumb-editor-modal').forEach(el => {
      el.addEventListener('click', (ev) => {
        ev.preventDefault();
        if (el.getAttribute('data-studio-ready') === '0') {
          this.toast('Das Cover-Studio ist erst verfügbar, sobald ein Vorschaubild hochgeladen wurde oder die Video-Konvertierung abgeschlossen ist.', 'info');
          return;
        }
        const targetFsk = el.getAttribute('data-target-fsk') || el.getAttribute('data-fsk') || '16';
        const sceneNum = el.getAttribute('data-num') || el.getAttribute('data-scene') || '1';
        const customLabel = el.getAttribute('data-label') || '';
        const defaultTab = el.getAttribute('data-default-tab') || '';
        const editorUrl = el.getAttribute('data-editor-url') || '';
        const zoomUrl = el.getAttribute('data-zoom-src') || editorUrl;
        const thumbUrl = el.getAttribute('data-thumb-url') || el.getAttribute('src') || zoomUrl;

        openStudioWithScene(
          el.getAttribute('data-movie-id') || state.entityId,
          sceneNum,
          thumbUrl,
          zoomUrl,
          editorUrl,
          targetFsk,
          customLabel,
          defaultTab
        );
      });
    });

    document.querySelectorAll('.js-open-active-fsk-editor').forEach(btn => {
      btn.addEventListener('click', () => {
        const fsk = btn.getAttribute('data-target-fsk') || btn.getAttribute('data-fsk') || '16';

        // Foto-Album Modus
        if (state.studioContext === 'photo-album') {
          const isReady = btn.getAttribute('data-studio-ready') === '1';
          if (isReady) {
            const imgEl = document.getElementById(`preview-fsk${fsk}-img`);
            const sceneNum = btn.getAttribute('data-scene') || (`cover_${fsk}`);
            const eUrl = (imgEl && imgEl.getAttribute('data-editor-url')) || btn.getAttribute('data-editor-url') || '';
            const zUrl = (imgEl && imgEl.getAttribute('data-zoom-src')) || eUrl;
            const tUrl = (imgEl && imgEl.getAttribute('src')) || zUrl;
            openStudioWithScene(state.entityId, sceneNum, tUrl, zUrl, eUrl, fsk, `FSK ${fsk} Cover`);
            return;
          }

          // Noch kein eigenes FSK16/18 Cover vorhanden -> automatisch erstes Album-Foto aus dem Filmstreifen als Vorlage laden
          const firstPhotoItem = modalEl.querySelector('.js-studio-filmstrip-thumb:not([id^="studio-filmstrip-custom-"])');
          if (firstPhotoItem) {
            const pNum = firstPhotoItem.getAttribute('data-num') || 'photo_1';
            const pThumb = firstPhotoItem.getAttribute('data-thumb-url') || '';
            const pZoom = firstPhotoItem.getAttribute('data-zoom-url') || pThumb;
            const pEditor = firstPhotoItem.getAttribute('data-editor-url') || pZoom;
            const pLabel = firstPhotoItem.getAttribute('data-label') || 'Foto #1';
            openStudioWithScene(state.entityId, pNum, pThumb, pZoom, pEditor, fsk, pLabel);
            this.toast(`${pLabel} wurde als Vorlage geladen. Du kannst unten im Filmstreifen jederzeit ein anderes Album-Foto wählen!`, 'info');
          } else {
            openStudioWithScene(state.entityId, `cover_${fsk}`, '', '', '', fsk, `FSK ${fsk} Cover`);
            this.toast('Lade im Studio über „Eigenes Foto laden" ein Bild vom Gerät oder lade zuerst Fotos in dein Album hoch.', 'info');
          }
          return;
        }

        // Video (Movie) Modus
        if (btn.getAttribute('data-studio-ready') === '0') {
          this.toast('Das Cover-Studio ist erst verfügbar, sobald ein Vorschaubild hochgeladen wurde oder die Video-Konvertierung abgeschlossen ist.', 'info');
          return;
        }
        const wrap = document.getElementById(`current-fsk${fsk}-wrap`);
        if (!wrap || wrap.getAttribute('data-studio-ready') === '0') {
          this.toast('Das Cover-Studio ist erst verfügbar, sobald ein Vorschaubild hochgeladen wurde oder die Video-Konvertierung abgeschlossen ist.', 'info');
          return;
        }
        openStudioWithScene(
          wrap.getAttribute('data-movie-id'),
          wrap.getAttribute('data-num'),
          wrap.getAttribute('data-thumb-url'),
          wrap.getAttribute('data-zoom-src'),
          wrap.getAttribute('data-editor-url'),
          fsk
        );
      });
    });

    // ----------------------------------------------------------------------
    // 9. Speichern & Aktivieren (für den gewählten Ziel-Modus FSK 16 oder FSK 18)
    // ----------------------------------------------------------------------
    if (btnSavePrimary) {
      btnSavePrimary.addEventListener('click', async () => {
        const targetFsk = state.targetFsk === '18' ? '18' : '16';

        // Foto-Album Modus: Leinwand immer als JPEG exportieren und an photo-albums/upload-cover senden
        if (state.studioContext === 'photo-album') {
          if (!state.baseImage) {
            this.toast('Bitte wähle zuerst unten ein Album-Foto aus oder lade ein eigenes Bild in das Studio.', 'warning');
            return;
          }

          state.selectedObjId = null;
          renderCanvas(false);
          btnSavePrimary.disabled = true;

          canvas.toBlob(async (blob) => {
            if (!blob) {
              this.toast('Das Coverbild konnte nicht erzeugt werden.', 'danger');
              btnSavePrimary.disabled = false;
              renderCanvas(true);
              return;
            }

            try {
              const file = new File([blob], `album_fsk${targetFsk}_studio_${state.entityId}.jpg`, { type: 'image/jpeg' });
              const formData = new FormData();
              formData.append('album_id', state.entityId);
              formData.append('fsk', targetFsk);
              formData.append('cover', file);
              formData.append('_csrf', this.csrfToken);

              const res = await fetch(`${this.basePath}/api.php?route=photo-albums/upload-cover`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': this.csrfToken },
                body: formData
              });
              const data = await res.json();

              if (res.ok && data.status === 'ok') {
                this.applyPhotoAlbumCoverUpdate(targetFsk, data);
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();
                this.toast(`Dein im Cover-Studio gestaltetes FSK ${targetFsk} Album-Cover wurde gespeichert!`, 'success');
              } else {
                this.toast(data.message || `Fehler beim Speichern des FSK ${targetFsk} Album-Covers.`, 'danger');
                renderCanvas(true);
              }
            } catch (err) {
              this.toast('Upload-Fehler: ' + err.message, 'danger');
              renderCanvas(true);
            } finally {
              btnSavePrimary.disabled = false;
            }
          }, 'image/jpeg', 0.92);
          return;
        }

        // Video (Movie) Modus:
        // Fall A: Keine Bearbeitungen vorgenommen und ein reguläres Szenenbild (#1..#10) oder passendes eigenes Bild (#11/#12) ist aktiv
        if (!hasAnyEdits()) {
          let targetNum = state.num;
          if (targetFsk === '16' && targetNum === '12') targetNum = '1';
          if (targetFsk === '18' && targetNum === '11') targetNum = '1';

          if (typeof applyMovieThumbSelection === 'function') {
            applyMovieThumbSelection(targetFsk, targetNum, state.thumbUrl, state.zoomUrl, state.editorUrl, false);
          }
          const bsModal = bootstrap.Modal.getInstance(modalEl);
          if (bsModal) bsModal.hide();
          this.toast(`${state.sourceLabel} als FSK ${targetFsk} Cover ausgewählt. Bitte speichere anschließend deine Änderungen.`, 'success');
          return;
        }

        // Fall B: Bild wurde im Studio bearbeitet -> als eigenes Cover (#11 für FSK16 bzw. #12 für FSK18) speichern!
        state.selectedObjId = null;
        renderCanvas(false);
        btnSavePrimary.disabled = true;

        canvas.toBlob(async (blob) => {
          if (!blob) {
            this.toast('Das bearbeitete Coverbild konnte nicht erzeugt werden.', 'danger');
            btnSavePrimary.disabled = false;
            renderCanvas(true);
            return;
          }

          try {
            const file = new File([blob], `fsk${targetFsk}_studio_${state.movieId}.jpg`, { type: 'image/jpeg' });
            const formData = new FormData();
            formData.append('movie_id', state.movieId);
            formData.append('fsk', targetFsk);
            formData.append('poster', file);
            formData.append('_csrf', this.csrfToken);

            const res = await fetch(`${this.basePath}/api.php?route=movies/upload-poster`, {
              method: 'POST',
              headers: { 'X-CSRF-Token': this.csrfToken },
              body: formData
            });
            const data = await res.json();

            if (res.ok && data.status === 'ok') {
              const thumbNum = data.thumb_number || (targetFsk === '16' ? 11 : 12);
              const previewUrl = data.preview_url || data.thumb_url || '';
              const zoomUrl = data.zoom_url || previewUrl;
              const editorUrl = data.editor_url || zoomUrl;

              if (typeof applyMovieThumbSelection === 'function') {
                applyMovieThumbSelection(targetFsk, thumbNum, previewUrl, zoomUrl, editorUrl, true);
              }
              const bsModal = bootstrap.Modal.getInstance(modalEl);
              if (bsModal) bsModal.hide();
              this.toast(`Dein im Cover-Studio gestaltetes FSK ${targetFsk} Cover (#${thumbNum}) wurde gespeichert und aktiviert!`, 'success');
            } else {
              this.toast(data.message || `Fehler beim Speichern des FSK ${targetFsk} Covers.`, 'danger');
              renderCanvas(true);
            }
          } catch (err) {
            this.toast('Upload-Fehler: ' + err.message, 'danger');
            renderCanvas(true);
          } finally {
            btnSavePrimary.disabled = false;
          }
        }, 'image/jpeg', 0.92);
      });
    }
  },

  /**
   * Aktualisiert die FSK16/FSK18-Cover-Boxen, das Filmstrip-Item im Cover-Studio
   * sowie die Status-Anzeige eines Foto-Albums ohne Seiten-Reload.
   */
  applyPhotoAlbumCoverUpdate(fsk, data) {
    const fskStr = String(fsk) === '18' ? '18' : '16';
    const previewUrl = data.preview_url || '';
    const zoomUrl = data.zoom_url || previewUrl;
    const editorUrl = data.editor_url || zoomUrl;

    // 1. Vorschau-Bild & Platzhalter aktualisieren
    const imgWrap = document.getElementById(`preview-fsk${fskStr}-img-wrap`);
    const imgEl = document.getElementById(`preview-fsk${fskStr}-img`) || document.getElementById(`album-cover-fsk${fskStr}-img`);
    const placeholderEl = document.getElementById(`empty-fsk${fskStr}-placeholder`);
    const badgeSourceEl = document.getElementById(`badge-fsk${fskStr}-source`);
    const studioBtn = document.getElementById(`btn-open-studio-fsk${fskStr}`);

    if (imgWrap) imgWrap.classList.remove('d-none');
    if (placeholderEl) {
      placeholderEl.classList.add('d-none');
      placeholderEl.setAttribute('data-studio-ready', '1');
      if (editorUrl) placeholderEl.setAttribute('data-editor-url', editorUrl);
    }
    if (imgEl && previewUrl) {
      imgEl.src = previewUrl;
      imgEl.setAttribute('data-zoom-src', zoomUrl);
      imgEl.setAttribute('data-editor-url', editorUrl);
    }
    if (badgeSourceEl) {
      badgeSourceEl.textContent = 'Eigenes Cover';
      badgeSourceEl.className = 'badge bg-primary';
    }
    if (studioBtn) {
      studioBtn.setAttribute('data-studio-ready', '1');
      if (editorUrl) studioBtn.setAttribute('data-editor-url', editorUrl);
    }

    // 2. Filmstreifen-Eintrag im Cover-Studio (#studio-filmstrip-custom-11 / 12) aktualisieren
    const slotNum = fskStr === '16' ? '11' : '12';
    const stripItem = document.getElementById(`studio-filmstrip-custom-${slotNum}`);
    const stripImg = document.getElementById(`studio-filmstrip-img-${slotNum}`);
    if (stripItem) {
      stripItem.classList.remove('d-none');
      if (previewUrl) stripItem.setAttribute('data-thumb-url', previewUrl);
      if (zoomUrl) stripItem.setAttribute('data-zoom-url', zoomUrl);
      if (editorUrl) stripItem.setAttribute('data-editor-url', editorUrl);
    }
    if (stripImg && previewUrl) {
      stripImg.src = previewUrl;
    }

    // 3. Warnhinweis & Schritt-1-Statusbox aktualisieren
    const coversCard = document.getElementById('photo-album-covers-card');
    if (coversCard) {
      coversCard.setAttribute(`data-has-fsk${fskStr}`, '1');
    }
    const has16 = typeof data.has_fsk16 !== 'undefined'
      ? !!data.has_fsk16
      : (coversCard ? coversCard.getAttribute('data-has-fsk16') === '1' : false);
    const has18 = typeof data.has_fsk18 !== 'undefined'
      ? !!data.has_fsk18
      : (coversCard ? coversCard.getAttribute('data-has-fsk18') === '1' : false);

    const warnBox = document.getElementById('fsk16-missing-warning');
    if (warnBox && has16 && has18) {
      warnBox.classList.add('d-none');
    }

    const step1Box = document.getElementById('photo-album-step1-status-box');
    const gridCountEl = document.getElementById('album-photos-grid-count');
    const totalPhotos = typeof data.total_photos !== 'undefined'
      ? parseInt(String(data.total_photos), 10)
      : parseInt(gridCountEl ? (gridCountEl.textContent || '0') : '0', 10);

    if (step1Box && has16 && has18 && totalPhotos >= 4) {
      step1Box.innerHTML = `
        <div class="p-2 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25">
          <div class="fw-bold text-success small">
            <i class="bi bi-check-circle-fill me-1"></i> 1. Fotos &amp; Cover komplett
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">${totalPhotos} Fotos sowie FSK16 &amp; FSK18 Cover vorhanden</div>
        </div>
      `;
    }

    this.initThumbnailHoverZoom();
  },

  // ========================================================================
  // 3c. Foto-Alben (PhotoAlbums): Cover/Foto-Upload & Bearbeitung
  // ========================================================================
  initPhotoAlbumsEvents() {
    // 1. Foto-Album löschen
    document.querySelectorAll('.js-delete-photo-album-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const albumId = btn.getAttribute('data-album-id');
        const albumTitle = btn.getAttribute('data-album-title') || (`#${albumId}`);
        if (!albumId) return;

        if (!window.confirm(`Möchtest du das Foto-Album „${albumTitle}" wirklich löschen?`)) {
          return;
        }

        btn.disabled = true;
        try {
          const formData = new FormData();
          formData.append('id', albumId);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=photo-albums/delete`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || 'Foto-Album gelöscht.', 'success');
            if (window.location.hash.startsWith('#/photo-album?')) {
              window.location.hash = '#/photo-albums';
            } else {
              this.handleRoute();
            }
          } else {
            this.toast(data.message || 'Das Foto-Album konnte nicht gelöscht werden.', 'danger');
            btn.disabled = false;
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
          btn.disabled = false;
        }
      });
    });

    // 3. Foto-Album Schritt 1 speichern (#/photo-album-upload)
    const step1Form = document.getElementById('kkc-photo-album-step1-form');
    if (step1Form) {
      step1Form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('btn-submit-photo-album-step1');
        if (submitBtn) submitBtn.disabled = true;

        try {
          const formData = new FormData(step1Form);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=photo-albums/save-step1`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || 'Basisdaten gespeichert.', 'success');
            if (data.redirect) {
              window.location.hash = data.redirect;
            }
          } else {
            this.toast(data.message || 'Bitte prüfe deine Eingaben.', 'danger');
            if (submitBtn) submitBtn.disabled = false;
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
          if (submitBtn) submitBtn.disabled = false;
        }
      });
    }

    // 4. Foto-Album Bearbeitungsformular speichern (#/photo-album?id=...)
    const editForm = document.getElementById('kkc-photo-album-edit-form');
    if (editForm) {
      editForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btns = document.querySelectorAll('#btn-save-photo-album-top, #btn-save-photo-album-bottom');
        btns.forEach(b => { b.disabled = true; });

        try {
          const formData = new FormData(editForm);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=photo-albums/update`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || 'Foto-Album erfolgreich aktualisiert.', 'success');
          } else {
            this.toast(data.message || 'Fehler beim Speichern des Foto-Albums.', 'danger');
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
        } finally {
          btns.forEach(b => { b.disabled = false; });
        }
      });
    }

    // 5. FSK16 / FSK18 Cover für Foto-Album direkt per Datei-Auswahl hochladen
    document.querySelectorAll('.js-album-cover-upload').forEach(input => {
      input.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) return;

        const albumId = input.getAttribute('data-album-id');
        const fsk = input.getAttribute('data-fsk');

        if (file.size > 6 * 1024 * 1024) {
          this.toast('Das Coverbild darf maximal 6 MB groß sein.', 'warning');
          input.value = '';
          return;
        }

        input.disabled = true;
        try {
          const formData = new FormData();
          formData.append('album_id', albumId);
          formData.append('fsk', fsk);
          formData.append('cover', file);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=photo-albums/upload-cover`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || `FSK${fsk}-Cover gespeichert.`, 'success');
            this.applyPhotoAlbumCoverUpdate(fsk, data);
          } else {
            this.toast(data.message || 'Fehler beim Hochladen des Coverbilds.', 'danger');
          }
        } catch (err) {
          this.toast('Upload-Fehler: ' + err.message, 'danger');
        } finally {
          input.disabled = false;
          input.value = '';
        }
      });
    });

    // 6. Mehrere Fotos ins Album hochladen
    document.querySelectorAll('.js-album-photos-upload').forEach(input => {
      input.addEventListener('change', async () => {
        const files = input.files;
        if (!files || files.length === 0) return;

        const albumId = input.getAttribute('data-album-id');
        const statusBox = document.getElementById('album-photos-upload-status');
        if (statusBox) statusBox.classList.remove('d-none');
        input.disabled = true;

        try {
          const formData = new FormData();
          formData.append('album_id', albumId);
          formData.append('_csrf', this.csrfToken);
          for (let i = 0; i < files.length; i++) {
            formData.append('photos[]', files[i]);
          }

          const res = await fetch(`${this.basePath}/api.php?route=photo-albums/upload-photos`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || 'Fotos erfolgreich hochgeladen.', 'success');
            this.handleRoute();
          } else {
            this.toast(data.message || 'Fehler beim Hochladen der Fotos.', 'danger');
          }
        } catch (err) {
          this.toast('Upload-Fehler: ' + err.message, 'danger');
        } finally {
          if (statusBox) statusBox.classList.add('d-none');
          input.disabled = false;
          input.value = '';
        }
      });
    });

    // 7. Einzelnes Foto aus dem Album löschen
    document.querySelectorAll('.js-delete-album-photo-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const albumId = btn.getAttribute('data-album-id');
        const fileId = btn.getAttribute('data-file-id');
        if (!albumId || !fileId) return;

        if (!window.confirm('Möchtest du dieses Foto wirklich aus dem Album entfernen?')) {
          return;
        }

        btn.disabled = true;
        try {
          const formData = new FormData();
          formData.append('album_id', albumId);
          formData.append('file_id', fileId);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=photo-albums/delete-photo`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || 'Foto entfernt.', 'success');
            const card = document.querySelector(`.js-album-photo-card[data-file-id="${fileId}"]`);
            if (card) card.remove();

            const stripThumb = document.querySelector(`.js-studio-filmstrip-thumb[data-num="photo_${fileId}"]`);
            if (stripThumb) stripThumb.remove();

            const countEls = document.querySelectorAll('#album-photos-grid-count, #badge-album-photo-count');
            countEls.forEach(el => {
              if (typeof data.total_photos !== 'undefined') {
                el.textContent = String(data.total_photos);
              }
            });
          } else {
            this.toast(data.message || 'Foto konnte nicht gelöscht werden.', 'danger');
            btn.disabled = false;
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
          btn.disabled = false;
        }
      });
    });

    // 8. Foto-Zoom-Modal öffnen
    document.querySelectorAll('.js-zoom-album-photo').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const zoomUrl = link.getAttribute('data-zoom-url');
        const imgEl = document.getElementById('modal-album-photo-zoom-img');
        const modalEl = document.getElementById('modalAlbumPhotoZoom');
        if (imgEl && zoomUrl && modalEl) {
          imgEl.src = zoomUrl;
          const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
          modal.show();
        }
      });
    });

    // 9. Foto-Album in Schritt 2 fertigstellen & zur Prüfung einreichen
    const btnPublishAlbum = document.getElementById('btn-publish-photo-album');
    if (btnPublishAlbum) {
      btnPublishAlbum.addEventListener('click', async () => {
        const albumId = btnPublishAlbum.getAttribute('data-album-id');
        if (!albumId) return;

        btnPublishAlbum.disabled = true;
        try {
          const formData = new FormData();
          formData.append('album_id', albumId);
          formData.append('_csrf', this.csrfToken);

          const res = await fetch(`${this.basePath}/api.php?route=photo-albums/publish`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrfToken },
            body: formData
          });
          const data = await res.json();

          if (res.ok && data.status === 'ok') {
            this.toast(data.message || 'Foto-Album erfolgreich eingereicht.', 'success');
            if (data.redirect) {
              window.location.hash = data.redirect;
            }
          } else {
            this.toast(data.message || 'Bitte vervollständige zuerst alle Bilder.', 'warning');
            btnPublishAlbum.disabled = false;
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
          btnPublishAlbum.disabled = false;
        }
      });
    }
  },

  // ========================================================================
  // 3d. Creator-Feedback & Testlauf-Meldungen (Direkter Draht zu Martin)
  // ========================================================================
  initCreatorFeedbackEvents() {
    const formEl = document.getElementById('ec-creator-feedback-form');
    if (!formEl) return;

    const categoryRadios = Array.from(formEl.querySelectorAll('input[name="ec_feedback_category"]'));
    const labelEl = document.getElementById('ec-feedback-label');
    const messageEl = document.getElementById('ec-feedback-message');
    const fileInput = document.getElementById('ec-feedback-files');
    const addFileBtn = document.getElementById('ec-feedback-add-file-btn');
    const fileCountBadge = document.getElementById('ec-feedback-file-count-badge');
    const fileListEl = document.getElementById('ec-feedback-file-list');
    const errorAlert = document.getElementById('ec-feedback-error-alert');
    const submitBtn = document.getElementById('ec-feedback-submit-btn');
    const successBox = document.getElementById('ec-feedback-success-box');
    const successTitle = document.getElementById('ec-feedback-success-title');
    const successText = document.getElementById('ec-feedback-success-text');
    const resetBtn = document.getElementById('ec-feedback-reset-btn');

    let selectedFiles = [];
    const maxFiles = 3;
    const maxFileSize = 6 * 1024 * 1024; // 6 MB
    const allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];

    const showError = (msg) => {
      if (!errorAlert) return;
      errorAlert.textContent = msg;
      errorAlert.classList.remove('d-none');
    };

    const hideError = () => {
      if (!errorAlert) return;
      errorAlert.textContent = '';
      errorAlert.classList.add('d-none');
    };

    // 1. Kategoriewechsel: Label & Platzhalter dynamisch anpassen
    categoryRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (!radio.checked || !labelEl || !messageEl) return;
        const val = radio.value;
        if (val === 'bug') {
          labelEl.textContent = '2. Welches Problem oder welcher Fehler ist dir im neuen Bereich aufgefallen?';
          messageEl.placeholder = 'Beschreibe mir kurz, wo das Problem auftritt und was genau passiert (z. B. beim Film-Upload, in den Fotoalben, bei deinen Profilen...)';
        } else if (val === 'idea') {
          labelEl.textContent = '2. Welche Funktion oder Verbesserung wünschst du dir?';
          messageEl.placeholder = 'Erzähl mir von deiner Idee! Was würde dir die Arbeit im EroCloud-Partnerbereich noch einfacher machen?';
        } else {
          labelEl.textContent = '2. Deine Nachricht oder Frage an mich:';
          messageEl.placeholder = 'Schreib mir einfach frei heraus, was dir zum neuen Partnerbereich auf dem Herzen liegt...';
        }
      });
    });

    // 2. Dateianhänge verwalten (Auswahl + Strg+V Zwischenablage)
    const renderSelectedFiles = () => {
      if (!fileListEl || !fileCountBadge || !addFileBtn) return;
      fileListEl.innerHTML = '';
      fileCountBadge.textContent = `${selectedFiles.length} / ${maxFiles}`;
      addFileBtn.disabled = (selectedFiles.length >= maxFiles);

      selectedFiles.forEach((file, idx) => {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        const isPdf = (ext === 'pdf' || file.type === 'application/pdf');
        const iconClass = isPdf ? 'bi-file-earmark-pdf-fill text-danger' : 'bi-file-earmark-image-fill ec-text-primary';
        const sizeKb = Math.max(1, Math.round(file.size / 1024));
        const sizeLabel = sizeKb >= 1024 ? `${(sizeKb / 1024).toFixed(1)} MB` : `${sizeKb} KB`;

        const chip = document.createElement('div');
        chip.className = 'd-inline-flex align-items-center gap-2 px-2 py-1 rounded-3 border small shadow-sm';
        chip.style.fontSize = '0.78rem';
        chip.style.backgroundColor = 'var(--kkc-surface)';
        chip.style.borderColor = 'var(--kkc-border-strong)';
        chip.innerHTML = `
          <i class="bi ${iconClass}"></i>
          <span class="text-truncate fw-medium" style="max-width: 170px;" title="${this.escapeHtml(file.name)}">${this.escapeHtml(file.name)}</span>
          <span class="text-muted">(${sizeLabel})</span>
          <button type="button" class="btn btn-sm p-0 border-0 text-secondary ms-1 js-ec-fb-remove-file" data-idx="${idx}" title="Datei entfernen" style="line-height: 1;">
            <i class="bi bi-x-circle-fill"></i>
          </button>
        `;
        fileListEl.appendChild(chip);
      });

      fileListEl.querySelectorAll('.js-ec-fb-remove-file').forEach(btn => {
        btn.addEventListener('click', () => {
          const rmIdx = parseInt(btn.getAttribute('data-idx') || '-1', 10);
          if (rmIdx >= 0 && rmIdx < selectedFiles.length) {
            selectedFiles.splice(rmIdx, 1);
            renderSelectedFiles();
          }
        });
      });
    };

    const addFilesToSelection = (fileList) => {
      hideError();
      for (let i = 0; i < fileList.length; i++) {
        if (selectedFiles.length >= maxFiles) {
          showError(`Du kannst maximal ${maxFiles} Dateien pro Nachricht anhängen.`);
          break;
        }
        const f = fileList[i];
        const ext = (f.name.split('.').pop() || '').toLowerCase();
        if (!allowedExts.includes(ext)) {
          showError(`Die Datei „${f.name}" hat kein unterstütztes Format (erlaubt: JPG, PNG, WEBP, GIF, PDF).`);
          continue;
        }
        if (f.size > maxFileSize) {
          showError(`Die Datei „${f.name}" ist größer als 6 MB.`);
          continue;
        }
        selectedFiles.push(f);
      }
      renderSelectedFiles();
    };

    if (addFileBtn && fileInput) {
      addFileBtn.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', () => {
        if (fileInput.files && fileInput.files.length > 0) {
          addFilesToSelection(fileInput.files);
          fileInput.value = '';
        }
      });
    }

    // Strg+V Screenshot-Paste direkt im Formular
    formEl.addEventListener('paste', (ev) => {
      const items = ev.clipboardData?.items;
      if (!items) return;
      const pastedFiles = [];
      for (let i = 0; i < items.length; i++) {
        const item = items[i];
        if (item.kind === 'file') {
          const blob = item.getAsFile();
          if (blob) {
            let ext = 'png';
            if (blob.type === 'image/jpeg') ext = 'jpg';
            else if (blob.type === 'image/webp') ext = 'webp';
            else if (blob.type === 'image/gif') ext = 'gif';
            else if (blob.type === 'application/pdf') ext = 'pdf';
            const stamp = new Date().toISOString().slice(11, 19).replace(/:/g, '');
            const namedFile = new File([blob], `screenshot-${stamp}.${ext}`, { type: blob.type });
            pastedFiles.push(namedFile);
          }
        }
      }
      if (pastedFiles.length > 0) {
        addFilesToSelection(pastedFiles);
      }
    });

    // 3. Verlauf & Rückmeldungen laden und rendern
    const renderFeedbackItemCard = (item, isUnreadReply) => {
      const catBadgeMap = {
        idea: '<span class="badge bg-warning bg-opacity-15 text-warning-emphasis border border-warning border-opacity-25"><i class="bi bi-lightbulb-fill text-warning me-1"></i>Idee / Wunsch</span>',
        bug: '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><i class="bi bi-bug-fill me-1"></i>Fehler / Problem</span>',
        question: '<span class="badge bg-primary bg-opacity-10 ec-text-primary border border-primary border-opacity-25"><i class="bi bi-chat-heart-fill me-1"></i>Frage / Feedback</span>'
      };
      const statusBadgeMap = {
        new: '<span class="badge bg-secondary bg-opacity-10 text-secondary border"><i class="bi bi-send-check me-1"></i>Bei Martin eingegangen</span>',
        in_progress: '<span class="badge bg-info bg-opacity-15 text-info-emphasis border border-info border-opacity-25"><i class="bi bi-gear-wide-connected me-1"></i>In Bearbeitung / Geplant</span>',
        resolved: '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check-circle-fill me-1"></i>Erledigt / Beantwortet</span>',
        archived: '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check2-all me-1"></i>Beantwortet</span>'
      };

      const catBadge = catBadgeMap[item.category] || catBadgeMap.bug;
      const statusBadge = statusBadgeMap[item.status] || statusBadgeMap.new;
      const hasReply = Boolean(item.admin_reply && String(item.admin_reply).trim() !== '');

      let attachmentsHtml = '';
      if (Array.isArray(item.attachments) && item.attachments.length > 0) {
        const chips = item.attachments.map(att => {
          const isPdf = (att.type === 'pdf');
          const icon = isPdf ? 'bi-file-earmark-pdf-fill text-danger' : 'bi-image ec-text-primary';
          return `<a href="${this.escapeHtml(att.url)}" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-2 border text-decoration-none small" style="font-size: 0.76rem; background-color: var(--kkc-surface); color: var(--kkc-text);">
            <i class="bi ${icon}"></i>
            <span class="text-truncate" style="max-width: 150px;">${this.escapeHtml(att.name || 'Anhang')}</span>
          </a>`;
        }).join('');
        attachmentsHtml = `<div class="d-flex flex-wrap gap-1 mt-2">${chips}</div>`;
      }

      let replyBoxHtml = '';
      if (hasReply) {
        replyBoxHtml = `
          <div class="mt-3 p-3 rounded-3 border ${isUnreadReply ? 'border-success bg-success bg-opacity-10 ec-fb-reply-highlight' : 'border-success border-opacity-25 bg-success bg-opacity-10'}">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <div class="d-flex align-items-center gap-2">
                <img src="assets/img/ceo-150x150.jpg" onerror="this.onerror=null;this.src='https://erocms.net/images/ceo-150x150.jpg';" alt="Martin" class="rounded-circle object-fit-cover border border-2 border-white shadow-sm" style="width: 28px; height: 28px;">
                <span class="fw-bold small text-success"><i class="bi bi-reply-fill me-1"></i>Antwort von Martin Zimmermann</span>
                ${isUnreadReply ? '<span class="badge bg-success text-white rounded-pill">Neue Antwort</span>' : ''}
              </div>
              <span class="text-muted" style="font-size: 0.75rem;">${this.escapeHtml(item.replied_at_formatted || '')}</span>
            </div>
            <div class="text-body small" style="white-space: pre-line; line-height: 1.55;">${this.escapeHtml(item.admin_reply)}</div>
            ${isUnreadReply ? `
              <div class="mt-2 pt-2 border-top border-success border-opacity-25 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-semibold ec-fb-mark-read-btn" data-id="${parseInt(item.id, 10)}" style="font-size: 0.78rem;">
                  <i class="bi bi-check2-circle me-1"></i>Gelesen &amp; verstanden – Danke!
                </button>
              </div>
            ` : ''}
          </div>
        `;
      }

      return `
        <div class="p-3 rounded-3 border shadow-sm ec-fb-history-item" data-item-id="${parseInt(item.id, 10)}" style="background-color: var(--kkc-surface); border-color: ${isUnreadReply ? '#10B981' : 'var(--kkc-border)'} !important;">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <div class="d-flex align-items-center flex-wrap gap-2">
              ${catBadge}
              ${statusBadge}
            </div>
            <span class="text-muted" style="font-size: 0.76rem;"><i class="bi bi-clock me-1"></i>Gesendet am ${this.escapeHtml(item.created_at_formatted || '')}</span>
          </div>
          <div class="text-body small" style="white-space: pre-line; line-height: 1.5;">${this.escapeHtml(item.message || '')}</div>
          ${attachmentsHtml}
          ${replyBoxHtml}
        </div>
      `;
    };

    const updateReadAccordionState = () => {
      const readList = document.getElementById('ec-fb-read-accordion-list');
      const readWrap = document.getElementById('ec-creator-feedback-read-accordion-wrap');
      const activeList = document.getElementById('ec-creator-feedback-history-list');
      const countBadge = document.getElementById('ec-fb-read-count-badge');
      const titleEl = document.getElementById('ec-fb-read-accordion-title');

      if (!readList || !readWrap || !activeList) return;
      const readCount = readList.querySelectorAll('.ec-fb-history-item').length;
      const activeCount = activeList.querySelectorAll('.ec-fb-history-item').length;

      if (countBadge) countBadge.textContent = String(readCount);
      if (titleEl) {
        titleEl.textContent = readCount === 1
          ? 'Bereits gelesene Rückmeldung'
          : 'Bereits gelesene Rückmeldungen';
      }

      if (readCount > 0) {
        readWrap.classList.remove('d-none');
        readWrap.classList.toggle('mt-3', activeCount > 0);
      } else {
        readWrap.classList.add('d-none');
        readWrap.classList.remove('mt-3');
      }
    };

    // Akkordeon Auf-/Zuklappen
    const readToggle = document.getElementById('ec-fb-read-accordion-toggle');
    if (readToggle) {
      readToggle.addEventListener('click', () => {
        const bodyEl = document.getElementById('ec-fb-read-accordion-body');
        const labelElToggle = document.getElementById('ec-fb-read-toggle-label');
        if (!bodyEl) return;
        const isOpen = (readToggle.getAttribute('aria-expanded') === 'true');
        if (isOpen) {
          readToggle.setAttribute('aria-expanded', 'false');
          if (labelElToggle) labelElToggle.textContent = 'Bei Bedarf aufklappen';
          bodyEl.style.display = 'none';
        } else {
          readToggle.setAttribute('aria-expanded', 'true');
          if (labelElToggle) labelElToggle.textContent = 'Wieder zuklappen';
          bodyEl.style.display = 'block';
        }
      });
    }

    const loadCreatorFeedbackHistory = async () => {
      const historyWrap = document.getElementById('ec-creator-feedback-history-wrap');
      const historyList = document.getElementById('ec-creator-feedback-history-list');
      const readList = document.getElementById('ec-fb-read-accordion-list');
      const unreadBadge = document.getElementById('ec-fb-history-unread-badge');
      const topAlertContainer = document.getElementById('ec-feedback-top-alert-container');

      if (!historyWrap || !historyList || !readList) return;

      try {
        const formData = new FormData();
        formData.append('csrf_token', this.csrfToken);

        const res = await fetch(`${this.basePath}/api.php?route=home/feedback-history`, {
          method: 'POST',
          body: formData
        });
        const resp = await res.json();
        if (!resp || !resp.success || !Array.isArray(resp.items)) return;

        if (resp.items.length === 0) {
          historyWrap.classList.add('d-none');
          if (topAlertContainer) topAlertContainer.innerHTML = '';
          return;
        }

        const unreadCount = parseInt(resp.unread_count ?? resp.unread_replies ?? 0, 10);
        if (unreadBadge) {
          if (unreadCount > 0) {
            unreadBadge.textContent = unreadCount === 1 ? '1 neue Antwort' : `${unreadCount} neue Antworten`;
            unreadBadge.classList.remove('d-none');
          } else {
            unreadBadge.classList.add('d-none');
          }
        }

        // Top-Benachrichtigungs-Banner ganz oben auf der Startseite anzeigen, wenn ungelesene Antwort vorliegt
        if (topAlertContainer) {
          if (unreadCount > 0) {
            topAlertContainer.innerHTML = `
              <div class="alert border-0 shadow-sm rounded-4 mb-4 p-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-3" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.14) 0%, rgba(2, 132, 199, 0.12) 100%); border-left: 4px solid #10B981 !important;">
                <div class="d-flex align-items-center gap-3">
                  <div class="position-relative flex-shrink-0">
                    <img src="assets/img/ceo-150x150.jpg" onerror="this.onerror=null;this.src='https://erocms.net/images/ceo-150x150.jpg';" alt="Martin Zimmermann" class="rounded-circle border border-2 border-white shadow-sm object-fit-cover" style="width: 46px; height: 46px;">
                    <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width: 12px; height: 12px;"></span>
                  </div>
                  <div>
                    <div class="fw-bold text-body">
                      <span class="badge bg-success me-1">Neu</span>
                      Du hast ${unreadCount === 1 ? 'eine persönliche Antwort' : `${unreadCount} persönliche Antworten`} von Martin Zimmermann erhalten!
                    </div>
                    <div class="text-muted small">Zu deiner Meldung aus dem EroCloud-Testlauf liegt eine Rückmeldung vor.</div>
                  </div>
                </div>
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-2 fw-semibold shadow-sm" id="ec-jump-to-feedback-reply-btn">
                  <i class="bi bi-chat-dots-fill me-1"></i>Antwort jetzt lesen
                </button>
              </div>
            `;

            const jumpBtn = document.getElementById('ec-jump-to-feedback-reply-btn');
            if (jumpBtn) {
              jumpBtn.addEventListener('click', () => {
                historyWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
              });
            }
          } else {
            topAlertContainer.innerHTML = '';
          }
        }

        const activeHtmlParts = [];
        const readHtmlParts = [];

        resp.items.forEach(item => {
          const hasReply = Boolean(item.has_reply || (item.admin_reply && String(item.admin_reply).trim() !== ''));
          const isUnreadReply = hasReply && (typeof item.is_unread_reply !== 'undefined' ? Boolean(item.is_unread_reply) : !item.reply_read_at);
          const cardHtml = renderFeedbackItemCard(item, isUnreadReply);

          if (hasReply && !isUnreadReply) {
            readHtmlParts.push(cardHtml);
          } else {
            activeHtmlParts.push(cardHtml);
          }
        });

        historyList.innerHTML = activeHtmlParts.join('');
        readList.innerHTML = readHtmlParts.join('');
        updateReadAccordionState();
        historyWrap.classList.remove('d-none');

        // Klick-Handler für „Gelesen & verstanden – Danke!"
        historyList.querySelectorAll('.ec-fb-mark-read-btn').forEach(btn => {
          btn.addEventListener('click', async () => {
            const fbId = parseInt(btn.getAttribute('data-id') || '0', 10);
            if (!fbId) return;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Wird bestätigt...';

            try {
              const fd = new FormData();
              fd.append('feedback_id', String(fbId));
              fd.append('csrf_token', this.csrfToken);

              const r = await fetch(`${this.basePath}/api.php?route=home/feedback-mark-read`, {
                method: 'POST',
                body: fd
              });
              const resMark = await r.json();
              if (resMark && resMark.success) {
                const itemCard = btn.closest('.ec-fb-history-item');
                if (itemCard) {
                  itemCard.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                  itemCard.style.opacity = '0';
                  itemCard.style.transform = 'scale(0.98)';
                  setTimeout(() => {
                    loadCreatorFeedbackHistory().then(() => {
                      const toggleEl = document.getElementById('ec-fb-read-accordion-toggle');
                      if (toggleEl) {
                        toggleEl.classList.remove('ec-fb-read-pulse');
                        void toggleEl.offsetWidth;
                        toggleEl.classList.add('ec-fb-read-pulse');
                      }
                    });
                  }, 240);
                } else {
                  loadCreatorFeedbackHistory();
                }
              } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Gelesen & verstanden – Danke!';
              }
            } catch (_) {
              btn.disabled = false;
              btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Gelesen & verstanden – Danke!';
            }
          });
        });
      } catch (_) {
        // Silent fail on history fetch
      }
    };

    // Initial beim Laden der Startseite abrufen
    loadCreatorFeedbackHistory();

    // 4. Formular absenden
    formEl.addEventListener('submit', async (e) => {
      e.preventDefault();
      hideError();

      const checkedCat = formEl.querySelector('input[name="ec_feedback_category"]:checked');
      const category = checkedCat ? checkedCat.value : 'bug';
      const msg = (messageEl ? messageEl.value : '').trim();

      if (msg.length < 5) {
        showError('Bitte schreibe mir noch ein paar Worte (mindestens 5 Zeichen), damit ich weiß, worum es geht.');
        if (messageEl) messageEl.focus();
        return;
      }

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Wird direkt an Martin gesendet...';
      }

      try {
        const formData = new FormData();
        formData.append('category', category);
        formData.append('message', msg);
        formData.append('page_context', window.location.hash || '#/startseite');
        formData.append('csrf_token', this.csrfToken);

        selectedFiles.forEach(file => {
          formData.append('attachments[]', file, file.name);
        });

        const res = await fetch(`${this.basePath}/api.php?route=home/feedback-send`, {
          method: 'POST',
          body: formData
        });
        const resp = await res.json();

        if (res.ok && resp && resp.success) {
          formEl.classList.add('d-none');
          if ((resp.title || resp.creator_title) && successTitle) {
            successTitle.textContent = resp.title || resp.creator_title;
          }
          if (resp.message && successText) {
            successText.textContent = resp.message;
          }
          if (successBox) {
            successBox.classList.remove('d-none');
          }
          if (messageEl) {
            messageEl.value = '';
          }
          selectedFiles = [];
          renderSelectedFiles();
          loadCreatorFeedbackHistory();
        } else {
          showError((resp && resp.message) ? resp.message : 'Deine Nachricht konnte leider nicht gesendet werden.');
        }
      } catch (_) {
        showError('Der Feedback-Server ist momentan nicht erreichbar. Bitte versuche es gleich noch einmal.');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Nachricht absenden';
        }
      }
    });

    // 5. Weitere Nachricht senden (Reset-Button)
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        if (successBox) successBox.classList.add('d-none');
        formEl.classList.remove('d-none');
        if (messageEl) messageEl.focus();
      });
    }
  },

  // ========================================================================
  // 4. Toast Notification Helper
  // ========================================================================
  toast(message, type = 'info') {
    if (type === 'success' && typeof this.invalidateSearchCatalog === 'function') {
      this.invalidateSearchCatalog();
    }
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '1090';
      document.body.appendChild(container);
    }

    const iconMap = {
      success: 'bi-check-circle-fill text-success',
      danger: 'bi-exclamation-triangle-fill text-danger',
      warning: 'bi-exclamation-circle-fill text-warning',
      info: 'bi-info-circle-fill ec-text-primary',
    };
    const icon = iconMap[type] || iconMap.info;

    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center border shadow-lg';
    toastEl.style.backgroundColor = 'var(--kkc-surface)';
    toastEl.style.borderColor = 'var(--kkc-border-strong)';
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2">
          <i class="bi ${icon} fs-5"></i>
          <span>${this.escapeHtml(message)}</span>
        </div>
        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    `;

    container.appendChild(toastEl);
    const bsToast = new bootstrap.Toast(toastEl, { delay: 3000 });
    bsToast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
  },

  escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str ?? '');
    return div.innerHTML;
  }
};

document.addEventListener('DOMContentLoaded', () => App.init());
