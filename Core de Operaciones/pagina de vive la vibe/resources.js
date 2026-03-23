/* resources.js
   Paletas, marca, redes, actividades y hospedajes.
   Ahora los hospedajes se cargan dinamicamente desde la base de datos
   utilizando el procedimiento almacenado sp_get_company_properties.
*/
(function(){
  const credentials = window.VIVE_CREDENTIALS || {};
  const companyCode = credentials.companyCode || 'VIBE';
  const hostingsEndpoint = credentials.hostingsEndpoint || '/get-hostings.php';
  const activitiesEndpoint = credentials.activitiesEndpoint || '/get-activities.php';

  const fallbackImage = 'https://picsum.photos/seed/vive-fallback/1200/800';
  const fallbackActivityImage = 'https://picsum.photos/seed/vive-activity-fallback/1200/800';
  const defaultCurrency = 'MXN';

  function durationLabelFromMinutes(minutes){
    if (typeof minutes !== 'number' || !isFinite(minutes) || minutes <= 0) return null;
    if (minutes % 60 === 0) {
      const hours = minutes / 60;
      return hours === 1 ? '1 hora' : `${hours} horas`;
    }
    if (minutes < 60) return `${minutes} minutos`;
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;
    const parts = [];
    if (hours > 0) parts.push(hours === 1 ? '1 hora' : `${hours} horas`);
    if (remaining > 0) parts.push(`${remaining} minutos`);
    return parts.join(' ');
  }

  function toNumber(value){
    if (typeof value === 'number' && isFinite(value)) return value;
    if (typeof value === 'string' && value.trim() !== ''){
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : null;
    }
    return null;
  }

  function normalizeActivity(activity, index, fallbackCurrency){
    const currency = fallbackCurrency || defaultCurrency;
    if (!activity || typeof activity !== 'object') {
      return {
        id: `activity-${index + 1}`,
        code: null,
        title: `Actividad ${index + 1}`,
        name: `Actividad ${index + 1}`,
        description: null,
        type: 'tour',
        pricePerPerson: null,
        currency,
        durationMinutes: null,
        durationLabel: null,
        location: null,
        capacityDefault: null,
        property: null,
        images: [fallbackActivityImage]
      };
    }

    const idRaw = activity.id ?? activity.code ?? activity.activity_code ?? activity.activityCode;
    const id = idRaw ? String(idRaw) : `activity-${index + 1}`;
    const title = activity.title ?? activity.name ?? activity.activity_name ?? `Actividad ${index + 1}`;

    const price = toNumber(activity.pricePerPerson);
    const priceCents = toNumber(activity.base_price_cents ?? activity.basePriceCents);
    const finalPrice = price != null ? price : (priceCents != null ? priceCents / 100 : null);

    const durationMinutes = toNumber(activity.durationMinutes ?? activity.duration_minutes);
    const durationLabel = activity.durationLabel ?? durationLabelFromMinutes(durationMinutes ?? undefined);

    const images = Array.isArray(activity.images) ? activity.images.filter(Boolean) : [];
    const propertyRaw = activity.property && typeof activity.property === 'object' ? activity.property : null;

    return {
      id,
      code: activity.code ?? activity.activity_code ?? null,
      title,
      name: activity.name ?? activity.activity_name ?? title,
      description: activity.description ?? null,
      type: (activity.type ?? activity.activity_type ?? 'tour').toLowerCase(),
      pricePerPerson: finalPrice,
      currency: activity.currency ?? currency,
      durationMinutes: durationMinutes ?? null,
      durationLabel,
      location: activity.location ?? null,
      capacityDefault: toNumber(activity.capacityDefault ?? activity.capacity_default),
      property: propertyRaw ? {
        id: propertyRaw.id ?? propertyRaw.id_property ?? null,
        code: propertyRaw.code ?? propertyRaw.property_code ?? null,
        name: propertyRaw.name ?? propertyRaw.property_name ?? null,
      } : null,
      images: images.length ? images : [fallbackActivityImage]
    };
  }

  function normalizeLodgings(lodgings, fallbackCurrency){
    return lodgings.map((lodging, index) => {
      const lobbyImages = Array.isArray(lodging.lobbyImages)
        ? lodging.lobbyImages.filter(Boolean)
        : [];
      const galleryImages = Array.isArray(lodging.images)
        ? lodging.images.filter(Boolean)
        : [];
      const combinedImages = galleryImages.length
        ? galleryImages
        : (lobbyImages.length ? lobbyImages : [fallbackImage]);

      const roomTypes = Array.isArray(lodging.roomTypes) ? lodging.roomTypes.map((room, rIndex) => {
        const roomImages = Array.isArray(room.images) ? room.images.filter(Boolean) : [];
        return {
          id: room.id || room.code || `room-${index + 1}-${rIndex + 1}`,
          code: room.code ?? null,
          name: room.name || `Habitacion ${rIndex + 1}`,
          capacity: room.capacity ?? null,
          pricePerNight: room.pricePerNight ?? null,
          priceRange: room.priceRange ?? null,
          images: roomImages.length ? roomImages : (combinedImages.length ? [combinedImages[0]] : [fallbackImage]),
          description: room.description ?? null,
        };
      }) : [];

      return {
        id: lodging.id || lodging.code || `property-${index + 1}`,
        code: lodging.code ?? null,
        name: lodging.name || `Propiedad ${index + 1}`,
        description: lodging.description ?? null,
        city: lodging.city ?? null,
        state: lodging.state ?? null,
        country: lodging.country ?? null,
        currency: lodging.currency || fallbackCurrency,
        lobbyImages,
        images: combinedImages,
        roomTypes,
      };
    });
  }

  const resources = {
    currency: defaultCurrency,
    currencies: ['MXN','USD','EUR'],

    brand: {
      name: 'Vive la Vibe',
      logoUrl: '/images/logo.png'
    },

    socials: {
      instagram: 'https://instagram.com',
      tiktok: 'https://tiktok.com',
      facebook: 'https://facebook.com',
      whatsapp: 'https://wa.me/521234567890'
    },

    activePalette: 'vivelavibePastel',
    palettes: {
      vivelavibePastel: {
        '--bg':'#fcf9ea',
        '--surface':'#fffdf7',
        '--surface-strong':'#fff3f5',
        '--muted':'#badfdb',
        '--primary':'#e6007e',
        '--primary-strong':'#c2006a',
        '--accent':'#ffa4a4',
        '--accent-soft':'#ffbdbd',
        '--text':'#2d1b2f',
        '--text-soft':'#72506b',
        '--shadow':'0 14px 32px rgba(230,0,126,.12)'
      }
    },

    pageThemes: {
      default: {
        '--bg':'#fcf9ea',
        '--surface':'#fffdf7',
        '--surface-strong':'#fff3f5',
        '--muted':'#badfdb',
        '--primary':'#e6007e',
        '--primary-strong':'#c2006a',
        '--accent':'#ffa4a4',
        '--accent-soft':'#ffbdbd',
        '--text':'#2d1b2f',
        '--text-soft':'#72506b',
        '--shadow':'0 14px 32px rgba(230,0,126,.12)'
      },
      contact: {
        '--bg':'#fef7f9',
        '--surface':'#ffffff',
        '--surface-strong':'#eef7f5',
        '--muted':'#a2d4cc',
        '--primary':'#e6007e',
        '--primary-strong':'#c2006a',
        '--accent':'#ffbdbd',
        '--accent-soft':'#ffe5e5',
        '--text':'#2d1b2f',
        '--text-soft':'#7d5c70',
        '--shadow':'0 14px 32px rgba(186,223,219,.28)'
      },
      activities: {
        '--bg':'#fdf4f6',
        '--surface':'#fffafa',
        '--surface-strong':'#fff1f4',
        '--muted':'#badfdb',
        '--primary':'#e6007e',
        '--primary-strong':'#c2006a',
        '--accent':'#ffa4a4',
        '--accent-soft':'#ffbdbd',
        '--text':'#2d1b2f',
        '--text-soft':'#73536d',
        '--shadow':'0 14px 32px rgba(230,0,126,.14)'
      }
    },

    aspectRatios: ['4/3','1/1','16/9','3/4','2/3'],

    lodgings: [],
    lodgingsSource: 'pending',

    activities: [],
    activitiesSource: 'pending'
  };

  window.VIVE_RESOURCES = resources;

  function dispatchLodgingsEvent(){
    try {
      window.dispatchEvent(new CustomEvent('vive:lodgings-loaded', {
        detail: {
          source: resources.lodgingsSource,
          count: resources.lodgings.length,
          companyCode,
        }
      }));
    } catch (error) {
      console.warn('No se pudo emitir el evento vive:lodgings-loaded', error);
    }
  }

  function dispatchActivitiesEvent(){
    try {
      window.dispatchEvent(new CustomEvent('vive:activities-loaded', {
        detail: {
          source: resources.activitiesSource,
          count: resources.activities.length,
          companyCode,
        }
      }));
    } catch (error) {
      console.warn('No se pudo emitir el evento vive:activities-loaded', error);
    }
  }

  async function loadHostings(){
    try {
      const url = new URL(hostingsEndpoint, window.location.href);
      if (!url.searchParams.has('code')) {
        url.searchParams.set('code', companyCode);
      }

      const response = await fetch(url.toString(), {
        headers: { 'Accept': 'application/json' }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const payload = await response.json();
      const lodgings = Array.isArray(payload?.lodgings) ? payload.lodgings : [];

      if (!lodgings.length) {
        console.warn('sp_get_company_properties no devolvio hospedajes para', companyCode, payload);
        resources.lodgings = [];
        resources.lodgingsSource = 'empty';
      } else {
        resources.lodgings = normalizeLodgings(lodgings, resources.currency);
        resources.lodgingsSource = 'database';
      }

      if (Object.prototype.hasOwnProperty.call(window, 'VIVE_RESOURCES_ERROR')) {
        try {
          delete window.VIVE_RESOURCES_ERROR;
        } catch {
          window.VIVE_RESOURCES_ERROR = undefined;
        }
      }
    } catch (error) {
      console.error('Error al cargar hospedajes para', companyCode, error);
      resources.lodgings = [];
      resources.lodgingsSource = 'error';
      window.VIVE_RESOURCES_ERROR = error;
    }

    dispatchLodgingsEvent();
  }

  async function loadActivities(){
    try {
      const url = new URL(activitiesEndpoint, window.location.href);
      if (!url.searchParams.has('code')) {
        url.searchParams.set('code', companyCode);
      }

      const response = await fetch(url.toString(), {
        headers: { 'Accept': 'application/json' }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const payload = await response.json();
      const activities = Array.isArray(payload?.activities) ? payload.activities : [];

      if (!activities.length) {
        resources.activities = [];
        resources.activitiesSource = 'empty';
      } else {
        resources.activities = activities.map((activity, index) => normalizeActivity(activity, index, resources.currency));
        resources.activitiesSource = 'database';
      }

      if (Object.prototype.hasOwnProperty.call(window, 'VIVE_ACTIVITIES_ERROR')) {
        try {
          delete window.VIVE_ACTIVITIES_ERROR;
        } catch {
          window.VIVE_ACTIVITIES_ERROR = undefined;
        }
      }
    } catch (error) {
      console.error('Error al cargar actividades para', companyCode, error);
      resources.activities = [];
      resources.activitiesSource = 'error';
      window.VIVE_ACTIVITIES_ERROR = error;
    }

    dispatchActivitiesEvent();
  }

  async function loadResources(){
    await Promise.all([loadHostings(), loadActivities()]);
    return resources;
  }

  window.VIVE_RESOURCES_READY = loadResources();
})();
