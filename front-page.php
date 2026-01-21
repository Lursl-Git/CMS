<?php
/**
 * Template Name: Startseite (KL Radius)
 * Template Post Type: page
 */
if ( ! defined('ABSPATH') ) { exit; }
get_header();

/* =========================================================
   Config: Kaiserslautern Radius
========================================================= */
$KLD_CENTER_LAT = 49.4447;
$KLD_CENTER_LNG = 7.7690;
$KLD_RADIUS_KM  = 20.0;
$KLD_RADIUS_M   = intval($KLD_RADIUS_KM * 1000);

/* =========================================================
   Helper: Cached GET JSON
========================================================= */
function kld_cached_get_json( string $url, int $ttl = 120 ) {
  $key = 'kld_' . md5($url);
  $cached = get_transient($key);
  if ($cached !== false) return $cached;

  $resp = wp_remote_get($url, [
    'timeout' => 12,
    'headers' => ['Accept' => 'application/json'],
  ]);

  if (is_wp_error($resp)) {
    return ['error' => $resp->get_error_message()];
  }

  $code = wp_remote_retrieve_response_code($resp);
  if ($code < 200 || $code >= 300) {
    return ['error' => 'HTTP ' . $code];
  }

  $body = wp_remote_retrieve_body($resp);
  $data = json_decode($body, true);

  if (!is_array($data)) {
    return ['error' => 'Unerwartete Antwort'];
  }

  set_transient($key, $data, $ttl);
  return $data;
}

/* =========================================================
   Helper: Keine Beiträge ohne Thumbnail
========================================================= */
function kld_pick_thumb_token(array $it): string {
  foreach (['ThumbnailToken','Thumbnail2Token','Thumbnail3Token','Thumbnail4Token','Thumbnail5Token'] as $k) {
    $v = trim((string)($it[$k] ?? ''));
    if ($v !== '') return $v;
  }
  if (!empty($it['Dokumente'][0]) && is_array($it['Dokumente'][0])) {
    foreach (['ThumbnailToken','Thumbnail2Token','Thumbnail3Token'] as $k) {
      $v = trim((string)($it['Dokumente'][0][$k] ?? ''));
      if ($v !== '') return $v;
    }
  }
  return '';
}

/* =========================================================
   Helper: KuLaDig thumbnail URL
========================================================= */
function kld_thumb_url(string $token): string {
  if ($token === '') return '';
  return 'https://www.kuladig.de/api/public/Dokument?token=' . rawurlencode($token);
}

/* =========================================================
   Helper: Clean description
========================================================= */
function kld_clean_text($html): string {
  $text = wp_strip_all_tags((string)$html);
  $text = html_entity_decode($text);
  return trim($text);
}

/* =========================================================
   Helper: Extrahiert die Koordinaten
========================================================= */
function kld_extract_latlng_from_search(array $it): ?array {
  $pk = $it['Punktkoordinate'] ?? ($it['punktkoordinate'] ?? null);
  if (is_array($pk)) {
    if (isset($pk['lat'], $pk['lng'])) {
      $lat = floatval($pk['lat']); $lng = floatval($pk['lng']);
      if (is_finite($lat) && is_finite($lng)) return ['lat'=>$lat,'lng'=>$lng];
    }
    if (isset($pk['Lat'], $pk['Lng'])) {
      $lat = floatval($pk['Lat']); $lng = floatval($pk['Lng']);
      if (is_finite($lat) && is_finite($lng)) return ['lat'=>$lat,'lng'=>$lng];
    }
    if (isset($pk['coordinates']) && is_array($pk['coordinates']) && count($pk['coordinates']) >= 2) {
      $lng = floatval($pk['coordinates'][0]); $lat = floatval($pk['coordinates'][1]);
      if (is_finite($lat) && is_finite($lng)) return ['lat'=>$lat,'lng'=>$lng];
    }
  }
  if (isset($it['pt']['lat'], $it['pt']['lng'])) {
    $lat = floatval($it['pt']['lat']); $lng = floatval($it['pt']['lng']);
    if (is_finite($lat) && is_finite($lng)) return ['lat'=>$lat,'lng'=>$lng];
  }
  return null;
}

