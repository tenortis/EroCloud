<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

require_once(SOURCEDIR . '/includes/klassen/CoActorManager.inc.php');
CoActorManager::ensureSchema();

// Admin-Aktion verarbeiten (Bestätigen / Ablehnen / Status zurücksetzen)
$actionFeedbackHtml = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['acp_co_actor_action']) && !empty($_POST['acp_co_actor_id'])) {
    $actId = abs((int)$_POST['acp_co_actor_id']);
    $actType = trim((string)$_POST['acp_co_actor_action']);
    $empId = isset($_SESSION['employee_id']) ? abs((int)$_SESSION['employee_id']) : 0;

    $rsRow = p4c_query("SELECT `id`, `merchant_id` FROM `co_actors` WHERE `id` = '" . $actId . "' AND `status` != 'deleted' LIMIT 1;", __FILE__, __LINE__);
    if ($rsRow && p4c_num_rows($rsRow) > 0) {
        $rObj = p4c_fetch_object($rsRow);
        $mId  = abs((int)$rObj->merchant_id);

        if ($actType === 'reset_review') {
            p4c_query(
                "UPDATE `co_actors` SET
                    `checked_status` = 'under_review',
                    `rejection_reason` = ''
                 WHERE `id` = '" . $actId . "' LIMIT 1;",
                __FILE__,
                __LINE__
            );
            $actionFeedbackHtml = '<div style="background:#e0f2fe; border:1px solid #7dd3fc; color:#0369a1; padding:10px 14px; border-radius:6px; margin-bottom:14px; font-weight:bold;">&#x21bb; Status des Darstellers #' . $actId . ' wurde wieder auf &bdquo;Zur Pr&uuml;fung offen&ldquo; gesetzt.</div>';
        } else {
            $actionFeedbackHtml = CoActorManager::handleAcpAction($mId);
        }
    }
}

// Filter-Parameter
$statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : 'under_review';
$allowedFilters = array('under_review', 'verified', 'rejected', 'pending_qr', 'all');
if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'under_review';
}

$roleFilter = isset($_GET['role']) ? trim((string)$_GET['role']) : 'all';
if (!in_array($roleFilter, array('all', 'owner', 'co_actor'), true)) {
    $roleFilter = 'all';
}

$searchQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Zähler je Status abrufen
$counts = array(
    'under_review' => 0,
    'verified'     => 0,
    'rejected'     => 0,
    'pending_qr'   => 0,
    'all'          => 0,
);
$rsCounts = p4c_query(
    "SELECT `checked_status`, COUNT(*) AS `cnt` FROM `co_actors` WHERE `status` != 'deleted' GROUP BY `checked_status`;",
    __FILE__,
    __LINE__
);
if ($rsCounts) {
    while ($cObj = p4c_fetch_object($rsCounts)) {
        $st = (string)$cObj->checked_status;
        $cnt = (int)$cObj->cnt;
        if (isset($counts[$st])) {
            $counts[$st] = $cnt;
        } else {
            $counts['pending_qr'] += $cnt;
        }
        $counts['all'] += $cnt;
    }
}

// Mitarbeiter-Namen für die Anzeige „Geprüft von“ laden
$employeeNames = array();
$rsEmps = p4c_query("SELECT `id`, `username` FROM `employee`;", __FILE__, __LINE__);
if ($rsEmps) {
    while ($eObj = p4c_fetch_object($rsEmps)) {
        $employeeNames[(int)$eObj->id] = (string)$eObj->username;
    }
}

