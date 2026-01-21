<?php
/**
 * Template Name: Rechtliches
 * Template Post Type: page
 */
if ( ! defined('ABSPATH') ) { exit; }

get_header();
?>

<div class="rechtliches-wrapper">
    <div class="rechtliches-container">
        
        <!-- Sticky Navigation -->
        <nav class="rechtliches-nav" role="navigation" aria-label="Rechtliche Informationen">
            <a href="#impressum" class="nav-link">Impressum</a>
            <a href="#datenschutz" class="nav-link">Datenschutz</a>
            <a href="#kontakt" class="nav-link">Kontakt</a>
        </nav>

        <!-- Impressum Section -->
        <section id="impressum" class="rechtliches-section">
            <h2>Impressum</h2>
            
            <h3>Angaben gemäß § 5 TMG</h3>
            <div class="contact-info">
                <p><strong>Betreiber der Website:</strong></p>
                <p>[Ihr Name / Firmenname]</p>
                <p>[Straße und Hausnummer]</p>
                <p>[PLZ und Ort]</p>
            </div>

            <h3>Kontakt</h3>
            <p>
                <strong>Telefon:</strong> [Ihre Telefonnummer]<br>
                <strong>E-Mail:</strong> <a href="mailto:info@example.de">info@example.de</a>
            </p>

            <h3>Umsatzsteuer-ID</h3>
            <p>Umsatzsteuer-Identifikationsnummer gemäß §27 a Umsatzsteuergesetz:<br>
            [Ihre USt-IdNr.]</p>

            <h3>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h3>
            <p>
                [Name]<br>
                [Adresse]
            </p>

            <h3>EU-Streitschlichtung</h3>
            <p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit: 
            <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr</a><br>
            Unsere E-Mail-Adresse finden Sie oben im Impressum.</p>

            <h3>Verbraucherstreitbeilegung/Universalschlichtungsstelle</h3>
            <p>Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer 
            Verbraucherschlichtungsstelle teilzunehmen.</p>
        </section>

        <!-- Datenschutz Section -->
        <section id="datenschutz" class="rechtliches-section">
            <h2>Datenschutzerklärung</h2>

            <h3>1. Datenschutz auf einen Blick</h3>
            
            <h4>Allgemeine Hinweise</h4>
            <p>Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen 
            Daten passiert, wenn Sie diese Website besuchen. Personenbezogene Daten sind alle Daten, mit 
            denen Sie persönlich identifiziert werden können.</p>

            <h4>Datenerfassung auf dieser Website</h4>
            <p><strong>Wer ist verantwortlich für die Datenerfassung auf dieser Website?</strong></p>
            <p>Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber. Dessen 
            Kontaktdaten können Sie dem Impressum dieser Website entnehmen.</p>

            <h3>2. Hosting</h3>
            <p>Wir hosten die Inhalte unserer Website bei folgendem Anbieter:</p>
            <p><strong>[Hosting-Anbieter Name]</strong><br>
            Diese Website wird extern gehostet. Die personenbezogenen Daten, die auf dieser Website 
            erfasst werden, werden auf den Servern des Hosters gespeichert.</p>

            <h3>3. Allgemeine Hinweise und Pflichtinformationen</h3>
            
            <h4>Datenschutz</h4>
            <p>Die Betreiber dieser Seiten nehmen den Schutz Ihrer persönlichen Daten sehr ernst. Wir 
            behandeln Ihre personenbezogenen Daten vertraulich und entsprechend der gesetzlichen 
            Datenschutzvorschriften sowie dieser Datenschutzerklärung.</p>

            <h4>Hinweis zur verantwortlichen Stelle</h4>
            <p>Die verantwortliche Stelle für die Datenverarbeitung auf dieser Website ist:</p>
            <div class="contact-info">
                <p>[Name / Firmenname]<br>
                [Straße und Hausnummer]<br>
                [PLZ und Ort]<br>
                Telefon: [Telefonnummer]<br>
                E-Mail: <a href="mailto:info@example.de">info@example.de</a></p>
            </div>

            <h3>4. Datenerfassung auf dieser Website</h3>
            
            <h4>Cookies</h4>
            <p>Unsere Internetseiten verwenden so genannte „Cookies". Cookies sind kleine Textdateien und 
            richten auf Ihrem Endgerät keinen Schaden an. Sie werden entweder vorübergehend für die Dauer 
            einer Sitzung (Session-Cookies) oder dauerhaft (permanente Cookies) auf Ihrem Endgerät gespeichert.</p>

            <h4>Server-Log-Dateien</h4>
            <p>Der Provider der Seiten erhebt und speichert automatisch Informationen in so genannten 
            Server-Log-Dateien, die Ihr Browser automatisch an uns übermittelt. Dies sind:</p>
            <ul>
                <li>Browsertyp und Browserversion</li>
                <li>verwendetes Betriebssystem</li>
                <li>Referrer URL</li>
                <li>Hostname des zugreifenden Rechners</li>
                <li>Uhrzeit der Serveranfrage</li>
                <li>IP-Adresse</li>
            </ul>

            <h3>5. Externe APIs und Dienste</h3>
            <p>Diese Website nutzt die KuLaDig-API zur Darstellung von Kulturobjekten und -routen. 
            Dabei werden Daten von externen Servern geladen. Weitere Informationen zum Datenschutz 
            finden Sie unter: <a href="https://www.kuladig.de/datenschutz" target="_blank" rel="noopener">www.kuladig.de</a></p>

            <h3>6. Ihre Rechte</h3>
            <p>Sie haben folgende Rechte:</p>
            <ul>
                <li>Recht auf Auskunft über Ihre gespeicherten Daten</li>
                <li>Recht auf Berichtigung unrichtiger Daten</li>
                <li>Recht auf Löschung Ihrer Daten</li>
                <li>Recht auf Einschränkung der Datenverarbeitung</li>
                <li>Recht auf Datenübertragbarkeit</li>
                <li>Widerspruchsrecht gegen die Verarbeitung</li>
            </ul>
        </section>

        <!-- Kontakt Section -->
        <section id="kontakt" class="rechtliches-section">
            <h2>Kontakt</h2>

            <h3>So erreichen Sie uns</h3>
            <p>Haben Sie Fragen, Anregungen oder möchten Sie uns kontaktieren? Wir freuen uns auf Ihre Nachricht!</p>

            <div class="contact-info">
                <p><strong>Postanschrift:</strong></p>
                <p>[Ihr Name / Firmenname]<br>
                [Straße und Hausnummer]<br>
                [PLZ und Ort]<br>
                Deutschland</p>
            </div>

            <div class="contact-info">
                <p><strong>Telefon:</strong> [Ihre Telefonnummer]</p>
                <p><strong>E-Mail:</strong> <a href="mailto:info@example.de">info@example.de</a></p>
                <p><strong>Website:</strong> <a href="<?php echo esc_url( home_url('/') ); ?>"><?php echo esc_html( get_bloginfo('name') ); ?></a></p>
            </div>

            <h3>Öffnungszeiten</h3>
            <p>
                <strong>Montag – Freitag:</strong> 09:00 – 17:00 Uhr<br>
                <strong>Samstag – Sonntag:</strong> Geschlossen
            </p>

            <h3>Feedback und Anfragen</h3>
            <p>Für technische Probleme, Verbesserungsvorschläge oder allgemeine Anfragen zur Website 
            nutzen Sie bitte die oben genannten Kontaktdaten. Wir bemühen uns, alle Anfragen innerhalb 
            von 2-3 Werktagen zu beantworten.</p>

            <h3>Kooperationen</h3>
            <p>Sie möchten mit uns kooperieren oder haben ein interessantes Projekt? 
            Kontaktieren Sie uns gerne per E-Mail mit einer kurzen Beschreibung Ihres Anliegens.</p>
        </section>

    </div>
</div>

<!-- Back to Top Button -->
<a href="#" class="back-to-top" aria-label="Nach oben scrollen">↑</a>

<?php get_footer(); ?>