/* texts.js - todos los textos centralizados */
window.VIVE_TEXTS = {
  brandName: "Vive la Vibe",
  nav: { activities: "Actividades", lodgings: "Hospedajes", contact: "Contacto", cta: "Cotiza tu Viaje" },
  header: { miniButton: "Ir" },

  intro: {
    title: "Vive la Vibe",
    tagline: "Tu viaje curado en Puerto Escondido",
    subtagline: "Casas boutique, aventuras iconicas y concierge local 24/7"
  },

  booker: {
    title: "Calculadora rapida",
    lodgingLabel: "Hospedaje (opcional)",
    checkinLabel: "Check-in",
    checkoutLabel: "Noches",
    peopleLabel: "Personas",
    searchBtn: "Calcular",
    placeholderSelect: "Todos los hospedajes",
    searching: "Calculando..."
  },

  activities: {
    title: "Actividades destacadas",
    desc: "Selecciona actividades para tu cotizacion (precios por persona)."
  },

  lodgings: {
    title: "Nuestros hospedajes",
    desc: "Casas, cabanas y posadas para distintos estilos y presupuestos.",
    idPrefix: "ID"
  },

  footer: {
    tagline: "Experiencias autenticas y hospedajes curados en la costa de Oaxaca.",
    exploreHeading: "Explora",
    contactHeading: "Contacto",
    contactLines: ["hola@vivelavibe.mx", "+52 555 000 0000"],
    tags: ["Puerto Escondido", "Manialtepec", "Costa de Oaxaca"]
  },

  calc: {
    sections: { lodging: "Hospedaje", activities: "Actividades" },
    checkin: "Check-in",
    nights: "Noches",
    people: "Personas",
    activitiesHint: "Elige actividades (precio por persona).",
    calculateBtn: "Calcular",
    loading: "Calculando...",
    resultsTitle: "Tu cotizacion",

    totalsBox: {
      activities: "Actividades",
      lodging: "Hospedaje",
      total: "Total estimado",
      forNights: "por {n} noches"
    },

    breakdown: {
      title: "Desglose",
      lodgingRange: "Hospedaje (rango)",
      activities: "Actividades",
      perPerson: "por persona",
      xPeople: "{p} personas"
    },

    currency: {
      label: "Moneda",
      MXN: "MXN",
      USD: "USD",
      EUR: "EUR"
    },

    lines: {
      actOne: "para una persona",
      actMany: "para {p} personas",
      dates: "Entrada: {in} - Salida: {out}"
    },

    segments: {
      add: "Agregar hospedaje",
      remove: "Quitar"
    },

    suggestionsTitle: "Sugerencias de hospedaje",
    chooseRoom: "Tipo de habitacion",
    perNight: "por noche",
    changeLodging: "Cambiar hospedaje",
    reserveIntent: "Reservar interes",
    noAvailability: "No hay disponibilidad para esas fechas. Prueba con otras."
  },

  contactPage: {
    metaTitle: "Contacto",
    hero: {
      eyebrow: "Tu concierge en Puerto Escondido",
      title: "Hablemos de tu viaje",
      subtitle: "Disenamos experiencias a medida para que solo te ocupes de disfrutar.",
      description: "Comparte fechas, intereses y presupuesto. Respondemos en menos de 24 horas habiles.",
      primaryCta: "Agenda una llamada",
      secondaryCta: "Escribenos por WhatsApp"
    },
    channels: {
      heading: "Canales de contacto",
      description: "Puedes escribirnos, llamarnos o agendar una videollamada. Siempre hay alguien del equipo listo para ayudarte.",
      items: [
        { label: "Email", value: "hola@vivelavibe.mx" },
        { label: "WhatsApp", value: "+52 555 000 0000" },
        { label: "Telefono", value: "+52 555 111 2222" }
      ]
    },
    office: {
      heading: "Oficina base",
      lines: [
        "Av. Costa Dorada 125",
        "Puerto Escondido, Oaxaca",
        "Mexico"
      ]
    },
    schedule: {
      heading: "Horario de atencion",
      lines: [
        "Lunes a viernes 09:00 - 19:00 hrs",
        "Sabados 10:00 - 14:00 hrs"
      ]
    },
    assistance: {
      heading: "Asesoria personalizada",
      bullets: [
        "Itinerarios a medida con hospedajes, actividades y transporte",
        "Soporte antes, durante y despues del viaje",
        "Equipo bilingue con base en Puerto Escondido"
      ]
    },
    form: {
      heading: "Cuentanos tus planes",
      description: "Completa el formulario y en breve nuestro concierge te contacta con propuestas.",
      fields: {
        name: "Nombre completo",
        email: "Email",
        phone: "WhatsApp",
        dates: "Fechas tentativas",
        guests: "Personas",
        message: "Que tipo de experiencia buscas?"
      },
      submit: "Enviar mensaje",
      privacy: "Protegemos tus datos y solo los utilizamos para responder a tu consulta.",
      success: "Gracias por escribirnos. Te contactaremos muy pronto."
    },
    social: {
      heading: "Siguenos",
      lines: [
        "Noticias sobre nuevas propiedades",
        "Recomendaciones locales y guias de temporada"
      ]
    },
    cta: {
      heading: "Listo para planear tu viaje?",
      body: "Nuestro concierge crea una propuesta con hospedajes y actividades acorde a tus fechas.",
      button: "Habla con un concierge"
    },
    faq: {
      heading: "Preguntas frecuentes",
      items: [
        {
          question: "Cuanto tardan en responder?",
          answer: "En horario laboral respondemos en menos de 24 horas. Fuera de horario dejamos todo listo para la manana siguiente."
        },
        {
          question: "Necesito senar para reservar?",
          answer: "Solicitamos un anticipo del 30% para bloquear hospedajes y actividades. El resto se liquida antes del check-in."
        },
        {
          question: "Pueden organizar transporte?",
          answer: "Si, coordinamos traslados terrestres y aereos, asi como actividades privadas segun el perfil del grupo."
        }
      ]
    }
  },

  activitiesPage: {
    metaTitle: "Actividades",
    featuredTag: "Seleccion VIBE",
    featuredDescription: "Las experiencias favoritas de nuestros viajeros.",
    hero: {
      eyebrow: "Experiencias curadas",
      title: "Actividades para todos los ritmos",
      subtitle: "Tours iconicos y experiencias privadas diseniadas por Vive la Vibe.",
      description: "Elige entre aventuras de adrenalina, escapes relajantes y experiencias exclusivas para huespedes VIBE.",
      primaryCta: "Explorar tours",
      secondaryCta: "Solicitar itinerario"
    },
    tours: {
      heading: "Tours guiados",
      description: "Exploraciones grupales con operadores certificados y logistica completa.",
      items: [
        {
          id: "bio",
          title: "Laguna de bioluminiscencia",
          duration: "3 horas",
          intensity: "Baja",
          description: "Navega por la laguna de Manialtepec y observa el plancton brillando con cada movimiento.",
          includes: ["Traslado redondo", "Guia bilingue", "Bebidas ligeras"]
        },
        {
          id: "delfines",
          title: "Avistamiento de delfines y tortugas",
          duration: "4 horas",
          intensity: "Media",
          description: "Salimos al amanecer para encontrar manadas de delfines, tortugas y en temporada ballenas jorobadas.",
          includes: ["Equipo de seguridad", "Fotografias", "Snack saludable"]
        },
        {
          id: "cascadas",
          title: "Cascadas y cafetales de la sierra",
          duration: "Dia completo",
          intensity: "Media",
          description: "Excursion guiada a cafetales de altura y cascadas escondidas con almuerzo tradicional.",
          includes: ["Transporte 4x4", "Almuerzo", "Entradas"]
        }
      ],
      note: "Consulta disponibilidad segun temporada alta o condiciones del clima."
    },
    vibe: {
      heading: "VIBE Activities",
      description: "Experiencias privadas diseniadas exclusivamente para huespedes de Vive la Vibe.",
      items: [
        {
          id: "chef",
          title: "Cena privada con chef invitado",
          duration: "3 horas",
          description: "Menu degustacion inspirado en ingredientes oaxaquenos preparado en tu hospedaje.",
          highlights: ["Menu personalizado", "Maridaje con vinos mexicanos", "Chef y sous-chef en sitio"]
        },
        {
          id: "wellness",
          title: "Sunrise wellness session",
          duration: "90 minutos",
          description: "Clase de yoga frente al mar con masaje relajante y jugos cold press para iniciar el dia.",
          highlights: ["Instructor certificado", "Kit wellness", "Playlist curada"]
        },
        {
          id: "mixologia",
          title: "Taller de mixologia oaxaquena",
          duration: "2 horas",
          description: "Aprende a preparar cocteles de autor con mezcal premium guiado por un mixologo local.",
          highlights: ["Mezcales seleccionados", "Recetario digital", "Bartender dedicado"]
        }
      ]
    },
    cta: {
      heading: "Listo para vivirlo?",
      body: "Nuestro concierge arma un itinerario segun tus fechas, estilo y presupuesto.",
      button: "Cotiza actividades"
    }
  }
};