// SQL-Where aufbauen
$whereParts = array("c.`status` != 'deleted'");
if ($statusFilter !== 'all') {
    $whereParts[] = "c.`checked_status` = '" . p4c_escape_string($statusFilter) . "'";
}
if ($roleFilter === 'owner') {
    $whereParts[] = "c.`is_owner` = 1";
} elseif ($roleFilter === 'co_actor') {
    $whereParts[] = "c.`is_owner` = 0";
}
if ($searchQuery !== '') {
    $escQ = p4c_escape_string($searchQuery);
    $numQ = abs((int)$searchQuery);
    $searchConds = array(
        "c.`creator_name` LIKE '%" . $escQ . "%'",
        "CAST(AES_DECRYPT(c.`nickname_not_public`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'",
        "CAST(AES_DECRYPT(c.`first_name`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'",
        "CAST(AES_DECRYPT(c.`last_name`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'",
        "CAST(AES_DECRYPT(c.`id_doc_number`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'",
        "m.`partner_id` LIKE '%" . $escQ . "%'",
        "CAST(AES_DECRYPT(m.`username`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'"
    );
    if ($numQ > 0) {
        $searchConds[] = "c.`id` = '" . $numQ . "'";
        $searchConds[] = "c.`merchant_id` = '" . $numQ . "'";
    }
    $whereParts[] = "(" . implode(" OR ", $searchConds) . ")";
}

$whereSql = implode(" AND ", $whereParts);

// Einträge laden (inkl. Partner-Stammdaten)
$sqlList = "SELECT
        c.`id`, c.`merchant_id`, c.`is_owner`, c.`creator_name`,
        AES_DECRYPT(c.`nickname_not_public`, '" . AES_KEY . "') AS `dec_nickname`,
        AES_DECRYPT(c.`first_name`, '" . AES_KEY . "') AS `dec_first_name`,
        AES_DECRYPT(c.`last_name`, '" . AES_KEY . "') AS `dec_last_name`,
        AES_DECRYPT(c.`birth_date`, '" . AES_KEY . "') AS `dec_birth_date`,
        AES_DECRYPT(c.`street`, '" . AES_KEY . "') AS `dec_street`,
        AES_DECRYPT(c.`zip_code`, '" . AES_KEY . "') AS `dec_zip_code`,
        AES_DECRYPT(c.`city`, '" . AES_KEY . "') AS `dec_city`,
        c.`country`, c.`id_doc_type`,
        AES_DECRYPT(c.`id_doc_number`, '" . AES_KEY . "') AS `dec_id_doc_number`,
        c.`id_doc_valid_until`, c.`verification_method`,
        c.`qr_token`, c.`qr_token_expires_at`,
        c.`face_ref_img`, c.`id_shot_img1`, c.`id_front_img`, c.`id_back_img`,
        c.`consent_doc_file`, c.`signature_data_json`,
        c.`ai_face_ready`, c.`checked_status`, c.`rejection_reason`,
        c.`create_datetime`, c.`submitted_datetime`, c.`checked_on`, c.`checked_by_employee`, c.`status`,
        m.`partner_id` AS `merchant_partner_id`,
        AES_DECRYPT(m.`username`, '" . AES_KEY . "') AS `merchant_username`,
        AES_DECRYPT(m.`firstname`, '" . AES_KEY . "') AS `merchant_firstname`,
        AES_DECRYPT(m.`surname`, '" . AES_KEY . "') AS `merchant_surname`
    FROM `co_actors` c
    LEFT JOIN `merchants` m ON m.`id` = c.`merchant_id`
    WHERE " . $whereSql . "
    ORDER BY
        CASE c.`checked_status`
            WHEN 'under_review' THEN 1
            WHEN 'rejected' THEN 2
            WHEN 'verified' THEN 3
            ELSE 4
        END ASC,
        c.`submitted_datetime` DESC,
        c.`id` DESC
    LIMIT 200;";

$rsList = p4c_query($sqlList, __FILE__, __LINE__);
$rows = array();
$coActorIds = array();
if ($rsList) {
    while ($obj = p4c_fetch_object($rsList)) {
        $formatted = CoActorManager::formatCoActorRow($obj);
        $formatted['merchant_partner_id'] = CoActorManager::cleanUtf8((string)($obj->merchant_partner_id ?? ''));
        $formatted['merchant_username']   = CoActorManager::cleanUtf8((string)($obj->merchant_username ?? ''));
        $formatted['merchant_real_name']  = trim(
            CoActorManager::cleanUtf8((string)($obj->merchant_firstname ?? '')) . ' ' .
            CoActorManager::cleanUtf8((string)($obj->merchant_surname ?? ''))
        );
        $rows[] = $formatted;
        $coActorIds[] = (int)$formatted['id'];
    }
}

