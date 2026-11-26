<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLicense;
use App\Http\Middleware\EnsureInstalled;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Clients\Client;
use Modules\Core\WhatsApp\AiSensySender;
use Modules\Core\WhatsApp\WhatsAppSender;
use Modules\Invoices\Invoice;
use Modules\Settings\SettingStore;
use Tests\TestCase;

class AiSensyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([CheckLicense::class, EnsureInstalled::class]);
    }

    private function configureAiSensy(array $overrides = []): void
    {
        $s = app(SettingStore::class);
        $s->set('whatsapp.enabled', '1', 'whatsapp');
        $s->set('whatsapp.provider', 'aisensy', 'whatsapp');
        $s->set('aisensy.api_key', Crypt::encryptString('aisensy-test-key'), 'aisensy');
        $s->set('aisensy.campaign.reminder', 'payment_reminder_api', 'aisensy');
        $s->set('aisensy.campaign.report', 'report_ready_api', 'aisensy');
        $s->set('aisensy.campaign.test', 'crm_test_api', 'aisensy');
        $s->set('aisensy.source', 'CRM test', 'aisensy');
        foreach ($overrides as $key => $value) {
            $s->set($key, $value);
        }
        $s->flush();
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_aisensy_posts_the_campaign_payload_and_journals_success(): void
    {
        $this->configureAiSensy();
        Http::fake(['*' => Http::response(['messageId' => 'wamid.aisensy.1'], 200)]);

        $res = AiSensySender::send('919876543210', 'payment_reminder_api', ['Priya Nair', 'INV-9001', 'INR 1,000.00', '4 Dec 2026'], [
            'client_name' => 'Priya Nair',
        ]);

        $this->assertTrue($res['ok'], $res['message']);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://backend.aisensy.com/campaign/t1/api/v2'
                && $request->method() === 'POST'
                && $data['apiKey'] === 'aisensy-test-key'
                && $data['campaignName'] === 'payment_reminder_api'
                && $data['destination'] === '919876543210'
                && $data['userName'] === 'Priya Nair'
                && $data['source'] === 'CRM test'
                && $data['templateParams'] === ['Priya Nair', 'INV-9001', 'INR 1,000.00', '4 Dec 2026'];
        });
        $this->assertDatabaseHas('whatsapp_logs', [
            'provider' => 'aisensy',
            'template' => 'payment_reminder_api',
            'recipient' => '919876543210',
            'status' => 'sent',
            'wamid' => 'wamid.aisensy.1',
        ]);
    }

    public function test_aisensy_rejection_is_journalled_with_the_error(): void
    {
        $this->configureAiSensy();
        Http::fake(['*' => Http::response(['message' => 'Campaign is not live'], 422)]);

        $res = AiSensySender::send('919876543210', 'payment_reminder_api', ['Priya']);

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('Campaign is not live', $res['message']);
        $this->assertDatabaseHas('whatsapp_logs', [
            'provider' => 'aisensy',
            'status' => 'failed',
            'error' => 'Campaign is not live',
        ]);
    }

    public function test_aisensy_200_with_success_false_is_treated_as_a_failure(): void
    {
        $this->configureAiSensy();
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Template parameter count mismatch'], 200)]);

        $res = AiSensySender::send('919876543210', 'payment_reminder_api', ['Priya']);

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('parameter count mismatch', $res['message']);
        $this->assertDatabaseHas('whatsapp_logs', [
            'provider' => 'aisensy',
            'status' => 'failed',
            'error' => 'Template parameter count mismatch',
        ]);
    }

    public function test_invoice_reminder_routes_through_aisensy_and_sets_the_throttle(): void
    {
        $this->configureAiSensy();
        Http::fake(['*' => Http::response(['messageId' => 'wamid.aisensy.2'], 200)]);

        $client = Client::create(['name' => 'Priya Nair', 'phone' => '98765 43210', 'email' => 'priya@example.test']);
        $invoice = Invoice::create([
            'number' => 'INV-9001',
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'sent',
            'total' => 1000,
            'paid_amount' => 0,
        ]);

        $res = WhatsAppSender::sendReminder($invoice, 'manual');

        $this->assertTrue($res['ok'], $res['message']);
        $this->assertNotNull($invoice->fresh()->last_whatsapp_at);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['campaignName'] === 'payment_reminder_api'
                && $data['destination'] === '919876543210'
                && count($data['templateParams']) === 4
                && $data['templateParams'][0] === 'Priya Nair'
                && $data['templateParams'][1] === 'INV-9001';
        });
        $this->assertDatabaseHas('whatsapp_logs', [
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'provider' => 'aisensy',
            'source' => 'manual',
        ]);
    }

    public function test_scheduled_reports_use_the_report_campaign_and_params(): void
    {
        $this->configureAiSensy();
        Http::fake(['*' => Http::response(['messageId' => 'wamid.aisensy.3'], 200)]);

        $res = WhatsAppSender::sendReport('919876543210', 'Weekly sales', 'Week 48', ['INR 10,000 collected']);

        $this->assertTrue($res['ok'], $res['message']);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['campaignName'] === 'report_ready_api'
                && $data['templateParams'] === ['Weekly sales', 'Week 48', 'INR 10,000 collected'];
        });
        $this->assertDatabaseHas('whatsapp_logs', [
            'provider' => 'aisensy',
            'template' => 'report_ready_api',
            'source' => 'auto',
        ]);
    }

    public function test_meta_stays_the_default_provider_and_is_still_used(): void
    {
        $s = app(SettingStore::class);
        $s->set('whatsapp.enabled', '1', 'whatsapp');
        $s->set('whatsapp.provider', 'meta', 'whatsapp');
        $s->set('whatsapp.template', 'payment_reminder', 'whatsapp');
        $s->flush();
        config(['whatsapp.phone_number_id' => '123456', 'whatsapp.token' => 'meta-token']);
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.meta.1']]], 200)]);

        $res = WhatsAppSender::sendTest('919876543210');

        $this->assertTrue($res['ok'], $res['message']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com/v21.0/123456/messages')
                && $request['template']['name'] === 'payment_reminder';
        });
        $this->assertDatabaseHas('whatsapp_logs', ['provider' => 'meta', 'status' => 'sent']);
    }

    public function test_test_form_splits_params_on_semicolons_and_keeps_commas(): void
    {
        $this->configureAiSensy();
        Http::fake(['*' => Http::response(['messageId' => 'wamid.aisensy.4'], 200)]);
        $this->actingAs($this->admin());

        $this->post(route('settings.whatsapp.test'), [
            'to' => '98765 43210',
            'params' => 'Priya Nair; INV-9001; INR 1,000.00; 4 Dec 2026',
        ])->assertSessionHasNoErrors();

        Http::assertSent(function ($request) {
            return $request->data()['campaignName'] === 'crm_test_api'
                && $request->data()['templateParams'] === ['Priya Nair', 'INV-9001', 'INR 1,000.00', '4 Dec 2026'];
        });
    }

    public function test_settings_save_encrypts_the_aisensy_key_and_stores_campaigns(): void
    {
        $this->actingAs($this->admin());

        $response = $this->post(route('settings.whatsapp.store'), [
            'WHATSAPP_ENABLED' => '1',
            'WHATSAPP_PROVIDER' => 'aisensy',
            'AISENSY_API_KEY' => 'secret-aisensy-key',
            'AISENSY_CAMPAIGN_REMINDER' => 'payment_reminder_api',
            'AISENSY_CAMPAIGN_REPORT' => 'report_ready_api',
            'AISENSY_CAMPAIGN_TEST' => 'crm_test_api',
            'AISENSY_SOURCE' => 'CRM',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $stored = DB::table('settings')->where('key', 'aisensy.api_key')->value('value');
        $this->assertNotSame('secret-aisensy-key', $stored);
        $this->assertSame('secret-aisensy-key', Crypt::decryptString($stored));
        $this->assertSame('aisensy', DB::table('settings')->where('key', 'whatsapp.provider')->value('value'));
        $this->assertSame('payment_reminder_api', DB::table('settings')->where('key', 'aisensy.campaign.reminder')->value('value'));
    }

    public function test_clear_checkbox_removes_the_saved_aisensy_key(): void
    {
        $this->configureAiSensy();
        $this->actingAs($this->admin());

        $this->post(route('settings.whatsapp.store'), [
            'WHATSAPP_ENABLED' => '1',
            'WHATSAPP_PROVIDER' => 'aisensy',
            'AISENSY_CLEAR' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull(DB::table('settings')->where('key', 'aisensy.api_key')->value('value'));
        $store = app(SettingStore::class);
        $store->flush();
        $this->assertFalse(AiSensySender::hasKey($store));
    }
}
