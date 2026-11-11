<?php

define('BASE_PATH', dirname(__DIR__));
define('DISPLAY_ERROR', true);
define('APP_ENV', 'local');
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'ServBay.dev');
define('DB_NAME', 'ratsa_db');
define('RASTA_BOOTSTRAP_PIN', '4868');
define('STORAGE_PRIVATE', BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private');

require_once BASE_PATH . '/helpers/protocol.php';

define('CURRENT_DOMAIN', trim(currentDomain(), '/'));
