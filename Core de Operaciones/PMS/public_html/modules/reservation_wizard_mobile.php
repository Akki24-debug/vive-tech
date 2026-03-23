<?php
if (!isset($step)) {
    return;
}
?>
<style>
@media (max-width: 1180px), (hover: none) and (pointer: coarse) {
  .wizard-lightbox-host {
    padding: 2px !important;
    align-items: flex-start !important;
  }
  .wizard-lightbox-shell {
    width: 100% !important;
    max-height: calc(100dvh - 4px) !important;
    border-radius: 8px !important;
    overflow: hidden !important;
  }
  .wizard-lightbox-head {
    padding: 4px 8px !important;
    min-height: 34px !important;
    gap: 6px !important;
  }
  .wizard-lightbox-title {
    font-size: clamp(0.72rem, 2.8vw, 0.86rem) !important;
    line-height: 1.1 !important;
    margin: 0 !important;
  }
  #wizard-lightbox-close.rw-mobile-close {
    min-width: 24px !important;
    width: 24px !important;
    height: 24px !important;
    padding: 0 !important;
    border-radius: 999px !important;
    border: 1px solid rgba(248, 113, 113, 0.9) !important;
    background: rgba(127, 29, 29, 0.35) !important;
    color: #ef4444 !important;
    font-size: 0.82rem !important;
    font-weight: 700 !important;
    line-height: 1 !important;
  }
  .wizard-lightbox-body {
    height: calc(100dvh - 44px) !important;
    max-height: calc(100dvh - 44px) !important;
    padding: 4px 6px !important;
    overflow: hidden !important;
  }
  .wizard-lightbox-body.rw-mobile-allow-scroll {
    overflow: auto !important;
    -webkit-overflow-scrolling: touch;
  }
  .reservation-create-form.rw-mobile-mode {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
  }
  .reservation-create-form.rw-mobile-mode .form-grid {
    grid-template-columns: 1fr !important;
    gap: 0.8vh;
  }
  .reservation-create-form.rw-mobile-mode label {
    width: 100%;
    margin: 0;
    font-size: clamp(0.72rem, 2.4vw, 0.86rem);
    line-height: 1.08;
  }
  .reservation-create-form.rw-mobile-mode input,
  .reservation-create-form.rw-mobile-mode select,
  .reservation-create-form.rw-mobile-mode textarea {
    width: 100%;
    box-sizing: border-box;
    min-height: clamp(30px, 4.6vh, 36px);
    padding: clamp(4px, 0.9vh, 6px) clamp(6px, 1.4vw, 9px) !important;
    font-size: clamp(0.78rem, 2.8vw, 0.9rem);
  }
  .reservation-create-form.rw-mobile-mode .phone-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .reservation-create-form.rw-mobile-mode textarea[name="wizard_notes_internal"] {
    min-height: clamp(56px, 10vh, 84px) !important;
    max-height: clamp(56px, 10vh, 84px) !important;
    resize: none;
  }

  .rw-mobile-shell {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
    flex: 1;
    height: 100%;
    min-height: 0;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    background: rgba(8, 18, 32, 0.58);
    padding: clamp(4px, 1.1vw, 6px);
    margin-bottom: 0;
  }
  .rw-mobile-panel-host {
    height: 100%;
    min-height: 0;
    overflow: hidden;
  }
  .rw-mobile-progress {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
  }
  .rw-mobile-dot {
    flex: 1;
    height: 5px;
    border-radius: 999px;
    background: rgba(255,255,255,0.18);
    transition: all .2s ease;
  }
  .rw-mobile-dot.is-active {
    background: rgba(86, 219, 255, 0.95);
  }
  .rw-mobile-panel {
    display: none;
    height: 100%;
    min-height: 0;
    overflow: hidden;
    gap: 4px;
  }
  .rw-mobile-panel.is-active {
    display: grid;
    overflow: hidden;
  }
  .rw-mobile-panel-frame {
    transform-origin: top left;
    will-change: transform;
  }
  .rw-mobile-title {
    margin: 0;
    font-size: clamp(0.78rem, 2.7vw, 0.92rem);
    color: #d6eeff;
  }
  .rw-mobile-subtitle {
    margin: 0;
    color: #9ec7e6;
    font-size: clamp(0.62rem, 2.1vw, 0.74rem);
  }
  .rw-mobile-panel .form-grid,
  .rw-mobile-panel .form-section,
  .rw-mobile-panel .form-actions {
    margin: 0;
  }
  .rw-mobile-panel .guest-suggestions {
    max-height: 34vh;
    overflow: auto;
    position: fixed;
    z-index: 80;
    margin-top: 0;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 10px;
    background: rgba(7, 16, 30, 0.96);
    padding: 4px;
    box-shadow: 0 14px 34px rgba(2, 6, 23, 0.45);
  }
  .rw-mobile-panel .guest-suggestion {
    padding: 8px 10px;
    border-radius: 8px;
  }
  .reservation-create-form.rw-mobile-mode .form-grid.rw-grid-dates {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 1.2vw !important;
  }
  .reservation-create-form.rw-mobile-mode .form-grid.rw-grid-source {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 1.2vw !important;
  }
  .rw-grid-source .rw-col-full {
    grid-column: 1 / -1;
  }

  .rw-mobile-top-nav {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    margin-left: auto;
  }
  .rw-mobile-top-nav .button-secondary {
    min-height: 24px;
    height: 24px;
    padding: 0 8px;
    font-size: 0.72rem;
    border-radius: 999px;
  }
  .rw-mobile-top-nav .button-secondary[disabled] {
    opacity: 0.4;
    pointer-events: none;
  }
  .rw-mobile-actions-only {
    margin-top: 8px;
    padding: 0.8vh 1.2vw 1.6vh;
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .rw-mobile-actions-only .button-secondary,
  .rw-mobile-actions-only .button-primary {
    width: 100%;
    min-height: clamp(36px, 5.8vh, 44px);
  }

  .reservation-create-form.rw-mobile-step2 .wizard-breakdown-layout {
    grid-template-columns: 1fr !important;
    gap: 10px;
  }
  .reservation-create-form.rw-mobile-step2 {
    height: auto;
    min-height: 0;
  }
  .reservation-create-form.rw-mobile-step2 .payment-block {
    padding: 10px !important;
  }
  .reservation-create-form.rw-mobile-step2 .nightly-breakdown .nightly-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
    align-items: stretch;
  }
  .reservation-create-form.rw-mobile-step2 .nightly-amount-input {
    width: 100%;
  }
  .reservation-create-form.rw-mobile-step2 .form-actions {
    position: sticky;
    bottom: 0;
    z-index: 3;
    padding: 10px;
    background: rgba(7, 15, 26, 0.94);
    border-top: 1px solid rgba(255,255,255,0.08);
  }
  .reservation-create-form.rw-mobile-step2 .form-actions .button-secondary,
  .reservation-create-form.rw-mobile-step2 .form-actions .button-primary {
    width: 100%;
  }
}
</style>

