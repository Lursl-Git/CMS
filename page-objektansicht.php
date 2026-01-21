<?php
/**
 * Template Name: Objektansicht
 * Template Post Type: page
 */
if ( ! defined('ABSPATH') ) { exit; }

get_header();

/* Helper Functions */
function kld_get_object_detail( $id ) {
  if ( empty($id) ) return null;
  
  $cache_key = 'kld_obj_' . md5($id);
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $url = 'https://www.kuladig.de/api/public/Objekt/' . rawurlencode($id);
  $resp = wp_remote_get($url, [
    'timeout' => 15,
    'headers' => ['Accept' => 'application/json']
  ]);

  if (is_wp_error($resp)) {
    error_log('KuLaDig API Error: ' . $resp->get_error_message());
    return null;
  }
  
  $code = wp_remote_retrieve_response_code($resp);
  if ($code < 200 || $code >= 300) {
    error_log('KuLaDig API HTTP Error: ' . $code);
    return null;
  }

  $body = wp_remote_retrieve_body($resp);
  $data = json_decode($body, true);
  
  if (!is_array($data)) {
    error_log('KuLaDig API: Invalid JSON response');
    return null;
  }

  set_transient($cache_key, $data, 1800);
  return $data;
}

function kld_get_image_url($token) {
  if (empty($token)) return '';
  return 'https://www.kuladig.de/api/public/Dokument?token=' . rawurlencode($token);
}

function kld_format_date($dateStr) {
  if (empty($dateStr)) return '';
  $timestamp = strtotime($dateStr);
  if (!$timestamp) return $dateStr;
  return date_i18n('d.m.Y', $timestamp);
}


/**
 * Pierre-Helper
 */
if (!function_exists('kld_label')) {
  function kld_label($value) {
    if (is_array($value)) {
      if (isset($value[1]) && is_scalar($value[1])) return trim((string)$value[1]);
      if (isset($value['Name']) && is_scalar($value['Name'])) return trim((string)$value['Name']);
      if (isset($value['name']) && is_scalar($value['name'])) return trim((string)$value['name']);
      if (isset($value['Titel']) && is_scalar($value['Titel'])) return trim((string)$value['Titel']);
      if (isset($value['titel']) && is_scalar($value['titel'])) return trim((string)$value['titel']);
      foreach ($value as $v) {
        if (is_scalar($v)) return trim((string)$v);
      }
      return '';
    }
    return is_scalar($value) ? trim((string)$value) : '';
  }
}

if (!function_exists('kld_labels_list')) {
  function kld_labels_list($value) {
    if (empty($value)) return [];
    if (!is_array($value)) {
      $one = kld_label($value);
      return $one !== '' ? [$one] : [];
    }

    $items = $value;
    $is_assoc = array_keys($value) !== range(0, count($value) - 1);
    if ($is_assoc) $items = [$value];

    $out = [];
    foreach ($items as $it) {
      $label = kld_label($it);
      if ($label !== '') $out[] = $label;
    }
    return array_values(array_unique($out));
  }
}

if (!function_exists('kld_parse_bbcode')) {
  function kld_parse_bbcode($text) {
    if (empty($text)) return '';

    $text = preg_replace('/\[id=.*?\]\s*\[\/id\]/si', '', $text);
    $text = preg_replace('/\[right\].*?\[\/right\]/si', '', $text);
    $text = preg_replace('/\[a=.*?\](.*?)\[\/a\]/si', '$1', $text);

    $text = preg_replace('/\[b\](.*?)\[\/b\]/si', '<strong>$1</strong>', $text);
    $text = preg_replace('/\[i\](.*?)\[\/i\]/si', '<em>$1</em>', $text);

    $text = preg_replace('/\[list\]/i', '<ul>', $text);
    $text = preg_replace('/\[\/list\]/i', '</ul>', $text);
    $text = preg_replace('/\[\*\](.*?)($|\[\*\])/si', '<li>$1</li>', $text);

    $text = preg_replace_callback(
      '/\[url=(.*?)\](.*?)\[\/url\]/si',
      function($m) { return '<a href="'.esc_url($m[1]).'" target="_blank" rel="noopener">'.$m[2].'</a>'; },
      $text
    );

    return wpautop(wp_kses_post($text));
  }
}

