<?php

declare(strict_types=1);

function tddFail(string $message): int
{
    fwrite(STDERR, "Error: {$message}" . PHP_EOL);

    return 1;
}

function tddReadFile(string $path): string
{
    if ($path === '' || !is_file($path)) {
        return '';
    }

    $contents = file_get_contents($path);

    return $contents === false ? '' : $contents;
}

function tddTrimmedSummary(string $stdout, string $stderr, int $limit = 1200): string
{
    $summary = trim($stderr);
    if ($summary === '') {
        $summary = trim($stdout);
    }

    if ($summary === '') {
        return 'none';
    }

    if (strlen($summary) <= $limit) {
        return $summary;
    }

    return substr($summary, 0, $limit - 3) . '...';
}

function tddJsonEscape(string $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '""';
}

function tddParseCsv(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }

    $items = [];
    foreach (explode(',', $raw) as $item) {
        $item = trim($item);
        if ($item === '') {
            continue;
        }
        $items[] = $item;
    }

    return array_values(array_unique($items));
}

function tddMethodMatches(string $testcaseName, string $targetMethod): bool
{
    return $testcaseName === $targetMethod
        || str_starts_with($testcaseName, $targetMethod . ' with data set ');
}

function tddClassifyFailureCategory(string $summary): string
{
    $lower = strtolower($summary);

    $patterns = [
        'docker' => ['container is not running', 'docker is not installed', 'docker is not running', 'error response from daemon'],
        'site_config' => ['missing --site', 'site_root does not exist', 'site config not found'],
        'phpunit_entrypoint' => ['phpunit entrypoint not found', 'failed to resolve phpunit entrypoint'],
        'bootstrap' => [
            'unable to resolve document_root',
            'php bootstrap file not found',
            'runtime inspector file not found',
            'site config bootstrap not found',
            'undefined constant',
        ],
        'test_discovery' => ['class', 'method', 'not found', 'no tests executed'],
    ];

    foreach ($patterns as $category => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return $category;
            }
        }
    }

    return 'runtime';
}

function tddCollectTestcases(SimpleXMLElement $xml): array
{
    $results = [];

    $nodes = $xml->xpath('//testcase');
    if ($nodes === false) {
        return [];
    }

    foreach ($nodes as $testcase) {
        $name = (string) ($testcase['name'] ?? '');
        $className = (string) ($testcase['classname'] ?? '');
        $status = 'pass';
        $message = '';
        $messageType = 'none';

        if (isset($testcase->failure)) {
            $status = 'failure';
            $messageType = 'failure';
            $message = trim((string) ($testcase->failure['message'] ?? ''));
            if ($message === '') {
                $message = trim((string) $testcase->failure);
            }
        } elseif (isset($testcase->error)) {
            $status = 'error';
            $messageType = 'error';
            $message = trim((string) ($testcase->error['message'] ?? ''));
            if ($message === '') {
                $message = trim((string) $testcase->error);
            }
        } elseif (isset($testcase->skipped)) {
            $status = 'skipped';
            $messageType = 'skipped';
            $message = trim((string) ($testcase->skipped['message'] ?? ''));
            if ($message === '') {
                $message = trim((string) $testcase->skipped);
            }
        }

        $results[] = [
            'name' => $name,
            'className' => $className,
            'status' => $status,
            'messageType' => $messageType,
            'messageSummary' => $message === '' ? 'none' : $message,
        ];
    }

    return $results;
}

function tddTargetedResults(array $allResults, array $targetMethods): array
{
    if ($targetMethods === []) {
        return $allResults;
    }

    $targeted = [];
    foreach ($allResults as $result) {
        foreach ($targetMethods as $method) {
            if (tddMethodMatches($result['name'], $method)) {
                $targeted[] = $result;
                break;
            }
        }
    }

    return $targeted;
}

