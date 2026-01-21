<?php
/**
 * Template Name: Tour (Detail)
 * Template Post Type: page
 */
if ( ! defined('ABSPATH') ) { exit; }
get_header();


$tourId = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
$ajaxURL = admin_url('admin-ajax.php');
$objectBase = home_url('/objekt/'); 
?>

<style>
  :root { --tour-gap: 100px; }
  #main { padding-top: var(--tour-gap); }
  :target { scroll-margin-top: var(--tour-gap); }

  .tour-wrap{display:grid;grid-template-columns: 1.25fr .75fr;gap:18px;align-items:start;}
  @media (max-width: 980px){ .tour-wrap{grid-template-columns:1fr;} }

  .tour-panel{
    border:1px solid #1d2530;border-radius:16px;
    background:rgba(255,255,255,.03);
    overflow:hidden;
    box-shadow:0 10px 28px rgba(0,0,0,.22);
  }
  .tour-head{padding:16px 16px 12px;border-bottom:1px solid #1d2530;}
  .tour-head h1{margin:0 0 8px;font-size:1.6rem;}
  .tour-head p{margin:0;color:#c9d1e1;line-height:1.5;}
  .tour-meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px;}
  .badge{
    display:inline-flex;align-items:center;gap:6px;
    font-size:.85rem;line-height:1;
    padding:6px 10px;border-radius:999px;
    border:1px solid #233043;
    background:rgba(255,255,255,0.04);
    color:#c9d1e1;
  }
  .badge small{opacity:.85}

  #tour-map{height:460px;background:rgba(255,255,255,.04);}
  @media (max-width:980px){ #tour-map{height:380px;} }

  .tour-actions{display:flex;gap:10px;flex-wrap:wrap;padding:12px 16px;border-top:1px solid #1d2530;background:rgba(255,255,255,0.02);}
  .btn-ghost{padding:10px 12px;border-radius:12px;border:1px solid #233043;background:rgba(255,255,255,0.03);color:#f5f7fb;cursor:pointer;}
  .btn-ghost:disabled{opacity:.55;cursor:not-allowed;}

  .stops{ padding:14px 16px; }
  .stop{
    display:flex;gap:10px;align-items:flex-start;
    padding:10px 0;border-bottom:1px solid rgba(29,37,48,.65);
  }
  .stop:last-child{border-bottom:0;}
  .num{
    width:28px;height:28px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    border:1px solid #233043;background:rgba(255,255,255,0.04);
    color:#c9d1e1;font-size:.9rem;flex:0 0 auto;
  }
  .stop a{color:#f5f7fb;text-decoration:none;}
  .muted{color:#9aa3b2;font-size:.9rem;line-height:1.35;}
</style>

<section class="container" style="padding:32px 0 48px;">
  <?php if(!$tourId): ?>
    <h1>Tour</h1>
    <p>Keine Tour-ID übergeben. Öffne die Seite als <code>?id=KLD-…</code>.</p>
  <?php else: ?>
    <div class="tour-wrap" id="tour-app"
      data-id="<?php echo esc_attr($tourId); ?>"
      data-ajax="<?php echo esc_url($ajaxURL); ?>"
      data-object-base="<?php echo esc_url($objectBase); ?>"
    >
      <div class="tour-panel">
        <div class="tour-head">
          <h1 id="tour-title">Tour wird geladen…</h1>
          <p id="tour-desc" class="muted"></p>

          <div class="tour-meta">
            <span class="badge" id="tour-stops">🧷 <span class="v">—</span><small>Stopps</small></span>
            <span class="badge" id="tour-km">📏 <span class="v">—</span><small>km</small></span>
            <span class="badge" id="tour-min" style="display:none;">⏱️ <span class="v">—</span><small>min (gehen)</small></span>
          </div>
        </div>

        <div id="tour-map"></div>

        <div class="tour-actions">
          <button class="btn-ghost" type="button" id="gmaps">In Google Maps öffnen</button>
          <a class="btn btn-primary" href="<?php echo esc_url( home_url('/touren/') ); ?>">Zurück zu Touren</a>
        </div>
      </div>

      <aside class="tour-panel">
        <div class="tour-head">
          <h2 style="margin:0;font-size:1.15rem;">Stopps</h2>
          <p class="muted" style="margin-top:8px;">Klicke auf einen Stopp, um zur Objektansicht zu springen.</p>
        </div>
        <div class="stops" id="tour-stops-list">
          <div class="muted">Lade Stopps…</div>
        </div>
      </aside>
    </div>
  <?php endif; ?>
</section>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
window.addEventListener('load', async () => {
  if(!window.L) return;
  const app = document.getElementById('tour-app');
  if(!app) return;

  const ajaxURL = app.dataset.ajax;
  const tourId  = app.dataset.id;
  const objectBase = app.dataset.objectBase;

  const titleEl = document.getElementById('tour-title');
  const descEl  = document.getElementById('tour-desc');
  const stopsEl = document.querySelector('#tour-stops .v');
  const kmEl    = document.querySelector('#tour-km .v');
  const minWrap = document.getElementById('tour-min');
  const minEl   = document.querySelector('#tour-min .v');
  const listEl  = document.getElementById('tour-stops-list');
  const gmapsBtn= document.getElementById('gmaps');

  function esc(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function kmToMinutes(km, speedKmH){ return Math.round((km/speedKmH)*60); }

  async function fetchTour(){
    const u = new URL(ajaxURL);
    u.searchParams.set('action','kld_route_preview');
    u.searchParams.set('id', tourId);
    u.searchParams.set('limit','80');
    const res = await fetch(u.toString(), { headers:{'Accept':'application/json'} });
    const json = await res.json();
    if(!json || !json.success) throw new Error(json?.data?.message || 'Tour-Fehler');
    return json.data;
  }

  const map = L.map('tour-map', { zoomControl:true }).setView([49.4447, 7.7690], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

  let currentStops = [];

  function render(data){
    titleEl.textContent = data.group?.name || 'Tour';
    descEl.textContent  = data.group?.desc || '';

    currentStops = Array.isArray(data.stops) ? data.stops : [];
    const km = Number(data.total_km || 0);

    if(stopsEl) stopsEl.textContent = String(currentStops.length || 0);
    if(kmEl) kmEl.textContent = km ? km.toFixed(1).replace('.', ',') : '—';
    if(minWrap && minEl && km){
      minWrap.style.display = '';
      minEl.textContent = String(kmToMinutes(km, 4));
    }

    if(listEl){
      listEl.innerHTML = currentStops.length ? currentStops.map((s, i) => {
        const url = objectBase + '?id=' + encodeURIComponent(s.id);
        return `
          <div class="stop">
            <div class="num">${i+1}</div>
            <div>
              <div><a href="${esc(url)}">${esc(s.name || s.id)}</a></div>
              <div class="muted">${Number.isFinite(Number(s.lat)) ? (Number(s.lat).toFixed(4)+', '+Number(s.lng).toFixed(4)) : ''}</div>
            </div>
          </div>
        `;
      }).join('') : `<div class="muted">Keine Stopps/Koordinaten gefunden.</div>`;
    }

    const latlngs = currentStops
      .map(s => [Number(s.lat), Number(s.lng)])
      .filter(p => Number.isFinite(p[0]) && Number.isFinite(p[1]));

    if(latlngs.length >= 1){
      latlngs.forEach((p, idx) => {
        L.circleMarker(p, { radius: 6 }).addTo(map).bindTooltip(String(idx+1), {direction:'top'});
      });
    }
    if(latlngs.length >= 2){
      const line = L.polyline(latlngs, { weight: 5 }).addTo(map);
      map.fitBounds(line.getBounds(), { padding:[18,18] });
    } else if(latlngs.length === 1){
      map.setView(latlngs[0], 14);
    }
  }

  function openGoogleMaps(){
    if(!currentStops || currentStops.length < 2) return;

    const origin = `${currentStops[0].lat},${currentStops[0].lng}`;
    const destination = `${currentStops[currentStops.length-1].lat},${currentStops[currentStops.length-1].lng}`;
    const waypoints = currentStops.length > 2
      ? currentStops.slice(1, -1).map(s => `${s.lat},${s.lng}`).join('|')
      : '';

    const base = new URL('https://www.google.com/maps/dir/');
    base.searchParams.set('api', '1');
    base.searchParams.set('origin', origin);
    base.searchParams.set('destination', destination);
    if (waypoints) base.searchParams.set('waypoints', waypoints);
    window.open(base.toString(), '_blank', 'noopener');
  }

  gmapsBtn?.addEventListener('click', openGoogleMaps);

  try{
    const data = await fetchTour();
    render(data);
  }catch(e){
    console.error(e);
    titleEl.textContent = 'Tour konnte nicht geladen werden.';
    descEl.textContent  = String(e.message || e);
    if(listEl) listEl.innerHTML = `<div class="muted">Fehler: ${esc(e.message || e)}</div>`;
  }
});
</script>

<?php get_footer(); ?>
