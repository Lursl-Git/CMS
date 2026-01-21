<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- Anmelde-Modal -->
<div id="loginModal" class="modal" role="dialog" aria-labelledby="loginModalTitle" aria-hidden="true">
  <div class="modal-overlay" data-close-modal></div>
  <div class="modal-content">
    <button class="modal-close" data-close-modal aria-label="Schließen">×</button>
    
    <h2 id="loginModalTitle" style="margin: 0 0 8px;">Anmelden</h2>
    <p style="color: #9aa3b2; margin: 0 0 24px;">Melde dich an, um eigene Orte einzureichen und Favoriten zu speichern.</p>
    
    <form class="login-form" onsubmit="return handleLogin(event)">
      <div class="form-group">
        <label for="loginEmail">E-Mail</label>
        <input type="email" id="loginEmail" name="email" required placeholder="deine@email.de">
      </div>
      
      <div class="form-group">
        <label for="loginPassword">Passwort</label>
        <input type="password" id="loginPassword" name="password" required placeholder="••••••••">
      </div>
      
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" style="width: 100%;">Anmelden</button>
      </div>
      
      <p style="text-align: center; margin: 16px 0 0; color: #9aa3b2; font-size: 0.9rem;">
        Noch kein Konto? <a href="#register" style="color: #4cc9f0;">Jetzt registrieren</a>
      </p>
    </form>
  </div>
</div>

<!-- Hinweis: Standort in Chrome auf HTTP (Dev) -->
<div id="geoHelpModal" class="modal" role="dialog" aria-labelledby="geoHelpModalTitle" aria-hidden="true">
  <div class="modal-overlay" data-close-modal></div>
  <div class="modal-content">
    <button class="modal-close" data-close-modal aria-label="Schließen">×</button>

    <h2 id="geoHelpModalTitle" style="margin: 0 0 8px;">Standortfunktion aktivieren</h2>
    <p style="margin: 0 0 12px;">
      Auf <strong>HTTP</strong> blockiert Chrome die Standortabfrage. Für die Entwicklung kannst du dieses Origin temporär als „secure“ behandeln.
    </p>

    <ol style="margin: 0 0 14px; padding-left: 18px;">
      <li>Öffne <code>chrome://flags/#unsafely-treat-insecure-origin-as-secure</code></li>
      <li>Trage unter <em>Insecure origins treated as secure</em> dieses Origin ein: <code data-geo-origin></code></li>
      <li>Chrome neu starten</li>
    </ol>

    <p style="margin: 0 0 14px;">
      Alternative: Seite über <strong>HTTPS</strong> oder <strong>localhost</strong> aufrufen.
    </p>

    <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
      <button type="button" class="btn-secondary" data-geo-copy>Origin kopieren</button>
      <button type="button" class="btn-secondary" data-geo-dismiss data-close-modal>Nicht mehr anzeigen</button>
      <button type="button" class="btn btn-primary" data-close-modal>OK</button>
    </div>
  </div>
</div>

<header>
    <div class="container nav" role="navigation" aria-label="Haupt">
        <a class="brand" href="<?php echo esc_url( home_url('/') ); ?>" aria-label="<?php esc_attr_e('Zur Startseite','kuladig'); ?>">
            <img
                id="brandLogo"
                class="brand-badge"
                src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/KuLaDig_W_img.png' ); ?>"
                alt="<?php bloginfo('name'); ?>">
        </a>
        
        <nav class="menu" aria-label="Primäre Navigation">
            <a href="<?php echo esc_url( home_url('/karte/') ); ?>">Karte entdecken</a>
            <a href="<?php echo esc_url( home_url('/objekte/') ); ?>">Objekte</a>
            <a href="<?php echo esc_url( home_url('/routen/') ); ?>">Touren</a>
            <a href="<?php echo esc_url( home_url('/mitmachen/') ); ?>">Ort einreichen</a>
            <a href="#" data-open-modal="loginModal">Anmelden</a>
            <button id="themeToggle" class="theme-toggle" aria-label="Theme wechseln" title="Dark/Light Mode">
                <span class="theme-icon">🌙</span>
            </button>
            <a class="cta-mini" href="<?php echo esc_url( home_url('/karte/') ); ?>">Jetzt entdecken</a>
        </nav>
        
        <button class="burger" aria-label="Menü öffnen" aria-expanded="false" aria-controls="drawer">☰</button>
    </div>
    <div id="drawer" class="mobile-hidden"></div>
</header>

<main id="main">