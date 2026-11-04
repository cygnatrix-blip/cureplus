<?php

namespace App\Services\Domains;

use App\Contracts\DnsResolver;

class PhpDnsResolver implements DnsResolver
{
    public function lookup(string $hostname): array
    {
        return [
            'cname' => $this->values($hostname, DNS_CNAME, 'target'),
            'a' => $this->values($hostname, DNS_A, 'ip'),
            'aaaa' => $this->values($hostname, DNS_AAAA, 'ipv6'),
        ];
    }

    /**
     * @return list<string>
     */
    private function values(string $hostname, int $type, string $field): array
    {
        $records = @dns_get_record($hostname, $type);

        return is_array($records)
            ? array_map(fn (array $record) => strtolower(rtrim((string) ($record[$field] ?? ''), '.')), $records)
            : [];
    }
}
