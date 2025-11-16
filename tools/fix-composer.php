<?php

declare(strict_types=1);

$file = getcwd().'/composer.json';

if (! file_exists($file)) {
    fwrite(STDERR, sprintf("composer.json not found in %s\n", getcwd()));
    exit(0);
}

$json = json_decode((string) file_get_contents($file), true);

if (! is_array($json)) {
    fwrite(STDERR, "Failed to parse composer.json\n");
    exit(1);
}

$changed = false;

$stripIlluminate = function (array &$section) use (&$changed): void {
    foreach (array_keys($section) as $package) {
        if (str_starts_with($package, 'illuminate/')) {
            unset($section[$package]);
            $changed = true;
        }
    }
};

$removePackages = function (array &$section, array $packages) use (&$changed): void {
    foreach ($packages as $package) {
        if (array_key_exists($package, $section)) {
            unset($section[$package]);
            $changed = true;
        }
    }
};

if (isset($json['require']) && is_array($json['require'])) {
    $stripIlluminate($json['require']);
}

if (isset($json['require-dev']) && is_array($json['require-dev'])) {
    $stripIlluminate($json['require-dev']);
}

$legacy = ['fideloper/proxy'];

if (isset($json['require']) && is_array($json['require'])) {
    $removePackages($json['require'], $legacy);
}

if (isset($json['require-dev']) && is_array($json['require-dev'])) {
    $removePackages($json['require-dev'], $legacy);
}

if (! isset($json['require']) || ! is_array($json['require'])) {
    $json['require'] = [];
}

if (! isset($json['require']['laravel/framework'])) {
    $json['require']['laravel/framework'] = '^11.0';
    $changed = true;
} else {
    $current = (string) $json['require']['laravel/framework'];

    if (! preg_match('/(^|\s)(\^|~)?11(\.|$)/', $current)) {
        $json['require']['laravel/framework'] = '^11.0';
        $changed = true;
    }
}

if (isset($json['conflict']) && is_array($json['conflict'])) {
    if (array_key_exists('illuminate/*', $json['conflict'])) {
        unset($json['conflict']['illuminate/*']);
        $changed = true;
    }

    if ($json['conflict'] === []) {
        unset($json['conflict']);
        $changed = true;
    }
}

if ($changed) {
    file_put_contents(
        $file,
        json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
    );

    echo sprintf("composer.json updated in %s\n", getcwd());
} else {
    echo sprintf("composer.json already OK in %s\n", getcwd());
}
