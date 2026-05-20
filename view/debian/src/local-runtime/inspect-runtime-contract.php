<?php

$appRoot = getenv('VIEW_APP_ROOT');
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? getenv('DOCUMENT_ROOT') ?: '';

if (($appRoot === false || $appRoot === '') && $documentRoot !== '') {
    $appRoot = dirname($documentRoot);
}

if ($appRoot === false || $appRoot === '') {
    fwrite(STDERR, "VIEW_APP_ROOT or DOCUMENT_ROOT is required\n");
    exit(1);
}

$appRoot = rtrim($appRoot, DIRECTORY_SEPARATOR);

defined('BASE_DIR') || define('BASE_DIR', $appRoot);
defined('SYSTEM_DIR') || define('SYSTEM_DIR', BASE_DIR . '/sys');
defined('WEB_DIR') || define('WEB_DIR', BASE_DIR . '/web');

require_once SYSTEM_DIR . '/includes/tke_config.php';

$pairs = [
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
    'http_host' => $_SERVER['HTTP_HOST'] ?? '',
    'server_name' => $_SERVER['SERVER_NAME'] ?? '',
    'request_scheme' => $_SERVER['REQUEST_SCHEME'] ?? '',
    'base_dir' => BASE_DIR,
    'database_name' => defined('DATABASE_NAME') ? DATABASE_NAME : '',
    'global_database_name' => defined('GLOBAL_DATABASE_NAME') ? GLOBAL_DATABASE_NAME : '',
    'company_logo' => defined('COMPANY_LOGO') ? COMPANY_LOGO : '',
];

foreach ($pairs as $key => $value) {
    echo $key . '=' . $value . PHP_EOL;
}
