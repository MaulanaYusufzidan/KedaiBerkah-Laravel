<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Test tidak bergantung pada hasil `npm run build`.
        $this->withoutVite();
    }

    private function admin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    public function test_login_page_can_be_opened(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Login Admin')
            ->assertSee('Masuk');
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = $this->admin(['email' => 'admin@example.test']);

        $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->admin(['email' => 'admin@example.test']);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'salah'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['login' => 'Email atau password salah.']);

        $this->assertGuest();
    }

    public function test_unknown_email_gets_the_same_message_as_wrong_password(): void
    {
        $this->admin(['email' => 'admin@example.test']);

        $wrongPassword = $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'salah']);
        $unknownEmail = $this->post('/admin/login', ['email' => 'tidakada@example.test', 'password' => 'salah']);

        $wrongPassword->assertSessionHasErrors(['login' => 'Email atau password salah.']);
        $unknownEmail->assertSessionHasErrors(['login' => 'Email atau password salah.']);
        $this->assertGuest();
    }

    public function test_non_admin_cannot_login_even_with_correct_password(): void
    {
        User::factory()->create(['email' => 'staff@example.test']); // role default: staff

        $this->post('/admin/login', ['email' => 'staff@example.test', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'Email atau password salah.']);

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->post('/admin/login', [])
            ->assertSessionHasErrors(['email' => 'Email wajib diisi.', 'password' => 'Password wajib diisi.']);
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $this->admin(['email' => 'admin@example.test']);

        foreach (range(1, 5) as $i) {
            $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'salah']);
        }

        // Percobaan ke-6 ditolak walaupun passwordnya benar.
        $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session()->get('errors')->first('login')
        );
        $this->assertGuest();

        RateLimiter::clear('admin@example.test|127.0.0.1');
    }

    public function test_admin_can_open_dashboard(): void
    {
        $this->actingAs($this->admin(['name' => 'Siti']))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Halo, Siti.')
            ->assertSee('Keluar');
    }

    public function test_admin_index_redirects_to_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_guest_cannot_open_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_non_admin_cannot_open_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_logged_in_admin_is_redirected_away_from_login_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_can_logout_and_loses_access(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertGuest();

        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_logout_only_accepts_post(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/logout')
            ->assertMethodNotAllowed();

        $this->assertAuthenticated();
    }

    public function test_guest_cannot_logout_endpoint(): void
    {
        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
    }

    public function test_dashboard_shows_empty_state_without_placeholder_numbers_when_there_are_no_orders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Belum ada penjualan pada periode ini.')
            ->assertSee('Rp0')
            ->assertDontSee('Produk terlaris');
    }

    public function test_dashboard_escapes_admin_name(): void
    {
        $this->actingAs($this->admin(['name' => '<script>alert(1)</script>']))
            ->get('/admin/dashboard')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_role_cannot_be_set_through_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'Penyusup',
            'email' => 'x@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->assertFalse($user->fresh()->isAdmin());
    }
}
