<?php

declare(strict_types=1);

/**
 * Legacy path bridges for Autotest + Apache (container layer only; nothing under the code mount).
 *
 * Layout:
 * - VIEW_TK_PATH (/opt/tk)     — Autotest TK_PATH; TK_PATH/core/* is the mounted repo
 * - VIEW_APP_ROOT (/opt/tk/core) — code mount (web, sys, vivid, scripts)
 *
 * Bridges created outside the repo mount:
 * - /opt/tkdev2/{country}/web -> code web (BaseBootstrap DOCUMENT_ROOT)
 * - /opt/tk/autotest -> /opt/autotest
 * - /opt/tk/web|sys|vivid -> /opt/tk/core/... (Apache view.conf + php.ini on older images)
 */

function bridgeFail(string $message): int
{
    fwrite(STDERR, "Error: {$message}" . PHP_EOL);

    return 1;
}

function bridgeResolveTkPath(): string
{
    $tkPath = getenv('VIEW_TK_PATH');
    if ($tkPath !== false && trim($tkPath) !== '') {
        return rtrim($tkPath, '/');
    }

    $codeRoot = rtrim((string) (getenv('VIEW_APP_ROOT') ?: '/opt/tk/core'), '/');
    if (str_ends_with($codeRoot, '/core')) {
        return dirname($codeRoot);
    }

    return '/opt/tk';
}

function bridgeResolveCodeRoot(): string
{
    $codeRoot = getenv('VIEW_APP_ROOT');
    if ($codeRoot !== false && trim($codeRoot) !== '') {
        return rtrim($codeRoot, '/');
    }

    return bridgeResolveTkPath() . '/core';
}

function bridgeEnsureSymlink(string $linkPath, string $targetPath): void
{
    $parent = dirname($linkPath);
    if (!is_dir($parent)) {
        mkdir($parent, 0775, true);
    }

    if (is_link($linkPath)) {
        $current = readlink($linkPath);
        if ($current === $targetPath) {
            return;
        }
        unlink($linkPath);
    } elseif (is_file($linkPath) || is_dir($linkPath)) {
        throw new RuntimeException("Refusing to replace existing path: {$linkPath}");
    }

    if (!symlink($targetPath, $linkPath)) {
        throw new RuntimeException("Failed to create symlink {$linkPath} -> {$targetPath}");
    }
}

$tkPath = bridgeResolveTkPath();
$codeRoot = bridgeResolveCodeRoot();
$autotestRoot = rtrim(getenv('VIEW_AUTOTEST_ROOT') ?: '/opt/autotest', '/');
$country = getenv('VIEW_AUTOTEST_COUNTRY_NAME') ?: 'china';
$legacyRoot = rtrim(getenv('VIEW_LEGACY_TK_ROOT') ?: '/opt/tkdev2', '/');

if (!is_dir($codeRoot . '/web')) {
    exit(bridgeFail("code web root not found: {$codeRoot}/web"));
}

if (!is_dir($autotestRoot . '/phpunit')) {
    exit(bridgeFail("autotest phpunit root not found: {$autotestRoot}/phpunit"));
}

try {
    bridgeEnsureSymlink("{$legacyRoot}/{$country}/web", "{$codeRoot}/web");
    bridgeEnsureSymlink("{$tkPath}/autotest", $autotestRoot);

    foreach (['web', 'sys', 'vivid'] as $segment) {
        if (!is_dir("{$codeRoot}/{$segment}")) {
            continue;
        }
        bridgeEnsureSymlink("{$tkPath}/{$segment}", "{$codeRoot}/{$segment}");
    }
} catch (Throwable $e) {
    exit(bridgeFail($e->getMessage()));
}

echo "prepared_autotest_bridges=1\n";
echo "legacy_document_root={$legacyRoot}/{$country}/web\n";
echo "tk_path={$tkPath}\n";
echo "code_root={$codeRoot}\n";
echo "autotest_path={$autotestRoot}\n";
exit(0);
