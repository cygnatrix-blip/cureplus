<?php

namespace Tests\Feature;

use App\Contracts\DnsResolver;
use App\Mail\ClinicMessageMail;
use App\Models\CustomDomain;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\Domains\DomainService;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakeDns;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    private FakeDns $dns;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->dns = new FakeDns;
        $this->app->instance(DnsResolver::class, $this->dns);
    }

    private function goodCname(): void
    {
        $this->dns->records['cname'] = [config('cureplus.custom_domains.connect_host')];
    }

    /**
     * @return array{Organization, CustomDomain}
     */
    private function activeDomain(string $host = 'book.drroy.test'): array
    {
        $organization = Organization::factory()->create(['name' => 'Roy Clinic', 'slug' => 'roy-clinic']);
        $location = Location::factory()->create(['organization_id' => $organization->id]);
        $this->addMember($organization, ['owner'], location: $location);
        $domain = CustomDomain::withoutTenancy()->create(['organization_id' => $organization->id, 'hostname' => $host, 'type' => 'subdomain', 'status' => 'active']);

        return [$organization, $domain];
    }

    public function test_an_owner_adds_a_domain_and_dns_decides_its_status(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');

        $this->post(route('app.domains.store'), ['hostname' => 'Book.DrRoy.test', 'type' => 'subdomain'])->assertRedirect();

        $domain = CustomDomain::withoutTenancy()->firstOrFail();
        $this->assertSame('book.drroy.test', $domain->hostname);
        $this->assertSame('pending_dns', $domain->status);

        $this->post(route('app.domains.check', $domain))->assertSessionHas('error');

        $this->goodCname();
        $this->post(route('app.domains.check', $domain))->assertSessionHas('success');
        $this->assertSame('awaiting_parking', $domain->fresh()->status ?? CustomDomain::withoutTenancy()->find($domain->id)->status);
    }

    public function test_a_root_domain_must_have_only_our_addresses(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $ip = config('cureplus.custom_domains.ipv4');
        $this->actingAs($owner, 'web');
        $this->post(route('app.domains.store'), ['hostname' => 'drroy.test', 'type' => 'root']);
        $domain = CustomDomain::withoutTenancy()->firstOrFail();

        $this->dns->records['a'] = [$ip, '9.9.9.9'];
        $this->post(route('app.domains.check', $domain))->assertSessionHas('error', 'Remove the other A or AAAA records on this domain.');

        $this->dns->records['a'] = [$ip];
        $this->dns->records['aaaa'] = ['2001:db8::1'];
        $this->post(route('app.domains.check', $domain))->assertSessionHas('error');

        $this->dns->records['aaaa'] = [];
        $this->post(route('app.domains.check', $domain))->assertSessionHas('success');
    }

    public function test_bad_or_platform_hostnames_are_refused(): void
    {
        [, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');

        foreach (['not a domain', 'localhost', 'https://x.test', config('cureplus.domain'), 'x.'.config('cureplus.domain')] as $bad) {
            $this->from(route('app.domains.index'))->post(route('app.domains.store'), ['hostname' => $bad, 'type' => 'subdomain'])->assertSessionHasErrors('hostname');
        }

        $this->assertSame(0, CustomDomain::withoutTenancy()->count());
    }

    public function test_one_domain_cannot_be_claimed_twice(): void
    {
        $this->activeDomain();
        [, $owner] = $this->organizationWithOwner();

        $this->actingAs($owner, 'web')->from(route('app.domains.index'))->post(route('app.domains.store'), ['hostname' => 'book.drroy.test', 'type' => 'subdomain'])
            ->assertSessionHasErrors(['hostname' => 'This domain is already in use.']);
    }

    public function test_marking_as_parked_goes_live_only_once_https_answers(): void
    {
        Http::fake(['https://book.drroy.test/up' => Http::sequence()->push('', 500)->push('ok', 200)]);
        [$organization] = $this->activeDomain();
        $domain = CustomDomain::withoutTenancy()->firstOrFail();
        $domain->forceFill(['status' => 'awaiting_parking'])->save();
        $this->actingAs($this->superAdmin(), 'platform');

        $this->get(route('admin.domains.index'))->assertOk()->assertSee('book.drroy.test');
        $this->post(route('admin.domains.park', $domain->id))->assertSessionHas('status');
        $this->assertSame('parked', CustomDomain::withoutTenancy()->find($domain->id)->status);

        $this->post(route('admin.domains.retry', $domain->id))->assertSessionHas('success');
        $live = CustomDomain::withoutTenancy()->find($domain->id);
        $this->assertSame('active', $live->status);
        $this->assertNotNull($live->activated_at);
        Mail::assertQueued(ClinicMessageMail::class);
        $this->assertNotNull($organization);
    }

    public function test_a_custom_domain_serves_only_the_booking_site(): void
    {
        $this->activeDomain();

        $this->get('http://book.drroy.test/')->assertOk()->assertSee('Roy Clinic')->assertSee('Book your appointment at Roy Clinic');
        $this->get('http://book.drroy.test/sw.js')->assertOk();

        foreach (['/login', '/admin/login', '/app', '/register', '/admin', '/forgot-password', '/health', '/api/v1/medicines'] as $path) {
            $this->get('http://book.drroy.test'.$path)->assertNotFound();
        }
    }

    public function test_a_custom_domain_only_shows_its_own_organization(): void
    {
        $this->activeDomain();
        Organization::factory()->create(['slug' => 'other-clinic', 'name' => 'Other Clinic']);

        $this->get('http://book.drroy.test/other-clinic')->assertNotFound();
        $this->post('http://book.drroy.test/book/other-clinic', [])->assertNotFound();
    }

    public function test_a_doctors_link_works_on_the_custom_domain(): void
    {
        [$organization] = $this->activeDomain();
        $doctor = $this->addMember($organization, ['doctor'], User::factory()->create(['name' => 'Dr Custom']));
        Membership::withoutTenancy()->where('user_id', $doctor->id)->update(['doctor_slug' => 'dr-custom']);

        $this->get('http://book.drroy.test/dr-custom')->assertOk()->assertSee('Dr Custom')->assertSee('Book your appointment at');
    }

    public function test_unknown_unverified_and_broken_hosts_get_a_neutral_404(): void
    {
        [, $domain] = $this->activeDomain();

        $this->get('http://never-heard-of-it.test/')->assertNotFound()->assertSee('Page not found')->assertDontSee('Roy Clinic');

        $domain->forceFill(['status' => 'pending_dns'])->save();
        $this->get('http://book.drroy.test/')->assertNotFound();
    }

    public function test_a_suspended_organization_stops_serving_on_its_domain(): void
    {
        [$organization] = $this->activeDomain();
        $organization->forceFill(['status' => 'suspended'])->save();

        $this->get('http://book.drroy.test/')->assertNotFound();
    }

    public function test_the_daily_recheck_flags_broken_dns_and_tells_the_owner(): void
    {
        $this->activeDomain();
        $service = app(DomainService::class);

        $this->goodCname();
        $this->assertSame(['checked' => 1, 'broken' => 0], $service->recheckActive());

        $this->dns->records['cname'] = ['somewhere-else.test'];
        $this->assertSame(['checked' => 1, 'broken' => 1], $service->recheckActive());
        $this->assertSame('dns_broken', CustomDomain::withoutTenancy()->firstOrFail()->status);
        Mail::assertQueued(ClinicMessageMail::class);

        $this->get('http://book.drroy.test/')->assertNotFound();
    }

    public function test_custom_domains_need_a_plan_that_includes_them(): void
    {
        config(['plans.enabled' => true]);
        $this->seed(PlanSeeder::class);
        [$organization, $owner] = $this->organizationWithOwner();
        OrganizationSubscription::withoutTenancy()->create(['organization_id' => $organization->id, 'plan_id' => Plan::query()->where('code', 'starter')->value('id'), 'status' => 'active']);

        $this->actingAs($owner, 'web')->get(route('app.domains.index'))->assertRedirect(route('app.subscription.index'));
    }

    public function test_another_tenants_domain_is_404(): void
    {
        [, $domain] = $this->activeDomain();
        [, $stranger] = $this->organizationWithOwner();

        $this->actingAs($stranger, 'web')->delete(route('app.domains.destroy', $domain->id))->assertNotFound();
    }

    public function test_the_caddy_endpoint_only_approves_verified_domains(): void
    {
        $this->activeDomain();

        $this->get(route('internal.domains.allowed', ['domain' => 'book.drroy.test']), ['REMOTE_ADDR' => '127.0.0.1'])->assertOk();
        $this->get(route('internal.domains.allowed', ['domain' => 'random.test']))->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get(route('internal.domains.allowed', ['domain' => 'book.drroy.test']))->assertNotFound();
    }
}