/* =========================================================
   Frontpage: Objects POOL in radius, aber die Auswahl der 6 wird pro Page-Request neu gewürfelt
========================================================= */
function kld_frontpage_get_objects_pool_kl(float $centerLat, float $centerLng, int $radiusM, int $want = 60): array {
  $cache_key = 'kld_front_objects_pool_' . md5($centerLat.'|'.$centerLng.'|'.$radiusM.'|'.$want);
  $cached = get_transient($cache_key);
  if ($cached !== false && is_array($cached)) return $cached;

  $geo = wp_json_encode([ 'type' => 'Point', 'coordinates' => [ $centerLng, $centerLat ] ]);
  $params = [
    'ObjektTyp' => 'KuladigObjekt',
    'Geometrie' => $geo,
    'Distanz'   => $radiusM,
    'Seite'     => 0,
    'SortierModus'     => 'Aenderungsdatum',
    'Sortierrichtung'  => 'Absteigend',
  ];
  $url = 'https://www.kuladig.de/api/public/Objekt?' . http_build_query($params);
  $data = kld_cached_get_json($url, 180);

  $pool = [];
  if (!isset($data['error']) && is_array($data)) {
    $raw = $data['Ergebnis'] ?? [];
    $items = is_array($raw) ? array_values($raw) : [];

    foreach ($items as $it) {
      if (count($pool) >= $want) break;

      $id = (string)($it['Id'] ?? '');
      if ($id === '') continue;

      $clean = kld_clean_text($it['Beschreibung'] ?? '');
      if ($clean === '') continue;

      $token = kld_pick_thumb_token((array)$it);
      if ($token === '') continue;

      $pt = kld_extract_latlng_from_search((array)$it);
      if (!$pt) continue;

      $pool[] = [
        'id'   => $id,
        'name' => (string)($it['Name'] ?? 'Objekt'),
        'desc' => $clean,
        'img'  => kld_thumb_url($token),
        'lat'  => $pt['lat'],
        'lng'  => $pt['lng'],
      ];
    }
  }

  set_transient($cache_key, $pool, 20 * MINUTE_IN_SECONDS);
  return $pool;
}

function kld_frontpage_pick_random(array $pool, int $n): array {
  if (count($pool) <= $n) return $pool;
  shuffle($pool); // pro Request neu -> "bei jedem Aktualisieren neu gewürfelt"
  return array_slice($pool, 0, $n);
}

/* =========================================================
   Frontpage: Tours in radius
========================================================= */
function kld_frontpage_get_tours_kl(float $centerLat, float $centerLng, float $radiusKm): array {
  $cache_key = 'kld_front_tours_' . md5($centerLat.'|'.$centerLng.'|'.$radiusKm);
  $cached = get_transient($cache_key);
  if ($cached !== false && is_array($cached)) return $cached;

  if (!function_exists('kld_route_preview_payload')) {
    set_transient($cache_key, [], 5 * MINUTE_IN_SECONDS);
    return [];
  }

  $tours = [];
  $page = 0;
  $pages = 1;
  $SCAN_PAGES_MAX = 6;
  $SCAN_ITEMS_MAX = 60;
  $checked = 0;

  while (count($tours) < 6 && $page < $pages && $page < $SCAN_PAGES_MAX && $checked < $SCAN_ITEMS_MAX) {
    $params = [ 'ObjektTyp' => 'Objektgruppe', 'Seite' => $page ];
    $url = 'https://www.kuladig.de/api/public/Objekt?' . http_build_query($params);
    $data = kld_cached_get_json($url, 180);

    if (is_array($data) && isset($data['error'])) break;

    $pages = max(1, intval($data['AnzahlSeiten'] ?? 1));
    $raw = $data['Ergebnis'] ?? [];
    $items = is_array($raw) ? array_values($raw) : [];

    foreach ($items as $it) {
      if (count($tours) >= 6) break;
      if ($checked >= $SCAN_ITEMS_MAX) break;

      $checked++;
      $id = (string)($it['Id'] ?? '');
      if ($id === '') continue;

      $preview = kld_route_preview_payload($id, 30, $centerLat, $centerLng, $radiusKm);
      if (!is_array($preview)) continue;

      $stops = $preview['stops'] ?? [];
      if (!is_array($stops) || count($stops) < 2) continue;
      if (empty($preview['in_radius'])) continue;

      $desc = isset($it['Beschreibung']) ? kld_clean_text($it['Beschreibung']) : (string)($preview['group']['desc'] ?? '');
      if ($desc === '') continue;

      $token = kld_pick_thumb_token((array)$it);
      $img = $token ? kld_thumb_url($token) : '';

      $tours[] = [
        'id'       => $id,
        'name'     => (string)($it['Name'] ?? ($preview['group']['name'] ?? 'Tour')),
        'desc'     => $desc,
        'img'      => $img,
        'stops'    => $stops,
        'total_km' => floatval($preview['total_km'] ?? 0),
      ];
    }

    $page++;
  }

  set_transient($cache_key, $tours, 20 * MINUTE_IN_SECONDS);
  return $tours;
}

