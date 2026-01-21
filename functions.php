<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action('after_setup_theme', function(){
  add_theme_support('title-tag');
  add_theme_support('html5',['style','script','navigation-widgets']);
});

/**
 * Lädt main.css/main.js entweder aus /assets/
 */
function kld_theme_asset_uri(string $preferred, string $fallback) : string {
  $dir = get_template_directory();
  if (file_exists($dir . $preferred)) return get_template_directory_uri() . $preferred;
  return get_template_directory_uri() . $fallback;
}
function kld_theme_asset_mtime(string $preferred, string $fallback) : int {
  $dir = get_template_directory();
  if (file_exists($dir . $preferred)) return filemtime($dir . $preferred);
  if (file_exists($dir . $fallback)) return filemtime($dir . $fallback);
  return time();
}

add_action('wp_enqueue_scripts', function(){
  // CSS: entweder /assets/css/main.css oder /main.css
  $css_uri = kld_theme_asset_uri('/assets/css/main.css', '/main.css');
  $css_ver = kld_theme_asset_mtime('/assets/css/main.css', '/main.css');
  wp_enqueue_style('cms-projekt-main', $css_uri, [], $css_ver);

  // JS: entweder /assets/js/main.js oder /main.js
  $js_uri = kld_theme_asset_uri('/assets/js/main.js', '/main.js');
  $js_ver = kld_theme_asset_mtime('/assets/js/main.js', '/main.js');
  wp_enqueue_script('cms-projekt-main', $js_uri, [], $js_ver, true);

  wp_localize_script('cms-projekt-main', 'KLD', [
    'ajaxUrl'    => admin_url('admin-ajax.php'),
    'objektBase' => home_url('/objekt/'),
    'isKarte'    => is_page('karte'),
    'kl_center'  => ['lat' => 49.4447, 'lng' => 7.7690],
    'radius_km'  => 20,
  ]);
});

/* === Haversine Distanz Berechnung === */
function kld_haversine_distance($lat1, $lon1, $lat2, $lon2) {
  $earth_radius = 6371; // km

  $dLat = deg2rad($lat2 - $lat1);
  $dLon = deg2rad($lon2 - $lon1);

  $a = sin($dLat/2) * sin($dLat/2) +
       cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
       sin($dLon/2) * sin($dLon/2);

  $c = 2 * atan2(sqrt($a), sqrt(1-$a));

  return $earth_radius * $c;
}

/**
 * Punktkoordinate aus KuLaDig Ergebnissen lesen
 * KuLaDig liefert GeoJSON: { type:"Point", coordinates:[lng,lat] }
 */
function kld_extract_point_from_item(array $it): ?array {
  if (!isset($it['Punktkoordinate'])) return null;
  $pt = $it['Punktkoordinate'];

  // GeoJSON
  if (is_array($pt) && isset($pt['coordinates']) && is_array($pt['coordinates']) && count($pt['coordinates']) >= 2) {
    $lng = floatval($pt['coordinates'][0]);
    $lat = floatval($pt['coordinates'][1]);
    if (is_finite($lat) && is_finite($lng) && abs($lat) <= 90 && abs($lng) <= 180) {
      return ['lat' => $lat, 'lng' => $lng];
    }
  }

  // Fallback: [lng,lat] oder [lat,lng]
  if (is_array($pt) && isset($pt[0], $pt[1])) {
    $a = floatval($pt[0]);
    $b = floatval($pt[1]);

    $lngLatValid = (abs($a) <= 180 && abs($b) <= 90);
    $latLngValid = (abs($a) <= 90 && abs($b) <= 180);

    if ($lngLatValid) return ['lat' => $b, 'lng' => $a];
    if ($latLngValid) return ['lat' => $a, 'lng' => $b];
  }

  return null;
}

/* === KuLaDig Map Proxy mit Radius-Filter === */
add_action('wp_ajax_kld_map', 'kld_map_handler');
add_action('wp_ajax_nopriv_kld_map', 'kld_map_handler');

/**
 * kld_map_handler
 */
