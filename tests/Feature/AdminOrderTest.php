<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-02 10:00:00');
        $this->admin = User::factory()->admin()->create(['name' => 'Siti Admin']);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(array $attributes = [], ?string $payment = null): Order
    {
        $order = Order::factory()->create(array_merge(['subtotal' => 30000, 'total' => 30000], $attributes));
        if ($payment) {
            Payment::factory()->create(['order_id' => $order->id, 'status' => $payment, 'amount' => $order->total]);
        }

        return $order;
    }

    // ---------- daftar ----------

    public function test_list_shows_newest_first_with_totals_status_and_payment(): void
    {
        $old = $this->order(['order_number' => 'KB-20261001-0001', 'customer_name' => 'Budi', 'created_at' => '2026-10-01 09:00:00']);
        $new = $this->order(['order_number' => 'KB-20261002-0002', 'customer_name' => 'Ani', 'created_at' => '2026-10-02 09:00:00', 'status' => 'waiting_verification', 'total' => 45000], 'waiting_verification');
        OrderItem::factory()->count(2)->create(['order_id' => $new->id]);

        $html = $this->get('/admin/orders')->assertOk()
            ->assertSee('KB-20261002-0002')->assertSee('Ani')->assertSee('Rp45.000')
            ->assertSee('2 item')->assertSee('Menunggu Verifikasi')->assertSee('Bayar: Belum ada')
            ->getContent();

        $this->assertLessThan(strpos($html, 'KB-20261001-0001'), strpos($html, 'KB-20261002-0002'), 'terbaru di atas');
    }

    public function test_empty_state_without_orders_and_with_filter(): void
    {
        $this->get('/admin/orders')->assertSee('Belum ada pesanan.');
        $this->get('/admin/orders?q=zzz')->assertSee('Tidak ada pesanan yang cocok')->assertSee('Reset pencarian');
    }

    public function test_search_by_order_number_or_customer_name_with_literal_wildcards(): void
    {
        $this->order(['order_number' => 'KB-20261002-0001', 'customer_name' => 'Budi Santoso']);
        $this->order(['order_number' => 'KB-20261002-0002', 'customer_name' => 'Ani 100% Setia']);
        $this->order(['order_number' => 'KB-20261002-0003', 'customer_name' => 'Citra']);

        $this->get('/admin/orders?q=0001')->assertSee('KB-20261002-0001')->assertDontSee('KB-20261002-0003');
        $this->get('/admin/orders?q=santoso')->assertSee('KB-20261002-0001')->assertDontSee('KB-20261002-0002');
        $this->get('/admin/orders?q=%25')->assertSee('KB-20261002-0002')->assertDontSee('KB-20261002-0003');
        $this->get('/admin/orders?q=_')->assertSee('Tidak ada pesanan yang cocok');
    }

    public function test_filter_by_date_range_is_inclusive_in_jakarta_time(): void
    {
        $this->order(['order_number' => 'KB-A', 'created_at' => '2026-10-01 23:59:59']);
        $this->order(['order_number' => 'KB-B', 'created_at' => '2026-10-02 00:00:00']);
        $this->order(['order_number' => 'KB-C', 'created_at' => '2026-10-02 23:59:59']);
        $this->order(['order_number' => 'KB-D', 'created_at' => '2026-10-03 00:00:00']);

        $this->get('/admin/orders?from=2026-10-02&to=2026-10-02')
            ->assertSee('KB-B')->assertSee('KB-C')->assertDontSee('KB-A')->assertDontSee('KB-D');
    }

    public function test_filter_by_status_payment_and_fulfillment(): void
    {
        $this->order(['order_number' => 'KB-PAID', 'status' => 'processing', 'fulfillment_type' => 'pickup'], 'paid');
        $this->order(['order_number' => 'KB-WAIT', 'status' => 'waiting_verification', 'fulfillment_type' => 'delivery'], 'waiting_verification');
        $this->order(['order_number' => 'KB-NEW', 'status' => 'pending_payment', 'fulfillment_type' => 'delivery']);

        $this->get('/admin/orders?status=processing')->assertSee('KB-PAID')->assertDontSee('KB-WAIT')->assertDontSee('KB-NEW');
        $this->get('/admin/orders?payment=waiting_verification')->assertSee('KB-WAIT')->assertDontSee('KB-PAID');
        $this->get('/admin/orders?fulfillment=delivery')->assertSee('KB-WAIT')->assertSee('KB-NEW')->assertDontSee('KB-PAID');
        $this->get('/admin/orders?fulfillment=delivery&status=pending_payment')->assertSee('KB-NEW')->assertDontSee('KB-WAIT');
    }

    public function test_payment_filter_uses_only_the_latest_payment(): void
    {
        $order = $this->order(['order_number' => 'KB-RETRY', 'status' => 'waiting_verification']);
        Payment::factory()->create(['order_id' => $order->id, 'status' => 'rejected', 'amount' => 30000]);
        Payment::factory()->create(['order_id' => $order->id, 'status' => 'waiting_verification', 'amount' => 30000]);

        $this->get('/admin/orders?payment=rejected')->assertDontSee('KB-RETRY');
        $this->get('/admin/orders?payment=waiting_verification')->assertSee('KB-RETRY');
    }

    public function test_status_summary_counts_come_from_real_data_and_respect_other_filters(): void
    {
        $this->order(['status' => 'pending_payment', 'fulfillment_type' => 'pickup']);
        $this->order(['status' => 'pending_payment', 'fulfillment_type' => 'delivery']);
        $this->order(['status' => 'completed', 'fulfillment_type' => 'delivery']);

        $this->get('/admin/orders')
            ->assertSee('Semua (3)')->assertSee('Menunggu Pembayaran (2)')->assertSee('Selesai (1)')->assertSee('Sedang Diproses (0)');

        $this->get('/admin/orders?fulfillment=delivery')
            ->assertSee('Semua (2)')->assertSee('Menunggu Pembayaran (1)');

        $this->get('/admin/orders?status=completed')
            ->assertSee('Semua (3)', false); // ringkasan tidak ikut menyempit oleh filter status itu sendiri
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        $this->order(['order_number' => 'KB-OK']);

        $this->get('/admin/orders?status=ngawur&payment=x&fulfillment=y&from=abc&to=2026-13-40')->assertOk()->assertSee('KB-OK');
        $this->get('/admin/orders?status[]=x&q[]=y')->assertOk();
    }

    public function test_pagination_keeps_filters(): void
    {
        foreach (range(1, 25) as $i) {
            $this->order(['order_number' => sprintf('KB-P-%02d', $i), 'status' => 'processing']);
        }

        $this->get('/admin/orders?status=processing')->assertSee('Halaman 1 dari 2')->assertSee('status=processing', false)->assertSee('page=2', false);
    }

    public function test_list_does_not_run_extra_queries_per_order(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/admin/orders')->assertOk();

            return count(DB::getQueryLog());
        };

        $this->order([], 'paid');
        $few = $count();
        foreach (range(1, 15) as $i) {
            $this->order([], 'waiting_verification');
        }

        $this->assertSame($few, $count());
    }

    public function test_customer_text_is_escaped_in_list_and_detail(): void
    {
        $order = $this->order(['customer_name' => '<script>alert(1)</script>', 'address' => '<img src=x onerror=alert(2)>', 'notes' => '</dd><script>alert(3)</script>', 'fulfillment_type' => 'delivery']);

        foreach (['/admin/orders', "/admin/orders/{$order->id}"] as $url) {
            $this->get($url)
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertDontSee('<img src=x onerror=alert(2)>', false)
                ->assertDontSee('<script>alert(3)</script>', false);
        }
    }

    // ---------- detail ----------

    public function test_detail_shows_items_totals_customer_and_history(): void
    {
        $order = $this->order(['order_number' => 'KB-20261002-0007', 'customer_name' => 'Budi', 'customer_phone' => '087874627555', 'fulfillment_type' => 'pickup',
            'subtotal' => 38000, 'total' => 38000, 'notes' => 'Sambal dipisah']);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Ayam Bakar Paha', 'quantity' => 2, 'unit_price' => 15000, 'line_total' => 30000]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Es Teh', 'quantity' => 2, 'unit_price' => 4000, 'line_total' => 8000]);
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => null, 'to_status' => OrderStatus::PendingPayment, 'note' => 'Pesanan dibuat']);

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('KB-20261002-0007')->assertSee('Budi')->assertSee('Ambil Sendiri')->assertSee('Sambal dipisah')
            ->assertSee('Ayam Bakar Paha')->assertSee('2 × Rp15.000', false)->assertSee('Rp30.000')->assertSee('Es Teh')
            ->assertSee('Subtotal')->assertSee('Rp38.000')
            ->assertSee('Riwayat status')->assertSee('Pesanan dibuat')->assertSee('Sistem')
            ->assertSee('https://wa.me/6287874627555?text=', false)
            ->assertDontSee('Area pengiriman'); // pickup tidak menampilkan area
    }

    public function test_detail_for_delivery_shows_area_snapshot_not_current_area_name(): void
    {
        $area = DeliveryArea::factory()->create(['district' => 'Tanah Sareal', 'delivery_fee' => 5000]);
        $order = $this->order(['fulfillment_type' => 'delivery', 'delivery_area_id' => $area->id, 'delivery_area_name' => 'Tanah Sareal', 'delivery_fee' => 5000, 'total' => 35000, 'address' => 'Jl. Kukupu No. 1']);

        $area->update(['district' => 'Nama Sudah Diganti', 'delivery_fee' => 12000]);

        $this->get("/admin/orders/{$order->id}")
            ->assertSee('Area pengiriman')->assertSee('Tanah Sareal')->assertSee('Jl. Kukupu No. 1')
            ->assertDontSee('Nama Sudah Diganti')->assertSee('Rp5.000')->assertDontSee('Rp12.000');
    }

    public function test_detail_falls_back_to_current_area_for_legacy_orders_without_snapshot(): void
    {
        $area = DeliveryArea::factory()->create(['district' => 'Bogor Barat']);
        $order = $this->order(['fulfillment_type' => 'delivery', 'delivery_area_id' => $area->id, 'delivery_area_name' => null]);

        $this->get("/admin/orders/{$order->id}")->assertSee('Bogor Barat');
    }

    public function test_item_prices_are_snapshots_and_do_not_follow_product_changes(): void
    {
        $order = $this->order();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Soto Ayam', 'unit_price' => 15000, 'quantity' => 1, 'line_total' => 15000]);
        Product::factory()->create(['name' => 'Soto Ayam', 'base_price' => 99000]);

        $this->get("/admin/orders/{$order->id}")->assertSee('Rp15.000')->assertDontSee('Rp99.000');
    }

    public function test_detail_shows_payment_verifier_and_rejection_reason_separately_from_order_status(): void
    {
        $order = $this->order(['status' => 'pending_payment']);
        Payment::factory()->create(['order_id' => $order->id, 'status' => 'rejected', 'amount' => 30000,
            'verified_at' => '2026-10-01 15:30:00', 'verified_by' => $this->admin->id, 'rejection_reason' => 'Nominal kurang']);

        $this->get("/admin/orders/{$order->id}")
            ->assertSee('Menunggu Pembayaran')->assertSee('Bayar: Ditolak')
            ->assertSee('Diperiksa')->assertSee('oleh Siti Admin')->assertSee('Alasan penolakan: Nominal kurang');
    }

    public function test_detail_only_offers_actions_valid_for_the_current_status(): void
    {
        $pending = $this->order(['status' => 'pending_payment']);
        $this->get("/admin/orders/{$pending->id}")
            ->assertSee('Bukti diterima (via WhatsApp)')->assertSee('Batalkan pesanan')
            ->assertDontSee('Verifikasi pembayaran &amp; mulai proses', false);

        $waiting = $this->order(['status' => 'waiting_verification'], 'waiting_verification');
        $this->get("/admin/orders/{$waiting->id}")
            ->assertSee('Verifikasi pembayaran &amp; mulai proses', false)->assertSee('Tolak bukti pembayaran')
            ->assertDontSee('Bukti diterima (via WhatsApp)');

        $pickup = $this->order(['status' => 'processing', 'fulfillment_type' => 'pickup'], 'paid');
        $this->get("/admin/orders/{$pickup->id}")->assertSee('Tandai siap diambil')->assertDontSee('Mulai pengiriman');

        $delivery = $this->order(['status' => 'processing', 'fulfillment_type' => 'delivery'], 'paid');
        $this->get("/admin/orders/{$delivery->id}")->assertSee('Mulai pengiriman')->assertDontSee('Tandai siap diambil');

        $done = $this->order(['status' => 'completed'], 'paid');
        $this->get("/admin/orders/{$done->id}")->assertSee('Tidak ada tindakan untuk pesanan selesai.');
    }

    public function test_unknown_order_returns_404(): void
    {
        $this->get('/admin/orders/99999')->assertNotFound();
        $this->post('/admin/orders/99999/transition', ['action' => 'cancel', 'note' => 'x'])->assertNotFound();
    }

    // ---------- aksi lewat HTTP ----------

    public function test_admin_runs_the_full_pickup_flow_through_the_interface(): void
    {
        $order = $this->order();

        foreach (['mark_proof_received', 'verify_payment', 'mark_ready', 'complete'] as $action) {
            $this->post("/admin/orders/{$order->id}/transition", ['action' => $action])
                ->assertRedirect(route('admin.orders.show', $order))
                ->assertSessionHas('status');
        }

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()->status);
        $this->assertSame(4, $order->statusHistories()->count());
        $this->get("/admin/orders/{$order->id}")->assertSee('Siti Admin')->assertSee('Selesai');
    }

    public function test_flash_message_names_the_new_status(): void
    {
        $order = $this->order();

        $this->post("/admin/orders/{$order->id}/transition", ['action' => 'mark_proof_received'])
            ->assertSessionHas('status', 'Status pesanan diperbarui menjadi "Menunggu Verifikasi".');
    }

    public function test_reject_and_cancel_without_reason_show_a_validation_error_and_change_nothing(): void
    {
        $order = $this->order(['status' => 'waiting_verification'], 'waiting_verification');

        $this->from("/admin/orders/{$order->id}")
            ->post("/admin/orders/{$order->id}/transition", ['action' => 'reject_payment', 'note' => ''])
            ->assertSessionHasErrors(['note' => 'Alasan wajib diisi.']);
        $this->post("/admin/orders/{$order->id}/transition", ['action' => 'cancel'])->assertSessionHasErrors('note');
        $this->post("/admin/orders/{$order->id}/transition", ['action' => 'cancel', 'note' => str_repeat('a', 256)])->assertSessionHasErrors('note');

        $this->assertSame(OrderStatus::WaitingVerification, $order->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::count());
    }

    public function test_reject_with_reason_is_recorded_and_visible(): void
    {
        $order = $this->order(['status' => 'waiting_verification'], 'waiting_verification');

        $this->post("/admin/orders/{$order->id}/transition", ['action' => 'reject_payment', 'note' => 'Bukti buram'])->assertSessionHasNoErrors();

        $this->get("/admin/orders/{$order->id}")->assertSee('Bukti buram')->assertSee('Bayar: Ditolak')->assertSee('Menunggu Pembayaran');
    }

    public function test_invalid_action_is_rejected_by_validation(): void
    {
        $order = $this->order();

        $this->post("/admin/orders/{$order->id}/transition", ['action' => 'hapus'])->assertSessionHasErrors('action');
        $this->post("/admin/orders/{$order->id}/transition", [])->assertSessionHasErrors('action');
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_action_that_is_not_allowed_shows_a_clear_error_message(): void
    {
        $order = $this->order(['status' => 'pending_payment']);

        $this->post("/admin/orders/{$order->id}/transition", ['action' => 'verify_payment'])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'status pesanan sudah berubah') && ! str_contains($m, 'SQLSTATE'));

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_double_click_second_request_fails_cleanly_and_history_is_not_duplicated(): void
    {
        $order = $this->order(['status' => 'waiting_verification'], 'waiting_verification');

        $this->post("/admin/orders/{$order->id}/transition", ['action' => 'verify_payment'])->assertSessionHas('status');
        $this->post("/admin/orders/{$order->id}/transition", ['action' => 'verify_payment'])->assertSessionHas('error');

        $this->assertSame(1, $order->statusHistories()->count());
    }

    public function test_status_cannot_be_forced_through_extra_request_fields(): void
    {
        $order = $this->order();

        $this->post("/admin/orders/{$order->id}/transition", ['action' => 'cancel', 'note' => 'batal', 'status' => 'completed', 'total' => 1, 'payment_status' => 'paid']);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(30000, $order->total);
    }

    public function test_get_requests_cannot_change_status(): void
    {
        $order = $this->order();

        $this->get("/admin/orders/{$order->id}/transition?action=cancel")->assertStatus(405);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    // ---------- bukti pembayaran ----------

    public function test_proof_is_served_privately_to_admin_with_safe_headers(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payment-proofs/bukti-1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $order = $this->order([], 'waiting_verification');
        Payment::query()->update(['proof' => 'payment-proofs/bukti-1.png']);

        $this->get("/admin/orders/{$order->id}")->assertSee('Lihat bukti pembayaran');
        $this->get("/admin/orders/{$order->id}/proof")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_proof_returns_404_when_missing_or_path_is_unsafe(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('rahasia.txt', 'x');
        $order = $this->order([], 'waiting_verification');

        $this->get("/admin/orders/{$order->id}/proof")->assertNotFound();
        $this->get("/admin/orders/{$order->id}")->assertDontSee('Lihat bukti pembayaran');

        foreach (['payment-proofs/../rahasia.txt', '../rahasia.txt', 'rahasia.txt', '/etc/passwd', 'payment-proofs/tidak-ada.png'] as $path) {
            Payment::query()->update(['proof' => $path]);
            $this->get("/admin/orders/{$order->id}/proof")->assertNotFound();
        }
    }

    // ---------- akses ----------

    public function test_guest_and_non_admin_cannot_access_any_order_route(): void
    {
        $order = $this->order([], 'waiting_verification');
        $requests = [
            ['get', '/admin/orders'],
            ['get', "/admin/orders/{$order->id}"],
            ['post', "/admin/orders/{$order->id}/transition", ['action' => 'verify_payment']],
            ['get', "/admin/orders/{$order->id}/proof"],
        ];

        auth()->logout();
        foreach ($requests as $request) {
            [$method, $url, $data] = $request + [2 => []];
            $this->{$method}($url, $data)->assertRedirect(route('admin.login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($requests as $request) {
            [$method, $url, $data] = $request + [2 => []];
            $this->{$method}($url, $data)->assertForbidden();
        }

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(PaymentStatus::WaitingVerification, $order->payments()->first()->status);
        $this->assertSame(0, OrderStatusHistory::count());
    }

    public function test_navigation_contains_orders_link(): void
    {
        $this->get('/admin/dashboard')->assertSee(route('admin.orders.index'), false);
    }
}