/* =========================================================
   Fetch for UI
========================================================= */
$objekte_pool  = kld_frontpage_get_objects_pool_kl($KLD_CENTER_LAT, $KLD_CENTER_LNG, $KLD_RADIUS_M, 60);
$objekte_items = kld_frontpage_pick_random($objekte_pool, 6);
$routen_items  = kld_frontpage_get_tours_kl($KLD_CENTER_LAT, $KLD_CENTER_LNG, $KLD_RADIUS_KM);

$tour_detail_base = home_url('/tour/');
$tour_list_url    = home_url('/touren/');

?>

<section class="hero" aria-label="Einstieg">
  <div class="container hero-inner">
    <h1 class="headline">Entdecke Kulturlandschaften & Orte rund um Kaiserslautern</h1>
    <p class="sub">
      Live aus KuLaDig: <strong>Objekte & Touren im <?php echo esc_html($KLD_RADIUS_KM); ?> km Radius</strong>.
      <br>
    </p>

    <div class="search-wrap">
      <div class="search suggest" role="search" aria-label="Suche nach Orten oder Objekten">
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24">
          <path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79L20 21.5L21.5 20zM9.5 14A4.5 4.5 0 1 1 14 9.5A4.5 4.5 0 0 1 9.5 14"/>
        </svg>
        <input id="q" type="search" placeholder="Suche nach Ort, Objekt, Epoche …" autocomplete="off" />
      </div>

       <button class="cta-mini" type="button" id="hero-search-btn">Objekt suchen</button>
     
    </div>

    <div class="quick" aria-label="Schnellfilter">
      <a class="chip" href="<?php echo esc_url(home_url('/objekte/?suchText=Fabrik')); ?>">#Fabrik</a>
      <a class="chip" href="<?php echo esc_url(home_url('/objekte/?suchText=Ruine')); ?>">#Ruine</a>
      <a class="chip" href="<?php echo esc_url(home_url('/objekte/?suchText=Kirche')); ?>">#Kirche</a>
      <a class="chip" href="<?php echo esc_url(home_url('/objekte/?suchText=Wald')); ?>">#Wald</a>
    </div>

    <p id="geo-hint" style="margin:14px 0 0;color:#9aa3b2;font-size:.95rem;"></p>
  </div>
  <div class="scroll-cue">↓ Weiter scrollen</div>
</section>