// Verknüpfte Filme zu diesen Darstellern nachladen
$linkedMoviesByActor = array();
if (!empty($coActorIds)) {
    $idListSql = implode(',', array_map('intval', $coActorIds));
    $rsLinks = p4c_query(
        "SELECT
            cc.`co_actor_id`,
            cc.`content_id` AS `movie_id`,
            mv.`title` AS `movie_title`,
            mv.`status` AS `movie_status`,
            mv.`released` AS `movie_released`,
            mv.`movie_checked`
         FROM `content_co_actors` cc
         INNER JOIN `movies` mv ON mv.`id` = cc.`content_id`
         WHERE cc.`content_type` = 'movie'
           AND cc.`co_actor_id` IN (" . $idListSql . ")
           AND mv.`status` != 'deleted'
         ORDER BY mv.`id` DESC;",
        __FILE__,
        __LINE__
    );
    if ($rsLinks) {
        while ($lnk = p4c_fetch_array($rsLinks)) {
            $caId = (int)$lnk['co_actor_id'];
            if (!isset($linkedMoviesByActor[$caId])) {
                $linkedMoviesByActor[$caId] = array();
            }
            $linkedMoviesByActor[$caId][] = $lnk;
        }
    }
}

$baseUrl = ACP_URL . '/Drehpartner-pruefen';

$site .= '
<div class="ui-widget-header" style="padding:12px 16px; font-size:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <div>
        <strong>&#x1FAAA; Drehpartner &amp; ID-Check (Model-Release &amp; Ausweispr&uuml;fung)</strong>
    </div>
    <div style="font-size:13px; font-weight:normal;">
        Offen zur Pr&uuml;fung: <strong style="color:#d97706;">' . (int)$counts['under_review'] . '</strong> &nbsp;|&nbsp;
        Verifiziert: <strong style="color:#16a34a;">' . (int)$counts['verified'] . '</strong> &nbsp;|&nbsp;
        Abgelehnt: <strong style="color:#dc2626;">' . (int)$counts['rejected'] . '</strong> &nbsp;|&nbsp;
        Wartet auf QR-Scan: <strong>' . (int)$counts['pending_qr'] . '</strong>
    </div>
</div>

<div class="ui-widget-content" style="padding:15px; border-top:none;">
    ' . $actionFeedbackHtml . '

    <!-- Filter-Leiste -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #e2e8f0;">
        <div style="display:flex; gap:6px; flex-wrap:wrap;">';

$filterTabs = array(
    'under_review' => array('label' => 'Zur Prüfung offen',  'count' => $counts['under_review'], 'color' => '#d97706'),
    'verified'     => array('label' => 'Verifiziert (OK)',   'count' => $counts['verified'],     'color' => '#16a34a'),
    'rejected'     => array('label' => 'Abgelehnt',          'count' => $counts['rejected'],     'color' => '#dc2626'),
    'pending_qr'   => array('label' => 'Wartet auf QR-Scan', 'count' => $counts['pending_qr'],   'color' => '#64748b'),
    'all'          => array('label' => 'Alle anzeigen',      'count' => $counts['all'],          'color' => '#0284c7'),
);

foreach ($filterTabs as $fKey => $fCfg) {
    $isActive = ($statusFilter === $fKey);
    $tabUrl = $baseUrl . '?status=' . urlencode($fKey) . '&role=' . urlencode($roleFilter) . ($searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '');
    $bg = $isActive ? $fCfg['color'] : '#f1f5f9';
    $fg = $isActive ? '#ffffff' : '#334155';
    $bd = $isActive ? $fCfg['color'] : '#cbd5e1';
    $site .= '
            <a href="' . htmlspecialchars($tabUrl, ENT_QUOTES, 'UTF-8') . '" style="text-decoration:none; padding:7px 13px; border-radius:6px; font-size:13px; font-weight:bold; background:' . $bg . '; color:' . $fg . '; border:1px solid ' . $bd . ';">
                ' . htmlspecialchars($fCfg['label'], ENT_QUOTES, 'UTF-8') . ' (' . (int)$fCfg['count'] . ')
            </a>';
}

