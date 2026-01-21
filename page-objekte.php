<?php
/**
 * Template Name: Objekte (KuLaDig) – Radius KL
 * Template Post Type: page
 */
if ( ! defined('ABSPATH') ) { exit; }
get_header();


/* ---------- Helpers ---------- */
function kld_cached_get_json( string $url, int $ttl = 60 ){
  $key = 'kld_' . md5( $url );
  $cached = get_transient( $key );
  if ( $cached !== false ) return $cached;

  $resp = wp_remote_get( $url, [
    'timeout' => 12,
    'headers' => [ 'Accept' => 'application/json' ],
  ] );

  if ( is_wp_error( $resp ) ) return [ 'error' => $resp->get_error_message() ];

  $code = wp_remote_retrieve_response_code( $resp );
  if ( $code < 200 || $code >= 300 ) return [ 'error' => 'HTTP '.$code ];

  $body = wp_remote_retrieve_body( $resp );
  $data = json_decode( $body, true );
  if ( ! is_array( $data ) ) return [ 'error' => 'Unerwartete Antwort' ];

  set_transient( $key, $data, $ttl );
  return $data;
}

function kld_thumb_url( $token ){
  if ( empty( $token ) ) return '';
  return 'https://www.kuladig.de/api/public/Dokument?token=' . rawurlencode( $token );
}

/** Bounding Box für Umkreis*/
function kld_bbox_from_radius_km( float $lat, float $lng, float $radius_km ): array {
  $earth = 6371.0;
  $dLat = rad2deg( $radius_km / $earth );
  $dLng = rad2deg( ($radius_km / $earth) / cos(deg2rad($lat)) );

  return [
    'sw_lat' => $lat - $dLat,
    'sw_lng' => $lng - $dLng,
    'ne_lat' => $lat + $dLat,
    'ne_lng' => $lng + $dLng,
  ];
}

/* ---------- Inputs ---------- */
$suchText   = isset($_GET['suchText'])  ? sanitize_text_field($_GET['suchText'])   : '';
$objektTyp  = isset($_GET['ObjektTyp']) ? sanitize_text_field($_GET['ObjektTyp'])  : '';
$seite      = isset($_GET['Seite'])     ? max(0, intval($_GET['Seite']))           : 0;

$kategorie  = isset($_GET['kategorie']) ? sanitize_text_field($_GET['kategorie'])  : '';

$allowedObjektTyp = ['KuladigObjekt','Objektgruppe',''];
if ( ! in_array($objektTyp, $allowedObjektTyp, true) ) { $objektTyp = ''; }

/* ---------- Radius um KL ---------- */
$CENTER_LAT = 49.4447;
$CENTER_LNG = 7.7690;
$RADIUS_KM  = 20.0;

$bbox = kld_bbox_from_radius_km($CENTER_LAT, $CENTER_LNG, $RADIUS_KM);

/* ---------- Proxy URL (wie Karte) ---------- */
$ajax  = admin_url('admin-ajax.php');
$proxy_url = add_query_arg([
  'action' => 'kld_map',
  'sw_lng' => $bbox['sw_lng'],
  'sw_lat' => $bbox['sw_lat'],
  'ne_lng' => $bbox['ne_lng'],
  'ne_lat' => $bbox['ne_lat'],
  'page'   => $seite,
], $ajax);

/* ---------- Fetch ---------- */
$data = kld_cached_get_json( $proxy_url, 60 );
$err  = is_array($data) && isset($data['error']) ? $data['error'] : '';

$items     = [];
$anzSeiten = 1;

if ( !$err && is_array($data) ) {
  if ( empty($data['success']) ) {
    $err = $data['data']['message'] ?? 'Proxy-Fehler';
  } else {
    $payload  = $data['data'] ?? [];
    $raw      = $payload['items'] ?? [];
    $items    = is_array($raw) ? array_values($raw) : [];
    $anzSeiten = max(1, intval($payload['anzahlSeiten'] ?? 1));
  }
}

/* ---------- Local filtering ---------- */
$needle = mb_strtolower($suchText);
$seen   = [];
$filtered = [];

foreach ($items as $it) {
  $id = isset($it['id']) ? (string)$it['id'] : '';
  if ($id !== '' && isset($seen[$id])) continue;
  if ($id !== '') $seen[$id] = true;

  $name = (string)($it['name'] ?? '');
  $desc = (string)($it['desc'] ?? '');

  if ($needle !== '') {
    $hay = mb_strtolower($name . ' ' . $desc);
    if (mb_strpos($hay, $needle) === false) continue;
  }

  if ($objektTyp !== '') {
    $type = (string)($it['objektTyp'] ?? ($it['ObjektTyp'] ?? ($it['typ'] ?? ($it['type'] ?? ''))));
    if ($type !== '' && $type !== $objektTyp) continue;
  }

  $filtered[] = $it;
}
$items = $filtered;

