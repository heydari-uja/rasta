<?php


session_start();

require_once __DIR__ . '/config/config.php';

spl_autoload_register(function ($className) {
    $base = BASE_PATH . DIRECTORY_SEPARATOR;
    if (str_starts_with($className, 'Activities\\')) {
        $rel = substr(str_replace('\\', DIRECTORY_SEPARATOR, $className), strlen('Activities' . DIRECTORY_SEPARATOR));
        $file = $base . 'activities' . DIRECTORY_SEPARATOR . $rel . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
    if (str_starts_with($className, 'Lib\\')) {
        $rel = substr(str_replace('\\', DIRECTORY_SEPARATOR, $className), strlen('Lib' . DIRECTORY_SEPARATOR));
        $file = $base . 'lib' . DIRECTORY_SEPARATOR . $rel . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
    if (str_starts_with($className, 'Database\\')) {
        require_once $base . 'database' . DIRECTORY_SEPARATOR . 'Database.php';
    }
});

require_once BASE_PATH . '/database/Database.php';
require_once BASE_PATH . '/activities/Auth/Auth.php';
require_once BASE_PATH . '/activities/Security/Security.php';
require_once BASE_PATH . '/activities/Workspace/Workspace.php';
require_once BASE_PATH . '/helpers/helper.php';

// #### TEST SECTION ####
// dd(asset('app/css/app.css'));

require_once BASE_PATH . '/routes/web.php';
