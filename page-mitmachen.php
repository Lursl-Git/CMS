<?php
/**
 * Template Name: Mitmachen (Ort einreichen)
 * Template Post Type: page
 */
if (!defined('ABSPATH')) { exit; }

get_header();

// ---- Verarbeitung ----
$success = false; $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kld_nonce'])) {
  if (!wp_verify_nonce($_POST['kld_nonce'], 'kld_submit')) {
    $errors[] = 'Sicherheitsprüfung fehlgeschlagen.';
  } else {
    // Eingaben einsammeln & sanitisieren
    $titel   = isset($_POST['titel'])   ? sanitize_text_field($_POST['titel'])   : '';
    $name    = isset($_POST['name'])    ? sanitize_text_field($_POST['name'])    : '';
    $email   = isset($_POST['email'])   ? sanitize_email($_POST['email'])        : '';
    $ort     = isset($_POST['ort'])     ? sanitize_text_field($_POST['ort'])     : '';
    $lat     = isset($_POST['lat'])     ? floatval($_POST['lat'])                : 0.0;
    $lng     = isset($_POST['lng'])     ? floatval($_POST['lng'])                : 0.0;
    $bildurl = isset($_POST['bildurl']) ? esc_url_raw($_POST['bildurl'])         : '';
    $text    = isset($_POST['text'])    ? wp_kses_post($_POST['text'])           : '';
    $honeyp  = isset($_POST['webseite']) ? trim($_POST['webseite'])              : ''; // Honeypot

    // Pflichtfelder prüfen
    if ($honeyp !== '') { $errors[] = 'Spam erkannt.'; }
    if ($titel === '')  { $errors[] = 'Bitte einen Titel angeben.'; }
    if (!is_email($email)) { $errors[] = 'Bitte eine gültige E-Mail angeben.'; }
    if ($text === '')   { $errors[] = 'Bitte eine Beschreibung angeben.'; }

    if (empty($errors)) {
      // Beitrag anlegen (Pending)
      $post_id = wp_insert_post([
        'post_type'   => 'kld_vorschlag',
        'post_status' => 'pending',
        'post_title'  => $titel,
        'post_content'=> $text,
        'meta_input'  => [
          '_kld_name'    => $name,
          '_kld_email'   => $email,
          '_kld_ort'     => $ort,
          '_kld_lat'     => $lat,
          '_kld_lng'     => $lng,
          '_kld_bildurl' => $bildurl,
        ],
      ], true);

      if (is_wp_error($post_id)) {
        $errors[] = 'Konnte den Vorschlag nicht speichern: '.$post_id->get_error_message();
      } else {
        // Admin-Mail
        $admin = get_option('admin_email');
        $subj  = 'Neuer KuLaDig-Vorschlag: '.$titel;
        $msg   = "Titel: $titel\n"
               . "Name: $name\n"
               . "E-Mail: $email\n"
               . "Ort: $ort\n"
               . "Koordinaten: $lat, $lng\n"
               . "Bild-URL: $bildurl\n\n"
               . "Beschreibung:\n$text\n\n"
               . "Bearbeiten: ".admin_url('post.php?post='.$post_id.'&action=edit');

        wp_mail($admin, $subj, $msg);

        // Flag setzen
        $success = true;
      }
    }
  }
}
?>

