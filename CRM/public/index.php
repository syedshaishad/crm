<?php

/**
 * Entry point for shared/cPanel hosting.
 *
 * Standard layout (recommended):
 *   /home/user/crm-app/{app,bootstrap,config,...}   <- upload everything EXCEPT public/
 *   /home/user/public_html/{index.php,.htaccess}    <- copy contents of public/ here
 *   Then edit public_html/index.php paths ($appPath) to point at /home/user/crm-app.
 *
 * Simpler layout (VPS / addon-domain pointing at public/):
 *   Point the domain document-root directly at this app's public/ directory.
 */

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// cPanel-friendly: allow index.php to live one level below the app base.
$appBase = $_ENV['APP_BASE_PATH'] ?? $_SERVER['APP_BASE_PATH'] ?? dirname(__DIR__);

if (file_exists($maintenance = $appBase.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appBase.'/vendor/autoload.php';

$app = require $appBase.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
