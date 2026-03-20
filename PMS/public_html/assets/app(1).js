document.addEventListener('DOMContentLoaded', function () {
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
  const calendarTable = document.querySelector('.calendar-table');
  if (!calendarTable) {
    return;
  }

  const scrollContainer = calendarTable.closest('.calendar-scroll');
  const actionBar = document.createElement('div');
  actionBar.className = 'calendar-selection-actions';
  actionBar.innerHTML = `
    <button type="button" class="calendar-action-btn js-calendar-block" hidden>Bloquear fechas seleccionadas</button>
    <button type="button" class="calendar-action-btn js-calendar-create" hidden>Crear reserva para fechas</button>
  `;
  const blockButton = actionBar.querySelector('.js-calendar-block');
  const createButton = actionBar.querySelector('.js-calendar-create');
  if (scrollContainer && scrollContainer.parentElement) {
    scrollContainer.parentElement.insertBefore(actionBar, scrollContainer.nextSibling);
  } else {
    calendarTable.insertAdjacentElement('afterend', actionBar);
  }

  let selectionCells = [];
  let selectionData = [];
  window.pmsCalendarSelection = selectionData;

  function clearSelection() {
    calendarTable.querySelectorAll('.calendar-cell.is-range-selected').forEach(function (cell) {
      cell.classList.remove('is-range-selected');
    });
    selectionCells = [];
    selectionData = [];
    window.pmsCalendarSelection = selectionData;
    updateActionBar();
  }

  function getCellData(cell) {
    return {
      dayIndex: parseInt(cell.getAttribute('data-day-index') || '-1', 10),
      date: cell.getAttribute('data-date') || '',
      roomCode: cell.getAttribute('data-room-code') || '',
      roomName: cell.getAttribute('data-room-name') || '',
      propertyCode: cell.getAttribute('data-property-code') || ''
    };
  }

  function toggleCellSelection(cell) {
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
    updateActionBar();
  }

  function getSelectionSummary() {
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
    return {
      startDate: startDate,
      endDateInclusive: endDateInclusive,
      roomCode: roomCode,
      propertyCode: propertyCode
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

  function updateActionBar() {
    const summary = getSelectionSummary();
    if (blockButton) {
      blockButton.hidden = !summary.hasSelection;
    }
    if (createButton) {
      createButton.hidden = !(summary.hasSelection && summary.sameRoom && summary.contiguous);
      if (!createButton.hidden) {
        const nights = summary.nights;
        createButton.textContent = 'Crear reserva para fechas (' + nights + ' noche' + (nights === 1 ? '' : 's') + ')';
      }
    }
  }

  calendarTable.addEventListener('click', function (event) {
    if (event.button !== 0) {
      return;
    }
    const cell = event.target.closest('.calendar-cell.is-empty');
    if (!cell) {
      return;
    }
    toggleCellSelection(cell);
  });

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

      addInput('reservations_current_subtab', 'static:new');
      if (range.propertyCode) {
        addInput('create_property_code', range.propertyCode);
        addInput('reservations_filter_property', range.propertyCode);
      }
      if (range.roomCode) {
        addInput('create_room_code', range.roomCode);
      }
      if (range.startDate) {
        addInput('create_check_in', range.startDate);
      }
      if (range.endDateInclusive) {
        addInput('create_check_out', addDays(range.endDateInclusive, 1));
      }

      document.body.appendChild(form);
      form.submit();
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      clearSelection();
    }
  });
});
