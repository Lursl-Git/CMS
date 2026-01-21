<?php
/**
 * Template Name: Karte (KuLaDig)
 * Template Post Type: page
 */
if ( ! defined('ABSPATH') ) { exit; }
get_header();
?>

<style>
  .route-box__title{
    width:100%;
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:transparent;
    border:0;
    padding:0;
    margin:0;
    font:inherit;
    color:inherit;
    cursor:pointer;
    text-align:left;
  }
</style>

<main id="main" class="karte-page">
  <section class="container karte-container">
    <div class="karte-hero">
      <h1>Entdecke Rheinland Pfalz</h1>
      <p>Erkunde kulturelle und historische Orte auf der interaktiven Karte</p>
    </div>

    <div class="karte-layout">
      <aside class="karte-side" aria-label="Objektliste">
        <div class="karte-side__head">
          <h2>Objekte</h2>
          <p id="list-hint">Lade Daten…</p>
        </div>

        <!-- Route Builder (eingeklappt per Default) -->
        <div class="route-box" aria-label="Routenplaner">
          <button class="route-box__title" id="route-toggle" type="button" aria-expanded="false" aria-controls="route-body">
            <span>Routenplaner</span>
            <span id="route-count" style="opacity:.8;font-size:.9rem;">0 Stops</span>
          </button>

          <div id="route-body" hidden>
            <div class="route-stops" id="route-stops"></div>
            <div class="route-actions">
              <button class="btn" id="route-clear" type="button" disabled>Leeren</button>
              <button class="btn btn-primary" id="route-open" type="button" disabled>Google Maps öffnen</button>
            </div>
            <div style="margin-top:10px;color:#9aa3b2;font-size:.85rem;">
              Tipp: In Popup oder Liste „Zur Route“ drücken (max. 10 Stopps).
            </div>
          </div>
        </div>

        <div class="karte-side__list" id="objekt-list">
          <div style="color:#9aa3b2;padding:10px;">Wird geladen…</div>
        </div>

        <div class="karte-side__head" style="border-top:1px solid #1d2530; border-bottom: none;">
          <div class="load-more-wrap">
            <button class="btn load-more" id="load-more" type="button" disabled>Mehr laden</button>
          </div>
          <p id="list-pageinfo" style="margin-top:10px;">—</p>
        </div>
      </aside>

      <div>
        <div class="map-wrap">
          <div id="map" role="region" aria-label="Interaktive Karte"></div>
        </div>
        <div id="map-status"></div>
      </div>
    </div>
  </section>
</main>

