<?php

namespace Tests\Fakes;

use App\Contracts\SmsProvider;

class FakeSms implements SmsProvider
{
    /** @var list<array{string, string}> */
    public array $sent = [];

    public function send(string $mobile, #[\SensitiveParameter] string $message): void
    {
        $this->sent[] = [$mobile, $message];
    }

    public function lastCode(): string
    {
        preg_match('/\d{6}/', (string) end($this->sent)[1], $match);

        return $match[0];
    }
}
