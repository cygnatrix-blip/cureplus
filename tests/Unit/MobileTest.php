<?php

namespace Tests\Unit;

use App\Support\Mobile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MobileTest extends TestCase
{
    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function numbers(): array
    {
        return [
            'plain' => ['9812345678', '+919812345678'],
            'with code' => ['+919812345678', '+919812345678'],
            'code no plus' => ['919812345678', '+919812345678'],
            'trunk zero' => ['09812345678', '+919812345678'],
            'spaces and dash' => ['+91 98123-45678', '+919812345678'],
            'landline-like start' => ['1234567890', null],
            'too short' => ['98123', null],
            'too long' => ['98123456789012', null],
            'letters' => ['abcdefghij', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalize(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, Mobile::normalize($input));
    }
}