function kld_map_handler() {
  $kl_lat = 49.4447;
  $kl_lng = 7.7690;
  $radius_km = 20.0;
  $radius_m  = 20000;

  $max_pages = isset($_GET['max_pages']) ? max(1, min(20, intval($_GET['max_pages']))) : 10;
  $debug     = !empty($_GET['debug']);

  // Optionaler Suchtext 
  $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';

  // Cache 
  $cache_key = 'kld_map_kl20_' . md5($q . '|' . $max_pages);
  $cached = get_transient($cache_key);
  if ($cached !== false) {
    wp_send_json_success($cached);
  }

  $base_url = 'https://www.kuladig.de/api/public/Objekt';

  $geo_point = [
    'type' => 'Point',
    'coordinates' => [$kl_lng, $kl_lat],
  ];

  $out = [];
  $pages_fetched = 0;
  $total_pages_reported = null;

  for ($page = 0; $page < $max_pages; $page++) {
    $args = [
      'ObjektTyp' => 'KuladigObjekt',
      'Geometrie' => wp_json_encode($geo_point),
      'Distanz'   => $radius_m,
      'Seite'     => $page,
    ];
    if ($q !== '') $args['suchText'] = $q;

    $url = add_query_arg($args, $base_url);

    $resp = wp_remote_get($url, [
      'timeout' => 15,
      'headers' => ['Accept' => 'application/json'],
    ]);

    if (is_wp_error($resp)) break;

    $code = wp_remote_retrieve_response_code($resp);
    if ($code < 200 || $code >= 300) break;

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($data)) break;

    $pages_fetched++;
    if ($total_pages_reported === null) {
      $total_pages_reported = intval($data['AnzahlSeiten'] ?? 1);
    }

    $items = $data['Ergebnis'] ?? [];
    if (is_array($items) && array_keys($items) !== range(0, count($items) - 1)) {
      $items = array_values($items);
    }
    if (!is_array($items) || count($items) === 0) break;

    foreach ($items as $it) {
      if (!is_array($it)) continue;

      $pt = kld_extract_point_from_item($it);
      if (!$pt) continue;

      $distance = kld_haversine_distance($kl_lat, $kl_lng, $pt['lat'], $pt['lng']);
      if ($distance > $radius_km) continue;

      $id = strval($it['Id'] ?? '');
      if ($id === '') continue;

      $out[] = [
        'id'   => $id,
        'name' => strval($it['Name'] ?? ''),
        'desc' => strval($it['Beschreibung'] ?? ''),
        'token'=> strval($it['ThumbnailToken'] ?? ''),
        'pt'   => ['lat' => floatval($pt['lat']), 'lng' => floatval($pt['lng'])],
        'distance' => round($distance, 2),
      ];
    }

    if ($total_pages_reported !== null && ($page + 1) >= $total_pages_reported) break;
  }

  // Duplikate entfernen
  $seen = [];
  $out_unique = [];
  foreach ($out as $o) {
    if (empty($o['id']) || isset($seen[$o['id']])) continue;
    $seen[$o['id']] = true;
    $out_unique[] = $o;
  }

  // nach Distanz sortieren
  usort($out_unique, function($a, $b){
    return ($a['distance'] <=> $b['distance']);
  });

  $payload = [
    'items'        => $out_unique,
    'anzahlSeiten' => $total_pages_reported ?? 0,
    'filtered'     => count($out_unique),
    'pagesFetched' => $pages_fetched,
  ];

  if ($debug) {
    $payload['debug'] = [
      'center' => ['lat' => $kl_lat, 'lng' => $kl_lng],
      'radius_km' => $radius_km,
      'max_pages' => $max_pages,
      'q' => $q,
    ];
  }

  set_transient($cache_key, $payload, 5 * MINUTE_IN_SECONDS);
  wp_send_json_success($payload);
}

function kld_load_all_objects( array $params = [], int $maxPages = 50 ) {
    $base = 'https://www.kuladig.de/api/public/Objekt';
    $cache_key = 'kld_all_' . md5(json_encode($params));
    $cached = get_transient($cache_key);

    if ($cached !== false) return $cached;

    $all = [];
    for ($page = 0; $page < $maxPages; $page++) {
        $url = add_query_arg(array_merge($params, ['Seite' => $page]), $base);
        $resp = wp_remote_get($url, [
            'timeout' => 12,
            'headers' => ['Accept' => 'application/json']
        ]);

        if (is_wp_error($resp)) break;
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['Ergebnis'])) break;

        foreach ($data['Ergebnis'] as $obj) {
            $all[] = $obj;
        }

        $totalPages = intval($data['AnzahlSeiten'] ?? 1);
        if ($page + 1 >= $totalPages) break;
    }

    set_transient($cache_key, $all, 12 * HOUR_IN_SECONDS);
    return $all;
}

// ========== KuLaDig Meta (Kategorie) Proxy ==========

add_action('wp_ajax_kld_obj_meta', 'kld_ajax_kld_obj_meta');
add_action('wp_ajax_nopriv_kld_obj_meta', 'kld_ajax_kld_obj_meta');

