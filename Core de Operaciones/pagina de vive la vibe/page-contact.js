(function(){
  function t(path, fallback){
    const root = window.VIVE_TEXTS || {};
    return path.split('.').reduce((acc,key)=> (acc && acc[key] !== undefined ? acc[key] : undefined), root) ?? fallback;
  }
  function setText(id, value){
    const el = document.getElementById(id);
    if(el && value !== undefined){ el.textContent = value; }
  }
  function buildList(listId, values, formatter){
    const container = document.getElementById(listId);
    if(!container) return;
    container.innerHTML = "";
    (values || []).forEach(item => {
      const li = document.createElement('li');
      li.innerHTML = formatter ? formatter(item) : item;
      container.appendChild(li);
    });
  }

  function cleanPhone(value){
    return (value || '').replace(/[^0-9]/g, '');
  }

  document.addEventListener('DOMContentLoaded', () => {
    const texts = (window.VIVE_TEXTS || {}).contactPage || {};
    const navTexts = (window.VIVE_TEXTS || {}).nav || {};
    const footerTexts = (window.VIVE_TEXTS || {}).footer || {};
    const resources = window.VIVE_RESOURCES || {};

    if (typeof window.VIVE_APPLY_THEME === 'function') {
      window.VIVE_APPLY_THEME('contact');
    }

    const brandName = window.VIVE_TEXTS?.brandName || 'Vive la Vibe';
    document.title = `${texts.metaTitle || 'Contacto'} | ${brandName}`;

    const logoImg = document.getElementById('logoImg');
    if (logoImg && resources.brand?.logoUrl) {
      logoImg.src = resources.brand.logoUrl;
      logoImg.alt = resources.brand.name || brandName;
    }
    setText('logoText', resources.brand?.name || brandName);

    setText('navActivities', navTexts.activities || 'Actividades');
    setText('navLodgings', navTexts.lodgings || 'Hospedajes');
    setText('navContact', navTexts.contact || 'Contacto');
    setText('navCTA', navTexts.cta || 'Cotiza tu Viaje');

    const hero = texts.hero || {};
    setText('contactHeroEyebrow', hero.eyebrow);
    setText('contactHeroTitle', hero.title);
    setText('contactHeroSubtitle', hero.subtitle);
    setText('contactHeroDescription', hero.description);

    const primaryBtn = document.getElementById('contactPrimaryCta');
    if (primaryBtn){
      primaryBtn.textContent = hero.primaryCta || 'Agendar llamada';
      primaryBtn.addEventListener('click', () => {
        document.getElementById('contactForm')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }

    const secondaryLink = document.getElementById('contactSecondaryCta');
    if (secondaryLink){
      secondaryLink.textContent = hero.secondaryCta || 'Escribir ahora';
      const waChannel = (texts.channels?.items || []).find(item => /whatsapp/i.test(item.label || ''));
      const raw = waChannel?.value || resources.socials?.whatsapp;
      if (raw){
        const phone = cleanPhone(raw);
        secondaryLink.href = phone ? `https://wa.me/${phone}` : raw;
      } else {
        secondaryLink.href = '#contactForm';
      }
    }

    const channels = texts.channels || {};
    setText('contactChannelsHeading', channels.heading);
    setText('contactChannelsDescription', channels.description);
    setText('contactChannelsSubheading', channels.heading);
    buildList('contactChannelList', channels.items, (item) => {
      const label = item.label || '';
      const value = item.value || '';
      let href = value;
      if (value.includes('@')) href = `mailto:${value}`;
      else if (/^\+?\d+/.test(value)) href = `tel:${cleanPhone(value)}`;
      else if (value.startsWith('http')) href = value;
      return `<strong>${label}:</strong> <a href="${href}" target="_blank" rel="noopener">${value}</a>`;
    });

    const office = texts.office || {};
    setText('contactOfficeHeading', office.heading);
    buildList('contactOfficeLines', office.lines, (line) => `${line}`);

    const schedule = texts.schedule || {};
    setText('contactScheduleHeading', schedule.heading);
    buildList('contactScheduleLines', schedule.lines, (line) => `${line}`);

    const assistance = texts.assistance || {};
    setText('contactAssistanceHeading', assistance.heading);
    buildList('contactAssistanceList', assistance.bullets, (line) => `${line}`);

    const formTexts = texts.form || {};
    setText('contactFormHeading', formTexts.heading);
    setText('contactFormDescription', formTexts.description);
    setText('contactFieldNameLabel', formTexts.fields?.name);
    setText('contactFieldEmailLabel', formTexts.fields?.email);
    setText('contactFieldPhoneLabel', formTexts.fields?.phone);
    setText('contactFieldGuestsLabel', formTexts.fields?.guests);
    setText('contactFieldDatesLabel', formTexts.fields?.dates);
    setText('contactFieldMessageLabel', formTexts.fields?.message);
    setText('contactSubmit', formTexts.submit);
    setText('contactFormPrivacy', formTexts.privacy);

    const form = document.getElementById('contactForm');
    const feedback = document.getElementById('contactFormFeedback');
    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      form.reset();
      if (feedback){
        feedback.textContent = formTexts.success || 'Gracias, te contactaremos pronto.';
        feedback.hidden = false;
      }
    });

    const social = texts.social || {};
    setText('contactSocialHeading', social.heading);
    buildList('contactSocialList', social.lines, (line) => `${line}`);

    const socials = resources.socials || {};
    const socialMap = [
      ['socialInstagram', socials.instagram],
      ['socialTikTok', socials.tiktok],
      ['socialFacebook', socials.facebook],
      ['socialWhatsApp', socials.whatsapp]
    ];
    socialMap.forEach(([id, url]) => {
      const anchor = document.getElementById(id);
      if (anchor && url){ anchor.href = url; }
    });

    const faq = texts.faq || {};
    setText('contactFaqHeading', faq.heading);
    const faqContainer = document.getElementById('contactFaqList');
    if (faqContainer){
      faqContainer.innerHTML = '';
      (faq.items || []).forEach(item => {
        const card = document.createElement('article');
        card.className = 'faq-item';
        const q = document.createElement('h4');
        q.textContent = item.question;
        const a = document.createElement('p');
        a.className = 'muted-text';
        a.textContent = item.answer;
        card.append(q, a);
        faqContainer.appendChild(card);
      });
    }

    const cta = texts.cta || {};
    setText('contactCtaHeading', cta.heading);
    setText('contactCtaBody', cta.body);
    const ctaButton = document.getElementById('contactCtaButton');
    if (ctaButton){
      ctaButton.textContent = cta.button || 'Cotizar';
      ctaButton.href = '#contactForm';
      ctaButton.addEventListener('click', (ev) => {
        ev.preventDefault();
        document.getElementById('contactForm')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }

    const footerBrand = document.getElementById('footerBrand');
    const footerContact = document.getElementById('footerContact');
    const footerCopy = document.getElementById('footerCopy');
    if (footerBrand) footerBrand.textContent = brandName;
    if (footerContact) footerContact.textContent = (footerTexts.contactLines || []).join(' · ');
    if (footerCopy) footerCopy.textContent = `© ${new Date().getFullYear()} ${brandName}. Todos los derechos reservados.`;
  });
})();
