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
    this.initGlobalTopbarSearch();
    this.initSessionKeepAlive();
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
        this.toast('Ihr API-Key wurde in die Zwischenablage kopiert.', 'success');
      } catch (_) {
        input.select();
        document.execCommand('copy');
        this.toast('Ihr API-Key wurde in die Zwischenablage kopiert.', 'success');
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
  },

  // Globale Schnellsuche in der Topbar: Filtert live auf #/movies oder #/photo-albums bzw. wechselt bei Enter zu #/movies
  initGlobalTopbarSearch() {
    const globalInput = document.getElementById('global-search-input');
    if (!globalInput) return;

    globalInput.addEventListener('input', () => {
      const smartSearch = document.getElementById('smart-search-input');
      if (smartSearch) {
        smartSearch.value = globalInput.value;
        smartSearch.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });

    globalInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const smartSearch = document.getElementById('smart-search-input');
        if (!smartSearch && globalInput.value.trim() !== '') {
          this.pendingGlobalSearch = globalInput.value.trim();
          window.location.hash = '#/movies';
        }
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
        this.toast('Bitte setzen Sie zuerst das Häkchen zur Bestätigung der Nutzungsrichtlinien.', 'warning');
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
        feedbackEl.textContent = 'Bitte geben Sie mindestens 3 Zeichen ein.';
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

        if (!window.confirm(`Möchten Sie den Film „${movieTitle}" wirklich unwiderruflich löschen?`)) {
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

    // 3. Live-Berechnung Streaming-Coins & Download-Switch
    const amountSecSelect = document.getElementById('movie-amount-second');
    const calcCoinsSpan = document.getElementById('movie-calc-coins');
    const editForm = document.getElementById('kkc-movie-edit-form');
    if (amountSecSelect && calcCoinsSpan && editForm) {
      const playtimeSec = parseFloat(editForm.getAttribute('data-playtime-seconds') || '0');
      amountSecSelect.addEventListener('change', () => {
        const rate = parseFloat(amountSecSelect.value || '0.8');
        calcCoinsSpan.textContent = String(Math.round(playtimeSec * rate));
      });
    }

    const asDownloadSwitch = document.getElementById('movie-as-download');
    const downloadWrap = document.getElementById('movie-download-price-wrap');
    const downloadSelect = document.getElementById('movie-amount-download');
    if (asDownloadSwitch && downloadWrap && downloadSelect) {
      asDownloadSwitch.addEventListener('change', () => {
        const enabled = asDownloadSwitch.checked;
        downloadSelect.disabled = !enabled;
        downloadWrap.style.opacity = enabled ? '1' : '0.5';
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

    // 5. Automatisch generiertes Vorschaubild (1..10) für FSK16 / FSK18 auswählen
    document.querySelectorAll('.js-select-movie-thumb').forEach(btn => {
      btn.addEventListener('click', () => {
        const fsk = btn.getAttribute('data-fsk');
        const num = btn.getAttribute('data-num');
        const url = btn.getAttribute('data-url');
        if (!fsk || !num) return;

        const hiddenInput = document.getElementById(`input-preview-fsk${fsk}`);
        const previewImg = document.getElementById(`current-fsk${fsk}-preview`);
        const badgeNum = document.getElementById(`badge-fsk${fsk}-num`);

        if (hiddenInput) hiddenInput.value = num;
        if (previewImg && url) previewImg.src = url;
        if (badgeNum) badgeNum.textContent = `Bild #${num}`;

        document.querySelectorAll(`.js-select-movie-thumb[data-fsk="${fsk}"]`).forEach(b => {
          b.classList.remove(fsk === '16' ? 'btn-success' : 'btn-danger');
          b.classList.add('btn-outline-secondary');
        });
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add(fsk === '16' ? 'btn-success' : 'btn-danger');
        this.toast(`Vorschaubild #${num} für FSK${fsk} ausgewählt. Bitte speichern Sie Ihre Änderungen.`, 'info');
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
            const hiddenInput = document.getElementById(`input-preview-fsk${fsk}`);
            const previewImg = document.getElementById(`current-fsk${fsk}-preview`);
            const badgeNum = document.getElementById(`badge-fsk${fsk}-num`);

            if (hiddenInput && data.thumb_number) hiddenInput.value = String(data.thumb_number);
            if (previewImg && data.preview_url) previewImg.src = data.preview_url;
            if (badgeNum && data.thumb_number) badgeNum.textContent = `Eigenes Bild (#${data.thumb_number})`;
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

    // 7. Film-Bearbeitungsformular speichern (#/movie?id=...)
    if (editForm) {
      editForm.addEventListener('submit', async (e) => {
        e.preventDefault();
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
            this.toast(data.message || 'Bitte überprüfen Sie Ihre Eingaben.', 'danger');
            if (submitBtn) submitBtn.disabled = false;
          }
        } catch (err) {
          this.toast('Verbindungsfehler: ' + err.message, 'danger');
          if (submitBtn) submitBtn.disabled = false;
        }
      });
    }

    // 9. Film-Upload Schritt 2: Videodatei mit Live-Fortschrittsbalken hochladen
    const btnStartUpload = document.getElementById('btn-start-movie-file-upload');
    const btnCancelUpload = document.getElementById('btn-cancel-movie-file-upload');
    const videoFileInput = document.getElementById('movie-video-file-input');

    if (btnStartUpload && videoFileInput) {
      btnStartUpload.addEventListener('click', () => {
        const file = videoFileInput.files?.[0];
        if (!file) {
          this.toast('Bitte wählen Sie zuerst eine Videodatei auf Ihrem Gerät aus.', 'warning');
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
        btnStartUpload.disabled = true;
        videoFileInput.disabled = true;
        if (btnCancelUpload) btnCancelUpload.classList.remove('d-none');

        const formData = new FormData();
        formData.append('movie_id', movieId);
        formData.append('video', file);
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
              if (data.redirect) {
                window.location.hash = data.redirect;
              }
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

        if (!window.confirm(`Möchten Sie das Foto-Album „${albumTitle}" wirklich löschen?`)) {
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
            this.toast(data.message || 'Bitte prüfen Sie Ihre Eingaben.', 'danger');
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

    // 5. FSK16 / FSK18 Cover für Foto-Album hochladen
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
            const imgEl = document.getElementById(`album-cover-fsk${fsk}-img`);
            if (imgEl && data.preview_url) {
              imgEl.src = data.preview_url;
            }
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

        if (!window.confirm('Möchten Sie dieses Foto wirklich aus dem Album entfernen?')) {
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
            this.toast(data.message || 'Bitte vervollständigen Sie zuerst alle Bilder.', 'warning');
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
  // 4. Toast Notification Helper
  // ========================================================================
  toast(message, type = 'info') {
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
