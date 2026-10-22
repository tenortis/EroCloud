<?php
declare(strict_types=1);

namespace App\Modules\Preview;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\Request;
use App\Core\View;

/**
 * Controller für alle modularen Platzhalter-Ansichten im neuen EroCloud ACP (#/acp_neu),
 * die in den nächsten Schritten schrittweise mit der vollständigen Fachlogik ausgebaut werden.
 */
final class PreviewController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    private function renderPlaceholder(
        string $icon,
        string $badge,
        string $title,
        string $subtitle,
        string $routeKey,
        string $legacyRoute,
        string $legacyFile
    ): string {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => $icon,
            'badge'       => $badge,
            'title'       => $title,
            'subtitle'    => $subtitle,
            'routeKey'    => $routeKey,
            'legacyRoute' => $legacyRoute,
            'legacyFile'  => $legacyFile,
            'itemId'      => trim((string)$this->request->get('id', '')),
        ]);
    }

    // 2. Content prüfen (content_check)
    public function coActorsChecking(): string
    {
        return $this->renderPlaceholder(
            'bi-person-badge',
            'Content prüfen',
            'Drehpartner & ID-Check',
            'Prüfe eingereichte Ausweisdokumente, Selfies, Altersangaben und digitale Model-Release-Unterschriften.',
            'co-actors-checking',
            '/Drehpartner-pruefen',
            'acp/includes/co_actors_checking.php'
        );
    }

    public function aiCompliance(): string
    {
        return $this->renderPlaceholder(
            'bi-cpu',
            'Content prüfen',
            'KI-Analyse & BRAM',
            'Lokales KI-Testlabor, Whisper-Untertitel-Pipeline, Gesichtsabgleich und Visa/Mastercard BRAM-Compliance-Cockpit.',
            'ai-compliance',
            '/KI-Analyse',
            'acp/includes/ai_compliance.php'
        );
    }

    // 3. Content Online (content_online)
    public function contentCleanup(): string
    {
        return $this->renderPlaceholder(
            'bi-trash3',
            'Content Online',
            'Content-Bereinigung',
            'Verwalte abgelaufene Entwürfe, gelöschte Medien und den Lebenszyklus archivierter Inhalte.',
            'content-cleanup',
            '/Content-Bereinigung',
            'acp/includes/content_cleanup.php'
        );
    }

    public function storageAnalytics(): string
    {
        return $this->renderPlaceholder(
            'bi-hdd-network',
            'Content Online',
            'Speicher & Traffic',
            'Auswertung der Cloud-Storage-Belegung (/var/www/web1/cloud_storage) sowie Streaming- und Download-Traffic.',
            'storage-analytics',
            '/Speicher-Analyse',
            'acp/includes/storage_analytics.php'
        );
    }

    // 4. Profile (actors)
    public function actors(): string
    {
        return $this->renderPlaceholder(
            'bi-person-bounding-box',
            'Profile',
            'Darsteller-Profile',
            'Verwalte alle angelegten Darsteller-Profile, prüfe neue Profile und steuere Freischaltungen.',
            'actors',
            '/Actors',
            'acp/includes/actors.php'
        );
    }

    public function actor(): string
    {
        $id = abs((int)$this->request->get('id', 0));
        return $this->renderPlaceholder(
            'bi-person-vcard-fill',
            'Profile',
            'Darsteller-Profil Details' . ($id > 0 ? ' (#' . $id . ')' : ''),
            'Detailansicht und Bearbeitung eines Darsteller-Profils inkl. Profilbildern, Steckbrief und Status.',
            'actor' . ($id > 0 ? '?id=' . $id : ''),
            '/Actor/' . ($id > 0 ? $id : ''),
            'acp/includes/actor.php'
        );
    }

    // 5. Webseiten (websites)
    public function sites(): string
    {
        return $this->renderPlaceholder(
            'bi-globe2',
            'Webseiten',
            'Angeschlossene Webseiten (Sites)',
            'Übersicht aller an die EroCloud angebundenen Partner-Webseiten und EroCMS-Shops.',
            'sites',
            '/Sites',
            'acp/includes/sites.php'
        );
    }

    public function site(): string
    {
        $id = trim((string)$this->request->get('id', ''));
        return $this->renderPlaceholder(
            'bi-window-stack',
            'Webseiten',
            'Webseite Details' . ($id !== '' ? ' (' . $id . ')' : ''),
            'Detailansicht, API-Anbindung und zugewiesene Darsteller einer angeschlossenen Webseite.',
            'site' . ($id !== '' ? '?id=' . $id : ''),
            '/Site/' . $id,
            'acp/includes/site.php'
        );
    }

    // 6. Partnerprogramm (affiliate_program)
    public function webmasterPartner(): string
    {
        return $this->renderPlaceholder(
            'bi-people-fill',
            'Partnerprogramm',
            'Aktive Webmaster',
            'Übersicht und Verwaltung der aktiven Affiliate-Webmaster im Partnerprogramm.',
            'webmaster-partner',
            '/Webmaster/Partner',
            'acp/includes/webmaster_partner.php'
        );
    }

    public function banners(): string
    {
        return $this->renderPlaceholder(
            'bi-layers-fill',
            'Partnerprogramm',
            'Aktive Banner',
            'Verwalte alle freigeschalteten Werbebanner und Kampagnen-Mittel.',
            'banners',
            '/Banner',
            'acp/includes/banners.php'
        );
    }

    public function bannersNew(): string
    {
        return $this->renderPlaceholder(
            'bi-stars',
            'Partnerprogramm',
            'Neue Banner prüfen',
            'Prüfe und gib neu hochgeladene Werbebanner frei.',
            'banners-new',
            '/Neue-Banner',
            'acp/includes/banners_new.php'
        );
    }

    // 7. Webcams (webcams)
    public function webcams(): string
    {
        return $this->renderPlaceholder(
            'bi-camera-video-fill',
            'Webcams',
            'Aktive Webcams',
            'Live-Übersicht aller aktuell sendenden Darsteller-Webcams und Streamserver.',
            'webcams',
            '/Webcams',
            'acp/includes/webcams.php'
        );
    }

    // 8. P4C-Merchants (merchants)
    public function merchants(): string
    {
        return $this->renderPlaceholder(
            'bi-briefcase-fill',
            'P4C-Merchants',
            'Pay4Coins-Merchants (Händler)',
            'Übersicht aller verknüpften Pay4Coins-Partnerkonten, API-Keys, Profil-Limits und Umsätze.',
            'merchants',
            '/Haendler',
            'acp/includes/merchants.php'
        );
    }

    public function merchant(): string
    {
        $id = abs((int)$this->request->get('id', 0));
        return $this->renderPlaceholder(
            'bi-person-workspace',
            'P4C-Merchants',
            'Merchant Details' . ($id > 0 ? ' (MID #' . $id . ')' : ''),
            'Stammdaten, zugeordnete Darsteller-Profile, Mindestprofilanzahl und Historie dieses Pay4Coins-Partners.',
            'merchant' . ($id > 0 ? '?id=' . $id : ''),
            '/Haendler/' . ($id > 0 ? $id : ''),
            'acp/includes/merchant.php'
        );
    }

    // 9. Cloud-User (users)
    public function users(): string
    {
        return $this->renderPlaceholder(
            'bi-people',
            'Cloud-User',
            'Cloud-User (Mitglieder)',
            'Verwalte alle registrierten Endkunden über alle angeschlossenen Domains hinweg.',
            'users',
            '/User',
            'acp/includes/users.php'
        );
    }

    public function user(): string
    {
        $id = abs((int)$this->request->get('id', 0));
        return $this->renderPlaceholder(
            'bi-person-lines-fill',
            'Cloud-User',
            'Cloud-User Details' . ($id > 0 ? ' (#' . $id . ')' : ''),
            'Konto-Details, Coin-Guthaben, Transaktionen und Aktivitäten des ausgewählten Endkunden.',
            'user' . ($id > 0 ? '?id=' . $id : ''),
            '/User/' . ($id > 0 ? $id : ''),
            'acp/includes/user.php'
        );
    }

    // 10. Punktesystem (point_system)
    public function pointssystem(): string
    {
        return $this->renderPlaceholder(
            'bi-star-fill',
            'Punktesystem',
            'Punktesystem',
            'Konfiguration und Auswertung des Aktivitäts- und Punktesystems.',
            'pointssystem',
            '/Punktesystem',
            'acp/includes/pointssystem.php'
        );
    }

    // 11. System & Technik (error_log)
    public function systemSettings(): string
    {
        return $this->renderPlaceholder(
            'bi-sliders',
            'System & Technik',
            'Impressum & E-Mail',
            'Zentrale Verwaltung der Impressums- und Support-Stammdaten sowie E-Mail-Benachrichtigungs-Tests.',
            'system-settings',
            '/Einstellungen',
            'acp/includes/system_settings.php'
        );
    }

    public function errors(): string
    {
        return $this->renderPlaceholder(
            'bi-bug-fill',
            'System & Technik',
            'Zentrales PHP-Error-Log',
            'Echtzeit-Einsicht in das zentrale Fehlerprotokoll (/var/www/web1/htdocs/log/.errorlog) aller Systembereiche.',
            'errors',
            '/Errors',
            'acp/includes/errors.php'
        );
    }
}