$site .= '
        </div>

        <form method="get" action="' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:0;">
            <input type="hidden" name="status" value="' . htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') . '" />
            <select name="role" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:5px; font-size:13px;">
                <option value="all"' . ($roleFilter === 'all' ? ' selected="selected"' : '') . '>Alle Rollen (Ich &amp; Drehpartner)</option>
                <option value="owner"' . ($roleFilter === 'owner' ? ' selected="selected"' : '') . '>Nur Account-Inhaber (&bdquo;Ich&ldquo;)</option>
                <option value="co_actor"' . ($roleFilter === 'co_actor' ? ' selected="selected"' : '') . '>Nur weitere Drehpartner</option>
            </select>
            <input type="text" name="q" value="' . htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') . '" placeholder="Name, Creator, Ausweis-Nr., Partner-ID..." style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:5px; font-size:13px; width:240px;" />
            <button type="submit" style="padding:6px 12px; background:#0284c7; color:#fff; border:1px solid #0369a1; border-radius:5px; font-weight:bold; cursor:pointer; font-size:13px;">Filtern</button>';
if ($searchQuery !== '' || $roleFilter !== 'all') {
    $resetUrl = $baseUrl . '?status=' . urlencode($statusFilter);
    $site .= '
            <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="padding:6px 10px; background:#e2e8f0; color:#334155; border-radius:5px; text-decoration:none; font-size:12px;">Reset</a>';
}
$site .= '
        </form>
    </div>';

