<?php
// Sicherheit: Direktes Aufrufen verhindern
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
</main> <!-- schließt das <main> aus header.php -->


<footer class="site-footer" role="contentinfo">
  <div class="container footer-content">

    <div class="footer-top">
      <div class="footer-left">
        <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. Alle Rechte vorbehalten.</p>
      </div>

     <nav class="footer-right" aria-label="<?php esc_attr_e('Footer-Menü','kuladig'); ?>">
  <ul class="footer-menu">
    <li><a href="<?php echo esc_url( home_url('/rechtliches/#impressum') ); ?>">Impressum</a></li>
    <li><a href="<?php echo esc_url( home_url('/rechtliches/#datenschutz') ); ?>">Datenschutz</a></li>
    <li><a href="<?php echo esc_url( home_url('/rechtliches/#kontakt') ); ?>">Kontakt</a></li>
  </ul>
</nav>
    </div>

    <div class="footer-logos" aria-label="<?php esc_attr_e('Partner & Förderer','kuladig'); ?>">
      <ul class="logo-list">
        <li>
          <a href="https://hessen.de/" target="_blank" rel="noopener noreferrer" aria-label="Zur Website von Hessen">
            <img src="<?php echo get_template_directory_uri(); ?>/assets/images/Logo-Hessen.png" 
                 alt="Land Hessen" loading="lazy">
          </a>
        </li>
        <li>
          <a href="https://www.rlp.de/" target="_blank" rel="noopener noreferrer" aria-label="Zur Website von Rheinland-Pfalz">
            <img src="<?php echo get_template_directory_uri(); ?>/assets/images/Logo-RP.png" 
                 alt="Land Rheinland-Pfalz" loading="lazy">
          </a>
        </li>
        <li>
          <a href="https://www.schleswig-holstein.de/" target="_blank" rel="noopener noreferrer" aria-label="Zur Website von Schleswig-Holstein">
            <img src="<?php echo get_template_directory_uri(); ?>/assets/images/Logo-SH.png" 
                 alt="Land Schleswig-Holstein" loading="lazy">
          </a>
        </li>
        <li>
          <a href="https://www.rheinischer-verein.de/" target="_blank" rel="noopener noreferrer" aria-label="Zur Website des Rheinischen Vereins">
            <img src="<?php echo get_template_directory_uri(); ?>/assets/images/rheinischer_verein_Farbe.png" 
                 alt="Rheinischer Verein für Denkmalpflege und Landschaftsschutz" loading="lazy">
          </a>
        </li>
      </ul>
    </div>

  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>