?>
<style>
  :root { --objekte-gap: 100px; }
  #main { padding-top: var(--objekte-gap); }
  :target { scroll-margin-top: var(--objekte-gap); }

  .objekte-grid{
    display:grid;
    width:100%;
    grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));
    gap:20px;
    align-items:start;
  }
  .objekte-grid .card{
    border:1px solid #1d2530;
    border-radius:14px;
    background:rgba(255,255,255,0.03);
    overflow:hidden;
    box-shadow:0 8px 26px rgba(0,0,0,.25);
  }
  .objekte-grid .card img{
    display:block;width:100%;height:200px;object-fit:cover;
    background:rgba(255,255,255,0.04);
  }
  .thumb-fallback{
    height:200px;display:flex;align-items:center;justify-content:center;
    color:#9aa3b2;background:rgba(255,255,255,0.04);
    border-bottom:1px solid #1d2530;
    font-size:2rem;
  }

  .meta-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:6px 0 10px;}
  .badge{
    display:inline-flex;align-items:center;gap:6px;
    font-size:.85rem;line-height:1;
    padding:6px 10px;border-radius:999px;
    border:1px solid #233043;
    background:rgba(255,255,255,0.04);
    color:#c9d1e1;
    max-width:100%;
  }
  .badge span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px}
  .badge small{opacity:.85}
  .distance{color:#9aa3b2;font-size:.9rem;}

  .search{flex:1 1 420px;display:flex;align-items:center;gap:10px;
    min-height:32px;padding:12px 16px;border-radius:14px;
    background:rgba(255,255,255,0.06);border:1px solid #1d2530;outline:2px solid transparent;}
  .search:focus-within{outline-color:rgba(76,201,240,0.45);box-shadow:0 0 0 3px rgba(72,149,239,.2);}
  .search input{width:100%;height:24px;padding:0 14px;font-size:1.075rem;border:0;background:transparent;color:#f5f7fb;outline:none;box-shadow:none;}
  .search input::placeholder{color:#9aa3b2;}

  .hint{margin:0 0 16px;color:#9aa3b2;font-size:.95rem;}
</style>

<section class="container" style="padding:75px 0 48px;">
  <h1 style="margin:0 0 12px;">Objekte </h1>
  <p style="margin:0 0 18px;color:#c9d1e1;">
    Hier werden Objekte 20 km um Kaiserslautern angezeigt.
  </p>

  <form method="get" action="<?php echo esc_url( get_permalink() ); ?>" class="search-wrap" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <label class="search">
      <input type="text" name="suchText" placeholder="Suche nach Ort, Objekt …" value="<?php echo esc_attr($suchText); ?>">
    </label>

    <select id="kld-cat" name="kategorie" class="select" style="padding:12px;border-radius:12px;border:1px solid #233043;background:rgba(9,13,18,.6);color:#f5f7fb;min-width:220px;">
      <option value="">Alle Kategorien</option>

      <?php if ($kategorie !== ''): ?>
        <option value="<?php echo esc_attr($kategorie); ?>" selected>Aktiv: <?php echo esc_html($kategorie); ?> (lädt…)</option>
      <?php endif; ?>

      <option value="" disabled>Kategorien laden…</option>
    </select>

    <button class="btn btn-primary" type="submit">Suchen</button>
  </form>

  <?php if ( $err ): ?>
    <div class="notice notice-error">
      Proxy/API-Fehler: <?php echo esc_html($err); ?><br>
      <small style="opacity:.8;word-break:break-all;"><?php echo esc_html($proxy_url); ?></small>
    </div>
  <?php endif; ?>

  <?php if ( !empty($items) ): ?>
    <div id="geo-hint" class="hint"></div>

    <div class="objekte-grid" id="objekte-grid">
      <?php foreach ( $items as $it ):
        $id   = isset($it['id']) ? (string)$it['id'] : '';
        $name = (string)($it['name'] ?? '');
        $desc = (string)($it['desc'] ?? '');

        $token = (string)($it['token'] ?? '');
        $img   = $token ? kld_thumb_url($token) : '';

        $lat = isset($it['lat']) ? (float)$it['lat'] : (isset($it['pt']['lat']) ? (float)$it['pt']['lat'] : null);
        $lng = isset($it['lng']) ? (float)$it['lng'] : (isset($it['pt']['lng']) ? (float)$it['pt']['lng'] : null);

        $detail_url = $id ? esc_url( home_url('/objekt/') . '?id=' . rawurlencode($id) ) : '#';
      ?>
        <article class="card"
          data-id="<?php echo esc_attr($id); ?>"
          data-lat="<?php echo esc_attr(is_null($lat) ? '' : $lat); ?>"
          data-lng="<?php echo esc_attr(is_null($lng) ? '' : $lng); ?>"
          data-cat=""
          data-cat-tokens=""
        >
          <a href="<?php echo $detail_url; ?>" aria-label="<?php echo esc_attr($name); ?>">
            <?php if ( $img ): ?>
              <img src="<?php echo esc_url($img); ?>" alt="">
            <?php else: ?>
              <div class="thumb-fallback">📍</div>
            <?php endif; ?>
          </a>

          <div style="padding:14px;">
            <h3 style="margin:0 0 6px;font-size:1.05rem;line-height:1.25;">
              <a href="<?php echo $detail_url; ?>" style="color:#f5f7fb;text-decoration:none;"><?php echo esc_html($name); ?></a>
            </h3>

            <div class="meta-row">
              <span class="badge cat-badge" style="display:none;" title="Kategorie">🏷️ <span class="cat-text"></span></span>
              <span class="badge distance" data-distance="">📍 <span class="distance-text">— km</span><small>(von dir)</small></span>
            </div>

            <p style="margin:0;color:#c9d1e1;line-height:1.45;">
              <?php echo esc_html( mb_strimwidth( wp_strip_all_tags($desc), 0, 180, ' …' ) ); ?>
            </p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="pagination" style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <?php
        $base_link = remove_query_arg('Seite', get_permalink());
        if ( $seite > 0 ) {
          $prev_url = add_query_arg( array_merge($_GET,['Seite'=>$seite-1]), $base_link );
          echo '<a class="btn-secondary" href="'.esc_url($prev_url).'">« Zurück</a>';
        }
        if ( ($seite + 1) < $anzSeiten ) {
          $next_url = add_query_arg( array_merge($_GET,['Seite'=>$seite+1]), $base_link );
          echo '<a class="btn-secondary" href="'.esc_url($next_url).'">Weiter »</a>';
        }
      ?>
      <span style="color:#9aa3b2;">Seite <?php echo esc_html( $seite + 1 ); ?> / <?php echo esc_html( $anzSeiten ); ?></span>
    </div>

  <?php else: ?>
    <p style="margin-top:16px;">Keine Treffer im Umkreis – verändere Suche oder Filter.</p>
  <?php endif; ?>
</section>

<script>
(function(){
  const ajaxURL = <?php echo json_encode( admin_url('admin-ajax.php') ); ?>;
  const grid = document.getElementById('objekte-grid');
  const hint = document.getElementById('geo-hint');
  const catSelect = document.getElementById('kld-cat');
  if(!grid) return;

  const cards = Array.from(grid.querySelectorAll('.card'));

  const urlParams = new URLSearchParams(window.location.search);
  const urlCategoryRaw = (urlParams.get('kategorie') || '').toString();

  function norm(s){
    return String(s||'')
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/\s+/g,' ')
      .trim()
      .toLowerCase();
  }

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  function splitCats(raw){
    return String(raw||'')
      .split(/[;,|/]+/g)
      .map(x => x.trim())
      .filter(Boolean);
  }

  function setHidden(card, hidden){
    card.classList.toggle('kld-hidden', !!hidden);
  }

  // -------------------- Kategorie-Filter --------------------
  function applyCategoryFilter(value){
    const v = norm(value);
    let shown = 0;

    for(const card of cards){
      const tokens = (card.dataset.catTokens || '').split('||').filter(Boolean);
      const catRaw = card.dataset.cat || '';

      const ok =
        !v ||
        tokens.includes(v) ||
        (catRaw && norm(catRaw).includes(v)); 

      setHidden(card, !ok);
      if(ok) shown++;
    }

    if(hint){
      if(v) hint.textContent = `Kategorie-Filter aktiv: ${value} (${shown} Treffer sichtbar).`;
      else hint.textContent = 'Sortiert nach Entfernung (nächste zuerst).';
    }
  }

  if(catSelect){
    catSelect.addEventListener('change', () => applyCategoryFilter(catSelect.value));
  }

  if(norm(urlCategoryRaw)){
    cards.forEach(c => setHidden(c, true));
    if(hint) hint.textContent = 'Kategorie-Filter aktiv – Kategorien werden geladen…';
  }

  // -------------------- Kategorien --------------------
  async function loadCategories(){
    const ids = cards.map(c => c.dataset.id).filter(Boolean);
    if(!ids.length) return;

    if(catSelect){
      catSelect.innerHTML =
        '<option value="">Alle Kategorien</option>' +
        '<option value="" disabled selected>Kategorien laden…</option>';
    }

    try{
      const u = new URL(ajaxURL);
      u.searchParams.set('action','kld_obj_meta');
      u.searchParams.set('ids', ids.join(','));

      const res = await fetch(u.toString(), { headers:{'Accept':'application/json'}, credentials:'same-origin' });
      const json = await res.json();
      if(!json || !json.success) throw new Error(json?.data?.message || 'Meta-Request fehlgeschlagen');

      const byId = json.data?.byId || {};
      const catsRaw = (json.data?.categories || []).filter(Boolean);

      // Dropdown
      const catMap = new Map(); // norm -> display
      for(const c of catsRaw){
        const display = String(c).trim();
        if(!display) continue;
        const k = norm(display);
        if(!k) continue;
        if(!catMap.has(k)) catMap.set(k, display);
      }
      const catList = Array.from(catMap.values()).sort((a,b)=>a.localeCompare(b,'de',{sensitivity:'base'}));

      // Cards updaten
      for(const card of cards){
        const id = card.dataset.id;
        const raw = (byId[id]?.category || '').toString().trim();
        if(!raw) continue;

        const parts = splitCats(raw);
        const tokens = parts.map(p => norm(p)).filter(Boolean);

        // Anzeige: erster Part
        card.dataset.cat = parts[0] || raw;
        card.dataset.catTokens = tokens.join('||');

        const badge = card.querySelector('.cat-badge');
        const txt = card.querySelector('.cat-text');
        if(badge && txt){
          txt.textContent = parts[0] || raw;
          badge.style.display = '';
        }
      }

      // Dropdown füllen
      if(catSelect){
        catSelect.innerHTML =
          '<option value="">Alle Kategorien</option>' +
          catList.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');

        if(urlCategoryRaw){
          catSelect.value = urlCategoryRaw;

          if(catSelect.value !== urlCategoryRaw){
            const wanted = norm(urlCategoryRaw);
            for(const opt of Array.from(catSelect.options)){
              if(norm(opt.value) === wanted){
                catSelect.value = opt.value;
                break;
              }
            }
          }
        }

        // JETZT anwenden (das war der entscheidende Schritt)
        applyCategoryFilter(catSelect.value);
      } else {
        if(urlCategoryRaw) applyCategoryFilter(urlCategoryRaw);
        else cards.forEach(c => setHidden(c, false));
      }

    }catch(e){
      console.warn('Kategorien konnten nicht geladen werden:', e);
      // Fallback: alles zeigen
      cards.forEach(c => setHidden(c, false));
      if(hint) hint.textContent = 'Kategorien konnten nicht geladen werden – Filter deaktiviert.';
      if(catSelect) catSelect.innerHTML = '<option value="">Alle Kategorien</option>';
    }
  }

  // -------------------- Distanz + Sortierung --------------------
  function haversineKm(lat1, lon1, lat2, lon2){
    const R = 6371;
    const toRad = d => d * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a =
      Math.sin(dLat/2) * Math.sin(dLat/2) +
      Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
      Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
  }

  function applyDistancesAndSort(userLat, userLng){
    for(const card of cards){
      const lat = parseFloat(card.dataset.lat);
      const lng = parseFloat(card.dataset.lng);
      if(!Number.isFinite(lat) || !Number.isFinite(lng)){
        card.dataset.distance = '';
        continue;
      }
      const km = haversineKm(userLat, userLng, lat, lng);
      card.dataset.distance = String(km);
      const el = card.querySelector('.distance-text');
      if(el) el.textContent = `${km.toFixed(1).replace('.', ',')} km`;
    }

    const sorted = cards.slice().sort((a,b) => {
      const da = parseFloat(a.dataset.distance);
      const db = parseFloat(b.dataset.distance);
      const aOk = Number.isFinite(da);
      const bOk = Number.isFinite(db);
      if(aOk && bOk) return da - db;
      if(aOk && !bOk) return -1;
      if(!aOk && bOk) return 1;
      return 0;
    });

    for(const el of sorted) grid.appendChild(el);
  }

  function explainGeoBlock(){
    if(!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1'){
      if(hint && !norm(urlCategoryRaw)) hint.textContent = 'Standort blockiert (HTTP) – nutze HTTPS oder localhost.';
      return true;
    }
    return false;
  }

  function initGeo(){
    if(!navigator.geolocation){
      if(hint && !norm(urlCategoryRaw)) hint.textContent = 'Browser ohne Standortbestimmung – Entfernung kann nicht berechnet werden.';
      return;
    }
    if(explainGeoBlock()) return;

    if(hint && !norm(urlCategoryRaw)) hint.textContent = 'Standort wird abgefragt… (für Entfernung & Sortierung)';
    navigator.geolocation.getCurrentPosition(
      pos => applyDistancesAndSort(pos.coords.latitude, pos.coords.longitude),
      () => { if(hint && !norm(urlCategoryRaw)) hint.textContent = 'Standort nicht freigegeben – Entfernung & Sortierung deaktiviert.'; },
      { enableHighAccuracy:true, timeout:7000, maximumAge:60000 }
    );
  }

  // Start
  initGeo();
  loadCategories();
})();
</script>


<?php get_footer(); ?>
