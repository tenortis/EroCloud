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
      'user': 'users',
      'chat': 'all-chats'
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
  },

  // ========================================================================
  // 4. Dashboard: Gemeinsame Notizen & Persönliche Notizen (AJAX)
  // ========================================================================
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
