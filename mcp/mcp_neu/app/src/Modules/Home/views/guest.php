<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * EroCloud – Public guest landing page (https://erocloud.net/)
 *
 * Shown to visitors without an active MCP session. Presents EroCloud as the central
 * content backend of Pay4Coins for creators with their own EroCMS website.
 *
 * SEO / GEO (AI search) / Social sharing:
 *  - Unique title, meta description, canonical, hreflang, robots directives
 *  - Open Graph + Twitter Card (1200x630 share image: assets/img/og-erocloud.jpg)
 *  - JSON-LD @graph (Organization, WebSite, WebPage, SoftwareApplication, FAQPage)
 *  - Semantic HTML5 landmarks, strict h1 → h2 → h3 hierarchy, quotable definitions
 *  - Companion files in webroot: robots.txt, sitemap.xml, llms.txt
 *
 * Expected variables (via View::render): string $basePath, array $imprintData
 */

use App\Core\View;

$basePath    = isset($basePath) ? (string)$basePath : '';
$imprintData = isset($imprintData) && is_array($imprintData) ? $imprintData : [];

// --- Central URLs (verified targets on pay4coins.com) -----------------------------------------
$siteUrl       = rtrim(defined('URL') ? (string)URL : 'https://erocloud.net', '/');
$canonicalUrl  = $siteUrl . '/';
$loginUrl      = (defined('LOGIN_URL') ? (string)LOGIN_URL : 'https://login.pay4coins.com') . '?ref=erocloud';
$p4cUrl        = defined('Pay4Coins_URL') ? (string)Pay4Coins_URL : 'https://pay4coins.com';
$erocmsUrl     = $p4cUrl . '/de/Betreiben-Sie-Ihre-ganz-private-Erotikwebsite-mit-EroCMS';
$erocmsNetUrl  = 'https://erocms.net';
$partnerUrl    = (defined('Pay4Coins_MCP_URL') ? (string)Pay4Coins_MCP_URL : 'https://merchant.pay4coins.com') . '/Partner-werden';
$privacyUrl    = $p4cUrl . '/de/datenschutz';
$imprintUrl    = (string)($imprintData['imprint_external_url'] ?? ($p4cUrl . '/de/impressum'));

// --- Company & contact data (central settings table with fallbacks) ---------------------------
$companyName   = (string)($imprintData['imprint_company_name'] ?? (defined('COMPANYNAME') ? COMPANYNAME : 'CIPA Media S.L.U.'));
$addressStreet = (string)($imprintData['imprint_address_street'] ?? 'Av. Emilio Luque Moreno 19 Oficina 6');
$addressCity   = (string)($imprintData['imprint_address_city'] ?? 'ES-38300 La Orotava (S/C de Tenerife)');
$vatId         = (string)($imprintData['imprint_vat_id'] ?? 'CIF: B76659168');
$supportEmail  = (string)($imprintData['imprint_support_email'] ?? (defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@pay4coins.com'));
$supportPhone  = (string)($imprintData['imprint_phone'] ?? (defined('SUPPORT_PHONE_NUMBER') ? SUPPORT_PHONE_NUMBER : ''));
$whatsappUrl   = (string)($imprintData['imprint_whatsapp_url'] ?? '');

// Split "ES-38300 La Orotava (S/C de Tenerife)" into structured address parts for JSON-LD
$postalCode = '';
$locality   = $addressCity;
if (preg_match('/^(?:[A-Z]{2}-)?(\d{4,5})\s+(.+)$/u', $addressCity, $addrMatch)) {
    $postalCode = $addrMatch[1];
    $locality   = trim((string)preg_replace('/\s*\(.*\)\s*$/u', '', $addrMatch[2]));
}

// --- Meta data -------------------------------------------------------------------------------
$metaTitle       = 'EroCloud – Die Content-Cloud für deine eigene EroCMS-Website | Pay4Coins';
$metaDescription = 'Lade Filme & Fotoalben einmal in die EroCloud hoch und verkaufe sie automatisch auf deiner eigenen EroCMS-Erotikwebsite. Hosting, Streaming, ID-Check & Payment von Pay4Coins.';
$ogTitle         = 'EroCloud – Deine zentrale Creator-Cloud für deine eigene Website';
$ogDescription   = 'Content einmal hochladen, automatisch auf deiner eigenen EroCMS-Website verkaufen. Ein Service von Pay4Coins.';
$ogImagePath     = '/assets/img/og-erocloud.jpg';
$ogImageUrl      = $siteUrl . $ogImagePath;
$ogImageVersion  = (int)@filemtime(dirname(__DIR__, 5) . '/web' . $ogImagePath);
if ($ogImageVersion > 0) {
    $ogImageUrl .= '?v=' . $ogImageVersion;
}

$assetVersion = static function (string $relPath): int {
    return (int)@filemtime(dirname(__DIR__, 5) . '/web/' . ltrim($relPath, '/'));
};

/** Renders the Pay4Coins word mark in its familiar style (blue "4"). */
$p4cBrand = static function (string $extraClass = ''): string {
    return '<span class="p4c-brand' . ($extraClass !== '' ? ' ' . View::e($extraClass) : '') . '">pay<span class="p4c-brand-4">4</span><span class="p4c-brand-coins">coins</span></span>';
};

// --- FAQ (single source of truth for visible accordion + FAQPage JSON-LD) ---------------------
$faqItems = [
    [
        'q' => 'Was ist die EroCloud?',
        'a' => 'Die EroCloud ist das zentrale Content-Backend von Pay4Coins für Creator. Du lädst dort deine Filme und Fotoalben hoch, pflegst deine Darsteller-Profile und verifizierst deine Drehpartner. Deine eigene EroCMS-Website übernimmt diese Inhalte anschließend automatisch über deinen persönlichen API-Key.',
    ],
    [
        'q' => 'Brauche ich eine eigene EroCMS-Website, um die EroCloud zu nutzen?',
        'a' => 'Die EroCloud ist genau auf das Zusammenspiel mit EroCMS ausgelegt: Deine Inhalte werden zentral in der Cloud verwaltet und auf deiner eigenen EroCMS-Website verkauft. Falls du noch keine eigene Website hast, richtet dir Pay4Coins ein schlüsselfertiges Erotik- oder Fetischportal unter deiner Wunschdomain ein.',
    ],
    [
        'q' => 'Wie bekomme ich Zugang zur EroCloud?',
        'a' => 'Du brauchst ein Partnerkonto bei Pay4Coins. Mit deinen Pay4Coins-Zugangsdaten loggst du dich anschließend direkt in die EroCloud ein – ein separates Passwort ist nicht nötig.',
    ],
    [
        'q' => 'Muss ich meine Videos auf meinem eigenen Webspace speichern?',
        'a' => 'Nein. Deine Videodateien bleiben sicher in der EroCloud und werden beim Abruf direkt von dort an deine Kunden gestreamt oder zum Download ausgeliefert – über zeitlich begrenzte, geschützte Zugangslinks. Deine Website muss weder Speicherplatz noch Video-Traffic stemmen.',
    ],
    [
        'q' => 'Welche Videoformate kann ich hochladen?',
        'a' => 'Du kannst Filme in den Formaten MP4, MOV, MKV, AVI, WMV, M4V, MPG und FLV hochladen. Die EroCloud konvertiert sie anschließend automatisch. Für das beste Ergebnis empfehlen wir MP4 mit H.264 in Full-HD (1080p).',
    ],
    [
        'q' => 'Warum muss ich meine Drehpartner verifizieren?',
        'a' => 'Für jede Person, die in deinem Content zu sehen ist, muss nachweisbar sein, dass sie volljährig ist und der Veröffentlichung zugestimmt hat. In der EroCloud erledigst du das digital: Deine Drehpartner scannen einen QR-Code, fotografieren ihren Ausweis mit dem Smartphone und unterschreiben direkt auf dem Display. Daraus entsteht automatisch ein Einverständnis-Zertifikat.',
    ],
    [
        'q' => 'Wo sehe ich meine Umsätze und Auszahlungen?',
        'a' => 'Deine Umsätze und Abrechnungen findest du direkt in deinem Pay4Coins-Account bzw. in deiner EroCMS-Website. Die EroCloud selbst konzentriert sich bewusst auf die Verwaltung deines Contents und deiner Profile.',
    ],
];

// --- JSON-LD structured data ------------------------------------------------------------------
$orgId      = $siteUrl . '/#organization';
$p4cId      = $siteUrl . '/#pay4coins';
$websiteId  = $siteUrl . '/#website';
$webpageId  = $canonicalUrl . '#webpage';
$softwareId = $siteUrl . '/#erocloud';
$erocmsId   = $siteUrl . '/#erocms';

$orgAddress = [
    '@type'           => 'PostalAddress',
    'streetAddress'   => $addressStreet,
    'addressLocality' => $locality,
    'addressRegion'   => 'Santa Cruz de Tenerife',
    'addressCountry'  => 'ES',
];
if ($postalCode !== '') {
    $orgAddress['postalCode'] = $postalCode;
}

$faqEntities = [];
foreach ($faqItems as $faq) {
    $faqEntities[] = [
        '@type'          => 'Question',
        'name'           => $faq['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
    ];
}

$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        array_filter([
            '@type'     => 'Organization',
            '@id'       => $orgId,
            'name'      => $companyName,
            'legalName' => $companyName,
            'url'       => $p4cUrl,
            'email'     => $supportEmail,
            'telephone' => $supportPhone !== '' ? $supportPhone : null,
            'taxID'     => trim((string)preg_replace('/^CIF:\s*/i', '', $vatId)),
            'address'   => $orgAddress,
            'brand'     => [['@id' => $p4cId], ['@id' => $softwareId]],
        ], static fn($v) => $v !== null && $v !== ''),
        [
            '@type'              => 'Organization',
            '@id'                => $p4cId,
            'name'               => 'Pay4Coins',
            'url'                => $p4cUrl,
            'description'        => 'Spezialisierter Zahlungs- und Plattformdienstleister für Creator, Betreiber von EroCMS-Websites und die EroCloud.',
            'parentOrganization' => ['@id' => $orgId],
        ],
        [
            '@type'       => 'WebSite',
            '@id'         => $websiteId,
            'url'         => $canonicalUrl,
            'name'        => 'EroCloud',
            'description' => $metaDescription,
            'inLanguage'  => 'de-DE',
            'publisher'   => ['@id' => $orgId],
        ],
        [
            '@type'              => 'WebPage',
            '@id'                => $webpageId,
            'url'                => $canonicalUrl,
            'name'               => $metaTitle,
            'description'        => $metaDescription,
            'inLanguage'         => 'de-DE',
            'isPartOf'           => ['@id' => $websiteId],
            'about'              => ['@id' => $softwareId],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $ogImageUrl, 'width' => 1200, 'height' => 630],
        ],
        [
            '@type'               => 'WebApplication',
            '@id'                 => $softwareId,
            'name'                => 'EroCloud',
            'url'                 => $canonicalUrl,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem'     => 'Web',
            'inLanguage'          => 'de-DE',
            'description'         => 'Zentrale Content-Cloud für Creator: Upload, Konvertierung, Hosting und geschütztes Streaming von Filmen und Fotoalben, Darsteller-Profile sowie digitaler Drehpartner-ID-Check. Inhalte werden per API-Key automatisch auf die eigene EroCMS-Website übertragen.',
            'image'               => $ogImageUrl,
            'provider'            => ['@id' => $p4cId],
            'audience'            => ['@type' => 'BusinessAudience', 'audienceType' => 'Content Creator, Produzenten und Betreiber eigener Erotik-Websites'],
            'featureList'         => [
                'Zentraler Upload für Filme und Fotoalben',
                'Automatische Videokonvertierung und Streaming bis 4K',
                'Cover-Studio für FSK16- und FSK18-Cover',
                'Digitaler Drehpartner- und ID-Check per QR-Code mit Einverständnis-Zertifikat',
                'Automatische Kategorievorschläge und SEO-Felder',
                'Geplante Veröffentlichungen',
                'Automatischer Import auf die eigene EroCMS-Website per API-Key',
                'Single-Sign-On mit dem Pay4Coins-Partnerkonto',
            ],
            'isRelatedTo'         => ['@id' => $erocmsId],
        ],
        [
            '@type'               => 'SoftwareApplication',
            '@id'                 => $erocmsId,
            'name'                => 'EroCMS',
            'url'                 => $erocmsUrl,
            'sameAs'              => [$erocmsNetUrl],
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem'     => 'Web',
            'description'         => 'Content-Management-System für die eigene, schlüsselfertige Erotik- oder Fetisch-Website mit Videothek, Fotoalben, Messenger, Webcam-Chat und Shop.',
        ],
        [
            '@type'      => 'FAQPage',
            '@id'        => $canonicalUrl . '#faq',
            'isPartOf'   => ['@id' => $webpageId],
            'inLanguage' => 'de-DE',
            'mainEntity' => $faqEntities,
        ],
    ],
];
$jsonLdOutput = (string)json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

$b = View::e($basePath);
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= View::e($metaTitle) ?></title>
  <meta name="description" content="<?= View::e($metaDescription) ?>">
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
  <meta name="author" content="<?= View::e($companyName) ?>">
  <meta name="application-name" content="EroCloud">
  <meta name="theme-color" content="#0284C7" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0B0F19" media="(prefers-color-scheme: dark)">
  <meta name="color-scheme" content="light dark">
  <meta name="format-detection" content="telephone=no">
  <link rel="canonical" href="<?= View::e($canonicalUrl) ?>">
  <link rel="alternate" hreflang="de" href="<?= View::e($canonicalUrl) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= View::e($canonicalUrl) ?>">
  <link rel="alternate" type="text/plain" title="LLM-Zusammenfassung" href="<?= View::e($siteUrl) ?>/llms.txt">

  <!-- Open Graph (Facebook, WhatsApp, Telegram, LinkedIn, Signal, iMessage …) -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="EroCloud">
  <meta property="og:locale" content="de_DE">
  <meta property="og:url" content="<?= View::e($canonicalUrl) ?>">
  <meta property="og:title" content="<?= View::e($ogTitle) ?>">
  <meta property="og:description" content="<?= View::e($ogDescription) ?>">
  <meta property="og:image" content="<?= View::e($ogImageUrl) ?>">
  <meta property="og:image:secure_url" content="<?= View::e($ogImageUrl) ?>">
  <meta property="og:image:type" content="image/jpeg">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="EroCloud – Deine zentrale Creator-Cloud für deine eigene EroCMS-Website. Ein Service von Pay4Coins.">

  <!-- Twitter / X Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= View::e($ogTitle) ?>">
  <meta name="twitter:description" content="<?= View::e($ogDescription) ?>">
  <meta name="twitter:image" content="<?= View::e($ogImageUrl) ?>">
  <meta name="twitter:image:alt" content="EroCloud – Deine zentrale Creator-Cloud für deine eigene EroCMS-Website.">

  <!-- Icons -->
  <link rel="icon" type="image/x-icon" href="<?= $b ?>/assets/img/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= $b ?>/assets/img/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= $b ?>/assets/img/favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="96x96" href="<?= $b ?>/assets/img/favicon-96x96.png">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= $b ?>/assets/img/apple-touch-icon.png">

  <!-- Local assets only (GDPR / no external CDNs) -->
  <link rel="preload" href="<?= $b ?>/assets/fonts/montserrat-700.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="<?= $b ?>/assets/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= $b ?>/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= $b ?>/assets/css/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= $b ?>/assets/css/fonts.css">
  <link rel="stylesheet" href="<?= $b ?>/assets/css/landing.css?v=<?= $assetVersion('assets/css/landing.css') ?>">

  <script>
    (function () {
      var t = 'light';
      try { t = localStorage.getItem('ec-mcp-theme') || 'light'; } catch (e) {}
      document.documentElement.setAttribute('data-bs-theme', t);
    })();
  </script>

  <script type="application/ld+json"><?= $jsonLdOutput ?></script>
</head>
<body class="lp-body">

<a class="visually-hidden-focusable lp-skip-link" href="#inhalt">Direkt zum Inhalt springen</a>

<!-- ======================================================================
     HEADER / NAVIGATION
     ====================================================================== -->
<header class="lp-header" id="lp-header">
  <nav class="navbar navbar-expand-lg py-0" aria-label="Hauptnavigation">
    <div class="container-xl lp-header-inner">
      <a class="navbar-brand lp-brand" href="<?= View::e($canonicalUrl) ?>" aria-label="EroCloud Startseite">
        <img src="<?= $b ?>/assets/img/erocloud_logo.png" alt="EroCloud Logo" width="52" height="40" decoding="async">
        <span class="lp-brand-text">
          <span class="lp-brand-name">EroCloud</span>
          <span class="lp-brand-sub">ein Service von <?= $p4cBrand() ?></span>
        </span>
      </a>

      <div class="d-flex align-items-center gap-2 order-lg-3">
        <button type="button" class="lp-icon-btn" id="lp-theme-toggle" aria-label="Hell-/Dunkel-Design umschalten" title="Design wechseln">
          <i class="bi bi-moon-stars-fill" id="lp-theme-icon" aria-hidden="true"></i>
        </button>
        <a href="<?= View::e($loginUrl) ?>" class="btn lp-btn lp-btn-primary lp-btn-sm d-none d-sm-inline-flex" rel="nofollow">
          <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Creator-Login
        </a>
        <button class="lp-icon-btn d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#lp-nav" aria-controls="lp-nav" aria-expanded="false" aria-label="Menü öffnen">
          <i class="bi bi-list" aria-hidden="true"></i>
        </button>
      </div>

      <div class="collapse navbar-collapse order-lg-2" id="lp-nav">
        <ul class="navbar-nav mx-lg-auto lp-nav">
          <li class="nav-item"><a class="nav-link" href="#prinzip">Das Prinzip</a></li>
          <li class="nav-item"><a class="nav-link" href="#funktionen">Funktionen</a></li>
          <li class="nav-item"><a class="nav-link" href="#erocms">Deine Website</a></li>
          <li class="nav-item"><a class="nav-link" href="#pay4coins">Pay4Coins</a></li>
          <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
          <li class="nav-item d-sm-none mt-2 mb-3">
            <a href="<?= View::e($loginUrl) ?>" class="btn lp-btn lp-btn-primary w-100" rel="nofollow">
              <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Creator-Login
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</header>

<main id="inhalt">

  <!-- ====================================================================
       HERO
       ==================================================================== -->
  <section class="lp-hero" aria-labelledby="lp-hero-title">
    <div class="lp-hero-bg" aria-hidden="true"></div>
    <div class="container-xl position-relative">
      <div class="row align-items-center g-5">
        <div class="col-lg-6">
          <p class="lp-eyebrow lp-reveal">
            <i class="bi bi-stars" aria-hidden="true"></i>
            Ein Service von <?= $p4cBrand() ?> · für Creator mit eigener Website
          </p>
          <h1 id="lp-hero-title" class="lp-hero-title lp-reveal">
            <span class="lp-hero-kicker">EroCloud</span>
            Lade deinen Content <span class="lp-gradient-text">einmal</span> hoch –
            und verkaufe ihn auf deiner <span class="lp-gradient-text">eigenen Website</span>.
          </h1>
          <p class="lp-lead lp-reveal">
            Die EroCloud ist das zentrale Content-Backend von Pay4Coins: Du verwaltest deine Filme,
            Fotoalben und Darsteller-Profile an einem Ort – und deine persönliche
            <strong>EroCMS-Website</strong> übernimmt alles automatisch.
          </p>
          <div class="d-flex flex-wrap gap-3 lp-reveal">
            <a href="<?= View::e($erocmsUrl) ?>" class="btn lp-btn lp-btn-primary lp-btn-lg" target="_blank" rel="noopener">
              <i class="bi bi-globe2" aria-hidden="true"></i> Eigene Website mit EroCMS starten
            </a>
            <a href="<?= View::e($loginUrl) ?>" class="btn lp-btn lp-btn-ghost lp-btn-lg" rel="nofollow">
              <i class="bi bi-person-circle" aria-hidden="true"></i> Zum Creator-Login
            </a>
          </div>
          <ul class="lp-trust lp-reveal" aria-label="Deine Vorteile auf einen Blick">
            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Ein Login für alles</li>
            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Hosting &amp; Streaming bis 4K</li>
            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Diskretes Payment</li>
          </ul>
        </div>

        <!-- Visual: content flow Upload → EroCloud → EroCMS website + Pay4Coins -->
        <div class="col-lg-6">
          <figure class="lp-flow lp-reveal" aria-label="So fließt dein Content: Upload, EroCloud, deine EroCMS-Website und Pay4Coins">
            <div class="lp-flow-card lp-flow-upload">
              <span class="lp-flow-icon lp-flow-icon-muted"><i class="bi bi-cloud-arrow-up-fill" aria-hidden="true"></i></span>
              <div>
                <div class="lp-flow-title">Dein Upload</div>
                <div class="lp-flow-meta">Filme · Fotoalben · Profile</div>
              </div>
              <span class="lp-flow-pill"><i class="bi bi-film" aria-hidden="true"></i> 4K</span>
            </div>

            <div class="lp-flow-connector" aria-hidden="true"><span></span></div>

            <div class="lp-flow-card lp-flow-core">
              <img src="<?= $b ?>/assets/img/erocloud_logo.png" alt="" width="64" height="49" loading="lazy" decoding="async">
              <div class="flex-grow-1">
                <div class="lp-flow-title">EroCloud</div>
                <div class="lp-flow-tags">
                  <span>Konvertierung</span><span>Hosting</span><span>Streaming</span><span>ID-Check</span>
                </div>
              </div>
              <span class="lp-flow-status"><span class="lp-dot"></span> live</span>
            </div>

            <div class="lp-flow-split" aria-hidden="true"><span></span><span></span></div>

            <div class="row g-3">
              <div class="col-sm-7">
                <div class="lp-flow-card lp-flow-site h-100">
                  <div class="lp-browser-bar" aria-hidden="true">
                    <span></span><span></span><span></span>
                    <div class="lp-browser-url"><i class="bi bi-lock-fill"></i> deine-domain.de</div>
                  </div>
                  <div class="lp-flow-title mt-2">Deine EroCMS-Website</div>
                  <div class="lp-site-grid" aria-hidden="true">
                    <i></i><i></i><i></i><i></i><i></i><i></i>
                  </div>
                  <div class="lp-flow-meta">Automatisch synchronisiert per API-Key</div>
                </div>
              </div>
              <div class="col-sm-5">
                <div class="lp-flow-card lp-flow-pay h-100">
                  <div class="lp-flow-p4c"><?= $p4cBrand('fs-5') ?></div>
                  <ul class="lp-flow-list">
                    <li><i class="bi bi-credit-card-2-front" aria-hidden="true"></i> Payment</li>
                    <li><i class="bi bi-wallet2" aria-hidden="true"></i> Auszahlung</li>
                    <li><i class="bi bi-key" aria-hidden="true"></i> Login</li>
                  </ul>
                </div>
              </div>
            </div>
            <figcaption class="visually-hidden">
              Du lädst deine Filme und Fotoalben in die EroCloud hoch. Die EroCloud konvertiert, hostet und streamt sie,
              deine EroCMS-Website zeigt sie automatisch an und Pay4Coins übernimmt Payment, Auszahlung und Login.
            </figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ====================================================================
       DAS PRINZIP – Was ist die EroCloud? (definition block for SEO & AI)
       ==================================================================== -->
  <section class="lp-section" id="prinzip" aria-labelledby="lp-prinzip-title">
    <div class="container-xl">
      <div class="lp-section-head lp-reveal">
        <p class="lp-section-label">Das Prinzip</p>
        <h2 id="lp-prinzip-title" class="lp-h2">Drei Bausteine, ein System für deinen Erfolg</h2>
        <p class="lp-section-intro">
          <strong>Die EroCloud ist die zentrale Content-Cloud von Pay4Coins für Creator.</strong>
          Sie verbindet die Verwaltung deiner Inhalte mit deiner eigenen EroCMS-Website und der
          Zahlungsabwicklung von Pay4Coins – so konzentrierst du dich auf deinen Content, nicht auf Technik.
        </p>
      </div>

      <div class="row g-4">
        <div class="col-md-4">
          <article class="lp-card lp-card-product lp-reveal">
            <div class="lp-card-icon lp-icon-cloud"><i class="bi bi-cloud-fill" aria-hidden="true"></i></div>
            <h3 class="lp-h3">EroCloud</h3>
            <p class="lp-card-sub">Dein zentrales Content-Backend</p>
            <p>
              Hier lädst du deine Filme und Fotoalben hoch, pflegst deine Darsteller-Profile und
              verifizierst deine Drehpartner. Die Cloud konvertiert, speichert und streamt deine Videos –
              deine Website muss weder Speicherplatz noch Video-Traffic stemmen.
            </p>
          </article>
        </div>
        <div class="col-md-4">
          <article class="lp-card lp-card-product lp-reveal">
            <div class="lp-card-icon lp-icon-site"><i class="bi bi-window-sidebar" aria-hidden="true"></i></div>
            <h3 class="lp-h3">EroCMS</h3>
            <p class="lp-card-sub">Deine eigene Erotik-Website</p>
            <p>
              Deine schlüsselfertige Website unter deiner Wunschdomain – mit 100&nbsp;% Kontrolle über deine Marke.
              Über deinen persönlichen API-Key holt sie sich deine Inhalte samt Covern, Preisen und SEO-Texten
              automatisch aus der EroCloud.
            </p>
            <a class="lp-link" href="<?= View::e($erocmsUrl) ?>" target="_blank" rel="noopener">EroCMS entdecken <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
          </article>
        </div>
        <div class="col-md-4">
          <article class="lp-card lp-card-product lp-reveal">
            <div class="lp-card-icon lp-icon-pay"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i></div>
            <h3 class="lp-h3">Pay4Coins</h3>
            <p class="lp-card-sub">Payment, Auszahlung &amp; Login</p>
            <p>
              Pay4Coins wickelt die Zahlungen deiner Kunden sicher und diskret ab, kümmert sich um deine
              Auszahlungen und ist dein Schlüssel zu allem: Mit einem Partnerkonto loggst du dich auch
              direkt in die EroCloud ein.
            </p>
            <a class="lp-link" href="<?= View::e($p4cUrl) ?>" target="_blank" rel="noopener">Zu Pay4Coins <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
          </article>
        </div>
      </div>
    </div>
  </section>

  <!-- ====================================================================
       SO FUNKTIONIERT'S – 4 steps
       ==================================================================== -->
  <section class="lp-section lp-section-alt" id="ablauf" aria-labelledby="lp-ablauf-title">
    <div class="container-xl">
      <div class="lp-section-head lp-reveal">
        <p class="lp-section-label">So funktioniert's</p>
        <h2 id="lp-ablauf-title" class="lp-h2">In vier Schritten zu deiner eigenen Creator-Website</h2>
      </div>
      <ol class="lp-steps">
        <li class="lp-step lp-reveal">
          <span class="lp-step-num" aria-hidden="true">1</span>
          <h3 class="lp-h3">Partner werden</h3>
          <p>Du eröffnest dein Partnerkonto bei Pay4Coins und legitimierst dich einmalig.</p>
        </li>
        <li class="lp-step lp-reveal">
          <span class="lp-step-num" aria-hidden="true">2</span>
          <h3 class="lp-h3">Website einrichten lassen</h3>
          <p>Pay4Coins installiert und konfiguriert deine EroCMS-Website unter deiner Wunschdomain.</p>
        </li>
        <li class="lp-step lp-reveal">
          <span class="lp-step-num" aria-hidden="true">3</span>
          <h3 class="lp-h3">Content hochladen</h3>
          <p>Du legst dein Profil an, verifizierst deine Drehpartner und lädst Filme &amp; Fotoalben in die EroCloud.</p>
        </li>
        <li class="lp-step lp-reveal">
          <span class="lp-step-num" aria-hidden="true">4</span>
          <h3 class="lp-h3">Automatisch online</h3>
          <p>Nach der Freigabe erscheint dein Content auf deiner Website – Pay4Coins übernimmt das Payment.</p>
        </li>
      </ol>
    </div>
  </section>

  <!-- ====================================================================
       FUNKTIONEN
       ==================================================================== -->
  <section class="lp-section" id="funktionen" aria-labelledby="lp-funktionen-title">
    <div class="container-xl">
      <div class="lp-section-head lp-reveal">
        <p class="lp-section-label">Funktionen</p>
        <h2 id="lp-funktionen-title" class="lp-h2">Was dir die EroCloud im Alltag abnimmt</h2>
        <p class="lp-section-intro">Alles, was du brauchst, um deinen Content professionell zu verwalten – übersichtlich in einem Partner-Bereich.</p>
      </div>

      <div class="row g-4">
        <?php
        $features = [
            ['bi-cloud-arrow-up-fill', 'Zentraler Upload', 'Filme und Fotoalben lädst du nur ein einziges Mal hoch – direkt im Browser, auch große Videodateien.'],
            ['bi-magic', 'Automatische Konvertierung', 'Die EroCloud bereitet deine Filme automatisch für alle Geräte auf und erzeugt Vorschau-Szenen – von SD bis 4K.'],
            ['bi-play-btn-fill', 'Geschütztes Streaming', 'Deine Videos bleiben in der Cloud und werden über zeitlich begrenzte Zugangslinks gestreamt oder zum Download ausgeliefert.'],
            ['bi-image-fill', 'Cover-Studio', 'Erstelle direkt im Browser jugendfreie FSK16-Cover für Vorschau &amp; Suchmaschinen sowie deine FSK18-Cover.'],
            ['bi-qr-code-scan', 'Digitaler ID-Check', 'Drehpartner verifizieren sich per QR-Code am Smartphone und unterschreiben auf dem Display – inklusive Einverständnis-Zertifikat.'],
            ['bi-tags-fill', 'Kategorien &amp; SEO', 'Smarte Kategorievorschläge, SEO-Titel, Beschreibungen und sprechende URLs pflegst du zentral an einem Ort.'],
            ['bi-calendar2-check-fill', 'Veröffentlichung planen', 'Lege fest, ab wann ein Film online geht – deine Website übernimmt das Release pünktlich und automatisch.'],
            ['bi-bell-fill', 'Status in Echtzeit', 'Du wirst per E-Mail informiert, sobald deine Uploads konvertiert, geprüft und startklar sind.'],
        ];
        foreach ($features as $feature):
        ?>
        <div class="col-sm-6 col-lg-3">
          <article class="lp-card lp-card-feature lp-reveal">
            <div class="lp-feature-icon"><i class="bi <?= View::e($feature[0]) ?>" aria-hidden="true"></i></div>
            <h3 class="lp-h3"><?= $feature[1] ?></h3>
            <p><?= $feature[2] ?></p>
          </article>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ====================================================================
       EROCMS – Deine eigene Website
       ==================================================================== -->
  <section class="lp-section lp-section-dark" id="erocms" aria-labelledby="lp-erocms-title">
    <div class="lp-dark-glow" aria-hidden="true"></div>
    <div class="container-xl position-relative">
      <div class="row align-items-center g-5">
        <div class="col-lg-6">
          <p class="lp-section-label lp-label-light lp-reveal">EroCMS · Deine eigene Website</p>
          <h2 id="lp-erocms-title" class="lp-h2 text-white lp-reveal">Mach dich unabhängig von fremden Plattformen</h2>
          <p class="lp-dark-lead lp-reveal">
            Mit EroCMS verkaufst du Videos, Fotoalben und Downloads auf deiner <strong>eigenen Domain</strong> –
            ohne hohe Abgaben an große Plattformen, ohne Konkurrenz neben deinem Content. Pay4Coins installiert und
            konfiguriert dein schlüsselfertiges Erotik- oder Fetischportal und kümmert sich um Technik,
            Hosting und diskretes Payment.
          </p>

          <ul class="lp-module-list lp-reveal" aria-label="EroCMS-Module">
            <li><i class="bi bi-collection-play-fill" aria-hidden="true"></i> Videothek (Streaming &amp; Download)</li>
            <li><i class="bi bi-images" aria-hidden="true"></i> Exklusive Fotoalben</li>
            <li><i class="bi bi-chat-heart-fill" aria-hidden="true"></i> Live-Messenger</li>
            <li><i class="bi bi-camera-video-fill" aria-hidden="true"></i> Webcam-Chat (Cam2Cam)</li>
            <li><i class="bi bi-bag-heart-fill" aria-hidden="true"></i> Eigener Shop für Fan-Artikel</li>
            <li><i class="bi bi-envelope-paper-heart-fill" aria-hidden="true"></i> Newsletter &amp; Gutscheine</li>
          </ul>

          <div class="d-flex flex-wrap gap-3 lp-reveal">
            <a href="<?= View::e($erocmsUrl) ?>" class="btn lp-btn lp-btn-light lp-btn-lg" target="_blank" rel="noopener">
              Alle Details zu EroCMS ansehen <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </a>
            <a href="<?= View::e($erocmsNetUrl) ?>" class="btn lp-btn lp-btn-outline-light lp-btn-lg" target="_blank" rel="noopener">
              EroCMS.net
            </a>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="lp-service-card lp-reveal">
            <h3 class="lp-h3 text-white mb-3"><i class="bi bi-gem me-2" aria-hidden="true"></i>Full-Service inklusive</h3>
            <ul class="lp-service-list">
              <li>
                <i class="bi bi-whatsapp" aria-hidden="true"></i>
                <div><strong>Persönlicher Support</strong><span>per WhatsApp &amp; E-Mail</span></div>
              </li>
              <li>
                <i class="bi bi-hdd-network-fill" aria-hidden="true"></i>
                <div><strong>Managed High-Speed-Hosting</strong><span>4K-ready, ohne eigenen Server</span></div>
              </li>
              <li>
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                <div><strong>Kostenlose Plattform-Updates</strong><span>immer auf dem neuesten Stand</span></div>
              </li>
              <li>
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                <div><strong>Datenschutz &amp; tägliche Backups</strong><span>deine Daten in sicheren Händen</span></div>
              </li>
              <li>
                <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                <div><strong>Marketing &amp; SEO</strong><span>Unterstützung für mehr Reichweite</span></div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ====================================================================
       PAY4COINS
       ==================================================================== -->
  <section class="lp-section" id="pay4coins" aria-labelledby="lp-p4c-title">
    <div class="container-xl">
      <div class="row align-items-center g-5">
        <div class="col-lg-5 order-lg-2">
          <div class="lp-p4c-panel lp-reveal">
            <div class="lp-p4c-logo"><?= $p4cBrand() ?></div>
            <p class="lp-p4c-claim">Payment · Auszahlung · Login</p>
            <div class="lp-p4c-methods" aria-label="Beispiele für Zahlungsarten">
              <span><i class="bi bi-credit-card" aria-hidden="true"></i> Kreditkarte</span>
              <span><i class="bi bi-bank" aria-hidden="true"></i> SEPA</span>
              <span><i class="bi bi-upc-scan" aria-hidden="true"></i> paysafecard</span>
              <span><i class="bi bi-currency-bitcoin" aria-hidden="true"></i> Bitcoin</span>
              <span><i class="bi bi-three-dots" aria-hidden="true"></i> und mehr</span>
            </div>
          </div>
        </div>
        <div class="col-lg-7 order-lg-1">
          <p class="lp-section-label lp-reveal">Pay4Coins</p>
          <h2 id="lp-p4c-title" class="lp-h2 lp-reveal">Zahlungsabwicklung und Zugang aus einer Hand</h2>
          <p class="lp-section-intro text-start mx-0 lp-reveal">
            Hinter EroCloud und EroCMS steht Pay4Coins – der spezialisierte Zahlungsdienstleister für Creator
            und Betreiber eigener Erotik-Websites.
          </p>
          <div class="row g-3 mt-1">
            <div class="col-sm-6">
              <div class="lp-mini lp-reveal">
                <i class="bi bi-key-fill" aria-hidden="true"></i>
                <div><h3 class="lp-h4">Ein Konto für alles</h3><p>Mit deinem Pay4Coins-Partnerkonto loggst du dich direkt in die EroCloud ein.</p></div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="lp-mini lp-reveal">
                <i class="bi bi-incognito" aria-hidden="true"></i>
                <div><h3 class="lp-h4">Diskret für deine Fans</h3><p>Sichere, diskrete Bezahlung mit zahlreichen gängigen Zahlungsarten.</p></div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="lp-mini lp-reveal">
                <i class="bi bi-receipt" aria-hidden="true"></i>
                <div><h3 class="lp-h4">Steuern erledigt</h3><p>Payouts werden netto ausgewiesen, die Mehrwertsteuer führt Pay4Coins für dich ab.</p></div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="lp-mini lp-reveal">
                <i class="bi bi-bar-chart-line-fill" aria-hidden="true"></i>
                <div><h3 class="lp-h4">Abrechnung im Blick</h3><p>Umsätze und Auszahlungen siehst du jederzeit in deinem Pay4Coins-Account.</p></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ====================================================================
       FAQ
       ==================================================================== -->
  <section class="lp-section lp-section-alt" id="faq" aria-labelledby="lp-faq-title">
    <div class="container-xl">
      <div class="row g-5">
        <div class="col-lg-4">
          <div class="lp-faq-aside lp-reveal">
            <p class="lp-section-label">FAQ</p>
            <h2 id="lp-faq-title" class="lp-h2">Häufige Fragen zur EroCloud</h2>
            <p class="text-body-secondary">Deine Frage ist nicht dabei? Unser Support-Team hilft dir gerne weiter.</p>
            <div class="d-flex flex-column gap-2 mt-3">
              <a class="lp-contact" href="mailto:<?= View::e($supportEmail) ?>">
                <i class="bi bi-envelope-fill" aria-hidden="true"></i> <?= View::e($supportEmail) ?>
              </a>
              <?php if ($whatsappUrl !== ''): ?>
              <a class="lp-contact" href="<?= View::e($whatsappUrl) ?>" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp-Support
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="accordion lp-accordion" id="lp-faq-accordion">
            <?php foreach ($faqItems as $i => $faq): ?>
            <div class="accordion-item lp-reveal">
              <h3 class="accordion-header" id="lp-faq-h<?= (int)$i ?>">
                <button class="accordion-button<?= $i === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse"
                        data-bs-target="#lp-faq-c<?= (int)$i ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="lp-faq-c<?= (int)$i ?>">
                  <?= View::e($faq['q']) ?>
                </button>
              </h3>
              <div id="lp-faq-c<?= (int)$i ?>" class="accordion-collapse collapse<?= $i === 0 ? ' show' : '' ?>" aria-labelledby="lp-faq-h<?= (int)$i ?>" data-bs-parent="#lp-faq-accordion">
                <div class="accordion-body"><?= View::e($faq['a']) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ====================================================================
       FINAL CTA
       ==================================================================== -->
  <section class="lp-section pt-0 lp-section-alt" aria-labelledby="lp-cta-title">
    <div class="container-xl">
      <div class="lp-cta lp-reveal">
        <div>
          <h2 id="lp-cta-title" class="lp-h2 text-white mb-2">Bereit für deine eigene Website?</h2>
          <p class="mb-0">Starte mit EroCMS und verwalte deinen gesamten Content bequem in deiner EroCloud.</p>
        </div>
        <div class="d-flex flex-wrap gap-3">
          <a href="<?= View::e($erocmsUrl) ?>" class="btn lp-btn lp-btn-light lp-btn-lg" target="_blank" rel="noopener">
            <i class="bi bi-rocket-takeoff-fill" aria-hidden="true"></i> Jetzt Website starten
          </a>
          <a href="<?= View::e($loginUrl) ?>" class="btn lp-btn lp-btn-outline-light lp-btn-lg" rel="nofollow">
            Ich bin schon Partner
          </a>
        </div>
      </div>
    </div>
  </section>

</main>

<!-- ======================================================================
     FOOTER
     ====================================================================== -->
<footer class="lp-footer">
  <div class="container-xl">
    <div class="row g-4">
      <div class="col-lg-4">
        <a href="<?= View::e($canonicalUrl) ?>" class="lp-brand mb-3 d-inline-flex" aria-label="EroCloud Startseite">
          <img src="<?= $b ?>/assets/img/erocloud_logo.png" alt="EroCloud Logo" width="52" height="40" loading="lazy" decoding="async">
          <span class="lp-brand-text">
            <span class="lp-brand-name">EroCloud</span>
            <span class="lp-brand-sub">ein Service von <?= $p4cBrand() ?></span>
          </span>
        </a>
        <p class="lp-footer-text">
          Die zentrale Content-Cloud für Creator mit eigener EroCMS-Website – Upload, Hosting, Streaming
          und ID-Check an einem Ort.
        </p>
      </div>
      <nav class="col-6 col-lg-2" aria-label="EroCloud">
        <h2 class="lp-footer-title">EroCloud</h2>
        <ul class="lp-footer-links">
          <li><a href="#prinzip">Das Prinzip</a></li>
          <li><a href="#funktionen">Funktionen</a></li>
          <li><a href="#faq">FAQ</a></li>
          <li><a href="<?= View::e($loginUrl) ?>" rel="nofollow">Creator-Login</a></li>
        </ul>
      </nav>
      <nav class="col-6 col-lg-2" aria-label="Pay4Coins &amp; EroCMS">
        <h2 class="lp-footer-title">Pay4Coins</h2>
        <ul class="lp-footer-links">
          <li><a href="<?= View::e($erocmsUrl) ?>" target="_blank" rel="noopener">EroCMS-Website</a></li>
          <li><a href="<?= View::e($partnerUrl) ?>" target="_blank" rel="noopener">Partner werden</a></li>
          <li><a href="<?= View::e($p4cUrl) ?>" target="_blank" rel="noopener">pay4coins.com</a></li>
          <li><a href="<?= View::e($erocmsNetUrl) ?>" target="_blank" rel="noopener">erocms.net</a></li>
        </ul>
      </nav>
      <div class="col-lg-4">
        <h2 class="lp-footer-title">Kontakt</h2>
        <address class="lp-footer-text mb-2">
          <strong><?= View::e($companyName) ?></strong><br>
          <?= View::e($addressStreet) ?><br>
          <?= View::e($addressCity) ?><br>
          <a href="mailto:<?= View::e($supportEmail) ?>"><?= View::e($supportEmail) ?></a>
        </address>
      </div>
    </div>
    <div class="lp-footer-bottom">
      <span>&copy; <?= (int)date('Y') ?> <?= View::e($companyName) ?> · EroCloud ist ein Service von Pay4Coins.</span>
      <span class="d-flex gap-3">
        <a href="<?= View::e($imprintUrl) ?>" target="_blank" rel="noopener">Impressum</a>
        <a href="<?= View::e($privacyUrl) ?>" target="_blank" rel="noopener">Datenschutz</a>
      </span>
    </div>
  </div>
</footer>

<script src="<?= $b ?>/assets/js/bootstrap.bundle.min.js" defer></script>
<script src="<?= $b ?>/assets/js/landing.js?v=<?= $assetVersion('assets/js/landing.js') ?>" defer></script>
</body>
</html>