if (empty($rows)) {
    $site .= '
    <div style="padding:30px; text-align:center; color:#64748b; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; font-size:14px;">
        Keine Darsteller-Eintr&auml;ge f&uuml;r diesen Filter gefunden.
    </div>';
} else {
    $docTypeLabels = array(
        'id_card'         => 'Personalausweis',
        'passport'        => 'Reisepass',
        'drivers_license' => 'Führerschein',
    );

    $site .= '
    <table style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead>
            <tr style="background:#f1f5f9; border-bottom:2px solid #cbd5e1; text-align:left;">
                <th style="padding:10px 8px; width:230px;">Darsteller &amp; Partner</th>
                <th style="padding:10px 8px; width:270px;">Entschl&uuml;sselte Ausweisdaten</th>
                <th style="padding:10px 8px;">Fotos, Unterschrift &amp; PDF-Zertifikat</th>
                <th style="padding:10px 8px; width:220px;">Verkn&uuml;pfte Filme</th>
                <th style="padding:10px 8px; width:230px;">Status &amp; Pr&uuml;f-Aktion</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($rows as $actor) {
        $caId       = (int)$actor['id'];
        $merchantId = (int)$actor['merchant_id'];
        $isOwner    = !empty($actor['is_owner']);

        $roleBadge = $isOwner
            ? '<span style="background:#dbeafe; color:#1e40af; padding:2px 7px; border-radius:4px; font-weight:bold; font-size:11px; display:inline-block; margin-bottom:4px;">&#x1F464; Account-Inhaber (&bdquo;Ich&ldquo;)</span>'
            : '<span style="background:#f3e8ff; color:#6b21a8; padding:2px 7px; border-radius:4px; font-weight:bold; font-size:11px; display:inline-block; margin-bottom:4px;">&#x1F465; Drehpartner</span>';

        $methodBadge = !empty($actor['ai_face_ready'])
            ? '<div style="color:#0369a1; font-size:11px; margin-top:4px; font-weight:bold;">&#x1F4F1; QR-Code Smartphone (KI-Ready)</div>'
            : (!empty($actor['is_legacy_upload'])
                ? '<div style="color:#b45309; font-size:11px; margin-top:4px; font-weight:bold;">&#x1F4C4; Altbestand-Upload</div>'
                : '<div style="color:#64748b; font-size:11px; margin-top:4px;">&#x1F4F1; QR-Code Link bereit</div>');

        // Status-Farbe
        $stColor = '#d97706';
        $stBg    = '#fef3c7';
        if ($actor['checked_status'] === 'verified') {
            $stColor = '#15803d';
            $stBg    = '#dcfce7';
        } elseif ($actor['checked_status'] === 'rejected') {
            $stColor = '#b91c1c';
            $stBg    = '#fee2e2';
        } elseif ($actor['checked_status'] === 'pending_qr') {
            $stColor = '#475569';
            $stBg    = '#f1f5f9';
        }

        // Medien-Thumbnails
        $thumbsHtml = '<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">';
        $mediaItems = array(
            array('url' => $actor['face_ref_url'], 'label' => '1. KI-Selfie'),
            array('url' => $actor['id_shot_url'],  'label' => '2. ID-Shot'),
            array('url' => $actor['id_front_url'], 'label' => '3. Ausweis V.'),
            array('url' => $actor['id_back_url'],  'label' => '4. Ausweis R.'),
        );
        foreach ($mediaItems as $mInfo) {
            if (!empty($mInfo['url'])) {
                $u = htmlspecialchars((string)$mInfo['url'], ENT_QUOTES, 'UTF-8');
                $thumbsHtml .= '<a href="' . $u . '" target="_blank" title="' . htmlspecialchars($mInfo['label'], ENT_QUOTES, 'UTF-8') . ' in Originalgr&ouml;&szlig;e &ouml;ffnen" style="display:inline-block; text-align:center; text-decoration:none; border:1px solid #cbd5e1; border-radius:5px; padding:3px; background:#fff;">'
                    . '<img src="' . $u . '" style="width:68px; height:50px; object-fit:cover; display:block; border-radius:3px;" />'
                    . '<span style="font-size:10px; color:#334155; display:block; margin-top:2px; font-weight:bold;">' . htmlspecialchars($mInfo['label'], ENT_QUOTES, 'UTF-8') . '</span>'
                    . '</a>';
            }
        }
        if (!empty($actor['certificate_url'])) {
            $certUrl = htmlspecialchars((string)$actor['certificate_url'], ENT_QUOTES, 'UTF-8');
            $thumbsHtml .= '<a href="' . $certUrl . '" target="_blank" style="display:inline-block; padding:7px 11px; background:#0284c7; color:#fff; text-decoration:none; border-radius:5px; font-size:11px; font-weight:bold;">&#x1F4C4; Einverst&auml;ndnis-Zertifikat / PDF</a>';
        }
        if (!empty($actor['consent_doc_file'])) {
            $legUrl = CoActorManager::getMediaProxyUrl($caId, 'consent_doc', (string)$actor['qr_token']);
            $thumbsHtml .= '<a href="' . htmlspecialchars($legUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="display:inline-block; padding:7px 11px; background:#475569; color:#fff; text-decoration:none; border-radius:5px; font-size:11px; font-weight:bold;">&#x1F4CE; Altbestand-Dokument</a>';
        }
        if (empty($actor['is_complete'])) {
            $thumbsHtml .= '<span style="color:#b91c1c; font-size:12px; font-weight:bold;">⚠️ Noch keine vollst&auml;ndigen Aufnahmen eingereicht</span>';
        }
        $thumbsHtml .= '</div>';

        $sigData = is_array($actor['signature_data'] ?? null) ? $actor['signature_data'] : array();
        if (!empty($sigData['signed_at'])) {
            $thumbsHtml .= '<div style="font-size:11px; color:#64748b; margin-top:6px;">Signiert am: <strong>' . date('d.m.Y H:i', strtotime((string)$sigData['signed_at'])) . ' Uhr</strong> | IP: ' . htmlspecialchars((string)($sigData['ip'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</div>';
        } elseif (!empty($actor['submitted_datetime']) && !str_starts_with((string)$actor['submitted_datetime'], '0000')) {
            $thumbsHtml .= '<div style="font-size:11px; color:#64748b; margin-top:6px;">Eingereicht am: <strong>' . date('d.m.Y H:i', strtotime((string)$actor['submitted_datetime'])) . ' Uhr</strong></div>';
        }

        // Verknüpfte Filme
        $moviesHtml = '';
        $linkedMovies = $linkedMoviesByActor[$caId] ?? array();
        if (empty($linkedMovies)) {
            $moviesHtml = '<span style="color:#94a3b8; font-size:12px;">Noch keinem Film zugeordnet</span>';
        } else {
            foreach ($linkedMovies as $mv) {
                $mvId = (int)$mv['movie_id'];
                $mvTitle = htmlspecialchars(CoActorManager::cleanUtf8((string)$mv['movie_title']), ENT_QUOTES, 'UTF-8');
                if ($mvTitle === '') {
                    $mvTitle = 'Film #' . $mvId;
                }
                $isUnchecked = ((string)$mv['movie_checked'] === '0000-00-00 00:00:00' && (int)$mv['movie_released'] === 1);
                $mvLink = $isUnchecked ? (ACP_URL . '/Film-pruefen/' . $mvId) : (ACP_URL . '/Film-bearbeiten/' . $mvId);
                $mvBadge = $isUnchecked
                    ? '<span style="background:#fef3c7; color:#b45309; padding:1px 5px; border-radius:3px; font-size:10px;">Pr&uuml;fung</span>'
                    : ($mv['movie_status'] === 'active'
                        ? '<span style="background:#dcfce7; color:#15803d; padding:1px 5px; border-radius:3px; font-size:10px;">Online</span>'
                        : '<span style="background:#f1f5f9; color:#475569; padding:1px 5px; border-radius:3px; font-size:10px;">Entwurf</span>');

                $moviesHtml .= '<div style="margin-bottom:4px; line-height:1.35;">'
                    . '<a href="' . htmlspecialchars($mvLink, ENT_QUOTES, 'UTF-8') . '" style="color:#0284c7; text-decoration:none; font-weight:bold;">#' . $mvId . ' ' . $mvTitle . '</a> '
                    . $mvBadge
                    . '</div>';
            }
        }

        $docTypeLabel = $docTypeLabels[(string)$actor['id_doc_type']] ?? 'Ausweis';
        $empIdChecked = (int)($actor['checked_by_employee'] ?? 0);
        $empNameChecked = $empIdChecked > 0 ? ($employeeNames[$empIdChecked] ?? ('Mitarbeiter #' . $empIdChecked)) : '';

        $site .= '
            <tr style="border-bottom:1px solid #e2e8f0; vertical-align:top; background:' . ($actor['checked_status'] === 'under_review' ? '#fffbeb' : '#ffffff') . ';">
                <td style="padding:12px 8px;">
                    ' . $roleBadge . '
                    <div style="font-weight:bold; font-size:14px; color:#0f172a;">' . htmlspecialchars((string)$actor['display_label'], ENT_QUOTES, 'UTF-8') . '</div>
                    <div style="font-size:11px; color:#64748b;">Ref: ' . htmlspecialchars((string)$actor['ref_code'], ENT_QUOTES, 'UTF-8') . ' (ID #' . $caId . ')</div>
                    ' . $methodBadge . '
                    <div style="margin-top:8px; padding-top:6px; border-top:1px dashed #e2e8f0; font-size:11px; color:#334155;">
                        <strong>Partner:</strong> <a href="' . ACP_URL . '/Haendler/' . $merchantId . '" style="color:#0284c7; text-decoration:none; font-weight:bold;">#' . $merchantId . ' ' . htmlspecialchars((string)($actor['merchant_username'] ?: $actor['merchant_partner_id']), ENT_QUOTES, 'UTF-8') . '</a><br />
                        ' . ($actor['merchant_real_name'] !== '' ? '<span style="color:#64748b;">(' . htmlspecialchars($actor['merchant_real_name'], ENT_QUOTES, 'UTF-8') . ')</span>' : '') . '
                    </div>
                </td>
                <td style="padding:12px 8px; line-height:1.45;">
                    <div><strong>Klarname:</strong> ' . htmlspecialchars((string)($actor['real_full_name'] ?: '-'), ENT_QUOTES, 'UTF-8') . '</div>
                    <div><strong>Geburtsdatum:</strong> ' . htmlspecialchars((string)($actor['birth_date_de'] ?: '-'), ENT_QUOTES, 'UTF-8') . ($actor['age'] !== null ? ' <strong>(' . (int)$actor['age'] . ' J.)</strong>' : '') . '</div>
                    <div><strong>Anschrift:</strong> ' . htmlspecialchars(trim((string)$actor['street'] . ', ' . (string)$actor['zip_code'] . ' ' . (string)$actor['city'] . ' (' . (string)$actor['country'] . ')', ', '), ENT_QUOTES, 'UTF-8') . '</div>
                    <div><strong>Ausweis:</strong> ' . htmlspecialchars($docTypeLabel, ENT_QUOTES, 'UTF-8') . ' &ndash; Nr.: <strong>' . htmlspecialchars((string)($actor['id_doc_number'] ?: '-'), ENT_QUOTES, 'UTF-8') . '</strong></div>
                    <div><strong>G&uuml;ltig bis:</strong> ' . htmlspecialchars((string)($actor['id_doc_valid_until'] ?: 'Unbegrenzt / k.A.'), ENT_QUOTES, 'UTF-8') . '</div>
                </td>
                <td style="padding:12px 8px;">
                    ' . $thumbsHtml . '
                </td>
                <td style="padding:12px 8px;">
                    ' . $moviesHtml . '
                </td>
                <td style="padding:12px 8px;">
                    <span style="display:inline-block; padding:4px 9px; border-radius:4px; font-weight:bold; font-size:12px; background:' . $stBg . '; color:' . $stColor . ';">
                        ' . htmlspecialchars((string)$actor['status_label'], ENT_QUOTES, 'UTF-8') . '
                    </span>';

        if ($empNameChecked !== '' && !empty($actor['checked_on']) && !str_starts_with((string)$actor['checked_on'], '0000')) {
            $site .= '<div style="font-size:11px; color:#64748b; margin-top:4px;">Gepr&uuml;ft von <strong>' . htmlspecialchars($empNameChecked, ENT_QUOTES, 'UTF-8') . '</strong> am ' . date('d.m.Y H:i', strtotime((string)$actor['checked_on'])) . '</div>';
        }

        if ($actor['rejection_reason'] !== '') {
            $site .= '<div style="font-size:11px; color:#b91c1c; margin-top:5px; background:#fef2f2; border:1px solid #fecaca; padding:4px 6px; border-radius:4px;"><strong>Grund:</strong> ' . htmlspecialchars((string)$actor['rejection_reason'], ENT_QUOTES, 'UTF-8') . '</div>';
        }

        // Aktions-Buttons
        $site .= '<div style="margin-top:8px;">';
        if ($actor['checked_status'] !== 'verified') {
            $site .= '
                <form method="post" style="margin:0 0 6px 0;">
                    <input type="hidden" name="acp_co_actor_action" value="verify" />
                    <input type="hidden" name="acp_co_actor_id" value="' . $caId . '" />
                    <button type="submit" style="width:100%; background:#16a34a; color:#fff; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:bold;">&#x2713; ID-Shot &amp; Person best&auml;tigen</button>
                </form>';
        }

        if ($actor['checked_status'] !== 'rejected') {
            $site .= '
                <form method="post" style="margin:0; display:flex; gap:4px;" onsubmit="var r = this.querySelector(\'input[name=acp_co_actor_reject_reason]\').value; if(!r){alert(\'Bitte Ablehnungsgrund angeben.\'); return false;} return true;">
                    <input type="hidden" name="acp_co_actor_action" value="reject" />
                    <input type="hidden" name="acp_co_actor_id" value="' . $caId . '" />
                    <input type="text" name="acp_co_actor_reject_reason" placeholder="Ablehnungsgrund..." style="flex:1; font-size:11px; padding:4px 6px; border:1px solid #cbd5e1; border-radius:4px; min-width:0;" />
                    <button type="submit" style="background:#dc2626; color:#fff; border:none; padding:5px 8px; border-radius:4px; cursor:pointer; font-size:11px; font-weight:bold;">&#x2717; Ablehnen</button>
                </form>';
        }

        if ($actor['checked_status'] === 'verified' || $actor['checked_status'] === 'rejected') {
            $site .= '
                <form method="post" style="margin:6px 0 0 0;">
                    <input type="hidden" name="acp_co_actor_action" value="reset_review" />
                    <input type="hidden" name="acp_co_actor_id" value="' . $caId . '" />
                    <button type="submit" style="width:100%; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:11px;">&#x21bb; Zur&uuml;ck auf &bdquo;Offen&ldquo; setzen</button>
                </form>';
        }

        $site .= '</div>
                </td>
            </tr>';
    }

    $site .= '
        </tbody>
    </table>';
}

$site .= '
</div>';
