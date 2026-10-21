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

    // Falls eine Unterseite von Profile aufgerufen wird (z.B. #/actors, #/new-actor, #/actor), Submenü öffnen
    if (!activeCollapseId && ['actors', 'new-actor', 'actor'].includes(pathPart)) {
      activeCollapseId = 'submenu-actors';
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
