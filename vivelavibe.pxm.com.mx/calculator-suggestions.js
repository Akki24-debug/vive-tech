/* calculator-suggestions.js v1.0
   Centraliza la lgica de datasets y combinaciones de habitaciones para la calculadora.
*/
(function () {
  const global = typeof window !== 'undefined' ? window : {};

  function resources() {
    return global.VIVE_RESOURCES || {};
  }

  function fallbackCurrency() {
    return resources().currency || 'MXN';
  }

  function safeNumber(value) {
    if (typeof value === 'number' && Number.isFinite(value)) return value;
    if (typeof value === 'string' && value.trim() !== '') {
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : null;
    }
    return null;
  }

  function average(values) {
    return values.length ? values.reduce((sum, value) => sum + value, 0) / values.length : null;
  }

  function buildEntriesFromResources(people) {
    const entries = [];
    const data = resources();
    (data.lodgings || []).forEach((lodging, lodgingIndex) => {
      const lodgingId = lodging.id || lodging.code || String(lodgingIndex + 1);
      const lodgingName = lodging.name || lodgingId;
      const lodgingImages = Array.isArray(lodging.images) ? lodging.images.filter(Boolean) : [];
      (lodging.roomTypes || []).forEach((room, roomIndex) => {
        const basePrice = safeNumber(room.pricePerNight);
        const range = room.priceRange || {};
        const rangeAverage = safeNumber(range.average);
        const rangeMin = safeNumber(range.min);
        const rangeMax = safeNumber(range.max);
        const price = basePrice ?? rangeAverage ?? rangeMin ?? rangeMax;
        if (!Number.isFinite(price) || price <= 0) return;
        const capacity = safeNumber(room.capacity) ?? null;
        if (capacity != null && capacity <= 0) return;
        const roomImages = Array.isArray(room.images) ? room.images.filter(Boolean) : [];
        entries.push({
          lodgingId,
          lodgingName,
          lodgingData: lodging,
          roomTypeId: room.id || room.code || `${lodgingId}-room-${roomIndex + 1}`,
          roomTypeName: room.name || `Habitacin ${roomIndex + 1}`,
          pricePerNight: price,
          capacity,
          images: roomImages.length ? roomImages : lodgingImages,
          currency: room.currency || lodging.currency || data.currency || 'MXN',
          location: lodging.location || lodging.address || null,
          description: room.description || '',
          minOccupancy: safeNumber(room.minOccupancy) ?? null
        });
      });
    });
    return entries;
  }

  function aggregateLodgings(entries) {
    const map = new Map();
    entries.forEach((entry) => {
      if (!entry) return;
      const lodgingId = String(entry.lodgingId || entry.lodgingName || 'lodging');
      const price = safeNumber(entry.pricePerNight);
      const room = {
        lodgingId,
        lodgingName: entry.lodgingName || entry.lodgingId || 'Hospedaje',
        roomTypeId: entry.roomTypeId || '',
        roomTypeName: entry.roomTypeName || 'Habitacin',
        pricePerNight: price,
        capacity: entry.capacity ?? null,
        images: Array.isArray(entry.images) ? entry.images.filter(Boolean) : [],
        currency: entry.currency || fallbackCurrency(),
        description: entry.description || '',
        location: entry.location || null,
        minOccupancy: entry.minOccupancy ?? null
      };
      if (!map.has(lodgingId)) {
        map.set(lodgingId, {
          lodgingId,
          lodgingName: room.lodgingName,
          rooms: [],
          images: room.images.slice(),
          minPrice: Number.isFinite(price) ? price : null,
          maxPrice: Number.isFinite(price) ? price : null
        });
      }
      const current = map.get(lodgingId);
      current.rooms.push(room);
      if (room.images.length) current.images = room.images.slice();
      if (Number.isFinite(price)) {
        current.minPrice = current.minPrice == null ? price : Math.min(current.minPrice, price);
        current.maxPrice = current.maxPrice == null ? price : Math.max(current.maxPrice, price);
      }
    });
    const lodgings = Array.from(map.values()).map((item) => {
      const validPrices = item.rooms.map((room) => room.pricePerNight).filter((v) => Number.isFinite(v));
      item.averagePrice = validPrices.length ? average(validPrices) : null;
      return item;
    });
    lodgings.sort((a, b) => {
      const av = Number.isFinite(a.averagePrice) ? a.averagePrice : Infinity;
      const bv = Number.isFinite(b.averagePrice) ? b.averagePrice : Infinity;
      return av - bv;
    });
    return { lodgings, map };
  }

  function computeBands(entries) {
    const prices = entries
      .map((entry) => safeNumber(entry.pricePerNight))
      .filter((value) => Number.isFinite(value) && value > 0)
      .sort((a, b) => a - b);
    if (!prices.length) {
      return { friendly: null, standard: null, premium: null, min: null, max: null, average: null };
    }
    const min = prices[0];
    const max = prices[prices.length - 1];
    const avg = average(prices);
    const chunk = Math.max(1, Math.floor(prices.length / 3));
    const friendly = average(prices.slice(0, chunk)) ?? avg;
    const premium = average(prices.slice(prices.length - chunk)) ?? avg;
    const midStart = chunk;
    const midEnd = Math.max(midStart, prices.length - chunk);
    const standardSlice = prices.slice(midStart, midEnd);
    const standard = standardSlice.length ? average(standardSlice) : avg;
    return { friendly, standard, premium, min, max, average: avg };
  }

  function cloneRoom(entry) {
    return {
      lodgingId: entry.lodgingId,
      lodgingName: entry.lodgingName,
      roomTypeId: entry.roomTypeId,
      roomTypeName: entry.roomTypeName,
      pricePerNight: entry.pricePerNight,
      capacity: entry.capacity ?? null,
      images: entry.images,
      currency: entry.currency || fallbackCurrency(),
      description: entry.description || '',
      location: entry.location || null,
      minOccupancy: entry.minOccupancy ?? null
    };
  }

  function addRoomToCombo(existingRooms, entry) {
    const next = existingRooms.map((room) => ({ ...room }));
    const found = next.find((room) => room.roomTypeId === entry.roomTypeId && room.lodgingId === entry.lodgingId);
    if (found) {
      found.count += 1;
      found.totalCapacity += entry.capacity || 0;
      found.subtotalPerNight += entry.pricePerNight || 0;
    } else {
      const clone = cloneRoom(entry);
      next.push({
        ...clone,
        count: 1,
        totalCapacity: entry.capacity || 0,
        subtotalPerNight: entry.pricePerNight || 0
      });
    }
    return next;
  }

  function isBetterCombo(candidate, current, peopleTarget) {
    if (!current) return true;
    if (candidate.roomsCount !== current.roomsCount) {
      return candidate.roomsCount < current.roomsCount;
    }
    if (candidate.cost !== current.cost) {
      return candidate.cost < current.cost;
    }
    const candidateOvershoot = Math.max(0, candidate.capacity - peopleTarget);
    const currentOvershoot = Math.max(0, current.capacity - peopleTarget);
    if (candidateOvershoot !== currentOvershoot) {
      return candidateOvershoot < currentOvershoot;
    }
    return false;
  }

  function bestComboForLodging(lodging, people) {
    const rooms = lodging.rooms.filter((room) => Number.isFinite(room.pricePerNight));
    if (!rooms.length) return null;
    const maxCapacity = Math.max(...rooms.map((room) => room.capacity || 0), 0);
    const target = Math.max(people || 1, 1);
    const limit = target + (maxCapacity || target);
    const dp = Array(limit + 1).fill(null);
    dp[0] = { cost: 0, roomsCount: 0, rooms: [], capacity: 0 };
    for (let p = 0; p <= limit; p += 1) {
      const state = dp[p];
      if (!state) continue;
      for (const room of rooms) {
        const cap = room.capacity || 1;
        const nextIndex = Math.min(limit, p + cap);
        const nextCost = state.cost + (room.pricePerNight || 0);
        const nextRoomsCount = state.roomsCount + 1;
        const nextRooms = addRoomToCombo(state.rooms, room);
        const nextCapacity = state.capacity + cap;
        const candidate = {
          cost: nextCost,
          roomsCount: nextRoomsCount,
          rooms: nextRooms,
          capacity: nextCapacity
        };
        if (isBetterCombo(candidate, dp[nextIndex], target)) {
          dp[nextIndex] = candidate;
        }
      }
    }
    let best = null;
    for (let i = target; i <= limit; i += 1) {
      const state = dp[i];
      if (!state) continue;
      if (isBetterCombo(state, best, target)) {
        best = state;
      }
    }
    if (!best) return null;
    const combinationRooms = best.rooms.map((room) => ({
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
    }));
    return {
      lodgingId: lodging.lodgingId,
      lodgingName: lodging.lodgingName,
      rooms: combinationRooms,
      totalPerNight: best.cost,
      totalCapacity: best.capacity,
      roomsCount: best.roomsCount,
      images: lodging.images
    };
  }

  function findBestCombination(aggregate, people, targetNightly) {
    if (!aggregate || !Array.isArray(aggregate.lodgings)) return null;
    let best = null;
    aggregate.lodgings.forEach((lodging) => {
      const combo = bestComboForLodging(lodging, people);
      if (!combo) return;
      const diff = Number.isFinite(targetNightly) ? Math.abs((combo.totalPerNight || 0) - targetNightly) : 0;
      const score = {
        ...combo,
        diff
      };
      if (!best) {
        best = score;
        return;
      }
      if (score.roomsCount !== best.roomsCount) {
        if (score.roomsCount < best.roomsCount) best = score;
        return;
      }
      if (Number.isFinite(targetNightly)) {
        if (score.diff < best.diff) {
          best = score;
          return;
        }
      }
      if (score.totalPerNight < best.totalPerNight) {
        best = score;
      }
    });
    return best;
  }

  function summarizeRooms(combo) {
    if (!combo || !Array.isArray(combo.rooms) || !combo.rooms.length) return '';
    return combo.rooms
      .map((room) => {
        const qty = room.count > 1 ? `${room.count} ` : '';
        const capacity = room.totalCapacity || (room.capacity || 0) * (room.count || 1);
        const pax = capacity ? ` (${capacity} pax)` : '';
        return `${qty}${room.roomTypeName}${pax}`;
      })
      .join(', ');
  }

  global.VIVE_SUGGEST = {
    buildEntriesFromResources,
    aggregateLodgings,
    computeBands,
    findBestCombination,
    summarizeRooms,
    safeNumber // export for convenience inside calculator.js
  };
})();
