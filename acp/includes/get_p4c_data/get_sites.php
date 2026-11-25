<?php

declare(strict_types=1);

define('SAFE_INC', 1);

include_once('../../../config.inc.php');
include_once(ACP_DIR . '/common.inc.php');

if (is_logged_in('acp') === false) {
    exit;
}

require_once SOURCEDIR . '/includes/p4c_sites_sync.inc.php';
ec_p4c_sync_all_sites();
