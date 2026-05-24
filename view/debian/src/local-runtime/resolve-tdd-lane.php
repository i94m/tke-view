<?php

declare(strict_types=1);

function laneFail(string $message): int
{
    fwrite(STDERR, "Error: {$message}" . PHP_EOL);

    return 1;
}

function laneParseOptions(array $argv): array
{
    $options = [
        'contracts' => '',
        'test-file' => '',
        'lane' => '',
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

function laneNormalizeTestFile(string $testFile): string
{
    $normalized = str_replace('\\', '/', trim($testFile));
    if (str_starts_with($normalized, './')) {
        $normalized = substr($normalized, 2);
    }

    if (str_starts_with($normalized, '/opt/tk/core/')) {
        $normalized = substr($normalized, strlen('/opt/tk/core/'));
    } elseif (str_starts_with($normalized, '/opt/tk/')) {
        $normalized = substr($normalized, strlen('/opt/tk/'));
    }

    if (str_starts_with($normalized, 'vivid/') && str_contains($normalized, 'tests/Unit/')) {
        return $normalized;
    }

    if (str_starts_with($normalized, 'tests/Unit/')) {
        return 'vivid/' . $normalized;
    }

    return $normalized;
}

function laneMatchesPatterns(string $testFile, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (@preg_match('#' . $pattern . '#', $testFile) === 1) {
            return true;
        }
    }

    return false;
}

function laneFindByPath(string $testFile, array $entries): ?array
{
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $patterns = $entry['pathPatterns'] ?? [];
        if (!is_array($patterns) || $patterns === []) {
            continue;
        }

        if (laneMatchesPatterns($testFile, $patterns)) {
            return $entry;
        }
    }

    return null;
}

function laneEntryById(string $laneId, array $entries): ?array
{
    foreach ($entries as $entry) {
        if (is_array($entry) && ($entry['id'] ?? '') === $laneId) {
            return $entry;
        }
    }

    return null;
}

function laneIsActive(array $entry): bool
{
    return ($entry['status'] ?? '') === 'active';
}

function laneAutotestRoot(): string
{
    $root = getenv('VIEW_AUTOTEST_ROOT');
    if ($root === false || trim($root) === '') {
        return '/opt/autotest';
    }

    return rtrim($root, '/');
}

function laneAutotestIsMounted(): bool
{
    return is_dir(laneAutotestRoot() . '/phpunit');
}

function laneEntryRequiresAutotest(array $entry, array $contracts): bool
{
    $profile = laneProfileFor($contracts, $entry);
    $profiles = $contracts['profiles'] ?? [];
    if (is_array($profiles) && isset($profiles[$profile]) && is_array($profiles[$profile])) {
        return (bool) ($profiles[$profile]['requiresAutotestMount'] ?? false);
    }

    $laneId = (string) ($entry['id'] ?? '');

    return str_starts_with($laneId, 'sharp')
        || str_contains($laneId, 'nimod')
        || str_contains($laneId, 'webservice');
}

function laneIsRunnable(array $entry, array $contracts): bool
{
    if (laneEntryRequiresAutotest($entry, $contracts) && !laneAutotestIsMounted()) {
        return false;
    }

    if (laneIsActive($entry)) {
        return true;
    }

    if (($entry['status'] ?? '') !== 'planned') {
        return false;
    }

    if (!laneEntryRequiresAutotest($entry, $contracts)) {
        return false;
    }

    return laneAutotestIsMounted();
}

function laneSharpPhpunitLayout(string $testFile): ?array
{
    if (!str_starts_with($testFile, 'autotest/phpunit/')) {
        return null;
    }

    $relative = substr($testFile, strlen('autotest/phpunit/'));
    $parts = explode('/', $relative, 2);
    $suite = $parts[0] ?? '';
    if ($suite === '') {
        return null;
    }

    $workdir = laneAutotestRoot() . '/phpunit';

    return [
        'phpunitWorkdir' => $workdir,
        'phpunitConfig' => $workdir . '/' . $suite . '/phpunit.xml',
        'phpunitTestPath' => $relative,
        'autotestSuite' => $suite,
    ];
}

function laneProfileFor(array $contracts, array $entry): string
{
    $profile = (string) ($entry['profile'] ?? $entry['id'] ?? '');
    if ($profile !== '') {
        return $profile;
    }

    return (string) ($contracts['defaultLane'] ?? 'vivid-unit');
}

function laneOutput(array $payload, string $format): void
{
    if ($format === 'text') {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
            }
            echo $key . ': ' . $value . PHP_EOL;
        }

        return;
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$options = laneParseOptions($_SERVER['argv'] ?? []);
$contractsPath = $options['contracts'] !== ''
    ? $options['contracts']
    : (rtrim((string) getenv('VIEW_APP_ROOT'), '/') . '/testing-contracts.json');
$testFile = laneNormalizeTestFile((string) $options['test-file']);
$requestedLane = trim((string) $options['lane']);
$format = $options['format'] === 'text' ? 'text' : 'json';

