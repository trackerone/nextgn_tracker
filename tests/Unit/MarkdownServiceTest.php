<?php

declare(strict_types=1);

use App\Services\MarkdownService;

it('renders markdown to sanitized html', function (): void {
    $service = app(MarkdownService::class);

    $html = $service->render('**bold** *italic* [link](https://example.com)');

    expect($html)
        ->toContain('<strong>bold</strong>')
        ->toContain('<em>italic</em>')
        ->toContain('<a href="https://example.com">link</a>');
});

it('strips dangerous markup from html output', function (): void {
    $service = app(MarkdownService::class);

    $markdown = '<script>alert(1)</script> [hack](javascript:alert(1)) <a href="https://example.com" onclick="evil()">link</a>';
    $html = $service->render($markdown);

    expect($html)
        ->not()->toContain('<script')
        ->not()->toContain('onclick=')
        ->not()->toContain('javascript:alert')
        ->toContain('<a href="https://example.com">link</a>');
});
