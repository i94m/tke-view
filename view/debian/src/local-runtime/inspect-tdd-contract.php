<?php

declare(strict_types=1);

function contractFail(string $message): int
{
    fwrite(STDERR, "Error: {$message}" . PHP_EOL);

    return 1;
}

function contractParseOptions(array $argv): array
{
    $options = [
        'contracts' => '',
        'site' => '',
        'format' => 'json',
        'app-root' => rtrim((string) getenv('VIEW_APP_ROOT'), '/'),
        'autotest-root' => rtrim((string) (getenv('VIEW_AUTOTEST_ROOT') ?: '/opt/autotest'), '/'),
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

function contractLaneSummary(array $lane): array
{
    return [
        'id' => (string) ($lane['id'] ?? ''),
        'profile' => (string) ($lane['profile'] ?? ''),
        'status' => (string) ($lane['status'] ?? ''),
        'pathPatterns' => $lane['pathPatterns'] ?? [],
        'testRoots' => $lane['testRoots'] ?? [],
        'expectsDatabase' => (bool) ($lane['expectsDatabase'] ?? false),
    ];
}

$options = contractParseOptions($_SERVER['argv'] ?? []);
$appRoot = rtrim((string) $options['app-root'], '/');
$contractsPath = $options['contracts'] !== ''
    ? $options['contracts']
    : $appRoot . '/testing-contracts.json';
$site = (string) $options['site'];
$format = $options['format'] === 'text' ? 'text' : 'json';
$autotestRoot = rtrim((string) $options['autotest-root'], '/');
$siteRoot = $site !== '' ? '/opt/sites/' . $site : '';

$autotestMounted = is_dir($autotestRoot) && is_readable($autotestRoot . '/phpunit');
$viewLoggerPresent = is_file($appRoot . '/ViewLoggerConfig.php');
$log4phpPresent = is_file($appRoot . '/log4php_config.xml');
$sharpRunnable = $autotestMounted && $viewLoggerPresent && $log4phpPresent;

if (!is_file($contractsPath)) {
    exit(contractFail("contracts file not found: {$contractsPath}"));
}

$contracts = json_decode((string) file_get_contents($contractsPath), true);
if (!is_array($contracts)) {
    exit(contractFail("invalid contracts JSON: {$contractsPath}"));
}

$activeLanes = [];
foreach ($contracts['lanes'] ?? [] as $lane) {
    if (is_array($lane) && ($lane['status'] ?? '') === 'active') {
        $activeLanes[] = contractLaneSummary($lane);
    }
}

$plannedLaneIds = [];
foreach ($contracts['plannedLanes'] ?? [] as $lane) {
    if (is_array($lane) && isset($lane['id'])) {
        $plannedLaneIds[] = (string) $lane['id'];
    }
}

$sitePlaceholder = $site !== '' ? $site : '<site>';
$commands = [
    'diagnose' => "bin/local diagnose --site {$sitePlaceholder}",
    'tddRun' => "bin/local tdd-run --site {$sitePlaceholder} --phase RED --file vivid/tests/Unit/... --methods testExample --json",
    'verify' => "bin/local verify --site {$sitePlaceholder} --file vivid/tests/Unit/... --methods testExample --json",
    'inspectContract' => "bin/local inspect-contract --site {$sitePlaceholder} --json",
    'test' => "bin/local test --site {$sitePlaceholder} --unit tests/Unit/Services/...",
];

$readiness = [
    'vividUnit' => [
        'status' => 'ready',
        'defaultForNewTdd' => true,
        'testPathExample' => 'vivid/tests/Unit/...',
        'mockExternalDeps' => true,
    ],
    'sharpAutotest' => [
        'status' => $sharpRunnable ? 'ready' : ($autotestMounted ? 'degraded' : 'blocked'),
        'defaultForNewTdd' => false,
        'testPathExample' => 'autotest/phpunit/sharp/...',
        'requiresAutotestMount' => true,
        'requiresRemoteOrConfiguredDb' => true,
        'integrationStyle' => true,
        'blockers' => array_values(array_filter([
            !$autotestMounted ? 'Set VIEW_LOCAL_AUTOTEST_PATH and run bin/local up' : '',
            !$viewLoggerPresent ? 'Missing ViewLoggerConfig.php under code mount (run bin/local up)' : '',
            !$log4phpPresent ? 'Missing log4php_config.xml under code mount (sync from site or templates)' : '',
        ])),
    ],
    'nimodWebserviceSelenium' => [
        'status' => 'not_ready',
        'notes' => 'Lanes remain in plannedLanes; use vivid-unit or sharp-service only.',
    ],
];

$payload = [
    'contractsVersion' => (int) ($contracts['version'] ?? 1),
    'defaultLane' => (string) ($contracts['defaultLane'] ?? 'vivid-unit'),
    'laneResolution' => $contracts['laneResolution'] ?? [],
    'activeLanes' => $activeLanes,
    'plannedLaneIds' => $plannedLaneIds,
    'profiles' => $contracts['profiles'] ?? [],
    'runtime' => $contracts['runtime'] ?? [],
    'documentation' => $contracts['documentation'] ?? [],
    'readiness' => $readiness,
    'environment' => [
        'appRoot' => $appRoot,
        'contractsPath' => $contractsPath,
        'contractsReadable' => is_readable($contractsPath),
        'autotestRoot' => $autotestRoot,
        'autotestMounted' => $autotestMounted,
        'sharpRunnable' => $sharpRunnable,
        'viewLoggerConfigPresent' => $viewLoggerPresent,
        'log4phpConfigPresent' => $log4phpPresent,
        'site' => $site,
        'siteRoot' => $siteRoot,
        'siteConfigPresent' => $siteRoot !== '' && is_file($siteRoot . '/config.php'),
    ],
    'recommendedCommands' => $commands,
    'modelRules' => [
        'Use bin/local tdd-run with explicit --site and --file; omit --lane unless overriding.',
        'New TDD: prefer vivid/tests/Unit with mocks; do not add new Autotest-only tests unless maintaining legacy Sharp.',
        'After GREEN intent use bin/local verify (alias for tdd-run --phase GREEN).',
        'On environment_blocked run bin/local diagnose; do not edit business code.',
        'Do not use raw phpunit, php artisan test, or docker exec autotest.',
    ],
];

if ($format === 'text') {
    echo 'contracts_version: ' . $payload['contractsVersion'] . PHP_EOL;
    echo 'default_lane: ' . $payload['defaultLane'] . PHP_EOL;
    echo 'active_lanes: ' . implode(', ', array_column($activeLanes, 'id')) . PHP_EOL;
    echo 'planned_lanes: ' . implode(', ', $plannedLaneIds) . PHP_EOL;
    echo 'autotest_mounted: ' . ($autotestMounted ? 'yes' : 'no') . PHP_EOL;
    echo 'sharp_runnable: ' . ($sharpRunnable ? 'yes' : 'no') . PHP_EOL;
    echo 'vivid_unit: ' . $readiness['vividUnit']['status'] . PHP_EOL;
    echo 'sharp_autotest: ' . $readiness['sharpAutotest']['status'] . PHP_EOL;
    echo 'recommended_tdd_run: ' . $commands['tddRun'] . PHP_EOL;
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(0);
