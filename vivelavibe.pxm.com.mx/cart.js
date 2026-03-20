(function () {
  const STORAGE_KEY = 'VIVE_CART_ITEMS';
  const state = {
    items: []
  };

  let overlay;
  let listEl;
  let totalEl;
  let emptyEl;
  let navButton;
  let navCountEl;

  function loadFromStorage() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return [];
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return [];
      return parsed.map((item) => ({
        ...item,
        quantity: Math.max(1, parseInt(item.quantity || 1, 10))
      }));
    } catch {
      return [];
    }
  }

  function persist() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state.items));
    } catch {
      // ignore storage quota errors
    }
  }

  function defaultCurrency() {
    return (window.VIVE_RESOURCES && window.VIVE_RESOURCES.currency) || 'MXN';
  }

  function formatCurrency(value, currency) {
    if (typeof value !== 'number' || !isFinite(value)) return 'A definir';
    try {
      return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: currency || defaultCurrency(),
        maximumFractionDigits: 0
      }).format(value);
    } catch {
      return `${value} ${currency || defaultCurrency()}`;
    }
  }

  function ensureStyles() {
    if (document.getElementById('viveCartStyles')) return;
    const style = document.createElement('style');
    style.id = 'viveCartStyles';
    style.textContent = `
      #viveCartOverlay {
        position: fixed;
        inset: 0;
        background: rgba(6, 11, 22, 0.74);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 520;
        padding: 20px;
      }
      #viveCartOverlay.active { display: flex; }
      .vcart-panel {
        width: min(520px, 100%);
        max-height: 92vh;
        background: var(--surface-strong, #0e1627);
        border-radius: 18px;
        border: 1px solid color-mix(in srgb, var(--muted, #badfdb) 55%, transparent);
        box-shadow: 0 20px 50px rgba(0,0,0,0.45);
        display: flex;
        flex-direction: column;
        overflow: hidden;
      }
      .vcart-header {
        padding: 18px 22px;
        border-bottom: 1px solid color-mix(in srgb, var(--muted, #badfdb) 55%, transparent);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
      }
      .vcart-header h3 {
        margin: 0;
        font-size: 1.2rem;
      }
      .vcart-close {
        background: transparent;
        border: 0;
        color: var(--text-soft, #72506b);
        font-size: 1.4rem;
        cursor: pointer;
        line-height: 1;
        padding: 4px 8px;
      }
      .vcart-body {
        flex: 1;
        overflow-y: auto;
        padding: 18px 22px;
        display: grid;
        gap: 16px;
      }
      .vcart-empty {
        margin: 0;
        text-align: center;
        color: var(--text-soft, #72506b);
      }
      .vcart-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: 12px;
      }
      .vcart-item {
        border: 1px solid color-mix(in srgb, var(--muted, #badfdb) 55%, transparent);
        border-radius: 14px;
        padding: 14px;
        background: color-mix(in srgb, var(--surface, #fffdf7) 94%, transparent);
        display: grid;
        gap: 10px;
      }
      .vcart-item header {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
      }
      .vcart-item h4 {
        margin: 0;
        font-size: 1.05rem;
      }
      .vcart-item small {
        color: var(--text-soft, #72506b);
      }
      .vcart-item p {
        margin: 0;
        color: var(--text-soft, #72506b);
        font-size: 0.9rem;
      }
      .vcart-controls {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
      }
      .vcart-controls label {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--text-soft, #72506b);
        font-size: 0.85rem;
      }
      .vcart-controls input {
        width: 70px;
        padding: 6px;
        border-radius: 10px;
        border: 1px solid color-mix(in srgb, var(--muted, #badfdb) 60%, transparent);
        background: var(--surface, #fffdf7);
        color: var(--text, #e5e7eb);
      }
      .vcart-price {
        font-weight: 700;
      }
      .vcart-remove {
        margin-left: auto;
        background: transparent;
        border: 1px solid color-mix(in srgb, var(--muted, #badfdb) 60%, transparent);
        color: var(--text-soft, #72506b);
        border-radius: 10px;
        padding: 6px 10px;
        cursor: pointer;
      }
      .vcart-remove:hover {
        border-color: var(--accent, #ffa4a4);
        color: var(--text, #e5e7eb);
      }
      .vcart-footer {
        padding: 18px 22px;
        border-top: 1px solid color-mix(in srgb, var(--muted, #badfdb) 55%, transparent);
        display: grid;
        gap: 12px;
      }
      .vcart-total {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
      }
      .vcart-total strong {
        font-size: 1.2rem;
      }
      .vcart-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
      }
      .vcart-btn {
        padding: 10px 16px;
        border-radius: 12px;
        border: 0;
        cursor: pointer;
        font-weight: 700;
      }
      .vcart-btn.primary {
        background: var(--primary, #e6007e);
        color: #0b0e11;
      }
      .vcart-btn.secondary {
        background: transparent;
        border: 1px solid color-mix(in srgb, var(--muted, #badfdb) 60%, transparent);
        color: var(--text-soft, #72506b);
      }
      .vcart-btn.secondary:hover {
        color: var(--text, #e5e7eb);
        border-color: var(--accent, #ffa4a4);
      }
      .cart-nav-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        position: relative;
      }
      .cart-nav-count {
        background: var(--accent, #ffa4a4);
        color: #0b0e11;
        font-weight: 700;
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 0.75rem;
      }
      @media (max-width: 540px) {
        .vcart-panel {
          width: 100%;
        }
        .vcart-controls {
          flex-direction: column;
          align-items: flex-start;
        }
        .vcart-remove {
          margin-left: 0;
        }
      }
    `;
    document.head.appendChild(style);
  }

  function ensureOverlay() {
    if (overlay) return overlay;
    ensureStyles();
    overlay = document.createElement('div');
    overlay.id = 'viveCartOverlay';
    overlay.innerHTML = `
      <div class="vcart-panel" role="dialog" aria-modal="true" aria-labelledby="vcartTitle">
        <div class="vcart-header">
          <h3 id="vcartTitle">Mi viaje</h3>
          <button type="button" class="vcart-close" aria-label="Cerrar carrito">&times;</button>
        </div>
        <div class="vcart-body">
          <p class="vcart-empty">Aún no agregas actividades ni experiencias.</p>
          <ul class="vcart-list"></ul>
        </div>
        <div class="vcart-footer">
          <div class="vcart-total">
            <span>Total estimado</span>
            <strong id="vcartTotal">A definir</strong>
          </div>
          <div class="vcart-actions">
            <button type="button" class="vcart-btn secondary" data-action="clear">Vaciar</button>
            <button type="button" class="vcart-btn primary" data-action="checkout">Ir al checkout</button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
    listEl = overlay.querySelector('.vcart-list');
    totalEl = overlay.querySelector('#vcartTotal');
    emptyEl = overlay.querySelector('.vcart-empty');

    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        closeCart();
      }
    });
    overlay.querySelector('.vcart-close').addEventListener('click', closeCart);
    overlay.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeCart();
      }
    });
    overlay.querySelector('[data-action="clear"]').addEventListener('click', () => {
      state.items = [];
      persist();
      updateCount();
      renderCart();
    });
    overlay.querySelector('[data-action="checkout"]').addEventListener('click', () => {
      openCheckout();
    });
    listEl.addEventListener('input', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLInputElement)) return;
      const li = target.closest('.vcart-item');
      if (!li) return;
      const key = li.dataset.key;
      let nextQuantity = parseInt(target.value || '1', 10);
      if (!Number.isFinite(nextQuantity) || nextQuantity < 1) nextQuantity = 1;
      const item = state.items.find((entry) => entry.key === key);
      if (!item) return;
      item.quantity = nextQuantity;
      persist();
      updateCount();
      renderCart();
    });
    listEl.addEventListener('click', (event) => {
      const button = event.target.closest('.vcart-remove');
      if (!button) return;
      const li = button.closest('.vcart-item');
      if (!li) return;
      const key = li.dataset.key;
      state.items = state.items.filter((entry) => entry.key !== key);
      persist();
      updateCount();
      renderCart();
    });
    return overlay;
  }

  function ensureNavButton() {
    if (navButton) return;
    const navMenu = document.querySelector('.menu');
    if (!navMenu) return;
    navButton = document.createElement('button');
    navButton.id = 'viveCartNavButton';
    navButton.type = 'button';
    navButton.className = 'btn-ghost cart-nav-btn';
    navButton.innerHTML = `
      Mi viaje
      <span class="cart-nav-count">0</span>
    `;
    navCountEl = navButton.querySelector('.cart-nav-count');
    navButton.addEventListener('click', () => openCart());
    navMenu.appendChild(navButton);
  }

  function updateCount() {
    const totalItems = state.items.reduce((sum, item) => sum + (item.quantity || 0), 0);
    if (navCountEl) {
      navCountEl.textContent = String(totalItems);
    }
  }

  function renderCart() {
    ensureOverlay();
    if (!listEl || !totalEl || !emptyEl) return;
    listEl.innerHTML = '';
    if (!state.items.length) {
      emptyEl.style.display = 'block';
      totalEl.textContent = 'A definir';
      return;
    }
    emptyEl.style.display = 'none';
    const fragment = document.createDocumentFragment();
    let totalValue = 0;
    let totalHasPrice = false;
    state.items.forEach((item) => {
      const itemTotal = (item.price || 0) * (item.quantity || 0);
      if (item.price) {
        totalValue += itemTotal;
        totalHasPrice = true;
      }
      const li = document.createElement('li');
      li.className = 'vcart-item';
      li.dataset.key = item.key;
      li.innerHTML = `
        <header>
          <h4>${item.title || 'Actividad'}</h4>
          <small>${(item.category || item.type || '').toUpperCase()}</small>
        </header>
        ${item.location ? `<small>${item.location}</small>` : ''}
        ${item.description ? `<p>${item.description}</p>` : ''}
        <div class="vcart-controls">
          <label>
            Personas
            <input type="number" min="1" value="${item.quantity || 1}">
          </label>
          <span class="vcart-price">${item.price ? formatCurrency(item.price, item.currency) : 'A cotizar'}</span>
          <button type="button" class="vcart-remove">Quitar</button>
        </div>
      `;
      fragment.appendChild(li);
    });
    listEl.appendChild(fragment);
    totalEl.textContent = totalHasPrice ? formatCurrency(totalValue, state.items[0]?.currency || defaultCurrency()) : 'A definir';
  }

  function openCart() {
    ensureOverlay();
    renderCart();
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeCart() {
    if (!overlay) return;
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  function addItem(rawItem) {
    if (!rawItem || !rawItem.id) return;
    const key = `${rawItem.type || 'custom'}:${rawItem.id}`;
    const quantity = Math.max(1, parseInt(rawItem.quantity || 1, 10));
    const existing = state.items.find((entry) => entry.key === key);
    if (existing) {
      existing.quantity += quantity;
      if (rawItem.price != null) existing.price = rawItem.price;
      existing.currency = rawItem.currency || existing.currency;
      if (rawItem.description) existing.description = rawItem.description;
      if (rawItem.location) existing.location = rawItem.location;
    } else {
      state.items.push({
        key,
        id: rawItem.id,
        type: rawItem.type || 'custom',
        category: rawItem.category || rawItem.type || 'custom',
        title: rawItem.title || 'Item',
        description: rawItem.description || '',
        location: rawItem.location || '',
        price: typeof rawItem.price === 'number' ? rawItem.price : null,
        currency: rawItem.currency || defaultCurrency(),
        quantity
      });
    }
    persist();
    updateCount();
    renderCart();
  }

  function openCheckout() {
    if (!window.VIVE_CHECKOUT || typeof window.VIVE_CHECKOUT.open !== 'function') {
      alert('Checkout no disponible en esta vista.');
      return;
    }
    if (!state.items.length) {
      alert('Tu viaje está vacío. Agrega actividades antes de continuar.');
      return;
    }
    const currency = state.items.find((item) => item.currency)?.currency || defaultCurrency();
    const activities = state.items.map((item) => ({
      id: item.id,
      title: item.title,
      persons: item.quantity,
      pricePerPerson: item.price,
      subtotal: item.price ? item.price * item.quantity : 0
    }));
    const totalValue = activities.reduce((sum, entry) => sum + (entry.subtotal || 0), 0);
    const quote = {
      currency,
      checkIn: '',
      checkOut: '',
      nights: 1,
      lodging: null,
      activities,
      totals: {
        type: 'fixed',
        value: totalValue
      }
    };
    window.VIVE_CHECKOUT.open(quote);
  }

  function addActivity(activity, options = {}) {
    if (!activity) return;
    const quantity = Math.max(1, parseInt(options.quantity || 1, 10));
    addItem({
      id: activity.id || activity.code || `activity-${Date.now()}`,
      type: 'activity',
      category: (activity.type || 'tour').toLowerCase(),
      title: activity.title || activity.name || 'Actividad',
      description: activity.description || '',
      location: activity.location || '',
      price: typeof activity.pricePerPerson === 'number' ? activity.pricePerPerson : null,
      currency: activity.currency || defaultCurrency(),
      quantity
    });
    openCart();
  }

  function clearCart() {
    state.items = [];
    persist();
    updateCount();
    renderCart();
  }

  window.VIVE_CART = {
    addActivity,
    addItem,
    clear: clearCart,
    open: openCart,
    items: () => state.items.map((item) => ({ ...item }))
  };

  document.addEventListener('DOMContentLoaded', () => {
    state.items = loadFromStorage();
    ensureOverlay();
    ensureNavButton();
    updateCount();
  });
})();
