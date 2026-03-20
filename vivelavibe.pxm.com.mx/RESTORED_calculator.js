(function () {
  'use strict';

  const BAND_ORDER = ['friendly', 'standard', 'premium'];
  const BAND_LABELS = {
    friendly: 'Ahorro',
    standard: 'Balanceado',
    premium: 'Premium'
  };

  const state = {
    checkIn: '',
    checkOut: '',
    nights: 1,
    people: 1,
    currency: 'MXN',
    selectedBands: [],
    bandOrder: BAND_ORDER.slice(),
    activeBand: null,
    bands: {},
    combos: {},
    availabilityResults: [],
    suggestionsSource: 'none',
    suggestionEntries: [],
    activities: [],
    extras: [],
    lodgingTab: 'example',
    lodgingSelections: { example: true, vive: null },
    viveLodgings: [],
    viveBestId: null,
    totals: {
      lodging: { perNight: 0, subtotal: 0, hasPrice: false },
      activities: 0,
      extras: 0,
      grand: 0,
      perPerson: 0
    }
  };

  const refs = {};

  function docReady(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
      callback();
    }
  }

  function $(selector, root) {
    return (root || document).querySelector(selector);
  }

  function $all(selector, root) {
    return Array.from((root || document).querySelectorAll(selector));
  }

  function clampNumber(value, min, max) {
    const num = Number(value);
    if (!Number.isFinite(num)) return min;
    if (num < min) return min;
    if (typeof max === 'number' && num > max) return max;
    return num;
  }

  function safeNumber(value) {
    if (window.VIVE_SUGGEST && typeof window.VIVE_SUGGEST.safeNumber === 'function') {
      return window.VIVE_SUGGEST.safeNumber(value);
    }
    if (typeof value === 'number' && Number.isFinite(value)) return value;
    if (typeof value === 'string' && value.trim() !== '') {
      const sanitized = value
        .trim()
        .replace(/[^0-9.,-]/g, '')
        .replace(/,/g, '');
      if (!sanitized) return null;
      const parsed = Number(sanitized);
      return Number.isFinite(parsed) ? parsed : null;
    }
    return null;
  }

  function formatCurrency(value, currency) {
    const cur = currency || state.currency || 'MXN';
    if (typeof value !== 'number' || !Number.isFinite(value) || value <= 0) {
      return 'A definir';
    }
    try {
      return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: cur,
        maximumFractionDigits: 0
      }).format(value);
    } catch {
      return `${value} ${cur}`;
    }
  }

  function formatDate(dateString) {
    if (!dateString) return '';
    const d = new Date(dateString);
    if (Number.isNaN(d.getTime())) return '';
    try {
      return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short'
      }).format(d);
    } catch {
      return d.toISOString().slice(0, 10);
    }
  }

  function computeCheckOutDate(checkIn, nights) {
    if (!checkIn) return '';
    const d = new Date(checkIn);
    if (Number.isNaN(d.getTime())) return '';
    const stay = Math.max(1, parseInt(nights, 10) || 1);
    const out = new Date(d);
    out.setDate(out.getDate() + stay);
    return out.toISOString().slice(0, 10);
  }

  function normalizeExtraId(raw) {
    const value = String(raw || '').trim();
    if (!value) return '';
    return value.startsWith('extra_') ? value : `extra_${value}`;
  }

  function roomsTextList(combo) {
    if (!combo || !Array.isArray(combo.rooms) || !combo.rooms.length) return '';
    return combo.rooms
      .map((room) => {
        const qty = room.count > 1 ? `${room.count}x ` : '';
        const cap = room.totalCapacity || (room.capacity || 0) * (room.count || 1);
        const pax = cap ? ` (${cap} pax)` : '';
        return `${qty}${room.roomTypeName || 'Habitacion'}${pax}`;
      })
      .join(' - ');
  }

  function getExampleCombo() {
    if (!state.activeBand) return null;
    const combo = state.combos[state.activeBand];
    if (!combo || !Array.isArray(combo.rooms) || !combo.rooms.length) return null;
    return combo;
  }

  function getSelectedViveOffer() {
    const selectedId = state.lodgingSelections.vive;
    if (!selectedId) return null;
    const target = String(selectedId);
    return (
      state.viveLodgings.find((offer) => {
        const offerId = offer?.id ?? offer?.code ?? offer?.slug ?? offer?.lodgingId;
        if (offerId == null) return false;
        return String(offerId) === target;
      }) || null
    );
  }

  function getSelectedLodging() {
    if (state.lodgingSelections.example) {
      const combo = getExampleCombo();
      if (combo) {
        const perNight = Number.isFinite(combo.totalPerNight) ? combo.totalPerNight : null;
        return {
          type: 'example',
          label: BAND_LABELS[state.activeBand] || 'Hospedaje sugerido',
          perNight,
          combo
        };
      }
    }
    const offer = getSelectedViveOffer();
    if (offer) {
      const price = safeNumber(
        offer.totalPerNight ??
          offer.pricePerNight ??
          offer.price ??
          offer.amount
      );
      return {
        type: 'vive',
        label: offer.lodgingName || offer.title || offer.name || 'Hospedaje Vive la Vibe',
        perNight: Number.isFinite(price) ? price : null,
        offer
      };
    }
    return null;
  }
  function ensureStyles() {
    if ($('#calcStyles')) return;
    const style = document.createElement('style');
    style.id = 'calcStyles';
    style.textContent = `
      .calc-overlay{
        position:fixed;
        inset:0;
        display:none;
        align-items:center;
        justify-content:center;
        padding:20px;
        background:rgba(10,16,27,0.78);
        backdrop-filter:blur(6px);
        z-index:460;
      }
      .calc-overlay.active{ display:flex; }
      .calc-modal{
        width:min(1040px,100%);
        max-height:94vh;
        overflow:hidden;
        border-radius:18px;
        border:1px solid color-mix(in srgb,var(--muted, #badfdb) 60%, transparent);
        background:var(--surface-strong, #0e1627);
        box-shadow:0 24px 60px rgba(0,0,0,0.45);
        display:flex;
        flex-direction:column;
      }
      .calc-header{
        padding:18px 22px;
        border-bottom:1px solid color-mix(in srgb,var(--muted, #badfdb) 55%, transparent);
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:12px;
      }
      .calc-header h2{ margin:0; font-size:1.35rem; }
      .calc-subtitle{ margin:4px 0 0; color:var(--text-soft, #72506b); font-size:.95rem; }
      .calc-close{
        border:0;
        background:transparent;
        color:var(--text-soft, #72506b);
        font-size:1.4rem;
        cursor:pointer;
        padding:4px 6px;
        transition:color .15s ease;
      }
      .calc-close:hover{ color:var(--text, #e5e7eb); }
      .calc-summary{
        padding:18px 22px;
        display:flex;
        flex-wrap:wrap;
        gap:16px;
        align-items:center;
        border-bottom:1px solid color-mix(in srgb,var(--muted, #badfdb) 45%, transparent);
      }
      .calc-summary-label{ display:block; font-size:.9rem; color:var(--text-soft, #72506b); margin-bottom:4px; }
      .calc-total-amount{ font-size:2rem; font-weight:800; }
      .calc-chips{ display:flex; flex-wrap:wrap; gap:8px; }
      .calc-chip{
        padding:6px 10px;
        border-radius:999px;
        border:1px solid color-mix(in srgb,var(--muted, #badfdb) 55%, transparent);
        background:color-mix(in srgb,var(--surface, #fffdf7) 94%, transparent);
        font-size:.85rem;
        color:var(--text-soft, #72506b);
      }
      .calc-body{ flex:1; overflow:auto; padding:18px 22px; display:grid; gap:14px; }
      .calc-section{
        border:1px solid color-mix(in srgb,var(--muted, #badfdb) 45%, transparent);
        border-radius:14px;
        background:color-mix(in srgb,var(--surface, #fffdf7) 95%, transparent);
        overflow:hidden;
      }
      .calc-section summary{
        list-style:none;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        padding:14px 16px;
        cursor:pointer;
        font-weight:600;
      }
      .calc-section summary::-webkit-details-marker{ display:none; }
      .calc-section-title{ display:block; font-size:1rem; }
      .calc-section-summary{ display:block; font-size:.9rem; color:var(--text-soft, #72506b); margin-top:4px; }
      .calc-indicator{
        width:22px;
        height:22px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        font-size:.85rem;
        background:color-mix(in srgb,var(--muted, #badfdb) 55%, transparent);
        color:var(--text-soft, #72506b);
        transition:background .2s ease, color .2s ease;
      }
      .calc-indicator::before{
        content:'>';
        transition:transform .2s ease;
      }
      summary:hover .calc-indicator{
        background:color-mix(in srgb,var(--muted, #badfdb) 70%, transparent);
        color:var(--text, #e5e7eb);
      }
      .calc-section[open] > summary .calc-indicator::before{ content:'v'; }
      .calc-section-body{ padding:16px; display:grid; gap:14px; }
      .calc-lodging-body{ display:flex; flex-direction:column; gap:16px; }
      .calc-lodging-inline{
        display:none;
        flex:1;
        align-items:center;
        gap:12px;
        margin-left:16px;
        min-height:36px;
        flex-wrap:wrap;
      }
      .calc-section[open] > summary .calc-lodging-inline{ display:flex; }
      .calc-lodging-inline .calc-band-switch{
        flex:1 1 320px;
        justify-content:flex-start;
        flex-wrap:nowrap;
        align-items:center;
        min-height:36px;
      }
      .calc-lodging-inline .calc-vive-switch{ margin-left:auto; }
      .calc-vive-switch{
        display:inline-flex;
        align-items:center;
        gap:8px;
        font-weight:600;
        color:var(--text-soft, #72506b);
        cursor:pointer;
        max-width:180px;
      }
      .calc-vive-switch input{
        width:16px;
        height:16px;
        accent-color:var(--primary, #e6007e);
        margin-top:2px;
      }
      .calc-vive-switch span{
        display:inline-block;
        line-height:1.2;
        white-space:normal;
      }
      .calc-vive-switch input:checked + span,
      .calc-vive-switch.active span{
        color:var(--text, #e5e7eb);
      }
      .calc-band-switch.hidden{ display:none !important; }
      .calc-tab-panels{
        position:relative;
        width:100%;
        margin-top:8px;
      }
      .calc-tab-panel{
        display:flex;
        flex-direction:column;
        gap:16px;
        position:absolute;
        inset:0;
        width:100%;
        opacity:0;
        pointer-events:none;
        visibility:hidden;
      }
      .calc-tab-panel.active{
        position:relative;
        opacity:1;
        pointer-events:auto;
        visibility:visible;
      }
      .calc-band-switch{ display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
      .calc-band-btn{
        border:1px solid color-mix(in srgb,var(--muted, #badfdb) 55%, transparent);
        border-radius:999px;
        padding:6px 12px;
        min-height:36px;
        background:color-mix(in srgb,var(--surface, #fffdf7) 94%, transparent);
        color:var(--text, #e5e7eb);
        cursor:pointer;
        font-weight:600;
        font-size:.9rem;
        white-space:nowrap;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        transition:background .15s ease, border-color .15s ease, transform .1s ease;
      }
      .calc-band-btn:hover{ transform:translateY(-1px); }
      .calc-band-btn.active{
        border-color:var(--accent, #ffa4a4);
        background:linear-gradient(135deg,var(--primary, #e6007e),var(--accent, #ffa4a4));
        color:#0b0e11;
      }
      .calc-band-btn:disabled{
        opacity:.45;
        cursor:not-allowed;
        transform:none;
      }
      .calc-rooms{ display:grid; gap:12px; }
      .calc-room-card{
        border:1px solid color-mix(in srgb,var(--muted, #badfdb) 45%, transparent);
        border-radius:12px;
        padding:14px;
        background:color-mix(in srgb,var(--surface, #fffdf7) 96%, transparent);
        display:grid;
        gap:8px;
      }
      .calc-room-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
      .calc-room-head h4{ margin:0; font-size:1rem; flex:1; }
      .calc-room-capacity{
        font-size:.85rem;
        color:var(--text-soft, #72506b);
        border:1px solid color-mix(in srgb,var(--muted, #badfdb) 45%, transparent);
        padding:4px 8px;
        border-radius:999px;
      }
      .calc-room-meta{ margin:0; font-size:.9rem; color:var(--text-soft, #72506b); }
      .calc-room-desc{ margin:0; font-size:.9rem; color:var(--text-soft, #72506b); }
      .calc-vive-list{ display:flex; flex-direction:column; gap:14px; }
      .calc-vive-card{
        border:1px solid color-mix(in srgb,var(--muted, #badfdb) 45%, transparent);
        border-radius:12px;
        padding:14px;
        background:color-mix(in srgb,var(--surface, #fffdf7) 96%, transparent);
        display:flex;
        flex-direction:column;
        gap:10px;
        transition:border-color .2s ease, background .2s ease;
      }
      .calc-vive-card.selected{
        border-color:color-mix(in srgb,var(--primary, #e6007e) 60%, transparent);
        background:color-mix(in srgb,var(--surface, #fffdf7) 92%, transparent);
      }
      .calc-vive-card-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:12px;
      }
      .calc-vive-card h4{ margin:0; font-size:1rem; }
      .calc-vive-meta{ margin:0; font-size:.9rem; color:var(--text-soft, #72506b); }
      .calc-vive-extra{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        flex-wrap:wrap;
      }
      .calc-vive-price{ font-weight:700; }
      .calc-vive-toggle{
        display:flex;
        align-items:center;
        gap:8px;
        font-weight:600;
      }
      .calc-vive-toggle input{ accent-color:var(--primary, #e6007e); }
      .calc-vive-rooms{ display:grid; gap:12px; margin-top:12px; }
      .calc-room-total{
        margin-top:4px;
        font-weight:700;
        font-size:1rem;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:10px;
        padding:10px 12px;
        border-radius:12px;
        background:linear-gradient(135deg,var(--primary, #e6007e),var(--accent, #ffa4a4));
        color:#0b0e11;
      }
      .calc-room-total.is-empty{
        background:color-mix(in srgb,var(--muted, #badfdb) 70%, transparent);
        color:var(--text, #e5e7eb);
      }
        .calc-activities{
          display:flex;
          flex-direction:column;
          gap:14px;
          max-height:360px;
          overflow-y:auto;
          padding-right:8px;
          padding-bottom:40px;
          scrollbar-width:thin;
        }
        .calc-activities::-webkit-scrollbar{
          width:6px;
        }
        .calc-activities::-webkit-scrollbar-track{
          background:transparent;
        }
        .calc-activities::-webkit-scrollbar-thumb{
          background:color-mix(in srgb,var(--muted, #badfdb) 65%, transparent);
          border-radius:8px;
        }
        .calc-activity-item{
          display:flex;
          flex-direction:column;
          align-items:flex-start;
          gap:10px;
          border:1px solid color-mix(in srgb,var(--muted, #badfdb) 45%, transparent);
          border-radius:12px;
          padding:12px 14px;
          background:color-mix(in srgb,var(--surface, #fffdf7) 96%, transparent);
          transition:border-color .2s ease, background .2s ease;
        }
        .calc-activity-item.selected{
          border-color:color-mix(in srgb,var(--primary, #e6007e) 55%, transparent);
          background:color-mix(in srgb,var(--surface, #fffdf7) 92%, transparent);
        }
        .calc-activity-toggle{
          display:flex;
          align-items:center;
          gap:8px;
          font-weight:600;
          min-width:0;
        }
        .calc-activity-toggle input{ accent-color:var(--primary, #e6007e); }
        .calc-activity-group{
          display:flex;
          flex-direction:column;
          gap:12px;
          min-width:0;
        }
        .calc-activity-group + .calc-activity-group{ margin-top:18px; }
        .calc-activity-group-head{
          display:flex;
          align-items:center;
          justify-content:space-between;
          gap:8px;
          padding:0 4px;
        }
        .calc-activity-group-title{
          font-size:.95rem;
          font-weight:700;
          text-transform:uppercase;
          letter-spacing:.05em;
          color:var(--text-soft, #72506b);
        }
        .calc-activity-group-count{
          font-size:.75rem;
          color:color-mix(in srgb,var(--text-soft, #72506b) 80%, transparent);
        }
        .calc-activity-group-vibe .calc-activity-group-title{
          color:var(--primary, #e6007e);
        }
        .calc-activity-group-tour .calc-activity-group-title{
          color:var(--accent, #ffa4a4);
        }
        .calc-activity-group-body{
          display:flex;
          flex-direction:column;
          gap:12px;
        }
        .calc-activities-columns{
          display:flex;
          gap:18px;
          align-items:flex-start;
          width:100%;
        }
        .calc-activities-col{
          flex:1 1 0;
          min-width:0;
          display:flex;
          flex-direction:column;
          gap:14px;
        }
        .calc-activities-col-empty{
          display:none;
        }
        .calc-activity-name{ white-space:nowrap; text-overflow:ellipsis; overflow:hidden; }
        .calc-activity-meta{ margin:0; font-size:.85rem; color:var(--text-soft, #72506b); }
        .calc-activity-extra{
          display:flex;
          align-items:center;
          gap:14px;
          flex-wrap:wrap;
        }
        .calc-activity-price{
          font-size:.95rem;
          font-weight:600;
        }
        .calc-activity-controls{
          display:flex;
          align-items:center;
          gap:8px;
        }
      .calc-activity-controls.disabled{
        opacity:.5;
        pointer-events:none;
      }
      .calc-stepper{
        display:inline-flex;
        align-items:center;
        gap:4px;
        border:1px solid color-mix(in srgb,var(--muted, #badfdb) 55%, transparent);
        border-radius:10px;
        padding:4px;
        background:color-mix(in srgb,var(--surface, #fffdf7) 94%, transparent);
      }
        .calc-step{
          width:28px;
          height:28px;
          border:0;
          border-radius:8px;
          background:color-mix(in srgb,var(--surface-strong, #0e1627) 92%, transparent);
          color:var(--text, #e5e7eb);
          cursor:pointer;
          font-size:1.1rem;
          display:flex;
          align-items:center;
          justify-content:center;
          line-height:1;
          font-weight:600;
          transition:background .15s ease, transform .1s ease;
        }
        .calc-step:hover{ background:color-mix(in srgb,var(--primary, #e6007e) 20%, transparent); }
        .calc-step:active{ transform:scale(.95); }
      .calc-step-input{
        width:46px;
        border:0;
        background:transparent;
        color:var(--text, #e5e7eb);
        text-align:center;
        font-size:1rem;
      }
      .calc-extras{ display:grid; gap:10px; }
      .calc-extra-row{
        display:flex;
        align-items:flex-start;
        gap:10px;
        padding:10px 12px;
        border:1px solid color-mix(in srgb,var(--muted, #badfdb) 45%, transparent);
        border-radius:12px;
        background:color-mix(in srgb,var(--surface, #fffdf7) 96%, transparent);
        cursor:pointer;
      }
      .calc-extra-row input{ accent-color:var(--primary, #e6007e); margin-top:4px; }
      .calc-extra-text{ display:grid; gap:4px; }
      .calc-extra-title{ font-weight:600; }
      .calc-extra-meta{ font-size:.85rem; color:var(--text-soft, #72506b); }
      .calc-empty{
        margin:0;
        color:var(--text-soft, #72506b);
        font-size:.95rem;
      }
      .calc-footer{
        border-top:1px solid color-mix(in srgb,var(--muted, #badfdb) 45%, transparent);
        padding:16px 22px;
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        align-items:center;
        justify-content:space-between;
      }
      .calc-per-person{ color:var(--text-soft, #72506b); font-size:.95rem; }
      .calc-footer-actions{ display:flex; flex-wrap:wrap; gap:10px; }
      .calc-btn{
        border-radius:12px;
        padding:10px 16px;
        font-weight:700;
        cursor:pointer;
        border:1px solid transparent;
        transition:filter .15s ease, transform .1s ease;
      }
      .calc-btn.primary{
        background:linear-gradient(135deg,var(--primary, #e6007e),var(--accent, #ffa4a4));
        color:#0b0e11;
      }
      .calc-btn.ghost{
        background:transparent;
        border-color:color-mix(in srgb,var(--muted, #badfdb) 55%, transparent);
        color:var(--text, #e5e7eb);
      }
      .calc-btn:disabled{ opacity:.6; cursor:not-allowed; }
      .calc-btn:not(:disabled):hover{ filter:brightness(1.05); transform:translateY(-1px); }
      @media (max-width: 768px){
        .calc-summary{ flex-direction:column; align-items:flex-start; }
        .calc-footer{ flex-direction:column; align-items:flex-start; }
        .calc-footer-actions{ width:100%; }
        .calc-footer-actions .calc-btn{ flex:1; text-align:center; }
        .calc-activity-controls{ flex-direction:column; align-items:flex-start; }
      }
    `;
    document.head.appendChild(style);
  }
  function ensureOverlay(texts) {
    if (refs.overlay) return;
    const overlay = document.createElement('div');
    overlay.id = 'calcOverlay';
    overlay.className = 'calc-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('hidden', 'hidden');

    const calcTexts = texts.calc || {};
    overlay.innerHTML = `
      <div class="calc-modal" role="document">
        <header class="calc-header">
          <div>
            <h2 id="calcTitle">${calcTexts.resultsTitle || 'Tu cotizacion'}</h2>
            <p id="calcSubtitle" class="calc-subtitle"></p>
          </div>
          <button type="button" id="calcClose" class="calc-close" aria-label="Cerrar">&times;</button>
        </header>
        <section class="calc-summary">
          <div>
            <span class="calc-summary-label">${calcTexts.totalsBox?.total || 'Total estimado'}</span>
            <div id="calcTotal" class="calc-total-amount">A definir</div>
          </div>
          <div id="calcChips" class="calc-chips"></div>
        </section>
        <div class="calc-body">
            <details id="calcLodgingSection" class="calc-section">
            <summary>
              <div>
                <span class="calc-section-title">${calcTexts.sections?.lodging || 'Hospedaje'}</span>
                <span id="calcLodgingSummary" class="calc-section-summary"></span>
              </div>
              <div class="calc-lodging-inline">
                <div id="calcBandSwitch" class="calc-band-switch"></div>
                <label class="calc-vive-switch">
                  <input type="checkbox" id="calcViveToggle">
                  <span>Recomendaciones Vive la Vibe</span>
                </label>
              </div>
              <span class="calc-indicator" aria-hidden="true"></span>
            </summary>
            <div class="calc-section-body calc-lodging-body">
              <div id="calcLodgingPanels" class="calc-tab-panels">
                <div id="calcExamplePanel" class="calc-tab-panel active">
                  <div id="calcRoomsContainer" class="calc-rooms"></div>
                </div>
                <div id="calcVivePanel" class="calc-tab-panel">
                  <div id="calcViveList" class="calc-vive-list"></div>
                </div>
              </div>
              <div id="calcLodgingTotals" class="calc-room-total is-empty">
                <span>Subtotal hospedaje</span>
                <span>Selecciona una opcion para ver el estimado</span>
              </div>
            </div>
          </details>

            <details id="calcActivitiesSection" class="calc-section">
            <summary>
              <div>
                <span class="calc-section-title">${calcTexts.sections?.activities || 'Actividades'}</span>
                <span id="calcActivitiesSummary" class="calc-section-summary"></span>
              </div>
              <span class="calc-indicator" aria-hidden="true"></span>
            </summary>
            <div class="calc-section-body">
              <div id="calcActivitiesList" class="calc-activities"></div>
            </div>
          </details>

          <details id="calcExtrasSection" class="calc-section">
            <summary>
              <div>
                <span class="calc-section-title">Servicios adicionales</span>
                <span id="calcExtrasSummary" class="calc-section-summary"></span>
              </div>
              <span class="calc-indicator" aria-hidden="true"></span>
            </summary>
            <div class="calc-section-body">
              <div id="calcExtrasList" class="calc-extras"></div>
            </div>
          </details>
        </div>
        <footer class="calc-footer">
          <div class="calc-footer-totals">
            <span id="calcPerPerson" class="calc-per-person"></span>
          </div>
          <div class="calc-footer-actions">
            <button type="button" id="calcAddLodging" class="calc-btn primary">${calcTexts.segments?.add || 'Agregar hospedaje'}</button>
            <button type="button" id="calcCheckout" class="calc-btn ghost">Ir al checkout</button>
          </div>
        </footer>
      </div>
    `;

    document.body.appendChild(overlay);

    refs.overlay = overlay;
    refs.total = $('#calcTotal', overlay);
      refs.lodgingSection = $('#calcLodgingSection', overlay);
      refs.chips = $('#calcChips', overlay);
      refs.lodgingSummary = $('#calcLodgingSummary', overlay);
      refs.activitiesSummary = $('#calcActivitiesSummary', overlay);
      refs.extrasSummary = $('#calcExtrasSummary', overlay);
      refs.viveToggle = $('#calcViveToggle', overlay);
      refs.lodgingPanels = $('#calcLodgingPanels', overlay);
      refs.examplePanel = $('#calcExamplePanel', overlay);
      refs.vivePanel = $('#calcVivePanel', overlay);
      refs.bandSwitch = $('#calcBandSwitch', overlay);
      refs.roomsContainer = $('#calcRoomsContainer', overlay);
      refs.viveList = $('#calcViveList', overlay);
      refs.lodgingTotal = $('#calcLodgingTotals', overlay);
      refs.activitiesList = $('#calcActivitiesList', overlay);
      refs.extrasList = $('#calcExtrasList', overlay);
      refs.perPerson = $('#calcPerPerson', overlay);
      refs.subtitle = $('#calcSubtitle', overlay);
      refs.addLodgingBtn = $('#calcAddLodging', overlay);
      refs.checkoutBtn = $('#calcCheckout', overlay);

      const detailSections = [
        $('#calcLodgingSection', overlay),
        $('#calcActivitiesSection', overlay),
        $('#calcExtrasSection', overlay)
      ].filter(Boolean);

      detailSections.forEach((section) => {
        section.addEventListener('toggle', () => {
          if (section.open) {
            detailSections.forEach((other) => {
              if (other !== section) other.open = false;
            });
          }
          renderSummary();
        });
      });

      const closeBtn = $('#calcClose', overlay);
      if (closeBtn) closeBtn.addEventListener('click', closeOverlay);

    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) closeOverlay();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && refs.overlay && !refs.overlay.hasAttribute('hidden')) {
        closeOverlay();
      }
    });

    if (refs.viveToggle) {
      refs.viveToggle.addEventListener('change', (event) => {
        setLodgingMode(event.target.checked);
      });
    }

    if (refs.viveList) {
      refs.viveList.addEventListener('change', (event) => {
        const input = event.target.closest('input[data-vive-id]');
        if (!input) return;
        toggleViveSelection(input.getAttribute('data-vive-id'), input.checked);
      });
    }

    if (refs.bandSwitch) {
      refs.bandSwitch.addEventListener('click', (event) => {
        const btn = event.target.closest('button[data-band]');
        if (!btn) return;
        const band = btn.getAttribute('data-band');
        if (!band || band === state.activeBand || btn.disabled) return;
        state.activeBand = band;
        recomputeTotals();
        renderAll();
      });
    }

    if (refs.activitiesList) {
      refs.activitiesList.addEventListener('click', (event) => {
        const stepBtn = event.target.closest('.calc-step');
        if (!stepBtn) return;
        const card = event.target.closest('.calc-activity-item');
        if (!card) return;
        const id = card.getAttribute('data-activity-id');
        const delta = Number(stepBtn.getAttribute('data-step')) || 0;
        adjustActivityPersons(id, delta);
      });
      refs.activitiesList.addEventListener('change', (event) => {
        if (event.target.classList.contains('calc-step-input')) {
          const card = event.target.closest('.calc-activity-item');
          const id = card ? card.getAttribute('data-activity-id') : null;
          if (!id) return;
          setActivityPersons(id, Number(event.target.value));
          return;
        }
        if (event.target.classList.contains('calc-activity-toggle-input')) {
          const card = event.target.closest('.calc-activity-item');
          const id = card ? card.getAttribute('data-activity-id') : null;
          if (!id) return;
          setActivitySelected(id, event.target.checked);
        }
      });
    }

    if (refs.extrasList) {
      refs.extrasList.addEventListener('change', (event) => {
        const input = event.target.closest('input[data-extra-id]');
        if (!input) return;
        const id = input.getAttribute('data-extra-id');
        toggleExtra(id, input.checked);
      });
    }

    if (refs.addLodgingBtn) {
      refs.addLodgingBtn.addEventListener('click', pushLodgingToCart);
    }

    if (refs.checkoutBtn) {
      refs.checkoutBtn.addEventListener('click', openCheckout);
    }
  }
  function openOverlay() {
    if (!refs.overlay) return;
    refs.overlay.classList.add('active');
    refs.overlay.removeAttribute('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeOverlay() {
    if (!refs.overlay) return;
    refs.overlay.classList.remove('active');
    refs.overlay.setAttribute('hidden', 'hidden');
    document.body.style.overflow = '';
  }

  function readBandSelections() {
    return BAND_ORDER.slice();
  }

  function readBooker() {
    const booker = $('#bookerCard');
    const checkInInput = booker ? $('input[type="date"]', booker) : null;
    const nightsInput = booker ? $('input[type="number"][aria-label="Noches"]', booker) : null;
    const peopleInput = booker ? $('input[type="number"][aria-label="Personas"]', booker) : null;

    const checkIn = checkInInput ? checkInInput.value : '';
    const nightsRaw = nightsInput ? nightsInput.value : '1';
    const peopleRaw = peopleInput ? peopleInput.value : '1';
    const nights = Math.max(1, parseInt(nightsRaw, 10) || 1);
    const people = Math.max(1, parseInt(peopleRaw, 10) || 1);
    const checkOut = computeCheckOutDate(checkIn, nights);

    const selectedBands = readBandSelections();

    return { checkIn, checkOut, nights, people, selectedBands };
  }

  function resolveActivityType(rawType) {
    const normalized = typeof rawType === 'string' ? rawType.trim().toLowerCase() : '';
    switch (normalized) {
      case 'tour':
      case 'tours':
        return { type: 'tour', label: 'Tour' };
      case 'vibe':
      case 'actividad vibe':
      case 'vibe activity':
        return { type: 'vibe', label: 'Actividad VIBE' };
      case 'experience':
      case 'experiencia':
        return { type: 'experience', label: 'Experiencia' };
      case 'activity':
      case 'actividades':
      case 'actividad':
        return { type: 'activity', label: 'Actividad' };
      default:
        if (!normalized) {
          return { type: 'activity', label: 'Actividad' };
        }
        const label = normalized.replace(/\b\w/g, (char) => char.toUpperCase());
        return { type: normalized, label };
    }
  }

  function readActivities(resources, defaultPeople) {
    const catalog = Array.isArray(resources.activities) ? resources.activities : [];
    const basePeople = clampNumber(defaultPeople, 1, 99);
    const previous = new Map(state.activities.map((activity) => [String(activity.id), activity]));
    const fallbackCurrency = resources.currency || state.currency || 'MXN';

    const asideInputs = $all('#actsWrap input[type="checkbox"]');
    const asideEntries = asideInputs.map((input, index) => {
      const raw = input.id || '';
      const id = raw.replace(/^act_/, '') || `activity-form-${index + 1}`;
      const label = input.closest('label');
      return {
        id,
        checked: Boolean(input.checked),
          title: label ? label.textContent.replace(/\s+/g, ' ').trim() : `Actividad ${index + 1}`
        };
      });

    const groupedActivities = catalog.reduce((acc, item) => {
      if (!item || typeof item !== 'object') return acc;
      const typeKey = resolveActivityType(item.type ?? item.activity_type).type;
      if (!acc[typeKey]) acc[typeKey] = [];
      acc[typeKey].push(item);
      return acc;
    }, {});

    const normalizedMap = new Map();

    const addActivity = (item, index, opts = {}) => {
      if (!item) return;
      const idRaw = item.id ?? item.code ?? item.slug ?? opts.fallbackId ?? `activity-${index + 1}`;
      const id = String(idRaw);
      const prev = previous.get(id);
      const typeMeta = resolveActivityType(item.type ?? item.activity_type ?? prev?.type ?? opts.type);
      const minPersons = clampNumber(
        item.minPersons ?? item.minGuests ?? prev?.minPersons ?? opts.minPersons ?? 1,
        1,
        99
      );
      const maxPersons = clampNumber(
        item.maxPersons ?? item.maxGuests ?? prev?.maxPersons ?? opts.maxPersons ?? minPersons ?? 99,
        minPersons,
        99
      );
      const persons = clampNumber(prev?.persons ?? opts.persons ?? basePeople, minPersons, maxPersons);
      const price = safeNumber(item.pricePerPerson ?? item.price ?? item.amount ?? prev?.pricePerPerson ?? opts.price);

      const record = {
        id,
        title: item.title || item.name || prev?.title || opts.title || `Actividad ${index + 1}`,
        description: item.description || prev?.description || opts.description || '',
        durationLabel: item.durationLabel || item.duration || prev?.durationLabel || opts.durationLabel || '',
        location: item.location || prev?.location || opts.location || '',
        pricePerPerson: price,
        currency: item.currency || prev?.currency || fallbackCurrency,
        persons,
        selected: Boolean(prev?.selected),
        minPersons,
        maxPersons,
        type: typeMeta.type,
        typeLabel: typeMeta.label,
        source: opts.source || 'resources'
      };
      normalizedMap.set(id, record);
    };

    const orderedTypes = ['vibe', 'tour', 'experience', 'activity', 'otros'];
    orderedTypes.forEach((typeKey) => {
      const items = groupedActivities[typeKey];
      if (Array.isArray(items)) {
        items.forEach((item, index) => addActivity(item, index));
      }
    });

    catalog.forEach((item, index) => {
      const id = String(item?.id ?? item?.code ?? item?.slug ?? `activity-${index + 1}`);
      if (!normalizedMap.has(id)) {
        addActivity(item, index);
      }
    });

    previous.forEach((prev, id) => {
      if (normalizedMap.has(id)) return;
      const typeMeta = resolveActivityType(prev?.type);
      const minPersons = clampNumber(prev?.minPersons ?? 1, 1, 99);
      const maxPersons = clampNumber(prev?.maxPersons ?? minPersons, minPersons, 99);
      const persons = clampNumber(prev?.persons ?? basePeople, minPersons, maxPersons);
      normalizedMap.set(id, {
        id,
        title: prev?.title || `Actividad ${normalizedMap.size + 1}`,
        description: prev?.description || '',
        durationLabel: prev?.durationLabel || '',
        location: prev?.location || '',
        pricePerPerson: safeNumber(prev?.pricePerPerson),
        currency: prev?.currency || fallbackCurrency,
        persons,
        selected: Boolean(prev?.selected),
        minPersons,
        maxPersons,
        type: typeMeta.type,
        typeLabel: typeMeta.label,
        source: prev?.source || 'previous'
      });
    });

    asideEntries.forEach((entry, index) => {
      let record = normalizedMap.get(entry.id);
      const prev = previous.get(entry.id);
      const typeMeta = resolveActivityType(record?.type ?? prev?.type);
      if (!record) {
        const minPersons = clampNumber(prev?.minPersons ?? 1, 1, 99);
        const maxPersons = clampNumber(prev?.maxPersons ?? minPersons, minPersons, 99);
        const persons = clampNumber(prev?.persons ?? basePeople, minPersons, maxPersons);
        record = {
          id: entry.id,
          title: entry.title,
          description: prev?.description || '',
          durationLabel: prev?.durationLabel || '',
          location: prev?.location || '',
          pricePerPerson: safeNumber(prev?.pricePerPerson),
          currency: prev?.currency || fallbackCurrency,
          persons,
          selected: Boolean(prev?.selected),
          minPersons,
          maxPersons,
          type: typeMeta.type,
          typeLabel: typeMeta.label,
          source: 'form'
        };
        normalizedMap.set(entry.id, record);
      }
      const minPersons = clampNumber(record.minPersons ?? 1, 1, 99);
      const maxPersons = clampNumber(record.maxPersons ?? minPersons, minPersons, 99);
      const persons = clampNumber(record.persons ?? prev?.persons ?? basePeople, minPersons, maxPersons);
      record.minPersons = minPersons;
      record.maxPersons = maxPersons;
      record.persons = persons;
      record.selected = entry.checked || Boolean(prev?.selected) || Boolean(record.selected);
      if (!record.title) {
        record.title = entry.title;
      }
    });

    const list = Array.from(normalizedMap.values()).map((item) => {
      const minPersons = clampNumber(item.minPersons ?? 1, 1, 99);
      const maxPersons = clampNumber(item.maxPersons ?? minPersons, minPersons, 99);
      const persons = clampNumber(item.persons ?? basePeople, minPersons, maxPersons);
      return {
        ...item,
        minPersons,
        maxPersons,
        persons,
        selected: Boolean(item.selected)
      };
    });

    list.sort((a, b) => {
      const priority = (value) => {
        const key = (value || '').toString().toLowerCase();
        if (key === 'vibe') return 0;
        if (key === 'tour') return 1;
        if (key === 'experience') return 2;
        if (key === 'activity') return 3;
        return 4;
      };
      const pa = priority(a.type);
      const pb = priority(b.type);
      if (pa !== pb) return pa - pb;
      return a.title.localeCompare(b.title, 'es');
    });

    return list;
  }

  function readExtras(resources) {
    const inputs = $all('#extrasWrap input[type="checkbox"]');
    const selected = new Set(inputs.filter((input) => input.checked).map((input) => normalizeExtraId(input.id)));

    let extrasData = [];
    if (Array.isArray(resources.extras) && resources.extras.length) {
      extrasData = resources.extras.map((extra) => {
        const id = normalizeExtraId(extra.id || extra.code || extra.slug || extra.name || Math.random().toString(36).slice(2, 8));
        const price = safeNumber(extra.price ?? extra.pricePerUnit ?? extra.pricePerService ?? extra.amount);
        return {
          id,
          title: extra.title || extra.name || 'Servicio adicional',
          price,
          currency: extra.currency || resources.currency || state.currency || 'MXN',
          description: extra.description || '',
          selected: selected.has(id)
        };
      });
    } else {
      extrasData = inputs.map((input) => {
        const label = input.closest('label');
        const id = normalizeExtraId(input.id);
        const title = label ? label.textContent.replace(/\s+/g, ' ').trim() : 'Servicio adicional';
        return {
          id,
          title,
          price: null,
          currency: resources.currency || state.currency || 'MXN',
          description: '',
          selected: selected.has(id)
        };
      });
    }

    return extrasData;
  }

  function readViveLodgings(resources) {
    const catalog = Array.isArray(resources.viveLodgings)
      ? resources.viveLodgings
      : Array.isArray(resources.viveStays)
        ? resources.viveStays
        : [];
    return catalog.map((item, index) => {
      const id = String(item?.id ?? item?.code ?? item?.slug ?? `vive-${index + 1}`);
      const pricePerNight = safeNumber(item?.pricePerNight ?? item?.price ?? item?.amount);
      return {
        ...item,
        id,
        pricePerNight,
        currency: item?.currency || resources.currency || state.currency || 'MXN'
      };
    });
  }

  async function fetchTransformedSuggestions(results, people) {
    if (!Array.isArray(results) || !results.length) return null;
    try {
      const response = await fetch('suggest_transform.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ results, people })
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return await response.json();
    } catch (error) {
      console.warn('suggest_transform request failed', error);
      return null;
    }
  }

  function normalizeTransformRoom(room, fallbackName, index) {
    if (!room) return null;
    const count = clampNumber(room.count ?? 1, 1, 99);
    const capacity = safeNumber(room.capacity) ?? null;
    const subtotal = safeNumber(room.subtotalPerNight);
    const price = safeNumber(room.pricePerNight) ?? (subtotal != null && count > 0 ? subtotal / count : null);
    const totalCapacity =
      safeNumber(room.totalCapacity) ??
      (capacity != null && count > 0 ? capacity * count : null);
    return {
      lodgingId: room.lodgingId || null,
      lodgingName: room.lodgingName || fallbackName || 'Hospedaje Vive la Vibe',
      roomTypeId: room.roomTypeId || `vive-room-${Date.now()}-${index}`,
      roomTypeName: room.roomTypeName || `Habitación ${index + 1}`,
      count,
      capacity,
      totalCapacity,
      pricePerNight: price,
      subtotalPerNight: subtotal ?? (Number.isFinite(price) ? price * count : null),
      images: Array.isArray(room.images) ? room.images.filter(Boolean) : [],
      currency: room.currency || state.currency,
      description: room.description || '',
      location: room.location || null,
      minOccupancy: room.minOccupancy ?? null
    };
  }

  function normalizeTransformCombo(source, indexLabel) {
    if (!source) return null;
    const roomsRaw = Array.isArray(source.rooms) ? source.rooms : [];
    const normalizedRooms = roomsRaw
      .map((room, index) => normalizeTransformRoom(room, source.lodgingName, index))
      .filter(Boolean);
    const sum = normalizedRooms.reduce(
      (acc, room) => acc + (safeNumber(room.subtotalPerNight) ?? 0),
      0
    );
    const capacity = normalizedRooms.reduce(
      (acc, room) => acc + (safeNumber(room.totalCapacity) ?? 0),
      0
    );
    const idSource =
      source.id ??
      source.comboId ??
      source.signature ??
      `${source.lodgingId || 'combo'}-${indexLabel}`;
    return {
      id: String(idSource),
      lodgingId: source.lodgingId || null,
      lodgingName: source.lodgingName || source.title || 'Hospedaje Vive la Vibe',
      title: source.title || source.lodgingName || 'Hospedaje Vive la Vibe',
      name: source.lodgingName || source.title || 'Hospedaje Vive la Vibe',
      description: source.description || '',
      rooms: normalizedRooms,
      roomsCount: source.roomsCount ?? normalizedRooms.length,
      totalCapacity: safeNumber(source.totalCapacity) ?? capacity,
      totalPerNight: safeNumber(source.totalPerNight) ?? sum,
      currency:
        source.currency ||
        normalizedRooms.find((room) => room.currency)?.currency ||
        state.currency,
      images: Array.isArray(source.images) ? source.images.filter(Boolean) : [],
      isBest: Boolean(source.isBest),
      signature: `${source.lodgingId || idSource}|${
        source.roomsCount ?? normalizedRooms.length
      }|${Math.round((safeNumber(source.totalPerNight) ?? sum) || 0)}`
    };
  }

  function normalizeTransformCombos(data) {
    const list = [];
    if (Array.isArray(data?.combos)) {
      data.combos.forEach((combo, index) => {
        const normalized = normalizeTransformCombo(combo, index + 1);
        if (normalized) list.push(normalized);
      });
    }
    let bestId = null;
    if (data?.bestCombination) {
      const best = normalizeTransformCombo(data.bestCombination, 'best');
      if (best) {
        const existing = list.find((item) => item.signature === best.signature);
        if (existing) {
          existing.isBest = true;
          bestId = existing.id;
        } else {
          best.isBest = true;
          list.unshift(best);
          bestId = best.id;
        }
      }
    }
    if (!bestId && list.length) {
      list[0].isBest = true;
      bestId = list[0].id;
    }
    return { list, bestId };
  }

  function pickClosestComboByPrice(list, targetPrice) {
    if (!Array.isArray(list) || !list.length || !Number.isFinite(targetPrice)) return null;
    let best = null;
    let bestDiff = Infinity;
    list.forEach((combo) => {
      const value = safeNumber(combo.totalPerNight);
      if (!Number.isFinite(value)) return;
      const diff = Math.abs(value - targetPrice);
      if (diff < bestDiff) {
        bestDiff = diff;
        best = combo;
      }
    });
    return best;
  }

  function cloneComboForBands(combo) {
    if (!combo || !Array.isArray(combo.rooms) || !combo.rooms.length) return null;
    return {
      lodgingId: combo.lodgingId,
      lodgingName: combo.lodgingName,
      rooms: combo.rooms.map((room) => ({
        lodgingId: room.lodgingId,
        lodgingName: room.lodgingName,
        roomTypeId: room.roomTypeId,
        roomTypeName: room.roomTypeName,
        count: room.count,
        capacity: room.capacity,
        totalCapacity: room.totalCapacity,
        pricePerNight: room.pricePerNight,
        subtotalPerNight: room.subtotalPerNight,
        images: room.images,
        currency: room.currency,
        description: room.description,
        location: room.location,
        minOccupancy: room.minOccupancy
      })),
      totalPerNight: combo.totalPerNight,
      totalCapacity: combo.totalCapacity,
      roomsCount: combo.roomsCount,
      images: combo.images || [],
      diff: 0
    };
  }

  function buildBandsFromTransform(data, combosList) {
    const sorted = combosList.slice().sort((a, b) => {
      const av = safeNumber(a.totalPerNight) ?? Infinity;
      const bv = safeNumber(b.totalPerNight) ?? Infinity;
      return av - bv;
    });
    const friendly = sorted[0] || null;
    const premium = sorted[sorted.length - 1] || friendly;
    const targetStandard = safeNumber(data?.bands?.standard?.price);
    const standard =
      pickClosestComboByPrice(sorted, targetStandard) ||
      sorted[Math.min(1, sorted.length - 1)] ||
      friendly;

    const bands = {
      friendly: safeNumber(data?.bands?.friendly?.price) ?? safeNumber(friendly?.totalPerNight) ?? null,
      standard: safeNumber(data?.bands?.standard?.price) ?? safeNumber(standard?.totalPerNight) ?? null,
      premium: safeNumber(data?.bands?.premium?.price) ?? safeNumber(premium?.totalPerNight) ?? null,
      min: safeNumber(data?.bands?.standard?.min) ?? safeNumber(data?.bands?.min) ?? safeNumber(friendly?.totalPerNight) ?? null,
      max: safeNumber(data?.bands?.standard?.max) ?? safeNumber(data?.bands?.max) ?? safeNumber(premium?.totalPerNight) ?? null,
      average: safeNumber(data?.bands?.standard?.price) ?? safeNumber(standard?.totalPerNight) ?? null
    };

    return {
      combos: {
        friendly: cloneComboForBands(friendly),
        standard: cloneComboForBands(standard),
        premium: cloneComboForBands(premium)
      },
      bands
    };
  }

  function buildSuggestionsFromTransform(data, entries) {
    if (!data) return null;
    const normalized = normalizeTransformCombos(data);
    if (!normalized.list.length) return null;
    const bandData = buildBandsFromTransform(data, normalized.list);
    const hasCombos = Object.values(bandData.combos).some(
      (combo) => combo && Array.isArray(combo.rooms) && combo.rooms.length
    );
    if (!hasCombos) return null;
    return {
      combos: bandData.combos,
      bands: bandData.bands,
      source: 'transform',
      entries: Array.isArray(entries) ? entries : [],
      viveCombos: normalized.list,
      bestViveId: normalized.bestId
    };
  }

  function buildSuggestions(resources, people, availabilityResults) {
  const suggest = window.VIVE_SUGGEST;
  if (!suggest) {
    return { bands: {}, combos: {}, source: 'none', entries: [] };
  }

  // 1) Collect entries (prefer availability, then resources)
  let source = 'resources';
  let entries = [];

  if (Array.isArray(availabilityResults) && availabilityResults.length) {
    try {
      entries = suggest.buildEntriesFromAvailability(availabilityResults, { resources, people }) || [];
      source = 'availability';
    } catch (e) {
      console.warn('buildEntriesFromAvailability failed:', e);
    }
  }

  if (!entries.length) {
    // Support either signature: (resources, people) or (people)
    try {
      entries =
        (suggest.buildEntriesFromResources?.(resources, people)) ??
        (suggest.buildEntriesFromResources?.(people)) ??
        [];
      source = 'resources';
    } catch (e) {
      console.warn('buildEntriesFromResources failed:', e);
    }
  }

  if (!entries.length) {
    return { bands: {}, combos: {}, source, entries: [] };
  }

  // 2) Sanity-check and sort
  entries = entries.filter(e => Number.isFinite(e.pricePerNight));
  if (!entries.length) {
    return { bands: {}, combos: {}, source, entries: [] };
  }

  const sortedEntries = entries.slice().sort((a, b) => a.pricePerNight - b.pricePerNight);
  const minEntry = sortedEntries[0];
  const maxEntry = sortedEntries[sortedEntries.length - 1];

  const priceBands = suggest.computeBands(entries);
  const avgPriceRaw = sortedEntries.reduce((sum, e) => sum + e.pricePerNight, 0) / sortedEntries.length;
  const roundedAverage = Number.isFinite(priceBands.standard) ? priceBands.standard : avgPriceRaw;

  // 3) Build combos for each band
  const aggregate = suggest.aggregateLodgings(entries);
  const combos = { friendly: null, standard: null, premium: null };
  const ensureRooms = c => c && Array.isArray(c.rooms) && c.rooms.length;

  // friendly
  const friendlyTarget = priceBands.friendly ?? priceBands.min ?? priceBands.average ?? null;
  let friendlyCombo = suggest.findBestCombination(aggregate, people, friendlyTarget, 'min');
  if (!ensureRooms(friendlyCombo)) {
    friendlyCombo = suggest.findBestCombination(aggregate, people, priceBands.average ?? null, 'min');
  }
  if (!ensureRooms(friendlyCombo) && minEntry) {
    friendlyCombo = suggest.comboFromEntry(minEntry, people, resources.currency);
  }
  combos.friendly = friendlyCombo;

  // premium
  const premiumTarget = priceBands.premium ?? priceBands.max ?? priceBands.average ?? null;
  let premiumCombo = suggest.findBestCombination(aggregate, people, premiumTarget, 'max');
  if (!ensureRooms(premiumCombo)) {
    premiumCombo = suggest.findBestCombination(aggregate, people, premiumTarget, 'min');
  }
  if (!ensureRooms(premiumCombo) && maxEntry) {
    premiumCombo = suggest.comboFromEntry(maxEntry, people, resources.currency);
  }
  combos.premium = premiumCombo;

  // standard (synthetic from average, else best target)
  if (Number.isFinite(roundedAverage) && roundedAverage > 0) {
    const requiredPeople = Math.max(1, people || 1);
    const currency = resources.currency || state.currency || 'MXN';
    const referenceCombo = ensureRooms(friendlyCombo) ? friendlyCombo : null;
    const referenceRooms = referenceCombo ? Math.max(1, referenceCombo.roomsCount || 1) : null;
    const referenceCapacity = referenceCombo && referenceCombo.totalCapacity
      ? Math.max(1, Math.floor(referenceCombo.totalCapacity / Math.max(1, referenceCombo.roomsCount || 1)))
      : null;
    const fallbackCapacity =
      Number.isFinite(minEntry?.capacity) && minEntry.capacity > 0 ? minEntry.capacity : requiredPeople;
    const roomCapacity = Math.max(1, referenceCapacity || fallbackCapacity);
    const roomsNeeded = Math.max(1, referenceRooms || Math.ceil(requiredPeople / roomCapacity));
    const totalCapacity = roomCapacity * roomsNeeded;
    const totalPerNight = roundedAverage * roomsNeeded;

    combos.standard = {
      lodgingId: '',
      lodgingName: 'Precio promedio',
      rooms: [{
        lodgingId: '',
        lodgingName: '',
        roomTypeId: 'average',
        roomTypeName: `Habitacion para ${roomCapacity} ${roomCapacity === 1 ? 'persona' : 'personas'}`,
        count: roomsNeeded,
        capacity: roomCapacity,
        totalCapacity,
        pricePerNight: roundedAverage,
        subtotalPerNight: totalPerNight,
        images: [],
        currency,
        description: '',
        location: null,
        minOccupancy: null
      }],
      totalPerNight,
      totalCapacity,
      roomsCount: roomsNeeded,
      images: [],
      diff: 0,
      synthetic: true
    };
  } else {
    const target = priceBands.standard ?? priceBands.average ?? priceBands.min ?? null;
    let standardCombo = suggest.findBestCombination(aggregate, people, target, 'target');
    if (!ensureRooms(standardCombo)) {
      standardCombo = suggest.findBestCombination(aggregate, people, target, 'min');
    }
    if (!ensureRooms(standardCombo) && sortedEntries.length) {
      standardCombo = suggest.comboFromEntry(sortedEntries[Math.floor(sortedEntries.length / 2)], people, resources.currency);
    }
    combos.standard = standardCombo;
  }

  // Last-chance fallback: clone a valid entry into all bands
  let hasValidCombo = Object.values(combos).some(ensureRooms);
  if (!hasValidCombo && sortedEntries.length) {
    const fallbackEntry = sortedEntries.find(e => Number.isFinite(e.pricePerNight) && e.pricePerNight > 0);
    if (fallbackEntry) {
      const fallbackCombo = suggest.comboFromEntry(fallbackEntry, people, resources.currency);
      if (fallbackCombo) {
        const baseCapacity = Number.isFinite(fallbackEntry.capacity) && fallbackEntry.capacity > 0
          ? fallbackEntry.capacity
          : Math.max(1, people || 1);
        const label = `Habitacion para ${baseCapacity} ${baseCapacity === 1 ? 'persona' : 'personas'}`;
        fallbackCombo.rooms = fallbackCombo.rooms.map(room => ({
          lodgingId: room.lodgingId,
          lodgingName: room.lodgingName,
          roomTypeId: room.roomTypeId,
          roomTypeName: label,
          count: room.count,
          capacity: room.capacity,
          totalCapacity: room.totalCapacity,
          pricePerNight: room.pricePerNight,
          subtotalPerNight: room.subtotalPerNight,
          images: room.images,
          currency: room.currency,
          description: '',
          location: room.location,
          minOccupancy: room.minOccupancy
        }));
        BAND_ORDER.forEach(b => { combos[b] = fallbackCombo; });
        hasValidCombo = true;
      }
    }
  }

  // 4) Price bands returned to UI (use combo totals when available)
  const bands = {
    friendly: combos.friendly ? combos.friendly.totalPerNight : priceBands.friendly,
    standard: combos.standard ? combos.standard.totalPerNight : priceBands.standard,
    premium:  combos.premium  ? combos.premium.totalPerNight  : priceBands.premium,
    min:      combos.friendly ? combos.friendly.totalPerNight : priceBands.min,
    max:      combos.premium  ? combos.premium.totalPerNight  : priceBands.max,
    average:  combos.standard ? combos.standard.totalPerNight : priceBands.average
  };

  return { bands, combos, source, entries };
}


  function determineActiveBand(selectedBands, combos) {
    const preferred = (selectedBands.length ? selectedBands : BAND_ORDER).filter((band) => BAND_ORDER.includes(band));
    for (const band of preferred) {
      const combo = combos[band];
      if (combo && Array.isArray(combo.rooms) && combo.rooms.length) return band;
    }
    for (const fallback of BAND_ORDER) {
      const combo = combos[fallback];
      if (combo && Array.isArray(combo.rooms) && combo.rooms.length) return fallback;
    }
    return preferred[0] || BAND_ORDER[0];
  }

  function setLodgingMode(useViveRecommendations) {
    const nextTab = useViveRecommendations ? 'vive' : 'example';
    if (state.lodgingTab === nextTab) {
      state.lodgingSelections.example = nextTab === 'example';
      return;
    }
    state.lodgingTab = nextTab;
    state.lodgingSelections.example = nextTab === 'example';
    if (nextTab === 'vive') {
      if (!state.lodgingSelections.vive && state.viveBestId) {
        state.lodgingSelections.vive = state.viveBestId;
      }
    }
    recomputeTotals();
    renderLodging();
    renderSummary();
  }

  function toggleViveSelection(id, isSelected) {
    if (id == null) return;
    const normalized = String(id);
    const exists = state.viveLodgings.some((offer) => {
      const offerId = offer?.id ?? offer?.code ?? offer?.slug ?? offer?.lodgingId;
      if (offerId == null) return false;
      return String(offerId) === normalized;
    });
    if (!exists) return;
    if (isSelected) {
      state.lodgingSelections.vive = normalized;
      state.lodgingSelections.example = false;
      state.lodgingTab = 'vive';
    } else if (state.lodgingSelections.vive === normalized) {
      // Mantener al menos una selección activa
      state.lodgingSelections.vive = normalized;
    }
    recomputeTotals();
    renderAll();
  }

  function adjustActivityPersons(id, delta) {
    if (!id) return;
    const activity = state.activities.find((item) => item.id === id);
    if (!activity) return;
    const min = Number.isFinite(activity.minPersons) ? activity.minPersons : 1;
    const max = Number.isFinite(activity.maxPersons) ? activity.maxPersons : 99;
    const current = Number.isFinite(activity.persons) ? activity.persons : Math.max(min, state.people || min);
    const next = clampNumber(current + delta, min, max);
    activity.persons = next;
    if (!activity.selected) {
      activity.selected = true;
      const asideInput = document.getElementById(`act_${id}`);
      if (asideInput) asideInput.checked = true;
    }
    recomputeTotals();
    renderActivities();
    renderSummary();
  }

  function setActivityPersons(id, value) {
    if (!id) return;
    const activity = state.activities.find((item) => item.id === id);
    if (!activity) return;
    const min = Number.isFinite(activity.minPersons) ? activity.minPersons : 1;
    const max = Number.isFinite(activity.maxPersons) ? activity.maxPersons : 99;
    activity.persons = clampNumber(value, min, max);
    if (!activity.selected) {
      activity.selected = true;
      const asideInput = document.getElementById(`act_${id}`);
      if (asideInput) asideInput.checked = true;
    }
    recomputeTotals();
    renderActivities();
    renderSummary();
  }

  function setActivitySelected(id, isSelected) {
    if (!id) return;
    const activity = state.activities.find((item) => item.id === id);
    if (!activity) return;
    activity.selected = Boolean(isSelected);
    if (activity.selected) {
      const min = Number.isFinite(activity.minPersons) ? activity.minPersons : 1;
      const max = Number.isFinite(activity.maxPersons) ? activity.maxPersons : 99;
      const base = Number.isFinite(activity.persons) ? activity.persons : Math.max(min, state.people || min);
      activity.persons = clampNumber(base, min, max);
    }
    const asideInput = document.getElementById(`act_${id}`);
    if (asideInput) asideInput.checked = activity.selected;
    recomputeTotals();
    renderActivities();
    renderSummary();
  }

  function toggleExtra(id, isSelected) {
    const extra = state.extras.find((item) => item.id === id);
    if (!extra) return;
    extra.selected = Boolean(isSelected);
    const asideInput = document.getElementById(id);
    if (asideInput) asideInput.checked = extra.selected;
    recomputeTotals();
    renderExtras();
    renderSummary();
  }

  function recomputeTotals() {
    const selection = getSelectedLodging();
    const perNight = selection && Number.isFinite(selection.perNight) ? selection.perNight : null;
    const lodgingSubtotal = perNight != null ? perNight * state.nights : 0;
    state.totals.lodging = {
      perNight: perNight ?? 0,
      subtotal: lodgingSubtotal,
      hasPrice: perNight != null
    };

    state.totals.activities = state.activities.reduce((sum, activity) => {
      if (!activity.selected) return sum;
      if (!Number.isFinite(activity.pricePerPerson)) return sum;
      const min = Number.isFinite(activity.minPersons) ? activity.minPersons : 1;
      const max = Number.isFinite(activity.maxPersons) ? activity.maxPersons : 99;
      const persons = clampNumber(activity.persons ?? state.people ?? min, min, max);
      return sum + activity.pricePerPerson * persons;
    }, 0);

    state.totals.extras = state.extras.reduce((sum, extra) => {
      if (!extra.selected) return sum;
      if (!Number.isFinite(extra.price)) return sum;
      return sum + extra.price;
    }, 0);

    const totalWithLodging = state.totals.lodging.hasPrice ? state.totals.lodging.subtotal : 0;
    const variable = state.totals.activities + state.totals.extras;
    state.totals.grand = totalWithLodging + variable;
    state.totals.perPerson = state.people > 0 ? state.totals.grand / state.people : state.totals.grand;
  }

  function renderSummary() {
      if (refs.subtitle) {
        const nightsLabel = `${state.nights} ${state.nights === 1 ? 'noche' : 'noches'}`;
        const peopleLabel = `${state.people} ${state.people === 1 ? 'viajero' : 'viajeros'}`;
        const windowLabel = state.checkIn && state.checkOut
          ? `${formatDate(state.checkIn)} al ${formatDate(state.checkOut)}`
          : 'fechas flexibles';
        refs.subtitle.textContent = `Tres planes para vivir tu viaje a tu ritmo - ${windowLabel} - ${nightsLabel} - ${peopleLabel}`;
      }

    if (refs.total) {
      const hasAny = state.totals.lodging.hasPrice || state.totals.activities > 0 || state.totals.extras > 0;
      const baseTotal = state.totals.grand;
      refs.total.textContent = hasAny ? formatCurrency(baseTotal, state.currency) : 'A definir';
    }

    if (refs.chips) {
      const chips = [];
      chips.push(`${state.people} ${state.people === 1 ? 'viajero' : 'viajeros'}`);
      chips.push(`${state.nights} ${state.nights === 1 ? 'noche' : 'noches'}`);
      if (state.checkIn) chips.push(`Entrada ${formatDate(state.checkIn)}`);
      if (state.checkOut) chips.push(`Salida ${formatDate(state.checkOut)}`);
      chips.push(state.currency);
      refs.chips.innerHTML = chips.map((label) => `<span class="calc-chip">${label}</span>`).join('');
    }
    if (refs.lodgingSummary) {
      const isOpen = refs.lodgingSection && refs.lodgingSection.open;
      if (isOpen) {
        refs.lodgingSummary.textContent = '';
      } else {
        const selection = getSelectedLodging();
        if (!selection) {
          refs.lodgingSummary.textContent = 'Selecciona una opcion para tu hospedaje.';
        } else if (selection.type === 'example' && selection.combo) {
          const list = roomsTextList(selection.combo);
          const nightly = Number.isFinite(selection.perNight)
            ? `${formatCurrency(selection.perNight, state.currency)} por noche`
            : 'Precio por definir';
          refs.lodgingSummary.textContent = `${list} - ${nightly}`;
        } else if (selection.type === 'vive' && selection.offer) {
          const nightly = Number.isFinite(selection.perNight)
            ? `${formatCurrency(selection.perNight, selection.offer.currency || state.currency)} por noche`
            : 'Precio por definir';
          refs.lodgingSummary.textContent = `${selection.offer.title || selection.label} - ${nightly}`;
        } else {
          refs.lodgingSummary.textContent = 'Selecciona una opcion para tu hospedaje.';
        }
      }
    }

    if (refs.activitiesSummary) {
      const selected = state.activities.filter((activity) => activity.selected);
      if (!selected.length) {
        refs.activitiesSummary.textContent = 'Sin actividades seleccionadas';
      } else {
        const labels = selected.map((activity) => {
          const min = Number.isFinite(activity.minPersons) ? activity.minPersons : 1;
          const max = Number.isFinite(activity.maxPersons) ? activity.maxPersons : 99;
          const persons = clampNumber(activity.persons ?? state.people ?? min, min, max);
          return `${activity.title} (${persons} pax)`;
        }).join(', ');
        refs.activitiesSummary.textContent = labels;
      }
    }

    if (refs.extrasSummary) {
      const selected = state.extras.filter((extra) => extra.selected);
      if (!selected.length) {
        refs.extrasSummary.textContent = 'Seleccion opcional';
      } else {
        const total = selected.reduce((sum, extra) => {
          if (!Number.isFinite(extra.price)) return sum;
          return sum + extra.price;
        }, 0);
        refs.extrasSummary.textContent = `${selected.length} ${selected.length === 1 ? 'extra' : 'extras'} - ${total ? formatCurrency(total, state.currency) : 'Precio a definir'}`;
      }
    }

    if (refs.perPerson) {
      if (state.totals.grand > 0 && state.people > 0) {
        refs.perPerson.textContent = `Por persona: ${formatCurrency(state.totals.perPerson, state.currency)}`;
      } else {
        refs.perPerson.textContent = 'Define servicios para ver el total por persona.';
      }
    }
  }

  function renderBandSwitch() {
    if (!refs.bandSwitch) return;
    const fragment = document.createDocumentFragment();
    const order = state.bandOrder.length ? state.bandOrder : BAND_ORDER;

    order.forEach((band) => {
      if (!BAND_LABELS[band]) return;
      const combo = state.combos[band];
      const hasCombo = combo && Array.isArray(combo.rooms) && combo.rooms.length;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `calc-band-btn${state.activeBand === band ? ' active' : ''}`;
      button.dataset.band = band;
      const priceLabel = hasCombo && Number.isFinite(combo.totalPerNight)
        ? ` - ${formatCurrency(combo.totalPerNight, state.currency)}/noche`
        : '';
      button.textContent = `${BAND_LABELS[band]}${priceLabel}`;
      button.disabled = !hasCombo;
      fragment.appendChild(button);
    });

    refs.bandSwitch.innerHTML = '';
    refs.bandSwitch.appendChild(fragment);
  }

  function renderLodging() {
    const isViveMode = state.lodgingTab === 'vive';
    if (refs.viveToggle) {
      refs.viveToggle.checked = isViveMode;
      const switchWrap = refs.viveToggle.closest('.calc-vive-switch');
      if (switchWrap) switchWrap.classList.toggle('active', isViveMode);
    }
    if (refs.bandSwitch) {
      refs.bandSwitch.classList.toggle('hidden', isViveMode);
    }
    if (refs.examplePanel) {
      refs.examplePanel.classList.toggle('active', !isViveMode);
    }
    if (refs.vivePanel) {
      refs.vivePanel.classList.toggle('active', isViveMode);
    }

    renderBandSwitch();
    renderExampleRooms();
    renderViveOffers();
    renderLodgingTotal();
    syncLodgingPanelsHeight();
  }

  function renderExampleRooms() {
    if (!refs.roomsContainer) return;
    refs.roomsContainer.innerHTML = '';
    const combo = getExampleCombo();
    if (!combo) {
      const msg = document.createElement('p');
      msg.className = 'calc-empty';
      msg.textContent = 'No encontramos una combinacion que cubra a tu grupo con los datos actuales.';
      refs.roomsContainer.appendChild(msg);
      return;
    }

    combo.rooms.forEach((room) => {
      const card = document.createElement('article');
      card.className = 'calc-room-card';
      card.innerHTML = `
        <div class="calc-room-head">
          <h4>${room.roomTypeName || 'Habitacion'}</h4>
          <span class="calc-room-capacity">${room.totalCapacity || room.capacity || '?'} pax</span>
        </div>
        <p class="calc-room-meta">${room.count > 1 ? `${room.count} unidades - ` : ''}${formatCurrency(room.pricePerNight, room.currency || state.currency)} por noche</p>
        ${room.description ? `<p class="calc-room-desc">${room.description}</p>` : ''}
      `;
      refs.roomsContainer.appendChild(card);
    });
  }

  function renderViveOffers() {
    if (!refs.viveList) return;
    refs.viveList.innerHTML = '';
    if (!state.viveLodgings.length) {
      const msg = document.createElement('p');
      msg.className = 'calc-empty';
      msg.textContent = 'Muy pronto agregaremos opciones exclusivas de Vive la Vibe.';
      refs.viveList.appendChild(msg);
      return;
    }

    const selectedId = state.lodgingSelections.vive ? String(state.lodgingSelections.vive) : null;
    state.viveLodgings.forEach((offer, index) => {
      if (!offer) return;
      const rawId = offer.id ?? offer.code ?? offer.slug ?? offer.lodgingId ?? `vive-${index + 1}`;
      const id = String(rawId);
      const safeId = id.replace(/"/g, '&quot;');
      const nightlyValue = safeNumber(offer.totalPerNight ?? offer.pricePerNight ?? offer.price ?? offer.amount);
      const currency = offer.currency || state.currency;
      const nightly = Number.isFinite(nightlyValue) ? formatCurrency(nightlyValue, currency) : 'Precio por definir';
      const selected = selectedId ? selectedId === id : Boolean(offer.isBest);
      if (!state.lodgingSelections.vive && selected) {
        state.lodgingSelections.vive = id;
      }

      const card = document.createElement('article');
      card.className = `calc-vive-card${selected ? ' selected' : ''}`;
      card.innerHTML = `
        <div class="calc-vive-card-head">
          <label class="calc-vive-toggle">
            <input type="checkbox" data-vive-id="${safeId}" ${selected ? 'checked' : ''}>
            <span>${offer.lodgingName || offer.title || offer.name || 'Hospedaje Vive la Vibe'}</span>
          </label>
          <span class="calc-vive-price">${nightly}</span>
        </div>
        ${offer.description ? `<p class="calc-vive-meta">${offer.description}</p>` : ''}
      `;

      const roomsWrap = document.createElement('div');
      roomsWrap.className = 'calc-vive-rooms';
      if (Array.isArray(offer.rooms) && offer.rooms.length) {
        offer.rooms.forEach((room) => {
          if (!room) return;
          const cardRoom = document.createElement('article');
          cardRoom.className = 'calc-room-card';
          const perNight = safeNumber(room.pricePerNight) ?? safeNumber(room.subtotalPerNight);
          cardRoom.innerHTML = `
            <div class="calc-room-head">
              <h4>${room.roomTypeName || 'Habitacion'}</h4>
              <span class="calc-room-capacity">${room.totalCapacity || room.capacity || '?'} pax</span>
            </div>
            <p class="calc-room-meta">${room.count > 1 ? `${room.count} unidades - ` : ''}${Number.isFinite(perNight) ? formatCurrency(perNight, room.currency || currency) : 'Precio por definir'} por noche</p>
            ${room.description ? `<p class="calc-room-desc">${room.description}</p>` : ''}
          `;
          roomsWrap.appendChild(cardRoom);
        });
      } else {
        const empty = document.createElement('p');
        empty.className = 'calc-empty';
        empty.textContent = 'Consulta un asesor para detalles de habitaciones.';
        roomsWrap.appendChild(empty);
      }
      card.appendChild(roomsWrap);
      refs.viveList.appendChild(card);
    });
  }

  function syncLodgingPanelsHeight() {
    if (!refs.lodgingPanels || !refs.examplePanel || !refs.vivePanel) return;
    const exampleHeight = refs.examplePanel.scrollHeight || 0;
    const viveHeight = refs.vivePanel.scrollHeight || 0;
    const max = Math.max(exampleHeight, viveHeight, 0);
    if (max > 0) {
      refs.lodgingPanels.style.minHeight = `${max}px`;
    } else {
      refs.lodgingPanels.style.removeProperty('min-height');
    }
  }

  function renderLodgingTotal() {
    if (!refs.lodgingTotal) return;
    const selection = getSelectedLodging();
    const nightsLabel = `${state.nights} ${state.nights === 1 ? 'noche' : 'noches'}`;
    if (!selection) {
      refs.lodgingTotal.classList.add('is-empty');
      refs.lodgingTotal.innerHTML = `
        <span>Subtotal hospedaje</span>
        <span>Activa un hospedaje para ver el estimado</span>
      `;
      return;
    }

    if (Number.isFinite(selection.perNight)) {
      refs.lodgingTotal.classList.remove('is-empty');
      const subtotal = selection.perNight * state.nights;
      const currency = selection.offer?.currency || state.currency;
      refs.lodgingTotal.innerHTML = `
        <span>Subtotal hospedaje</span>
        <span>${formatCurrency(selection.perNight, currency)} x ${nightsLabel} - ${formatCurrency(subtotal, currency)}</span>
      `;
    } else {
      refs.lodgingTotal.classList.add('is-empty');
      refs.lodgingTotal.innerHTML = `
        <span>Subtotal hospedaje</span>
        <span>Solicita tarifa exacta para esta opcion</span>
      `;
    }
  }
  function renderActivities() {
    if (!refs.activitiesList) return;
    refs.activitiesList.innerHTML = '';
    if (!state.activities.length) {
      const msg = document.createElement('p');
      msg.className = 'calc-empty';
      msg.textContent = 'No hay actividades disponibles para estas fechas.';
      refs.activitiesList.appendChild(msg);
      return;
    }

    const defaultLabelForType = (type) => {
      switch (type) {
        case 'vibe':
          return 'Actividades VIBE';
        case 'tour':
          return 'Tours';
        case 'experience':
          return 'Experiencias';
        case 'activity':
          return 'Actividades';
        default:
          return 'Otras experiencias';
      }
    };

    const groupMap = new Map();
    state.activities.forEach((activity) => {
      const rawKey = (activity.type || '').toString().trim().toLowerCase();
      const key = rawKey || 'otros';
      const label = activity.typeLabel || defaultLabelForType(key);
      const priority = key === 'tour' ? 0 : key === 'vibe' ? 1 : key === 'experience' ? 2 : key === 'activity' ? 3 : 4;
      if (!groupMap.has(key)) {
        groupMap.set(key, { key, label, priority, items: [] });
      }
      const group = groupMap.get(key);
      if (activity.typeLabel && !group.customLabel) {
        group.label = activity.typeLabel;
        group.customLabel = true;
      }
      group.items.push(activity);
    });

    const groups = Array.from(groupMap.values()).sort((a, b) => {
      if (a.priority !== b.priority) return a.priority - b.priority;
      return a.label.localeCompare(b.label, 'es');
    });

    const columnKeys = new Set(['tour', 'vibe']);
    const running = { index: 0 };

    const buildCard = (activity) => {
      const selected = Boolean(activity.selected);
      const minPersons = Number.isFinite(activity.minPersons) ? activity.minPersons : 1;
      const maxPersons = Number.isFinite(activity.maxPersons) ? activity.maxPersons : 99;
      const personsValue = clampNumber(activity.persons ?? state.people ?? minPersons, minPersons, maxPersons);
      const pricePerPerson = Number.isFinite(activity.pricePerPerson) ? activity.pricePerPerson : null;
      const priceLabel = pricePerPerson != null
        ? `${formatCurrency(pricePerPerson, activity.currency || state.currency)}/persona`
        : 'Precio a definir';

      const card = document.createElement('article');
      card.className = `calc-activity-item${selected ? ' selected' : ''}`;
      card.setAttribute('data-activity-id', activity.id);
      card.innerHTML = `
        <label class="calc-activity-toggle">
          <input type="checkbox" class="calc-activity-toggle-input" ${selected ? 'checked' : ''} aria-label="Seleccionar ${activity.title}">
          <span class="calc-activity-name" title="${activity.title}">${activity.title}</span>
        </label>
        <div class="calc-activity-extra">
          <span class="calc-activity-price">${priceLabel}</span>
          <div class="calc-activity-controls${selected ? '' : ' disabled'}">
            <div class="calc-stepper">
              <button type="button" class="calc-step" data-step="-1" aria-label="Restar personas">-</button>
              <input id="calc-act-${running.index}" type="number" class="calc-step-input" value="${personsValue}" min="${minPersons}" max="${maxPersons}" aria-label="Personas para ${activity.title}">
              <button type="button" class="calc-step" data-step="1" aria-label="Sumar personas">+</button>
            </div>
          </div>
        </div>
      `;
      running.index += 1;
      return card;
    };

    const buildGroup = (group) => {
      const classKey = group.key.replace(/[^a-z0-9]+/g, '-') || 'otros';
      const wrapper = document.createElement('section');
      wrapper.className = `calc-activity-group calc-activity-group-${classKey}`;
      wrapper.innerHTML = `
        <header class="calc-activity-group-head">
          <span class="calc-activity-group-title">${group.label}</span>
          <span class="calc-activity-group-count">${group.items.length} ${group.items.length === 1 ? 'opcion' : 'opciones'}</span>
        </header>
        <div class="calc-activity-group-body"></div>
      `;
      const body = wrapper.querySelector('.calc-activity-group-body');
      group.items.forEach((activity) => body.appendChild(buildCard(activity)));
      return wrapper;
    };

    const columnsWrapper = document.createElement('div');
    columnsWrapper.className = 'calc-activities-columns';
    const usedKeys = new Set();

    ['tour', 'vibe'].forEach((key) => {
      const group = groups.find((item) => item.key === key);
      if (!group) return;
      columnsWrapper.appendChild(buildGroup(group));
      usedKeys.add(key);
    });

    if (columnsWrapper.children.length) {
      refs.activitiesList.appendChild(columnsWrapper);
    }

    groups
      .filter((group) => !usedKeys.has(group.key))
      .forEach((group) => {
        refs.activitiesList.appendChild(buildGroup(group));
      });
  }

  function renderExtras() {
    if (!refs.extrasList) return;
    refs.extrasList.innerHTML = '';
    if (!state.extras.length) {
      const msg = document.createElement('p');
      msg.className = 'calc-empty';
      msg.textContent = 'Configura servicios adicionales desde el panel lateral.';
      refs.extrasList.appendChild(msg);
      return;
    }

    state.extras.forEach((extra) => {
      const row = document.createElement('label');
      row.className = 'calc-extra-row';
      row.innerHTML = `
        <input type="checkbox" data-extra-id="${extra.id}" ${extra.selected ? 'checked' : ''}>
        <div class="calc-extra-text">
          <span class="calc-extra-title">${extra.title}</span>
          <span class="calc-extra-meta">${Number.isFinite(extra.price) ? formatCurrency(extra.price, extra.currency || state.currency) : 'Precio a confirmar'}</span>
        </div>
      `;
      refs.extrasList.appendChild(row);
    });
  }

  function renderAll() {
    renderSummary();
    renderLodging();
    renderActivities();
    renderExtras();
  }
  function pushLodgingToCart() {
    if (!window.VIVE_CART || typeof window.VIVE_CART.addItem !== 'function') {
      alert('El carrito no esta disponible en esta vista.');
      return;
    }
    const selection = getSelectedLodging();
    if (!selection) {
      alert('Selecciona un hospedaje antes de agregarlo al viaje.');
      return;
    }
    if (selection.type === 'example' && selection.combo) {
      const combo = selection.combo;
      const description = roomsTextList(combo);
      const perNight = Number.isFinite(selection.perNight) ? selection.perNight : null;
      window.VIVE_CART.addItem({
        id: `${combo.lodgingId || 'lodging'}-${state.activeBand}`,
        type: 'lodging',
        title: combo.lodgingName || 'Hospedaje seleccionado',
        description: `${description} - ${state.nights} ${state.nights === 1 ? 'noche' : 'noches'}`,
        price: perNight,
        currency: state.currency,
        quantity: state.nights
      });
      return;
    }
    if (selection.type === 'vive' && selection.offer) {
      window.VIVE_CART.addItem({
        id: selection.offer.id || selection.offer.code || `vive-${Date.now()}`,
        type: 'lodging',
        title: selection.offer.title || selection.label,
        description: selection.offer.description || 'Hospedaje Vive la Vibe',
        price: Number.isFinite(selection.perNight) ? selection.perNight : null,
        currency: selection.offer.currency || state.currency,
        quantity: state.nights
      });
      return;
    }
    alert('No pudimos identificar los detalles del hospedaje seleccionado.');
  }

  function openCheckout() {
    if (!window.VIVE_CHECKOUT || typeof window.VIVE_CHECKOUT.open !== 'function') {
      alert('Checkout no disponible en esta vista.');
      return;
    }
    const selection = getSelectedLodging();
    const lodgingBlock =
      selection && selection.type === 'example' && selection.combo
        ? {
            type: 'fixed',
            lodgingId: selection.combo.lodgingId,
            lodgingName: selection.combo.lodgingName,
            rooms: selection.combo.rooms.map((room) => ({
              roomTypeId: room.roomTypeId,
              roomTypeName: room.roomTypeName,
              count: room.count,
              capacity: room.capacity,
              totalCapacity: room.totalCapacity,
              pricePerNight: room.pricePerNight
            })),
            pricePerNight: selection.perNight,
            subtotal: Number.isFinite(selection.perNight) ? selection.perNight * state.nights : null,
            currency: state.currency,
            nights: state.nights
          }
        : selection && selection.type === 'vive' && selection.offer
          ? {
              type: 'vive',
              lodgingId: selection.offer.id || selection.offer.code || 'vive',
              lodgingName: selection.offer.title || selection.label,
              rooms: [],
              pricePerNight: selection.perNight,
              subtotal: Number.isFinite(selection.perNight) ? selection.perNight * state.nights : null,
              currency: selection.offer.currency || state.currency,
              nights: state.nights,
              description: selection.offer.description || ''
            }
          : null;

    const activities = state.activities
      .filter((activity) => activity.selected)
      .map((activity) => {
        const min = Number.isFinite(activity.minPersons) ? activity.minPersons : 1;
        const max = Number.isFinite(activity.maxPersons) ? activity.maxPersons : 99;
        const persons = clampNumber(activity.persons ?? state.people ?? min, min, max);
        return {
          id: activity.id,
          title: activity.title,
          persons,
          pricePerPerson: activity.pricePerPerson,
          subtotal: Number.isFinite(activity.pricePerPerson) ? activity.pricePerPerson * persons : null,
          currency: activity.currency || state.currency
        };
      });

    const extras = state.extras
      .filter((extra) => extra.selected)
      .map((extra) => ({
        id: extra.id,
        title: extra.title,
        price: extra.price,
        currency: extra.currency || state.currency
      }));

    const totals = {
      type: state.totals.lodging.hasPrice ? 'fixed' : 'range',
      value: state.totals.grand,
      min: state.totals.activities + state.totals.extras,
      max: state.totals.lodging.hasPrice ? state.totals.grand : state.totals.activities + state.totals.extras
    };

    window.VIVE_CHECKOUT.open({
      currency: state.currency,
      checkIn: state.checkIn,
      checkOut: state.checkOut,
      nights: state.nights,
      lodging: lodgingBlock,
      activities,
      extras,
      totals
    });
  }
  async function handleCalculate(button, texts) {
    const original = button.textContent;
    const loadingLabel = texts.calc?.loading || 'Calculando...';
    button.disabled = true;
    button.textContent = loadingLabel;

    try {
      await (window.VIVE_RESOURCES_READY || Promise.resolve());
      const resources = window.VIVE_RESOURCES || {};
      const { checkIn, checkOut, nights, people, selectedBands } = readBooker();
      const activities = readActivities(resources, people);

      let availabilityResults = [];
      if (window.VIVE_API && typeof window.VIVE_API.searchAvailability === 'function') {
        try {
          const payload = await window.VIVE_API.searchAvailability({
            checkIn: checkIn || undefined,
            checkOut: checkOut || undefined,
            nights
          });
          if (payload && Array.isArray(payload.results)) {
            availabilityResults = payload.results;
          }
        } catch (error) {
          console.warn('searchAvailability failed', error);
        }
      }

      const extras = readExtras(resources);
      let suggestionPayload = null;
      if (availabilityResults.length) {
        const transformed = await fetchTransformedSuggestions(availabilityResults, people);
        if (transformed) {
          suggestionPayload = buildSuggestionsFromTransform(transformed, availabilityResults);
        }
      }
      if (!suggestionPayload) {
        suggestionPayload = buildSuggestions(resources, people, availabilityResults);
      }
      const {
        bands,
        combos,
        source,
        entries,
        viveCombos = [],
        bestViveId = null
      } = suggestionPayload;

      state.checkIn = checkIn;
      state.checkOut = checkOut;
      state.nights = nights;
      state.people = people;
      state.currency = resources.currency || state.currency || 'MXN';
      const preferredBands = Array.isArray(selectedBands) && selectedBands.length ? selectedBands : BAND_ORDER;
      const enforcedBands = BAND_ORDER.slice();
      state.selectedBands = enforcedBands.slice();
      state.bandOrder = enforcedBands.slice();
      state.bands = bands;
      state.combos = combos;
      state.availabilityResults = availabilityResults;
      state.suggestionsSource = source;
      state.suggestionEntries = entries;
      state.activities = activities;
      state.extras = extras;
      state.activeBand = determineActiveBand(preferredBands, combos);
      state.lodgingTab = 'example';
      state.lodgingSelections.example = true;
      const hasTransformCombos = Array.isArray(viveCombos) && viveCombos.length;
      if (hasTransformCombos) {
        state.viveLodgings = viveCombos;
        const fallbackId = viveCombos[0]?.id ?? viveCombos[0]?.lodgingId ?? null;
        state.viveBestId = bestViveId
          ? String(bestViveId)
          : fallbackId != null
            ? String(fallbackId)
            : null;
      } else {
        state.viveLodgings = readViveLodgings(resources);
        const fallbackId = state.viveLodgings[0]
          ? state.viveLodgings[0].id ?? state.viveLodgings[0].code ?? state.viveLodgings[0].slug ?? state.viveLodgings[0].lodgingId ?? null
          : null;
        state.viveBestId = fallbackId != null ? String(fallbackId) : null;
      }
      if (!state.viveLodgings.length) {
        state.lodgingSelections.vive = null;
      } else if (
        !state.lodgingSelections.vive ||
        !state.viveLodgings.some((offer) => {
          const offerId = offer?.id ?? offer?.code ?? offer?.slug ?? offer?.lodgingId;
          return offerId != null && String(offerId) === String(state.lodgingSelections.vive);
        })
      ) {
        const fallbackId = state.viveLodgings[0]
          ? state.viveLodgings[0].id ?? state.viveLodgings[0].code ?? state.viveLodgings[0].slug ?? state.viveLodgings[0].lodgingId ?? 'vive-1'
          : 'vive-1';
        state.lodgingSelections.vive = state.viveBestId || String(fallbackId);
      }

      recomputeTotals();
      renderAll();
      openOverlay();
    } catch (error) {
      console.error('Error calculando sugerencias', error);
      alert('No pudimos preparar la cotizacion en este momento. Intenta de nuevo.');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  docReady(() => {
    const texts = window.VIVE_TEXTS || {};
    ensureStyles();
    ensureOverlay(texts);

    const button = $('#btnSearch');
    if (button) {
      button.addEventListener('click', () => handleCalculate(button, texts));
    }
  });
})();