<?php if (!empty($objekte_items)): ?>
<section class="preview-section" id="objekte">
  <div class="container">
    <div class="preview-header">
      <h2>Objekte in deiner Region</h2>
      <a href="<?php echo esc_url(home_url('/objekte/')); ?>" class="view-all-link">Alle Objekte ansehen →</a>
    </div>

    <div class="preview-grid" id="objects-grid">
      <?php foreach ($objekte_items as $it):
        $detail_url = esc_url( home_url('/objekt/') . '?id=' . rawurlencode($it['id']) );
      ?>
        <article class="preview-card object-card"
                 data-lat="<?php echo esc_attr($it['lat']); ?>"
                 data-lng="<?php echo esc_attr($it['lng']); ?>"
                 data-id="<?php echo esc_attr($it['id']); ?>">
          <?php if (!empty($it['img'])): ?>
            <a href="<?php echo $detail_url; ?>" aria-label="<?php echo esc_attr($it['name']); ?>">
              <img src="<?php echo esc_url($it['img']); ?>"
                   alt="<?php echo esc_attr($it['name']); ?>"
                   loading="lazy">
            </a>
          <?php endif; ?>

          <div class="preview-card-content">
            <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
              <h3 style="margin:0;"><a href="<?php echo $detail_url; ?>"><?php echo esc_html($it['name']); ?></a></h3>
              <span class="badge" data-dist style="white-space:nowrap;">📍 — km</span>
            </div>
            <p><?php echo esc_html(mb_strimwidth($it['desc'], 0, 140, ' …')); ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($routen_items)): ?>
<section class="preview-section" style="background: rgba(0,0,0,0.1);" id="touren">
  <div class="container">
    <div class="preview-header">
      <h2>Touren in deiner Region</h2>
      <a href="<?php echo esc_url($tour_list_url); ?>" class="view-all-link">Alle Touren ansehen →</a>
    </div>

    <div class="tour-grid" id="front-tours">
      <?php foreach ($routen_items as $idx => $t):
        $tour_url = esc_url( add_query_arg('id', rawurlencode($t['id']), $tour_detail_base) );
        $mapId = 'front_tour_map_' . intval($idx);
      ?>
        <article class="tour-card" data-map-id="<?php echo esc_attr($mapId); ?>">
          <div class="tour-top">
            <div class="tour-hero">
              <?php if (!empty($t['img'])): ?>
                <a href="<?php echo $tour_url; ?>" aria-label="<?php echo esc_attr($t['name']); ?>">
                  <img src="<?php echo esc_url($t['img']); ?>" alt="" loading="lazy">
                </a>
              <?php else: ?>
                <div class="fallback">🧭</div>
              <?php endif; ?>
            </div>
            <div class="tour-map" id="<?php echo esc_attr($mapId); ?>"></div>
          </div>

          <div class="tour-body">
            <h3 class="tour-title"><a href="<?php echo $tour_url; ?>"><?php echo esc_html($t['name']); ?></a></h3>
            <p class="tour-desc"><?php echo esc_html( mb_strimwidth( $t['desc'], 0, 160, ' …' ) ); ?></p>

            <div class="tour-meta">
              <span class="badge">🧷 <span class="v"><?php echo esc_html( count($t['stops']) ); ?></span><small>Stopps</small></span>
              <span class="badge">📏 <span class="v"><?php echo $t['total_km'] ? esc_html( number_format($t['total_km'], 1, ',', '.') ) : '—'; ?></span><small>km</small></span>
            </div>
          </div>

          <div class="tour-actions">
            <a class="btn btn-primary" href="<?php echo $tour_url; ?>">Tour öffnen</a>
          </div>

          <script type="application/json" data-stops-json="<?php echo esc_attr($mapId); ?>">
            <?php echo wp_json_encode($t['stops']); ?>
          </script>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="preview-section" id="karte">
  <div class="container">
    <div class="preview-header">
      <h2>Interaktive Karte</h2>
      <a href="<?php echo esc_url(home_url('/karte/')); ?>" class="view-all-link">Zur Vollbildkarte →</a>
    </div>
    <p style="margin-bottom: 24px;">Vorschau: <?php echo esc_html($KLD_RADIUS_KM); ?> km Radius um Kaiserslautern (Marker = Startseiten-Objekte).</p>

    <div style="height: 420px; border-radius: 14px; overflow: hidden; border: 1px solid #1d2530;">
      <div id="preview-map" style="height: 100%; width: 100%;"></div>
    </div>

    <script type="application/json" id="front-obj-points">
      <?php echo wp_json_encode($objekte_items); ?>
    </script>
  </div>
