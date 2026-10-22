<?php
declare(strict_types=1);

/**
 * EroCloud ACP (v2.0) - Zentraler API-Entrypoint für SPA-Fragmente
 */

if (!defined('SAFE_INC')) {
    define('SAFE_INC', 1);
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

$request = new Request();
Router::dispatch($request);
