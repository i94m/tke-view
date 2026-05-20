<?php

function viewLocalSiteHostAliases(): array
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

function viewLocalHostLabelForSite(string $site): string
{
    $aliases = viewLocalSiteHostAliases();

    return $aliases[$site] ?? $site;
}

function viewLocalHttpHostForSite(string $site): string
{
    $template = getenv('VIEW_LOCAL_HOST_TEMPLATE');
    if ($template === false || $template === '') {
        $template = '%s.local.test';
    }

    return str_replace('%s', viewLocalHostLabelForSite($site), $template);
}

if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $documentRoot = getenv('DOCUMENT_ROOT');
    if ($documentRoot !== false && $documentRoot !== '') {
        $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
    }
}

if (empty($_SERVER['HTTP_HOST'])) {
    $viewSite = getenv('VIEW_SITE');
    if ($viewSite !== false && $viewSite !== '') {
        $_SERVER['HTTP_HOST'] = viewLocalHttpHostForSite($viewSite);
    }
}

if (empty($_SERVER['SERVER_NAME']) && !empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
}

if (empty($_SERVER['REQUEST_SCHEME'])) {
    $_SERVER['REQUEST_SCHEME'] = getenv('REQUEST_SCHEME') ?: 'http';
}
