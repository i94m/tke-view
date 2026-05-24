<?php

/**
 * Replaces legacy autotest /run/init.sh sed on BaseBootstrap.php.
 * Used as PHPUnit auto_prepend_file for Sharp / autotest lanes.
 */

function viewSharpLocalSiteHostAliases(): array
{
    $rawAliases = getenv('VIEW_LOCAL_SITE_HOST_ALIASES');
    if ($rawAliases === false || trim($rawAliases) === '') {
        return [];
    }

    $aliases = [];
    foreach (explode(',', $rawAliases) as $entry) {
        $entry = trim($entry);
        if ($entry === '' || strpos($entry, '=') === false) {
            continue;
        }

        [$site, $hostLabel] = array_map('trim', explode('=', $entry, 2));
        if ($site === '' || $hostLabel === '') {
            continue;
        }

        $aliases[$site] = $hostLabel;
    }

    return $aliases;
}

function viewSharpLocalHttpHostForSite(string $site): string
{
    $template = getenv('VIEW_LOCAL_HOST_TEMPLATE');
    if ($template === false || $template === '') {
        $template = '%s.local.test';
    }

    $aliases = viewSharpLocalSiteHostAliases();
    $hostLabel = $aliases[$site] ?? $site;

    return str_replace('%s', $hostLabel, $template);
}

function viewSharpLocalCodeRoot(): string
{
    $codeRoot = getenv('VIEW_APP_ROOT');
    if ($codeRoot !== false && trim($codeRoot) !== '') {
        return rtrim($codeRoot, '/');
    }

    $tkPath = getenv('VIEW_TK_PATH');
    if ($tkPath !== false && trim($tkPath) !== '') {
        return rtrim($tkPath, '/') . '/core';
    }

    return '/opt/tk/core';
}

function viewSharpLocalTkPath(string $codeRoot): string
{
    $tkPath = getenv('VIEW_TK_PATH');
    if ($tkPath !== false && trim($tkPath) !== '') {
        return rtrim($tkPath, '/');
    }

    if (str_ends_with($codeRoot, '/core')) {
        return dirname($codeRoot);
    }

    return '/opt/tk';
}

$codeRoot = viewSharpLocalCodeRoot();
$tkPath = viewSharpLocalTkPath($codeRoot);

$documentRoot = getenv('DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') {
    $documentRoot = $codeRoot . '/web';
}

if (!defined('TK_PATH')) {
    define('TK_PATH', $tkPath);
}

defined('BASE_DIR') || define('BASE_DIR', $codeRoot);
defined('SYSTEM_DIR') || define('SYSTEM_DIR', BASE_DIR . '/sys');
defined('WEB_DIR') || define('WEB_DIR', BASE_DIR . '/web');

$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: $documentRoot;

if (empty($_SERVER['HTTP_HOST'])) {
    $viewSite = getenv('VIEW_SITE');
    if ($viewSite !== false && $viewSite !== '') {
        $_SERVER['HTTP_HOST'] = viewSharpLocalHttpHostForSite($viewSite);
    }
}

if (empty($_SERVER['SERVER_NAME']) && !empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
}

if (empty($_SERVER['REQUEST_SCHEME'])) {
    $_SERVER['REQUEST_SCHEME'] = getenv('REQUEST_SCHEME') ?: 'http';
}

$siteConfig = SYSTEM_DIR . '/includes/tke_config.php';
if (is_file($siteConfig)) {
    require_once $siteConfig;
}
