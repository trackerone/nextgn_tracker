<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Services\Security\SanitizationService;
use Tests\TestCase;

final class SanitizationServiceTest extends TestCase
{
    public function testItSanitizesAllowedMarkup(): void
    {
        config()->set('security.max_input_length', 12000);
        $service = new SanitizationService();

        $input = "<script>alert('x')</script><strong>bold</strong><em>ok</em>";

        $this->assertSame('<strong>bold</strong><em>ok</em>', $service->sanitizeString($input));
    }

    public function testItRemovesDangerousAttributes(): void
    {
        $service = new SanitizationService();

        $html = '<a href="javascript:alert(1)" onclick="evil()">payload</a>';

        $this->assertSame('<a href="#">payload</a>', $service->sanitizeHtmlDocument($html));
    }

    public function testItLimitsInputLength(): void
    {
        config()->set('security.max_input_length', 5);
        $service = new SanitizationService();

        $this->assertSame('hello', $service->sanitizeString('helloworld'));
    }
}
