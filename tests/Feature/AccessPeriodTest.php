<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Jobs\SendMessage;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\PlatformAdmin;
use App\Services\OrganizationVerification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AccessPeriodTest extends TestCase
{
    public function test_a_date_can_be_set_when_approving(): void
    {
        Bus::fake([SendMessage::class]);
        $organization = Organization::factory()->pending()->create();
        Location::factory()->create(['organization_id' => $organization->id]);
        OrganizationDocument::factory()->create(['organization_id' => $organization->id]);
        $date = today()->addMonths(6)->toDateString();

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.organizations.approve', $organization), ['access_until' => $date])->assertSessionHas('success');

        $this->assertSame($date, $organization->fresh()->access_until->toDateString());
    }

    public function test_a_past_date_is_refused_when_approving(): void
    {
        $organization = Organization::factory()->pending()->create();
        Location::factory()->create(['organization_id' => $organization->id]);
        OrganizationDocument::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.organizations.approve', $organization), ['access_until' => today()->subDay()->toDateString()])->assertSessionHasErrors('access_until');
        $this->assertFalse($organization->fresh()->isActive());
    }

    public function test_the_last_day_still_works_and_the_next_day_locks_everyone_out(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $organization->forceFill(['access_until' => today()])->save();
        $this->actingAs($owner, 'web');

        $this->get(route('app.dashboard'))->assertOk();
        $this->get('/'.$organization->slug)->assertOk();

        $this->travel(1)->days();

        $this->get(route('app.dashboard'))->assertRedirect(route('app.organization.status'));
        $this->get(route('app.organization.status'))->assertOk()->assertSee('Your access ended on')->assertSee('Your records are safe');
        $this->get(route('app.patients.index'))->assertRedirect(route('app.organization.status'));
        $this->get('/'.$organization->slug)->assertNotFound();
        $this->assertSame(today()->subDay()->toDateString(), $organization->fresh()->access_until->toDateString());
    }

    public function test_the_queue_screen_stops_when_access_ends(): void
    {
        [$organization, , $location] = $this->organizationWithOwner();
        $location->forceFill(['display_token' => 'screen-token'])->saveQuietly();

        $this->get(route('display.show', 'screen-token'))->assertOk();

        $organization->forceFill(['access_until' => today()->subDay()])->save();

        $this->get(route('display.show', 'screen-token'))->assertNotFound();
    }

    public function test_updating_the_date_gives_access_back_and_is_audited(): void
    {
        Bus::fake([SendMessage::class]);
        [$organization, $owner] = $this->organizationWithOwner();
        $organization->forceFill(['access_until' => today()->subDay()])->save();
        $admin = $this->superAdmin();
        $date = today()->addYear()->toDateString();

        $this->actingAs($admin, 'platform')->post(route('admin.organizations.access', $organization), ['access_until' => $date])->assertSessionHas('success');

        $this->assertTrue($organization->fresh()->isActive());
        $log = AuditLog::withoutTenancy()->where('action', 'organizations.access_changed')->firstOrFail();
        $this->assertSame($date, $log->metadata['to']);
        $this->assertSame($admin->id, (int) $log->actor_id);
        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'organization_access_changed' && $job->to === $owner->email);

        $this->actingAs($owner, 'web')->get(route('app.dashboard'))->assertOk();
    }

    public function test_extending_by_months_counts_from_the_later_of_today_and_the_current_date(): void
    {
        Bus::fake([SendMessage::class]);
        [$organization] = $this->organizationWithOwner();
        $this->actingAs($this->superAdmin(), 'platform');

        $organization->forceFill(['access_until' => today()->addDays(10)])->save();
        $this->post(route('admin.organizations.access', $organization), ['months' => '3']);
        $this->assertSame(today()->addDays(10)->addMonths(3)->toDateString(), $organization->fresh()->access_until->toDateString());

        $organization->forceFill(['access_until' => today()->subMonth()])->save();
        $this->post(route('admin.organizations.access', $organization), ['months' => '1']);
        $this->assertSame(today()->addMonth()->toDateString(), $organization->fresh()->access_until->toDateString());
    }

    public function test_leaving_the_date_empty_means_no_end_date(): void
    {
        Bus::fake([SendMessage::class]);
        [$organization] = $this->organizationWithOwner();
        $organization->forceFill(['access_until' => today()->subDay()])->save();

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.organizations.access', $organization), ['access_until' => ''])->assertSessionHas('success');

        $this->assertNull($organization->fresh()->access_until);
        $this->assertTrue($organization->fresh()->isActive());
    }

    public function test_a_suspended_practice_stays_suspended_whatever_the_date(): void
    {
        Bus::fake([SendMessage::class]);
        [$organization] = $this->organizationWithOwner();
        $organization->forceFill(['status' => OrganizationStatus::Suspended])->save();

        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.organizations.access', $organization), ['access_until' => today()->addYear()->toDateString()]);

        $this->assertFalse($organization->fresh()->isActive());
    }

    public function test_only_people_allowed_to_approve_can_change_it(): void
    {
        [$organization] = $this->organizationWithOwner();
        $sales = PlatformAdmin::factory()->create(['role' => 'sales', 'two_factor_method' => 'otp', 'two_factor_confirmed_at' => now()]);

        $this->actingAs($sales, 'platform')->post(route('admin.organizations.access', $organization), ['access_until' => today()->addYear()->toDateString()])->assertForbidden();
    }

    public function test_the_owner_is_warned_in_the_last_week_only(): void
    {
        [$organization, $owner] = $this->organizationWithOwner();
        $this->actingAs($owner, 'web');

        $organization->forceFill(['access_until' => today()->addDays(30)])->save();
        $this->get(route('app.dashboard'))->assertDontSee('Your access ends on');

        $organization->forceFill(['access_until' => today()->addDays(5)])->save();
        $this->get(route('app.dashboard'))->assertSee('Your access ends on');
    }

    public function test_reminders_go_out_a_week_before_and_on_the_last_day_once_each(): void
    {
        Bus::fake([SendMessage::class]);
        [$organization, $owner] = $this->organizationWithOwner();
        $organization->forceFill(['access_until' => today()->addDays(7)])->save();
        $verification = app(OrganizationVerification::class);
        $reminders = fn () => Bus::dispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'organization_access_ending' && $job->to === $owner->email)->count();

        $verification->sendAccessReminders();
        $verification->sendAccessReminders();
        $this->assertSame(1, $reminders(), 'Running twice must not send twice.');

        $this->travel(3)->days();
        $verification->sendAccessReminders();
        $this->assertSame(1, $reminders(), 'No more until the final notice.');

        $this->travel(3)->days();
        $verification->sendAccessReminders();
        $this->assertSame(2, $reminders(), 'The day before gets the final notice.');

        $this->travel(1)->days();
        $verification->sendAccessReminders();
        $this->assertSame(2, $reminders());
    }

    public function test_reminder_text_names_the_date_and_goes_by_whatsapp_too(): void
    {
        Bus::fake([SendMessage::class]);
        config(['messages.whatsapp.token' => 't', 'messages.whatsapp.phone_number_id' => 'p']);
        [$organization, $owner] = $this->organizationWithOwner();
        $organization->forceFill(['access_until' => today()->addDay()])->save();

        app(OrganizationVerification::class)->sendAccessReminders();

        Bus::assertDispatched(SendMessage::class, fn (SendMessage $job) => $job->channel === 'whatsapp' && $job->to === $owner->mobile && $job->variables['date'] === today()->addDay()->format('d M Y'));
    }

    public function test_no_reminder_for_far_off_dates_no_date_ended_or_suspended_practices(): void
    {
        Bus::fake([SendMessage::class]);
        $verification = app(OrganizationVerification::class);

        foreach ([today()->addDays(8), null, today()->subDay()] as $date) {
            [$organization] = $this->organizationWithOwner();
            $organization->forceFill(['access_until' => $date])->save();
        }

        [$suspended] = $this->organizationWithOwner();
        $suspended->forceFill(['access_until' => today()->addDay(), 'status' => OrganizationStatus::Suspended])->save();

        $verification->sendAccessReminders();

        Bus::assertNotDispatched(SendMessage::class);
    }

    public function test_renewing_starts_the_reminders_afresh_for_the_new_date(): void
    {
        Bus::fake([SendMessage::class]);
        [$organization] = $this->organizationWithOwner();
        $organization->forceFill(['access_until' => today()->addDays(5)])->save();
        $verification = app(OrganizationVerification::class);
        $verification->sendAccessReminders();

        $organization->forceFill(['access_until' => today()->addDays(6)])->save();
        $verification->sendAccessReminders();

        $this->assertSame(2, Bus::dispatched(SendMessage::class, fn (SendMessage $job) => $job->event === 'organization_access_ending' && $job->channel === 'email')->count());
    }

    public function test_the_reminder_is_scheduled_every_day(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => $event->description === 'access-reminders');

        $this->assertCount(1, $events);
        $this->assertSame('0 9 * * *', $events->first()->expression);
    }

    public function test_the_admin_sees_the_date_on_the_organization_page_and_list(): void
    {
        [$organization] = $this->organizationWithOwner();
        $organization->forceFill(['access_until' => today()->subDay()])->save();
        $this->actingAs($this->superAdmin(), 'platform');

        $this->get(route('admin.organizations.show', $organization))->assertOk()->assertSee('Access until')->assertSee('Access ended');
        $this->get(route('admin.organizations.index'))->assertOk()->assertSee('Access ended');
    }
}
