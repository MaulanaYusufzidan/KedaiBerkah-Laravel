<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Support\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-10 12:00:00');
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

    /** Pesanan dengan pembayaran lunas dan item (nama, qty, harga satuan). */
    private function paidOrder(string $createdAt, array $items, array $attributes = [], string $payment = 'paid'): Order
    {
        $total = array_sum(array_map(fn ($i) => $i[1] * $i[2], $items));
        $order = Order::factory()->create(array_merge([
            'created_at' => $createdAt, 'subtotal' => $total, 'total' => $total,
        ], $attributes));

        foreach ($items as [$name, $qty, $price]) {
            OrderItem::factory()->create([
                'order_id' => $order->id, 'product_name' => $name,
                'quantity' => $qty, 'unit_price' => $price, 'line_total' => $qty * $price,
            ]);
        }
        Payment::factory()->create(['order_id' => $order->id, 'status' => $payment, 'amount' => $total]);

        return $order;
    }

    public function test_only_realized_orders_count_as_revenue(): void
    {
        $this->paidOrder('2026-10-09 10:00:00', [['Ayam Bakar', 2, 15000]]);                          // 30.000 lunas
        $this->paidOrder('2026-10-09 11:00:00', [['Soto', 1, 20000]]);                                // 20.000 lunas
        $this->paidOrder('2026-10-09 12:00:00', [['Ayam Bakar', 5, 15000]], [], 'pending');           // belum dibayar
        $this->paidOrder('2026-10-09 13:00:00', [['Ayam Bakar', 9, 15000]], [], 'waiting_verification');
        $this->paidOrder('2026-10-09 14:00:00', [['Ayam Bakar', 1, 15000]], [], 'rejected');
        $this->paidOrder('2026-10-09 15:00:00', [['Ayam Bakar', 4, 15000]], ['status' => 'cancelled']); // lunas tapi dibatalkan
        Order::factory()->create(['created_at' => '2026-10-09 16:00:00']);                              // tanpa pembayaran

        $summary = (new SalesReport(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-10')))->summary();

        $this->assertSame(['revenue' => 50000, 'orders' => 2, 'average' => 25000], $summary);

        $this->asAdmin()->get('/admin/dashboard?from=2026-10-01&to=2026-10-10')
            ->assertOk()
            ->assertSee('Rp50.000')
            ->assertSee('Rp25.000');
    }

    public function test_revenue_is_not_duplicated_by_many_items_or_many_payment_rows(): void
    {
        $order = $this->paidOrder('2026-10-09 10:00:00', [['A', 1, 10000], ['B', 1, 20000], ['C', 3, 5000]]); // total 45.000, 3 item
        Payment::factory()->create(['order_id' => $order->id, 'status' => 'paid']);                             // baris paid kedua

        $summary = (new SalesReport(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-10')))->summary();

        $this->assertSame(45000, $summary['revenue']);
        $this->assertSame(1, $summary['orders']);
    }

    public function test_date_range_is_inclusive_and_uses_jakarta_time(): void
    {
        $this->paidOrder('2026-10-01 23:59:59', [['X', 1, 1000]]);   // sehari sebelum
        $this->paidOrder('2026-10-02 00:00:00', [['X', 1, 2000]]);   // awal hari -> masuk
        $this->paidOrder('2026-10-02 23:59:59', [['X', 1, 4000]]);   // akhir hari -> masuk
        $this->paidOrder('2026-10-03 00:00:00', [['X', 1, 8000]]);   // sehari sesudah

        $report = new SalesReport(CarbonImmutable::parse('2026-10-02'), CarbonImmutable::parse('2026-10-02'));

        $this->assertSame(['revenue' => 6000, 'orders' => 2, 'average' => 3000], $report->summary());
    }

    public function test_top_products_are_ranked_by_quantity_from_realized_orders_only(): void
    {
        $this->paidOrder('2026-10-09 10:00:00', [['Ayam Bakar', 3, 15000], ['Es Teh', 1, 4000]]);
        $this->paidOrder('2026-10-09 11:00:00', [['Ayam Bakar', 2, 15000], ['Soto Ayam', 4, 20000]]);
        $this->paidOrder('2026-10-09 12:00:00', [['Nasi Tidak Dibayar', 99, 5000]], [], 'pending');

        $top = (new SalesReport(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-10')))->topProducts();

        $this->assertSame(['Ayam Bakar', 'Soto Ayam', 'Es Teh'], $top->pluck('product_name')->all());
        $this->assertSame([5, 4, 1], $top->pluck('qty')->all());
        $this->assertSame([75000, 80000, 4000], $top->pluck('revenue')->all());
    }

    public function test_top_products_list_is_limited_and_names_are_escaped(): void
    {
        foreach (range(1, 7) as $i) {
            $this->paidOrder('2026-10-09 10:00:00', [["Menu {$i}", $i, 1000]]);
        }
        $this->paidOrder('2026-10-09 10:00:00', [['<script>alert(1)</script>', 100, 1000]]);

        $top = (new SalesReport(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-10')))->topProducts();
        $this->assertCount(5, $top);

        $this->asAdmin()->get('/admin/dashboard?from=2026-10-01&to=2026-10-10')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_status_counts_include_every_order_in_the_period(): void
    {
        $this->paidOrder('2026-10-09 10:00:00', [['A', 1, 1000]], ['status' => 'completed']);
        $this->paidOrder('2026-10-09 10:00:00', [['A', 1, 1000]], ['status' => 'processing']);
        $this->paidOrder('2026-10-09 10:00:00', [['A', 1, 1000]], ['status' => 'pending_payment'], 'pending');
        $this->paidOrder('2026-10-09 10:00:00', [['A', 1, 1000]], ['status' => 'cancelled']);
        $this->paidOrder('2026-09-01 10:00:00', [['A', 1, 1000]], ['status' => 'completed']); // di luar periode

        $counts = (new SalesReport(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-10')))->statusCounts();

        $this->assertSame(1, $counts['completed']);
        $this->assertSame(1, $counts['processing']);
        $this->assertSame(1, $counts['pending_payment']);
        $this->assertSame(1, $counts['cancelled']);
        $this->assertSame(0, $counts['ready']);
    }

    public function test_series_fills_empty_days_and_groups_weeks_and_months(): void
    {
        // Rabu 2026-09-30, Kamis 2026-10-01, Senin 2026-10-05 (minggu baru), Selasa 2026-10-06
        $this->paidOrder('2026-09-30 10:00:00', [['A', 1, 1000]]);
        $this->paidOrder('2026-10-01 10:00:00', [['A', 1, 2000]]);
        $this->paidOrder('2026-10-05 10:00:00', [['A', 1, 4000]]);
        $this->paidOrder('2026-10-06 10:00:00', [['A', 1, 8000]]);

        $report = new SalesReport(CarbonImmutable::parse('2026-09-28'), CarbonImmutable::parse('2026-10-11'));

        $daily = $report->series('daily');
        $this->assertCount(14, $daily);
        $this->assertSame(0, $daily[0]['revenue']);   // 28 Sep kosong tetap ada
        $this->assertSame(1000, $daily[2]['revenue']); // 30 Sep

        $weekly = $report->series('weekly');
        $this->assertCount(2, $weekly);
        $this->assertSame([3000, 12000], array_column($weekly, 'revenue'));
        $this->assertSame([2, 2], array_column($weekly, 'orders'));

        $monthly = $report->series('monthly');
        $this->assertSame([1000, 14000], array_column($monthly, 'revenue'));
        $this->assertSame(15000, array_sum(array_column($monthly, 'revenue')));
    }

    public function test_granularity_is_chosen_automatically_and_daily_is_limited_for_long_ranges(): void
    {
        $short = new SalesReport(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-10'));
        $medium = new SalesReport(CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-10-10'));
        $long = new SalesReport(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-10-10'));

        $this->assertSame('daily', $short->effectiveGranularity(null));
        $this->assertSame('weekly', $medium->effectiveGranularity(null));
        $this->assertSame('monthly', $long->effectiveGranularity(null));
        $this->assertSame('weekly', $medium->effectiveGranularity('daily'), 'harian dibatasi untuk rentang > 62 hari');
        $this->assertSame('monthly', $short->effectiveGranularity('monthly'));
    }

    public function test_default_period_is_last_30_days_in_jakarta_time(): void
    {
        // 2026-10-10 12:00 WIB -> 30 hari terakhir = 2026-09-11 s/d 2026-10-10
        $this->asAdmin()->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('value="2026-09-11"', false)
            ->assertSee('value="2026-10-10"', false)
            ->assertSee('(30 hari)');
    }

    public function test_invalid_filters_show_a_message_and_fall_back_to_defaults(): void
    {
        $this->asAdmin();

        $this->get('/admin/dashboard?from=abc&to=2026-10-10')
            ->assertOk()->assertSee('Tanggal awal tidak valid.')->assertSee('Menampilkan 30 hari terakhir.');

        $this->get('/admin/dashboard?from=2026-10-10&to=2026-10-01')
            ->assertOk()->assertSee('Tanggal awal tidak boleh setelah tanggal akhir.');

        $this->get('/admin/dashboard?from=2020-01-01&to=2026-10-10')
            ->assertOk()->assertSee('Rentang tanggal maksimal 366 hari.');

        $this->get('/admin/dashboard?group=tahunan')
            ->assertOk()->assertSee('Pilihan grafik tidak valid.');

        $this->get('/admin/dashboard?from[]=x')->assertOk();
    }

    public function test_chart_and_top_products_are_shown_only_when_there_are_sales(): void
    {
        $this->asAdmin()->get('/admin/dashboard')
            ->assertSee('Belum ada penjualan pada periode ini.')
            ->assertDontSee('Lihat data grafik')
            ->assertDontSee('Produk terlaris');

        $this->paidOrder('2026-10-09 10:00:00', [['Ayam Bakar', 2, 15000]]);

        $this->get('/admin/dashboard')
            ->assertDontSee('Belum ada penjualan pada periode ini.')
            ->assertSee('Lihat data grafik')
            ->assertSee('Produk terlaris')
            ->assertSee('Ayam Bakar')
            ->assertSee('2 terjual');
    }

    public function test_number_of_queries_does_not_grow_with_number_of_orders(): void
    {
        $this->asAdmin();

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/admin/dashboard?from=2026-10-01&to=2026-10-10')->assertOk();

            return count(DB::getQueryLog());
        };

        $this->paidOrder('2026-10-09 10:00:00', [['A', 1, 1000], ['B', 2, 2000]]);
        $few = $count();

        foreach (range(1, 40) as $i) {
            $this->paidOrder('2026-10-0'.(($i % 9) + 1).' 10:00:00', [['A', 1, 1000], ['C', 2, 500]]);
        }
        $many = $count();

        $this->assertSame($few, $many, 'jumlah query harus konstan, tidak bergantung jumlah pesanan');
    }

    public function test_guest_and_non_admin_cannot_open_dashboard_with_filters(): void
    {
        $this->get('/admin/dashboard?from=2026-10-01')->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create())->get('/admin/dashboard')->assertForbidden();
    }
}
