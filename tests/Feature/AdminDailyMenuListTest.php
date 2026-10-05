<?php

namespace Tests\Feature;

use App\Enums\DailyMenuStatus;
use App\Models\Category;
use App\Models\DailyMenu;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDailyMenuListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-02 10:00:00');
        $this->actingAs(User::factory()->admin()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function menu(string $name, array $menu = [], array $product = []): DailyMenu
    {
        return DailyMenu::factory()->create(array_merge([
            'menu_date' => '2026-10-02',
            'product_id' => Product::factory()->create(array_merge(['name' => $name], $product)),
        ], $menu));
    }

    public function test_today_uses_jakarta_business_date_even_when_utc_date_is_still_yesterday(): void
    {
        // 00:30 WIB tanggal 3 Oktober = 17:30 UTC tanggal 2 Oktober
        Carbon::setTestNow('2026-10-03 00:30:00');
        $this->menu('Menu Tanggal Tiga', ['menu_date' => '2026-10-03']);
        $this->menu('Menu Tanggal Dua', ['menu_date' => '2026-10-02']);

        $this->get('/admin/daily-menus')
            ->assertSee('Menu Tanggal Tiga')
            ->assertDontSee('Menu Tanggal Dua')
            ->assertSee('Sabtu, 3 Oktober 2026');
    }

    public function test_list_shows_thumbnail_category_daily_price_stock_and_status(): void
    {
        $category = Category::factory()->create(['name' => 'Ayam Bakar']);
        $this->menu('Paha Bakar', ['price' => 17000, 'stock' => 7, 'status' => DailyMenuStatus::Scheduled], ['category_id' => $category->id, 'base_price' => 15000, 'image' => null]);

        $this->get('/admin/daily-menus')
            ->assertSee('Paha Bakar')
            ->assertSee('Ayam Bakar')
            ->assertSee('Rp17.000')
            ->assertDontSee('Rp15.000')
            ->assertSee('stok 7')
            ->assertSee('Dijadwalkan')
            ->assertSee('images/placeholder-produk.svg', false);
    }

    public function test_each_status_has_its_own_label(): void
    {
        foreach (DailyMenuStatus::cases() as $status) {
            $this->menu('Menu '.$status->value, ['status' => $status]);
        }

        $page = $this->get('/admin/daily-menus');
        foreach (['Dijadwalkan', 'Tersedia', 'Habis', 'Nonaktif', 'Berakhir'] as $label) {
            $page->assertSee($label);
        }
    }

    public function test_inactive_product_is_flagged_in_the_list(): void
    {
        $this->menu('Produk Mati', [], ['is_active' => false]);
        $this->menu('Produk Hidup', [], ['is_active' => true]);

        $html = $this->get('/admin/daily-menus')->getContent();

        $this->assertSame(1, substr_count($html, 'Produk nonaktif'));
    }

    public function test_filter_by_status(): void
    {
        $this->menu('Ayam Tersedia', ['status' => DailyMenuStatus::Available]);
        $this->menu('Ayam Habis', ['status' => DailyMenuStatus::SoldOut]);

        $this->get('/admin/daily-menus?status=sold_out')->assertSee('Ayam Habis')->assertDontSee('Ayam Tersedia');
        $this->get('/admin/daily-menus?status=ngawur')->assertOk()->assertSee('Ayam Habis')->assertSee('Ayam Tersedia');
    }

    public function test_search_by_product_name_stays_within_selected_date(): void
    {
        $this->menu('Soto Ayam');
        $this->menu('Soto Besok', ['menu_date' => '2026-10-03']);
        $this->menu('Es Teh');

        $this->get('/admin/daily-menus?q=soto')->assertSee('Soto Ayam')->assertDontSee('Soto Besok')->assertDontSee('Es Teh');
        $this->get('/admin/daily-menus?q=zzz')->assertSee('Tidak ada menu yang cocok')->assertSee('Reset pencarian');
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $this->menu('Diskon 50% Spesial');
        $this->menu('Es Teh');

        $this->get('/admin/daily-menus?q=%25')->assertSee('Diskon 50% Spesial')->assertDontSee('Es Teh');
    }

    public function test_filters_survive_pagination(): void
    {
        foreach (range(1, 35) as $i) {
            $this->menu(sprintf('Menu %02d', $i));
        }

        $this->get('/admin/daily-menus?q=Menu&date=2026-10-02')
            ->assertSee('Halaman 1 dari 2')
            ->assertSee('q=Menu', false)
            ->assertSee('page=2', false);
    }

    public function test_empty_state_differs_with_and_without_filters(): void
    {
        $this->get('/admin/daily-menus')->assertSee('Belum ada menu pada tanggal ini.');
        $this->get('/admin/daily-menus?status=available')->assertSee('Tidak ada menu yang cocok');
    }

    public function test_status_is_not_changed_automatically_when_stock_is_zero_or_date_has_passed(): void
    {
        $menu = $this->menu('Ayam', ['stock' => 0, 'status' => DailyMenuStatus::Available, 'menu_date' => '2026-09-01']);

        $this->get('/admin/daily-menus?date=2026-09-01')->assertSee('Tersedia');

        $this->assertSame(DailyMenuStatus::Available, $menu->fresh()->status);
    }

    public function test_array_query_parameters_do_not_break_the_menu_list(): void
    {
        $this->menu('Menu Aman');

        $this->get('/admin/daily-menus?q[]=x&status[]=available&date[]=2026-10-02')->assertOk()->assertSee('Menu Aman');
    }
}