function tddMissingMethods(array $allResults, array $targetMethods): array
{
    if ($targetMethods === []) {
        return [];
    }

    $missing = [];
    foreach ($targetMethods as $method) {
        $matched = false;
        foreach ($allResults as $result) {
            if (tddMethodMatches($result['name'], $method)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $missing[] = $method;
        }
    }

    return $missing;
}

function tddResultExitCode(string $resultType): int
{
    return match ($resultType) {
        'valid_red', 'valid_green' => 0,
        'environment_blocked' => 3,
        default => 2,
    };
}

function tddParseOptions(array $argv): array
{
    $options = [];
    $expectsValue = [
        'format',
        'phase',
        'site',
        'lane',
        'test-file',
        'methods',
        'resolved-phpunit-path',
        'command-used',
        'raw-exit-code',
        'junit',
        'stdout-file',
        'stderr-file',
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

        if (!in_array($name, $expectsValue, true)) {
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

function tddTextOutput(array $result): string
{
    $lines = [
        'phase: ' . $result['phase'],
        'site: ' . $result['site'],
        'lane: ' . $result['lane'],
        'test_file: ' . $result['testFile'],
        'targeted_methods: ' . ($result['targetedMethods'] === [] ? 'all' : implode(', ', $result['targetedMethods'])),
        'result_type: ' . $result['resultType'],
        'failure_category: ' . $result['failureCategory'],
        'resolved_phpunit_path: ' . ($result['resolvedPhpunitPath'] === '' ? '(unresolved)' : $result['resolvedPhpunitPath']),
        'raw_exit_code: ' . (string) $result['rawExitCode'],
        'command_used: ' . ($result['commandUsed'] === '' ? 'none' : $result['commandUsed']),
        'assertion_summary: ' . $result['assertionSummary'],
        'environment_summary: ' . $result['environmentSummary'],
        'next: ' . $result['next'],
    ];

    if ($result['missingMethods'] !== []) {
        $lines[] = 'missing_methods: ' . implode(', ', $result['missingMethods']);
    }

    if ($result['methodResults'] !== []) {
        $lines[] = 'method_results:';
        foreach ($result['methodResults'] as $methodResult) {
            $lines[] = sprintf(
                '  - %s [%s] %s: %s',
                $methodResult['name'],
                $methodResult['className'] === '' ? 'unknown-class' : $methodResult['className'],
                $methodResult['status'],
                $methodResult['messageSummary']
            );
        }
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

$options = tddParseOptions($_SERVER['argv'] ?? []);

$format = $options['format'] ?? 'json';
$phase = strtoupper((string) ($options['phase'] ?? ''));
$site = (string) ($options['site'] ?? '');
$lane = (string) ($options['lane'] ?? 'vivid-unit');
$testFile = (string) ($options['test-file'] ?? '');
$methods = tddParseCsv($options['methods'] ?? null);
$resolvedPhpunitPath = (string) ($options['resolved-phpunit-path'] ?? '');
$commandUsed = (string) ($options['command-used'] ?? '');
$rawExitCode = (int) ($options['raw-exit-code'] ?? 0);
$junitPath = (string) ($options['junit'] ?? '');
$stdout = tddReadFile((string) ($options['stdout-file'] ?? ''));
$stderr = tddReadFile((string) ($options['stderr-file'] ?? ''));

if (!in_array($format, ['json', 'text'], true)) {
    exit(tddFail("unsupported --format '{$format}'"));
}

if (!in_array($phase, ['RED', 'GREEN'], true)) {
    exit(tddFail('missing or unsupported --phase; expected RED or GREEN'));
}

if ($testFile === '') {
    exit(tddFail('missing required --test-file'));
}

$result = [
    'phase' => $phase,
    'site' => $site,
    'lane' => $lane,
    'testFile' => $testFile,
    'targetedMethods' => $methods,
    'resolvedPhpunitPath' => $resolvedPhpunitPath,
    'commandUsed' => $commandUsed,
    'rawExitCode' => $rawExitCode,
    'resultType' => 'environment_blocked',
    'verdict' => 'environment_blocked',
    'failureCategory' => 'runtime',
    'assertionSummary' => 'none',
    'environmentSummary' => tddTrimmedSummary($stdout, $stderr),
    'missingMethods' => [],
    'methodResults' => [],
    'next' => 'fix_environment',
];

$noTestsExecuted = stripos($result['environmentSummary'], 'No tests executed!') !== false;

libxml_use_internal_errors(true);
$xml = null;
if ($junitPath !== '' && is_file($junitPath) && filesize($junitPath) > 0) {
    $xml = simplexml_load_file($junitPath);
}

if ($xml instanceof SimpleXMLElement) {
    $allResults = tddCollectTestcases($xml);
    if ($allResults !== []) {
        $targetedResults = tddTargetedResults($allResults, $methods);
        $missingMethods = tddMissingMethods($allResults, $methods);

        $result['methodResults'] = $targetedResults;
        $result['missingMethods'] = $missingMethods;

        $hasPass = false;
        $hasFailure = false;
        $hasError = false;
        $hasSkipped = false;

        foreach ($targetedResults as $targetedResult) {
            $status = $targetedResult['status'];
            $hasPass = $hasPass || $status === 'pass';
            $hasFailure = $hasFailure || $status === 'failure';
            $hasError = $hasError || $status === 'error';
            $hasSkipped = $hasSkipped || $status === 'skipped';
        }

        $targetCount = count($targetedResults);
        $allMatched = $targetCount > 0 && $missingMethods === [];

        if ($phase === 'RED') {
            if ($allMatched && !$hasPass && !$hasError && !$hasSkipped && $hasFailure) {
                $result['resultType'] = 'valid_red';
                $result['verdict'] = 'valid_red';
                $result['failureCategory'] = 'assertion';
                $result['assertionSummary'] = "{$targetCount} targeted test(s) failed by assertion as expected";
                $result['environmentSummary'] = 'none';
                $result['next'] = 'implement';
            } else {
                $result['resultType'] = 'invalid_red';
                $result['verdict'] = 'invalid_red';
                $result['failureCategory'] = $hasError || $hasSkipped || $missingMethods !== []
                    ? 'non_assertion'
                    : 'assertion';
                $result['assertionSummary'] = $hasFailure
                    ? "{$targetCount} targeted test(s) failed, but RED was not valid"
                    : 'none';
                $result['environmentSummary'] = $missingMethods !== []
                    ? 'Targeted methods missing from executed output'
                    : tddTrimmedSummary($stdout, $stderr);
                $result['next'] = 'fix_test_or_stub';
            }
        } else {
            if ($allMatched && !$hasFailure && !$hasError && !$hasSkipped && $hasPass) {
                $result['resultType'] = 'valid_green';
                $result['verdict'] = 'valid_green';
                $result['failureCategory'] = 'none';
                $result['assertionSummary'] = "{$targetCount} targeted test(s) passed";
                $result['environmentSummary'] = 'none';
                $result['next'] = 'complete';
            } else {
                $result['resultType'] = 'failed_green';
                $result['verdict'] = 'failed_green';
                $result['failureCategory'] = $hasError || $hasSkipped || $missingMethods !== []
                    ? 'non_assertion'
                    : 'assertion';
                $result['assertionSummary'] = $hasFailure
                    ? "{$targetCount} targeted test(s) still failing"
                    : 'none';
                $result['environmentSummary'] = $missingMethods !== []
                    ? 'Targeted methods missing from executed output'
                    : tddTrimmedSummary($stdout, $stderr);
                $result['next'] = 'review';
            }
        }
    }
}

if ($result['resultType'] === 'environment_blocked' && $result['missingMethods'] === [] && $methods !== [] && $noTestsExecuted) {
    $result['missingMethods'] = $methods;
    $result['next'] = 'check_method_names';
}

if ($result['resultType'] === 'environment_blocked') {
    $result['failureCategory'] = tddClassifyFailureCategory($result['environmentSummary']);
}

if ($format === 'json') {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} else {
    echo tddTextOutput($result);
}

exit(tddResultExitCode($result['resultType']));
