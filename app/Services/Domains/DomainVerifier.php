<?php

namespace App\Services\Domains;

use App\Contracts\DnsResolver;

class DomainVerifier
{
    public function __construct(private readonly DnsResolver $dns) {}

    /**
     * @return array{ok: bool, found: array{cname: list<string>, a: list<string>, aaaa: list<string>}, problem: string|null}
     */
    public function check(string $hostname, string $type): array
    {
        $found = $this->dns->lookup($hostname);
        $connectHost = strtolower((string) config('cureplus.custom_domains.connect_host'));
        $ipv4 = (string) config('cureplus.custom_domains.ipv4');
        $ipv6 = strtolower((string) config('cureplus.custom_domains.ipv6'));

        if ($type === 'subdomain') {
            // A CNAME may also show up as the resolved addresses, so either proof counts.
            $ok = in_array($connectHost, $found['cname'], true) || in_array($ipv4, $found['a'], true);

            return ['ok' => $ok, 'found' => $found, 'problem' => $ok ? null : "Add a CNAME record pointing to {$connectHost}."];
        }

        $hasOurAddress = in_array($ipv4, $found['a'], true);
        $strayV4 = array_values(array_diff($found['a'], [$ipv4]));
        $strayV6 = array_values(array_diff($found['aaaa'], [$ipv6]));

        // A leftover record sends some visitors to the old site, which also breaks certificates.
        if ($hasOurAddress && $strayV4 === [] && $strayV6 === []) {
            return ['ok' => true, 'found' => $found, 'problem' => null];
        }

        return ['ok' => false, 'found' => $found, 'problem' => $hasOurAddress
            ? 'Remove the other A or AAAA records on this domain.'
            : "Add an A record pointing to {$ipv4}."];
    }
}
