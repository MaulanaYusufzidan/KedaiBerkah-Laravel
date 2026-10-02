<?php

namespace Tests\Feature;

use App\Enums\DailyMenuStatus;
use App\Models\DailyMenu;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDailyMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-02 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function asAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create());
    }

    private function valid(Product $product, array $override = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'menu_date' => '2026-10-02',
            'price' => 16000,
            'stock' => 20,
            'available_from' => '09:30',
            'available_until' => '14:00',
            'status' => 'available',
            'notes' => 'Sambal terpisah',
        ], $override);
    }

    public function test_admin_sees_todays_menu_by_default_and_other_dates_by_query(): void
    {
        $today = DailyMenu::factory()->create(['menu_date' => '2026-10-02', 'product_id' => Product::factory()->create(['name' => 'Menu Hari Ini X'])]);
        DailyMenu::factory()->create(['menu_date' => '2026-10-03', 'product_id' => Product::factory()->create(['name' => 'Menu Besok Y'])]);

        $this->asAdmin()->get('/admin/daily-menus')
            ->assertOk()
            ->assertSee('Menu Hari Ini X')
            ->assertDontSee('Menu Besok Y')
            ->assertSee('Jumat, 2 Oktober 2026');

        $this->get('/admin/daily-menus?date=2026-10-03')
            ->assertSee('Menu Besok Y')
            ->assertDontSee('Menu Hari Ini X');
    }

    public function test_invalid_date_query_falls_back_to_today(): void
    {
        foreach (['abc', '2026-02-30', '2026-13-01', '02-10-2026'] as $bad) {
            $this->asAdmin()->get('/admin/daily-menus?date='.$bad)
                ->assertOk()
                ->assertSee('2 Oktober 2026');
        }

        $this->get('/admin/daily-menus?date[]=x')->assertOk();
    }

    public function test_empty_state_is_shown_for_date_without_menu(): void
    {
        $this->asAdmin()->get('/admin/daily-menus')->assertOk()->assertSee('Belum ada menu pada tanggal ini.');
    }

    public function test_list_shows_price_stock_hours_and_status_label(): void
    {
        DailyMenu::factory()->create([
            'menu_date' => '2026-10-02', 'price' => 17000, 'stock' => 12,
            'available_from' => '09:30', 'available_until' => '14:00', 'status' => DailyMenuStatus::SoldOut,
        ]);

        $this->asAdmin()->get('/admin/daily-menus')
            ->assertSee('Rp17.000')
            ->assertSee('stok 12')
            ->assertSee('09:30–14:00')
            ->assertSee('Habis');
    }

    public function test_create_form_lists_only_active_products(): void
    {
        Product::factory()->create(['name' => 'Aktif Satu', 'is_active' => true]);
        Product::factory()->create(['name' => 'Nonaktif Dua', 'is_active' => false]);

        $this->asAdmin()->get('/admin/daily-menus/create?date=2026-10-05')
            ->assertOk()
            ->assertSee('Aktif Satu')
            ->assertDontSee('Nonaktif Dua')
            ->assertSee('value="2026-10-05"', false);
    }

    public function test_admin_can_create_daily_menu(): void
    {
        $product = Product::factory()->create(['base_price' => 15000]);

        $this->asAdmin()->post('/admin/daily-menus', $this->valid($product))
            ->assertRedirect(route('admin.daily-menus.index', ['date' => '2026-10-02']))
            ->assertSessionHas('status', 'Menu harian berhasil ditambahkan.');

        $menu = DailyMenu::first();
        $this->assertSame($product->id, $menu->product_id);
        $this->assertSame('2026-10-02', $menu->menu_date->toDateString());
        $this->assertSame(16000, $menu->price);
        $this->assertSame(20, $menu->stock);
        $this->assertSame(DailyMenuStatus::Available, $menu->status);
        $this->assertSame('Sambal terpisah', $menu->notes);
    }

    public function test_blank_price_uses_product_base_price_from_database(): void
    {
        $product = Product::factory()->create(['base_price' => 15000]);

        $this->asAdmin()->post('/admin/daily-menus', $this->valid($product, ['price' => '']))->assertSessionHasNoErrors();

        $this->assertSame(15000, DailyMenu::first()->price);
    }

    public function test_product_is_required_must_exist_and_must_be_active(): void
    {
        $inactive = Product::factory()->create(['is_active' => false]);
        $any = Product::factory()->create();

        $this->asAdmin();
        $this->post('/admin/daily-menus', $this->valid($any, ['product_id' => '']))->assertSessionHasErrors('product_id');
        $this->post('/admin/daily-menus', $this->valid($any, ['product_id' => 9999]))->assertSessionHasErrors('product_id');
        $this->post('/admin/daily-menus', $this->valid($inactive))->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('daily_menus', 0);
    }

    public function test_date_is_required_and_must_be_valid(): void
    {
        $product = Product::factory()->create();

        $this->asAdmin();
        foreach (['', 'abc', '2026-02-30', '2026-13-01', '02/10/2026'] as $bad) {
            $this->post('/admin/daily-menus', $this->valid($product, ['menu_date' => $bad]))->assertSessionHasErrors('menu_date');
        }

        $this->assertDatabaseCount('daily_menus', 0);
    }

    public function test_status_must_be_valid(): void
    {
        $product = Product::factory()->create();

        $this->asAdmin();
        foreach (['', 'ready', 'AVAILABLE'] as $bad) {
            $this->post('/admin/daily-menus', $this->valid($product, ['status' => $bad]))->assertSessionHasErrors('status');
        }

        foreach (DailyMenuStatus::cases() as $i => $status) {
            $this->post('/admin/daily-menus', $this->valid($product, ['status' => $status->value, 'menu_date' => '2026-11-0'.($i + 1)]))
                ->assertSessionHasNoErrors();
        }
    }

    public function test_price_stock_and_hours_are_validated(): void
    {
        $product = Product::factory()->create();

        $this->asAdmin();
        $this->post('/admin/daily-menus', $this->valid($product, ['price' => '-1']))->assertSessionHasErrors('price');
        $this->post('/admin/daily-menus', $this->valid($product, ['price' => 'abc']))->assertSessionHasErrors('price');
        $this->post('/admin/daily-menus', $this->valid($product, ['stock' => '-1']))->assertSessionHasErrors('stock');
        $this->post('/admin/daily-menus', $this->valid($product, ['stock' => '']))->assertSessionHasErrors('stock');
        $this->post('/admin/daily-menus', $this->valid($product, ['available_from' => '25:00']))->assertSessionHasErrors('available_from');
        $this->post('/admin/daily-menus', $this->valid($product, ['available_from' => '14:00', 'available_until' => '09:00']))
            ->assertSessionHasErrors(['available_until' => 'Jam selesai harus setelah jam mulai.']);

        $this->assertDatabaseCount('daily_menus', 0);
    }

    public function test_hours_are_optional(): void
    {
        $product = Product::factory()->create();

        $this->asAdmin()->post('/admin/daily-menus', $this->valid($product, ['available_from' => '', 'available_until' => '', 'notes' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(DailyMenu::first()->available_from);
    }

    public function test_duplicate_product_and_date_is_rejected_with_clear_message(): void
    {
        $product = Product::factory()->create();
        DailyMenu::factory()->create(['product_id' => $product->id, 'menu_date' => '2026-10-02']);

        $this->asAdmin()->post('/admin/daily-menus', $this->valid($product))
            ->assertSessionHasErrors(['menu_date' => 'Produk tersebut sudah ditambahkan ke menu pada tanggal yang dipilih.']);

        $this->assertDatabaseCount('daily_menus', 1);
    }

    public function test_same_product_on_another_date_and_other_product_on_same_date_are_allowed(): void
    {
        $product = Product::factory()->create();
        DailyMenu::factory()->create(['product_id' => $product->id, 'menu_date' => '2026-10-02']);

        $this->asAdmin();
        $this->post('/admin/daily-menus', $this->valid($product, ['menu_date' => '2026-10-03']))->assertSessionHasNoErrors();
        $this->post('/admin/daily-menus', $this->valid(Product::factory()->create()))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('daily_menus', 3);
    }

    public function test_admin_can_update_menu_date_status_price_and_stock(): void
    {
        $menu = DailyMenu::factory()->create(['menu_date' => '2026-10-02', 'status' => DailyMenuStatus::Available]);

        $this->asAdmin()->put("/admin/daily-menus/{$menu->id}", [
            'menu_date' => '2026-10-04', 'price' => 19000, 'stock' => 5,
            'available_from' => '10:00', 'available_until' => '13:00',
            'status' => 'scheduled', 'notes' => 'Diubah',
        ])->assertRedirect(route('admin.daily-menus.index', ['date' => '2026-10-04']));

        $menu->refresh();
        $this->assertSame('2026-10-04', $menu->menu_date->toDateString());
        $this->assertSame(DailyMenuStatus::Scheduled, $menu->status);
        $this->assertSame(19000, $menu->price);
        $this->assertSame(5, $menu->stock);
    }

    public function test_edit_form_shows_current_values_and_product_is_read_only(): void
    {
        $menu = DailyMenu::factory()->create([
            'menu_date' => '2026-10-02', 'price' => 17000, 'available_from' => '09:30:00',
            'product_id' => Product::factory()->create(['name' => 'Soto Spesial']),
        ]);

        $this->asAdmin()->get("/admin/daily-menus/{$menu->id}/edit")
            ->assertOk()
            ->assertSee('Soto Spesial')
            ->assertSee('value="2026-10-02"', false)
            ->assertSee('value="17000"', false)
            ->assertSee('value="09:30"', false)
            ->assertDontSee('name="product_id"', false);
    }

    public function test_product_cannot_be_changed_on_update(): void
    {
        $menu = DailyMenu::factory()->create();
        $other = Product::factory()->create();

        $this->asAdmin()->put("/admin/daily-menus/{$menu->id}", [
            'product_id' => $other->id, 'menu_date' => '2026-10-02', 'price' => 1000, 'stock' => 1, 'status' => 'available',
        ])->assertSessionHasErrors('product_id');

        $this->assertNotSame($other->id, $menu->fresh()->product_id);
    }

    public function test_update_cannot_move_menu_onto_a_date_that_already_has_the_product(): void
    {
        $product = Product::factory()->create();
        DailyMenu::factory()->create(['product_id' => $product->id, 'menu_date' => '2026-10-03']);
        $menu = DailyMenu::factory()->create(['product_id' => $product->id, 'menu_date' => '2026-10-02']);

        $this->asAdmin()->put("/admin/daily-menus/{$menu->id}", [
            'menu_date' => '2026-10-03', 'price' => 1000, 'stock' => 1, 'status' => 'available',
        ])->assertSessionHasErrors('menu_date');

        $this->assertSame('2026-10-02', $menu->fresh()->menu_date->toDateString());
    }

    public function test_menu_can_be_saved_again_on_its_own_date(): void
    {
        $menu = DailyMenu::factory()->create(['menu_date' => '2026-10-02']);

        $this->asAdmin()->put("/admin/daily-menus/{$menu->id}", [
            'menu_date' => '2026-10-02', 'price' => 1000, 'stock' => 1, 'status' => 'available',
        ])->assertSessionHasNoErrors();
    }

    public function test_update_requires_price(): void
    {
        $menu = DailyMenu::factory()->create();

        $this->asAdmin()->put("/admin/daily-menus/{$menu->id}", [
            'menu_date' => '2026-10-02', 'price' => '', 'stock' => 1, 'status' => 'available',
        ])->assertSessionHasErrors('price');
    }

    public function test_admin_can_delete_daily_menu(): void
    {
        $menu = DailyMenu::factory()->create(['menu_date' => '2026-10-02']);

        $this->asAdmin()->delete("/admin/daily-menus/{$menu->id}")
            ->assertRedirect(route('admin.daily-menus.index', ['date' => '2026-10-02']));

        $this->assertDatabaseMissing('daily_menus', ['id' => $menu->id]);
    }

    public function test_menu_already_used_by_an_order_cannot_be_deleted(): void
    {
        $menu = DailyMenu::factory()->create();
        OrderItem::factory()->create(['daily_menu_id' => $menu->id]);

        $this->asAdmin()->from('/admin/daily-menus')
            ->delete("/admin/daily-menus/{$menu->id}")
            ->assertRedirect('/admin/daily-menus')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah dipakai pada pesanan'));

        $this->assertDatabaseHas('daily_menus', ['id' => $menu->id]);
    }

    public function test_notes_and_product_names_are_escaped(): void
    {
        DailyMenu::factory()->create([
            'menu_date' => '2026-10-02',
            'notes' => '<img src=x onerror=alert(1)>',
            'product_id' => Product::factory()->create(['name' => '<script>x</script>']),
        ]);

        $this->asAdmin()->get('/admin/daily-menus')
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertDontSee('<script>x</script>', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
    }

    public function test_guest_and_non_admin_cannot_use_daily_menu_management(): void
    {
        $menu = DailyMenu::factory()->create();
        $payload = $this->valid(Product::factory()->create());
        $requests = [
            ['get', '/admin/daily-menus'],
            ['get', '/admin/daily-menus/create'],
            ['post', '/admin/daily-menus', $payload],
            ['get', "/admin/daily-menus/{$menu->id}/edit"],
            ['put', "/admin/daily-menus/{$menu->id}", $payload],
            ['delete', "/admin/daily-menus/{$menu->id}"],
        ];

        foreach ($requests as $request) {
            [$method, $url, $data] = $request + [2 => []];
            $this->{$method}($url, $data)->assertRedirect(route('admin.login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($requests as $request) {
            [$method, $url, $data] = $request + [2 => []];
            $this->{$method}($url, $data)->assertForbidden();
        }

        $this->assertDatabaseCount('daily_menus', 1);
    }
}
