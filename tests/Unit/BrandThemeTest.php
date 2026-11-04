<?php

namespace Tests\Unit;

use App\Support\BrandTheme;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class BrandThemeTest extends TestCase
{
    #[TestWith(['#facc15'])]
    #[TestWith(['#ffffff'])]
    #[TestWith(['#22d3ee'])]
    #[TestWith(['#000000'])]
    #[TestWith(['#088575'])]
    public function test_white_button_text_is_always_readable_whatever_colour_is_picked(string $colour): void
    {
        $this->assertGreaterThanOrEqual(4.5, BrandTheme::contrastWithWhite(BrandTheme::shades($colour)[600]));
    }

    public function test_a_colour_that_is_already_readable_is_left_alone(): void
    {
        $this->assertSame('#1d4ed8', BrandTheme::shades('#1d4ed8')[600]);
    }

    public function test_it_produces_theme_variables(): void
    {
        $css = BrandTheme::css('#1d4ed8', '#f59e0b', 'round');

        $this->assertStringContainsString('--color-brand-600:#1d4ed8;', $css);
        $this->assertStringContainsString('--color-accent-500:#f59e0b;', $css);
        $this->assertStringContainsString('--ui-radius:1.25rem;', $css);
    }

    #[TestWith(['red;}body{display:none'])]
    #[TestWith(['#12345'])]
    #[TestWith(['</style><script>'])]
    public function test_anything_but_a_real_colour_is_ignored(string $bad): void
    {
        $this->assertSame('', BrandTheme::css($bad, $bad, 'nonsense'));
    }
}