<script>
(function () {
  var form = document.querySelector('.reservation-create-form');
  if (!form) {
    return;
  }

  var isMobile = window.matchMedia('(max-width: 1180px)').matches
    || window.matchMedia('(hover: none) and (pointer: coarse)').matches;
  if (!isMobile) {
    return;
  }

  var step = <?php echo (int)$step; ?>;
  var closeBtnGlobal = document.getElementById('wizard-lightbox-close');
  if (closeBtnGlobal) {
    closeBtnGlobal.classList.add('rw-mobile-close');
    closeBtnGlobal.textContent = 'X';
    closeBtnGlobal.setAttribute('aria-label', 'Cerrar');
    closeBtnGlobal.setAttribute('title', 'Cerrar');
  }
	  var lightboxTitleGlobal = document.getElementById('wizard-lightbox-title');
	  if (lightboxTitleGlobal && !String(lightboxTitleGlobal.textContent || '').trim()) {
	    lightboxTitleGlobal.textContent = 'Reservacion';
	  }

  if (step === 1) {
    form.classList.add('rw-mobile-mode');
    var lightboxHead = document.querySelector('.wizard-lightbox-head');

    var sections = form.querySelectorAll('.form-section');
    if (sections.length < 2) {
      return;
    }

    var reservationSection = sections[0];
    var guestSection = sections[1];

    var reservaGridMain = reservationSection.querySelector('.form-grid.grid-3');
    var reservaGridDates = reservationSection.querySelector('.form-grid.grid-2');
    var reservaGridSource = reservationSection.querySelector('.form-grid.grid-4');

    var guestGridNames = guestSection.querySelector('.form-grid.grid-3');
    var guestGridContact = guestSection.querySelector('.form-grid.grid-2');
    var guestSuggestions = guestSection.querySelector('.guest-suggestions');
    var guestUnlockActions = guestSection.querySelector('.form-actions:not(.wizard-actions-step1)');
    var notesGrid = guestSection.querySelector('.form-grid.grid-1');
    var finalActions = form.querySelector('.wizard-actions-step1');

    if (!reservaGridMain || !reservaGridDates || !reservaGridSource || !guestGridNames || !guestGridContact || !notesGrid || !finalActions) {
      return;
    }
    if (guestSuggestions && guestSuggestions.parentNode !== document.body) {
      document.body.appendChild(guestSuggestions);
    }
    reservaGridDates.classList.add('rw-grid-dates');
    reservaGridSource.classList.add('rw-grid-source');
    var sourceSelect = reservaGridSource.querySelector('select[name="wizard_source_id"]');
    var codeInput = reservaGridSource.querySelector('input[name="wizard_code"]');
    var adultsInput = reservaGridSource.querySelector('input[name="wizard_adults"]');
    var childrenInput = reservaGridSource.querySelector('input[name="wizard_children"]');
    if (sourceSelect && sourceSelect.closest('label')) {
      sourceSelect.closest('label').classList.add('rw-col-full');
    }
    if (codeInput && codeInput.closest('label')) {
      codeInput.closest('label').classList.add('rw-col-full');
    }
    if (adultsInput && adultsInput.closest('label')) {
      adultsInput.closest('label').classList.remove('rw-col-full');
    }
    if (childrenInput && childrenInput.closest('label')) {
      childrenInput.closest('label').classList.remove('rw-col-full');
    }

    var shell = document.createElement('div');
    shell.className = 'rw-mobile-shell';

    var progress = document.createElement('div');
    progress.className = 'rw-mobile-progress';
    shell.appendChild(progress);

    var panelHost = document.createElement('div');
    panelHost.className = 'rw-mobile-panel-host';
    shell.appendChild(panelHost);

    var nav = document.createElement('div');
    nav.className = 'rw-mobile-top-nav';

    var btnPrev = document.createElement('button');
    btnPrev.type = 'button';
    btnPrev.className = 'button-secondary';
    btnPrev.textContent = 'Anterior';

    var btnNext = document.createElement('button');
    btnNext.type = 'button';
    btnNext.className = 'button-secondary';
    btnNext.textContent = 'Siguiente';

    nav.appendChild(btnPrev);
    nav.appendChild(btnNext);
    if (lightboxHead) {
      lightboxHead.appendChild(nav);
    }

    var makePanel = function (idx, title, subtitle, nodes) {
      var panel = document.createElement('div');
      panel.className = 'rw-mobile-panel';
      panel.setAttribute('data-panel', String(idx));

      var frame = document.createElement('div');
      frame.className = 'rw-mobile-panel-frame';

      var h = document.createElement('h4');
      h.className = 'rw-mobile-title';
      h.textContent = title;
      frame.appendChild(h);

      if (subtitle) {
        var s = document.createElement('p');
        s.className = 'rw-mobile-subtitle';
        s.textContent = subtitle;
        frame.appendChild(s);
      }

      nodes.forEach(function (node) {
        if (node) {
          frame.appendChild(node);
        }
      });

      panel.appendChild(frame);
      panelHost.appendChild(panel);
      return panel;
    };

    var panels = [
      makePanel(1, 'Datos de la reserva', 'Incluye origen y ocupacion.', [reservaGridMain, reservaGridDates, reservaGridSource]),
      makePanel(2, 'Huesped y notas', 'Datos del huesped y notas internas.', [guestGridNames, guestGridContact, guestUnlockActions, notesGrid, finalActions])
    ];

    reservationSection.style.display = 'none';
    guestSection.style.display = 'none';
    finalActions.classList.add('rw-mobile-actions-only');

    var stepIndex = 0;
    var dots = panels.map(function () {
      var dot = document.createElement('span');
      dot.className = 'rw-mobile-dot';
      progress.appendChild(dot);
      return dot;
    });

    var render = function () {
      panels.forEach(function (panel, idx) {
        panel.classList.toggle('is-active', idx === stepIndex);
      });

      dots.forEach(function (dot, idx) {
        dot.classList.toggle('is-active', idx === stepIndex);
      });

      btnPrev.disabled = stepIndex === 0;
      btnNext.style.display = stepIndex >= (panels.length - 1) ? 'none' : '';
      if (guestSuggestions && stepIndex !== 1) {
        guestSuggestions.style.display = 'none';
      }
      fitActivePanel();
    };

    var fitPanel = function (panel) {
      if (!panel) {
        return;
      }
      var frame = panel.querySelector('.rw-mobile-panel-frame');
      if (!frame) {
        return;
      }
      frame.style.transform = 'scale(1)';
      frame.style.width = '100%';

      var available = panel.clientHeight;
      var required = frame.scrollHeight;
      if (available <= 0 || required <= 0) {
        return;
      }
      var ratio = available / required;
      var scale = Math.max(0.52, Math.min(1, ratio));
      if (scale < 0.999) {
        frame.style.width = (100 / scale).toFixed(3) + '%';
        frame.style.transform = 'scale(' + scale.toFixed(3) + ')';
      }
    };

    var fitActivePanel = function () {
      var panel = panels[stepIndex];
      if (!panel) {
        return;
      }
      window.requestAnimationFrame(function () {
        fitPanel(panel);
        setTimeout(function () { fitPanel(panel); }, 40);
      });
    };

    btnPrev.addEventListener('click', function () {
      if (stepIndex > 0) {
        stepIndex -= 1;
        render();
      }
    });

    btnNext.addEventListener('click', function () {
      if (stepIndex < panels.length - 1) {
        stepIndex += 1;
        render();
      }
    });

    var guestInputs = [guestGridNames, guestGridContact].map(function (grid) {
      return grid ? Array.prototype.slice.call(grid.querySelectorAll('input, select')) : [];
    }).reduce(function (acc, list) { return acc.concat(list); }, []);
    var activeGuestInput = null;
    var placeGuestSuggestions = function () {
      if (!guestSuggestions || !activeGuestInput) {
        return;
      }
      var isVisible = guestSuggestions.style.display !== 'none' && guestSuggestions.childElementCount > 0;
      if (!isVisible) {
        return;
      }
      var rect = activeGuestInput.getBoundingClientRect();
      var top = rect.bottom + 4;
      var left = rect.left;
      var width = rect.width;
      guestSuggestions.style.top = Math.max(40, Math.round(top)) + 'px';
      guestSuggestions.style.left = Math.max(6, Math.round(left)) + 'px';
      guestSuggestions.style.width = Math.max(160, Math.round(width)) + 'px';
      guestSuggestions.style.maxWidth = 'calc(100vw - 12px)';
      guestSuggestions.style.display = 'block';
    };

    guestInputs.forEach(function (field) {
      field.addEventListener('focus', function () {
        activeGuestInput = field;
        setTimeout(placeGuestSuggestions, 20);
      });
      field.addEventListener('input', function () {
        activeGuestInput = field;
        setTimeout(placeGuestSuggestions, 20);
      });
    });

    var guestObserver = null;
    if (guestSuggestions && typeof MutationObserver !== 'undefined') {
      guestObserver = new MutationObserver(function () {
        placeGuestSuggestions();
      });
      guestObserver.observe(guestSuggestions, { attributes: true, attributeFilter: ['style', 'class'], childList: true, subtree: true });
    }

    window.addEventListener('resize', fitActivePanel);
    window.addEventListener('orientationchange', fitActivePanel);
    window.addEventListener('resize', placeGuestSuggestions);
    window.addEventListener('orientationchange', placeGuestSuggestions);
    ['change', 'input', 'focusin'].forEach(function (evtName) {
      shell.addEventListener(evtName, fitActivePanel, true);
    });

    reservationSection.parentNode.insertBefore(shell, reservationSection);
    var bodyStep1 = document.querySelector('.wizard-lightbox-body');
    if (bodyStep1) {
      bodyStep1.classList.remove('rw-mobile-allow-scroll');
    }
    render();
  } else if (step === 2) {
    var bodyStep2 = document.querySelector('.wizard-lightbox-body');
    if (bodyStep2) {
      bodyStep2.classList.add('rw-mobile-allow-scroll');
    }
    form.classList.add('rw-mobile-mode');
    form.classList.add('rw-mobile-step2');
  } else {
    var bodyOther = document.querySelector('.wizard-lightbox-body');
    if (bodyOther) {
      bodyOther.classList.remove('rw-mobile-allow-scroll');
    }
    form.classList.add('rw-mobile-mode');
  }
})();
</script>
