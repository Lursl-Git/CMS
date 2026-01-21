<?php
/**
 * Template Name: Touren (KuLaDig) – Radius KL 
 * Template Post Type: page
 */
if ( ! defined('ABSPATH') ) { exit; }
get_header();


function kld_cached_get_json( string $url, int $ttl = 60 ){
  $key = 'kld_' . md5( $url );
  $cached = get_transient( $key );
  if ( $cached !== false ) return $cached;

  $resp = wp_remote_get( $url, [ 'timeout' => 12, 'headers' => [ 'Accept' => 'application/json' ] ] );
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
function kld_pick_group_token($it){
  return $it['ThumbnailToken']
    ?? ($it['Thumbnail3Token']
    ?? ($it['Dokumente'][0]['Thumbnail3Token'] ?? ''));
}

/* ---------- Radius ---------- */
$CENTER_LAT = 49.4447;
$CENTER_LNG = 7.7690;
$RADIUS_KM  = 20.0;

/* ---------- Inputs ---------- */
$suchText = isset($_GET['suchText']) ? sanitize_text_field($_GET['suchText']) : '';

/* ---------- Display settings ---------- */
$NEEDED = 6;                 
$SCAN_PAGES_MAX = 8;        
$SCAN_ITEMS_MAX = 80;       
$STOPS_LIMIT_FOR_CHECK = 60; 

$tours = [];
$err = '';

$base   = 'https://www.kuladig.de/api/public/Objekt';
$page   = 0;
$pages  = 1;
$checked = 0;

while ( count($tours) < $NEEDED && $page < $pages && $page < $SCAN_PAGES_MAX && $checked < $SCAN_ITEMS_MAX ) {
  $params = array_filter([
    'suchText'  => $suchText !== '' ? $suchText : null,
    'ObjektTyp' => 'Objektgruppe',
    'Seite'     => $page,
  ]);
  $api_url = $base . '?' . http_build_query($params);
  $data = kld_cached_get_json($api_url, 60);

  if ( is_array($data) && isset($data['error']) ) {
    $err = $data['error'];
    break;
  }

  $rawErg = $data['Ergebnis'] ?? [];
  $items  = is_array($rawErg) ? array_values($rawErg) : [];
  $pages  = max(1, intval($data['AnzahlSeiten'] ?? 1));

  foreach ($items as $it) {
    if (count($tours) >= $NEEDED) break;
    if ($checked >= $SCAN_ITEMS_MAX) break;

    $checked++;

    $id   = $it['Id']   ?? '';
    if (!$id) continue;

    // Preview/Check aus functions.php 
    $preview = null;
    if ( function_exists('kld_route_preview_payload') ) {
      $preview = kld_route_preview_payload((string)$id, $STOPS_LIMIT_FOR_CHECK, $CENTER_LAT, $CENTER_LNG, $RADIUS_KM);
    }

    if ( !is_array($preview) ) continue;

    $stops = $preview['stops'] ?? [];
    $stopsCount = is_array($stops) ? count($stops) : 0;

    // Tour braucht mind. 2 Koordinaten-Stopps
    if ($stopsCount < 2) continue;

    // Muss im Radius liegen
    if ( empty($preview['in_radius']) ) continue;

    $token = kld_pick_group_token($it);
    $img   = $token ? kld_thumb_url($token) : '';
    $name  = $it['Name'] ?? ($preview['group']['name'] ?? 'Tour');
    $desc  = isset($it['Beschreibung']) ? wp_strip_all_tags($it['Beschreibung']) : ($preview['group']['desc'] ?? '');

    $tours[] = [
      'id'      => (string)$id,
      'name'    => (string)$name,
      'desc'    => (string)$desc,
      'img'     => (string)$img,
      'stops'   => $stops,
      'total_km'=> floatval($preview['total_km'] ?? 0),
      'min_dist'=> floatval($preview['min_dist_km'] ?? 0),
    ];
  }

  $page++;
}

/* Tour Detail page */
$tour_detail_base = home_url('/routenansicht/'); 
?>

<style>
  :root { --tour-gap: 100px; }
  #main { padding-top: var(--tour-gap); }
  :target { scroll-margin-top: var(--tour-gap); }

  .search{flex:1 1 640px;display:flex;align-items:center;gap:10px;
    min-height:32px;padding:12px 16px;border-radius:14px;
    background:rgba(255,255,255,0.06);border:1px solid #1d2530;outline:2px solid transparent;}
  .search:focus-within{outline-color:rgba(76,201,240,0.45);box-shadow:0 0 0 3px rgba(72,149,239,.2);}
  .search input{width:100%;height:24px;padding:0 14px;font-size:1.075rem;border:0;background:transparent;color:#f5f7fb;outline:none;box-shadow:none;}
  .search input::placeholder{color:#9aa3b2;}

  .tour-grid{
    display:grid; width:100%;
    grid-template-columns:repeat(auto-fill, minmax(360px, 1fr));
    gap:20px; align-items:start;
  }
  .tour-card{
    border:1px solid #1d2530; border-radius:16px;
    background:rgba(255,255,255,.03); overflow:hidden;
    box-shadow:0 10px 28px rgba(0,0,0,.25);
  }

  .tour-top{
    display:grid;
    grid-template-columns: 1fr 220px;
    gap:0;
    border-bottom:1px solid #1d2530;
    min-height:200px;
  }
  @media (max-width: 560px){
    .tour-top{grid-template-columns:1fr;}
  }

  .tour-hero{
    position:relative;
    background:rgba(255,255,255,.05);
  }
  .tour-hero img{display:block;width:100%;height:100%;object-fit:cover;}
  .tour-hero .fallback{
    height:100%;display:flex;align-items:center;justify-content:center;
    color:#9aa3b2;font-size:2rem;
  }

  .tour-map{height:200px;background:rgba(255,255,255,.04);}
  @media (max-width: 560px){ .tour-map{height:160px;} }

  .tour-body{padding:14px 14px 10px;}
  .tour-title{margin:0 0 6px;font-size:1.05rem;line-height:1.25;}
  .tour-title a{color:#f5f7fb;text-decoration:none;}
  .tour-desc{margin:0;color:#c9d1e1;line-height:1.45;min-height:3.1em;}

  .tour-meta{
    display:flex;gap:8px;flex-wrap:wrap;align-items:center;
    margin:12px 0 0;
  }
  .badge{
    display:inline-flex;align-items:center;gap:6px;
    font-size:.85rem;line-height:1;
    padding:6px 10px;border-radius:999px;
    border:1px solid #233043;
    background:rgba(255,255,255,0.04);
    color:#c9d1e1;
  }
  .badge small{opacity:.85}

  .tour-actions{
    display:flex;gap:10px;flex-wrap:wrap;
    padding:12px 14px 14px;
    border-top:1px solid #1d2530;
    background:rgba(255,255,255,0.02);
  }

  .hint{color:#9aa3b2;font-size:.95rem;margin:0 0 14px;}
</style>

<section class="container" style="padding:75px 0 48px;">
  <h1 style="margin:0 0 12px;">Touren </h1>
  <p class="hint">Es werden nur Touren im Umkreis von 20 km angezeigt.</p>

  <form method="get" action="<?php echo esc_url( get_permalink() ); ?>" class="search-wrap" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <label class="search">
      <input type="text" name="suchText" placeholder="Suche in Touren …" value="<?php echo esc_attr($suchText); ?>">
    </label>
    <button class="btn btn-primary" type="submit">Suchen</button>
  </form>

  <?php if ( $err ): ?>
    <div class="notice notice-error">API-Fehler: <?php echo esc_html($err); ?></div>
  <?php endif; ?>

  <?php if ( !empty($tours) ): ?>
    <div class="tour-grid" id="tour-grid" data-center-lat="<?php echo esc_attr($CENTER_LAT); ?>" data-center-lng="<?php echo esc_attr($CENTER_LNG); ?>">
      <?php foreach ( $tours as $idx => $t ):
        $tour_url = esc_url( add_query_arg('id', rawurlencode($t['id']), $tour_detail_base) );
        $mapId = 'tourmap_' . intval($idx);
      ?>
        <article class="tour-card" data-tour-id="<?php echo esc_attr($t['id']); ?>" data-map-id="<?php echo esc_attr($mapId); ?>">
          <div class="tour-top">
            <div class="tour-hero">
              <?php if (!empty($t['img'])): ?>
                <a href="<?php echo $tour_url; ?>" aria-label="<?php echo esc_attr($t['name']); ?>">
                  <img src="<?php echo esc_url($t['img']); ?>" alt="">
                </a>
              <?php else: ?>
                <div class="fallback">🧭</div>
              <?php endif; ?>
            </div>
            <div class="tour-map" id="<?php echo esc_attr($mapId); ?>"></div>
          </div>

          <div class="tour-body">
            <h3 class="tour-title"><a href="<?php echo $tour_url; ?>"><?php echo esc_html($t['name']); ?></a></h3>
            <p class="tour-desc"><?php echo esc_html( mb_strimwidth( $t['desc'], 0, 180, ' …' ) ); ?></p>

            <div class="tour-meta">
              <span class="badge">🧷 <span class="v"><?php echo esc_html( count($t['stops']) ); ?></span><small>Stopps</small></span>
              <span class="badge">📏 <span class="v"><?php echo $t['total_km'] ? esc_html( number_format($t['total_km'], 1, ',', '.') ) : '—'; ?></span><small>km</small></span>
              <span class="badge">📍 <span class="v"><?php echo $t['min_dist'] ? esc_html( number_format($t['min_dist'], 1, ',', '.') ) : '—'; ?></span><small>km bis KL</small></span>
            </div>
          </div>

          <div class="tour-actions">
            <a class="btn btn-primary" href="<?php echo $tour_url; ?>">Tour öffnen</a>
          </div>

          <!-- Stops als JSON für die Mini-Map -->
          <script type="application/json" data-stops-json="<?php echo esc_attr($mapId); ?>">
            <?php echo wp_json_encode($t['stops']); ?>
          </script>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p style="margin-top:16px;">Keine Touren im Umkreis gefunden (oder keine Tour hat verwertbare Koordinaten).</p>
  <?php endif; ?>
</section>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
window.addEventListener('load', () => {
  if(!window.L) return;
  const grid = document.getElementById('tour-grid');
  if(!grid) return;

  const cards = Array.from(grid.querySelectorAll('.tour-card'));

  function getStops(mapId){
    const s = grid.querySelector(`script[data-stops-json="${mapId}"]`);
    if(!s) return [];
    try { return JSON.parse(s.textContent || '[]'); } catch(e){ return []; }
  }

  cards.forEach(card => {
    const mapId = card.dataset.mapId;
    const mapEl = document.getElementById(mapId);
    if(!mapEl) return;

    const stops = getStops(mapId);
    const latlngs = (stops || [])
      .map(s => [Number(s.lat), Number(s.lng)])
      .filter(p => Number.isFinite(p[0]) && Number.isFinite(p[1]));

    if(latlngs.length < 2){
      mapEl.innerHTML = `<div style="height:100%;display:flex;align-items:center;justify-content:center;color:#9aa3b2;font-size:.95rem;">Keine Route.</div>`;
      return;
    }

    const m = L.map(mapEl, { zoomControl:false, attributionControl:false, dragging:false, scrollWheelZoom:false, doubleClickZoom:false, boxZoom:false, keyboard:false, tap:false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(m);

    latlngs.forEach((p, idx) => {
      L.circleMarker(p, { radius: 5 }).addTo(m).bindTooltip(String(idx+1), {direction:'top'});
    });
    const line = L.polyline(latlngs, { weight: 4 }).addTo(m);
    m.fitBounds(line.getBounds(), { padding:[10,10] });
  });
});
</script>

<?php get_footer(); ?>
