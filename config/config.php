<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/env.php';
load_env(BASE_PATH . '/.env');

define('APP_NAME', (string)env('APP_NAME', 'KVSkills Hub'));
define('APP_ENV', (string)env('APP_ENV', 'production'));
define('APP_DEBUG', (bool)env('APP_DEBUG', false));
define('APP_URL', rtrim((string)env('APP_URL', 'http://localhost/KVSkills-Hub-backend'), '/'));
define('APP_KEY', (string)env('APP_KEY', 'development-only-key-change-me'));
define('STORAGE_PATH', BASE_PATH . '/storage/private');

date_default_timezone_set((string)env('APP_TIMEZONE', 'Asia/Kuala_Lumpur'));
