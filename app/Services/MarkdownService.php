<?php

declare(strict_types=1);

namespace App\Services;

class MarkdownService
{
    public function __construct(
        private readonly MarkdownRenderer $renderer,
        private readonly HtmlSanitizer $sanitizer,
    ) {
    }

    public function render(string $markdown): string
    {
        $html = $this->renderer->render($markdown);

        return $this->sanitizer->clean($html);
    }
}