function kld_ajax_kld_obj_meta() {
  $idsRaw = isset($_GET['ids']) ? sanitize_text_field($_GET['ids']) : '';
  $ids = array_values(array_filter(array_map('trim', explode(',', $idsRaw))));
  $ids = array_slice($ids, 0, 50); // Hard limit

  $byId = [];
  $uniqueCats = [];

  foreach ($ids as $id) {
    $cacheKey = 'kld_cat_' . md5($id);
    $cat = get_transient($cacheKey);

    if ($cat === false) {
      $url = 'https://www.kuladig.de/api/public/Objekt/' . rawurlencode($id);
      $resp = wp_remote_get($url, [
        'timeout' => 12,
        'headers' => ['Accept' => 'application/json']
      ]);

      $cat = '';
      if (!is_wp_error($resp)) {
        $code = wp_remote_retrieve_response_code($resp);
        if ($code >= 200 && $code < 300) {
          $data = json_decode(wp_remote_retrieve_body($resp), true);
          if (is_array($data)) {
            $cat = kld_extract_category_best_effort($data);
          }
        }
      }

      set_transient($cacheKey, $cat, 30 * DAY_IN_SECONDS);
    }

    $byId[$id] = ['category' => $cat];
    if (!empty($cat)) $uniqueCats[$cat] = true;
  }

  wp_send_json_success([
    'byId' => $byId,
    'categories' => array_values(array_keys($uniqueCats)),
  ]);
}

function kld_extract_category_best_effort(array $data): string {
  $hits = [];
  $rx = '/kateg|fachsicht|schlagw|thema/i';

  $collectLabels = function($v) use (&$hits, &$collectLabels) {
    if (is_string($v)) {
      $v = trim($v);
      if ($v !== '') $hits[] = $v;
      return;
    }
    if (is_array($v)) {
      // häufig: [{Name:"..."}, ...] oder ["...", "..."]
      foreach ($v as $k => $x) {
        if (is_string($k) && in_array(strtolower($k), ['name','label','titel','title'], true) && is_string($x)) {
          $x = trim($x);
          if ($x !== '') $hits[] = $x;
        } else {
          $collectLabels($x);
        }
      }
    }
  };

  $walk = function($node) use (&$walk, $rx, &$collectLabels) {
    if (!is_array($node)) return;
    foreach ($node as $k => $v) {
      if (is_string($k) && preg_match($rx, $k)) {
        $collectLabels($v);
      }
      if (is_array($v)) $walk($v);
    }
  };

  $walk($data);

  return isset($hits[0]) ? $hits[0] : '';
}



 // KuLa Touren
add_action('wp_ajax_kld_route_preview', 'kld_ajax_route_preview');
add_action('wp_ajax_nopriv_kld_route_preview', 'kld_ajax_route_preview');

function kld_ajax_route_preview(){
  $id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
  $limit = isset($_GET['limit']) ? max(2, min(200, intval($_GET['limit']))) : 12;

  //Radius um Kaiserslautern
  $centerLat = isset($_GET['centerLat']) ? floatval($_GET['centerLat']) : 49.4447;
  $centerLng = isset($_GET['centerLng']) ? floatval($_GET['centerLng']) : 7.7690;
  $radiusKm  = isset($_GET['radiusKm'])  ? floatval($_GET['radiusKm'])  : 20.0;

  if(empty($id)){
    wp_send_json_error(['message' => 'Missing id'], 400);
  }

  $payload = kld_route_preview_payload($id, $limit, $centerLat, $centerLng, $radiusKm);
  if(!$payload){
    wp_send_json_error(['message' => 'Objektgruppe konnte nicht geladen werden.'], 502);
  }

  wp_send_json_success($payload);
}

function kld_route_preview_payload(string $id, int $limit, float $centerLat, float $centerLng, float $radiusKm){
  $limit = max(2, min(200, $limit));

  $cache_key = 'kld_route_' . md5($id . '|' . $limit . '|' . $centerLat . '|' . $centerLng . '|' . $radiusKm);
  $cached = get_transient($cache_key);
  if($cached !== false){
    return $cached;
  }

  $group = kld_kuladig_get_detail($id);
  if(!$group) return null;

  $childIds = kld_extract_child_ids($group);
  if(empty($childIds)){
    $payload = [
      'group' => [
        'id' => $id,
        'name' => $group['Name'] ?? $id,
        'desc' => isset($group['Beschreibung']) ? wp_strip_all_tags($group['Beschreibung']) : '',
      ],
      'stops' => [],
      'total_km' => 0,
      'min_dist_km' => 0,
      'in_radius' => false,
    ];
    set_transient($cache_key, $payload, 6 * HOUR_IN_SECONDS);
    return $payload;
  }

  $childIds = array_slice($childIds, 0, $limit);

  $stops = [];
  $minDist = null;

  foreach($childIds as $cid){
    $obj = kld_kuladig_get_detail($cid);
    if(!$obj) continue;

    $pt = kld_extract_latlng($obj);
    if(!$pt) continue;

    $lat = floatval($pt['lat']);
    $lng = floatval($pt['lng']);

    $stops[] = [
      'id' => $cid,
      'name' => $obj['Name'] ?? $cid,
      'lat' => $lat,
      'lng' => $lng,
    ];

    $d = kld_haversine_km($centerLat, $centerLng, $lat, $lng);
    if($minDist === null || $d < $minDist) $minDist = $d;
  }

  $total_km = kld_polyline_length_km($stops);
  $minDistVal = $minDist === null ? 0.0 : round(floatval($minDist), 3);
  $inRadius = ($minDist !== null) && ($minDist <= $radiusKm);

  $payload = [
    'group' => [
      'id' => $id,
      'name' => $group['Name'] ?? $id,
      'desc' => isset($group['Beschreibung']) ? wp_strip_all_tags($group['Beschreibung']) : '',
    ],
    'stops' => $stops,
    'total_km' => $total_km,
    'min_dist_km' => $minDistVal,
    'in_radius' => $inRadius,
  ];

  set_transient($cache_key, $payload, 12 * HOUR_IN_SECONDS);
  return $payload;
}

