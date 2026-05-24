<?php

declare(strict_types=1);

/**
 * Ensure local runtime files exist under VIEW_APP_ROOT (/opt/tk/core — code mount; BASE_DIR for init.lib).
 * Safe to run on every prepare_runtime / bin/local up.
 */

function localAssetsFail(string $message): int
{
    fwrite(STDERR, "Error: {$message}" . PHP_EOL);

    return 1;
}

function localAssetsEnsureDirectory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException("Failed to create directory: {$path}");
    }
}

function localAssetsCopyFile(string $source, string $destination, bool $overwrite): void
{
    if (!is_file($source)) {
        throw new RuntimeException("Source file not found: {$source}");
    }

    if (is_file($destination) && !$overwrite) {
        return;
    }

    $parent = dirname($destination);
    if (!is_dir($parent)) {
        localAssetsEnsureDirectory($parent);
    }

    if (!copy($source, $destination)) {
        throw new RuntimeException("Failed to copy {$source} -> {$destination}");
    }
}

$appRoot = rtrim((string) (getenv('VIEW_APP_ROOT') ?: '/opt/tk/core'), '/');
$siteRoot = rtrim((string) (getenv('VIEW_SITE_ROOT') ?: ''), '/');
$runtimeDir = rtrim((string) (getenv('VIEW_LOCAL_RUNTIME_DIR') ?: '/usr/local/lib/tke-local'), '/');
$templateDir = $runtimeDir . '/templates';

if (!is_dir($appRoot)) {
    exit(localAssetsFail("app root not found: {$appRoot}"));
}

try {
    localAssetsEnsureDirectory('/var/log/View');
    localAssetsEnsureDirectory('/var/log/SelfService');

    $viewLoggerTarget = $appRoot . '/ViewLoggerConfig.php';
    if (!is_file($viewLoggerTarget)) {
        $template = $templateDir . '/ViewLoggerConfig.php';
        if (!is_file($template)) {
            throw new RuntimeException(
                "ViewLoggerConfig.php missing at {$viewLoggerTarget} and no template at {$template}"
            );
        }
        localAssetsCopyFile($template, $viewLoggerTarget, true);
    }

    $log4phpTarget = $appRoot . '/log4php_config.xml';
    if ($siteRoot !== '' && is_file($siteRoot . '/log4php_config.xml')) {
        localAssetsCopyFile($siteRoot . '/log4php_config.xml', $log4phpTarget, true);
    } elseif (!is_file($log4phpTarget)) {
        $log4phpTemplate = $templateDir . '/log4php_config.xml';
        if (!is_file($log4phpTemplate)) {
            throw new RuntimeException(
                "log4php_config.xml missing at {$log4phpTarget}; add site log4php under VIEW_SITE_ROOT or template {$log4phpTemplate}"
            );
        }
        localAssetsCopyFile($log4phpTemplate, $log4phpTarget, true);
    }
} catch (Throwable $exception) {
    exit(localAssetsFail($exception->getMessage()));
}

echo "prepared_local_runtime_assets=1\n";
echo "view_logger_config={$appRoot}/ViewLoggerConfig.php\n";
echo "log4php_config={$appRoot}/log4php_config.xml\n";
if ($siteRoot !== '') {
    echo "site_root={$siteRoot}\n";
}

exit(0);
