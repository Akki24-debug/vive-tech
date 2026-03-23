/* seo.js - SEO variables and head/meta helpers (pure JS) */
/* Variables SEO centralizadas */
window.VIVE_SEO = {
  siteUrl: "https://www.vivelavibe.com/",
  brand: "Vive la Vibe",
  description:
    "Hospedajes y experiencias en Puerto Escondido: casas, cabañas y tours como bioluminiscencia, liberación de tortugas y surf. Reserva fácil con Vive la Vibe.",
  ogTitle: "Vive la Vibe – Hospedajes y actividades en Puerto Escondido",
  ogImage: "https://www.vivelavibe.com/static/og-cover.jpg",
  logo: "https://www.vivelavibe.com/static/logo.png",

  socials: {
    instagram: "https://www.instagram.com/vivelavibe",
    tiktok: "https://www.tiktok.com/@vivelavibe",
    facebook: "https://www.facebook.com/vivelavibe",
    whatsapp: "https://wa.me/5215550000000"
  },

  address: { locality: "Puerto Escondido", region: "Oaxaca", country: "MX" },
  phone: "+52 555 000 0000",
  businessHours: "Mo-Su 09:00-20:00",

  // Texto alternativo y preloads
  heroWebp: "https://www.vivelavibe.com/static/hero.webp",
  heroWebp800: "https://www.vivelavibe.com/static/hero-800.webp",

  // FAQ visibles + JSON-LD
  faq: [
    {
      q: "¿Cuál es la mejor época para visitar Puerto Escondido?",
      a: "De noviembre a abril el clima es más estable; para surf, verano-otoño tiene mejores olas."
    },
    {
      q: "¿Ofrecen transporte desde el aeropuerto?",
      a: "Sí, con costo adicional. Puedes solicitarlo al cotizar tu hospedaje."
    },
    {
      q: "¿La bioluminiscencia se ve todo el año?",
      a: "Es frecuente, pero varía en intensidad. Evita luna llena para mejor visibilidad."
    }
  ],

  // Ruta que usaría tu buscador server-side si existiera
  searchPath: "/buscar",

  // Monedas de referencia que mostrarás en la calculadora (opcional)
  currencies: ["MXN", "USD", "EUR"]
};

/* Inyección de meta-tags y JSON-LD en <head> */
(function injectSEO() {
  const S = window.VIVE_SEO || {};
  const H = document.getElementsByTagName("head")[0];

  function meta(name, content) {
    if (!content) return;
    const m = document.createElement("meta");
    m.setAttribute("name", name);
    m.setAttribute("content", content);
    H.appendChild(m);
  }
  function prop(property, content) {
    if (!content) return;
    const m = document.createElement("meta");
    m.setAttribute("property", property);
    m.setAttribute("content", content);
    H.appendChild(m);
  }
  function link(rel, href, extra = {}) {
    if (!href) return;
    const l = document.createElement("link");
    l.rel = rel;
    l.href = href;
    Object.assign(l, extra);
    H.appendChild(l);
  }
  function ld(obj) {
    const s = document.createElement("script");
    s.type = "application/ld+json";
    s.text = JSON.stringify(obj);
    H.appendChild(s);
  }

  // Canonical + description + robots
  link("canonical", S.siteUrl || location.href);
  meta("description", S.description);
  meta("robots", "index,follow,max-image-preview:large");
  meta("author", S.brand || "Vive la Vibe");

  // Open Graph / Twitter
  prop("og:site_name", S.brand || "Vive la Vibe");
  prop("og:type", "website");
  prop("og:title", S.ogTitle || S.brand);
  prop("og:description", S.description);
  prop("og:url", S.siteUrl || location.href);
  prop("og:image", S.ogImage || S.logo);
  meta("twitter:card", "summary_large_image");
  meta("twitter:title", S.ogTitle || S.brand);
  meta("twitter:description", S.description);
  meta("twitter:image", S.ogImage || S.logo);

  // Performance hints (preload hero)
  link("preload", S.heroWebp, { as: "image", imagesrcset: `${S.heroWebp} 1200w, ${S.heroWebp800} 800w`, imagesizes: "(max-width: 800px) 100vw, 50vw" });

  // JSON-LD: LocalBusiness
  ld({
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "@id": (S.siteUrl || location.origin) + "#org",
    "name": S.brand,
    "url": S.siteUrl || location.origin,
    "image": S.logo,
    "telephone": S.phone,
    "address": {
      "@type": "PostalAddress",
      "addressLocality": S.address?.locality || "Puerto Escondido",
      "addressRegion": S.address?.region || "Oaxaca",
      "addressCountry": S.address?.country || "MX"
    },
    "openingHours": S.businessHours,
    "sameAs": Object.values(S.socials || {})
  });

  // JSON-LD: FAQPage
  ld({
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": (S.faq || []).map(f => ({
      "@type": "Question",
      "name": f.q,
      "acceptedAnswer": { "@type": "Answer", "text": f.a }
    }))
  });

  // JSON-LD: WebSite + SearchAction
  ld({
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": S.brand,
    "url": S.siteUrl || location.origin,
    "potentialAction": {
      "@type": "SearchAction",
      "target": (S.siteUrl || location.origin) + (S.searchPath || "/buscar") + "?q={query}",
      "query-input": "required name=query"
    }
  });
})();

