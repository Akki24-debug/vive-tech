document.addEventListener('DOMContentLoaded', function () {
  const SIDEBAR_SECTION_STATE_KEY = 'pms_sidebar_sections_v1';
  const sidebar = document.querySelector('.app-sidebar');
  const backdrop = document.querySelector('[data-sidebar-backdrop]');
  const navToggle = document.querySelector('.nav-toggle');
  const sidebarClose = document.querySelector('.sidebar-close');
  const sidebarSections = document.querySelectorAll('.sidebar-section');

  function setSidebarOpen(open) {
    if (!sidebar || !backdrop || !navToggle) {
      return;
    }
    sidebar.classList.toggle('is-open', open);
    backdrop.classList.toggle('is-open', open);
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
  }

  if (navToggle) {
    navToggle.addEventListener('click', function () {
      const isOpen = sidebar && sidebar.classList.contains('is-open');
      setSidebarOpen(!isOpen);
    });
  }

  if (sidebarClose) {
    sidebarClose.addEventListener('click', function () {
      setSidebarOpen(false);
    });
  }

  if (backdrop) {
    backdrop.addEventListener('click', function () {
      setSidebarOpen(false);
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      setSidebarOpen(false);
    }
  });

  function readSidebarSectionState() {
    try {
      const raw = window.localStorage.getItem(SIDEBAR_SECTION_STATE_KEY);
      if (!raw) {
        return {};
      }
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
      return {};
    }
  }

  function writeSidebarSectionState(state) {
    try {
      window.localStorage.setItem(SIDEBAR_SECTION_STATE_KEY, JSON.stringify(state || {}));
    } catch (error) {
      // Ignore storage failures and fall back to in-memory behavior.
    }
  }

  function getSidebarSectionKey(section, index) {
    if (!section) {
      return 'section-' + index;
    }
    const sectionKey = section.dataset && section.dataset.sectionKey ? String(section.dataset.sectionKey).trim() : '';
    return sectionKey || ('section-' + index);
  }

  if (sidebarSections.length) {
    const sidebarSectionState = readSidebarSectionState();
    sidebarSections.forEach(function (section, index) {
      const sectionKey = getSidebarSectionKey(section, index);
      const toggle = section.querySelector('.sidebar-section-toggle');
      const savedState = Object.prototype.hasOwnProperty.call(sidebarSectionState, sectionKey)
        ? sidebarSectionState[sectionKey]
        : null;
      const shouldOpen = savedState === null
        ? String(section.dataset.defaultOpen || '') === '1'
        : !!savedState;
      section.classList.toggle('is-open', shouldOpen);
      if (toggle) {
        toggle.addEventListener('click', function () {
          const isOpen = !section.classList.contains('is-open');
          section.classList.toggle('is-open', isOpen);
          sidebarSectionState[sectionKey] = isOpen;
          writeSidebarSectionState(sidebarSectionState);
        });
      }
    });
  }

  const containers = Array.prototype.slice.call(document.querySelectorAll('.subtabs'));

  function setActiveTab(container, targetKey) {
    if (!targetKey) {
      return;
    }
    const triggers = container.querySelectorAll('.subtab-trigger');
    const panels = container.querySelectorAll('.subtab-panel');
    Array.prototype.forEach.call(triggers, function (trigger) {
      trigger.classList.toggle('is-active', trigger.dataset.target === targetKey);
    });
    Array.prototype.forEach.call(panels, function (panel) {
      panel.classList.toggle('is-active', panel.dataset.tabKey === targetKey);
    });
    container.dataset.active = targetKey;
  }

  function updateDirtyState(container) {
    const panels = container.querySelectorAll('.subtab-panel');
    Array.prototype.forEach.call(panels, function (panel) {
      const key = panel.dataset.tabKey;
      if (!key) {
        return;
      }
      const hasDirty = panel.classList.contains('is-dirty');
      const triggers = container.querySelectorAll('.subtab-trigger[data-target="' + key + '"]');
      Array.prototype.forEach.call(triggers, function (trigger) {
        trigger.classList.toggle('is-dirty', hasDirty);
      });
    });
  }

  containers.forEach(function (container) {
    const activeKey = container.dataset.active;
    if (activeKey) {
      setActiveTab(container, activeKey);
    }

    const triggers = container.querySelectorAll('.subtab-trigger');
    Array.prototype.forEach.call(triggers, function (trigger) {
      trigger.addEventListener('click', function () {
        const target = trigger.dataset.target;
        if (target) {
          setActiveTab(container, target);
        }
      });
    });

    const closeButtons = container.querySelectorAll('.subtab-close');
    Array.prototype.forEach.call(closeButtons, function (btn) {
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        const formId = btn.getAttribute('data-close-form');
        if (!formId) {
          return;
        }
        const form = document.getElementById(formId);
        if (!form) {
          return;
        }
        const currentInput = form.querySelector('.js-subtab-current');
        if (currentInput) {
          const activePanel = container.querySelector('.subtab-panel.is-active');
          currentInput.value = activePanel ? activePanel.dataset.tabKey : container.dataset.active || 'static:general';
        }
        const dirtyInput = form.querySelector('.js-subtab-dirty');
        if (dirtyInput) {
          const dirtyKeys = [];
          container.querySelectorAll('.subtab-panel.is-dirty').forEach(function (panel) {
            dirtyKeys.push(panel.dataset.tabKey);
          });
          dirtyInput.value = dirtyKeys.join(',');
        }
        form.submit();
      });
    });
  });

  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      const container = form.closest('.subtabs');
      if (!container) {
        return;
      }
      const activePanel = container.querySelector('.subtab-panel.is-active');
      const currentInput = form.querySelector('.js-subtab-current');
      if (currentInput) {
        currentInput.value = activePanel ? activePanel.dataset.tabKey : container.dataset.active || 'static:general';
      }
      const dirtyInput = form.querySelector('.js-subtab-dirty');
      if (dirtyInput) {
        const dirtyKeys = [];
        container.querySelectorAll('.subtab-panel.is-dirty').forEach(function (panel) {
          dirtyKeys.push(panel.dataset.tabKey);
        });
        dirtyInput.value = dirtyKeys.join(',');
      }
    });
  });

  function toggleEditing(panel, enable) {
    if (!panel) {
      return;
    }
    const isEnabling = enable === undefined ? !panel.classList.contains('is-editing') : enable;
    panel.classList.toggle('is-editing', isEnabling);
    panel.querySelectorAll('[data-editable-field]').forEach(function (field) {
      if (field.dataset.locked === '1') {
        return;
      }
      if (isEnabling) {
        field.removeAttribute('disabled');
      } else {
        field.setAttribute('disabled', 'disabled');
      }
    });
    const toggleBtn = panel.querySelector('.js-toggle-edit');
    if (toggleBtn) {
      const viewLabel = toggleBtn.getAttribute('data-label-view') || 'Ver';
      const editLabel = toggleBtn.getAttribute('data-label-edit') || 'Editar';
      toggleBtn.textContent = isEnabling ? viewLabel : editLabel;
    }
  }

  document.querySelectorAll('.js-toggle-edit').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      const panel = button.closest('.subtab-panel');
      toggleEditing(panel);
    });
  });

  function markDirty(element) {
    const panel = element.closest('.subtab-panel');
    if (!panel || !panel.classList.contains('is-editing')) {
      return;
    }
    panel.classList.add('is-dirty');
    const container = panel.closest('.subtabs');
    if (container) {
      updateDirtyState(container);
    }
  }

  document.addEventListener('input', function (event) {
    const target = event.target;
    if (target.matches('[data-editable-field]')) {
      markDirty(target);
    }
  });

  document.addEventListener('change', function (event) {
    const target = event.target;
    if (target.matches('[data-editable-field]')) {
      markDirty(target);
    }
  });

  function isDateLikeInput(input) {
    if (!(input instanceof HTMLInputElement)) {
      return false;
    }
    const type = (input.getAttribute('type') || '').toLowerCase();
    return type === 'date'
      || type === 'datetime-local'
      || type === 'time'
      || type === 'month'
      || type === 'week';
  }

  // Make the whole field clickable to open native picker (not only the icon area).
  document.addEventListener('pointerdown', function (event) {
    const target = event.target;
    if (!isDateLikeInput(target) || target.disabled || target.readOnly) {
      return;
    }
    if (typeof target.showPicker === 'function') {
      try {
        target.showPicker();
      } catch (e) {
      }
    }
  });

  if (window.pmsRoomMap) {
    document.querySelectorAll('[data-room-filter]').forEach(function (form) {
      const propertySelect = form.querySelector('[data-room-filter-prop]');
      const roomSelect = form.querySelector('[data-room-filter-room]');
      if (!propertySelect || !roomSelect) {
        return;
      }
      function rebuildRooms() {
        const map = window.pmsRoomMap || {};
        const selectedProperty = (propertySelect.value || '').toUpperCase();
        const rooms = map[selectedProperty] || [];
        const currentValue = roomSelect.getAttribute('data-current-value') || roomSelect.value;
        roomSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Selecciona una habitacion';
        roomSelect.appendChild(placeholder);
        rooms.forEach(function (room) {
          const option = document.createElement('option');
          option.value = room.code;
          option.textContent = room.label;
          if (room.code.toUpperCase() === (currentValue || '').toUpperCase()) {
            option.selected = true;
          }
          roomSelect.appendChild(option);
        });
        if (!roomSelect.value && rooms.length) {
          roomSelect.selectedIndex = 1;
        }
        roomSelect.setAttribute('data-current-value', roomSelect.value);
      }
      propertySelect.addEventListener('change', function () {
        roomSelect.setAttribute('data-current-value', '');
        rebuildRooms();
      });
      rebuildRooms();
    });
  }
});

