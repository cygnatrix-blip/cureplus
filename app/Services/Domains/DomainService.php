<?php

namespace App\Services\Domains;

use App\Http\Middleware\ResolveHost;
use App\Models\CustomDomain;
use App\Services\OrganizationOwners;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DomainService
{
    public const PARKED_LIMIT = 100;

    public function __construct(private readonly DomainVerifier $verifier, private readonly OrganizationOwners $owners) {}

    /**
     * @return array{ok: bool, problem: string|null}
     */
    public function checkDns(CustomDomain $domain): array
    {
        $result = $this->verifier->check($domain->hostname, $domain->type);

        $domain->fill([
            'dns_result' => $result['found'],
            'dns_checked_at' => now(),
            'status' => match (true) {
                ! $result['ok'] => 'pending_dns',
                $domain->parked_in_hosting => $domain->status === 'active' ? 'active' : 'parked',
                default => 'awaiting_parking',
            },
        ])->save();

        return ['ok' => $result['ok'], 'problem' => $result['problem']];
    }

    /**
     * Called after an admin parks the domain in hPanel. It only goes live once HTTPS really
     * answers with this app's token, so a patient never lands on a certificate warning or on
     * someone else's server.
     */
    public function markParked(CustomDomain $domain): bool
    {
        $domain->fill(['parked_in_hosting' => true, 'parked_at' => now(), 'status' => 'parked'])->save();

        return $this->activateIfHttpsWorks($domain);
    }

    public function activateIfHttpsWorks(CustomDomain $domain): bool
    {
        try {
            $response = Http::timeout(10)->withoutRedirecting()->get("https://{$domain->hostname}/".ResolveHost::PROBE_PATH);
            $works = $response->successful() && hash_equals(ResolveHost::probeToken($domain->hostname), trim($response->body()));
        } catch (ConnectionException) {
            $works = false;
        }

        $domain->fill(['ssl_status' => $works ? 'active' : 'pending'])->save();

        if (! $works) {
            return false;
        }

        $domain->fill(['status' => 'active', 'activated_at' => now()])->save();
        $this->owners->email((int) $domain->organization_id, 'Your domain is live', "Your booking site is now live at https://{$domain->hostname}.");

        return true;
    }

    /**
     * @return array{checked: int, broken: int}
     */
    public function recheckActive(): array
    {
        $domains = CustomDomain::withoutTenancy()->where('status', 'active')->limit(500)->get();
        $broken = 0;

        foreach ($domains as $domain) {
            $result = $this->verifier->check($domain->hostname, $domain->type);
            $domain->fill(['dns_result' => $result['found'], 'dns_checked_at' => now()]);

            if (! $result['ok']) {
                $domain->status = 'dns_broken';
                $broken++;
                $this->owners->email((int) $domain->organization_id, 'Your domain has stopped working', "The DNS for {$domain->hostname} has changed, so your booking site is offline on it. {$result['problem']}");
            }

            $domain->save();
        }

        return ['checked' => $domains->count(), 'broken' => $broken];
    }
}
