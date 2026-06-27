<?php

declare(strict_types=1);

function snapDeployPreAlphaDocument(): string
{
    $path = base_path('docs/SNAPDEPLOY-PREALPHA.md');
    expect($path)->toBeFile();

    return (string) file_get_contents($path);
}

it('documents the SnapDeploy pre-alpha safety flag and production warning', function (): void {
    $document = snapDeployPreAlphaDocument();

    expect($document)
        ->toContain('NEXTGN_PREALPHA_DEMO=true')
        ->toContain('not production')
        ->toContain('Never point it at real production tracker data');
});

it('documents all deterministic demo users', function (): void {
    $document = snapDeployPreAlphaDocument();

    foreach ([
        'mira.sysop@example.test',
        'noah.mod@example.test',
        'iris.uploads@example.test',
        'theo.archive@example.test',
        'sam.member@example.test',
        'jules.newbie@example.test',
    ] as $email) {
        expect($document)->toContain($email);
    }
});

it('keeps the SnapDeploy startup script present and executable', function (): void {
    $path = base_path('scripts/snapdeploy-start.sh');

    expect($path)
        ->toBeFile()
        ->and(is_executable($path))->toBeTrue();
});