</section>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
.tour-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:20px;align-items:start;}
.tour-card{border:1px solid #1d2530;border-radius:16px;background:rgba(255,255,255,.03);overflow:hidden;box-shadow:0 10px 28px rgba(0,0,0,.25);}
.tour-top{display:grid;grid-template-columns:1fr 220px;gap:0;border-bottom:1px solid #1d2530;min-height:200px;}
@media (max-width:560px){.tour-top{grid-template-columns:1fr;}}
.tour-hero{position:relative;background:rgba(255,255,255,.05);}
.tour-hero img{display:block;width:100%;height:100%;object-fit:cover;}
.tour-hero .fallback{height:100%;display:flex;align-items:center;justify-content:center;color:#9aa3b2;font-size:2rem;}
.tour-map{height:200px;background:rgba(255,255,255,.04);}
@media (max-width:560px){.tour-map{height:160px;}}
.tour-body{padding:14px 14px 10px;}
.tour-title{margin:0 0 6px;font-size:1.05rem;line-height:1.25;}
.tour-title a{color:#f5f7fb;text-decoration:none;}
.tour-desc{margin:0;color:#c9d1e1;line-height:1.45;min-height:3.0em;}
.tour-meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:12px 0 0;}
.badge{display:inline-flex;align-items:center;gap:6px;font-size:.85rem;line-height:1;padding:6px 10px;border-radius:999px;border:1px solid #233043;background:rgba(255,255,255,0.04);color:#c9d1e1;}
.badge small{opacity:.85}
.tour-actions{display:flex;gap:10px;flex-wrap:wrap;padding:12px 14px 14px;border-top:1px solid #1d2530;background:rgba(255,255,255,0.02);}
</style>

<script>
    
    document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('q');
  const btn   = document.getElementById('hero-search-btn');
  if (!input || !btn) return;

  const base = <?php echo json_encode( home_url('/objekte/') ); ?>;

  const go = () => {
    const q = (input.value || '').trim();
    window.location.href = q ? (base + '?suchText=' + encodeURIComponent(q)) : base;
  };

  btn.addEventListener('click', go);
});
    
window.addEventListener('load', function(){
  // ---------- helpers ----------
  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
    });
  }

  function haversineKm(lat1, lon1, lat2, lon2){
    const R = 6371;
    const dLat = (lat2-lat1) * Math.PI/180;
    const dLon = (lon2-lon1) * Math.PI/180;
    const a =
      Math.sin(dLat/2)*Math.sin(dLat/2) +
      Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) *
      Math.sin(dLon/2)*Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R*c;
  }

  // ---------- search redirect ----------
  document.getElementById('q')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      const query = this.value.trim();
      if (query) {
        window.location.href = '<?php echo esc_url(home_url('/objekte/')); ?>?suchText=' + encodeURIComponent(query);
      }
    }
  });

  // ---------- leaflet maps ----------
  if (!window.L) return;

  const CENTER = [<?php echo json_encode($KLD_CENTER_LAT); ?>, <?php echo json_encode($KLD_CENTER_LNG); ?>];
  const RADIUS_M = <?php echo json_encode($KLD_RADIUS_M); ?>;

  const el = document.getElementById('preview-map');
  if (el) {
    const previewMap = L.map('preview-map', { zoomControl: true }).setView(CENTER, 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap',
      maxZoom: 19
    }).addTo(previewMap);

    L.circle(CENTER, { radius: RADIUS_M, weight: 2, fillOpacity: 0.08 }).addTo(previewMap);

    let points = [];
    try { points = JSON.parse(document.getElementById('front-obj-points')?.textContent || '[]'); } catch(e){ points = []; }

    points.forEach(p => {
      const lat = Number(p.lat), lng = Number(p.lng);
      if(!Number.isFinite(lat) || !Number.isFinite(lng)) return;
      const url = <?php echo json_encode( home_url('/objekt/') ); ?> + '?id=' + encodeURIComponent(p.id);
      L.marker([lat, lng]).addTo(previewMap)
        .bindPopup(`<b>${escapeHtml(p.name||'Objekt')}</b><br><a href="${url}">Zur Objektansicht →</a>`);
    });
  }

  document.querySelectorAll('.tour-card').forEach(card => {
    const mapId = card.getAttribute('data-map-id');
    if(!mapId) return;
    const mapEl = document.getElementById(mapId);
    if(!mapEl) return;

    const jsonEl = card.querySelector(`script[data-stops-json="${mapId}"]`);
    let stops = [];
    try { stops = JSON.parse(jsonEl?.textContent || '[]'); } catch(e){ stops = []; }

    const latlngs = (stops||[])
      .map(s => [Number(s.lat), Number(s.lng)])
      .filter(p => Number.isFinite(p[0]) && Number.isFinite(p[1]));

    if(latlngs.length < 2){
      mapEl.innerHTML = `<div style="height:100%;display:flex;align-items:center;justify-content:center;color:#9aa3b2;font-size:.95rem;">Keine Route.</div>`;
      return;
    }

    const m = L.map(mapEl, { zoomControl:false, attributionControl:false, dragging:false, scrollWheelZoom:false, doubleClickZoom:false, boxZoom:false, keyboard:false, tap:false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(m);

    latlngs.forEach((p, idx) => L.circleMarker(p, { radius: 5 }).addTo(m).bindTooltip(String(idx+1), {direction:'top'}));
    const line = L.polyline(latlngs, { weight: 4 }).addTo(m);
    m.fitBounds(line.getBounds(), { padding:[10,10] });
  });

  // ---------- distance tags ----------
  const geoBtn = document.getElementById('geo-sort');
  const hintEl = document.getElementById('geo-hint');
  const gridEl = document.getElementById('objects-grid');

  function applyDistances(uLat, uLng, doSort){
    if(!gridEl) return;
    const cards = Array.from(gridEl.querySelectorAll('.object-card'));

    cards.forEach(c => {
      const lat = Number(c.dataset.lat);
      const lng = Number(c.dataset.lng);
      if(!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      const d = haversineKm(uLat, uLng, lat, lng);
      c.dataset.dist = String(d);
      const b = c.querySelector('[data-dist]');
      if(b) b.textContent = `📍 ${d.toFixed(1)} km`;
    });

    if(doSort){
      cards.sort((a,b) => (Number(a.dataset.dist)||999999) - (Number(b.dataset.dist)||999999));
      cards.forEach(c => gridEl.appendChild(c));
    }
  }

  function requestGeo(doSort){
    if(!navigator.geolocation){
      if(hintEl) hintEl.textContent = 'Geolocation wird von deinem Browser nicht unterstützt.';
      return;
    }
    if(hintEl) hintEl.textContent = 'Standort wird abgefragt…';
    navigator.geolocation.getCurrentPosition(pos => {
      const uLat = pos.coords.latitude;
      const uLng = pos.coords.longitude;
      applyDistances(uLat, uLng, doSort);
      if(hintEl) hintEl.textContent = doSort
        ? 'Sortiert nach deiner Entfernung (nächste zuerst).'
        : '';
    }, () => {
      if(hintEl) hintEl.textContent = 'Standort nicht freigegeben – Entfernung & Sortierung deaktiviert.';
    }, { enableHighAccuracy:true, timeout:7000, maximumAge:60000 });
  }

  // Wenn Permission schon "granted" ist, sofort Distanz anzeigen
  try{
    if(navigator.permissions?.query){
      navigator.permissions.query({name:'geolocation'}).then(p => {
        if(p.state === 'granted'){
          requestGeo(false);
        } else if(p.state === 'denied'){
          if(hintEl) hintEl.textContent = 'Standort blockiert – Entfernung & Sortierung deaktiviert.';
        } else {
          if(hintEl) hintEl.textContent = 'Tipp: Standort freigeben, um Entfernungen zu sehen.';
        }
      }).catch(()=>{ if(hintEl) hintEl.textContent = 'Tipp: Standort freigeben, um Entfernungen zu sehen.'; });
    } else {
      if(hintEl) hintEl.textContent = 'Tipp: Standort freigeben, um Entfernungen zu sehen.';
    }
  }catch(e){
    if(hintEl) hintEl.textContent = 'Tipp: Standort freigeben, um Entfernungen zu sehen.';
  }

  if(geoBtn){
    geoBtn.addEventListener('click', () => requestGeo(true));
  }
});
</script>

<?php get_footer(); ?>