<!-- Leaflet + Cluster -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<script defer src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
window.addEventListener('load', function(){
  const statusEl = document.getElementById('map-status');
  const listEl   = document.getElementById('objekt-list');
  const hintEl   = document.getElementById('list-hint');
  const loadMoreBtn = document.getElementById('load-more');
  const pageInfoEl  = document.getElementById('list-pageinfo');

  const routeBodyEl  = document.getElementById('route-body');
  const routeToggleBtn = document.getElementById('route-toggle');
  const routeStopsEl = document.getElementById('route-stops');
  const routeCountEl = document.getElementById('route-count');
  const routeClearBtn= document.getElementById('route-clear');
  const routeOpenBtn = document.getElementById('route-open');

  function setStatus(msg, loading=false){
    if(!statusEl) return;
    statusEl.innerHTML = loading
      ? `<span class="spinner" aria-hidden="true"></span><span>${msg||''}</span>`
      : `<span>${msg||''}</span>`;
  }
  function setHint(msg){ if(hintEl) hintEl.textContent = msg || ''; }

  if (!window.L) { setStatus('Leaflet konnte nicht geladen werden.'); return; }

  const ajaxURL = "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>";
  const objektBase = "<?php echo esc_url( home_url('/objekt/') ); ?>";
  function objUrl(id){ return id ? (objektBase + '?id=' + encodeURIComponent(id)) : '#'; }
  function docUrl(token){ return token ? ('https://www.kuladig.de/api/public/Dokument?token=' + encodeURIComponent(token)) : ''; }
  function esc(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  // Route UI Toggle (eingeklappt per Default)
  function setRouteOpen(open){
    if(!routeBodyEl || !routeToggleBtn) return;
    if(open){
      routeBodyEl.removeAttribute('hidden');
      routeToggleBtn.setAttribute('aria-expanded','true');
    }else{
      routeBodyEl.setAttribute('hidden','');
      routeToggleBtn.setAttribute('aria-expanded','false');
    }
  }
  if(routeToggleBtn){
    routeToggleBtn.addEventListener('click', () => {
      const isOpen = routeBodyEl && !routeBodyEl.hasAttribute('hidden');
      setRouteOpen(!isOpen);
    });
  }

  const map = L.map('map', { zoomControl: true }).setView([49.4447, 7.7690], 10);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap', maxZoom: 19
  }).addTo(map);

  const cluster = L.markerClusterGroup({
    showCoverageOnHover: false,
    spiderfyOnMaxZoom: true,
    maxClusterRadius: 55
  });
  map.addLayer(cluster);

  let loading = false, lastKey = '';
  const markersById = new Map();
  let activeId = null;

  const PAGE_SIZE = 25;
  let listPage = 1;
  let allItemsForList = [];

  const MAX_STOPS = 10;
  const routeStops = [];

  function setActive(id){
    activeId = id;
    listEl?.querySelectorAll('.karte-item').forEach(card => {
      card.classList.toggle('is-active', card.dataset.id === id);
    });
  }

  function updateRouteUI(){
    if(!routeStopsEl) return;
    routeStopsEl.innerHTML = routeStops.length ? routeStops.map((s, idx) => `
      <div class="route-stop">
        <div class="name">${esc(s.name)}</div>
        <button type="button" data-remove="${idx}" title="Entfernen">×</button>
      </div>
    `).join('') : `<div style="color:#9aa3b2;font-size:.9rem;text-align:center;padding:8px;">Keine Stopps ausgewählt.</div>`;

    routeStopsEl.querySelectorAll('[data-remove]').forEach(btn => {
      btn.addEventListener('click', () => {
        const i = Number(btn.getAttribute('data-remove'));
        if(Number.isFinite(i)) routeStops.splice(i,1);
        updateRouteUI();
      });
    });

    if(routeCountEl) routeCountEl.textContent = `${routeStops.length} Stops`;
    routeClearBtn.disabled = routeStops.length === 0;
    routeOpenBtn.disabled  = routeStops.length < 1;
  }

  function addStopFromItem(it){
    const lat = Number(it.lat), lng = Number(it.lng);
    if(!Number.isFinite(lat) || !Number.isFinite(lng)) return;

    if(routeStops.find(s => s.id === it.id)) {
      setStatus('Stop ist schon in der Route.', false);
      return;
    }
    if(routeStops.length >= MAX_STOPS){
      setStatus(`Maximal ${MAX_STOPS} Stopps möglich.`, false);
      return;
    }

    routeStops.push({ id: it.id, name: it.name || 'Unbenannt', lat, lng });
    updateRouteUI();

    //Wenn man Stopps hinzufügt, Routenplaner automatisch aufklappen
    setRouteOpen(true);

    setStatus(`Zur Route hinzugefügt: ${it.name}`, false);
  }

function openGoogleMapsRoute(){
  if (routeStops.length < 1) return;

  const destination = `${routeStops[routeStops.length-1].lat},${routeStops[routeStops.length-1].lng}`;

  
  const waypointsArr = routeStops.slice(0, -1).map(s => `${s.lat},${s.lng}`);
  const waypoints = waypointsArr.join('|');

  const buildUrl = (origin) => {
    const base = new URL('https://www.google.com/maps/dir/');
    base.searchParams.set('api', '1');
    base.searchParams.set('destination', destination);
    if (origin) base.searchParams.set('origin', origin);
    if (waypointsArr.length) base.searchParams.set('waypoints', waypoints);
    window.open(base.toString(), '_blank', 'noopener');
  };

  if(navigator.geolocation){
    navigator.geolocation.getCurrentPosition(
      pos => buildUrl(`${pos.coords.latitude},${pos.coords.longitude}`),
      ()  => buildUrl(null),
      { enableHighAccuracy:true, timeout:6000, maximumAge:60000 }
    );
  } else {
    buildUrl(null);
  }
}

  routeClearBtn.addEventListener('click', () => {
    routeStops.splice(0, routeStops.length);
    updateRouteUI();
  });
  routeOpenBtn.addEventListener('click', openGoogleMapsRoute);

  function focusMarker(id){
    const m = markersById.get(id);
    if(!m) return;
    setActive(id);
    map.setView(m.getLatLng(), Math.max(map.getZoom(), 13), { animate:true });
    m.openPopup();
  }

  function renderListPaged(){
    if(!listEl) return;

    const total = allItemsForList.length;
    const shown = Math.min(total, listPage * PAGE_SIZE);
    const items = allItemsForList.slice(0, shown);

    if(!items.length){
      listEl.innerHTML = `<div style="color:#9aa3b2;padding:10px;">Keine Treffer im aktuellen Ausschnitt.</div>`;
      setHint('0 Treffer');
      loadMoreBtn.disabled = true;
      if(pageInfoEl) pageInfoEl.textContent = '—';
      return;
    }

    setHint(`${total} Treffer`);
    if(pageInfoEl) pageInfoEl.textContent = `Zeige ${shown} von ${total} Objekten`;

    loadMoreBtn.disabled = shown >= total;
    loadMoreBtn.textContent = shown >= total ? 'Alle geladen' : `Mehr laden (+${Math.min(PAGE_SIZE, total - shown)})`;

    listEl.innerHTML = items.map(it => {
      const title = esc(it.name||'');
      const short = esc(String(it.desc||'').slice(0,80));
      const thumb = it.token
        ? `<img src="${esc(docUrl(it.token))}" alt="">`
        : `<span style="opacity:.8;">📍</span>`;

      const url = it.url || objUrl(it.id);

      return `
        <div class="karte-item-wrap">
          <div class="karte-item ${activeId===it.id?'is-active':''}" role="button" tabindex="0" data-id="${esc(it.id)}">
            <div class="thumb">${thumb}</div>
            <div>
              <a class="karte-item__title" href="${esc(url)}">${title}</a>
              <div class="karte-item__meta">${short}${short ? '…' : ''}</div>
              <div class="karte-item__actions">
                <button class="mini" type="button" data-focus="${esc(it.id)}">Anzeigen</button>
                <button class="mini" type="button" data-route="${esc(it.id)}">Zur Route</button>
                <a class="mini" href="${esc(url)}">Details</a>
              </div>
            </div>
          </div>
        </div>
      `;
    }).join('');

    listEl.querySelectorAll('[data-focus]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = btn.getAttribute('data-focus');
        focusMarker(id);
      });
    });

    listEl.querySelectorAll('[data-route]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = btn.getAttribute('data-route');
        const it = allItemsForList.find(x => x.id === id);
        if(it) addStopFromItem(it);
      });
    });

    // Klick auf Marker
    listEl.querySelectorAll('.karte-item[role="button"]').forEach(card => {
      const id = card.getAttribute('data-id');

      card.addEventListener('click', (e) => {
        if (e.target.closest('.karte-item__actions')) return;
        if (e.target.closest('a')) return;
        focusMarker(id);
      });

      card.addEventListener('keydown', (e) => {
        if(e.key === 'Enter' || e.key === ' '){
          e.preventDefault();
          focusMarker(id);
        }
      });
    });
  }

  loadMoreBtn.addEventListener('click', () => {
    listPage++;
    renderListPaged();
  });

  async function loadPage(bounds, page){
    const sw = bounds.getSouthWest(), ne = bounds.getNorthEast();
    const p = new URLSearchParams({
      action: 'kld_map',
      sw_lng: sw.lng, sw_lat: sw.lat,
      ne_lng: ne.lng, ne_lat: ne.lat,
      page: page
    });
    const url = ajaxURL + '?' + p.toString();
    const res = await fetch(url, { headers: { 'Accept':'application/json' } });
    const json = await res.json();
    if (!json.success) throw new Error(json.data?.message || 'Proxy-Fehler');
    return json.data;
  }

  async function loadObjects(){
    if (loading) return;
    loading = true;

    const key = JSON.stringify(map.getBounds());
    lastKey = key;

    setStatus('Lade Objekte…', true);

    const tempCluster = L.markerClusterGroup({
      showCoverageOnHover: false,
      spiderfyOnMaxZoom: true,
      maxClusterRadius: 55
    });

    const tempMarkers = new Map();

    const seenIds = new Set();

    const collected = [];

    try{
      let page = 0, pages = 1;
      while (page < pages && page < 5) {
        const data = await loadPage(map.getBounds(), page);
        if (lastKey !== key) return;

        (data.items || []).forEach(it => {
          const lat = Number(it.pt?.lat), lng = Number(it.pt?.lng);
          if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

          const rawId = (it.id != null) ? String(it.id) : '';
          const idKey = rawId ? rawId : `${lat}|${lng}|${it.name||''}`;

          // Wenn gleiche ID schon da ist → skip
          if (seenIds.has(idKey)) return;
          seenIds.add(idKey);

          const img = docUrl(it.token || '');
          const url = rawId ? objUrl(rawId) : '#';
          const title = esc(it.name||'');
          const desc  = esc(String(it.desc||'').slice(0,160));

          const popupHtml = `
            ${img ? `<img src="${esc(img)}" alt="" class="kld-thumb">` : ''}
            <div class="kld-popup">
              <h3><a href="${esc(url)}" style="color:#0f172a;text-decoration:none">${title}</a></h3>
              <p>${desc}${desc ? ' …' : ''}</p>
              <div style="display:flex;gap:8px;margin-top:10px;">
                <button type="button" class="mini" data-popup-route="${esc(rawId)}">Zur Route</button>
              </div>
            </div>
          `;

          const marker = L.marker([lat, lng]).bindPopup(popupHtml, { maxWidth: 320 });

          marker.on('click', () => setActive(rawId));
          marker.on('popupopen', (ev) => {
            const el = ev.popup?.getElement?.();
            const b = el?.querySelector?.('[data-popup-route]');
            if (b) {
              b.addEventListener('click', () => {
                const found = collected.find(x => x.id === rawId);
                if(found) addStopFromItem(found);
              }, { once:true });
            }
          });

          tempCluster.addLayer(marker);
          if(rawId) tempMarkers.set(rawId, marker);

          collected.push({
            id: rawId,
            name: it.name,
            desc: it.desc,
            token: it.token || '',
            lat, lng,
            distance: it.distance,
            url
          });
        });

        pages = Math.max(1, parseInt(data.anzahlSeiten || 1, 10));
        page++;
      }

      map.removeLayer(cluster);
      cluster.clearLayers();
      tempCluster.eachLayer(layer => cluster.addLayer(layer));
      map.addLayer(cluster);

      markersById.clear();
      tempMarkers.forEach((v,k)=> markersById.set(k,v));

      allItemsForList = collected;
      listPage = 1;
      renderListPaged();

      setStatus(collected.length ? `Geladen: ${collected.length} Marker` : 'Keine Treffer im aktuellen Ausschnitt.');

    }catch(e){
      console.error('Karte/API', e);
      setStatus('Konnte Daten nicht laden: ' + (e.message || e));
      if(listEl) listEl.innerHTML = `<div style="color:#9aa3b2;padding:10px;">Fehler: ${esc(e.message || e)}</div>`;
      setHint('Fehler');
      loadMoreBtn.disabled = true;
    }finally{
      loading = false;
    }
  }

  updateRouteUI();

  let t=null;
  map.on('moveend', ()=>{ clearTimeout(t); t = setTimeout(loadObjects, 250); });
  loadObjects();
});
</script>

<?php get_footer(); ?>
