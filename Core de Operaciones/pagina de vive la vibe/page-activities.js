(function () {
  const IMAGE_FALLBACK = "https://picsum.photos/seed/vive-activity/1200/800";

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el && value !== undefined) {
      el.textContent = value;
    }
  }

  function createLightbox() {
    const overlay = document.getElementById('activityLightbox');
    if (!overlay) return null;

    const imageEl = overlay.querySelector('.activity-lightbox_image');
    const titleEl = overlay.querySelector('.activity-lightbox_title');
    const counterEl = overlay.querySelector('.activity-lightbox_counter');
    const thumbsEl = overlay.querySelector('.activity-lightbox_thumbs');
    const prevBtn = overlay.querySelector('.activity-lightbox_arrow.prev');
    const nextBtn = overlay.querySelector('.activity-lightbox_arrow.next');
    const dismissTargets = overlay.querySelectorAll('[data-lightbox-dismiss]');

    let images = [];
    let currentIndex = 0;
    let currentTitle = '';

    const updateCounter = () => {
      if (!counterEl) return;
      counterEl.textContent = images.length > 1
        ? `${currentIndex + 1} / ${images.length}`
        : '1 / 1';
    };

    const updateThumbs = () => {
      if (!thumbsEl) return;
      Array.from(thumbsEl.children).forEach((thumb, idx) => {
        thumb.classList.toggle('is-active', idx === currentIndex);
      });
    };

    const renderImage = (index) => {
      if (!images.length || !imageEl) return;
      currentIndex = (index + images.length) % images.length;
      const url = images[currentIndex];
      imageEl.src = url;
      imageEl.alt = `${currentTitle} - Imagen ${currentIndex + 1}`;
      updateCounter();
      updateThumbs();
    };

    const renderThumbs = () => {
      if (!thumbsEl) return;
      thumbsEl.innerHTML = '';
      images.forEach((url, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'activity-lightbox_thumb';
        btn.innerHTML = `<img src="${url}" alt="">`;
        btn.addEventListener('click', () => renderImage(idx));
        thumbsEl.appendChild(btn);
      });
    };

    const open = (activity) => {
      images = Array.isArray(activity?.images) && activity.images.length
        ? activity.images.filter(Boolean)
        : [IMAGE_FALLBACK];
      currentIndex = 0;
      currentTitle = activity?.title || activity?.name || 'Actividad';
      if (titleEl) {
        titleEl.textContent = currentTitle;
      }
      renderThumbs();
      renderImage(0);
      overlay.removeAttribute('hidden');
      overlay.classList.add('is-visible');
      document.body.classList.add('no-scroll');
    };

    const close = () => {
      overlay.classList.remove('is-visible');
      overlay.setAttribute('hidden', 'hidden');
      document.body.classList.remove('no-scroll');
      images = [];
      currentIndex = 0;
    };

    const step = (offset) => {
      if (!images.length) return;
      renderImage(currentIndex + offset);
    };

    if (prevBtn) {
      prevBtn.addEventListener('click', () => step(-1));
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', () => step(1));
    }

    dismissTargets.forEach((node) => {
      node.addEventListener('click', close);
    });

    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        close();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (!overlay.classList.contains('is-visible')) return;
      if (event.key === 'Escape') {
        close();
      }
      if (event.key === 'ArrowLeft') {
        step(-1);
      }
      if (event.key === 'ArrowRight') {
        step(1);
      }
    });

    return { open, close };
  }

  const lightbox = createLightbox();

  function formatCurrency(value, currency) {
    if (typeof value !== "number" || !isFinite(value)) return "";
    try {
      return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: currency || (window.VIVE_RESOURCES?.currency) || "MXN",
        maximumFractionDigits: 0
      }).format(value);
    } catch {
      return `${value} ${currency || "MXN"}`;
    }
  }

  function addToCart(activity) {
    if (!activity) return;
    if (window.VIVE_CART && typeof window.VIVE_CART.addActivity === "function") {
      window.VIVE_CART.addActivity(activity);
    }
  }

  function attachAddHandler(button, activity, fallbackLabel = "Agregar a mi viaje") {
    if (!button) return;
    button.addEventListener("click", (event) => {
      event.preventDefault();
      addToCart(activity);
      const original = button.textContent;
      button.textContent = "Agregado";
      button.disabled = true;
      setTimeout(() => {
        button.textContent = original || fallbackLabel;
        button.disabled = false;
      }, 1600);
    });
  }

  function buildCard(activity, options = {}) {
    const {
      includeSecondary = false,
      secondaryHref = "./contacto.html",
      secondaryLabel = "Solicitar itinerario",
      className = "activity-card"
    } = options;

    const card = document.createElement("article");
    card.className = className;

    const imgUrl = Array.isArray(activity.images) && activity.images[0]
      ? activity.images[0]
      : IMAGE_FALLBACK;

    const priceLabel = typeof activity.pricePerPerson === "number" && isFinite(activity.pricePerPerson)
      ? `${formatCurrency(activity.pricePerPerson, activity.currency)} por persona`
      : "";

    const capacityLabel = typeof activity.capacityDefault === "number"
      ? `Capacidad sugerida: ${activity.capacityDefault} personas`
      : "";

    const metaLines = [priceLabel, capacityLabel].filter(Boolean);

    card.innerHTML = `
      <div class="activity-card_media">
        <img src="${imgUrl}" alt="${activity.title || activity.name || ""}" loading="eager" width="640" height="420">
      </div>
      <div class="activity-card_body">
        ${activity.durationLabel ? `<span class="pill">${activity.durationLabel}</span>` : ""}
        <h3>${activity.title || activity.name || ""}</h3>
        ${activity.location ? `<small>${activity.location}</small>` : ""}
        ${activity.description ? `<p>${activity.description}</p>` : ""}
        ${metaLines.length ? `<div class="activity-card_meta">${metaLines.map(line => `<span>${line}</span>`).join('')}</div>` : ""}
        <div class="activity-card_actions">
          <button class="activity-card_btn" type="button">Agregar a mi viaje</button>
          ${includeSecondary ? `<a class="activity-card_btn secondary" href="${secondaryHref}">${secondaryLabel}</a>` : ""}
        </div>
      </div>
    `;

    const addBtn = card.querySelector('.activity-card_btn');
    attachAddHandler(addBtn, activity);

    return card;
  }

  function buildVibeCard(activity, openGallery) {
    const card = document.createElement('article');
    card.className = 'vibe-card';

    const galleryImages = Array.isArray(activity.images) && activity.images.length
      ? activity.images.filter(Boolean)
      : [IMAGE_FALLBACK];
    const heroImage = galleryImages[0] || IMAGE_FALLBACK;

    const priceLabel = typeof activity.pricePerPerson === 'number' && isFinite(activity.pricePerPerson)
      ? `${formatCurrency(activity.pricePerPerson, activity.currency)} por persona`
      : '';

    const capacityLabel = typeof activity.capacityDefault === 'number'
      ? `Capacidad sugerida: ${activity.capacityDefault} personas`
      : '';

    card.innerHTML = `
      <div class="vibe-card_media">
        <img src="${heroImage}" alt="${activity.title || activity.name || ''}" loading="lazy">
      </div>
      <div class="vibe-card_body">
        <div class="vibe-card_header">
          ${activity.durationLabel ? `<span class="pill">${activity.durationLabel}</span>` : ''}
          ${activity.location ? `<small>${activity.location}</small>` : ''}
        </div>
        <h3>${activity.title || activity.name || ''}</h3>
        ${activity.description ? `<p>${activity.description}</p>` : ''}
        ${(priceLabel || capacityLabel) ? `
          <div class="vibe-card_meta">
            ${priceLabel ? `<span>${priceLabel}</span>` : ''}
            ${capacityLabel ? `<span>${capacityLabel}</span>` : ''}
          </div>
        ` : ''}
        <div class="vibe-card_actions">
          <button class="btn-primary vibe-card_btn" type="button">Agregar a mi viaje</button>
          <button class="vibe-card_btn gallery" type="button"${galleryImages.length ? '' : ' disabled'}>Ver galeria</button>
          <a class="vibe-card_btn secondary" href="./contacto.html">Solicitar itinerario</a>
        </div>
      </div>
    `;

    const addBtn = card.querySelector('.btn-primary');
    attachAddHandler(addBtn, activity);

    const galleryBtn = card.querySelector('.vibe-card_btn.gallery');
    if (galleryBtn && typeof openGallery === 'function' && galleryImages.length) {
      galleryBtn.addEventListener('click', (event) => {
        event.preventDefault();
        openGallery(activity);
      });
    }

    return card;
  }

  function buildFeaturedCard(activity, variant = 'secondary') {
    const card = document.createElement('article');
    const variantClass = variant === 'primary'
      ? 'featured-card--primary'
      : variant === 'top'
        ? 'featured-card--top'
        : variant === 'bottom'
          ? 'featured-card--bottom'
          : '';
    card.className = ['featured-card', variantClass].filter(Boolean).join(' ');

    const imgUrl = Array.isArray(activity.images) && activity.images[0]
      ? activity.images[0]
      : IMAGE_FALLBACK;

    const highlightLabel = activity.type || activity.durationLabel || '';
    const badgeLabel = activity.type ? highlightLabel.toUpperCase() : highlightLabel;

    card.innerHTML = `
      <img src="${imgUrl}" alt="${activity.title || activity.name || ''}" loading="eager">
      <div class="featured-card_overlay">
        ${highlightLabel ? `<span class="pill">${badgeLabel}</span>` : ''}
        <h3>${activity.title || activity.name || ''}</h3>
        ${activity.location ? `<small>${activity.location}</small>` : ''}
        ${activity.description ? `<p>${activity.description}</p>` : ''}
        <button class="btn-primary featured-card_btn" type="button">Agregar a mi viaje</button>
      </div>
    `;

    const addBtn = card.querySelector('.featured-card_btn');
    attachAddHandler(addBtn, activity);

    return card;
  }

  function getGapSize(track) {
    if (!track) return 0;
    const style = window.getComputedStyle(track);
    const value = parseFloat(style.columnGap || style.gap || '0');
    return Number.isFinite(value) ? value : 0;
  }

  function setupCarousel(track, prev, next, items, emptyMessage, options = {}) {
    if (!track) return;
    track.innerHTML = '';

    if (!items.length) {
      const empty = document.createElement('p');
      empty.className = 'muted-text';
      empty.textContent = emptyMessage;
      track.appendChild(empty);
      if (prev) prev.disabled = true;
      if (next) next.disabled = true;
      return;
    }

    const cards = items.map(item => buildCard(item, options));
    cards.forEach(card => track.appendChild(card));

    const canLoop = cards.length > 1;
    if (prev) {
      prev.disabled = !canLoop;
      prev.onclick = null;
    }
    if (next) {
      next.disabled = !canLoop;
      next.onclick = null;
    }

    if (!canLoop) return;

    let animating = false;

    const resetTransform = () => {
      track.style.transition = '';
      track.style.transform = '';
      animating = false;
    };

    const handleTransitionEnd = (event) => {
      if (event.propertyName !== 'transform') return;
      track.removeEventListener('transitionend', handleTransitionEnd);
      track.style.transition = 'none';
      track.style.transform = 'translateX(0)';
      requestAnimationFrame(resetTransform);
    };

    const slideNext = () => {
      if (animating) return;
      const first = track.firstElementChild;
      if (!first) return;
      animating = true;

      const distance = first.getBoundingClientRect().width + getGapSize(track);
      track.style.transition = 'none';
      track.appendChild(first);
      track.style.transform = `translateX(-${distance}px)`;

      requestAnimationFrame(() => {
        track.style.transition = 'transform 0.45s ease';
        track.style.transform = 'translateX(0)';
      });

      track.addEventListener('transitionend', handleTransitionEnd, { once: true });
    };

    const slidePrev = () => {
      if (animating) return;
      const last = track.lastElementChild;
      if (!last) return;
      animating = true;

      const distance = last.getBoundingClientRect().width + getGapSize(track);
      track.style.transition = 'none';
      track.insertBefore(last, track.firstElementChild);
      track.style.transform = `translateX(-${distance}px)`;

      requestAnimationFrame(() => {
        track.style.transition = 'transform 0.45s ease';
        track.style.transform = 'translateX(0)';
      });

      track.addEventListener('transitionend', handleTransitionEnd, { once: true });
    };

    if (prev) prev.onclick = slidePrev;
    if (next) next.onclick = slideNext;
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const texts = (window.VIVE_TEXTS || {}).activitiesPage || {};
    const navTexts = (window.VIVE_TEXTS || {}).nav || {};
    const footerTexts = (window.VIVE_TEXTS || {}).footer || {};
    const brandName = window.VIVE_TEXTS?.brandName || 'Vive la Vibe';

    if (typeof window.VIVE_APPLY_THEME === 'function') {
      window.VIVE_APPLY_THEME('activities');
    }

    document.title = `${texts.metaTitle || 'Actividades'} | ${brandName}`;

    const logoImg = document.getElementById('actLogoImg');
    if (logoImg && window.VIVE_RESOURCES?.brand?.logoUrl) {
      logoImg.src = window.VIVE_RESOURCES.brand.logoUrl;
      logoImg.alt = window.VIVE_RESOURCES.brand.name || brandName;
    }
    setText('actLogoText', window.VIVE_RESOURCES?.brand?.name || brandName);

    setText('actNavActivities', navTexts.activities || 'Actividades');
    setText('actNavLodgings', navTexts.lodgings || 'Hospedajes');
    setText('actNavContact', navTexts.contact || 'Contacto');
    setText('actNavCTA', navTexts.cta || 'Cotiza tu Viaje');

    const hero = texts.hero || {};
    setText('activitiesHeroTitle', hero.title);
    setText('activitiesHeroSubtitle', hero.subtitle);
    setText('activitiesHeroDescription', hero.description);

    const primaryBtn = document.getElementById('activitiesPrimaryCta');
    if (primaryBtn) {
      primaryBtn.textContent = hero.primaryCta || 'Explorar tours';
      primaryBtn.addEventListener('click', () => {
        document.getElementById('toursSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }

    const secondaryLink = document.getElementById('activitiesSecondaryCta');
    if (secondaryLink) {
      secondaryLink.textContent = hero.secondaryCta || 'Solicitar itinerario';
      secondaryLink.href = './contacto.html';
    }

    const toursTexts = texts.tours || {};
    setText('toursHeading', toursTexts.heading);
    setText('toursDescription', toursTexts.description);
    setText('toursNote', toursTexts.note);

    const vibeTexts = texts.vibe || {};
    setText('vibeHeading', vibeTexts.heading);
    setText('vibeDescription', vibeTexts.description);

    const featuredTag = document.getElementById('featuredTag');
    const featuredDescription = document.getElementById('featuredDescription');
    const featuredShowcase = document.getElementById('featuredShowcase');

    if (featuredTag) {
      featuredTag.textContent = texts.featuredTag || texts.featuredHeading || 'Seleccion VIBE';
    }
    if (featuredDescription) {
      featuredDescription.textContent = texts.featuredDescription || 'Las experiencias favoritas de nuestros viajeros.';
    }

    const ready = window.VIVE_RESOURCES_READY;
    if (ready && typeof ready.then === 'function') {
      try { await ready; } catch {}
    }

    const toursTrack = document.getElementById('toursTrack');
    const toursPrev = document.getElementById('toursPrev');
    const toursNext = document.getElementById('toursNext');
    const vibeGallery = document.getElementById('vibeGallery');

    function renderActivities() {
      const resources = window.VIVE_RESOURCES || {};
      const activities = Array.isArray(resources.activities) ? resources.activities : [];
      const tourList = activities.filter(item => (item.type || '').toLowerCase() === 'tour');
      const vibeList = activities.filter(item => (item.type || '').toLowerCase() === 'vibe');

      const featuredSelection = [...tourList.slice(0, 2), ...(vibeList.length ? [vibeList[0]] : [])];
      if (featuredShowcase) {
        featuredShowcase.innerHTML = '';
        if (!featuredSelection.length) {
          const empty = document.createElement('p');
          empty.className = 'muted-text';
          empty.textContent = 'Aun no hay experiencias destacadas disponibles.';
          featuredShowcase.appendChild(empty);
        } else {
          featuredSelection.forEach((item, idx) => {
            const variant = idx === 0 ? 'primary' : idx === 1 ? 'top' : 'bottom';
            featuredShowcase.appendChild(buildFeaturedCard(item, variant));
          });
        }
      }

      setupCarousel(
        toursTrack,
        toursPrev,
        toursNext,
        tourList,
        'No hay tours disponibles por ahora.'
      );

      if (vibeGallery) {
        vibeGallery.innerHTML = '';
        if (!vibeList.length) {
          const empty = document.createElement('p');
          empty.className = 'muted-text';
          empty.textContent = 'No hay experiencias VIBE disponibles por ahora.';
          vibeGallery.appendChild(empty);
        } else {
          const openGallery = lightbox && typeof lightbox.open === 'function'
            ? (activity) => lightbox.open(activity)
            : null;
          vibeList.forEach((item) => {
            vibeGallery.appendChild(buildVibeCard(item, openGallery));
          });
        }
      }
    }

    renderActivities();
    window.addEventListener('vive:activities-loaded', renderActivities);

    const cta = texts.cta || {};
    setText('activitiesCtaHeading', cta.heading);
    setText('activitiesCtaBody', cta.body);
    const ctaButton = document.getElementById('activitiesCtaButton');
    if (ctaButton) {
      ctaButton.textContent = cta.button || 'Cotizar';
      ctaButton.href = './contacto.html';
    }

    setText('actFooterBrand', brandName);
    setText('actFooterContact', (footerTexts.contactLines || []).join(' - '));
    setText('actFooterCopy', `© ${new Date().getFullYear()} ${brandName}. Todos los derechos reservados.`);
  });
})();






