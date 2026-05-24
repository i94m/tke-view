<?php

declare(strict_types=1);

/**
 * Container-side TDD preflight. Expects host/container to have already resolved lane.
 * Do not call resolve-tdd-lane.php again — read RESOLVED_LANE / RESOLVED_PROFILE from the environment.
 */

function preflightFail(string $message): int
{
    fwrite(STDERR, "Error: {$message}" . PHP_EOL);

    return 1;
}

function preflightParseOptions(array $argv): array
{
    $options = [
        'site' => '',
        'test-file' => '',
        'format' => 'json',
    ];

    for ($index = 1, $count = count($argv); $index < $count; $index++) {
        $arg = $argv[$index];
        if (!str_starts_with($arg, '--')) {
            continue;
        }

        $name = substr($arg, 2);
        $value = null;

        if (str_contains($name, '=')) {
            [$name, $value] = explode('=', $name, 2);
        }

        if (!array_key_exists($name, $options)) {
            continue;
        }

        if ($value === null) {
            $index++;
            if ($index >= $count) {
                break;
            }
            $value = $argv[$index];
        }

        $options[$name] = $value;
    }

    return $options;
}

function preflightCheck(bool $ok, string $key): array
{
    return ['key' => $key, 'ok' => $ok];
}

function preflightResolvePhpunitPath(string $appRoot, string $site, string $profile): array
{
    $bootstrap = getenv('VIEW_LOCAL_PHP_BOOTSTRAP') ?: '/usr/local/lib/tke-local/php-cli-bootstrap.php';
    $helper = rtrim($appRoot, '/') . '/vivid/tests/bootstrap.php';

    if (!is_file($helper)) {
        return ['ok' => false, 'path' => '', 'detail' => "PHPUnit bootstrap helper missing: {$helper}"];
    }

    if (!is_file($bootstrap)) {
        return ['ok' => false, 'path' => '', 'detail' => "PHP bootstrap missing: {$bootstrap}"];
    }

    $cmd = [
        'php',
        '-d',
        'auto_prepend_file=' . $bootstrap,
        $helper,
        '--print-phpunit-bin',
    ];
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $env = $_ENV;
    $env['VIEW_SITE'] = $site;
    $env['VIEW_APP_ROOT'] = $appRoot;
    $env['DOCUMENT_ROOT'] = rtrim($appRoot, '/') . '/web';

    $process = proc_open($cmd, $descriptorSpec, $pipes, null, $env);
    if (!is_resource($process)) {
        return ['ok' => false, 'path' => '', 'detail' => 'Failed to run PHPUnit bootstrap helper'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $path = trim((string) $stdout);
    if ($exitCode !== 0 || $path === '') {
        return [
            'ok' => false,
            'path' => '',
            'detail' => trim($stderr !== '' ? $stderr : $stdout) ?: 'PHPUnit bootstrap helper returned empty path',
        ];
    }

    if (!is_file($path)) {
        return ['ok' => false, 'path' => $path, 'detail' => "PHPUnit binary not found: {$path}"];
    }

    return ['ok' => true, 'path' => $path, 'detail' => ''];
}

function preflightSharpPhpunitConfig(string $testFile, string $autotestRoot): array
{
    $normalized = str_replace('\\', '/', ltrim($testFile, './'));
    if (!str_starts_with($normalized, 'autotest/phpunit/')) {
        return ['ok' => false, 'path' => '', 'detail' => 'Invalid autotest test file path'];
    }

    $relative = substr($normalized, strlen('autotest/phpunit/'));
    $suite = explode('/', $relative)[0] ?? '';
    if ($suite === '') {
        return ['ok' => false, 'path' => '', 'detail' => 'Cannot determine autotest suite from test file'];
    }

    $config = rtrim($autotestRoot, '/') . '/phpunit/' . $suite . '/phpunit.xml';
    if (!is_file($config)) {
        return ['ok' => false, 'path' => $config, 'detail' => "phpunit.xml not found: {$config}"];
    }

    return ['ok' => true, 'path' => $config, 'detail' => ''];
}

$options = preflightParseOptions($_SERVER['argv'] ?? []);
$site = trim((string) $options['site']);
$testFile = trim((string) $options['test-file']);
$format = $options['format'] === 'text' ? 'text' : 'json';

$resolvedLane = trim((string) getenv('RESOLVED_LANE'));
$resolvedProfile = trim((string) getenv('RESOLVED_PROFILE'));

if ($site === '') {
    $site = trim((string) getenv('VIEW_SITE'));
}

$appRoot = rtrim((string) (getenv('VIEW_APP_ROOT') ?: '/opt/tk/core'), '/');
$siteRootBase = rtrim((string) (getenv('VIEW_SITE_ROOT_BASE') ?: '/opt/sites'), '/');
$autotestRoot = rtrim((string) (getenv('VIEW_AUTOTEST_ROOT') ?: '/opt/autotest'), '/');

if ($testFile === '') {
    $testFile = trim((string) getenv('RESOLVED_TEST_FILE'));
}

$checks = [];
$blockers = [];

if ($resolvedLane === '') {
    $checks['resolved_lane'] = preflightCheck(false, 'resolved_lane');
    $blockers[] = 'resolved_lane';
} else {
    $checks['resolved_lane'] = preflightCheck(true, 'resolved_lane');
}

if ($resolvedProfile === '') {
    $checks['resolved_profile'] = preflightCheck(false, 'resolved_profile');
    $blockers[] = 'resolved_profile';
} else {
    $checks['resolved_profile'] = preflightCheck(true, 'resolved_profile');
}

if ($site === '') {
    $checks['site'] = preflightCheck(false, 'site');
    $blockers[] = 'site';
} else {
    $checks['site'] = preflightCheck(true, 'site');
}

$siteDir = $site !== '' ? $siteRootBase . '/' . $site : '';
$siteConfig = $siteDir !== '' ? $siteDir . '/config.php' : '';

if ($site === '' || !is_dir($siteDir)) {
    $checks['site_exists'] = preflightCheck(false, 'site_exists');
    $blockers[] = 'site_exists';
} else {
    $checks['site_exists'] = preflightCheck(true, 'site_exists');
}

if ($siteConfig === '' || !is_readable($siteConfig)) {
    $checks['site_config_readable'] = preflightCheck(false, 'site_config_readable');
    $blockers[] = 'site_config_readable';
} else {
    $checks['site_config_readable'] = preflightCheck(true, 'site_config_readable');
}

$loggerConfig = $appRoot . '/log4php_config.xml';
$loggerPhp = $appRoot . '/ViewLoggerConfig.php';
$checks['view_logger_config'] = preflightCheck(is_file($loggerPhp), 'view_logger_config');
$checks['log4php_config'] = preflightCheck(is_file($loggerConfig), 'log4php_config');
if (!is_file($loggerPhp)) {
    $blockers[] = 'view_logger_config';
}
if (!is_file($loggerConfig)) {
    $blockers[] = 'log4php_config';
}

$needsAutotest = $resolvedProfile === 'sharp-unit'
    || str_starts_with($resolvedLane, 'sharp-')
    || str_starts_with($testFile, 'autotest/phpunit/');

if ($needsAutotest) {
    $autotestOk = is_dir($autotestRoot . '/phpunit');
    $checks['autotest_mounted'] = preflightCheck($autotestOk, 'autotest_mounted');
    if (!$autotestOk) {
        $blockers[] = 'autotest_mounted';
    } elseif ($testFile !== '') {
        $sharpConfig = preflightSharpPhpunitConfig($testFile, $autotestRoot);
        $checks['phpunit_config'] = preflightCheck($sharpConfig['ok'], 'phpunit_config');
        if (!$sharpConfig['ok']) {
            $blockers[] = 'phpunit_config';
        }
    }
} else {
    $phpunit = preflightResolvePhpunitPath($appRoot, $site, $resolvedProfile !== '' ? $resolvedProfile : 'vivid-unit');
    $checks['phpunit_path'] = preflightCheck($phpunit['ok'], 'phpunit_path');
    if (!$phpunit['ok']) {
        $blockers[] = 'phpunit_path';
    }
}

$status = $blockers === [] ? 'ready' : 'blocked';
$hint = $status === 'ready'
    ? ''
    : 'Run: bin/local diagnose --site ' . ($site !== '' ? $site : '<site>');

$payload = [
    'status' => $status,
    'site' => $site,
    'testFile' => $testFile,
    'resolvedLane' => $resolvedLane,
    'resolvedProfile' => $resolvedProfile,
    'checks' => $checks,
    'blockers' => array_values(array_unique($blockers)),
    'hint' => $hint,
];

if ($format === 'text') {
    foreach ($payload as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        }
        echo $key . ': ' . $value . PHP_EOL;
    }
    exit($status === 'ready' ? 0 : 2);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($status === 'ready' ? 0 : 2);