/* -------------------- helpers -------------------- */

function kld_kuladig_get_detail(string $id){
  $url = 'https://www.kuladig.de/api/public/Objekt/' . rawurlencode($id);
  $key = 'kld_obj_detail_' . md5($url);
  $cached = get_transient($key);
  if($cached !== false) return $cached;

  $resp = wp_remote_get($url, [
    'timeout' => 12,
    'headers' => ['Accept' => 'application/json']
  ]);
  if(is_wp_error($resp)) return null;

  $code = wp_remote_retrieve_response_code($resp);
  if($code < 200 || $code >= 300) return null;

  $data = json_decode(wp_remote_retrieve_body($resp), true);
  if(!is_array($data)) return null;

  set_transient($key, $data, 24 * HOUR_IN_SECONDS);
  return $data;
}

function kld_extract_child_ids(array $group): array {
  $keys = ['UntergeordneteObjekte','untergeordneteObjekte','Objekte','objekte','VerwandteObjekte','verwandteObjekte'];
  $ids = [];

  foreach($keys as $k){
    if(empty($group[$k]) || !is_array($group[$k])) continue;

    foreach($group[$k] as $entry){
      if(is_string($entry)){
        $ids[] = $entry;
        continue;
      }
      if(is_array($entry)){
        if(!empty($entry['Id'])) $ids[] = (string)$entry['Id'];
        elseif(!empty($entry['id'])) $ids[] = (string)$entry['id'];
      }
    }

    if(!empty($ids)) break;
  }

  $seen = [];
  $out = [];
  foreach($ids as $x){
    if($x === '' || isset($seen[$x])) continue;
    $seen[$x] = true;
    $out[] = $x;
  }
  return $out;
}

function kld_extract_latlng(array $obj){
  $pk = $obj['Punktkoordinate'] ?? ($obj['punktkoordinate'] ?? null);
  if(is_array($pk)){
    if(isset($pk['lat'], $pk['lng'])) return ['lat' => floatval($pk['lat']), 'lng' => floatval($pk['lng'])];
    if(isset($pk['Lat'], $pk['Lng'])) return ['lat' => floatval($pk['Lat']), 'lng' => floatval($pk['Lng'])];
    if(isset($pk['coordinates']) && is_array($pk['coordinates']) && count($pk['coordinates']) >= 2){
      return ['lng' => floatval($pk['coordinates'][0]), 'lat' => floatval($pk['coordinates'][1])];
    }
    if(isset($pk['Coordinates']) && is_array($pk['Coordinates']) && count($pk['Coordinates']) >= 2){
      return ['lng' => floatval($pk['Coordinates'][0]), 'lat' => floatval($pk['Coordinates'][1])];
    }
  }
  if(isset($obj['pt']['lat'], $obj['pt']['lng'])){
    return ['lat' => floatval($obj['pt']['lat']), 'lng' => floatval($obj['pt']['lng'])];
  }
  return null;
}

function kld_polyline_length_km(array $stops): float {
  $n = count($stops);
  if($n < 2) return 0.0;

  $sum = 0.0;
  for($i=1;$i<$n;$i++){
    $a = $stops[$i-1];
    $b = $stops[$i];
    $sum += kld_haversine_km(floatval($a['lat']), floatval($a['lng']), floatval($b['lat']), floatval($b['lng']));
  }
  return round($sum, 3);
}

function kld_haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float {
  $R = 6371.0;
  $dLat = deg2rad($lat2 - $lat1);
  $dLon = deg2rad($lon2 - $lon1);
  $lat1 = deg2rad($lat1);
  $lat2 = deg2rad($lat2);

  $a = sin($dLat/2)*sin($dLat/2) + cos($lat1)*cos($lat2) * sin($dLon/2)*sin($dLon/2);
  $c = 2 * atan2(sqrt($a), sqrt(1-$a));
  return $R * $c;
}