if (!function_exists('kld_zeitraum')) {
  function kld_zeitraum($d) {
    if (!is_array($d)) return '';
    if (!empty($d['AnfangVon']) && !empty($d['EndeBis'])) return $d['AnfangVon'].'–'.$d['EndeBis'];
    if (!empty($d['AnfangVon'])) return 'Beginn '.$d['AnfangVon'];
    if (!empty($d['AnfangBis'])) return 'Beginn vor '.$d['AnfangBis'];
    return '';
  }
}

$objekt_id = $_GET['id'] ?? '';

if (empty($objekt_id)) {
  echo '<div class="container" style="padding: 120px 20px 60px; text-align: center;">';
  echo '<h1>Keine ID angegeben</h1>';
  echo '<p>Bitte wählen Sie ein Objekt aus.</p>';
  echo '<a href="' . esc_url(home_url('/')) . '" class="btn-primary" style="display:inline-block;margin-top:20px;">Zurück zur Startseite</a>';
  echo '</div>';
  get_footer();
  exit;
}

$objekt = kld_get_object_detail($objekt_id);

if (!$objekt) {
  echo '<div class="container" style="padding: 120px 20px 60px; text-align: center;">';
  echo '<h1>Objekt nicht gefunden</h1>';
  echo '<p>Das angeforderte Objekt konnte nicht geladen werden.</p>';
  echo '<p style="color: #9aa3b2; font-size: 0.9rem;">ID: ' . esc_html($objekt_id) . '</p>';
  echo '<a href="' . esc_url(home_url('/')) . '" class="btn-primary" style="display:inline-block;margin-top:20px;">Zurück zur Startseite</a>';
  echo '</div>';
  get_footer();
  exit;
}

//Objekt-Struktur ausgeben
if (current_user_can('administrator') && isset($_GET['debug'])) {
  echo '<div style="background:#1a1f2e;color:#4cc9f0;padding:20px;margin:80px 20px 20px;border-radius:10px;font-family:monospace;font-size:12px;overflow:auto;max-height:400px;">';
  echo '<strong>DEBUG: API Response</strong><br><br>';
  echo '<strong>Verfügbare Felder:</strong><br>';
  echo htmlspecialchars(print_r(array_keys($objekt), true));
  echo '<br><br><strong>Thumbnail-Tokens:</strong><br>';
  echo 'ThumbnailToken: ' . (isset($objekt['ThumbnailToken']) ? substr($objekt['ThumbnailToken'], 0, 50) . '...' : 'nicht vorhanden') . '<br>';
  echo 'Thumbnail2Token: ' . (isset($objekt['Thumbnail2Token']) ? substr($objekt['Thumbnail2Token'], 0, 50) . '...' : 'nicht vorhanden') . '<br>';
  echo 'Thumbnail3Token: ' . (isset($objekt['Thumbnail3Token']) ? substr($objekt['Thumbnail3Token'], 0, 50) . '...' : 'nicht vorhanden') . '<br>';
  echo '</div>';
}

// Daten extrahieren
$name = $objekt['Name'] ?? 'Unbekanntes Objekt';

$beschreibung_raw = $objekt['Beschreibung'] ?? '';
$autor_name = '';
if (!empty($beschreibung_raw) && preg_match('/\[author\](.*?)\[\/author\]/si', $beschreibung_raw, $m)) {
  $autor_name = trim($m[1]);
  $beschreibung_raw = preg_replace('/\[author\].*?\[\/author\]/si', '', $beschreibung_raw);
}
$beschreibung = kld_parse_bbcode($beschreibung_raw);