<style>
  :root { --mitmachen-gap: 100px; }
  #main { padding-top: var(--mitmachen-gap); }
  .form-card{
    border:1px solid #1d2530; border-radius:14px;
    background:rgba(255,255,255,.04); padding:18px;
  }
  .form-grid{ display:grid; gap:12px; grid-template-columns:1fr 1fr; }
  .form-grid .span-2{ grid-column:1 / -1; }
  @media (max-width:800px){ .form-grid{ grid-template-columns:1fr; } }

  .field{ display:flex; flex-direction:column; gap:6px; }
  .field label{ color:#c9d1e1; font-size:.95rem; }
  .field input[type="text"],
  .field input[type="email"],
  .field input[type="url"],
  .field textarea{
    background:rgba(255,255,255,0.06);
    border:1px solid #1d2530; color:#f5f7fb;
    border-radius:12px; padding:12px 14px; font-size:1rem;
    outline:2px solid transparent;
  }
  .field textarea{ min-height:130px; resize:vertical; }
  .help{ color:#9aa3b2; font-size:.9rem; }

  .error{ background:#3a0c0c; border:1px solid #6e1f1f; padding:10px 12px; border-radius:10px; }
  .success{ background:#0f3727; border:1px solid #1f7a54; padding:10px 12px; border-radius:10px; }

  /* Karte */
  #map{ height:280px; border-radius:12px; border:1px solid #1d2530; }
</style>

<main id="main">
  <section class="container" style="padding:32px 0 48px;">
    <h1 style="margin:0 0 12px;">Ort einreichen</h1>
    <p class="help" style="margin:0 0 18px;">Schlage ein neues Objekt vor. Wir prüfen deinen Vorschlag und melden uns ggf. per E-Mail.</p>

    <?php if (!empty($errors)): ?>
      <div class="error" style="margin-bottom:12px;">
        <strong>Bitte prüfen:</strong>
        <ul style="margin:6px 0 0 18px;">
          <?php foreach ($errors as $e){ echo '<li>'.esc_html($e).'</li>'; } ?>
        </ul>
      </div>
    <?php elseif ($success): ?>
      <div class="success" style="margin-bottom:12px;">
        Danke! Dein Vorschlag wurde eingereicht und wird geprüft.
      </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( get_permalink() ); ?>" class="form-card">
      <?php wp_nonce_field('kld_submit','kld_nonce'); ?>

      <!-- Honeypot (Spam-Schutz) -->
      <div style="position:absolute;left:-9999px;top:auto;">
        <label>Webseite <input type="text" name="webseite" value=""></label>
      </div>

      <div class="form-grid">
        <div class="field">
          <label for="titel">Titel *</label>
          <input type="text" id="titel" name="titel" required>
        </div>

        <div class="field">
          <label for="ort">Ort / Gemeinde</label>
          <input type="text" id="ort" name="ort" placeholder="z. B. Koblenz">
        </div>

        <div class="field">
          <label for="name">Dein Name</label>
          <input type="text" id="name" name="name" placeholder="optional">
        </div>

        <div class="field">
          <label for="email">E-Mail *</label>
          <input type="email" id="email" name="email" required placeholder="wir kontaktieren dich nur bei Rückfragen">
        </div>

        <div class="field span-2">
          <label for="text">Beschreibung *</label>
          <textarea id="text" name="text" required placeholder="Warum ist der Ort relevant? Quellen/Links?"></textarea>
        </div>

        <div class="field">
          <label for="bildurl">Bild-URL (optional)</label>
          <input type="url" id="bildurl" name="bildurl" placeholder="https://…">
          <span class="help">Falls vorhanden – Bildrechte sicherstellen!</span>
        </div>

        <div class="field span-2">
          <label>Position (Karte) – klick zum Setzen</label>
          <div id="map"></div>
          <input type="hidden" name="lat" id="lat" value="">
          <input type="hidden" name="lng" id="lng" value="">
          <span class="help">Du kannst auch ohne Koordinaten einreichen.</span>
        </div>
      </div>

      <div style="display:flex;gap:10px;align-items:center;margin-top:12px;">
        <button class="btn btn-primary" type="submit">Vorschlag senden</button>
        <span class="help">Mit Klick stimmst du der Verarbeitung gemäß Datenschutz zu.</span>
      </div>
    </form>
  </section>
</main>

<!-- Leaflet nur hier für die kleine Karte laden -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  if (!window.L) return;
  var map = L.map('map').setView([51.2, 10.2], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'&copy; OpenStreetMap' }).addTo(map);
  var marker = null;
  function setLatLng(latlng){
    document.getElementById('lat').value = latlng.lat.toFixed(6);
    document.getElementById('lng').value = latlng.lng.toFixed(6);
    if (marker){ marker.setLatLng(latlng); } else { marker = L.marker(latlng).addTo(map); }
  }
  map.on('click', function(e){ setLatLng(e.latlng); });
});
</script>

<?php get_footer(); ?>
