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
}