// Kategorie-Felder
$kategorie = kld_label($objekt['Kategorie'] ?? '');
$unterkategorie = kld_label($objekt['Unterkategorie'] ?? '');
$ort = $objekt['Ort'] ?? '';
$strasse = $objekt['Strasse'] ?? '';
$hausnummer = $objekt['Hausnummer'] ?? '';
$plz = $objekt['PLZ'] ?? '';
$gemeinde = $objekt['Gemeinde'] ?? '';
$kreis = $objekt['Kreis'] ?? '';
$bundesland = $objekt['Bundesland'] ?? '';
$datierung = $objekt['Datierung'] ?? '';
$denkmalwert = kld_label($objekt['Denkmalschutz'] ?? ($objekt['Denkmalwert'] ?? ''));
$erfassungsmassstab = kld_label($objekt['Erfassungsmassstab'] ?? '');
$erfassungsmethoden = kld_labels_list($objekt['Erfassungsmethoden'] ?? []);
$fachsichten = kld_labels_list($objekt['Fachsichten'] ?? []);
$zeitraum = kld_zeitraum($objekt);
$epochen = $objekt['Epochen'] ?? [];
$schlagworte = $objekt['Schlagwoerter'] ?? ($objekt['Schlagworte'] ?? []);
$literatur = $objekt['Literatur'] ?? '';
$literatur_zitate = $objekt['LiteraturZitate'] ?? [];
$externer_link = $objekt['ExternerLink'] ?? '';
$kuladig_url = $objekt['KuladigUrl'] ?? '';
if (empty($kuladig_url) && !empty($objekt_id)) {
  $kuladig_url = 'https://www.kuladig.de/Objektansicht/KLD-' . $objekt_id;
}
$autor = $objekt['Autor'] ?? '';
$erstelldatum = $objekt['Erstelldatum'] ?? '';
$aenderdatum = $objekt['Aenderdatum'] ?? '';

// Koordinaten extrahieren
$punkt = $objekt['Punktkoordinate'] ?? null;
$lat = null;
$lng = null;

if (is_array($punkt)) {
  if (isset($punkt['lat']) && isset($punkt['lng'])) {
    $lat = floatval($punkt['lat']);
    $lng = floatval($punkt['lng']);
  } elseif (isset($punkt[1]) && isset($punkt[0])) {
    $lat = floatval($punkt[1]);
    $lng = floatval($punkt[0]);
  }
}

// Bilder sammeln
$bilder = [];

$thumbnail_fields = [
  ['token' => 'ThumbnailToken', 'titel' => 'ThumbnailTitel', 'urheber' => 'ThumbnailUrheber'],
  ['token' => 'Thumbnail2Token', 'titel' => 'Thumbnail2Titel', 'urheber' => 'Thumbnail2Urheber'],
  ['token' => 'Thumbnail3Token', 'titel' => 'Thumbnail3Titel', 'urheber' => 'Thumbnail3Urheber'],
  ['token' => 'Thumbnail4Token', 'titel' => 'Thumbnail4Titel', 'urheber' => 'Thumbnail4Urheber'],
  ['token' => 'Thumbnail5Token', 'titel' => 'Thumbnail5Titel', 'urheber' => 'Thumbnail5Urheber'],
];

foreach ($thumbnail_fields as $fields) {
  $token = $objekt[$fields['token']] ?? '';
  if (!empty($token)) {
    $bilder[] = [
      'token' => $token,
      'titel' => $objekt[$fields['titel']] ?? '',
      'urheber' => $objekt[$fields['urheber']] ?? '',
    ];
  }
}

//Falls kein Thumbnail
if (empty($bilder)) {
  $candidates = [];

  $maybeLists = [
    $objekt['Dokumente'] ?? null,
    $objekt['Bilder'] ?? null,
    $objekt['Medien'] ?? null,
  ];

  foreach ($maybeLists as $list) {
    if (!is_array($list)) continue;

    // Liste vs. einzelnes Objekt
    $items = $list;
    if (isset($list['Token']) || isset($list['token']) || isset($list['ThumbnailToken']) || isset($list['Thumbnail3Token'])) {
      $items = [$list];
    }

    foreach ($items as $it) {
      if (!is_array($it)) continue;

      $token = $it['ThumbnailToken']
            ?? ($it['Thumbnail3Token']
            ?? ($it['Token']
            ?? ($it['token'] ?? '')));

      if (empty($token)) continue;

      $key = (string)$token;
      if (isset($candidates[$key])) continue;

      $candidates[$key] = [
        'token'   => $token,
        'titel'   => $it['Titel'] ?? ($it['titel'] ?? ($it['Name'] ?? ($it['name'] ?? ''))),
        'urheber' => $it['Urheber'] ?? ($it['urheber'] ?? ''),
      ];

      if (count($candidates) >= 10) break 2;
    }
  }

  if (!empty($candidates)) {
    $bilder = array_values($candidates);
  }
}


// Bilder-Info ausgeben
if (current_user_can('administrator')) {
  error_log('KuLaDig Bilder gefunden: ' . count($bilder));
  if (!empty($bilder)) {
    error_log('Erstes Bild Token: ' . substr($bilder[0]['token'], 0, 50) . '...');
  }
}

