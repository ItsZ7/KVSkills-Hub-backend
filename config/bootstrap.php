<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/helpers.php';
require_once BASE_PATH . '/app/Csrf.php';
require_once BASE_PATH . '/app/Auth.php';
require_once BASE_PATH . '/app/CompetitionService.php';
require_once BASE_PATH . '/app/Repository.php';

if (APP_ENV === 'production' && (APP_KEY === 'development-only-key-change-me' || strlen(APP_KEY) < 32)) {
    throw new RuntimeException('APP_KEY produksi mesti ditetapkan kepada rahsia rawak sekurang-kurangnya 32 aksara.');
}

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
} elseif (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

Auth::refresh();
