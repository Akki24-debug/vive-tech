/* apiClient.js
   Cliente para tu backend Node. Si falla, usa STUBS con datos locales.
   Endpoints esperados:
   - POST /availability/search   {checkIn, nights, people} -> {results:[...]}
   - GET  /fx/latest?base=MXN&symbols=USD,EUR -> {base, rates:{USD:xx,EUR:yy}}
*/
(function () {
  const API_BASE = window.VIVE_API_BASE || "http://localhost:4000";
  const API_KEY  = window.VIVE_API_KEY  || "DEV_SAMPLE_KEY";
  const CREDS = window.VIVE_CREDENTIALS || {};
  const COMPANY = CREDS.companyCode || 'VIBE';
  // PHP availability endpoint (defaults to root path)
  const AVAIL_ENDPOINT = CREDS.availabilityEndpoint || '/search-availability.php';

  async function req(method, path, body){
    const res = await fetch(`${API_BASE}${path}`, {
      method, headers: {
        "Content-Type":"application/json",
        "Authorization": `Bearer ${API_KEY}`
      },
      body: body ? JSON.stringify(body) : undefined
    });
    if(!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  // ===== STUBS =====
  function stubAvailability({ checkIn, nights, people }){
    const R = window.VIVE_RESOURCES;
    const out = [];
    for(const L of R.lodgings){
      for(const rt of L.roomTypes){
        if(rt.capacity >= people){
          out.push({
            lodgingId: L.id,
            lodgingName: L.name,
            roomTypeId: rt.id,
            roomTypeName: rt.name,
            capacity: rt.capacity,
            pricePerNight: rt.pricePerNight,
            images: rt.images.length ? rt.images : L.images,
            currency: R.currency || "MXN"
          });
        }
      }
    }
    out.sort((a,b)=> a.pricePerNight - b.pricePerNight);
    return { results: out };
  }

  // Rates stub: suposición aproximada; el backend debe devolver lo real (puede usar Google/ECB).
  function stubRates(base, symbols){
    const approx = { MXN:1, USD:0.056, EUR:0.052 }; // ejemplo
    const rates = {};
    symbols.forEach(s => rates[s] = approx[s]);
    return { base, rates };
  }

  const api = {
    async searchAvailability(p){
      // 1) Try PHP endpoint first (no CORS issues on same domain)
      try{
        const res = await fetch(AVAIL_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ code: COMPANY, ...p })
        });
        if(res.ok){
          const data = await res.json();
          if (Array.isArray(data?.results)) return data;
        } else {
          throw new Error(`HTTP ${res.status}`);
        }
      }catch(e){ /* fallback below */ }
      // 2) If you run a Node API, try it; otherwise fall back to stub
      try{
        return await req("POST","/availability/search", p);
      }catch{
        return stubAvailability(p);
      }
    },
    async getRates(base, symbols){
      try{
        const q = `?base=${encodeURIComponent(base)}&symbols=${encodeURIComponent(symbols.join(","))}`;
        return await req("GET", `/fx/latest${q}`);
      }catch{
        return stubRates(base, symbols);
      }
    }
  };

  window.VIVE_API = api;
})();
