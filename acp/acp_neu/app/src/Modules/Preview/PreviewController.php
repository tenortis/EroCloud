<?php
declare(strict_types=1);

namespace App\Modules\Preview;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\Request;
use App\Core\View;

/**
 * Controller für Hinweis-Seiten noch nicht integrierter Bereiche im neuen EroCloud ACP (Webcams & Punktesystem).
 */
final class PreviewController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    private function renderNotice(
        string $icon,
        string $title,
        string $noticeTitle,
        string $noticeText,
        string $futureText
    ): string {
        return View::render('Preview', 'section_placeholder', [
            'icon'        => $icon,
            'title'       => $title,
            'noticeTitle' => $noticeTitle,
            'noticeText'  => $noticeText,
            'futureText'  => $futureText,
        ]);
    }

    // 7. Webcams (webcams)
    public function webcams(): string
    {
        return $this->renderNotice(
            'bi-camera-video-fill',
            'Webcams',
            'Bereich im neuen ACP noch nicht hinterlegt',
            'Dieser Bereich ist im neuen ACP aktuell noch nicht integriert und im Hintergrund nicht aktiv.',
            'Die Webcams werden zu einem späteren Zeitpunkt neu konzipiert und hier neu hinzugefügt.'
        );
    }

    // 10. Punktesystem (point_system)
    public function pointssystem(): string
    {
        return $this->renderNotice(
            'bi-star-fill',
            'Punktesystem',
            'Bereich im neuen ACP noch nicht hinterlegt',
            'Das bisherige Punktesystem ist in dieser Ansicht aktuell nicht integriert und im Hintergrund als eigenständiger Bereich noch nicht aktiv.',
            'Zu einem späteren Zeitpunkt wird festgelegt, ob und in welcher Form ein neues Punktesystem entwickelt und hier eingebunden wird.'
        );
    }
}
