<?php

declare(strict_types=1);

use Illuminate\Support\Str;

function livableAlphaSurfaceAudit(): string
{
    $path = base_path('docs/livable-alpha-surface-audit.md');
    expect($path)->toBeFile();

    return (string) file_get_contents($path);
}

it('documents the required livable alpha surface areas', function (): void {
    $audit = livableAlphaSurfaceAudit();

    foreach ([
        '1. Front page / discovery home',
        '2. Browse / torrent listing',
        '3. Torrent detail page',
        '4. Upload flow',
        '5. Account / user dashboard',
        '6. RSS / watch presets / notifications',
        '7. Staff / moderation',
        '8. Admin / operations',
        '9. Metadata and discovery visibility for normal users',
        '10. Navigation',
        '11. Mobile / responsive behavior',
        '12. Design / visual identity',
        '13. Empty states',
        '14. Error states',
        '15. Seeder / demo data',
        '16. Live readiness / health / monitoring',
    ] as $section) {
        expect($audit)->toContain("### {$section}");
    }
});

it('keeps the audit focused on launch gaps instead of discovery scope creep', function (): void {
    $audit = livableAlphaSurfaceAudit();

    expect($audit)
        ->toContain('Discovery scope is paused for now')
        ->toContain('No new foundation track is recommended here')
        ->toContain('P0 Launch Blocker')
        ->toContain('P1 Livable Alpha Important')
        ->toContain('P2 Polish / Later')
        ->toContain('Parked / Not now')
        ->toContain('Recommended post-Slice-101 roadmap')
        ->toContain('More discovery layers')
        ->toContain('Cosmetic redesign without UX blocker value');
});

it('separates true launch blockers from nice to have work', function (): void {
    $audit = livableAlphaSurfaceAudit();
    $launchBlockersSection = Str::between($audit, '## Launch blockers', '## P1 Livable Alpha priorities');

    expect($launchBlockersSection)
        ->toContain('Upload flow hardening')
        ->toContain('Account and tracker setup hub')
        ->toContain('Seeder/demo data')
        ->not->toContain('More discovery layers')
        ->not->toContain('Advanced recommendation tuning')
        ->not->toContain('Full community/social layer');
});
