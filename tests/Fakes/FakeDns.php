<?php

namespace Tests\Fakes;

use App\Contracts\DnsResolver;

class FakeDns implements DnsResolver
{
    /** @var array{cname: list<string>, a: list<string>, aaaa: list<string>} */
    public array $records = ['cname' => [], 'a' => [], 'aaaa' => []];

    public function lookup(string $hostname): array
    {
        return $this->records;
    }
}