// Adresse zusammensetzen
$adresse_teile = array_filter([
  trim($strasse . ' ' . $hausnummer),
  trim($plz . ' ' . $ort),
  $gemeinde,
  $kreis,
  $bundesland
]);
$adresse_built = implode(', ', $adresse_teile);
$adresse = !empty($objekt['Adresse']) ? $objekt['Adresse'] : $adresse_built;
$epochen_str = is_array($epochen) ? implode(', ', array_filter($epochen)) : '';
$schlagworte_arr = kld_labels_list($schlagworte);

// 3D Model
$media3d = get_template_directory_uri() . '/assets/demo/model.glb';
$has3d_file = file_exists(get_template_directory() . '/assets/demo/model.glb');

// Erlaubte HTML-Tags
$allowed_tags = [
  'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
  'a' => ['href' => [], 'title' => [], 'target' => [], 'rel' => []],
  'ul' => [], 'ol' => [], 'li' => [],
  'h3' => [], 'h4' => [],
  'blockquote' => [],
];

?>

<div class="objektansicht-wrapper">
  <div class="objektansicht-container">
    
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="<?php echo esc_url(home_url('/')); ?>">Start</a>
      <span class="separator">›</span>
      <a href="<?php echo esc_url(home_url('/objekte/')); ?>">Objekte</a>
      <span class="separator">›</span>
      <span class="current"><?php echo esc_html(mb_strimwidth($name, 0, 50, '...')); ?></span>
    </nav>

    <!-- Hero Header mit API-Bild -->
    <header class="objekt-header">
      <?php if (!empty($bilder)): ?>
        <div class="objekt-hero-image">
          <?php 
          $hero_image_url = kld_get_image_url($bilder[0]['token']);
          if (current_user_can('administrator')) {
            error_log('Hero Image URL: ' . $hero_image_url);
          }
          ?>
          <img src="<?php echo esc_url($hero_image_url); ?>" 
               alt="<?php echo esc_attr($name); ?>"
               loading="eager"
               onerror="console.error('Bild konnte nicht geladen werden:', this.src); this.style.display='none'; this.parentElement.innerHTML='<div style=\'height:400px;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.1);color:#9aa3b2;\'>Bild nicht verfügbar</div>';">
          <?php if (!empty($bilder[0]['urheber']) || !empty($bilder[0]['titel'])): ?>
            <div class="image-credit">
              <?php if (!empty($bilder[0]['urheber'])): ?>
                © <?php echo esc_html($bilder[0]['urheber']); ?>
              <?php endif; ?>
              <?php if (!empty($bilder[0]['titel'])): ?>
                <?php echo !empty($bilder[0]['urheber']) ? ' - ' : ''; ?>
                <?php echo esc_html($bilder[0]['titel']); ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div style="padding: 20px; text-align: center; color: #9aa3b2; background: rgba(0,0,0,0.1); border-radius: 14px; margin-bottom: 24px;">
          Kein Bild verfügbar
        </div>
      <?php endif; ?>

      <div class="objekt-header-content">
        <h1><?php echo esc_html($name); ?></h1>
        
        <?php if ($kategorie || $unterkategorie): ?>
          <div class="objekt-meta-tags">
            <?php if ($kategorie): ?>
              <span class="meta-tag category"><?php echo esc_html($kategorie); ?></span>
            <?php endif; ?>
            <?php if ($unterkategorie): ?>
              <span class="meta-tag subcategory"><?php echo esc_html($unterkategorie); ?></span>
            <?php endif; ?>
            <?php if (!empty($fachsichten)): ?>
              <?php foreach (array_slice($fachsichten, 0, 3) as $fs): ?>
                <span class="meta-tag"><?php echo esc_html($fs); ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($autor_name)): ?>
              <span class="meta-tag"><?php echo esc_html('Autor: ' . $autor_name); ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($adresse): ?>
          <div class="objekt-location">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
            <span><?php echo esc_html($adresse); ?></span>
          </div>
        <?php endif; ?>
      </div>
    </header>

    <div class="objekt-content-grid">
      
      <div class="objekt-main">
        
        <!-- Beschreibung -->
        <?php if ($beschreibung): ?>
          <section class="content-section">
            <h2>Beschreibung</h2>
            <div class="beschreibung-text">
              <?php echo $beschreibung; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- 3D Viewer Button und Container -->
        <section class="content-section">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="margin: 0;">3D-Ansicht</h2>
            <button id="toggle3DViewer" class="btn btn-primary">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>
              </svg>
              <span class="viewer-toggle-text">3D-Modell anzeigen</span>
            </button>
          </div>
          
          <div id="viewer3DSection" class="viewer-3d-section">
            <div class="viewer-3d-container">
              <?php if ($has3d_file): ?>
                <model-viewer
                  src="<?php echo esc_url($media3d); ?>"
                  alt="<?php echo esc_attr($name); ?> – 3D-Modell"
                  camera-controls
                  auto-rotate
                  interaction-prompt="auto"
                  bounds="tight"
                  camera-orbit="0deg 75deg auto"
                  camera-target="auto auto auto"
                  field-of-view="auto"
                  style="width:100%;height:100%;"
                ></model-viewer>
              <?php else: ?>
                <div class="viewer-3d-placeholder">
                  <div>
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                      <path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                    <p><strong>3D-Modell Demo</strong></p>
                    <p style="font-size:0.9rem;margin-top:8px;">In der finalen Version würde hier ein interaktives 3D-Modell des Objekts angezeigt werden.</p>
                    <p style="font-size:0.85rem;margin-top:8px;opacity:0.7;">Hinweis: Lege eine <code>model.glb</code> Datei unter <code>/assets/demo/</code> ab, um ein echtes 3D-Modell zu sehen.</p>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <!-- Weitere Bilder -->
        <?php if (count($bilder) > 1): ?>
          <section class="content-section">
            <h2>Weitere Bilder</h2>
            <div class="image-gallery">
              <?php foreach (array_slice($bilder, 1) as $idx => $bild): ?>
                <div class="gallery-item">
                  <img src="<?php echo esc_url(kld_get_image_url($bild['token'])); ?>" 
                       alt="<?php echo esc_attr($bild['titel'] ?: ($name . ' - Bild ' . ($idx + 2))); ?>"
                       loading="lazy"
                       onerror="this.parentElement.style.display='none';">
                  <?php if ($bild['urheber'] || $bild['titel']): ?>
                    <div class="gallery-caption">
                      <?php if ($bild['titel']): ?>
                        <strong><?php echo esc_html($bild['titel']); ?></strong><br>
                      <?php endif; ?>
                      <?php if ($bild['urheber']): ?>
                        <small>© <?php echo esc_html($bild['urheber']); ?></small>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- Denkmalwert -->
        <?php if ($denkmalwert): ?>
          <section class="content-section">
            <h2>Denkmalwert</h2>
            <div class="beschreibung-text">
              <?php echo wp_kses($denkmalwert, $allowed_tags); ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- Literatur -->
        <?php if (!empty($literatur) || (!empty($literatur_zitate) && is_array($literatur_zitate))): ?>
          <section class="content-section">
            <h2>Literatur & Quellen</h2>

            <?php if (!empty($literatur)): ?>
              <div class="literatur-text">
                <?php echo wp_kses($literatur, $allowed_tags); ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($literatur_zitate) && is_array($literatur_zitate)): ?>
              <ul class="literatur-list">
                <?php foreach ($literatur_zitate as $l): ?>
                  <?php
                    $line = '';
                    if (is_string($l)) $line = $l;
                    elseif (is_array($l)) {
                      $line = $l['Zitat'] ?? ($l['Text'] ?? ($l['Quelle'] ?? ''));
                      if (empty($line)) $line = implode(' – ', array_filter(array_map('kld_label', $l)));
                    }
                  ?>
                  <?php if (!empty($line)): ?>
                    <li><?php echo esc_html($line); ?></li>
                  <?php endif; ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
        <?php endif; ?>

      </div>

      <!-- Sidebar -->
      <aside class="objekt-sidebar">
        
        <!-- Standort Karte -->
        <?php if ($lat && $lng && is_finite($lat) && is_finite($lng)): ?>
          <div class="info-box">
            <h3>Standort</h3>
            <div id="objekt-map" class="objekt-map" data-lat="<?php echo esc_attr($lat); ?>" data-lng="<?php echo esc_attr($lng); ?>"></div>
            <div class="coordinates">
              <small>Koordinaten: <?php echo number_format($lat, 6, ',', ''); ?>, <?php echo number_format($lng, 6, ',', ''); ?></small>
            </div>
          </div>
        <?php endif; ?>
        
        <!-- Eckdaten -->
        <div class="info-box">
          <h3>Eckdaten</h3>
          
          <?php if ($datierung): ?>
            <div class="info-item">
              <span class="info-label">Datierung</span>
              <span class="info-value"><?php echo esc_html($datierung); ?></span>
            </div>
          <?php endif; ?>

          <?php if ($epochen_str): ?>
            <div class="info-item">
              <span class="info-label">Epoche</span>
              <span class="info-value"><?php echo esc_html($epochen_str); ?></span>
            </div>
          <?php endif; ?>

          <?php if (!empty($zeitraum)): ?>
            <div class="info-item">
              <span class="info-label">Historischer Zeitraum</span>
              <span class="info-value"><?php echo esc_html($zeitraum); ?></span>
            </div>
          <?php endif; ?>

          <?php if (!empty($denkmalwert)): ?>
            <div class="info-item">
              <span class="info-label">Denkmalschutz</span>
              <span class="info-value"><?php echo esc_html($denkmalwert); ?></span>
            </div>
          <?php endif; ?>

          <?php if (!empty($erfassungsmassstab)): ?>
            <div class="info-item">
              <span class="info-label">Erfassungsmaßstab</span>
              <span class="info-value"><?php echo esc_html($erfassungsmassstab); ?></span>
            </div>
          <?php endif; ?>

          <?php if (!empty($erfassungsmethoden)): ?>
            <div class="info-item">
              <span class="info-label">Erfassungsmethoden</span>
              <span class="info-value"><?php echo esc_html(implode(', ', $erfassungsmethoden)); ?></span>
            </div>
          <?php endif; ?>

          <?php if (!empty($fachsichten)): ?>
            <div class="info-item">
              <span class="info-label">Fachsichten</span>
              <span class="info-value"><?php echo esc_html(implode(', ', $fachsichten)); ?></span>
            </div>
          <?php endif; ?>

          <?php if (!empty($adresse)): ?>
            <div class="info-item">
              <span class="info-label">Adresse</span>
              <span class="info-value"><?php echo esc_html($adresse); ?></span>
            </div>
          <?php endif; ?>


          <?php if ($kategorie): ?>
            <div class="info-item">
              <span class="info-label">Kategorie</span>
              <span class="info-value"><?php echo esc_html($kategorie); ?></span>
            </div>
          <?php endif; ?>

          <?php if ($ort): ?>
            <div class="info-item">
              <span class="info-label">Ort</span>
              <span class="info-value"><?php echo esc_html($ort); ?></span>
            </div>
          <?php endif; ?>

          <?php if ($gemeinde && $gemeinde !== $ort): ?>
            <div class="info-item">
              <span class="info-label">Gemeinde</span>
              <span class="info-value"><?php echo esc_html($gemeinde); ?></span>
            </div>
          <?php endif; ?>

          <?php if ($kreis): ?>
            <div class="info-item">
              <span class="info-label">Kreis</span>
              <span class="info-value"><?php echo esc_html($kreis); ?></span>
            </div>
          <?php endif; ?>

          <?php if ($bundesland): ?>
            <div class="info-item">
              <span class="info-label">Bundesland</span>
              <span class="info-value"><?php echo esc_html($bundesland); ?></span>
            </div>
          <?php endif; ?>

          <?php if (!$datierung && !$epochen_str && !$zeitraum && !$denkmalwert && !$erfassungsmassstab && empty($erfassungsmethoden) && empty($fachsichten) && !$kategorie && !$ort && !$gemeinde && !$kreis && !$bundesland && !$adresse): ?>
            <p style="color: #9aa3b2; font-size: 0.9rem; margin: 0;">Keine weiteren Eckdaten verfügbar.</p>
          <?php endif; ?>
        </div>

        <!-- Schlagworte -->
        <?php if (!empty($schlagworte_arr)): ?>
          <div class="info-box">
            <h3>Schlagworte</h3>
            <div class="tag-cloud">
              <?php foreach ($schlagworte_arr as $tag): ?>
                <span class="tag"><?php echo esc_html($tag); ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Weitere Informationen -->
        <?php if ($kuladig_url || $externer_link): ?>
          <div class="info-box">
            <h3>Weitere Informationen</h3>
            
            <?php if ($kuladig_url): ?>
              <a href="<?php echo esc_url($kuladig_url); ?>" target="_blank" rel="noopener" class="external-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                  <polyline points="15 3 21 3 21 9"/>
                  <line x1="10" y1="14" x2="21" y2="3"/>
                </svg>
                KuLaDig Originalseite
              </a>
            <?php endif; ?>

            <?php if ($externer_link): ?>
              <a href="<?php echo esc_url($externer_link); ?>" target="_blank" rel="noopener" class="external-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                  <polyline points="15 3 21 3 21 9"/>
                  <line x1="10" y1="14" x2="21" y2="3"/>
                </svg>
                Externe Webseite
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Metadaten -->
        <?php if ($autor || $erstelldatum || $aenderdatum): ?>
          <div class="info-box meta-info">
            <h3>Metadaten</h3>
            
            <?php if ($autor): ?>
              <div class="meta-item">
                <small class="meta-label">Autor:</small>
                <small><?php echo esc_html($autor); ?></small>
              </div>
            <?php endif; ?>
            
            <?php if ($erstelldatum): ?>
              <div class="meta-item">
                <small class="meta-label">Erstellt:</small>
                <small><?php echo esc_html(kld_format_date($erstelldatum)); ?></small>
              </div>
            <?php endif; ?>
            
            <?php if ($aenderdatum): ?>
              <div class="meta-item">
                <small class="meta-label">Aktualisiert:</small>
                <small><?php echo esc_html(kld_format_date($aenderdatum)); ?></small>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </aside>
    </div>

    <!-- Back -->
    <div class="back-navigation">
      <a href="javascript:history.back()" class="btn-back">
        ← Zurück
      </a>
      <a href="<?php echo esc_url(home_url('/objekte/')); ?>" class="btn-back">
        Alle Objekte ansehen
      </a>
    </div>

  </div>
