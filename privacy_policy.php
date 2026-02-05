<?php
$pageData = array(
    'title' => 'Ochrana osobných údajov | E-shop',
    'metaDataDescription' => 'Zásady ochrany osobných údajov',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/legal.css')
    )
);
require_once 'theme/header.php';
?>

<div class="container">
    <div class="legal-page">
        <h1>Ochrana osobných údajov</h1>
        
        <div class="last-updated">
            Platné od: 01.01.2024<br>
            Posledná aktualizácia: <?php echo date('d.m.Y'); ?>
        </div>

        <div class="important-notice">
            <h3>⚠ Dôležité upozornenie</h3>
            <p>Táto stránka je <strong>vývojársky a demonštračný projekt</strong>. Všetky produkty sú fiktívne a nákupom nezískate žiadny reálny tovar.</p>
        </div>

        <div class="legal-section">
            <h2>1. Základné informácie</h2>
            <p><strong>Správca osobných údajov:</strong> mojadomena.sk<br>
            <strong>Účel spracovania:</strong> Funkčnosť e-shop aplikácie<br>
            <strong>Právny základ:</strong> Váš súhlas a oprávnený záujem</p>
        </div>

        <div class="legal-section">
            <h2>2. Aké údaje spracúvame</h2>
            <h3>2.1 Údaje poskytnuté pri registrácii:</h3>
            <ul>
                <li>E-mailová adresa (pre overenie jedinečnosti účtu)</li>
                <li>Meno a priezvisko (pre personalizáciu)</li>
                <li>Heslo (šifrované, neviditeľné ani pre správcov)</li>
            </ul>

            <h3>2.2 Údaje z objednávok:</h3>
            <ul>
                <li>Dodacie údaje (pre simuláciu dopravy)</li>
                <li>Kontaktné informácie</li>
                <li>Históriu objednávok</li>
            </ul>

            <h3>2.3 Technické údaje:</h3>
            <ul>
                <li>IP adresa</li>
                <li>Typ prehliadača a zariadenia</li>
                <li>Čas návštevy a stránky, ktoré ste navštívili</li>
            </ul>
        </div>

        <div class="legal-section">
            <h2>3. Ako používame vaše údaje</h2>
            <p>3.1 <strong>Všetky údaje sú používané výlučne na beh tejto aplikácie</strong> a nie sú predávané, prenajímané ani zdieľané s tretími stranami.</p>
            <p>3.2 Údaje z objednávok slúžia na demonštráciu funkčnosti nákupného procesu.</p>
            <p>3.3 E-mailové adresy sa <strong>nepoužívajú na marketingové účely</strong>.</p>
        </div>

        <div class="legal-section">
            <h2>4. Zabezpečenie údajov</h2>
            <p>4.1 Heslá sú ukladané v zašifrovanej podobe.</p>
            <p>4.2 Komunikácia medzi vášm prehliadačom a serverom je chránená.</p>
            <p>4.3 Pravidelne aktualizujeme bezpečnostné opatrenia.</p>
        </div>

        <div class="legal-section">
            <h2>5. Cookies a sledovacie technológie</h2>
            <p>5.1 <strong>Nezbierame žiadne sledovacie cookies</strong> pre marketingové účely.</p>
            <p>5.2 Technicky nevyhnutné cookies:</p>
            <ul>
                <li>Session cookies - pre prihlásenie a košík</li>
                <li>Bezpečnostné cookies - pre ochranu proti útokom</li>
            </ul>
            <p>5.3 Nepoužívame analytické nástroje tretích strán (Google Analytics, Facebook Pixel, atď.)</p>
        </div>

        <div class="legal-section">
            <h2>6. Vaše práva</h2>
            <p>Podľa GDPR máte právo na:</p>
            <ul>
                <li><strong>Prístup</strong> k vašim údajom</li>
                <li><strong>Opravu</strong> nesprávnych údajov</li>
                <li><strong>Vymazanie</strong> vašich údajov ("právo byť zabudnutý")</li>
                <li><strong>Obmedzenie</strong> spracovania</li>
                <li><strong>Prenos</strong> údajov</li>
                <li><strong>Námietku</strong> proti spracovaniu</li>
            </ul>
            <p>Pre uplatnenie týchto práv nás kontaktujte na e-mailu uvedenom nižšie.</p>
        </div>

        <div class="legal-section">
            <h2>7. Kontakt</h2>
            <p><strong>Kontakt na správcu:</strong><br>
            E-mail: <a href="mailto:someone@example.com">someone@example.com</a></p>
        </div>

        <div class="project-info">
            <h3>O tomto projekte</h3>
            <p>Táto webová stránka bola vytvorená ako <strong>školský projekt</strong> na demonštráciu funkčností e-shopu. Cieľom je ukázať technickú implementáciu, nie skutočný obchod.</p>
            <p>Všetky údaje sú testovacie a po dokončení projektu budú trvalo odstránené.</p>
        </div>
    </div>
</div>

<?php require_once 'theme/footer.php'; ?>