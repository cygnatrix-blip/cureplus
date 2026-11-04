<?php

namespace Tests\Feature;

use App\Services\PlatformSettings;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformEmailSettingsTest extends TestCase
{
    public function test_saving_smtp_details_actually_switches_sending_on(): void
    {
        config(['mail.default' => 'log']);

        app(PlatformSettings::class)->save(['mail.host' => 'smtp.gmail.com', 'mail.username' => 'me@example.test']);

        $this->assertSame('smtp', config('mail.default'), 'Saved details are useless if the app keeps writing email to a log file.');
    }

    public function test_the_page_warns_when_email_is_not_really_being_sent(): void
    {
        config(['mail.default' => 'log']);

        $this->actingAs($this->superAdmin(), 'platform')->get(route('admin.integrations.index'))->assertOk()
            ->assertSee('Emails are not being sent');
    }

    public function test_the_page_shows_what_env_already_provides_without_the_password(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'sender@example.test', 'mail.mailers.smtp.password' => 'real-app-password', 'mail.from.address' => 'from@example.test']);

        $this->actingAs($this->superAdmin(), 'platform')->get(route('admin.integrations.index'))->assertOk()
            ->assertSee('sender@example.test')
            ->assertSee('from@example.test')
            ->assertSee('Saved in .env')
            ->assertDontSee('real-app-password')
            ->assertDontSee('Emails are not being sent');
    }

    public function test_the_test_email_button_sends_and_reports_failure_safely(): void
    {
        Mail::fake();
        $this->actingAs($this->superAdmin(), 'platform')->post(route('admin.integrations.test-email'), ['to' => 'me@example.test'])->assertSessionHas('success');
    }
}