</div>

<!-- Model Viewer Script -->
<script type="module" src="https://unpkg.com/@google/model-viewer@3.3.0/dist/model-viewer.min.js"></script>

<!-- Leaflet für Karte -->
<?php if ($lat && $lng && is_finite($lat) && is_finite($lng)): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
window.addEventListener('load', function(){
  if (!window.L) return;
  
  const mapEl = document.getElementById('objekt-map');
  if (!mapEl) return;
  
  const lat = parseFloat(mapEl.dataset.lat);
  const lng = parseFloat(mapEl.dataset.lng);
  
  if (!isFinite(lat) || !isFinite(lng)) {
    mapEl.innerHTML = '<p style="padding:20px;text-align:center;color:#9aa3b2;">Koordinaten nicht verfügbar</p>';
    return;
  }
  
  try {
    const map = L.map('objekt-map', { 
      zoomControl: true,
      scrollWheelZoom: false 
    }).setView([lat, lng], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap',
      maxZoom: 19
    }).addTo(map);
    
    L.marker([lat, lng])
      .bindPopup('<b><?php echo esc_js($name); ?></b>')
      .addTo(map)
      .openPopup();
  } catch(e) {
    console.error('Fehler beim Initialisieren der Karte:', e);
  }
});
</script>
<?php endif; ?>

<!-- 3D Viewer Toggle Script -->
<script>
(function() {
  'use strict';
  
  function init3DToggle() {
    const toggleBtn = document.getElementById('toggle3DViewer');
    const viewerSection = document.getElementById('viewer3DSection');
    const toggleText = toggleBtn?.querySelector('.viewer-toggle-text');
    
    if (!toggleBtn || !viewerSection) {
      console.log('3D Toggle: Elemente nicht gefunden');
      return;
    }
    
    console.log('3D Toggle: Initialisiert');
    
    toggleBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const isActive = viewerSection.classList.contains('is-active');
      
      if (isActive) {
        viewerSection.classList.remove('is-active');
        if (toggleText) toggleText.textContent = '3D-Modell anzeigen';
        console.log('3D Viewer: Versteckt');
      } else {
        viewerSection.classList.add('is-active');
        if (toggleText) toggleText.textContent = '3D-Modell verbergen';
        console.log('3D Viewer: Angezeigt');
      }
    });
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init3DToggle);
  } else {
    init3DToggle();
  }
})();
</script>

<?php get_footer(); ?>