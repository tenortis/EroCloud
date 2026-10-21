<?php
declare(strict_types=1);

namespace App\Modules\Preview;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\Request;
use App\Core\View;

/**
 * EroCloud MCP - Statischer & Interaktiver Layout-Prototyp Controller
 * Liefert alle Menüansichten des aktuellen MCPs im neuen Kachel-Design (Pay4Coins-Blau) ohne Seiten-Reload.
 */
class PreviewController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function startseite(): string
    {
        return View::render('Preview', 'startseite');
    }

    public function guest(): string
    {
        return View::render('Preview', 'guest');
    }

    public function movieUpload(): string
    {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-cloud-arrow-up-fill',
            'badge'       => 'Content hochladen',
            'title'       => 'Film hochladen',
            'subtitle'    => 'Laden Sie Ihre Videodateien in 3 übersichtlichen Schritten in die EroCloud hoch.',
            'routeKey'    => 'movie-upload',
            'legacyRoute' => '/Movie-Upload',
        ]);
    }

    public function photoAlbumUpload(): string
    {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-file-earmark-image-fill',
            'badge'       => 'Content hochladen',
            'title'       => 'Fotos hochladen',
            'subtitle'    => 'Erstellen Sie ein neues Fotoalbum und laden Sie Ihre Bilder gesammelt hoch.',
            'routeKey'    => 'photo-album-upload',
            'legacyRoute' => '/Photo-Album-Upload',
        ]);
    }

    public function movies(): string
    {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-collection-play-fill',
            'badge'       => 'Content in der Cloud',
            'title'       => 'Filme in der Cloud',
            'subtitle'    => 'Verwalten Sie alle hochgeladenen Filme, Preise, Vorschau-Bilder und Freigabestatus.',
            'routeKey'    => 'movies',
            'legacyRoute' => '/Movies',
        ]);
    }

    public function movieDetail(): string
    {
        $id = abs((int)$this->request->get('id', 101));
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-film',
            'badge'       => 'Content in der Cloud',
            'title'       => 'Film #' . $id . ' bearbeiten',
            'subtitle'    => 'Detailansicht zur Bearbeitung von Titel, Beschreibung, Kategorien und Preisen Ihres Films.',
            'routeKey'    => 'movie',
            'legacyRoute' => '/video/' . $id,
        ]);
    }

    public function photoAlbums(): string
    {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-images',
            'badge'       => 'Content in der Cloud',
            'title'       => 'Fotos in der Cloud',
            'subtitle'    => 'Übersicht Ihrer gespeicherten Fotoalben inklusive Bildanzahl, Status und Verkaufspreisen.',
            'routeKey'    => 'photo-albums',
            'legacyRoute' => '/Photo-Albums',
        ]);
    }

    public function photoAlbumDetail(): string
    {
        $id = abs((int)$this->request->get('id', 1));
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-journal-richtext',
            'badge'       => 'Content in der Cloud',
            'title'       => 'Fotoalbum #' . $id . ' bearbeiten',
            'subtitle'    => 'Verwalten Sie die enthaltenen Fotos, Vorschau-Cover (FSK16 / FSK18) und Album-Details.',
            'routeKey'    => 'photo-album',
            'legacyRoute' => '/Photo-Album/' . $id,
        ]);
    }

    public function actors(): string
    {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-people-fill',
            'badge'       => 'Profile',
            'title'       => 'Alle Profile anzeigen',
            'subtitle'    => 'Verwalten Sie Ihre angelegten Darsteller-Profile, Freigabestatus und Steckbrief-Angaben.',
            'routeKey'    => 'actors',
            'legacyRoute' => '/Actors',
        ]);
    }

    public function newActor(): string
    {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-person-plus-fill',
            'badge'       => 'Profile',
            'title'       => 'Profil anlegen',
            'subtitle'    => 'Erstellen Sie ein neues Darsteller-Profil für die Vermarktung Ihrer Inhalte.',
            'routeKey'    => 'new-actor',
            'legacyRoute' => '/New-Actor',
        ]);
    }

    public function actorDetail(): string
    {
        $id = abs((int)$this->request->get('id', 1));
        $name = trim((string)$this->request->get('name', 'Darsteller-Profil #' . $id));
        return View::render('Preview', 'section_placeholder', [
            'icon'        => 'bi-person-badge-fill',
            'badge'       => 'Darsteller-Profil',
            'title'       => 'Profil: ' . $name,
            'subtitle'    => 'Bearbeiten Sie Profilbilder (FSK16 / FSK18), persönliche Merkmale und Über-mich-Texte.',
            'routeKey'    => 'actor',
            'legacyRoute' => '/Actor/' . $id,
        ]);
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
