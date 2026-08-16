<?php

declare(strict_types=1);

$buildDirectory = $argv[1] ?? dirname(__DIR__).'/public/build';
$manifestPath = $buildDirectory.'/manifest.json';

if (! is_file($manifestPath) || filesize($manifestPath) === 0) {
    fwrite(STDERR, "Vite manifest is missing or empty: {$manifestPath}\n");
    exit(1);
}

try {
    $manifest = json_decode(
        file_get_contents($manifestPath) ?: '',
        true,
        flags: JSON_THROW_ON_ERROR,
    );
} catch (JsonException $exception) {
    fwrite(STDERR, "Vite manifest is invalid JSON: {$exception->getMessage()}\n");
    exit(1);
}

if (! is_array($manifest)) {
    fwrite(STDERR, "Vite manifest must contain a JSON object.\n");
    exit(1);
}

$requiredEntries = [
    'resources/css/app.css',
    'resources/js/app.tsx',
];
$resolvedBuildDirectory = realpath($buildDirectory);

if ($resolvedBuildDirectory === false) {
    fwrite(STDERR, "Vite build directory is missing: {$buildDirectory}\n");
    exit(1);
}

foreach ($requiredEntries as $source) {
    $file = $manifest[$source]['file'] ?? null;

    if (! is_string($file) || $file === '') {
        fwrite(STDERR, "Vite manifest is missing an asset for {$source}.\n");
        exit(1);
    }

    $assetPath = realpath($resolvedBuildDirectory.'/'.ltrim($file, '/'));

    if (
        $assetPath === false
        || ! is_file($assetPath)
        || ! str_starts_with($assetPath, $resolvedBuildDirectory.DIRECTORY_SEPARATOR)
    ) {
        fwrite(STDERR, "Vite asset for {$source} is missing from the image: {$file}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Verified Vite manifest and compiled assets.\n");
