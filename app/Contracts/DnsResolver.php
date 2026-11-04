<?php

namespace App\Contracts;

interface DnsResolver
{
    /**
     * @return array{cname: list<string>, a: list<string>, aaaa: list<string>}
     */
    public function lookup(string $hostname): array;
}
