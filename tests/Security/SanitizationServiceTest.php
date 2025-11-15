<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Services\Security\SanitizationService;
use Tests\TestCase;

final class SanitizationServiceTest extends TestCase
{
    public function test_it_sanitizes_allowed_markup(): void
    {
        config()->set('security.max_input_length', 12000);
        $service = new SanitizationService();

        $input = "<script>alert('x')</script><strong>bold</strong><em>ok</em>";

        $this->assertSame('<strong>bold</strong><em>ok</em>', $service->sanitizeString($input));
    }

    public function test_it_removes_dangerous_attributes(): void
    {
        $service = new SanitizationService();

        $html = '<a href="javascript:alert(1)" onclick="evil()">payload</a>';

        $this->assertSame('<a href="#">payload</a>', $service->sanitizeHtmlDocument($html));
    }

    public function test_it_can_preserve_inline_styles_for_documents(): void
    {
        $service = new SanitizationService();

        $html = '<div style="color: red" onclick="evil()">payload</div>';

        $this->assertSame('<div style="color: red">payload</div>', $service->sanitizeHtmlDocument($html, ['style']));
    }

    public function test_it_limits_input_length(): void
    {
        config()->set('security.max_input_length', 5);
        $service = new SanitizationService();

        $this->assertSame('hello', $service->sanitizeString('helloworld'));
    }
}