if ($testFile === '') {
    exit(laneFail('missing required --test-file'));
}

if (!is_file($contractsPath)) {
    exit(laneFail("contracts file not found: {$contractsPath}"));
}

$contracts = json_decode((string) file_get_contents($contractsPath), true);
if (!is_array($contracts)) {
    exit(laneFail("invalid contracts JSON: {$contractsPath}"));
}

$activeLanes = is_array($contracts['lanes'] ?? null) ? $contracts['lanes'] : [];
$plannedLanes = is_array($contracts['plannedLanes'] ?? null) ? $contracts['plannedLanes'] : [];
$defaultLane = (string) ($contracts['defaultLane'] ?? 'vivid-unit');

$pathLane = laneFindByPath($testFile, $activeLanes);
if ($pathLane === null) {
    $pathLane = laneFindByPath($testFile, $plannedLanes);
}

$resolvedLane = $defaultLane;
$resolvedProfile = 'vivid-unit';
$status = 'ok';
$message = '';
$laneResolutionWarning = '';

if ($requestedLane !== '') {
    $explicitEntry = laneEntryById($requestedLane, $activeLanes);
    if ($explicitEntry === null) {
        $explicitEntry = laneEntryById($requestedLane, $plannedLanes);
    }

    if ($explicitEntry === null) {
        $status = 'blocked';
        $message = "Unknown lane '{$requestedLane}'. Run: bin/local inspect-contract --site <site> --json";
    } elseif (!laneIsRunnable($explicitEntry, $contracts)) {
        $status = 'blocked';
        $resolvedLane = (string) ($explicitEntry['id'] ?? $requestedLane);
        $resolvedProfile = laneProfileFor($contracts, $explicitEntry);
        if (laneEntryRequiresAutotest($explicitEntry, $contracts) && !laneAutotestIsMounted()) {
            $message = "Lane '{$resolvedLane}' requires Autotest at /opt/autotest. "
                . "Set VIEW_LOCAL_AUTOTEST_PATH in bin/local init and run bin/local up. "
                . "Do not use docker exec autotest /run/init.sh.";
        } else {
            $message = "Lane '{$resolvedLane}' is not runnable in the unified local runtime. "
                . "See docs/local-unified-tdd-runtime-plan.md";
        }
    } else {
        $resolvedLane = (string) ($explicitEntry['id'] ?? $requestedLane);
        $resolvedProfile = laneProfileFor($contracts, $explicitEntry);
        if ($pathLane !== null && ($pathLane['id'] ?? '') !== $resolvedLane) {
            $laneResolutionWarning = 'Explicit --lane overrides path-resolved lane '
                . ($pathLane['id'] ?? 'unknown');
        }
    }
} elseif ($pathLane !== null) {
    $resolvedLane = (string) ($pathLane['id'] ?? $defaultLane);
    $resolvedProfile = laneProfileFor($contracts, $pathLane);
    if (!laneIsRunnable($pathLane, $contracts)) {
        $status = 'blocked';
        if (laneEntryRequiresAutotest($pathLane, $contracts) && !laneAutotestIsMounted()) {
            $message = "Test file matches Sharp lane '{$resolvedLane}' but Autotest is not mounted. "
                . "Run bin/local init (set Autotest directory) and bin/local up. "
                . "Do not use docker exec autotest /run/init.sh.";
        } else {
            $message = "Test file matches lane '{$resolvedLane}', which is not runnable yet. "
                . "See docs/local-unified-tdd-runtime-plan.md";
        }
    }
} else {
    $status = 'blocked';
    $message = "Cannot resolve lane from test file '{$testFile}'. "
        . "Use a path under vivid/tests/Unit/ or an active lane path from testing-contracts.json";
}

$layout = laneSharpPhpunitLayout($testFile);
$autotestMounted = laneAutotestIsMounted();

$payload = [
    'status' => $status,
    'testFile' => $testFile,
    'requestedLane' => $requestedLane,
    'resolvedLane' => $resolvedLane,
    'resolvedProfile' => $resolvedProfile,
    'pathMatchedLane' => is_array($pathLane) ? (string) ($pathLane['id'] ?? '') : '',
    'laneResolutionWarning' => $laneResolutionWarning,
    'message' => $message,
    'contractsVersion' => (int) ($contracts['version'] ?? 1),
    'autotestMounted' => $autotestMounted,
    'autotestRoot' => laneAutotestRoot(),
    'phpunitWorkdir' => is_array($layout) ? (string) ($layout['phpunitWorkdir'] ?? '') : '',
    'phpunitConfig' => is_array($layout) ? (string) ($layout['phpunitConfig'] ?? '') : '',
    'phpunitTestPath' => is_array($layout) ? (string) ($layout['phpunitTestPath'] ?? '') : '',
    'autotestSuite' => is_array($layout) ? (string) ($layout['autotestSuite'] ?? '') : '',
];

laneOutput($payload, $format);

exit($status === 'ok' ? 0 : 2);