// Calendar cell selection (toggle)
document.addEventListener('DOMContentLoaded', function () {
  const calendarTables = Array.prototype.slice.call(document.querySelectorAll('.calendar-table'));
  // Expose shared service lightbox for non-calendar views (e.g. reservations)
  // before exiting this calendar initializer early.
  window.pmsOpenServiceLightbox = openServiceLightbox;
  window.pmsOpenPaymentLightbox = openPaymentLightbox;
  if (!calendarTables.length) {
    return;
  }
  const mobileCalendarQuery = window.matchMedia('(max-width: 900px)');
  const isMobileCalendar = function () {
    return !!mobileCalendarQuery.matches;
  };
  const viewportMetaTag = document.querySelector('meta[name="viewport"]');
  const MOBILE_CALENDAR_ZOOM_KEY = 'pms_calendar_mobile_zoom';
  const MOBILE_CALENDAR_ZOOM_MIN = 0.55;
  const MOBILE_CALENDAR_ZOOM_MAX = 1.35;
  const MOBILE_CALENDAR_ZOOM_STEP = 0.1;
  const CALENDAR_DAY_WIDTH_SCALE_KEY = 'pms_calendar_day_width_scale';
  const CALENDAR_DAY_WIDTH_SCALE_MIN = 0.5;
  const CALENDAR_DAY_WIDTH_SCALE_MAX = 1.5;
  const CALENDAR_ROW_HEIGHT_SCALE_KEY = 'pms_calendar_row_height_scale';
  const CALENDAR_ROW_HEIGHT_SCALE_MIN = 0.5;
  const CALENDAR_ROW_HEIGHT_SCALE_MAX = 1.5;
  const CALENDAR_RETURN_STATE_KEY = 'pms_calendar_return_state_v2';
  const CALENDAR_RETURN_STATE_TTL_MS = 45 * 60 * 1000;
  const CALENDAR_SKIP_CONTEXT_RESTORE_KEY = 'pms_calendar_skip_context_restore_v1';
  const CALENDAR_SKIP_CONTEXT_RESTORE_TTL_MS = 5 * 60 * 1000;

  function clampMobileCalendarZoom(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) {
      return 1;
    }
    return Math.max(MOBILE_CALENDAR_ZOOM_MIN, Math.min(MOBILE_CALENDAR_ZOOM_MAX, numeric));
  }

  function readMobileCalendarZoom() {
    try {
      const raw = window.localStorage.getItem(MOBILE_CALENDAR_ZOOM_KEY);
      if (raw === null || raw === '') {
        return 1;
      }
      return clampMobileCalendarZoom(parseFloat(raw));
    } catch (error) {
      return 1;
    }
  }

  let mobileCalendarZoom = readMobileCalendarZoom();

  function clampCalendarDayWidthScale(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) {
      return 1;
    }
    return Math.max(CALENDAR_DAY_WIDTH_SCALE_MIN, Math.min(CALENDAR_DAY_WIDTH_SCALE_MAX, numeric));
  }

  function readCalendarDayWidthScale() {
    try {
      const raw = window.localStorage.getItem(CALENDAR_DAY_WIDTH_SCALE_KEY);
      if (raw === null || raw === '') {
        return 1;
      }
      return clampCalendarDayWidthScale(parseFloat(raw));
    } catch (error) {
      return 1;
    }
  }

  let calendarDayWidthScale = readCalendarDayWidthScale();

  function clampCalendarRowHeightScale(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) {
      return 1;
    }
    return Math.max(CALENDAR_ROW_HEIGHT_SCALE_MIN, Math.min(CALENDAR_ROW_HEIGHT_SCALE_MAX, numeric));
  }

  function readCalendarRowHeightScale() {
    try {
      const raw = window.localStorage.getItem(CALENDAR_ROW_HEIGHT_SCALE_KEY);
      if (raw === null || raw === '') {
        return 1;
      }
      return clampCalendarRowHeightScale(parseFloat(raw));
    } catch (error) {
      return 1;
    }
  }

  let calendarRowHeightScale = readCalendarRowHeightScale();

  function syncCalendarViewportZoom() {
    if (!viewportMetaTag) return;
    if (isMobileCalendar()) {
      viewportMetaTag.setAttribute(
        'content',
        'width=device-width,initial-scale=1,minimum-scale=0.25,maximum-scale=5,user-scalable=yes,viewport-fit=cover'
      );
      return;
    }
    viewportMetaTag.setAttribute(
      'content',
      'width=device-width,initial-scale=1,minimum-scale=0.25,maximum-scale=5,user-scalable=yes,viewport-fit=cover'
    );
  }

  function getCalendarFabIconSvg(actionKey) {
    const icons = {
      block: '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8.5"></circle><path d="M7 17 17 7"></path></svg>',
      create: '<svg viewBox="0 0 24 24" focusable="false"><rect x="3.5" y="5.5" width="17" height="15" rx="2"></rect><path d="M8 3.5v4M16 3.5v4M3.5 10.5h17M12 13v5M9.5 15.5h5"></path></svg>',
      quick: '<svg viewBox="0 0 24 24" focusable="false"><path d="M13.2 2.5 5.8 13h5.1L9.8 21.5 18.2 10h-5z"></path></svg>',
      view: '<svg viewBox="0 0 24 24" focusable="false"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"></path><circle cx="12" cy="12" r="2.8"></circle></svg>',
      pay: '<svg viewBox="0 0 24 24" focusable="false"><rect x="2.5" y="6" width="19" height="12" rx="2"></rect><path d="M2.5 10h19M6.5 14.5h3.5"></path></svg>',
      charges: '<svg viewBox="0 0 24 24" focusable="false"><path d="M7 3.5h10l2 2V20.5H5V5.5l2-2z"></path><path d="M8 9.5h8M8 13h8M8 16.5h5M16.5 14.5v4M14.5 16.5h4"></path></svg>',
      move: '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3.5 8.5 7h2.5v4H7V8.5L3.5 12 7 15.5V13h4v4H8.5l3.5 3.5 3.5-3.5H13v-4h4v2.5l3.5-3.5L17 8.5V11h-4V7h2.5L12 3.5z"></path></svg>',
      advance: '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17z"></path><path d="M10 8.5 14.5 12 10 15.5M14.5 12H7.5"></path></svg>',
      noshow: '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8.5"></circle><path d="M8.5 8.5 15.5 15.5M15.5 8.5l-7 7"></path></svg>',
      cancel: '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8.5"></circle><path d="M9 9l6 6M15 9l-6 6"></path></svg>',
      group: '<svg viewBox="0 0 24 24" focusable="false"><circle cx="9" cy="9.2" r="2.8"></circle><circle cx="16.3" cy="10.3" r="2.2"></circle><path d="M4.8 18.5a4.8 4.8 0 0 1 8.4 0M13.2 18.5a3.7 3.7 0 0 1 6.1 0"></path></svg>',
      delete: '<svg viewBox="0 0 24 24" focusable="false"><path d="M4.5 6.5h15M9 6.5V4.7h6v1.8M7 6.5l.8 12h8.4l.8-12M10 10v6M14 10v6"></path></svg>'
    };
    return icons[actionKey] || '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="3"></circle></svg>';
  }

  let calendarScrollers = [];
  let mobileZoomControls = null;
  let mobileZoomValueLabel = null;
  let mobileZoomOutButton = null;
  let mobileZoomResetButton = null;
  let mobileZoomInButton = null;
  const desktopDayWidthSlider = document.querySelector('.js-calendar-daywidth-slider');
  const desktopDayWidthValue = document.querySelector('.js-calendar-daywidth-value');
  const desktopRowHeightSlider = document.querySelector('.js-calendar-rowheight-slider');
  const desktopRowHeightValue = document.querySelector('.js-calendar-rowheight-value');
  let calendarGlobalScrollbarRoot = null;
  let calendarGlobalScrollbarInner = null;
  let calendarGlobalScrollbarSpacer = null;
  let isCalendarGlobalScrollbarSyncing = false;
  let mobileDayWidthControls = null;
  let mobileDayWidthSlider = null;
  let mobileDayWidthValue = null;
  let mobileRowHeightControls = null;
  let mobileRowHeightSlider = null;
  let mobileRowHeightValue = null;

  function readCalendarControlValue(selector) {
    const field = document.querySelector(selector);
    if (!field) {
      return '';
    }
    return (field.value || '').toString().trim();
  }

  function currentCalendarReturnContext() {
    return {
      propertyCode: readCalendarControlValue('.calendar-filters select[name="property_code"]'),
      startDate: readCalendarControlValue('input[name="start_date"]'),
      viewMode: readCalendarControlValue('[name="view_mode"]'),
      orderMode: readCalendarControlValue('[name="order_mode"]'),
      currentSubtab: readCalendarControlValue('input[name="calendar_current_subtab"]'),
      dirtyTabs: readCalendarControlValue('input[name="calendar_dirty_tabs"]')
    };
  }

  function readCalendarContextFromForm(form) {
    if (!form) {
      return currentCalendarReturnContext();
    }
    const formData = new FormData(form);
    return {
      propertyCode: (formData.get('property_code') || '').toString().trim(),
      startDate: (formData.get('start_date') || '').toString().trim(),
      viewMode: (formData.get('view_mode') || '').toString().trim(),
      orderMode: (formData.get('order_mode') || '').toString().trim(),
      currentSubtab: (formData.get('calendar_current_subtab') || '').toString().trim(),
      dirtyTabs: (formData.get('calendar_dirty_tabs') || '').toString().trim()
    };
  }

  function normalizeCalendarReturnContext(context) {
    const source = context && typeof context === 'object' ? context : {};
    return {
      propertyCode: (source.propertyCode || '').toString().trim().toUpperCase(),
      startDate: (source.startDate || '').toString().trim(),
      viewMode: (source.viewMode || '').toString().trim(),
      orderMode: (source.orderMode || '').toString().trim(),
      currentSubtab: (source.currentSubtab || '').toString().trim(),
      dirtyTabs: (source.dirtyTabs || '').toString().trim()
    };
  }

  function calendarReturnContextsMatch(left, right) {
    const a = normalizeCalendarReturnContext(left);
    const b = normalizeCalendarReturnContext(right);
    return a.propertyCode === b.propertyCode
      && a.startDate === b.startDate
      && a.viewMode === b.viewMode
      && a.orderMode === b.orderMode;
  }

  function calendarHasExplicitSearchContext() {
    const searchParams = new URLSearchParams(window.location.search || '');
    return [
      'property_code',
      'start_date',
      'order_mode',
      'view_mode',
      'calendar_focus_reservation_id',
      'calendar_focus_property_code',
      'calendar_focus_room_code',
      'calendar_focus_check_in'
    ].some(function (key) {
      return searchParams.has(key);
    });
  }

  function calendarHasRestoreAttemptInUrl() {
    const searchParams = new URLSearchParams(window.location.search || '');
    return searchParams.get('calendar_restore') === '1';
  }

  function buildCalendarReturnState(contextOverride) {
    return {
      savedAt: Date.now(),
      context: normalizeCalendarReturnContext(contextOverride || currentCalendarReturnContext()),
      windowScrollY: Math.max(0, Math.round(window.scrollY || window.pageYOffset || 0)),
      scrollers: calendarScrollers.map(function (scroll, index) {
        const maxLeft = Math.max(0, scroll.scrollWidth - scroll.clientWidth);
        const leftRatio = maxLeft > 0 ? (scroll.scrollLeft / maxLeft) : 0;
        return {
          index: index,
          leftRatio: Math.max(0, Math.min(1, leftRatio))
        };
      })
    };
  }

  function appendWizardReturnContextInputs(addInput, context) {
    if (typeof addInput !== 'function') {
      return;
    }
    const payload = context || currentCalendarReturnContext();
    addInput('wizard_return_view', 'calendar');
    addInput('wizard_return_restore', '1');
    if (payload.propertyCode) addInput('wizard_return_property_code', payload.propertyCode);
    if (payload.startDate) addInput('wizard_return_start_date', payload.startDate);
    if (payload.viewMode) addInput('wizard_return_view_mode', payload.viewMode);
    if (payload.orderMode) addInput('wizard_return_order_mode', payload.orderMode);
    if (payload.currentSubtab) addInput('wizard_return_calendar_current_subtab', payload.currentSubtab);
    if (payload.dirtyTabs) addInput('wizard_return_calendar_dirty_tabs', payload.dirtyTabs);
  }

  function saveCalendarReturnState(contextOverride) {
    const state = buildCalendarReturnState(contextOverride);
    try {
      window.sessionStorage.setItem(CALENDAR_RETURN_STATE_KEY, JSON.stringify(state));
    } catch (error) {
      // Ignore storage restrictions.
    }
    return state.context;
  }

  function loadCalendarReturnState() {
    try {
      const raw = window.sessionStorage.getItem(CALENDAR_RETURN_STATE_KEY);
      if (!raw) {
        return null;
      }
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') {
        return null;
      }
      const savedAt = Number(parsed.savedAt || 0);
      if (!Number.isFinite(savedAt) || savedAt <= 0) {
        return null;
      }
      if ((Date.now() - savedAt) > CALENDAR_RETURN_STATE_TTL_MS) {
        return null;
      }
      return parsed;
    } catch (error) {
      return null;
    }
  }

  function markCalendarContextRestoreSkip() {
    try {
      window.sessionStorage.setItem(CALENDAR_SKIP_CONTEXT_RESTORE_KEY, String(Date.now()));
    } catch (error) {
      // Ignore storage restrictions.
    }
  }

  function consumeCalendarContextRestoreSkip() {
    try {
      const raw = window.sessionStorage.getItem(CALENDAR_SKIP_CONTEXT_RESTORE_KEY);
      if (!raw) {
        return false;
      }
      window.sessionStorage.removeItem(CALENDAR_SKIP_CONTEXT_RESTORE_KEY);
      const savedAt = Number(raw);
      if (!Number.isFinite(savedAt) || savedAt <= 0) {
        return false;
      }
      return (Date.now() - savedAt) <= CALENDAR_SKIP_CONTEXT_RESTORE_TTL_MS;
    } catch (error) {
      return false;
    }
  }

  let calendarReturnStateSaveTimer = 0;

  function scheduleCalendarReturnStateSave(delay) {
    if (calendarReturnStateSaveTimer) {
      window.clearTimeout(calendarReturnStateSaveTimer);
    }
    calendarReturnStateSaveTimer = window.setTimeout(function () {
      calendarReturnStateSaveTimer = 0;
      saveCalendarReturnState();
    }, Math.max(0, delay || 0));
  }

  function submitCalendarContextRestore(state) {
    const savedContext = state && state.context ? normalizeCalendarReturnContext(state.context) : null;
    if (!savedContext) {
      return false;
    }
    const form = document.createElement('form');
    const url = new URL(window.location.href);
    url.searchParams.set('view', 'calendar');
    url.searchParams.set('calendar_restore', '1');
    form.method = 'post';
    form.action = `${url.pathname}${url.search}`;
    function addInput(name, value) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    }
    addInput('property_code', savedContext.propertyCode || '');
    addInput('start_date', savedContext.startDate || '');
    addInput('order_mode', savedContext.orderMode || '');
    if (savedContext.viewMode) addInput('view_mode', savedContext.viewMode);
    if (savedContext.currentSubtab) addInput('calendar_current_subtab', savedContext.currentSubtab);
    if (savedContext.dirtyTabs || savedContext.dirtyTabs === '') addInput('calendar_dirty_tabs', savedContext.dirtyTabs);
    document.body.appendChild(form);
    form.submit();
    return true;
  }

  function maybeRestoreCalendarContext(state, focusContext) {
    if (!state || !state.context) {
      return false;
    }
    if (consumeCalendarContextRestoreSkip()) {
      return false;
    }
    if (calendarHasRestoreAttemptInUrl()) {
      return false;
    }
    if (calendarHasExplicitSearchContext() || focusContext) {
      return false;
    }
    const savedContext = normalizeCalendarReturnContext(state.context);
    const currentContext = currentCalendarReturnContext();
    if (calendarReturnContextsMatch(savedContext, currentContext)) {
      return false;
    }
    return submitCalendarContextRestore(state);
  }

  function restoreCalendarReturnState(state) {
    if (!state || typeof state !== 'object') {
      return;
    }
    const scrollerState = Array.isArray(state.scrollers) ? state.scrollers : [];
    if (!scrollerState.length && !Number.isFinite(Number(state.windowScrollY))) {
      return;
    }
    const applyRestore = function () {
      scrollerState.forEach(function (item, index) {
        const scroll = calendarScrollers[index];
        if (!scroll) {
          return;
        }
        const ratio = Number(item && item.leftRatio);
        const maxLeft = Math.max(0, scroll.scrollWidth - scroll.clientWidth);
        if (!Number.isFinite(ratio) || maxLeft <= 0) {
          return;
        }
        scroll.scrollLeft = Math.round(maxLeft * Math.max(0, Math.min(1, ratio)));
      });
      if (calendarScrollers.length) {
        syncCalendarScrollFrom(calendarScrollers[0]);
      }
      const targetScrollY = Number(state.windowScrollY);
      if (Number.isFinite(targetScrollY) && targetScrollY >= 0) {
        window.scrollTo(0, Math.round(targetScrollY));
      }
    };
    requestAnimationFrame(function () {
      applyRestore();
      window.setTimeout(applyRestore, 120);
    });
  }

  function escapeCalendarAttrValue(value) {
    return String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  function readCalendarFocusContext() {
    const searchParams = new URLSearchParams(window.location.search || '');
    const marker = document.getElementById('calendar-focus-context');
    const readMarkerValue = function (attributeName) {
      if (!marker) {
        return '';
      }
      return marker.getAttribute(attributeName) || '';
    };
    const reservationId = parseInt(
      searchParams.get('calendar_focus_reservation_id') || readMarkerValue('data-reservation-id') || '0',
      10
    );
    const propertyCode = (
      searchParams.get('calendar_focus_property_code') || readMarkerValue('data-property-code') || ''
    ).trim().toUpperCase();
    const roomCode = (
      searchParams.get('calendar_focus_room_code') || readMarkerValue('data-room-code') || ''
    ).trim().toUpperCase();
    const checkIn = (
      searchParams.get('calendar_focus_check_in') || readMarkerValue('data-check-in') || ''
    ).trim();
    if (!(reservationId > 0) && !roomCode && !checkIn) {
      return null;
    }
    return {
      reservationId: reservationId > 0 ? reservationId : 0,
      propertyCode,
      roomCode,
      checkIn
    };
  }

  function clearCalendarFocusQueryParams() {
    if (!window.history || typeof window.history.replaceState !== 'function') {
      return;
    }
    const url = new URL(window.location.href);
    let changed = false;
    [
      'calendar_restore',
      'calendar_focus_reservation_id',
      'calendar_focus_property_code',
      'calendar_focus_room_code',
      'calendar_focus_check_in'
    ].forEach(function (key) {
      if (url.searchParams.has(key)) {
        url.searchParams.delete(key);
        changed = true;
      }
    });
    if (!changed) {
      return;
    }
    const nextUrl = `${url.pathname}${url.search}${url.hash}`;
    window.history.replaceState({}, document.title, nextUrl);
  }

  function findCalendarFocusTarget(context) {
    if (!context || typeof context !== 'object') {
      return null;
    }
    if (context.reservationId > 0) {
      const directMatch = document.querySelector(
        `.js-calendar-reservation[data-reservation-id="${context.reservationId}"]`
      );
      if (directMatch) {
        return directMatch;
      }
    }
    let reservationSelector = '.js-calendar-reservation';
    if (context.roomCode) {
      reservationSelector += `[data-room-code="${escapeCalendarAttrValue(context.roomCode)}"]`;
    }
    if (context.propertyCode) {
      reservationSelector += `[data-property-code="${escapeCalendarAttrValue(context.propertyCode)}"]`;
    }
    if (context.checkIn) {
      reservationSelector += `[data-check-in="${escapeCalendarAttrValue(context.checkIn)}"]`;
    }
    if (reservationSelector !== '.js-calendar-reservation') {
      const reservationMatch = document.querySelector(reservationSelector);
      if (reservationMatch) {
        return reservationMatch;
      }
    }
    if (context.roomCode && context.checkIn) {
      let cellSelector = `.calendar-cell[data-room-code="${escapeCalendarAttrValue(context.roomCode)}"][data-date="${escapeCalendarAttrValue(context.checkIn)}"]`;
      if (context.propertyCode) {
        cellSelector += `[data-property-code="${escapeCalendarAttrValue(context.propertyCode)}"]`;
      }
      const cellMatch = document.querySelector(cellSelector);
      if (cellMatch) {
        return cellMatch;
      }
    }
    return null;
  }

  function focusCalendarTarget(cell) {
    if (!(cell instanceof HTMLElement)) {
      return;
    }
    if (typeof clearSelection === 'function') {
      clearSelection();
    }
    if (cell.classList.contains('js-calendar-reservation') && typeof toggleReservationSelection === 'function') {
      toggleReservationSelection(cell);
    } else {
      cell.classList.add('is-reservation-selected');
    }
    const scroller = cell.closest('.calendar-scroll');
    if (scroller) {
      const scrollerRect = scroller.getBoundingClientRect();
      const cellRect = cell.getBoundingClientRect();
      const targetLeft = scroller.scrollLeft + (cellRect.left - scrollerRect.left) - ((scroller.clientWidth - cellRect.width) / 2);
      scroller.scrollLeft = Math.max(0, Math.round(targetLeft));
      if (typeof syncCalendarScrollFrom === 'function') {
        syncCalendarScrollFrom(scroller);
      }
    }
    const topOffset = Math.max(96, Math.round(window.innerHeight * 0.18));
    const targetTop = Math.max(0, Math.round(window.scrollY + cell.getBoundingClientRect().top - topOffset));
    window.scrollTo(0, targetTop);
    if (typeof placeSelectionActionBar === 'function') {
      placeSelectionActionBar();
    }
  }

  function selectCalendarTargetWithoutScroll(cell) {
    if (!(cell instanceof HTMLElement)) {
      return;
    }
    if (typeof clearSelection === 'function') {
      clearSelection();
    }
    if (cell.classList.contains('js-calendar-reservation') && typeof toggleReservationSelection === 'function') {
      toggleReservationSelection(cell);
      return;
    }
    cell.classList.add('is-reservation-selected');
  }

  function isCalendarTargetVisible(cell) {
    if (!(cell instanceof HTMLElement)) {
      return false;
    }
    const rect = cell.getBoundingClientRect();
    const scroller = cell.closest('.calendar-scroll');
    const topPadding = Math.max(84, Math.round(window.innerHeight * 0.12));
    const bottomPadding = Math.max(32, Math.round(window.innerHeight * 0.08));
    const verticallyVisible = rect.top >= topPadding && rect.bottom <= (window.innerHeight - bottomPadding);
    if (!verticallyVisible) {
      return false;
    }
    if (!scroller) {
      return true;
    }
    const scrollerRect = scroller.getBoundingClientRect();
    return rect.left >= scrollerRect.left && rect.right <= scrollerRect.right;
  }

  function scheduleCalendarFocus(context) {
    if (!context) {
      return;
    }
    let attempt = 0;
    const maxAttempts = 8;
    const runAttempt = function () {
      const target = findCalendarFocusTarget(context);
      if (target) {
        if (isCalendarTargetVisible(target)) {
          selectCalendarTargetWithoutScroll(target);
          clearCalendarFocusQueryParams();
          return;
        }
        focusCalendarTarget(target);
        clearCalendarFocusQueryParams();
        return;
      }
      attempt += 1;
      if (attempt < maxAttempts) {
        window.setTimeout(runAttempt, 140);
        return;
      }
      clearCalendarFocusQueryParams();
    };
    window.setTimeout(runAttempt, 80);
  }

  function dayWidthScaleToPercent(scale) {
    return Math.round((scale - 1) * 100);
  }

  function dayWidthPercentToScale(percent) {
    const numeric = Number(percent);
    if (!Number.isFinite(numeric)) {
      return 1;
    }
    return 1 + (numeric / 100);
  }

  function formatDayWidthPercent(percent) {
    if (percent > 0) {
      return `+${percent}%`;
    }
    if (percent < 0) {
      return `${percent}%`;
    }
    return '0%';
  }

  function rowHeightScaleToPercent(scale) {
    return Math.round((scale - 1) * 100);
  }

  function rowHeightPercentToScale(percent) {
    const numeric = Number(percent);
    if (!Number.isFinite(numeric)) {
      return 1;
    }
    return 1 + (numeric / 100);
  }

  function formatRowHeightPercent(percent) {
    if (percent > 0) {
      return `+${percent}%`;
    }
    if (percent < 0) {
      return `${percent}%`;
    }
    return '0%';
  }

  function updateCalendarDayWidthUi() {
    const pct = dayWidthScaleToPercent(calendarDayWidthScale);
    if (desktopDayWidthSlider) {
      desktopDayWidthSlider.value = String(pct);
    }
    if (desktopDayWidthValue) {
      desktopDayWidthValue.textContent = formatDayWidthPercent(pct);
    }
    if (mobileDayWidthControls) {
      mobileDayWidthControls.hidden = !isMobileCalendar();
    }
    if (mobileDayWidthSlider) {
      mobileDayWidthSlider.value = String(pct);
    }
    if (mobileDayWidthValue) {
      mobileDayWidthValue.textContent = formatDayWidthPercent(pct);
    }
  }

  function updateCalendarRowHeightUi() {
    const pct = rowHeightScaleToPercent(calendarRowHeightScale);
    if (desktopRowHeightSlider) {
      desktopRowHeightSlider.value = String(pct);
    }
    if (desktopRowHeightValue) {
      desktopRowHeightValue.textContent = formatRowHeightPercent(pct);
    }
    if (mobileRowHeightControls) {
      mobileRowHeightControls.hidden = !isMobileCalendar();
    }
    if (mobileRowHeightSlider) {
      mobileRowHeightSlider.value = String(pct);
    }
    if (mobileRowHeightValue) {
      mobileRowHeightValue.textContent = formatRowHeightPercent(pct);
    }
  }

  function setCalendarDayWidthScale(nextScale, options) {
    const opts = options || {};
    const clamped = clampCalendarDayWidthScale(nextScale);
    const unchanged = Math.abs(clamped - calendarDayWidthScale) < 0.001;
    if (unchanged && !opts.force) {
      updateCalendarDayWidthUi();
      return;
    }
    const scrollState = captureCalendarScrollState();
    calendarDayWidthScale = clamped;
    try {
      window.localStorage.setItem(CALENDAR_DAY_WIDTH_SCALE_KEY, calendarDayWidthScale.toFixed(2));
    } catch (error) {
      // Ignore storage restrictions.
    }
    syncCalendarTableWidths();
    restoreCalendarScrollState(scrollState);
    normalizeCalendarScrollerRanges();
    updateCalendarDayWidthUi();
  }

  function setCalendarRowHeightScale(nextScale, options) {
    const opts = options || {};
    const clamped = clampCalendarRowHeightScale(nextScale);
    const unchanged = Math.abs(clamped - calendarRowHeightScale) < 0.001;
    if (unchanged && !opts.force) {
      updateCalendarRowHeightUi();
      return;
    }
    const scrollState = captureCalendarScrollState();
    calendarRowHeightScale = clamped;
    try {
      window.localStorage.setItem(CALENDAR_ROW_HEIGHT_SCALE_KEY, calendarRowHeightScale.toFixed(2));
    } catch (error) {
      // Ignore storage restrictions.
    }
    syncCalendarTableWidths();
    restoreCalendarScrollState(scrollState);
    updateCalendarRowHeightUi();
  }

  function bindDayWidthSlider(sliderElement) {
    if (!sliderElement || sliderElement.dataset.bound === '1') {
      return;
    }
    sliderElement.dataset.bound = '1';
    sliderElement.addEventListener('input', function () {
      const nextScale = dayWidthPercentToScale(sliderElement.value);
      setCalendarDayWidthScale(nextScale);
    });
  }

  function bindRowHeightSlider(sliderElement) {
    if (!sliderElement || sliderElement.dataset.bound === '1') {
      return;
    }
    sliderElement.dataset.bound = '1';
    sliderElement.addEventListener('input', function () {
      const nextScale = rowHeightPercentToScale(sliderElement.value);
      setCalendarRowHeightScale(nextScale);
    });
  }

  bindDayWidthSlider(desktopDayWidthSlider);
  bindRowHeightSlider(desktopRowHeightSlider);

  function captureCalendarScrollState() {
    return calendarScrollers.map(function (scroll) {
      const maxScroll = Math.max(0, scroll.scrollWidth - scroll.clientWidth);
      const ratio = maxScroll > 0 ? (scroll.scrollLeft / maxScroll) : 0;
      return {
        scroll: scroll,
        ratio: ratio
      };
    });
  }

  function restoreCalendarScrollState(state) {
    if (!Array.isArray(state) || !state.length) {
      return;
    }
    requestAnimationFrame(function () {
      state.forEach(function (item) {
        if (!item || !item.scroll) {
          return;
        }
        const maxScroll = Math.max(0, item.scroll.scrollWidth - item.scroll.clientWidth);
        item.scroll.scrollLeft = Math.round(maxScroll * Math.max(0, Math.min(1, item.ratio || 0)));
      });
    });
  }

  function updateMobileZoomUi() {
    if (!mobileZoomControls) {
      return;
    }
    const isMobile = isMobileCalendar();
    const pct = Math.round(mobileCalendarZoom * 100);
    mobileZoomControls.hidden = !isMobile;
    document.body.classList.toggle('calendar-mobile-zoom-compact', isMobile && mobileCalendarZoom <= 0.7);
    document.body.classList.toggle('calendar-mobile-zoom-micro', isMobile && mobileCalendarZoom <= 0.58);
    if (mobileZoomValueLabel) {
      mobileZoomValueLabel.textContent = String(pct) + '%';
    }
    if (mobileZoomOutButton) {
      mobileZoomOutButton.disabled = mobileCalendarZoom <= (MOBILE_CALENDAR_ZOOM_MIN + 0.001);
    }
    if (mobileZoomInButton) {
      mobileZoomInButton.disabled = mobileCalendarZoom >= (MOBILE_CALENDAR_ZOOM_MAX - 0.001);
    }
    if (mobileZoomResetButton) {
      mobileZoomResetButton.disabled = Math.abs(mobileCalendarZoom - 1) < 0.001;
    }
  }

  function setMobileCalendarZoom(nextZoom, options) {
    const opts = options || {};
    const clamped = clampMobileCalendarZoom(nextZoom);
    const unchanged = Math.abs(clamped - mobileCalendarZoom) < 0.001;
    if (unchanged && !opts.force) {
      updateMobileZoomUi();
      return;
    }
    const scrollState = captureCalendarScrollState();
    mobileCalendarZoom = clamped;
    try {
      window.localStorage.setItem(MOBILE_CALENDAR_ZOOM_KEY, mobileCalendarZoom.toFixed(2));
    } catch (error) {
      // Ignore storage restrictions.
    }
    syncCalendarTableWidths();
    restoreCalendarScrollState(scrollState);
    normalizeCalendarScrollerRanges();
    updateMobileZoomUi();
  }

  function updateCalendarScrollbarState() {
    calendarScrollers.forEach((scroll) => {
      const table = scroll.querySelector('.calendar-table');
      if (!table) {
        scroll.classList.remove('has-x-scroll');
        return;
      }
      const hasOverflow = table.scrollWidth > scroll.clientWidth + 1;
      scroll.classList.toggle('has-x-scroll', hasOverflow);
    });
  }

  function getPrimaryCalendarScroller() {
    return calendarScrollers.length ? calendarScrollers[0] : null;
  }

  function resolveCalendarScrollLeftForMetrics(targetScroll, sourceMetrics) {
    if (!targetScroll || !sourceMetrics) {
      return 0;
    }
    const targetMetrics = getCalendarScrollMetrics(targetScroll);
    if (!targetMetrics) {
      return 0;
    }
    const desiredLeft = targetMetrics.roomWidth + (sourceMetrics.dateOffset * targetMetrics.dayWidth);
    const maxLeft = Math.max(0, targetScroll.scrollWidth - targetScroll.clientWidth);
    return Math.max(0, Math.min(maxLeft, Math.round(desiredLeft)));
  }

  function syncCalendarGlobalScrollbarFromMetrics(sourceMetrics) {
    if (!calendarGlobalScrollbarRoot || !calendarGlobalScrollbarInner || !sourceMetrics || isMobileCalendar()) {
      return;
    }
    const primaryScroll = getPrimaryCalendarScroller();
    if (!primaryScroll) {
      return;
    }
    const desiredLeft = resolveCalendarScrollLeftForMetrics(primaryScroll, sourceMetrics);
    if (Math.abs(calendarGlobalScrollbarInner.scrollLeft - desiredLeft) <= 1) {
      return;
    }
    isCalendarGlobalScrollbarSyncing = true;
    calendarGlobalScrollbarInner.scrollLeft = desiredLeft;
    isCalendarGlobalScrollbarSyncing = false;
  }

  function ensureCalendarGlobalScrollbar() {
    if (calendarGlobalScrollbarRoot || !document.body) {
      return;
    }
    calendarGlobalScrollbarRoot = document.createElement('div');
    calendarGlobalScrollbarRoot.className = 'calendar-global-scrollbar';
    calendarGlobalScrollbarRoot.setAttribute('aria-hidden', 'true');
    const track = document.createElement('div');
    track.className = 'calendar-global-scrollbar-track';
    calendarGlobalScrollbarInner = document.createElement('div');
    calendarGlobalScrollbarInner.className = 'calendar-global-scrollbar-inner';
    calendarGlobalScrollbarInner.setAttribute('aria-label', 'Desplazamiento horizontal del calendario');
    calendarGlobalScrollbarSpacer = document.createElement('div');
    calendarGlobalScrollbarSpacer.className = 'calendar-global-scrollbar-spacer';
    calendarGlobalScrollbarInner.appendChild(calendarGlobalScrollbarSpacer);
    track.appendChild(calendarGlobalScrollbarInner);
    calendarGlobalScrollbarRoot.appendChild(track);
    document.body.appendChild(calendarGlobalScrollbarRoot);
    calendarGlobalScrollbarInner.addEventListener('scroll', function () {
      if (isCalendarGlobalScrollbarSyncing || isCalendarScrollSyncing || isMobileCalendar()) {
        return;
      }
      const primaryScroll = getPrimaryCalendarScroller();
      if (!primaryScroll) {
        return;
      }
      primaryScroll.scrollLeft = Math.round(calendarGlobalScrollbarInner.scrollLeft);
      syncCalendarScrollFrom(primaryScroll);
    }, { passive: true });
    calendarGlobalScrollbarRoot.addEventListener('wheel', handleCalendarHorizontalWheel, { passive: false });
  }

  function refreshCalendarGlobalScrollbar() {
    const card = document.querySelector('.calendar-card');
    if (card) {
      card.classList.remove('has-global-scrollbar');
    }
    if (!calendarScrollers.length || isMobileCalendar()) {
      if (calendarGlobalScrollbarRoot) {
        calendarGlobalScrollbarRoot.classList.remove('is-active');
      }
      return;
    }
    ensureCalendarGlobalScrollbar();
    const primaryScroll = getPrimaryCalendarScroller();
    if (!primaryScroll || !calendarGlobalScrollbarRoot || !calendarGlobalScrollbarInner || !calendarGlobalScrollbarSpacer) {
      return;
    }
    const scrollWidth = Math.max(primaryScroll.scrollWidth, primaryScroll.clientWidth);
    const hasOverflow = primaryScroll.scrollWidth > primaryScroll.clientWidth + 1;
    calendarGlobalScrollbarSpacer.style.width = `${Math.max(1, Math.round(scrollWidth))}px`;
    calendarGlobalScrollbarRoot.classList.toggle('is-active', hasOverflow);
    if (card) {
      card.classList.toggle('has-global-scrollbar', hasOverflow);
    }
    if (!hasOverflow) {
      return;
    }
    const primaryMetrics = getCalendarScrollMetrics(primaryScroll);
    if (primaryMetrics) {
      syncCalendarGlobalScrollbarFromMetrics(primaryMetrics);
    }
  }

  function isCalendarKeyboardEditableTarget(target) {
    if (!target || typeof target.closest !== 'function') {
      return false;
    }
    if (target.isContentEditable) {
      return true;
    }
    return !!target.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"], [role="textbox"]');
  }

  function handleCalendarArrowKeyScroll(event) {
    if (!event || isMobileCalendar()) {
      return;
    }
    if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) {
      return;
    }
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
      return;
    }
    if (isCalendarKeyboardEditableTarget(event.target)) {
      return;
    }
    if (!calendarGlobalScrollbarRoot || !calendarGlobalScrollbarRoot.classList.contains('is-active') || !calendarGlobalScrollbarInner) {
      return;
    }
    const primaryScroll = getPrimaryCalendarScroller();
    if (!primaryScroll) {
      return;
    }
    const metrics = getCalendarScrollMetrics(primaryScroll);
    const step = metrics
      ? Math.max(96, Math.round(metrics.dayWidth * 2))
      : Math.max(96, Math.round(primaryScroll.clientWidth * 0.18));
    const direction = event.key === 'ArrowRight' ? 1 : -1;
    const maxLeft = Math.max(0, calendarGlobalScrollbarInner.scrollWidth - calendarGlobalScrollbarInner.clientWidth);
    const nextLeft = Math.max(0, Math.min(maxLeft, Math.round(calendarGlobalScrollbarInner.scrollLeft + (direction * step))));
    if (Math.abs(nextLeft - calendarGlobalScrollbarInner.scrollLeft) <= 1) {
      return;
    }
    event.preventDefault();
    calendarGlobalScrollbarInner.scrollLeft = nextLeft;
  }

  function normalizeCalendarWheelDelta(value, deltaMode) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric) || Math.abs(numeric) <= 0.01) {
      return 0;
    }
    if (deltaMode === 1) {
      return numeric * 18;
    }
    if (deltaMode === 2) {
      return numeric * Math.max(320, window.innerWidth * 0.85);
    }
    return numeric;
  }

  function resolveCalendarHorizontalWheelDelta(event) {
    if (!event) {
      return 0;
    }
    let deltaX = normalizeCalendarWheelDelta(event.deltaX, event.deltaMode);
    const deltaY = normalizeCalendarWheelDelta(event.deltaY, event.deltaMode);
    if (Math.abs(deltaX) <= 0.01 && event.shiftKey && Math.abs(deltaY) > 0.01) {
      deltaX = deltaY;
    }
    if (Math.abs(deltaX) <= 0.01) {
      return 0;
    }
    if (!event.shiftKey && Math.abs(deltaY) > 0.01 && Math.abs(deltaX) < (Math.abs(deltaY) * 0.7)) {
      return 0;
    }
    return deltaX;
  }

  function handleCalendarHorizontalWheel(event) {
    if (!event || isMobileCalendar()) {
      return;
    }
    if (event.defaultPrevented) {
      return;
    }
    if (!document.body || !document.body.classList.contains('is-calendar-view')) {
      return;
    }
    if (!calendarGlobalScrollbarRoot || !calendarGlobalScrollbarRoot.classList.contains('is-active') || !calendarGlobalScrollbarInner) {
      return;
    }
    if (event.target && typeof event.target.closest === 'function') {
      if (event.target.closest('.app-sidebar, .sidebar-backdrop')) {
        return;
      }
    }
    if (isCalendarKeyboardEditableTarget(event.target)) {
      return;
    }
    const deltaX = resolveCalendarHorizontalWheelDelta(event);
    if (Math.abs(deltaX) <= 0.01) {
      return;
    }
    const maxLeft = Math.max(0, calendarGlobalScrollbarInner.scrollWidth - calendarGlobalScrollbarInner.clientWidth);
    if (maxLeft <= 0) {
      return;
    }
    const nextLeft = Math.max(0, Math.min(maxLeft, Math.round(calendarGlobalScrollbarInner.scrollLeft + deltaX)));
    if (Math.abs(nextLeft - calendarGlobalScrollbarInner.scrollLeft) <= 0.5) {
      return;
    }
    event.preventDefault();
    calendarGlobalScrollbarInner.scrollLeft = nextLeft;
  }

  function syncCalendarTableWidths() {
    calendarTables.forEach((table) => {
      const dayHeaders = table.querySelectorAll('thead th.day-header');
      const dayCount = dayHeaders.length;
      if (!dayCount) {
        return;
      }
      const isCategoryAvailability = table.classList.contains('calendar-category-availability');
      const roomCells = table.querySelectorAll('th.room-header, th.room-cell, td.room-cell, th.category-cell');
      let measuredRoomWidth = isCategoryAvailability ? 110 : 118;
      roomCells.forEach(function (cell) {
        measuredRoomWidth = Math.max(measuredRoomWidth, Math.ceil(cell.scrollWidth + 10));
      });
      if (!table.dataset.baseCellHeight) {
        const computedCellHeight = parseInt(window.getComputedStyle(table).getPropertyValue('--calendar-cell-height'), 10);
        const fallbackCellHeight = isCategoryAvailability ? 56 : 76;
        table.dataset.baseCellHeight = String(Number.isFinite(computedCellHeight) && computedCellHeight > 0 ? computedCellHeight : fallbackCellHeight);
      }
      const mobileZoomScale = isMobileCalendar() ? mobileCalendarZoom : 1;
      const mobileRoomBase = Math.max(52, Math.min(88, Math.floor(window.innerWidth * 0.22), measuredRoomWidth));
      const desktopRoomMin = isCategoryAvailability ? 108 : 112;
      const desktopRoomMax = isCategoryAvailability ? 188 : 168;
      const roomColWidth = isMobileCalendar()
        ? Math.max(44, Math.round(mobileRoomBase * mobileZoomScale))
        : Math.min(desktopRoomMax, Math.max(desktopRoomMin, measuredRoomWidth));
      const mobileDayBase = Math.max(62, Math.min(104, Math.floor(window.innerWidth * 0.2)));
      const baseDayColWidth = isMobileCalendar()
        ? Math.max(44, Math.round(mobileDayBase * mobileZoomScale))
        : 120;
      const dayColWidth = isMobileCalendar()
        ? Math.max(24, Math.round(baseDayColWidth * calendarDayWidthScale))
        : Math.max(60, Math.round(baseDayColWidth * calendarDayWidthScale));
      const tableWidth = roomColWidth + (dayCount * dayColWidth);
      const baseCellHeight = parseInt(table.dataset.baseCellHeight || '', 10) || (isCategoryAvailability ? 56 : 76);
      if (isMobileCalendar()) {
        const minCellHeight = isCategoryAvailability ? 24 : 28;
        const scaledCellHeight = Math.max(minCellHeight, Math.round(baseCellHeight * mobileZoomScale * calendarRowHeightScale));
        table.style.setProperty('--calendar-cell-height', `${scaledCellHeight}px`);
        table.style.setProperty('--calendar-mobile-zoom', `${mobileZoomScale.toFixed(2)}`);
      } else {
        const minCellHeight = isCategoryAvailability ? 28 : 36;
        const scaledCellHeight = Math.max(minCellHeight, Math.round(baseCellHeight * calendarRowHeightScale));
        table.style.setProperty('--calendar-cell-height', `${scaledCellHeight}px`);
        table.style.removeProperty('--calendar-mobile-zoom');
      }
      table.style.setProperty('--calendar-room-width', `${roomColWidth}px`);
      table.style.setProperty('--calendar-day-width', `${dayColWidth}px`);
      table.style.setProperty('--calendar-day-count', `${dayCount}`);
      table.style.width = `${tableWidth}px`;
      table.style.minWidth = `${tableWidth}px`;
    });
    updateCalendarScrollbarState();
    refreshCalendarGlobalScrollbar();
  }

  syncCalendarTableWidths();
  updateCalendarDayWidthUi();
  updateCalendarRowHeightUi();
  function scrollCalendarToDate(scroll, targetDate) {
    if (!scroll || !targetDate) return;
    const table = scroll.querySelector('.calendar-table');
    if (!table) return false;
    const header = table.querySelector('.day-header[data-date="' + targetDate + '"]');
    if (!header) return false;
    const style = window.getComputedStyle(table);
    const roomWidth = parseInt(style.getPropertyValue('--calendar-room-width'), 10) || 168;
    const left = Math.max(0, header.offsetLeft - roomWidth);
    scroll.scrollLeft = left;
    return true;
  }

  function initCalendarScrollPosition() {
    calendarScrollers.forEach((scroll) => {
      const preferred = scroll.getAttribute('data-scroll-date') || '';
      const today = scroll.getAttribute('data-today') || '';
      if (!preferred && !today) return;
      requestAnimationFrame(function () {
        const didScroll = preferred ? scrollCalendarToDate(scroll, preferred) : false;
        if (!didScroll && today) {
          scrollCalendarToDate(scroll, today);
        }
      });
    });
  }

  let isCalendarScrollSyncing = false;

  function getCalendarScrollMetrics(scroll) {
    if (!scroll) {
      return null;
    }
    const table = scroll.querySelector('.calendar-table');
    if (!table) {
      return null;
    }
    const style = window.getComputedStyle(table);
    const roomWidth = parseInt(style.getPropertyValue('--calendar-room-width'), 10) || 168;
    const dayWidth = Math.max(1, parseInt(style.getPropertyValue('--calendar-day-width'), 10) || 120);
    const dateOffset = Math.max(0, scroll.scrollLeft - roomWidth) / dayWidth;
    return {
      roomWidth: roomWidth,
      dayWidth: dayWidth,
      dateOffset: dateOffset
    };
  }

  function syncCalendarScrollFrom(sourceScroll) {
    if (!sourceScroll || isCalendarScrollSyncing) {
      return;
    }
    const sourceMetrics = getCalendarScrollMetrics(sourceScroll);
    if (!sourceMetrics) {
      return;
    }
    isCalendarScrollSyncing = true;
    calendarScrollers.forEach(function (targetScroll) {
      if (!targetScroll || targetScroll === sourceScroll) {
        return;
      }
      targetScroll.scrollLeft = resolveCalendarScrollLeftForMetrics(targetScroll, sourceMetrics);
    });
    isCalendarScrollSyncing = false;
    syncCalendarGlobalScrollbarFromMetrics(sourceMetrics);
  }

  function bindCalendarScrollSync() {
    calendarScrollers.forEach(function (scroll) {
      if (!scroll || scroll.dataset.calendarSyncBound === '1') {
        return;
      }
      scroll.dataset.calendarSyncBound = '1';
      scroll.addEventListener('scroll', function () {
        if (isCalendarScrollSyncing) {
          return;
        }
        syncCalendarScrollFrom(scroll);
        scheduleCalendarReturnStateSave(90);
      }, { passive: true });
      scroll.addEventListener('wheel', handleCalendarHorizontalWheel, { passive: false });
    });
  }

  function normalizeCalendarScrollerRanges() {
    if (!calendarScrollers.length) {
      return;
    }
    requestAnimationFrame(function () {
      syncCalendarScrollFrom(calendarScrollers[0]);
      refreshCalendarGlobalScrollbar();
    });
  }

  let activeCalendarTable = null;
  const actionSlot = document.getElementById('calendar-selection-actions-slot');
  const calendarControlsForm = document.getElementById('calendar-controls-form');
  function attachActionBar(table) {
    if (!table) return;
    activeCalendarTable = table;
    if (actionSlot && !isMobileCalendar()) {
      return;
    }
    const scroll = table.closest('.calendar-scroll');
    if (scroll && scroll.parentElement) {
      scroll.parentElement.insertBefore(actionBar, scroll.nextSibling);
    } else {
      table.insertAdjacentElement('afterend', actionBar);
    }
  }

  calendarScrollers = Array.prototype.slice.call(document.querySelectorAll('.calendar-scroll'));
  bindCalendarScrollSync();
  updateCalendarScrollbarState();
  document.addEventListener('keydown', handleCalendarArrowKeyScroll);
  document.addEventListener('wheel', handleCalendarHorizontalWheel, { passive: false });
  const scrollContainer = calendarTables[0].closest('.calendar-scroll');
  const controlsBar = document.querySelector('.calendar-controls');
  const actionBarRoot = document.querySelector('.calendar-action-bar');
  const desktopMenuSource = document.querySelector('.calendar-desktop-toolbar');
  const desktopMenuSlot = document.getElementById('calendar-desktop-menu-slot');
  const actionBarOriginalParent = actionBarRoot ? actionBarRoot.parentElement : null;
  const actionBarOriginalNext = actionBarRoot ? actionBarRoot.nextSibling : null;
  const dashboardUrl = (typeof window.pmsBuildUrl === 'function')
    ? window.pmsBuildUrl('index.php?view=dashboard')
    : 'index.php?view=dashboard';
  const logoutUrl = (typeof window.pmsBuildUrl === 'function')
    ? window.pmsBuildUrl('logout.php')
    : 'logout.php';
  let mobileTopShell = null;
  let mobileTopToggle = null;
  let mobileTopControlsRow = null;
  let actionBarPlaceholder = null;
  let actionBarInitialTop = null;

  function dockCalendarDesktopMenu() {
    if (!desktopMenuSource || !desktopMenuSlot) {
      return;
    }
    const desktopMenu = desktopMenuSource.querySelector('.calendar-desktop-menu');
    if (!desktopMenu) {
      return;
    }
    if (!desktopMenuSlot.contains(desktopMenu)) {
      desktopMenuSlot.appendChild(desktopMenu);
    }
    desktopMenuSource.classList.add('is-docked');
  }

  function bindCalendarToolbarFormOwner() {
    if (!calendarControlsForm || !actionBarRoot) {
      return;
    }
    const formId = calendarControlsForm.id || 'calendar-controls-form';
    if (!calendarControlsForm.id) {
      calendarControlsForm.id = formId;
    }
    actionBarRoot
      .querySelectorAll('.calendar-today-btn, .calendar-filters select[name], .calendar-filters input[name]')
      .forEach(function (control) {
        control.setAttribute('form', formId);
      });

    actionBarRoot
      .querySelectorAll('.calendar-filters select[name], .calendar-filters input[name="start_date"]')
      .forEach(function (control) {
        if (control.dataset.calendarAutoSubmitBound === '1') {
          return;
        }
        control.dataset.calendarAutoSubmitBound = '1';
        control.removeAttribute('onchange');
        control.addEventListener('change', function () {
          markCalendarContextRestoreSkip();
          saveCalendarReturnState(readCalendarContextFromForm(calendarControlsForm));
          if (typeof calendarControlsForm.requestSubmit === 'function') {
            calendarControlsForm.requestSubmit();
          } else {
            calendarControlsForm.submit();
          }
        });
      });

    const todayBtn = actionBarRoot.querySelector('.calendar-today-btn');
    if (todayBtn && todayBtn.dataset.calendarTodayBound !== '1') {
      todayBtn.dataset.calendarTodayBound = '1';
      todayBtn.addEventListener('click', function (event) {
        event.preventDefault();
        markCalendarContextRestoreSkip();
        saveCalendarReturnState();
        if (typeof calendarControlsForm.requestSubmit === 'function') {
          calendarControlsForm.requestSubmit(todayBtn);
        } else {
          calendarControlsForm.submit();
        }
      });
    }

    if (calendarControlsForm.dataset.calendarSubmitStateBound !== '1') {
      calendarControlsForm.dataset.calendarSubmitStateBound = '1';
      calendarControlsForm.addEventListener('submit', function () {
        saveCalendarReturnState(readCalendarContextFromForm(calendarControlsForm));
      });
    }
  }
  function syncCalendarActionBar() {
    if (!actionBarRoot) return;
    if (isMobileCalendar()) {
      actionBarRoot.classList.remove('is-fixed');
      actionBarRoot.style.left = '';
      actionBarRoot.style.width = '';
      if (actionBarPlaceholder) {
        actionBarPlaceholder.style.height = '0px';
      }
      return;
    }
    if (!actionBarPlaceholder) {
      actionBarPlaceholder = document.createElement('div');
      actionBarPlaceholder.className = 'calendar-action-bar-placeholder';
      actionBarRoot.parentElement.insertBefore(actionBarPlaceholder, actionBarRoot);
    }
    const rect = actionBarRoot.getBoundingClientRect();
    const parentRect = actionBarRoot.parentElement.getBoundingClientRect();
    if (actionBarInitialTop === null) {
      actionBarInitialTop = rect.top + window.scrollY;
    }
    const shouldFix = window.scrollY > (actionBarInitialTop - 8);
    if (shouldFix) {
      actionBarPlaceholder.style.height = rect.height + 'px';
      actionBarRoot.classList.add('is-fixed');
      actionBarRoot.style.left = parentRect.left + 'px';
      actionBarRoot.style.width = parentRect.width + 'px';
    } else {
      actionBarRoot.classList.remove('is-fixed');
      actionBarRoot.style.left = '';
      actionBarRoot.style.width = '';
      actionBarPlaceholder.style.height = '0px';
    }
  }
  const actionBar = document.createElement('div');
  actionBar.className = 'calendar-selection-actions';
  actionBar.innerHTML = `
    <div class="calendar-action-group calendar-action-cells">
      <button type="button" class="calendar-action-btn button-secondary js-calendar-block" hidden>Bloquear fechas seleccionadas</button>
      <button type="button" class="calendar-action-btn button-secondary js-calendar-create" hidden>Crear reserva para fechas</button>
      <button type="button" class="calendar-action-btn button-secondary js-calendar-quick-res" hidden>Reserva r&aacute;pida</button>
    </div>
    <div class="calendar-action-group calendar-action-reservations">
      <button type="button" class="calendar-action-btn button-secondary js-calendar-view-res" hidden>Ver reservacion</button>
      <button type="button" class="calendar-action-btn button-secondary js-calendar-move-res" hidden>Mover</button>
      <button type="button" class="calendar-action-btn button-secondary js-calendar-pay-res" hidden>Aplicar pago</button>
      <button type="button" class="calendar-action-btn button-secondary js-calendar-add-charges" hidden>Agregar servicio</button>
      <button type="button" class="calendar-action-btn button-secondary js-calendar-advance-status" hidden>Avanzar estatus</button>
      <button type="button" class="calendar-action-btn button-secondary js-calendar-no-show" hidden>No show</button>
      <button type="button" class="calendar-action-btn button-secondary js-calendar-cancel-res" hidden>Cancelar reservaciones</button>
      <button type="button" class="calendar-action-btn button-secondary js-calendar-group-res" hidden>Agregar a grupo</button>
      <button type="button" class="calendar-action-btn button-secondary js-calendar-delete-blocks" hidden>Eliminar bloqueos</button>
    </div>
  `;
  const blockButton = actionBar.querySelector('.js-calendar-block');
  const createButton = actionBar.querySelector('.js-calendar-create');
  const quickResButton = actionBar.querySelector('.js-calendar-quick-res');
  const viewResButton = actionBar.querySelector('.js-calendar-view-res');
  const moveResButton = actionBar.querySelector('.js-calendar-move-res');
  const payResButton = actionBar.querySelector('.js-calendar-pay-res');
  const addChargesButton = actionBar.querySelector('.js-calendar-add-charges');
  const advanceStatusButton = actionBar.querySelector('.js-calendar-advance-status');
  const noShowButton = actionBar.querySelector('.js-calendar-no-show');
  const cancelResButton = actionBar.querySelector('.js-calendar-cancel-res');
  const groupResButton = actionBar.querySelector('.js-calendar-group-res');
  const deleteBlocksButton = actionBar.querySelector('.js-calendar-delete-blocks');
  const mobileSelectionClearButton = document.createElement('button');
  mobileSelectionClearButton.type = 'button';
  mobileSelectionClearButton.className = 'calendar-mobile-selection-clear';
  mobileSelectionClearButton.setAttribute('aria-label', 'Cancelar seleccion');
  mobileSelectionClearButton.setAttribute('title', 'Cancelar seleccion');
  mobileSelectionClearButton.innerHTML = '<span aria-hidden="true">&times;</span>';
  mobileSelectionClearButton.hidden = true;
  mobileSelectionClearButton.addEventListener('click', function () {
    clearSelection();
  });
  document.body.appendChild(mobileSelectionClearButton);
  function placeSelectionActionBar() {
    if (isMobileCalendar()) {
      if (actionBar.parentElement !== document.body) {
        document.body.appendChild(actionBar);
      }
      return;
    }
    if (actionSlot) {
      if (actionBar.parentElement !== actionSlot) {
        actionSlot.appendChild(actionBar);
      }
      return;
    }
    if (scrollContainer && scrollContainer.parentElement) {
      if (actionBar.parentElement !== scrollContainer.parentElement) {
        scrollContainer.parentElement.insertBefore(actionBar, scrollContainer.nextSibling);
      }
      return;
    }
  }
  if (actionSlot) {
    actionSlot.appendChild(actionBar);
  } else if (scrollContainer && scrollContainer.parentElement) {
    scrollContainer.parentElement.insertBefore(actionBar, scrollContainer.nextSibling);
  } else {
    calendarTables[0].insertAdjacentElement('afterend', actionBar);
  }
  activeCalendarTable = calendarTables[0];
  function ensureMobileTopMenu() {
    document.body.classList.add('is-calendar-view');
    bindCalendarToolbarFormOwner();
    dockCalendarDesktopMenu();
    if (!isMobileCalendar()) {
      document.body.classList.remove('is-calendar-mobile');
      if (actionBarRoot && actionBarOriginalParent && actionBarRoot.parentElement !== actionBarOriginalParent) {
        if (actionBarOriginalNext && actionBarOriginalNext.parentNode === actionBarOriginalParent) {
          actionBarOriginalParent.insertBefore(actionBarRoot, actionBarOriginalNext);
        } else {
          actionBarOriginalParent.appendChild(actionBarRoot);
        }
      }
      if (mobileTopShell) {
        mobileTopShell.hidden = true;
      }
      if (mobileZoomControls) {
        mobileZoomControls.hidden = true;
      }
      if (mobileDayWidthControls) {
        mobileDayWidthControls.hidden = true;
      }
      if (mobileRowHeightControls) {
        mobileRowHeightControls.hidden = true;
      }
      return;
    }
    document.body.classList.add('is-calendar-mobile');
    if (!mobileTopShell) {
      mobileTopShell = document.createElement('div');
      mobileTopShell.className = 'calendar-mobile-topshell';
      mobileTopShell.innerHTML = ''
        + '<div class="calendar-mobile-topbar">'
        + '  <div class="calendar-mobile-top-left">'
        + '    <button type="button" class="calendar-mobile-iconbtn js-calendar-mobile-hamb" aria-label="Menu" title="Menu"><span aria-hidden="true">&#9776;</span></button>'
        + '  </div>'
        + '  <div class="calendar-mobile-top-center">'
        + '    <a class="calendar-mobile-iconbtn js-calendar-mobile-home" href="' + dashboardUrl + '" aria-label="Inicio" title="Inicio"><span aria-hidden="true">&#8962;</span></a>'
        + '  </div>'
        + '  <div class="calendar-mobile-top-right">'
        + '    <a class="calendar-mobile-iconbtn js-calendar-mobile-logout" href="' + logoutUrl + '" aria-label="Cerrar sesion" title="Cerrar sesion"><span aria-hidden="true">&#10162;</span></a>'
        + '  </div>'
        + '</div>'
        + '<button type="button" class="calendar-mobile-top-toggle" aria-label="Mostrar controles" title="Mostrar controles">'
        + '  <span aria-hidden="true">v</span>'
        + '</button>'
        + '<div class="calendar-mobile-controls-row"></div>';
      document.body.appendChild(mobileTopShell);
      mobileTopToggle = mobileTopShell.querySelector('.calendar-mobile-top-toggle');
      mobileTopControlsRow = mobileTopShell.querySelector('.calendar-mobile-controls-row');
      const mobileHamb = mobileTopShell.querySelector('.js-calendar-mobile-hamb');
      const globalHamb = document.querySelector('.nav-toggle');
      const syncMobileTopToggleVisual = function () {
        if (!mobileTopShell || !mobileTopToggle) return;
        const isOpen = mobileTopShell.classList.contains('is-open');
        mobileTopToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        mobileTopToggle.setAttribute('aria-label', isOpen ? 'Ocultar controles' : 'Mostrar controles');
        mobileTopToggle.setAttribute('title', isOpen ? 'Ocultar controles' : 'Mostrar controles');
        const toggleText = mobileTopToggle.querySelector('span');
        if (toggleText) {
          toggleText.textContent = isOpen ? '^' : 'v';
        }
      };

      mobileTopToggle.addEventListener('click', function () {
        const open = !mobileTopShell.classList.contains('is-open');
        mobileTopShell.classList.toggle('is-open', open);
        syncMobileTopToggleVisual();
      });
      mobileHamb && mobileHamb.addEventListener('click', function () {
        if (globalHamb) {
          globalHamb.click();
        }
      });
      document.addEventListener('click', function (ev) {
        if (!mobileTopShell) return;
        if (!mobileTopShell.contains(ev.target)) {
          if (mobileTopShell.classList.contains('is-open')) {
            mobileTopShell.classList.remove('is-open');
            syncMobileTopToggleVisual();
          }
        }
      });
      syncMobileTopToggleVisual();
    }
    if (!mobileTopControlsRow) {
      mobileTopControlsRow = mobileTopShell.querySelector('.calendar-mobile-controls-row');
    }
    if (actionBarRoot && mobileTopControlsRow && actionBarRoot.parentElement !== mobileTopControlsRow) {
      mobileTopControlsRow.appendChild(actionBarRoot);
    }
    if (!mobileDayWidthControls) {
      mobileDayWidthControls = document.createElement('div');
      mobileDayWidthControls.className = 'calendar-mobile-daywidth-controls';
      mobileDayWidthControls.innerHTML = ''
        + '<span class="calendar-daywidth-label">Ancho celdas</span>'
        + '<input type="range" min="-50" max="50" step="5" value="0"'
        + ' class="calendar-daywidth-slider js-calendar-daywidth-slider-mobile"'
        + ' aria-label="Ajustar ancho de columnas del calendario">'
        + '<span class="calendar-daywidth-value js-calendar-daywidth-value-mobile">0%</span>';
      if (mobileTopControlsRow) {
        mobileTopControlsRow.appendChild(mobileDayWidthControls);
      }
      mobileDayWidthSlider = mobileDayWidthControls.querySelector('.js-calendar-daywidth-slider-mobile');
      mobileDayWidthValue = mobileDayWidthControls.querySelector('.js-calendar-daywidth-value-mobile');
      bindDayWidthSlider(mobileDayWidthSlider);
    } else if (mobileTopControlsRow && mobileDayWidthControls.parentElement !== mobileTopControlsRow) {
      mobileTopControlsRow.appendChild(mobileDayWidthControls);
    }
    if (!mobileRowHeightControls) {
      mobileRowHeightControls = document.createElement('div');
      mobileRowHeightControls.className = 'calendar-mobile-rowheight-controls';
      mobileRowHeightControls.innerHTML = ''
        + '<span class="calendar-rowheight-label">Alto filas</span>'
        + '<input type="range" min="-50" max="50" step="5" value="0"'
        + ' class="calendar-rowheight-slider js-calendar-rowheight-slider-mobile"'
        + ' aria-label="Ajustar altura de filas del calendario">'
        + '<span class="calendar-rowheight-value js-calendar-rowheight-value-mobile">0%</span>';
      if (mobileTopControlsRow) {
        mobileTopControlsRow.appendChild(mobileRowHeightControls);
      }
      mobileRowHeightSlider = mobileRowHeightControls.querySelector('.js-calendar-rowheight-slider-mobile');
      mobileRowHeightValue = mobileRowHeightControls.querySelector('.js-calendar-rowheight-value-mobile');
      bindRowHeightSlider(mobileRowHeightSlider);
    } else if (mobileTopControlsRow && mobileRowHeightControls.parentElement !== mobileTopControlsRow) {
      mobileTopControlsRow.appendChild(mobileRowHeightControls);
    }
    bindCalendarToolbarFormOwner();
    if (!mobileZoomControls) {
      mobileZoomControls = document.createElement('div');
      mobileZoomControls.className = 'calendar-mobile-zoom-controls';
      mobileZoomControls.innerHTML = ''
        + '<button type="button" class="calendar-mobile-zoom-btn js-calendar-zoom-out" aria-label="Alejar calendario" title="Alejar">-</button>'
        + '<button type="button" class="calendar-mobile-zoom-btn calendar-mobile-zoom-value js-calendar-zoom-reset" aria-label="Restablecer zoom" title="Restablecer zoom">100%</button>'
        + '<button type="button" class="calendar-mobile-zoom-btn js-calendar-zoom-in" aria-label="Acercar calendario" title="Acercar">+</button>';
      document.body.appendChild(mobileZoomControls);
      mobileZoomOutButton = mobileZoomControls.querySelector('.js-calendar-zoom-out');
      mobileZoomResetButton = mobileZoomControls.querySelector('.js-calendar-zoom-reset');
      mobileZoomInButton = mobileZoomControls.querySelector('.js-calendar-zoom-in');
      mobileZoomValueLabel = mobileZoomResetButton;
      if (mobileZoomOutButton) {
        mobileZoomOutButton.addEventListener('click', function () {
          setMobileCalendarZoom(mobileCalendarZoom - MOBILE_CALENDAR_ZOOM_STEP);
        });
      }
      if (mobileZoomInButton) {
        mobileZoomInButton.addEventListener('click', function () {
          setMobileCalendarZoom(mobileCalendarZoom + MOBILE_CALENDAR_ZOOM_STEP);
        });
      }
      if (mobileZoomResetButton) {
        mobileZoomResetButton.addEventListener('click', function () {
          setMobileCalendarZoom(1);
        });
      }
    }
    mobileTopShell.hidden = false;
    updateMobileZoomUi();
    updateCalendarDayWidthUi();
    updateCalendarRowHeightUi();
  }
  initCalendarScrollPosition();
  syncCalendarViewportZoom();
  syncCalendarActionBar();
  placeSelectionActionBar();
  ensureMobileTopMenu();
  normalizeCalendarScrollerRanges();
  const calendarReturnState = loadCalendarReturnState();
  const calendarFocusContext = readCalendarFocusContext();
  if (maybeRestoreCalendarContext(calendarReturnState, calendarFocusContext)) {
    return;
  }
  if (calendarReturnState) {
    restoreCalendarReturnState(calendarReturnState);
    if (!calendarFocusContext) {
      window.setTimeout(clearCalendarFocusQueryParams, 180);
    }
  }
  if (calendarFocusContext) {
    scheduleCalendarFocus(calendarFocusContext);
  }

  window.addEventListener('pagehide', function () {
    saveCalendarReturnState();
  });
  window.addEventListener('beforeunload', function () {
    saveCalendarReturnState();
  });
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      saveCalendarReturnState();
    }
  });

  window.addEventListener('resize', function () {
    actionBarInitialTop = null;
    syncCalendarViewportZoom();
    syncCalendarTableWidths();
    syncCalendarActionBar();
    placeSelectionActionBar();
    ensureMobileTopMenu();
    updateMobileZoomUi();
    updateCalendarDayWidthUi();
    updateCalendarRowHeightUi();
    normalizeCalendarScrollerRanges();
    scheduleCalendarReturnStateSave(120);
  });
  window.addEventListener('scroll', function () {
    syncCalendarActionBar();
    scheduleCalendarReturnStateSave(90);
  }, { passive: true });

  let selectionCells = [];
  let selectionData = [];
  let reservationSelection = [];
  let reservationData = [];
  let selectionType = null; // 'cell' | 'reservation' | 'block'
  let selectionProperty = null;
  // Cache de fechas ocupadas y bloqueos visibles por propiedad/habitacion
  const occupiedDatesByRoom = {};
  const renderedBlocksByRoom = {};

  function normalizeStatus(value) {
    let normalized = (value || '').toString().toLowerCase().replace(/_/g, ' ').trim();
    normalized = normalized.replace(/\s+/g, ' ');
    if (normalized === 'encasa' || normalized === 'checkedin') {
      normalized = 'en casa';
    }
    if (normalized === 'confirmada') {
      normalized = 'confirmado';
    }
    if (normalized === 'sin confirmar' || normalized === 's/confirmar' || normalized === 'pendiente' || normalized === 'pending' || normalized === 'hold') {
      normalized = 'apartado';
    }
    if (normalized === 'no show' || normalized === 'noshow' || normalized === 'no_show') {
      normalized = 'no-show';
    }
    return normalized;
  }

  function getNextStatus(currentStatus) {
    const flow = ['apartado', 'confirmado', 'en casa', 'salida'];
    const normalized = normalizeStatus(currentStatus);
    const currentIndex = flow.indexOf(normalized);
    if (currentIndex < 0 || currentIndex >= flow.length - 1) {
      return null;
    }
    return flow[currentIndex + 1];
  }

  function addOccupied(prop, room, startDate, endDateExclusive) {
    if (!prop || !room || !startDate || !endDateExclusive) return;
    const key = prop + '::' + room;
    if (!occupiedDatesByRoom[key]) occupiedDatesByRoom[key] = new Set();
    const start = new Date(startDate + 'T00:00:00');
    const end = new Date(endDateExclusive + 'T00:00:00');
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return;
    for (let dt = new Date(start); dt < end; dt.setDate(dt.getDate() + 1)) {
      occupiedDatesByRoom[key].add(dt.toISOString().slice(0, 10));
    }
  }

  // Precalcula fechas ocupadas (reservas y bloqueos visibles)
  document.querySelectorAll('.calendar-cell.reservation').forEach(function (cell) {
    const propCode = (cell.getAttribute('data-property-code') || '').toUpperCase();
    const roomCode = (cell.getAttribute('data-room-code') || '').toUpperCase();
    const checkIn = cell.getAttribute('data-check-in') || '';
    const checkOut = cell.getAttribute('data-check-out') || '';
    const isBlock = cell.classList.contains('is-block');
    if (propCode && roomCode && checkIn && checkOut) {
      addOccupied(propCode, roomCode, checkIn, checkOut);
      if (isBlock) {
        const key = propCode + '::' + roomCode;
        if (!renderedBlocksByRoom[key]) renderedBlocksByRoom[key] = [];
        renderedBlocksByRoom[key].push({ start: checkIn, end: checkOut });
      }
  }
});

  // Normaliza y ordena bloques renderizados por fecha
  Object.keys(renderedBlocksByRoom).forEach(function (key) {
    renderedBlocksByRoom[key] = renderedBlocksByRoom[key]
      .map(function (b) { return { start: b.start, end: b.end }; })
      .filter(function (b) { return b.start && b.end; })
      .sort(function (a, b) { return a.start.localeCompare(b.start); });
  });
  window.pmsCalendarSelection = selectionData;
  let moveModeActive = false;
  let moveModeSource = null;
  const moveModeTargets = new Set();
  const calendarVisibleDateSet = new Set(
    Array.prototype.slice.call(document.querySelectorAll('.calendar-day-row .day-header[data-date]'))
      .map(function (header) { return (header.getAttribute('data-date') || '').trim(); })
      .filter(function (dateKey) { return dateKey !== ''; })
  );

  function clearMoveModeTargets() {
    document.querySelectorAll('.calendar-cell.is-move-target').forEach(function (cell) {
      cell.classList.remove('is-move-target');
      cell.removeAttribute('data-move-start');
      cell.removeAttribute('data-move-room');
      cell.removeAttribute('data-move-property');
    });
    moveModeTargets.clear();
  }

  function exitMoveMode() {
    if (!moveModeActive && !moveModeSource) {
      return;
    }
    moveModeActive = false;
    moveModeSource = null;
    clearMoveModeTargets();
    document.body.classList.remove('calendar-move-mode');
    actionBar.classList.remove('is-move-mode');
    updateActionBar();
  }

  function reservationNights(reservation) {
    const checkIn = (reservation && reservation.checkIn ? reservation.checkIn : '').trim();
    const checkOut = (reservation && reservation.checkOut ? reservation.checkOut : '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(checkIn) || !/^\d{4}-\d{2}-\d{2}$/.test(checkOut)) {
      return 0;
    }
    const start = new Date(checkIn + 'T00:00:00');
    const end = new Date(checkOut + 'T00:00:00');
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
      return 0;
    }
    return Math.round((end - start) / (1000 * 60 * 60 * 24));
  }

  function buildDateRange(startDate, nights) {
    const list = [];
    if (!/^\d{4}-\d{2}-\d{2}$/.test((startDate || '').trim()) || !Number.isFinite(nights) || nights <= 0) {
      return list;
    }
    const cursor = new Date(startDate + 'T00:00:00');
    if (Number.isNaN(cursor.getTime())) {
      return list;
    }
    for (let i = 0; i < nights; i++) {
      list.push(formatLocalYmd(cursor));
      cursor.setDate(cursor.getDate() + 1);
    }
    return list;
  }

  function isMoveRangeAvailable(source, targetPropertyCode, targetRoomCode, targetStartDate, nights) {
    if (!source || !targetPropertyCode || !targetRoomCode) {
      return false;
    }
    const rangeDates = buildDateRange(targetStartDate, nights);
    if (!rangeDates.length) {
      return false;
    }
    const occupiedKey = targetPropertyCode + '::' + targetRoomCode;
    const occupiedSet = occupiedDatesByRoom[occupiedKey] || new Set();
    const sourceRoomCode = (source.roomCode || '').toUpperCase();
    const sourcePropertyCode = (source.propertyCode || '').toUpperCase();
    const sourceCheckIn = (source.checkIn || '').trim();
    const sourceCheckOut = (source.checkOut || '').trim();

    for (let i = 0; i < rangeDates.length; i++) {
      const dateKey = rangeDates[i];
      if (!calendarVisibleDateSet.has(dateKey)) {
        return false;
      }
      const isOwnCurrentRange = targetPropertyCode === sourcePropertyCode
        && targetRoomCode === sourceRoomCode
        && sourceCheckIn !== ''
        && sourceCheckOut !== ''
        && dateKey >= sourceCheckIn
        && dateKey < sourceCheckOut;
      if (occupiedSet.has(dateKey) && !isOwnCurrentRange) {
        return false;
      }
    }
    return true;
  }

  function enterMoveMode(sourceReservation) {
    if (!sourceReservation || sourceReservation.isBlock || !sourceReservation.reservationId) {
      return;
    }
    const sourcePropertyCode = (sourceReservation.propertyCode || '').toUpperCase();
    const sourceRoomCode = (sourceReservation.roomCode || '').toUpperCase();
    const nights = reservationNights(sourceReservation);
    if (!sourcePropertyCode || !sourceRoomCode || nights <= 0) {
      alert('No se pudo determinar fechas/habitacion para mover la reservacion.');
      return;
    }

    clearMoveModeTargets();
    moveModeSource = {
      reservationId: sourceReservation.reservationId,
      reservationCode: sourceReservation.reservationCode || '',
      propertyCode: sourcePropertyCode,
      roomCode: sourceRoomCode,
      checkIn: (sourceReservation.checkIn || '').trim(),
      checkOut: (sourceReservation.checkOut || '').trim(),
      nights: nights
    };

    const eligibleCells = [];
    document.querySelectorAll('.calendar-cell.is-empty').forEach(function (cell) {
      const propCode = (cell.getAttribute('data-property-code') || '').toUpperCase();
      const roomCode = (cell.getAttribute('data-room-code') || '').toUpperCase();
      const dateKey = (cell.getAttribute('data-date') || '').trim();
      if (!propCode || !roomCode || !dateKey) {
        return;
      }
      if (!isMoveRangeAvailable(moveModeSource, propCode, roomCode, dateKey, nights)) {
        return;
      }
      cell.classList.add('is-move-target');
      cell.setAttribute('data-move-start', dateKey);
      cell.setAttribute('data-move-room', roomCode);
      cell.setAttribute('data-move-property', propCode);
      eligibleCells.push(cell);
      moveModeTargets.add(propCode + '::' + roomCode + '::' + dateKey);
    });

    if (!eligibleCells.length) {
      moveModeSource = null;
      moveModeActive = false;
      alert('No hay espacios disponibles en calendario para mover esta reservacion.');
      return;
    }

    moveModeActive = true;
    document.body.classList.add('calendar-move-mode');
    actionBar.classList.add('is-move-mode');
    updateActionBar();
  }

  function clearSelection() {
    exitMoveMode();
    document.querySelectorAll('.calendar-cell.is-range-selected').forEach(function (cell) {
      cell.classList.remove('is-range-selected');
    });
    document.querySelectorAll('.calendar-cell.is-reservation-selected').forEach(function (cell) {
      cell.classList.remove('is-reservation-selected');
    });
    selectionCells = [];
    selectionData = [];
    reservationSelection = [];
    reservationData = [];
    selectionType = null;
    selectionProperty = null;
    window.pmsCalendarSelection = selectionData;
    updateActionBar();
  }

  function getCellData(cell) {
    return {
      dayIndex: parseInt(cell.getAttribute('data-day-index') || '-1', 10),
      date: cell.getAttribute('data-date') || '',
      roomCode: cell.getAttribute('data-room-code') || '',
      roomName: cell.getAttribute('data-room-name') || '',
      propertyCode: cell.getAttribute('data-property-code') || '',
      categoryCode: cell.getAttribute('data-category-code') || ''
    };
  }

  function toggleCellSelection(cell) {
    // Disallow mixing with other selection types or properties
    if (selectionType && selectionType !== 'cell') return;
    const propCode = (cell.getAttribute('data-property-code') || '').toUpperCase();
    if (selectionProperty && selectionProperty !== propCode) return;
    if (!selectionProperty) selectionProperty = propCode;
    if (!selectionType) selectionType = 'cell';
    const alreadySelected = cell.classList.contains('is-range-selected');
    if (alreadySelected) {
      cell.classList.remove('is-range-selected');
      selectionCells = selectionCells.filter(function (item) {
        return item !== cell;
      });
    } else {
      cell.classList.add('is-range-selected');
      selectionCells.push(cell);
    }

    selectionData = selectionCells.map(function (item) {
      return getCellData(item);
    });
    window.pmsCalendarSelection = selectionData;
    selectionType = selectionData.length ? 'cell' : null;
    if (!selectionData.length) {
      // Important: release property lock when last selected cell is removed.
      selectionProperty = null;
    }
    updateActionBar();
  }

  function toggleReservationSelection(cell) {
    const selType = cell.getAttribute('data-selection-type') || 'reservation';
    const propCode = (cell.getAttribute('data-property-code') || '').toUpperCase();
    // Once a selection type/property is established, do not mix
    if (selectionType && selectionType !== selType) return;
    if (selectionProperty && selectionProperty !== propCode) return;
    if (!selectionProperty) selectionProperty = propCode;
    if (!selectionType) selectionType = selType;
    const already = cell.classList.contains('is-reservation-selected');
    if (already) {
      cell.classList.remove('is-reservation-selected');
      reservationSelection = reservationSelection.filter(function (c) { return c !== cell; });
    } else {
      cell.classList.add('is-reservation-selected');
      reservationSelection.push(cell);
    }
    reservationData = reservationSelection.map(function (item) {
      return {
        reservationId: parseInt(item.getAttribute('data-reservation-id') || '0', 10),
        reservationCode: item.getAttribute('data-reservation-code') || '',
        reservationStatus: item.getAttribute('data-reservation-status') || '',
        folioCount: parseInt(item.getAttribute('data-reservation-folio-count') || '0', 10),
        balanceCents: parseInt(item.getAttribute('data-reservation-balance-cents') || '0', 10),
        currency: item.getAttribute('data-reservation-currency') || 'MXN',
        roomCode: item.getAttribute('data-room-code') || '',
        propertyCode: item.getAttribute('data-property-code') || '',
        guestName: item.getAttribute('data-guest-name') || '',
        checkIn: item.getAttribute('data-check-in') || '',
        checkOut: item.getAttribute('data-check-out') || '',
        blockId: parseInt(item.getAttribute('data-block-id') || '0', 10),
        isBlock: item.classList.contains('is-block')
      };
    }).filter(function (r) { return r.reservationId > 0 || r.blockId > 0; });
    if (reservationData.length) {
      const hasBlocks = reservationData.some(function (r) { return r.blockId > 0 || r.isBlock; });
      const hasBookings = reservationData.some(function (r) { return r.reservationId > 0 && !r.isBlock; });
      selectionType = hasBlocks && !hasBookings ? 'block' : 'reservation';
    } else {
      selectionType = null;
      selectionProperty = null;
    }
    updateActionBar();
  }

  function getSelectionSummary() {
    if (selectionType === 'reservation' || selectionType === 'block') {
      const blockCount = reservationData.filter(function (r) { return r.blockId > 0 || r.isBlock; }).length;
      const allBlocks = blockCount === reservationData.length;
      return {
        hasSelection: reservationData.length > 0,
        reservationCount: reservationData.length,
        blockCount: blockCount,
        allBlocks: allBlocks
      };
    }
    if (!selectionData.length) {
      return { hasSelection: false, sameRoom: false, contiguous: false, nights: 0 };
    }
    const first = selectionData[0];
    const roomCode = first.roomCode;
    let sameRoom = true;
    const indices = [];
    const indexSet = {};
    const dateSet = {};
    const uniqueDates = [];
    selectionData.forEach(function (item) {
      if (item.roomCode !== roomCode || !item.roomCode) {
        sameRoom = false;
      }
      const idx = typeof item.dayIndex === 'number'
        ? item.dayIndex
        : parseInt(item.dayIndex || '-1', 10);
      if (!Number.isNaN(idx) && idx >= 0 && !indexSet[idx]) {
        indexSet[idx] = true;
        indices.push(idx);
      }
      const dateKey = item.date || '';
      if (dateKey && !dateSet[dateKey]) {
        dateSet[dateKey] = true;
        uniqueDates.push(dateKey);
      }
    });
    indices.sort(function (a, b) { return a - b; });
    uniqueDates.sort();

    function isConsecutiveDates(list) {
      if (!list.length) {
        return false;
      }
      if (list.length === 1) {
        return true;
      }
      for (let i = 1; i < list.length; i++) {
        const prev = new Date(list[i - 1] + 'T00:00:00');
        const curr = new Date(list[i] + 'T00:00:00');
        const diff = (curr - prev) / (1000 * 60 * 60 * 24);
        if (diff !== 1) {
          return false;
        }
      }
      return true;
    }

    const contiguousByIndex = indices.length === selectionData.length && indices.length > 0
      ? (indices.length === 1 || (indices[indices.length - 1] - indices[0] + 1 === indices.length))
      : false;
    const contiguousByDate = uniqueDates.length === selectionData.length && uniqueDates.length > 0
      ? isConsecutiveDates(uniqueDates)
      : false;
    const contiguous = sameRoom && (contiguousByIndex || contiguousByDate);

    return {
      hasSelection: true,
      sameRoom: sameRoom,
      contiguous: contiguous,
      nights: Math.max(indices.length, uniqueDates.length, selectionData.length > 0 ? 1 : 0)
    };
  }

  function getSelectionRange() {
    if (!selectionData.length) {
      return null;
    }
    const dates = selectionData
      .map(function (item) { return item.date || ''; })
      .filter(function (d) { return d; })
      .sort();
    if (!dates.length) {
      return null;
    }
    const startDate = dates[0];
    const endDateInclusive = dates[dates.length - 1];
    const roomCode = selectionData[0].roomCode || '';
    const propertyCode = selectionData[0].propertyCode || '';
    const categoryCode = selectionData[0].categoryCode || '';
    return {
      startDate: startDate,
      endDateInclusive: endDateInclusive,
      roomCode: roomCode,
      propertyCode: propertyCode,
      categoryCode: categoryCode
    };
  }

  function addDays(dateStr, days) {
    const date = new Date(dateStr + 'T00:00:00');
    if (Number.isNaN(date.getTime())) {
      return '';
    }
    date.setDate(date.getDate() + days);
    return date.toISOString().slice(0, 10);
  }

  function formatLocalYmd(dateObj) {
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function isDateBeforeToday(dateStr) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test((dateStr || '').toString())) {
      return false;
    }
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return dateStr < formatLocalYmd(today);
  }

  function openQuickReservationLightbox(context, onConfirm) {
    const existing = document.querySelector('.calendar-quickres-lightbox');
    if (existing) {
      existing.remove();
    }

    const overlay = document.createElement('div');
    overlay.className = 'calendar-quickres-lightbox';
    overlay.innerHTML = `
      <div class="calendar-quickres-window" role="dialog" aria-modal="true" aria-label="Reserva rapida">
        <div class="calendar-quickres-head">
          <h3>Reserva rapida</h3>
          <button type="button" class="button-secondary calendar-quickres-close" aria-label="Cerrar">&times;</button>
        </div>
        <p class="calendar-quickres-context">
          ${(context.propertyCode || '').toString()} / ${(context.roomCode || '').toString()} | ${(context.checkIn || '').toString()} -> ${(context.checkOut || '').toString()}
        </p>
        <form class="calendar-quickres-form" novalidate>
          <label>
            Nombre
            <input type="text" name="guest_first_name" autocomplete="given-name" placeholder="Nombre del huesped" required>
          </label>
          <label>
            Apellido paterno (opcional)
            <input type="text" name="guest_last_name" autocomplete="family-name" placeholder="Apellido paterno">
          </label>
          <label>
            Apellido materno (opcional)
            <input type="text" name="guest_maiden_name" autocomplete="additional-name" placeholder="Apellido materno">
          </label>
          <label>
            Precio total (MXN)
            <input type="text" name="price_raw" inputmode="decimal" placeholder="0.00">
          </label>
          <label class="calendar-quickres-origin">
            Origen
            <select name="origin_key">
            </select>
          </label>
          <label>
            Check-in
            <input type="date" name="check_in" value="${(context.checkIn || '').toString()}">
          </label>
          <label>
            Check-out
            <input type="date" name="check_out" value="${(context.checkOut || '').toString()}">
          </label>
          <label class="calendar-quickres-notes">
            Notas
            <textarea name="notes_raw" rows="2" placeholder="Notas opcionales"></textarea>
          </label>
          <label class="calendar-quickres-payment-toggle">
            <input type="checkbox" name="mark_paid" value="1">
            <span>Reservaci&oacute;n ya pagada</span>
          </label>
          <div class="calendar-quickres-payment-fields" style="display:none;">
            <label>
              Tipo de pago
              <select name="payment_method">
              </select>
            </label>
            <label>
              Fecha de pago
              <input type="date" name="payment_date" value="${formatLocalYmd(new Date())}">
            </label>
            <label>
              Referencia (opcional)
              <input type="text" name="payment_reference" maxlength="120" placeholder="Referencia">
            </label>
          </div>
          <div class="calendar-quickres-actions">
            <button type="submit" class="button-primary">Crear reserva rapida</button>
            <button type="button" class="button-secondary calendar-quickres-cancel calendar-quickres-cancel-danger">
              <span class="calendar-quickres-cancel-icon" aria-hidden="true">&#10005;</span> Cancelar
            </button>
          </div>
        </form>
      </div>
    `;

    const bodyPrevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.body.appendChild(overlay);

    const closeButton = overlay.querySelector('.calendar-quickres-close');
    const cancelButton = overlay.querySelector('.calendar-quickres-cancel');
    const form = overlay.querySelector('.calendar-quickres-form');
    const guestFirstInput = overlay.querySelector('input[name="guest_first_name"]');
    const guestLastInput = overlay.querySelector('input[name="guest_last_name"]');
    const guestMaidenInput = overlay.querySelector('input[name="guest_maiden_name"]');
    const priceInput = overlay.querySelector('input[name="price_raw"]');
    const originSelect = overlay.querySelector('select[name="origin_key"]');
    const checkInInput = overlay.querySelector('input[name="check_in"]');
    const checkOutInput = overlay.querySelector('input[name="check_out"]');
    const notesInput = overlay.querySelector('textarea[name="notes_raw"]');
    const markPaidInput = overlay.querySelector('input[name="mark_paid"]');
    const paymentFieldsWrap = overlay.querySelector('.calendar-quickres-payment-fields');
    const paymentMethodSelect = overlay.querySelector('select[name="payment_method"]');
    const paymentDateInput = overlay.querySelector('input[name="payment_date"]');
    const paymentReferenceInput = overlay.querySelector('input[name="payment_reference"]');
    const otaMap = window.pmsOtaMap && typeof window.pmsOtaMap === 'object' ? window.pmsOtaMap : {};
    const sourceMap = window.pmsReservationSourceMap && typeof window.pmsReservationSourceMap === 'object'
      ? window.pmsReservationSourceMap
      : {};
    const paymentCatalogMap = window.pmsCalendarPaymentCatalogMap && typeof window.pmsCalendarPaymentCatalogMap === 'object'
      ? window.pmsCalendarPaymentCatalogMap
      : {};
    const paymentCatalogMapByReservation = window.pmsCalendarPaymentCatalogMapByReservation
      && typeof window.pmsCalendarPaymentCatalogMapByReservation === 'object'
      ? window.pmsCalendarPaymentCatalogMapByReservation
      : {};

    function normalizeCode(value) {
      return (value || '').toString().trim().toUpperCase();
    }

    function normalizeMoneyInput(value) {
      let raw = (value || '').toString().trim();
      if (raw === '') {
        return '';
      }
      raw = raw.replace(/\$/g, '').replace(/\s+/g, '');
      if (raw.indexOf(',') !== -1 && raw.indexOf('.') !== -1) {
        raw = raw.replace(/,/g, '');
      } else {
        raw = raw.replace(/,/g, '.');
      }
      return raw;
    }

    function otaRowsForProperty(propertyCode) {
      const prop = normalizeCode(propertyCode);
      const rows = otaMap[prop];
      if (Array.isArray(rows) && rows.length) {
        return rows;
      }
      return [{ id_ota_account: 0, ota_name: 'Sin origen' }];
    }

    function sourceRowsForProperty(propertyCode) {
      const prop = normalizeCode(propertyCode);
      const rows = sourceMap[prop];
      if (Array.isArray(rows) && rows.length) {
        return rows;
      }
      return [{ id_reservation_source: 0, source_name: 'Directo' }];
    }

    function fillOriginSelect() {
      if (!originSelect) return;
      const otaRows = otaRowsForProperty(context.propertyCode || '');
      const sourceRows = sourceRowsForProperty(context.propertyCode || '');

      while (originSelect.options.length) {
        originSelect.remove(0);
      }

      const sourceGroup = document.createElement('optgroup');
      sourceGroup.label = 'Origen';
      sourceRows.forEach(function (row) {
        const id = parseInt(row && row.id_reservation_source ? row.id_reservation_source : 0, 10) || 0;
        const label = (row && row.source_name ? row.source_name : (id > 0 ? ('Origen #' + String(id)) : 'Directo')).toString();
        const opt = document.createElement('option');
        opt.value = 'src:' + String(id);
        opt.textContent = label;
        sourceGroup.appendChild(opt);
      });
      if (sourceGroup.children.length) {
        originSelect.appendChild(sourceGroup);
      }

      const otaGroup = document.createElement('optgroup');
      otaGroup.label = 'OTA';
      otaRows.forEach(function (row) {
        const id = parseInt(row && row.id_ota_account ? row.id_ota_account : 0, 10) || 0;
        if (id <= 0) return;
        const label = (row && row.ota_name ? row.ota_name : ('OTA #' + String(id))).toString();
        const opt = document.createElement('option');
        opt.value = 'ota:' + String(id);
        opt.textContent = label;
        otaGroup.appendChild(opt);
      });
      if (otaGroup.children.length) {
        originSelect.appendChild(otaGroup);
      }

      if (!originSelect.options.length) {
        const fallback = document.createElement('option');
        fallback.value = 'src:0';
        fallback.textContent = 'Directo';
        originSelect.appendChild(fallback);
      }
      originSelect.selectedIndex = 0;
    }

    function paymentRowsForProperty(propertyCode, reservationId) {
      const rid = parseInt(reservationId || 0, 10) || 0;
      if (rid > 0 && Array.isArray(paymentCatalogMapByReservation[rid])) {
        return paymentCatalogMapByReservation[rid];
      }
      const prop = normalizeCode(propertyCode);
      const directRows = paymentCatalogMap[prop];
      if (Array.isArray(directRows) && directRows.length) {
        return directRows;
      }
      const globalRows = paymentCatalogMap['*'];
      if (Array.isArray(globalRows) && globalRows.length) {
        return globalRows;
      }
      return [];
    }

    function fillPaymentMethods() {
      if (!paymentMethodSelect) return;
      while (paymentMethodSelect.options.length) {
        paymentMethodSelect.remove(0);
      }
      const rows = paymentRowsForProperty(context.propertyCode || '', context && context.reservationId ? context.reservationId : 0);
      const seen = {};
      rows.forEach(function (row) {
        const id = parseInt(row && row.id_payment_catalog ? row.id_payment_catalog : 0, 10) || 0;
        if (id <= 0 || seen[id]) return;
        seen[id] = true;
        const label = (row && row.label ? row.label : (row && row.name ? row.name : ('Concepto #' + String(id)))).toString();
        const opt = document.createElement('option');
        opt.value = String(id);
        opt.textContent = label;
        paymentMethodSelect.appendChild(opt);
      });
      if (!paymentMethodSelect.options.length) {
        const fallback = document.createElement('option');
        fallback.value = '';
        fallback.textContent = '(Sin conceptos configurados)';
        paymentMethodSelect.appendChild(fallback);
      }
      paymentMethodSelect.selectedIndex = 0;
    }

    function syncPaidFields() {
      if (!markPaidInput || !paymentFieldsWrap) return;
      paymentFieldsWrap.style.display = markPaidInput.checked ? 'block' : 'none';
    }

    fillOriginSelect();
    fillPaymentMethods();
    syncPaidFields();

    function closeLightbox() {
      document.removeEventListener('keydown', onEscape);
      document.body.style.overflow = bodyPrevOverflow;
      if (overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
    }

    function onEscape(event) {
      if (event.key === 'Escape') {
        closeLightbox();
      }
    }

    document.addEventListener('keydown', onEscape);

    if (closeButton) {
      closeButton.addEventListener('click', closeLightbox);
    }
    if (cancelButton) {
      cancelButton.addEventListener('click', closeLightbox);
    }
    if (markPaidInput) {
      markPaidInput.addEventListener('change', syncPaidFields);
    }

    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        closeLightbox();
      }
    });

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const checkInRaw = checkInInput && checkInInput.value ? checkInInput.value.trim() : '';
        const checkOutRaw = checkOutInput && checkOutInput.value ? checkOutInput.value.trim() : '';
        if (!/^\d{4}-\d{2}-\d{2}$/.test(checkInRaw) || !/^\d{4}-\d{2}-\d{2}$/.test(checkOutRaw)) {
          alert('Captura check-in y check-out validos.');
          return;
        }
        if (checkOutRaw <= checkInRaw) {
          alert('Check-out debe ser posterior a check-in.');
          return;
        }
        const guestFirstNameRaw = guestFirstInput && guestFirstInput.value ? guestFirstInput.value.trim() : '';
        const guestLastNameRaw = guestLastInput && guestLastInput.value ? guestLastInput.value.trim() : '';
        const guestMaidenNameRaw = guestMaidenInput && guestMaidenInput.value ? guestMaidenInput.value.trim() : '';
        const priceRaw = priceInput && priceInput.value ? priceInput.value.trim() : '';
        const normalizedPriceRaw = normalizeMoneyInput(priceRaw);
        const priceValue = normalizedPriceRaw === '' ? 0 : parseFloat(normalizedPriceRaw);
        if (!guestFirstNameRaw) {
          alert('Captura el nombre del huesped.');
          return;
        }
        if (!Number.isFinite(priceValue) || priceValue < 0) {
          alert('Captura un monto valido de hospedaje.');
          return;
        }
        const markPaid = !!(markPaidInput && markPaidInput.checked);
        const paymentMethodId = paymentMethodSelect && paymentMethodSelect.value
          ? (parseInt(paymentMethodSelect.value, 10) || 0)
          : 0;
        if (markPaid && priceValue <= 0 && markPaidInput) {
          markPaidInput.checked = false;
        }
        if (markPaid && priceValue > 0 && paymentMethodId <= 0) {
          alert('Selecciona un tipo de pago para marcarla como pagada.');
          return;
        }
        const selectedOrigin = originSelect && originSelect.value
          ? originSelect.value.trim().toLowerCase()
          : '';
        const selectedOtaId = selectedOrigin.indexOf('ota:') === 0
          ? (parseInt(selectedOrigin.substring(4), 10) || 0)
          : 0;
        const selectedSourceId = selectedOrigin.indexOf('src:') === 0
          ? (parseInt(selectedOrigin.substring(4), 10) || 0)
          : 0;
        const payload = {
          guestFirstName: guestFirstNameRaw,
          guestLastName: guestLastNameRaw,
          guestMaidenName: guestMaidenNameRaw,
          priceRaw: normalizedPriceRaw === '' ? '0' : normalizedPriceRaw,
          checkIn: checkInRaw,
          checkOut: checkOutRaw,
          sourceId: selectedSourceId > 0 ? selectedSourceId : 0,
          otaAccountId: selectedOtaId > 0 ? selectedOtaId : 0,
          notesRaw: notesInput && notesInput.value ? notesInput.value.trim() : '',
          markPaid: markPaid ? 1 : 0,
          paymentMethodId: paymentMethodId,
          paymentDate: paymentDateInput && paymentDateInput.value ? paymentDateInput.value.trim() : '',
          paymentReference: paymentReferenceInput && paymentReferenceInput.value ? paymentReferenceInput.value.trim() : ''
        };
        closeLightbox();
        if (typeof onConfirm === 'function') {
          onConfirm(payload);
        }
      });
    }

    if (guestFirstInput) {
      setTimeout(function () {
        guestFirstInput.focus();
      }, 10);
    }
  }

  function submitQuickReservation(context, payload) {
    const ctx = context || {};
    const data = payload || {};
    const checkIn = (data.checkIn || ctx.checkIn || '').toString().trim();
    const checkOut = (data.checkOut || ctx.checkOut || '').toString().trim();
    const propertyCode = (ctx.propertyCode || '').toString().trim();
    const roomCode = (ctx.roomCode || '').toString().trim();
    if (!checkIn || !checkOut || !propertyCode || !roomCode) {
      return;
    }

    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'index.php?view=calendar';

    function addInput(name, value) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    }

    addInput('calendar_action', 'quick_reservation');
    addInput('quick_property_code', propertyCode);
    addInput('quick_room_code', roomCode);
    addInput('quick_check_in', checkIn);
    addInput('quick_check_out', checkOut);
    addInput('quick_guest_first_name', data.guestFirstName || '');
    addInput('quick_guest_last_name', data.guestLastName || '');
    addInput('quick_guest_maiden_name', data.guestMaidenName || '');
    addInput('quick_price', data.priceRaw || '');
    addInput('quick_source_id', data.sourceId || 0);
    addInput('quick_source', '');
    addInput('quick_ota_account_id', data.otaAccountId || 0);
    addInput('quick_notes', data.notesRaw || '');
    addInput('quick_mark_paid', data.markPaid ? 1 : 0);
    addInput('quick_payment_method', data.paymentMethodId || 0);
    addInput('quick_payment_date', data.paymentDate || '');
    addInput('quick_payment_reference', data.paymentReference || '');
    const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
    const propertyFilterCode = currentPropertyFilter ? (currentPropertyFilter.value || '') : '';
    addInput('property_code', propertyFilterCode);

    const currentStart = document.querySelector('input[name="start_date"]');
    if (currentStart && currentStart.value) addInput('start_date', currentStart.value);
    const viewMode = document.querySelector('select[name="view_mode"]');
    if (viewMode && viewMode.value) addInput('view_mode', viewMode.value);
    const orderMode = document.querySelector('select[name="order_mode"]');
    if (orderMode && orderMode.value) addInput('order_mode', orderMode.value);
    const subtabCurrent = document.querySelector('input[name="calendar_current_subtab"]');
    if (subtabCurrent && subtabCurrent.value) addInput('calendar_current_subtab', subtabCurrent.value);
    const subtabDirty = document.querySelector('input[name="calendar_dirty_tabs"]');
    if (subtabDirty && subtabDirty.value !== undefined) addInput('calendar_dirty_tabs', subtabDirty.value);

    document.body.appendChild(form);
    form.submit();
  }

  function formatAmountInputFromCents(cents) {
    const value = Number.isFinite(cents) ? Math.max(0, cents) : 0;
    return (value / 100).toFixed(2);
  }

  function openPaymentLightbox(context, onConfirm) {
    const existing = document.querySelector('.calendar-quickres-lightbox');
    if (existing) {
      existing.remove();
    }

    const overlay = document.createElement('div');
    overlay.className = 'calendar-quickres-lightbox';
    const guestLabel = (context.guestName || '').toString().trim() || ('Reserva #' + String(context.reservationId || ''));
    const balanceLabel = (context.currency || 'MXN') === 'MXN'
      ? ('$' + Number((context.balanceCents || 0) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MXN')
      : (Number((context.balanceCents || 0) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + String(context.currency || ''));
    const paymentFoliosMap = window.pmsCalendarPaymentFoliosByReservation && typeof window.pmsCalendarPaymentFoliosByReservation === 'object'
      ? window.pmsCalendarPaymentFoliosByReservation
      : {};
    const paymentFoliosFromContext = Array.isArray(context && context.paymentFolios) ? context.paymentFolios : [];

    function formatMoneyFromCents(cents, currency) {
      const value = Number.isFinite(cents) ? Math.max(0, cents) : 0;
      const code = (currency || 'MXN').toString().trim() || 'MXN';
      if (code === 'MXN') {
        return '$' + Number(value / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MXN';
      }
      return Number(value / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + code;
    }

    function foliosForReservation(reservationId) {
      if (paymentFoliosFromContext.length) {
        const localDedup = [];
        const localSeen = {};
        paymentFoliosFromContext.forEach(function (row) {
          const folioId = parseInt(row && row.id_folio ? row.id_folio : 0, 10) || 0;
          if (folioId <= 0 || localSeen[folioId]) {
            return;
          }
          localSeen[folioId] = true;
          localDedup.push({
            id_folio: folioId,
            folio_name: row && row.folio_name ? String(row.folio_name) : ('Folio #' + String(folioId)),
            balance_cents: parseInt(row && row.balance_cents ? row.balance_cents : 0, 10) || 0,
            currency: row && row.currency ? String(row.currency) : (context.currency || 'MXN'),
            role: row && row.role ? String(row.role) : ''
          });
        });
        return localDedup;
      }
      const key = String(parseInt(reservationId || '0', 10) || 0);
      const rows = Array.isArray(paymentFoliosMap[key]) ? paymentFoliosMap[key] : [];
      const dedup = [];
      const seen = {};
      rows.forEach(function (row) {
        const folioId = parseInt(row && row.id_folio ? row.id_folio : 0, 10) || 0;
        if (folioId <= 0 || seen[folioId]) {
          return;
        }
        seen[folioId] = true;
        dedup.push({
          id_folio: folioId,
          folio_name: row && row.folio_name ? String(row.folio_name) : ('Folio #' + String(folioId)),
          balance_cents: parseInt(row && row.balance_cents ? row.balance_cents : 0, 10) || 0,
          currency: row && row.currency ? String(row.currency) : (context.currency || 'MXN'),
          role: row && row.role ? String(row.role) : ''
        });
      });
      return dedup;
    }

    overlay.innerHTML = `
      <div class="calendar-quickres-window" role="dialog" aria-modal="true" aria-label="Registrar pago">
        <div class="calendar-quickres-head">
          <h3>Registrar pago</h3>
          <button type="button" class="button-secondary calendar-quickres-close" aria-label="Cerrar">&times;</button>
        </div>
        <p class="calendar-quickres-context">
          ${(context.propertyCode || '').toString()} / ${(context.reservationCode || '').toString()} / ${guestLabel} | Balance: ${balanceLabel}
        </p>
        <form class="calendar-quickres-form" novalidate>
          <label>
            Folio
            <select name="payment_folio_id"></select>
          </label>
          <label>
            Concepto de pago
            <select name="payment_method"></select>
          </label>
          <label>
            Cantidad de pago
            <input type="number" step="0.01" min="0" name="payment_amount" inputmode="decimal" value="${formatAmountInputFromCents(context.balanceCents || 0)}">
          </label>
          <label>
            Referencia
            <input type="text" name="payment_reference" maxlength="120" placeholder="Opcional">
          </label>
          <label>
            Fecha de pago
            <input type="date" name="payment_service_date" value="${formatLocalYmd(new Date())}">
          </label>
          <div class="calendar-quickres-actions calendar-payment-actions">
            <button type="submit" class="button-primary calendar-payment-submit">Confirmar y registrar pago</button>
            <button type="button" class="button-secondary calendar-quickres-cancel calendar-quickres-cancel-danger calendar-payment-cancel">
              <span class="calendar-quickres-cancel-icon calendar-payment-cancel-icon" aria-hidden="true">X</span> Cancelar
            </button>
          </div>
        </form>
      </div>
    `;

    const bodyPrevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.body.appendChild(overlay);

    const closeButton = overlay.querySelector('.calendar-quickres-close');
    const cancelButton = overlay.querySelector('.calendar-quickres-cancel');
    const submitButton = overlay.querySelector('.calendar-payment-submit');
    const actionsRow = overlay.querySelector('.calendar-payment-actions');
    const form = overlay.querySelector('.calendar-quickres-form');
    const folioSelect = overlay.querySelector('select[name="payment_folio_id"]');
    const methodSelect = overlay.querySelector('select[name="payment_method"]');
    const amountInput = overlay.querySelector('input[name="payment_amount"]');
    const referenceInput = overlay.querySelector('input[name="payment_reference"]');
    const serviceDateInput = overlay.querySelector('input[name="payment_service_date"]');

    // Keep cancel as the rightmost action even if templates/styles are reordered.
    if (actionsRow && cancelButton && submitButton) {
      actionsRow.appendChild(cancelButton);
    }

    const paymentMap = window.pmsCalendarPaymentCatalogMap && typeof window.pmsCalendarPaymentCatalogMap === 'object'
      ? window.pmsCalendarPaymentCatalogMap
      : (window.pmsReservationPaymentCatalogMap && typeof window.pmsReservationPaymentCatalogMap === 'object'
        ? window.pmsReservationPaymentCatalogMap
        : {});
    const paymentMapByReservation = window.pmsCalendarPaymentCatalogMapByReservation
      && typeof window.pmsCalendarPaymentCatalogMapByReservation === 'object'
      ? window.pmsCalendarPaymentCatalogMapByReservation
      : (window.pmsReservationPaymentCatalogMapByReservation && typeof window.pmsReservationPaymentCatalogMapByReservation === 'object'
        ? window.pmsReservationPaymentCatalogMapByReservation
        : {});

    function normalizeCode(value) {
      return (value || '').toString().trim().toUpperCase();
    }

    function paymentRowsForProperty(propertyCode, reservationId) {
      const rid = parseInt(reservationId || 0, 10) || 0;
      if (rid > 0 && Array.isArray(paymentMapByReservation[rid])) {
        return paymentMapByReservation[rid];
      }
      const prop = normalizeCode(propertyCode);
      const rows = [];
      const seen = {};
      const globalRows = Array.isArray(paymentMap['*']) ? paymentMap['*'] : [];
      const localRows = prop && Array.isArray(paymentMap[prop]) ? paymentMap[prop] : [];
      const merged = globalRows.concat(localRows);
      merged.forEach(function (row) {
        const id = parseInt(row && row.id_payment_catalog ? row.id_payment_catalog : 0, 10) || 0;
        if (id <= 0 || seen[id]) {
          return;
        }
        seen[id] = true;
        rows.push({
          id_payment_catalog: id,
          label: (row && row.name) ? String(row.name) : ((row && row.label) ? String(row.label) : ('Concepto #' + String(id)))
        });
      });
      return rows;
    }

    function fillFolios() {
      if (!folioSelect) {
        return;
      }
      while (folioSelect.options.length) {
        folioSelect.remove(0);
      }
      const rows = foliosForReservation(context.reservationId || 0);
      if (!rows.length) {
        const fallback = document.createElement('option');
        fallback.value = '0';
        fallback.textContent = '(Sin folios abiertos)';
        folioSelect.appendChild(fallback);
        folioSelect.disabled = true;
        if (submitButton) submitButton.disabled = true;
        return;
      }
      rows.forEach(function (row) {
        const opt = document.createElement('option');
        opt.value = String(row.id_folio);
        const folioName = row.folio_name && row.folio_name.trim() !== '' ? row.folio_name : ('Folio #' + String(row.id_folio));
        opt.textContent = folioName + ' (' + formatMoneyFromCents(row.balance_cents, row.currency) + ')';
        opt.setAttribute('data-balance-cents', String(row.balance_cents || 0));
        folioSelect.appendChild(opt);
      });
      folioSelect.disabled = false;
      if (submitButton) submitButton.disabled = false;

      let preferredIndex = 0;
      const preferredFolioId = parseInt(context && context.preferredFolioId ? context.preferredFolioId : 0, 10) || 0;
      if (preferredFolioId > 0) {
        for (let i = 0; i < rows.length; i += 1) {
          if ((parseInt(rows[i].id_folio || 0, 10) || 0) === preferredFolioId) {
            preferredIndex = i;
            break;
          }
        }
      } else {
        for (let i = 0; i < rows.length; i += 1) {
          if ((rows[i].role || '').toLowerCase() === 'lodging') {
            preferredIndex = i;
            break;
          }
        }
      }
      folioSelect.selectedIndex = preferredIndex;

      if (amountInput) {
        const selectedOpt = folioSelect.options[folioSelect.selectedIndex];
        const selectedBalance = selectedOpt ? (parseInt(selectedOpt.getAttribute('data-balance-cents') || '0', 10) || 0) : 0;
        amountInput.value = formatAmountInputFromCents(selectedBalance);
      }
    }

    function fillPaymentMethods() {
      if (!methodSelect) {
        return;
      }
      while (methodSelect.options.length) {
        methodSelect.remove(0);
      }
      const rows = paymentRowsForProperty(context.propertyCode || '', context && context.reservationId ? context.reservationId : 0);
      if (!rows.length) {
        const fallback = document.createElement('option');
        fallback.value = '0';
        fallback.textContent = '(Sin conceptos configurados)';
        methodSelect.appendChild(fallback);
        methodSelect.disabled = true;
        return;
      }
      rows.forEach(function (row) {
        const opt = document.createElement('option');
        opt.value = String(row.id_payment_catalog);
        opt.textContent = row.label;
        methodSelect.appendChild(opt);
      });
      methodSelect.disabled = false;
      methodSelect.selectedIndex = 0;
    }

    function syncAmountWithSelectedFolio() {
      if (!folioSelect || !amountInput) {
        return;
      }
      const selectedOpt = folioSelect.options[folioSelect.selectedIndex];
      const selectedBalance = selectedOpt ? (parseInt(selectedOpt.getAttribute('data-balance-cents') || '0', 10) || 0) : 0;
      amountInput.value = formatAmountInputFromCents(selectedBalance);
    }

    fillFolios();
    fillPaymentMethods();
    if (folioSelect) {
      folioSelect.addEventListener('change', syncAmountWithSelectedFolio);
    }

    function closeLightbox() {
      document.removeEventListener('keydown', onEscape);
      document.body.style.overflow = bodyPrevOverflow;
      if (overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
    }

    function onEscape(event) {
      if (event.key === 'Escape') {
        closeLightbox();
      }
    }

    document.addEventListener('keydown', onEscape);

    if (closeButton) {
      closeButton.addEventListener('click', closeLightbox);
    }
    if (cancelButton) {
      cancelButton.addEventListener('click', closeLightbox);
    }
    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        closeLightbox();
      }
    });

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const folioId = folioSelect ? (parseInt(folioSelect.value || '0', 10) || 0) : 0;
        const methodId = methodSelect ? (parseInt(methodSelect.value || '0', 10) || 0) : 0;
        const amountRaw = amountInput && amountInput.value ? amountInput.value.trim() : '';
        const normalizedAmount = parseFloat((amountRaw || '0').replace(',', '.'));
        const referenceRaw = referenceInput && referenceInput.value ? referenceInput.value.trim() : '';
        const serviceDateRaw = serviceDateInput && serviceDateInput.value ? serviceDateInput.value.trim() : '';

        if (folioId <= 0) {
          alert('Selecciona un folio para aplicar el pago.');
          return;
        }
        if (methodId <= 0) {
          alert('Selecciona un concepto de pago.');
          return;
        }
        if (!Number.isFinite(normalizedAmount) || normalizedAmount <= 0) {
          alert('El monto del pago debe ser mayor a 0.');
          return;
        }

        let transferRemaining = '0';
        let transferTargetFolioId = '0';
        if (folioSelect) {
          const selectedOpt = folioSelect.options[folioSelect.selectedIndex];
          const selectedBalanceCents = selectedOpt ? (parseInt(selectedOpt.getAttribute('data-balance-cents') || '0', 10) || 0) : 0;
          const amountCents = Math.max(0, Math.round(normalizedAmount * 100));
          if (amountCents > selectedBalanceCents) {
            let fallbackTarget = null;
            for (let i = 0; i < folioSelect.options.length; i += 1) {
              const opt = folioSelect.options[i];
              const optId = parseInt(opt.value || '0', 10) || 0;
              const optBalance = parseInt(opt.getAttribute('data-balance-cents') || '0', 10) || 0;
              if (optId > 0 && optId !== folioId && optBalance > 0) {
                fallbackTarget = { id: optId, balanceCents: optBalance };
                break;
              }
            }
            if (fallbackTarget) {
              const remainingCents = amountCents - selectedBalanceCents;
              const wantTransfer = window.confirm(
                'Este pago excede el balance del folio por '
                + '$' + formatAmountInputFromCents(remainingCents)
                + '.\n\nDeseas transferir ese restante al otro folio pendiente?'
              );
              if (wantTransfer) {
                transferRemaining = '1';
                transferTargetFolioId = String(fallbackTarget.id);
              }
            }
          }
        }
        if (!confirm('Confirmar registro de pago por ' + normalizedAmount.toFixed(2) + '?')) {
          return;
        }

        const payload = {
          paymentFolioId: folioId,
          paymentTransferRemaining: transferRemaining,
          paymentTransferTargetFolioId: transferTargetFolioId,
          paymentMethodId: methodId,
          paymentAmountRaw: normalizedAmount.toFixed(2),
          paymentReference: referenceRaw,
          paymentServiceDate: serviceDateRaw
        };
        closeLightbox();
        if (typeof onConfirm === 'function') {
          onConfirm(payload);
        }
      });
    }

    if (amountInput) {
      setTimeout(function () {
        amountInput.focus();
        amountInput.select();
      }, 10);
    }
  }

  function openServiceLightbox(context, onConfirm) {
    const existing = document.querySelector('.calendar-quickres-lightbox');
    if (existing) {
      existing.remove();
    }

    const overlay = document.createElement('div');
    overlay.className = 'calendar-quickres-lightbox';
    const enableMarkPaid = !!(context && context.enableMarkPaid);
    const guestLabel = (context.guestName || '').toString().trim() || ('Reserva #' + String(context.reservationId || ''));
    overlay.innerHTML = `
      <div class="calendar-quickres-window" role="dialog" aria-modal="true" aria-label="Agregar servicio">
        <div class="calendar-quickres-head">
          <h3>Agregar servicio</h3>
          <button type="button" class="button-secondary calendar-quickres-close" aria-label="Cerrar">&times;</button>
        </div>
        <p class="calendar-quickres-context">
          ${(context.propertyCode || '').toString()} / ${(context.reservationCode || '').toString()} / ${guestLabel}
        </p>
        <form class="calendar-quickres-form" novalidate>
          <label>
            Categoria
            <select name="service_category"></select>
          </label>
          <label>
            Subcategoria
            <select name="service_subcategory"></select>
          </label>
          <label class="full">
            Concepto de servicio
            <select name="service_catalog_id"></select>
          </label>
          <label>
            Cantidad
            <input type="number" step="0.01" min="0.01" name="service_quantity" inputmode="decimal" value="1">
          </label>
          <label>
            Precio unitario
            <input type="number" step="0.01" min="0.01" name="service_unit_price" inputmode="decimal" value="0.00">
          </label>
          <label>
            Fecha de servicio
            <input type="date" name="service_date" value="${formatLocalYmd(new Date())}">
          </label>
          ${enableMarkPaid ? `
          <label class="full calendar-service-paid-toggle">
            <span class="calendar-service-paid-toggle-input">
              <input type="checkbox" name="service_mark_paid" value="1">
              Pagado
            </span>
          </label>
          <label class="full calendar-service-paid-method" style="display:none;">
            Tipo de pago
            <select name="service_payment_method"></select>
          </label>
          ` : ''}
          <label class="full">
            Descripcion
            <input type="text" name="service_description" maxlength="255" placeholder="Opcional">
          </label>
          <div class="calendar-quickres-actions calendar-payment-actions">
            <button type="submit" class="button-primary calendar-service-submit">Confirmar y agregar servicio</button>
            <button type="button" class="button-secondary calendar-quickres-cancel calendar-quickres-cancel-danger calendar-payment-cancel">
              <span class="calendar-quickres-cancel-icon calendar-payment-cancel-icon" aria-hidden="true">X</span> Cancelar
            </button>
          </div>
        </form>
      </div>
    `;

    const bodyPrevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.body.appendChild(overlay);

    const closeButton = overlay.querySelector('.calendar-quickres-close');
    const cancelButton = overlay.querySelector('.calendar-quickres-cancel');
    const submitButton = overlay.querySelector('.calendar-service-submit');
    const actionsRow = overlay.querySelector('.calendar-payment-actions');
    const form = overlay.querySelector('.calendar-quickres-form');
    const categorySelect = overlay.querySelector('select[name="service_category"]');
    const subcategorySelect = overlay.querySelector('select[name="service_subcategory"]');
    const conceptSelect = overlay.querySelector('select[name="service_catalog_id"]');
    const qtyInput = overlay.querySelector('input[name="service_quantity"]');
    const unitPriceInput = overlay.querySelector('input[name="service_unit_price"]');
    const dateInput = overlay.querySelector('input[name="service_date"]');
    const markPaidCheckbox = overlay.querySelector('input[name="service_mark_paid"]');
    const paymentMethodWrap = overlay.querySelector('.calendar-service-paid-method');
    const paymentMethodSelect = overlay.querySelector('select[name="service_payment_method"]');
    const descriptionInput = overlay.querySelector('input[name="service_description"]');

    if (actionsRow && cancelButton && submitButton) {
      actionsRow.appendChild(cancelButton);
    }

    const explicitServiceMap = context && context.serviceCatalogMap && typeof context.serviceCatalogMap === 'object'
      ? context.serviceCatalogMap
      : null;
    const globalServiceMap = window.pmsServiceCatalogMap && typeof window.pmsServiceCatalogMap === 'object'
      ? window.pmsServiceCatalogMap
      : (window.pmsCalendarServiceCatalogMap && typeof window.pmsCalendarServiceCatalogMap === 'object'
        ? window.pmsCalendarServiceCatalogMap
        : (window.pmsReservationServiceCatalogMap && typeof window.pmsReservationServiceCatalogMap === 'object'
          ? window.pmsReservationServiceCatalogMap
          : {}));
    const serviceMap = explicitServiceMap || globalServiceMap;
    const paymentMap = window.pmsCalendarPaymentCatalogMap && typeof window.pmsCalendarPaymentCatalogMap === 'object'
      ? window.pmsCalendarPaymentCatalogMap
      : (window.pmsReservationPaymentCatalogMap && typeof window.pmsReservationPaymentCatalogMap === 'object'
        ? window.pmsReservationPaymentCatalogMap
        : {});
    const paymentMapByReservation = window.pmsCalendarPaymentCatalogMapByReservation
      && typeof window.pmsCalendarPaymentCatalogMapByReservation === 'object'
      ? window.pmsCalendarPaymentCatalogMapByReservation
      : (window.pmsReservationPaymentCatalogMapByReservation && typeof window.pmsReservationPaymentCatalogMapByReservation === 'object'
        ? window.pmsReservationPaymentCatalogMapByReservation
        : {});

    function normalizeCode(value) {
      return (value || '').toString().trim().toUpperCase();
    }

    function normalizeText(value) {
      return (value || '').toString().trim();
    }

    function paymentRowsForProperty(propertyCode, reservationId) {
      const rid = parseInt(reservationId || 0, 10) || 0;
      if (rid > 0 && Array.isArray(paymentMapByReservation[rid])) {
        return paymentMapByReservation[rid];
      }
      const prop = normalizeCode(propertyCode);
      const rows = [];
      const seen = {};
      const globalRows = Array.isArray(paymentMap['*']) ? paymentMap['*'] : [];
      const localRows = prop && Array.isArray(paymentMap[prop]) ? paymentMap[prop] : [];
      const merged = globalRows.concat(localRows);
      merged.forEach(function (row) {
        const id = parseInt(row && row.id_payment_catalog ? row.id_payment_catalog : 0, 10) || 0;
        if (id <= 0 || seen[id]) {
          return;
        }
        seen[id] = true;
        rows.push({
          id_payment_catalog: id,
          label: (row && row.name) ? String(row.name) : ((row && row.label) ? String(row.label) : ('Concepto #' + String(id)))
        });
      });
      return rows;
    }

    function serviceRowsForProperty(propertyCode) {
      const prop = normalizeCode(propertyCode);
      const rows = [];
      const seen = {};
      const globalRows = Array.isArray(serviceMap['*']) ? serviceMap['*'] : [];
      const localRows = prop && Array.isArray(serviceMap[prop]) ? serviceMap[prop] : [];
      const merged = globalRows.concat(localRows);
      merged.forEach(function (row) {
        const id = parseInt(row && row.id_service_catalog ? row.id_service_catalog : 0, 10) || 0;
        if (id <= 0 || seen[id]) {
          return;
        }
        seen[id] = true;
        const defaultUnitPriceCents = parseInt(row && row.default_unit_price_cents ? row.default_unit_price_cents : 0, 10) || 0;
        const conceptName = normalizeText(row && row.name) || normalizeText(row && row.label) || ('Concepto #' + String(id));
        rows.push({
          id_service_catalog: id,
          name: conceptName,
          label: conceptName + (defaultUnitPriceCents > 0 ? (' ($' + formatAmountInputFromCents(defaultUnitPriceCents) + ')') : ''),
          category_name: normalizeText(row && row.category_name),
          subcategory_name: normalizeText(row && row.subcategory_name),
          default_unit_price_cents: defaultUnitPriceCents
        });
      });
      return rows;
    }

    const serviceRows = serviceRowsForProperty(context.propertyCode || '');

    function clearSelect(selectEl) {
      if (!selectEl) return;
      while (selectEl.options.length) {
        selectEl.remove(0);
      }
    }

    function appendOption(selectEl, value, label) {
      if (!selectEl) return;
      const opt = document.createElement('option');
      opt.value = String(value);
      opt.textContent = label;
      selectEl.appendChild(opt);
    }

    function fillCategories() {
      clearSelect(categorySelect);
      appendOption(categorySelect, '', 'Todas las categorias');
      const seen = {};
      serviceRows.forEach(function (row) {
        const key = row.category_name || '';
        if (!key || seen[key]) return;
        seen[key] = true;
        appendOption(categorySelect, key, key);
      });
      categorySelect.selectedIndex = 0;
    }

    function fillSubcategories() {
      clearSelect(subcategorySelect);
      appendOption(subcategorySelect, '', 'Todas las subcategorias');
      const selectedCategory = normalizeText(categorySelect && categorySelect.value);
      const seen = {};
      serviceRows.forEach(function (row) {
        if (selectedCategory && row.category_name !== selectedCategory) return;
        const key = row.subcategory_name || '';
        if (!key || seen[key]) return;
        seen[key] = true;
        appendOption(subcategorySelect, key, key);
      });
      subcategorySelect.selectedIndex = 0;
    }

    function filteredConceptRows() {
      const selectedCategory = normalizeText(categorySelect && categorySelect.value);
      const selectedSubcategory = normalizeText(subcategorySelect && subcategorySelect.value);
      return serviceRows.filter(function (row) {
        if (selectedCategory && row.category_name !== selectedCategory) return false;
        if (selectedSubcategory && row.subcategory_name !== selectedSubcategory) return false;
        return true;
      });
    }

    function updateUnitPriceFromConcept() {
      if (!conceptSelect || !unitPriceInput) return;
      const selectedId = parseInt(conceptSelect.value || '0', 10) || 0;
      const row = serviceRows.find(function (item) {
        return item.id_service_catalog === selectedId;
      });
      if (!row) return;
      unitPriceInput.value = formatAmountInputFromCents(row.default_unit_price_cents || 0);
    }

    function fillConcepts() {
      clearSelect(conceptSelect);
      const rows = filteredConceptRows();
      if (!rows.length) {
        appendOption(conceptSelect, '0', '(Sin conceptos configurados)');
        conceptSelect.disabled = true;
        if (submitButton) submitButton.disabled = true;
        return;
      }
      rows.forEach(function (row) {
        const opt = document.createElement('option');
        opt.value = String(row.id_service_catalog);
        opt.textContent = row.label;
        conceptSelect.appendChild(opt);
      });
      conceptSelect.disabled = false;
      if (submitButton) submitButton.disabled = false;
      conceptSelect.selectedIndex = 0;
      updateUnitPriceFromConcept();
    }

    function fillPaymentMethods() {
      if (!paymentMethodSelect) {
        return;
      }
      while (paymentMethodSelect.options.length) {
        paymentMethodSelect.remove(0);
      }
      const rows = paymentRowsForProperty(context.propertyCode || '', context && context.reservationId ? context.reservationId : 0);
      if (!rows.length) {
        const fallback = document.createElement('option');
        fallback.value = '0';
        fallback.textContent = '(Sin conceptos configurados)';
        paymentMethodSelect.appendChild(fallback);
        paymentMethodSelect.disabled = true;
        return;
      }
      rows.forEach(function (row) {
        const opt = document.createElement('option');
        opt.value = String(row.id_payment_catalog);
        opt.textContent = row.label;
        paymentMethodSelect.appendChild(opt);
      });
      paymentMethodSelect.disabled = false;
      paymentMethodSelect.selectedIndex = 0;
    }

    function updatePaidControls() {
      if (!paymentMethodWrap || !markPaidCheckbox) {
        return;
      }
      paymentMethodWrap.style.display = markPaidCheckbox.checked ? '' : 'none';
    }

    fillCategories();
    fillSubcategories();
    fillConcepts();
    fillPaymentMethods();
    updatePaidControls();

    if (categorySelect) {
      categorySelect.addEventListener('change', function () {
        fillSubcategories();
        fillConcepts();
      });
    }
    if (subcategorySelect) {
      subcategorySelect.addEventListener('change', function () {
        fillConcepts();
      });
    }
    if (conceptSelect) {
      conceptSelect.addEventListener('change', updateUnitPriceFromConcept);
    }
    if (markPaidCheckbox) {
      markPaidCheckbox.addEventListener('change', updatePaidControls);
    }

    function closeLightbox() {
      document.removeEventListener('keydown', onEscape);
      document.body.style.overflow = bodyPrevOverflow;
      if (overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
    }

    function onEscape(event) {
      if (event.key === 'Escape') {
        closeLightbox();
      }
    }

    document.addEventListener('keydown', onEscape);

    if (closeButton) {
      closeButton.addEventListener('click', closeLightbox);
    }
    if (cancelButton) {
      cancelButton.addEventListener('click', closeLightbox);
    }
    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        closeLightbox();
      }
    });

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const conceptId = conceptSelect ? (parseInt(conceptSelect.value || '0', 10) || 0) : 0;
        const qtyRaw = qtyInput && qtyInput.value ? qtyInput.value.trim() : '';
        const unitRaw = unitPriceInput && unitPriceInput.value ? unitPriceInput.value.trim() : '';
        const qtyValue = parseFloat((qtyRaw || '0').replace(',', '.'));
        const unitValue = parseFloat((unitRaw || '0').replace(',', '.'));
        const dateValue = dateInput && dateInput.value ? dateInput.value.trim() : '';
        const markPaid = !!(markPaidCheckbox && markPaidCheckbox.checked);
        const paymentMethodId = markPaid && paymentMethodSelect ? (parseInt(paymentMethodSelect.value || '0', 10) || 0) : 0;
        const description = descriptionInput && descriptionInput.value ? descriptionInput.value.trim() : '';

        if (conceptId <= 0) {
          alert('Selecciona un concepto de servicio.');
          return;
        }
        if (!Number.isFinite(qtyValue) || qtyValue <= 0) {
          alert('La cantidad debe ser mayor a 0.');
          return;
        }
        if (!Number.isFinite(unitValue) || unitValue <= 0) {
          alert('El precio unitario debe ser mayor a 0.');
          return;
        }
        if (markPaid && paymentMethodId <= 0) {
          alert('Selecciona un tipo de pago para marcarlo como pagado.');
          return;
        }
        const totalValue = qtyValue * unitValue;
        if (!confirm((markPaid ? 'Confirmar servicio y pago por ' : 'Confirmar servicio por ') + totalValue.toFixed(2) + '?')) {
          return;
        }

        const payload = {
          serviceCatalogId: conceptId,
          serviceQuantityRaw: qtyValue.toString(),
          serviceUnitPriceRaw: unitValue.toFixed(2),
          serviceDate: dateValue,
          serviceMarkPaid: markPaid ? '1' : '0',
          servicePaymentMethodId: markPaid ? String(paymentMethodId) : '0',
          serviceDescription: description
        };
        closeLightbox();
        if (typeof onConfirm === 'function') {
          onConfirm(payload);
        }
      });
    }

    if (conceptSelect && !conceptSelect.disabled) {
      setTimeout(function () {
        conceptSelect.focus();
      }, 10);
    }
  }

  // Shared service modal used by calendar and reservation detail views.
  window.pmsOpenServiceLightbox = openServiceLightbox;
  window.pmsOpenPaymentLightbox = openPaymentLightbox;

  function buildBlockChunks() {
    const map = {};
    // omite fechas que ya esten ocupadas (reservas o bloqueos)
    selectionData.forEach(function (item) {
      const room = (item.roomCode || '').toUpperCase();
      const prop = (item.propertyCode || '').toUpperCase();
      const date = item.date || '';
      if (!room || !prop || !date) return;
      const occKey = prop + '::' + room;
      const occupiedSet = occupiedDatesByRoom[occKey];
      if (occupiedSet && occupiedSet.has(date)) {
        return;
      }
      const key = occKey;
      if (!map[key]) {
        map[key] = { property: prop, room: room, dates: [] };
      }
      map[key].dates.push(date);
    });

    const chunks = [];
    Object.keys(map).forEach(function (key) {
      const bundle = map[key];
      const dates = Array.from(new Set(bundle.dates)).sort();
      if (!dates.length) {
        return;
      }
      let start = dates[0];
      let prev = dates[0];
      for (let i = 1; i < dates.length; i++) {
        const curr = dates[i];
        const prevDate = new Date(prev + 'T00:00:00');
        const currDate = new Date(curr + 'T00:00:00');
        const diff = (currDate - prevDate) / (1000 * 60 * 60 * 24);
        if (diff === 1) {
          prev = curr;
          continue;
        }
        chunks.push({
          property_code: bundle.property,
          room_code: bundle.room,
          start_date: start,
          end_date: addDays(prev, 1)
        });
        start = curr;
        prev = curr;
      }
      // end_date exclusivo
      let chunk = {
        property_code: bundle.property,
        room_code: bundle.room,
        start_date: start,
        end_date: addDays(prev, 1)
      };
      // Ajusta para evitar traslapes con bloqueos ya renderizados
      const existingBlocks = renderedBlocksByRoom[bundle.property + '::' + bundle.room] || [];
      for (let i = 0; i < existingBlocks.length && chunk; i++) {
        const blk = existingBlocks[i];
        const overlap = !(chunk.end_date <= blk.start || chunk.start_date >= blk.end);
        if (overlap) {
          // si el solape es por el inicio, mueve el inicio al fin del bloqueo
          if (chunk.start_date < blk.end && chunk.start_date >= blk.start) {
            chunk.start_date = blk.end;
          }
          // si el solape es por el final, recorta el final al inicio del bloqueo
          if (chunk.end_date > blk.start && chunk.end_date <= blk.end) {
            chunk.end_date = blk.start;
          }
          // si tras ajustar ya no hay rango, descarta
          if (chunk.start_date >= chunk.end_date) {
            chunk = null;
          }
        }
      }
      if (chunk) {
        chunks.push(chunk);
      }
    });
    return chunks;
  }

  function updateActionBar() {
    const summary = getSelectionSummary();
    if (!summary.hasSelection) {
      selectionType = null;
      selectionProperty = null;
    }
    const isCell = selectionType === 'cell';
    const isRes = selectionType === 'reservation' || selectionType === 'block';
    const onlyBlocks = (selectionType === 'block') || (isRes && summary.allBlocks);
    if (blockButton) blockButton.hidden = !(isCell && summary.hasSelection);
    if (createButton) {
      const show = isCell && summary.hasSelection && summary.sameRoom && summary.contiguous;
      createButton.hidden = !show;
      if (show) {
        const nights = summary.nights;
        createButton.textContent = 'Crear reserva para fechas (' + nights + ' noche' + (nights === 1 ? '' : 's') + ')';
      }
    }
    if (quickResButton) {
      quickResButton.hidden = !(isCell && summary.hasSelection && summary.sameRoom && summary.contiguous);
    }
    const singleReservation = !onlyBlocks && isRes && summary.reservationCount === 1;
    const target = singleReservation ? reservationData[0] : null;
    const targetStatus = target ? normalizeStatus(target.reservationStatus || '') : '';
    const hasFolio = !!(target && target.folioCount > 0);
    const canMoveReservation = singleReservation
      && target
      && !target.isBlock
      && target.reservationId > 0;
    const canAddCharges = singleReservation
      && target
      && !target.isBlock
      && target.reservationId > 0
      && hasFolio;
    const canMarkNoShow = singleReservation
      && target
      && !target.isBlock
      && target.reservationId > 0
      && targetStatus === 'confirmado'
      && isDateBeforeToday(target.checkIn || '');
    if (viewResButton) viewResButton.hidden = !singleReservation;
    if (moveResButton) {
      moveResButton.hidden = !canMoveReservation;
      moveResButton.textContent = moveModeActive ? 'Salir modo mover' : 'Mover';
    }
    if (payResButton) payResButton.hidden = !(singleReservation && hasFolio);
    if (addChargesButton) addChargesButton.hidden = !canAddCharges;
    if (noShowButton) noShowButton.hidden = !canMarkNoShow;
    if (advanceStatusButton) {
      const canAdvance = singleReservation && target && !target.isBlock && target.reservationId > 0;
      advanceStatusButton.disabled = false;
      advanceStatusButton.removeAttribute('title');
      if (canAdvance) {
        const nextStatus = getNextStatus(target.reservationStatus);
        if (!nextStatus) {
          advanceStatusButton.hidden = true;
        } else {
          advanceStatusButton.hidden = false;
          const blocksCheckoutForBalance = nextStatus === 'salida' && ((target.balanceCents || 0) > 0);
          if (targetStatus === 'apartado') {
            advanceStatusButton.textContent = 'Confirmar reserva';
          } else if (nextStatus === 'en casa') {
            advanceStatusButton.textContent = 'Check in';
          } else if (nextStatus === 'salida') {
            advanceStatusButton.textContent = 'Check out';
          } else {
            advanceStatusButton.textContent = 'Pasar a ' + nextStatus;
          }
          if (blocksCheckoutForBalance) {
            advanceStatusButton.disabled = true;
            advanceStatusButton.setAttribute('title', 'Liquida el balance pendiente para habilitar check-out');
          }
        }
      } else {
        advanceStatusButton.hidden = true;
      }
    }
    if (cancelResButton) {
      const hasBlocks = summary.blockCount > 0;
      const hasBookings = reservationData.some(function (r) { return r.reservationId > 0 && !r.isBlock; });
      cancelResButton.hidden = !(isRes && !hasBlocks && hasBookings);
      if (!cancelResButton.hidden) {
        cancelResButton.textContent = 'Cancelar reservacion' + (summary.reservationCount > 1 ? 'es' : '');
      }
    }
    if (groupResButton) groupResButton.hidden = !(!onlyBlocks && isRes && summary.reservationCount > 1);
    if (typeof deleteBlocksButton !== 'undefined' && deleteBlocksButton) {
      deleteBlocksButton.hidden = !(onlyBlocks && summary.blockCount > 0);
    }
    if (moveModeActive) {
      if (viewResButton) viewResButton.hidden = true;
      if (payResButton) payResButton.hidden = true;
      if (addChargesButton) addChargesButton.hidden = true;
      if (advanceStatusButton) advanceStatusButton.hidden = true;
      if (noShowButton) noShowButton.hidden = true;
      if (cancelResButton) cancelResButton.hidden = true;
      if (groupResButton) groupResButton.hidden = true;
      if (deleteBlocksButton) deleteBlocksButton.hidden = true;
      if (blockButton) blockButton.hidden = true;
      if (createButton) createButton.hidden = true;
      if (quickResButton) quickResButton.hidden = true;
    }
    if (mobileSelectionClearButton) {
      const showMobileClear = isMobileCalendar() && isCell && summary.hasSelection;
      mobileSelectionClearButton.hidden = !showMobileClear;
      document.body.classList.toggle('has-calendar-mobile-selection-clear', showMobileClear);
    }
    if (isMobileCalendar()) {
      const iconMap = new Map([
        [blockButton, 'block'],
        [createButton, 'create'],
        [quickResButton, 'quick'],
        [viewResButton, 'view'],
        [moveResButton, 'move'],
        [payResButton, 'pay'],
        [addChargesButton, 'charges'],
        [advanceStatusButton, 'advance'],
        [noShowButton, 'noshow'],
        [cancelResButton, 'cancel'],
        [groupResButton, 'group'],
        [deleteBlocksButton, 'delete']
      ]);
      iconMap.forEach(function (iconKey, btn) {
        if (!btn) return;
        const label = (btn.textContent || '').trim();
        if (label !== '') {
          btn.dataset.desktopLabel = label;
          btn.setAttribute('aria-label', label);
          btn.setAttribute('title', label);
        }
        btn.classList.add('calendar-action-fab');
        btn.innerHTML = '<span class="calendar-action-fab-icon" aria-hidden="true">' + getCalendarFabIconSvg(iconKey) + '</span>';
      });
      const anyVisible = [blockButton, createButton, quickResButton, viewResButton, moveResButton, payResButton, addChargesButton, advanceStatusButton, noShowButton, cancelResButton, groupResButton, deleteBlocksButton]
        .some(function (btn) { return !!btn && !btn.hidden; });
      actionBar.classList.toggle('is-visible', anyVisible);
    } else {
      [blockButton, createButton, quickResButton, viewResButton, moveResButton, payResButton, addChargesButton, advanceStatusButton, noShowButton, cancelResButton, groupResButton, deleteBlocksButton].forEach(function (btn) {
        if (!btn) return;
        btn.classList.remove('calendar-action-fab');
        if (btn.dataset.desktopLabel) {
          btn.textContent = btn.dataset.desktopLabel;
        }
      });
      actionBar.classList.remove('is-visible');
    }
  }

  document.addEventListener('click', function (event) {
    if (event.button !== 0) return;
    if (moveModeActive) {
      const moveTargetCell = event.target.closest('.calendar-cell.is-move-target');
      if (moveTargetCell && calendarTables.some(function (t) { return t.contains(moveTargetCell); }) && moveModeSource) {
        event.preventDefault();
        const targetPropertyCode = (moveTargetCell.getAttribute('data-move-property') || '').toUpperCase();
        const targetRoomCode = (moveTargetCell.getAttribute('data-move-room') || '').toUpperCase();
        const targetCheckIn = (moveTargetCell.getAttribute('data-move-start') || '').trim();
        const targetCheckOut = addDays(targetCheckIn, moveModeSource.nights);
        if (!targetPropertyCode || !targetRoomCode || !targetCheckIn || !targetCheckOut) {
          return;
        }

        const sourceLabel = (moveModeSource.propertyCode || '') + ' / ' + (moveModeSource.roomCode || '') + ' | ' + (moveModeSource.checkIn || '') + ' -> ' + (moveModeSource.checkOut || '');
        const targetLabel = targetPropertyCode + ' / ' + targetRoomCode + ' | ' + targetCheckIn + ' -> ' + targetCheckOut;
        if (!confirm('Mover reservacion #' + moveModeSource.reservationId + '?\n\nOrigen: ' + sourceLabel + '\nDestino: ' + targetLabel)) {
          return;
        }

        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'index.php?view=calendar';
        function addInput(name, value) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          input.value = value;
          form.appendChild(input);
        }
        addInput('calendar_action', 'move_reservation');
        addInput('reservation_id', moveModeSource.reservationId);
        addInput('target_property_code', targetPropertyCode);
        addInput('target_room_code', targetRoomCode);
        addInput('target_check_in', targetCheckIn);
        addInput('target_check_out', targetCheckOut);

        const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
        const propertyCode = currentPropertyFilter ? (currentPropertyFilter.value || '') : '';
        addInput('property_code', propertyCode);
        const currentStart = document.querySelector('input[name="start_date"]');
        if (currentStart && currentStart.value) addInput('start_date', currentStart.value);
        const viewMode = document.querySelector('select[name="view_mode"]');
        if (viewMode && viewMode.value) addInput('view_mode', viewMode.value);
        const orderMode = document.querySelector('select[name="order_mode"]');
        if (orderMode && orderMode.value) addInput('order_mode', orderMode.value);
        const subtabCurrent = document.querySelector('input[name="calendar_current_subtab"]');
        if (subtabCurrent && subtabCurrent.value) addInput('calendar_current_subtab', subtabCurrent.value);
        const subtabDirty = document.querySelector('input[name="calendar_dirty_tabs"]');
        if (subtabDirty && subtabDirty.value !== undefined) addInput('calendar_dirty_tabs', subtabDirty.value);

        document.body.appendChild(form);
        form.submit();
        return;
      }
      const clickedActionBar = event.target.closest('.calendar-selection-actions');
      if (!clickedActionBar) {
        event.preventDefault();
      }
      return;
    }
    if (!selectionData.length && !reservationData.length) {
      selectionType = null;
      selectionProperty = null;
    }
    const resCell = event.target.closest('.calendar-cell.reservation');
    if (resCell && calendarTables.some(function (t) { return t.contains(resCell); })) {
      event.preventDefault();
      const selType = resCell.getAttribute('data-selection-type') || 'reservation';
      const propCode = (resCell.getAttribute('data-property-code') || '').toUpperCase();
      // reject if property mismatch with current selection
      if (selectionProperty && selectionProperty !== propCode) return;
      if (selectionType && selectionType !== selType) return;
      // only attach bar when same property; otherwise ignore click entirely
      const table = resCell.closest('.calendar-table');
      if (selectionProperty && activeCalendarTable && table !== activeCalendarTable) return;
      if (!selectionProperty || selectionProperty === propCode) {
        attachActionBar(table);
      }
      toggleReservationSelection(resCell);
      return;
    }
    const cell = event.target.closest('.calendar-cell.is-empty');
    if (cell && calendarTables.some(function (t) { return t.contains(cell); })) {
      const propCode = (cell.getAttribute('data-property-code') || '').toUpperCase();
      if (selectionProperty && selectionProperty !== propCode) return;
      if (selectionType && selectionType !== 'cell') return;
      const table = cell.closest('.calendar-table');
      if (selectionProperty && activeCalendarTable && table !== activeCalendarTable) return;
      if (!selectionProperty || selectionProperty === propCode) {
        attachActionBar(table);
      }
      toggleCellSelection(cell);
    }
  });

  if (blockButton) {
    blockButton.addEventListener('click', function () {
      const chunks = buildBlockChunks();
      if (!chunks.length) {
        return;
      }
      const notes = prompt('Notas para el bloqueo:', '');
      if (notes === null) {
        return;
      }
      chunks.forEach(function (chunk) {
        chunk.notes = notes;
      });
      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'index.php?view=calendar';

      function addInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
      }

      const earliest = chunks.reduce(function (acc, item) {
        return !acc || item.start_date < acc ? item.start_date : acc;
      }, null);

      addInput('calendar_action', 'bulk_create_blocks');
      addInput('bulk_block_payload', JSON.stringify(chunks));

      const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
      const contextProperty = currentPropertyFilter ? (currentPropertyFilter.value || '') : '';
      addInput('property_code', contextProperty);
      const currentStart = document.querySelector('input[name="start_date"]');
      if (currentStart && currentStart.value) {
        addInput('start_date', currentStart.value);
      } else if (earliest) {
        addInput('start_date', earliest);
      }
      const viewMode = document.querySelector('select[name="view_mode"]');
      if (viewMode && viewMode.value) {
        addInput('view_mode', viewMode.value);
      }

      const subtabCurrent = document.querySelector('input[name="calendar_current_subtab"]');
      if (subtabCurrent && subtabCurrent.value) {
        addInput('calendar_current_subtab', subtabCurrent.value);
      }
      const subtabDirty = document.querySelector('input[name="calendar_dirty_tabs"]');
      if (subtabDirty && subtabDirty.value !== undefined) {
        addInput('calendar_dirty_tabs', subtabDirty.value);
      }

      document.body.appendChild(form);
      form.submit();
    });
  }

  if (createButton) {
    createButton.addEventListener('click', function () {
      const summary = getSelectionSummary();
      if (!summary.hasSelection || !summary.sameRoom || !summary.contiguous) {
        return;
      }
      const range = getSelectionRange();
      if (!range) {
        return;
      }
      openReservationWizardPrefill({
        propertyCode: range.propertyCode || '',
        categoryCode: range.categoryCode || '',
        roomCode: range.roomCode || '',
        checkIn: range.startDate || '',
        checkOut: range.endDateInclusive ? addDays(range.endDateInclusive, 1) : ''
      });
    });
  }

  function openReservationWizardPrefill(payload) {
    const checkIn = ((payload && payload.checkIn) || '').toString().trim();
    const checkOut = ((payload && payload.checkOut) || '').toString().trim();
    if (!checkIn || !checkOut) {
      return false;
    }
    const propertyCode = ((payload && payload.propertyCode) || '').toString().trim().toUpperCase();
    const categoryCode = ((payload && payload.categoryCode) || '').toString().trim().toUpperCase();
    const roomCode = ((payload && payload.roomCode) || '').toString().trim().toUpperCase();

    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'index.php?view=reservation_wizard';

    function addInput(name, value) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    }

    const returnContext = saveCalendarReturnState();
    addInput('wizard_step', '1');
    addInput('wizard_force_step', '1');
    if (propertyCode) {
      addInput('wizard_property', propertyCode);
    }
    if (categoryCode) {
      addInput('wizard_category', categoryCode);
    }
    if (roomCode) {
      addInput('wizard_room', roomCode);
    }
    addInput('wizard_check_in', checkIn);
    addInput('wizard_check_out', checkOut);
    appendWizardReturnContextInputs(addInput, returnContext);

    document.body.appendChild(form);
    form.submit();
    return true;
  }

  document.addEventListener('dblclick', function (event) {
    const reservationCell = event.target.closest('.calendar-cell.reservation');
    if (reservationCell && calendarTables.some(function (t) { return t.contains(reservationCell); })) {
      const reservation = reservationDataFromCell(reservationCell);
      if (reservation && !reservation.isBlock && reservation.reservationId > 0) {
        event.preventDefault();
        openReservationDetail(reservation);
      }
      return;
    }
    const cell = event.target.closest('.calendar-cell.is-empty');
    if (!cell || !calendarTables.some(function (t) { return t.contains(cell); })) {
      return;
    }
    const checkIn = (cell.getAttribute('data-date') || '').trim();
    const roomCode = (cell.getAttribute('data-room-code') || '').toUpperCase();
    if (!checkIn || !roomCode) {
      return;
    }
    let propertyCode = (cell.getAttribute('data-property-code') || '').toUpperCase();
    if (!propertyCode) {
      const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
      propertyCode = currentPropertyFilter ? (currentPropertyFilter.value || '').toUpperCase() : '';
    }
    const checkOut = addDays(checkIn, 1);
    if (!checkOut) {
      return;
    }
    event.preventDefault();
    openQuickReservationLightbox({
      propertyCode: propertyCode,
      roomCode: roomCode,
      checkIn: checkIn,
      checkOut: checkOut
    }, function (payload) {
      submitQuickReservation({
        propertyCode: propertyCode,
        roomCode: roomCode,
        checkIn: checkIn,
        checkOut: checkOut
      }, payload);
    });
  });

  const LONG_TAP_MS = 550;
  const LONG_TAP_MOVE_PX = 12;
  let reservationLongTapTimer = null;
  let reservationLongTapCell = null;
  let reservationLongTapStartX = 0;
  let reservationLongTapStartY = 0;

  function clearReservationLongTap() {
    if (reservationLongTapTimer) {
      clearTimeout(reservationLongTapTimer);
    }
    reservationLongTapTimer = null;
    reservationLongTapCell = null;
    reservationLongTapStartX = 0;
    reservationLongTapStartY = 0;
  }

  document.addEventListener('touchstart', function (event) {
    const touch = event.touches && event.touches[0] ? event.touches[0] : null;
    if (!touch) {
      clearReservationLongTap();
      return;
    }
    const cell = event.target.closest('.calendar-cell.reservation');
    if (!cell || !calendarTables.some(function (t) { return t.contains(cell); })) {
      clearReservationLongTap();
      return;
    }
    const reservation = reservationDataFromCell(cell);
    if (!reservation || reservation.isBlock || reservation.reservationId <= 0) {
      clearReservationLongTap();
      return;
    }

    clearReservationLongTap();
    reservationLongTapCell = cell;
    reservationLongTapStartX = touch.clientX;
    reservationLongTapStartY = touch.clientY;
    reservationLongTapTimer = setTimeout(function () {
      if (!reservationLongTapCell) {
        return;
      }
      const longTapReservation = reservationDataFromCell(reservationLongTapCell);
      clearReservationLongTap();
      if (!longTapReservation || longTapReservation.isBlock || longTapReservation.reservationId <= 0) {
        return;
      }
      openReservationDetail(longTapReservation);
    }, LONG_TAP_MS);
  }, { passive: true });

  document.addEventListener('touchmove', function (event) {
    if (!reservationLongTapTimer) {
      return;
    }
    const touch = event.touches && event.touches[0] ? event.touches[0] : null;
    if (!touch) {
      clearReservationLongTap();
      return;
    }
    const movedX = Math.abs(touch.clientX - reservationLongTapStartX);
    const movedY = Math.abs(touch.clientY - reservationLongTapStartY);
    if (movedX > LONG_TAP_MOVE_PX || movedY > LONG_TAP_MOVE_PX) {
      clearReservationLongTap();
    }
  }, { passive: true });

  document.addEventListener('touchend', clearReservationLongTap, { passive: true });
  document.addEventListener('touchcancel', clearReservationLongTap, { passive: true });

  if (quickResButton) {
    quickResButton.addEventListener('click', function () {
      const summary = getSelectionSummary();
      if (!summary.hasSelection || !summary.sameRoom || !summary.contiguous) {
        return;
      }
      const range = getSelectionRange();
      if (!range || !range.startDate || !range.endDateInclusive) {
        return;
      }
      openQuickReservationLightbox(
        {
          propertyCode: range.propertyCode || '',
          roomCode: range.roomCode || '',
          checkIn: range.startDate,
          checkOut: addDays(range.endDateInclusive, 1)
        },
        function (payload) {
          submitQuickReservation(
            {
              propertyCode: range.propertyCode || '',
              roomCode: range.roomCode || '',
              checkIn: range.startDate,
              checkOut: addDays(range.endDateInclusive, 1)
            },
            payload
          );
        }
      );
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      clearSelection();
    }
  });

  function addReservationsReturnContext(addInput) {
    if (typeof addInput !== 'function') return;
    addInput('reservations_filter_return_view', 'calendar');

    const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
    addInput('reservations_filter_return_property_code', currentPropertyFilter ? (currentPropertyFilter.value || '') : '');

    const currentStart = document.querySelector('input[name="start_date"]');
    if (currentStart && currentStart.value) addInput('reservations_filter_return_start_date', currentStart.value);

    const viewMode = document.querySelector('select[name="view_mode"]');
    if (viewMode && viewMode.value) addInput('reservations_filter_return_view_mode', viewMode.value);

    const orderMode = document.querySelector('select[name="order_mode"]');
    if (orderMode && orderMode.value) addInput('reservations_filter_return_order_mode', orderMode.value);
  }

  function reservationDataFromCell(cell) {
    if (!cell) return null;
    return {
      reservationId: parseInt(cell.getAttribute('data-reservation-id') || '0', 10),
      reservationCode: cell.getAttribute('data-reservation-code') || '',
      reservationStatus: cell.getAttribute('data-reservation-status') || '',
      folioCount: parseInt(cell.getAttribute('data-reservation-folio-count') || '0', 10),
      balanceCents: parseInt(cell.getAttribute('data-reservation-balance-cents') || '0', 10),
      currency: cell.getAttribute('data-reservation-currency') || 'MXN',
      roomCode: cell.getAttribute('data-room-code') || '',
      propertyCode: cell.getAttribute('data-property-code') || '',
      guestName: cell.getAttribute('data-guest-name') || '',
      checkIn: cell.getAttribute('data-check-in') || '',
      checkOut: cell.getAttribute('data-check-out') || '',
      blockId: parseInt(cell.getAttribute('data-block-id') || '0', 10),
      isBlock: cell.classList.contains('is-block')
    };
  }

  function openReservationDetail(reservation) {
    const res = reservation || null;
    if (!res || res.isBlock || !res.reservationId) return false;
    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'index.php?view=reservations';
    function addInput(name, value) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    }
    addInput('reservations_subtab_action', 'open');
    addInput('reservations_subtab_target', 'reservation:' + res.reservationId);
    if (res.propertyCode) addInput('reservations_filter_property', res.propertyCode);
    addReservationsReturnContext(addInput);
    document.body.appendChild(form);
    form.submit();
    return true;
  }

  if (viewResButton) {
    viewResButton.addEventListener('click', function () {
      if (selectionType !== 'reservation' || reservationData.length !== 1) return;
      openReservationDetail(reservationData[0]);
    });
  }

  if (moveResButton) {
    moveResButton.addEventListener('click', function () {
      if (moveModeActive) {
        exitMoveMode();
        return;
      }
      if (selectionType !== 'reservation' || reservationData.length !== 1) return;
      const res = reservationData[0];
      if (!res || res.isBlock || !res.reservationId) return;
      enterMoveMode(res);
    });
  }

  if (payResButton) {
    payResButton.addEventListener('click', function () {
      if (selectionType !== 'reservation' || reservationData.length !== 1) return;
      const res = reservationData[0];
      if (res.isBlock || !res.reservationId || res.folioCount <= 0) return;

      openPaymentLightbox({
        reservationId: res.reservationId,
        reservationCode: res.reservationCode || '',
        propertyCode: res.propertyCode || '',
        guestName: res.guestName || '',
        balanceCents: Number.isFinite(res.balanceCents) ? res.balanceCents : 0,
        currency: res.currency || 'MXN'
      }, function (payload) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'index.php?view=calendar';
        function addInput(name, value) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          input.value = value;
          form.appendChild(input);
        }

        addInput('calendar_action', 'create_reservation_payment');
        addInput('reservation_id', res.reservationId);
        addInput('payment_folio_id', payload.paymentFolioId || '0');
        addInput('payment_transfer_remaining', payload.paymentTransferRemaining || '0');
        addInput('payment_transfer_target_folio_id', payload.paymentTransferTargetFolioId || '0');
        addInput('payment_method', payload.paymentMethodId);
        addInput('payment_amount', payload.paymentAmountRaw);
        addInput('payment_reference', payload.paymentReference || '');
        addInput('payment_service_date', payload.paymentServiceDate || '');

        const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
        const propertyCode = currentPropertyFilter ? (currentPropertyFilter.value || '') : '';
        addInput('property_code', propertyCode);
        const currentStart = document.querySelector('input[name="start_date"]');
        if (currentStart && currentStart.value) addInput('start_date', currentStart.value);
        const viewMode = document.querySelector('select[name="view_mode"]');
        if (viewMode && viewMode.value) addInput('view_mode', viewMode.value);
        const orderMode = document.querySelector('select[name="order_mode"]');
        if (orderMode && orderMode.value) addInput('order_mode', orderMode.value);
        const subtabCurrent = document.querySelector('input[name="calendar_current_subtab"]');
        if (subtabCurrent && subtabCurrent.value) addInput('calendar_current_subtab', subtabCurrent.value);
        const subtabDirty = document.querySelector('input[name="calendar_dirty_tabs"]');
        if (subtabDirty && subtabDirty.value !== undefined) addInput('calendar_dirty_tabs', subtabDirty.value);

        document.body.appendChild(form);
        form.submit();
      });
    });
  }

  if (addChargesButton) {
    addChargesButton.addEventListener('click', function () {
      if (selectionType !== 'reservation' || reservationData.length !== 1) return;
      const res = reservationData[0];
      if (res.isBlock || !res.reservationId) return;
      if (res.folioCount <= 0) {
        return;
      }

      openServiceLightbox({
        reservationId: res.reservationId,
        reservationCode: res.reservationCode || '',
        propertyCode: res.propertyCode || '',
        guestName: res.guestName || '',
        enableMarkPaid: true
      }, function (payload) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'index.php?view=calendar';
        function addInput(name, value) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          input.value = value;
          form.appendChild(input);
        }

        addInput('calendar_action', 'create_reservation_service');
        addInput('reservation_id', res.reservationId);
        addInput('service_catalog_id', payload.serviceCatalogId);
        addInput('service_quantity', payload.serviceQuantityRaw);
        addInput('service_unit_price', payload.serviceUnitPriceRaw);
        addInput('service_date', payload.serviceDate || '');
        addInput('service_mark_paid', payload.serviceMarkPaid || '0');
        addInput('service_payment_method', payload.servicePaymentMethodId || '0');
        addInput('service_description', payload.serviceDescription || '');

        const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
        const propertyCode = currentPropertyFilter ? (currentPropertyFilter.value || '') : '';
        addInput('property_code', propertyCode);
        const currentStart = document.querySelector('input[name="start_date"]');
        if (currentStart && currentStart.value) addInput('start_date', currentStart.value);
        const viewMode = document.querySelector('select[name="view_mode"]');
        if (viewMode && viewMode.value) addInput('view_mode', viewMode.value);
        const orderMode = document.querySelector('select[name="order_mode"]');
        if (orderMode && orderMode.value) addInput('order_mode', orderMode.value);
        const subtabCurrent = document.querySelector('input[name="calendar_current_subtab"]');
        if (subtabCurrent && subtabCurrent.value) addInput('calendar_current_subtab', subtabCurrent.value);
        const subtabDirty = document.querySelector('input[name="calendar_dirty_tabs"]');
        if (subtabDirty && subtabDirty.value !== undefined) addInput('calendar_dirty_tabs', subtabDirty.value);

        document.body.appendChild(form);
        form.submit();
      });
    });
  }

  if (advanceStatusButton) {
    advanceStatusButton.addEventListener('click', function () {
      if (selectionType !== 'reservation' || reservationData.length !== 1) return;
      const res = reservationData[0];
      if (res.isBlock || !res.reservationId) return;
      const normalizedStatus = normalizeStatus(res.reservationStatus || '');
      const nextStatus = getNextStatus(normalizedStatus);
      if (nextStatus === 'salida' && (res.balanceCents || 0) > 0) {
        alert('No se puede pasar a check-out mientras la reservacion tenga balance pendiente.');
        return;
      }

      const form = document.createElement('form');
      form.method = 'post';
      form.action = normalizedStatus === 'apartado'
        ? 'index.php?view=reservation_wizard'
        : 'index.php?view=calendar';
      function addInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
      }

      if (normalizedStatus === 'apartado') {
        const returnContext = saveCalendarReturnState();
        const hasGuestAssigned = !!((res.guestName || '').trim());
        const shouldOpenReplaceLodging = hasGuestAssigned && ((res.folioCount || 0) <= 0);
        addInput('wizard_reservation_id', res.reservationId);
        addInput('wizard_step', shouldOpenReplaceLodging ? '2' : '1');
        addInput('wizard_force_step', shouldOpenReplaceLodging ? '2' : '1');
        if (shouldOpenReplaceLodging) {
          addInput('wizard_replace_lodging', '1');
          addInput('wizard_replace_folio_id', '0');
        }
        appendWizardReturnContextInputs(addInput, returnContext);
      } else {
        addInput('calendar_action', 'advance_reservation_status');
        addInput('reservation_id', res.reservationId);
        addInput('reservation_status', res.reservationStatus || '');
        const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
        const propertyCode = currentPropertyFilter ? (currentPropertyFilter.value || '') : '';
        addInput('property_code', propertyCode);
      }

      if (normalizedStatus !== 'apartado') {
        const currentStart = document.querySelector('input[name="start_date"]');
        if (currentStart && currentStart.value) addInput('start_date', currentStart.value);
        const viewMode = document.querySelector('select[name="view_mode"]');
        if (viewMode && viewMode.value) addInput('view_mode', viewMode.value);
        const subtabCurrent = document.querySelector('input[name="calendar_current_subtab"]');
        if (subtabCurrent && subtabCurrent.value) addInput('calendar_current_subtab', subtabCurrent.value);
        const subtabDirty = document.querySelector('input[name="calendar_dirty_tabs"]');
        if (subtabDirty && subtabDirty.value !== undefined) addInput('calendar_dirty_tabs', subtabDirty.value);
      }

      document.body.appendChild(form);
      form.submit();
    });
  }

  if (noShowButton) {
    noShowButton.addEventListener('click', function () {
      if (selectionType !== 'reservation' || reservationData.length !== 1) return;
      const res = reservationData[0];
      if (res.isBlock || !res.reservationId) return;
      const normalizedStatus = normalizeStatus(res.reservationStatus || '');
      if (normalizedStatus !== 'confirmado' || !isDateBeforeToday(res.checkIn || '')) return;

      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'index.php?view=calendar';
      function addInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
      }

      addInput('calendar_action', 'mark_reservation_no_show');
      addInput('reservation_id', res.reservationId);
      addInput('reservation_status', res.reservationStatus || '');
      addInput('reservation_check_in', res.checkIn || '');
      const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
      const propertyCode = currentPropertyFilter ? (currentPropertyFilter.value || '') : '';
      addInput('property_code', propertyCode);

      const currentStart = document.querySelector('input[name="start_date"]');
      if (currentStart && currentStart.value) addInput('start_date', currentStart.value);
      const viewMode = document.querySelector('select[name="view_mode"]');
      if (viewMode && viewMode.value) addInput('view_mode', viewMode.value);
      const subtabCurrent = document.querySelector('input[name="calendar_current_subtab"]');
      if (subtabCurrent && subtabCurrent.value) addInput('calendar_current_subtab', subtabCurrent.value);
      const subtabDirty = document.querySelector('input[name="calendar_dirty_tabs"]');
      if (subtabDirty && subtabDirty.value !== undefined) addInput('calendar_dirty_tabs', subtabDirty.value);

      document.body.appendChild(form);
      form.submit();
    });
  }

  if (groupResButton) {
    groupResButton.addEventListener('click', function () {
      alert('Agregar a grupo (pendiente).');
    });
  }

  if (cancelResButton) {
    cancelResButton.addEventListener('click', function () {
      if (selectionType !== 'reservation') return;
      const reservations = reservationData
        .filter(function (r) { return r.reservationId > 0 && !r.isBlock; })
        .map(function (r) { return r.reservationId; });
      if (!reservations.length) return;
      if (!confirm('Cancelar reservaciones seleccionadas?')) return;
      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'index.php?view=calendar';
      function addInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
      }
      addInput('calendar_action', 'cancel_reservations');
      addInput('reservation_ids', JSON.stringify(reservations));
      const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
      const propertyCode = currentPropertyFilter ? (currentPropertyFilter.value || '') : '';
      addInput('property_code', propertyCode);
      const currentStart = document.querySelector('input[name="start_date"]');
      if (currentStart && currentStart.value) addInput('start_date', currentStart.value);
      const viewMode = document.querySelector('select[name="view_mode"]');
      if (viewMode && viewMode.value) addInput('view_mode', viewMode.value);
      const orderMode = document.querySelector('select[name="order_mode"]');
      if (orderMode && orderMode.value) addInput('order_mode', orderMode.value);
      const subtabCurrent = document.querySelector('input[name="calendar_current_subtab"]');
      if (subtabCurrent && subtabCurrent.value) addInput('calendar_current_subtab', subtabCurrent.value);
      const subtabDirty = document.querySelector('input[name="calendar_dirty_tabs"]');
      if (subtabDirty && subtabDirty.value !== undefined) addInput('calendar_dirty_tabs', subtabDirty.value);
      document.body.appendChild(form);
      form.submit();
    });
  }


  if (deleteBlocksButton) {
    deleteBlocksButton.addEventListener('click', function () {
      if (selectionType !== 'block') return;
      const blocks = reservationData
        .map(function (r) { return r.blockId || 0; })
        .filter(function (id) { return id > 0; });
      if (!blocks.length) return;
      if (!confirm('Eliminar bloqueos seleccionados?')) return;
      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'index.php?view=calendar';
      function addInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
      }
      addInput('calendar_action', 'bulk_delete_blocks');
      addInput('bulk_delete_block_ids', JSON.stringify(blocks));
      const currentPropertyFilter = document.querySelector('.calendar-filters select[name="property_code"]');
      const propertyCode = currentPropertyFilter ? (currentPropertyFilter.value || '') : '';
      addInput('property_code', propertyCode);
      const currentStart = document.querySelector('input[name="start_date"]');
      if (currentStart && currentStart.value) addInput('start_date', currentStart.value);
      const viewMode = document.querySelector('select[name="view_mode"]');
      if (viewMode && viewMode.value) addInput('view_mode', viewMode.value);
      const orderMode = document.querySelector('select[name="order_mode"]');
      if (orderMode && orderMode.value) addInput('order_mode', orderMode.value);
      document.body.appendChild(form);
      form.submit();
    });
  }

  document.addEventListener('click', function (event) {
    const priceCell = event.target.closest('.js-rateplan-price');
    if (!priceCell) {
      return;
    }
    const propertyCode = priceCell.getAttribute('data-property-code') || '';
    const rateplanCode = priceCell.getAttribute('data-rateplan-code') || '';
    const categoryId = priceCell.getAttribute('data-category-id') || '';
    const dateKey = priceCell.getAttribute('data-date') || '';
    const currentPrice = priceCell.getAttribute('data-price') || '';
    if (!rateplanCode || !dateKey) {
      return;
    }
    const priceValue = prompt('Precio fijo para ' + dateKey + ':', currentPrice);
    if (priceValue === null) {
      return;
    }
    const normalized = priceValue.trim();
    if (normalized === '') {
      return;
    }
    const notes = prompt('Notas (opcional):', '');
    if (notes === null) {
      return;
    }
    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'index.php?view=calendar';
    function addInput(name, value) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    }
    addInput('calendar_action', 'rateplan_override_quick');
    addInput('rateplan_property_code', propertyCode);
    addInput('rateplan_code', rateplanCode);
    addInput('override_category_id', categoryId);
    addInput('override_date', dateKey);
    addInput('override_price', normalized);
    addInput('override_notes', notes);
    addInput('override_is_active', '1');
    if (propertyCode) {
      addInput('property_code', propertyCode);
    }
    const currentStart = document.querySelector('input[name="start_date"]');
    if (currentStart && currentStart.value) addInput('start_date', currentStart.value);
    const viewMode = document.querySelector('select[name="view_mode"]');
    if (viewMode && viewMode.value) addInput('view_mode', viewMode.value);
    const orderMode = document.querySelector('select[name="order_mode"]');
    if (orderMode && orderMode.value) addInput('order_mode', orderMode.value);
    document.body.appendChild(form);
    form.submit();
  });

  /* Concept selector -> rellena campos ocultos y precio */
  document.addEventListener('change', function (ev) {
    const select = ev.target;
    if (!select.classList || !select.classList.contains('concept-select')) return;
    const targetId = select.getAttribute('data-target');
    const option = select.selectedOptions && select.selectedOptions[0] ? select.selectedOptions[0] : null;
    if (!option || !targetId) return;
    const price = option.getAttribute('data-price') || '0';
    const priceField = document.getElementById('sale-price-' + targetId);
    const catalogField = document.getElementById('sale-catalog-' + targetId);
    if (priceField) priceField.value = (parseInt(price, 10) || 0) / 100;
    if (catalogField) catalogField.value = option.value;
  });

  /* Toggle panel con data-toggle */
  document.addEventListener('click', function (ev) {
    const btn = ev.target.closest('.js-folio-toggle');
    if (btn) {
      const targetId = btn.getAttribute('data-folio-target');
      if (!targetId) return;
      const target = document.getElementById(targetId);
      if (!target) return;
      target.style.display = (target.style.display === 'none' || target.style.display === '') ? 'block' : 'none';
      return;
    }
    const toggleBtn = ev.target.closest('[data-toggle]');
    if (toggleBtn && toggleBtn.dataset.toggle) {
      const target = document.getElementById(toggleBtn.dataset.toggle);
      if (!target) return;
      target.style.display = (target.style.display === 'none' || target.style.display === '') ? 'block' : 'none';
    }
  });
});
