<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLicense;
use App\Http\Middleware\EnsureInstalled;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The test app has no Licensify state or installer marker; the pages
        // themselves are what we are exercising here.
        $this->withoutMiddleware([CheckLicense::class, EnsureInstalled::class]);
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

    public function test_login_page_renders(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('name="email"', false);
    }

    public function test_main_pages_render_for_an_admin(): void
    {
        $this->actingAs($this->admin());

        foreach (['/', '/work', '/cashflow', '/clients', '/invoices', '/reports', '/reports/pnl', '/settings/general', '/settings/integrations', '/expenses', '/tasks'] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertSee('<h1', false);
        }
    }

    public function test_create_forms_have_one_heading_and_a_back_link(): void
    {
        $this->actingAs($this->admin());

        foreach (['/clients/create', '/invoices/create', '/expenses/create', '/projects/create'] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertSee('class="page-head ix-head"', false);
            $response->assertSee('← Back', false);
        }
    }
}
