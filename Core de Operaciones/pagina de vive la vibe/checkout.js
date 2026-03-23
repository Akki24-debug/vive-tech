/* checkout.js v1.1
   Lightbox de Checkout (resumen + datos de contacto + botón Pagar)
   Uso: window.VIVE_CHECKOUT.open(quote)
   Donde quote = {
     currency, checkIn, nights, checkOut,
     lodging?: { type:"fixed", ... } | { type:"range", ... },
     activities: [{id,title,persons,pricePerPerson,subtotal}],
     totals: { type:"fixed", value } | { type:"range", min,max }
   }
*/
(function(){
  const $ = (s, r) => (r || document).querySelector(s);
  const fmt = (n, c) => new Intl.NumberFormat("es-MX",{style:"currency",currency:c,maximumFractionDigits:0}).format(n);

  function styles(){
    if ($("#checkoutStyles")) return;
    const st=document.createElement("style");
    st.id="checkoutStyles";
    st.textContent = `
      .co-btn{ background:var(--primary);color:#0b0e11;border:0;border-radius:10px;padding:10px 14px;font-weight:800;cursor:pointer;transition:.15s ease }
      .co-btn:hover{ filter:brightness(1.05) }
      .co-ghost{ background:transparent;border:1px solid color-mix(in srgb,var(--muted) 60%, transparent);color:var(--text-soft);border-radius:10px;padding:6px 10px;cursor:pointer }
      .co-row{ display:grid;grid-template-columns:1fr 1fr;gap:16px }
      .co-card{ background:var(--surface-strong);border:1px solid color-mix(in srgb,var(--muted) 60%, transparent);border-radius:12px;padding:12px }
      .co-input{ width:100%;padding:10px;border:1px solid color-mix(in srgb,var(--muted) 70%, transparent);border-radius:10px;background:var(--surface);color:var(--text) }
      .mini{ font-size:.9rem;color:var(--text-soft) }
      .co-line{ display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px dashed color-mix(in srgb,var(--muted) 50%, transparent) }
      .co-line:last-child{ border-bottom:0 }
      .co-total{ padding:12px;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:12px;color:#0b0e11;display:flex;justify-content:space-between;align-items:center;font-weight:900 }
      @media (max-width: 960px){ .co-row{ grid-template-columns:1fr } }
    `;
    document.head.appendChild(st);
  }

  function ensureModal(){
    if ($("#coOverlay")) return $("#coOverlay");
    const m = document.createElement("div");
    m.id="coOverlay";
    m.style.cssText="position:fixed;inset:0;background:rgba(0,0,0,.78);display:none;align-items:center;justify-content:center;z-index:180;padding:20px";
    m.innerHTML = `
      <div id="coCard" style="max-width:1100px;width:100%;max-height:92vh;overflow:auto;background:var(--surface);border:1px solid color-mix(in srgb,var(--muted) 70%, transparent);border-radius:16px;padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <h3 style="margin:0">Checkout</h3>
          <button id="coClose" class="co-ghost">✕</button>
        </div>

        <div class="co-row">
          <section class="co-card">
            <h4 style="margin:0 0 8px 0">Resumen</h4>
            <div id="coSummary"></div>
          </section>

            <section class="co-card">
              <h4 style="margin:0 0 8px 0">Tus datos</h4>
              <div style="display:grid;gap:10px">
                <input id="coName" class="co-input" placeholder="Nombre completo">
                <input id="coEmail" class="co-input" placeholder="Correo electrónico">
                <input id="coPhone" class="co-input" placeholder="Teléfono">
                <label class="mini" style="display:flex;gap:8px;align-items:center">
                  <input id="coTerms" type="checkbox" style="accent-color:var(--primary)"> Acepto términos y condiciones
                </label>
                <button id="coPay" class="co-btn">Enviar solicitud</button>
                <div id="coMsg" class="mini" style="color:var(--primary, #e6007e);display:none"></div>
              </div>
            </section>
        </div>
      </div>`;
    m.addEventListener("click", (e)=>{ if(e.target.id==="coOverlay") m.style.display="none"; });
    $("#coClose", m).onclick=()=> m.style.display="none";
    document.body.appendChild(m);
    return m;
  }

  function renderSummary(container, q){
    const cur = q.currency || "MXN";
    const box = container;
    const activities = Array.isArray(q.activities) ? q.activities : [];

    let lodHtml;
    if (q.lodging && q.lodging.type === "fixed") {
      lodHtml = `
        <div class="co-line"><strong>Hospedaje</strong><span>${fmt(q.lodging.subtotal, cur)}</span></div>
        <div class="mini" style="margin-top:4px">
          ${(q.lodging.lodgingName || "Hospedaje seleccionado")} · ${(q.lodging.roomTypeName || "")}<br>
          ${(q.checkIn || "Fecha por definir")} - ${(q.checkOut || "Fecha por definir")} · ${(q.lodging.nights || 1)} noches · ${fmt(q.lodging.pricePerNight, cur)} por noche
        </div>`;
    } else if (q.lodging) {
      lodHtml = `
        <div class="co-line"><strong>Hospedaje</strong><span>${fmt(q.lodging.minSubtotal, cur)} - ${fmt(q.lodging.maxSubtotal, cur)}</span></div>
        <div class="mini" style="margin-top:4px">
          ${(q.checkIn || "Fecha por definir")} - ${(q.checkOut || "Fecha por definir")} · ${(q.lodging.nights || 1)} noches ·
          rango: ${fmt(q.lodging.minPerNight, cur)} - ${fmt(q.lodging.maxPerNight, cur)} por noche
        </div>`;
    } else {
      lodHtml = `
        <div class="co-line"><strong>Hospedaje</strong><span>Pendiente de elegir</span></div>
        <div class="mini" style="margin-top:4px">
          Aún no has seleccionado un hospedaje. Puedes definirlo más adelante.
        </div>`;
    }

    const actsHtml = activities.length
      ? activities.map(a=>`
          <div class="co-line">
            <span>${a.title} · ${a.persons} ${a.persons===1?"persona":"personas"}</span>
            <span>${fmt(a.subtotal || 0, cur)}</span>
          </div>`).join("")
      : `<div class="mini">Sin actividades</div>`;

    let totalsHtml;
    if (q.totals && q.totals.type === "range") {
      totalsHtml = `<div class="co-total" style="margin-top:12px"><span>Total</span><span>${fmt(q.totals.min, cur)} - ${fmt(q.totals.max, cur)}</span></div>`;
    } else {
      const fallbackTotal = activities.reduce((sum, entry) => sum + (entry.subtotal || 0), 0);
      const totalValue = q.totals && typeof q.totals.value === "number" ? q.totals.value : fallbackTotal;
      totalsHtml = `<div class="co-total" style="margin-top:12px"><span>Total</span><span>${fmt(totalValue, cur)}</span></div>`;
    }

    box.innerHTML = `
      ${lodHtml}
      <h5 style="margin:12px 0 6px 0">Actividades</h5>
      ${actsHtml}
      ${totalsHtml}
    `;
  }

  function validate(){
    const name = $("#coName").value.trim();
    const email = $("#coEmail").value.trim();
    const phone = $("#coPhone").value.trim();
    const terms = $("#coTerms").checked;
    if (!name || !email || !terms) {
      $("#coMsg").textContent = "Completa tu nombre, correo y acepta los términos para continuar.";
      $("#coMsg").style.display = "block";
      return false;
    }
    $("#coMsg").style.display = "none";
    return { name, email, phone };
  }

  window.VIVE_CHECKOUT = {
    open(quote){
      styles();
      const m = ensureModal();
      renderSummary($("#coSummary", m), quote);
      $("#coPay", m).onclick = () => {
        const ok = validate();
        if (!ok) return;
        console.log("Enviar a pago:", { quote, customer: ok });
        alert("Simulación de pago: listo para enviar al backend.");
      };
      m.style.display = "flex";
    }
  };
})();
