(function () {
  function pad2(value) {
    return value < 10 ? '0' + value : String(value);
  }

  function parseIsoDate(value) {
    var text = String(value || '').slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) {
      return null;
    }
    var parts = text.split('-');
    var year = Number(parts[0]);
    var month = Number(parts[1]) - 1;
    var day = Number(parts[2]);
    var date = new Date(year, month, day);
    if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) {
      return null;
    }
    date.setHours(0, 0, 0, 0);
    return date;
  }

  function formatIsoDate(date) {
    if (!(date instanceof Date)) {
      return '';
    }
    return [
      date.getFullYear(),
      pad2(date.getMonth() + 1),
      pad2(date.getDate())
    ].join('-');
  }

  function formatDisplayDate(date) {
    if (!(date instanceof Date)) {
      return '';
    }
    return [
      pad2(date.getDate()),
      pad2(date.getMonth() + 1),
      date.getFullYear()
    ].join('/');
  }

  function monthStart(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
  }

  function addMonths(date, amount) {
    return new Date(date.getFullYear(), date.getMonth() + amount, 1);
  }

  function sameDay(a, b) {
    return a && b &&
      a.getFullYear() === b.getFullYear() &&
      a.getMonth() === b.getMonth() &&
      a.getDate() === b.getDate();
  }

  function compareDate(a, b) {
    return a.getTime() - b.getTime();
  }

  function inRange(date, start, end) {
    if (!date || !start || !end) {
      return false;
    }
    return compareDate(date, start) >= 0 && compareDate(date, end) <= 0;
  }

  var monthNames = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ];
  var weekdayNames = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];

  function PmsDateRangePicker(root) {
    this.root = root;
    this.trigger = root.querySelector('[data-pms-date-range-trigger]');
    this.startInput = root.querySelector('[data-pms-date-range-start]');
    this.endInput = root.querySelector('[data-pms-date-range-end]');
    this.form = root.closest('form');
    this.submitForm = root.getAttribute('data-submit-form') === '1';
    this.displayStart = root.querySelector('[data-pms-date-range-display-start]');
    this.displayEnd = root.querySelector('[data-pms-date-range-display-end]');
    this.currentStart = parseIsoDate(this.startInput ? this.startInput.value : '') || new Date();
    this.currentEnd = parseIsoDate(this.endInput ? this.endInput.value : '') || this.currentStart;
    if (compareDate(this.currentEnd, this.currentStart) < 0) {
      this.currentEnd = this.currentStart;
    }
    this.draftStart = this.currentStart;
    this.draftEnd = this.currentEnd;
    this.viewMonth = monthStart(this.currentStart);
    this.isPickingEnd = false;
    this.backdrop = null;
    this.leftCalendar = null;
    this.rightCalendar = null;
    this.summaryStart = null;
    this.summaryEnd = null;
    this.build();
    this.bind();
    this.syncTrigger();
  }

  PmsDateRangePicker.prototype.build = function () {
    var backdrop = document.createElement('div');
    backdrop.className = 'pms-date-range-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    backdrop.innerHTML =
      '<div class="pms-date-range-dialog" role="dialog" aria-modal="true">' +
        '<div class="pms-date-range-dialog-header"><strong>Selecciona rango de fechas</strong></div>' +
        '<div class="pms-date-range-dialog-body">' +
          '<div class="pms-date-range-summary">' +
            '<div class="pms-date-range-summary-block">' +
              '<span class="pms-date-range-summary-label">Desde</span>' +
              '<strong class="pms-date-range-summary-value" data-summary-start></strong>' +
            '</div>' +
            '<div class="pms-date-range-summary-block">' +
              '<span class="pms-date-range-summary-label">Hasta</span>' +
              '<strong class="pms-date-range-summary-value" data-summary-end></strong>' +
            '</div>' +
          '</div>' +
          '<div class="pms-date-range-calendars">' +
            '<div class="pms-date-range-calendar" data-calendar-index="0"></div>' +
            '<div class="pms-date-range-calendar" data-calendar-index="1"></div>' +
          '</div>' +
        '</div>' +
        '<div class="pms-date-range-dialog-footer">' +
          '<span class="pms-date-range-note">Si eliges el mismo dia en ambos extremos, se buscara solo ese dia.</span>' +
          '<div class="pms-date-range-actions">' +
            '<button type="button" data-action="cancel">Cancelar</button>' +
            '<button type="button" data-action="apply">Aplicar</button>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(backdrop);
    this.backdrop = backdrop;
    this.leftCalendar = backdrop.querySelector('[data-calendar-index="0"]');
    this.rightCalendar = backdrop.querySelector('[data-calendar-index="1"]');
    this.summaryStart = backdrop.querySelector('[data-summary-start]');
    this.summaryEnd = backdrop.querySelector('[data-summary-end]');
    this.render();
  };

  PmsDateRangePicker.prototype.bind = function () {
    var self = this;
    if (this.trigger) {
      this.trigger.addEventListener('click', function () {
        self.open();
      });
    }
    if (this.backdrop) {
      this.backdrop.addEventListener('click', function (event) {
        if (event.target === self.backdrop) {
          self.close();
          return;
        }
        var actionButton = event.target.closest('[data-action]');
        if (!actionButton) {
          return;
        }
        var action = actionButton.getAttribute('data-action');
        if (action === 'cancel') {
          self.close();
        } else if (action === 'apply') {
          self.apply();
        }
      });
    }
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && self.backdrop && self.backdrop.classList.contains('is-open')) {
        self.close();
      }
    });
  };

  PmsDateRangePicker.prototype.open = function () {
    this.draftStart = parseIsoDate(this.startInput ? this.startInput.value : '') || this.currentStart || new Date();
    this.draftEnd = parseIsoDate(this.endInput ? this.endInput.value : '') || this.currentEnd || this.draftStart;
    if (compareDate(this.draftEnd, this.draftStart) < 0) {
      this.draftEnd = this.draftStart;
    }
    this.viewMonth = monthStart(this.draftStart);
    this.isPickingEnd = false;
    this.render();
    this.backdrop.classList.add('is-open');
    this.backdrop.setAttribute('aria-hidden', 'false');
  };

  PmsDateRangePicker.prototype.close = function () {
    if (!this.backdrop) {
      return;
    }
    this.backdrop.classList.remove('is-open');
    this.backdrop.setAttribute('aria-hidden', 'true');
  };

  PmsDateRangePicker.prototype.apply = function () {
    this.currentStart = this.draftStart;
    this.currentEnd = this.draftEnd;
    if (this.startInput) {
      this.startInput.value = formatIsoDate(this.currentStart);
    }
    if (this.endInput) {
      this.endInput.value = formatIsoDate(this.currentEnd);
    }
    this.syncTrigger();
    this.close();
    if (this.submitForm && this.form) {
      this.form.submit();
    } else {
      this.root.dispatchEvent(new CustomEvent('pms:date-range-change', {
        bubbles: true,
        detail: {
          start: formatIsoDate(this.currentStart),
          end: formatIsoDate(this.currentEnd)
        }
      }));
    }
  };

  PmsDateRangePicker.prototype.syncTrigger = function () {
    var startDate = parseIsoDate(this.startInput ? this.startInput.value : '') || this.currentStart;
    var endDate = parseIsoDate(this.endInput ? this.endInput.value : '') || this.currentEnd;
    if (this.displayStart) {
      this.displayStart.textContent = formatDisplayDate(startDate);
    }
    if (this.displayEnd) {
      this.displayEnd.textContent = formatDisplayDate(endDate);
    }
  };

  PmsDateRangePicker.prototype.render = function () {
    if (this.summaryStart) {
      this.summaryStart.textContent = formatDisplayDate(this.draftStart);
    }
    if (this.summaryEnd) {
      this.summaryEnd.textContent = formatDisplayDate(this.draftEnd);
    }
    this.renderCalendar(this.leftCalendar, this.viewMonth, true);
    this.renderCalendar(this.rightCalendar, addMonths(this.viewMonth, 1), false);
  };

  PmsDateRangePicker.prototype.renderCalendar = function (container, monthDate, allowPrev) {
    var self = this;
    if (!container) {
      return;
    }
    var year = monthDate.getFullYear();
    var month = monthDate.getMonth();
    var firstDay = new Date(year, month, 1);
    var startWeekday = (firstDay.getDay() + 6) % 7;
    var gridStart = new Date(year, month, 1 - startWeekday);
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    var daysHtml = '';
    for (var i = 0; i < 42; i += 1) {
      var current = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + i);
      var classes = ['pms-date-range-day'];
      if (current.getMonth() !== month) {
        classes.push('is-outside');
      }
      if (sameDay(current, today)) {
        classes.push('is-today');
      }
      if (inRange(current, this.draftStart, this.draftEnd)) {
        classes.push('is-in-range');
      }
      if (sameDay(current, this.draftStart)) {
        classes.push('is-range-start');
      }
      if (sameDay(current, this.draftEnd)) {
        classes.push('is-range-end');
      }
      daysHtml += '<button type="button" class="' + classes.join(' ') + '" data-date="' + formatIsoDate(current) + '">' + current.getDate() + '</button>';
    }
    var weekdaysHtml = weekdayNames.map(function (name) {
      return '<div class="pms-date-range-weekday">' + name + '</div>';
    }).join('');
    container.innerHTML =
      '<div class="pms-date-range-calendar-header">' +
        '<button type="button" class="pms-date-range-calendar-nav" data-nav="' + (allowPrev ? 'prev' : 'next') + '">' + (allowPrev ? '&#8249;' : '&#8250;') + '</button>' +
        '<div class="pms-date-range-calendar-title">' + monthNames[month] + ' ' + year + '</div>' +
        '<span class="pms-date-range-calendar-nav" style="visibility:hidden;"></span>' +
      '</div>' +
      '<div class="pms-date-range-weekdays">' + weekdaysHtml + '</div>' +
      '<div class="pms-date-range-days">' + daysHtml + '</div>';

    container.querySelector('[data-nav]').addEventListener('click', function () {
      var nav = this.getAttribute('data-nav');
      self.viewMonth = addMonths(self.viewMonth, nav === 'prev' ? -1 : 1);
      self.render();
    });

    Array.prototype.slice.call(container.querySelectorAll('[data-date]')).forEach(function (button) {
      button.addEventListener('click', function () {
        self.pick(parseIsoDate(this.getAttribute('data-date')));
      });
    });
  };

  PmsDateRangePicker.prototype.pick = function (date) {
    if (!(date instanceof Date)) {
      return;
    }
    if (!this.isPickingEnd) {
      this.draftStart = date;
      this.draftEnd = date;
      this.isPickingEnd = true;
    } else {
      if (compareDate(date, this.draftStart) < 0) {
        this.draftEnd = this.draftStart;
        this.draftStart = date;
      } else {
        this.draftEnd = date;
      }
      this.isPickingEnd = false;
    }
    this.render();
  };

  function mount(root) {
    if (!root || root._pmsDateRangePickerMounted) {
      return root && root._pmsDateRangePickerInstance ? root._pmsDateRangePickerInstance : null;
    }
    var instance = new PmsDateRangePicker(root);
    root._pmsDateRangePickerMounted = true;
    root._pmsDateRangePickerInstance = instance;
    return instance;
  }

  function initAll(scope) {
    var root = scope || document;
    Array.prototype.slice.call(root.querySelectorAll('[data-pms-date-range-picker]')).forEach(function (node) {
      mount(node);
    });
  }

  window.PmsDateRangePicker = {
    mount: mount,
    initAll: initAll
  };

  document.addEventListener('DOMContentLoaded', function () {
    initAll(document);
  });
})();
