<?php
declare(strict_types=1);

namespace App\Modules\Preview;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\Request;
use App\Core\View;

/**
 * Controller für die FAQ-Ansicht (#/faq) sowie das auslaufende Bestands-Partnerprogramm.
 */
final class PreviewController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function webmasterAds(): string
    {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-megaphone-fill',
            'badge'       => 'Partnerprogramm',
            'title'       => 'Kunden werben',
            'subtitle'    => 'Verwalten Sie Ihre Werbekampagnen und Tracking-Links zur Neukundengewinnung.',
            'routeKey'    => 'webmaster-ads',
            'legacyRoute' => '/Webmaster/Ads',
        ]);
    }

    public function webmasterNewAds(): string
    {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-layers-fill',
            'badge'       => 'Partnerprogramm',
            'title'       => 'Alle Werbebanner',
            'subtitle'    => 'Wählen Sie passende Werbemittel und Banner-Formate für Ihre Webseiten aus.',
            'routeKey'    => 'webmaster-newads',
            'legacyRoute' => '/Webmaster/NewAds',
        ]);
    }

    public function webmasterStats(): string
    {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-bar-chart-line-fill',
            'badge'       => 'Partnerprogramm',
            'title'       => 'Partnerprogramm-Statistiken',
            'subtitle'    => 'Auswertung Ihrer Klicks, Impressionen und geworbenen Kunden im Zeitverlauf.',
            'routeKey'    => 'webmaster-stats',
            'legacyRoute' => '/Webmaster/Statistics',
        ]);
    }

    public function webmasterAdvertisePartner(): string
    {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-person-heart',
            'badge'       => 'Partnerprogramm',
            'title'       => 'Darsteller werben',
            'subtitle'    => 'Ihr persönlicher Empfehlungslink zur Gewinnung neuer Darsteller und Produzenten.',
            'routeKey'    => 'webmaster-advertise-partner',
            'legacyRoute' => '/Webmaster/Advertise-Partner',
        ]);
    }

    public function faq(): string
    {
        return View::render('Preview', 'faq');
    }
}
