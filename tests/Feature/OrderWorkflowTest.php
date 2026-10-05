<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Support\OrderTransitionException;
use App\Support\OrderWorkflow;
use App\Support\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private OrderWorkflow $workflow;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = new OrderWorkflow;
        $this->admin = User::factory()->admin()->create(['name' => 'Siti Admin']);
        Carbon::setTestNow('2026-10-02 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(string $status = 'pending_payment', string $type = 'pickup', ?string $payment = null, array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'status' => $status, 'fulfillment_type' => $type, 'subtotal' => 30000, 'total' => 30000,
        ], $attributes));

        if ($payment) {
            Payment::factory()->create(['order_id' => $order->id, 'status' => $payment, 'amount' => $order->total]);
        }

        return $order;
    }

    private function statusOf(Order $order): OrderStatus
    {
        return $order->fresh()->status;
    }

    // ---------- alur normal ----------

    public function test_pickup_flow_from_payment_to_completion_records_every_step(): void
    {
        $order = $this->order();

        $this->workflow->apply($order, 'mark_proof_received', $this->admin);
        $this->assertSame(OrderStatus::WaitingVerification, $this->statusOf($order));
        $payment = $order->payments()->firstOrFail();
        $this->assertSame(PaymentStatus::WaitingVerification, $payment->status);
        $this->assertSame(30000, $payment->amount);
        $this->assertNull($payment->verified_by, 'bukti diterima belum berarti pembayaran valid');

        $this->workflow->apply($order, 'verify_payment', $this->admin);
        $payment->refresh();
        $this->assertSame(OrderStatus::Processing, $this->statusOf($order));
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame($this->admin->id, $payment->verified_by);
        $this->assertNotNull($payment->verified_at);
        $this->assertNotNull($payment->paid_at);

        $this->workflow->apply($order, 'mark_ready', $this->admin);
        $this->assertSame(OrderStatus::Ready, $this->statusOf($order));

        $this->workflow->apply($order, 'complete', $this->admin);
        $this->assertSame(OrderStatus::Completed, $this->statusOf($order));

        $histories = $order->statusHistories;
        $this->assertSame(
            ['pending_payment>waiting_verification', 'waiting_verification>processing', 'processing>ready', 'ready>completed'],
            $histories->map(fn ($h) => $h->from_status->value.'>'.$h->to_status->value)->all()
        );
        $this->assertTrue($histories->every(fn ($h) => $h->changed_by === $this->admin->id));
        $this->assertTrue($histories->every(fn ($h) => $h->note !== null));
    }

    public function test_delivery_flow_goes_through_delivering_and_never_ready(): void
    {
        $order = $this->order('processing', 'delivery', 'paid');

        $this->workflow->apply($order, 'start_delivery', $this->admin);
        $this->assertSame(OrderStatus::Delivering, $this->statusOf($order));

        $this->workflow->apply($order, 'complete', $this->admin);
        $this->assertSame(OrderStatus::Completed, $this->statusOf($order));

        $this->assertSame(['processing>delivering', 'delivering>completed'],
            $order->statusHistories->map(fn ($h) => $h->from_status->value.'>'.$h->to_status->value)->all());
    }

    public function test_rejected_payment_returns_to_pending_and_resubmission_creates_a_new_attempt(): void
    {
        $order = $this->order('waiting_verification', 'pickup', 'waiting_verification');

        $this->workflow->apply($order, 'reject_payment', $this->admin, 'Nominal transfer kurang');

        $first = $order->payments()->firstOrFail();
        $this->assertSame(OrderStatus::PendingPayment, $this->statusOf($order));
        $this->assertSame(PaymentStatus::Rejected, $first->status);
        $this->assertSame('Nominal transfer kurang', $first->rejection_reason);
        $this->assertSame($this->admin->id, $first->verified_by);
        $this->assertSame('Nominal transfer kurang', $order->statusHistories()->latest('id')->first()->note);

        $this->workflow->apply($order, 'mark_proof_received', $this->admin);
        $this->assertSame(2, $order->payments()->count(), 'riwayat penolakan dipertahankan');
        $this->assertSame(PaymentStatus::Rejected, $first->fresh()->status);

        $this->workflow->apply($order, 'verify_payment', $this->admin);
        $this->assertSame(OrderStatus::Processing, $this->statusOf($order));
        $this->assertSame(PaymentStatus::Paid, $order->payments()->latest('id')->first()->status);
        $this->assertSame(PaymentStatus::Rejected, $first->fresh()->status);
    }

    // ---------- aturan pembayaran vs processing ----------

    public function test_no_action_can_reach_processing_unless_the_latest_payment_is_paid(): void
    {
        $paymentStates = [null, 'pending', 'waiting_verification', 'rejected', 'expired'];

        foreach (OrderStatus::cases() as $status) {
            foreach ([FulfillmentType::Pickup, FulfillmentType::Delivery] as $type) {
                foreach ($paymentStates as $payment) {
                    foreach (array_keys(OrderWorkflow::ACTIONS) as $action) {
                        $order = $this->order($status->value, $type->value, $payment);
                        try {
                            $this->workflow->apply($order, $action, $this->admin, 'alasan');
                        } catch (OrderTransitionException) {
                            continue;
                        }

                        $order->refresh();
                        if ($order->status === OrderStatus::Processing) {
                            $this->assertSame(PaymentStatus::Paid, $order->payments()->latest('id')->first()?->status,
                                "{$status->value}/{$type->value}/".($payment ?? 'tanpa pembayaran')."/{$action} masuk processing tanpa pembayaran paid");
                        }
                    }
                }
            }
        }
    }

    public function test_verify_is_refused_when_no_proof_has_been_marked(): void
    {
        foreach ([null, 'pending'] as $payment) {
            $order = $this->order('pending_payment', 'pickup', $payment);
            try {
                $this->workflow->apply($order, 'verify_payment', $this->admin);
                $this->fail('verifikasi seharusnya ditolak');
            } catch (OrderTransitionException $e) {
                $this->assertStringContainsString('status pesanan sudah berubah', $e->getMessage());
            }
            $this->assertSame(OrderStatus::PendingPayment, $this->statusOf($order));
        }
    }

    public function test_verify_is_refused_when_status_and_payment_disagree(): void
    {
        $order = $this->order('waiting_verification', 'pickup', 'pending'); // data tidak konsisten

        $this->assertSame(['cancel'], $this->workflow->availableActions($order));
        $this->expectException(OrderTransitionException::class);
        $this->workflow->apply($order, 'verify_payment', $this->admin);
    }

    public function test_verify_is_refused_when_payment_amount_differs_from_order_total(): void
    {
        $order = $this->order('waiting_verification', 'pickup');
        Payment::factory()->create(['order_id' => $order->id, 'status' => 'waiting_verification', 'amount' => 25000]);

        try {
            $this->workflow->apply($order, 'verify_payment', $this->admin);
            $this->fail('nominal berbeda seharusnya ditolak');
        } catch (OrderTransitionException $e) {
            $this->assertStringContainsString('Rp25.000', $e->getMessage());
            $this->assertStringContainsString('Rp30.000', $e->getMessage());
        }

        $this->assertSame(OrderStatus::WaitingVerification, $this->statusOf($order));
        $this->assertSame(PaymentStatus::WaitingVerification, $order->payments()->first()->status);
        $this->assertSame(0, OrderStatusHistory::count());
    }

    // ---------- pickup vs delivery ----------

    public function test_pickup_cannot_enter_delivering_and_delivery_cannot_become_ready(): void
    {
        $pickup = $this->order('processing', 'pickup', 'paid');
        try {
            $this->workflow->apply($pickup, 'start_delivery', $this->admin);
            $this->fail();
        } catch (OrderTransitionException $e) {
            $this->assertStringContainsString('ambil sendiri tidak melalui pengiriman', $e->getMessage());
        }
        $this->assertSame(OrderStatus::Processing, $this->statusOf($pickup));

        $delivery = $this->order('processing', 'delivery', 'paid');
        try {
            $this->workflow->apply($delivery, 'mark_ready', $this->admin);
            $this->fail();
        } catch (OrderTransitionException $e) {
            $this->assertStringContainsString('Langsung mulai pengiriman', $e->getMessage());
        }
        $this->assertSame(OrderStatus::Processing, $this->statusOf($delivery));
    }

    public function test_orders_cannot_skip_steps_to_completed(): void
    {
        foreach ([['processing', 'pickup'], ['processing', 'delivery'], ['pending_payment', 'pickup'], ['waiting_verification', 'delivery']] as [$status, $type]) {
            $order = $this->order($status, $type, $status === 'processing' ? 'paid' : 'waiting_verification');
            try {
                $this->workflow->apply($order, 'complete', $this->admin);
                $this->fail("{$status}/{$type} tidak boleh langsung selesai");
            } catch (OrderTransitionException) {
                $this->assertSame(OrderStatus::from($status), $this->statusOf($order));
            }
        }
    }

    public function test_pickup_order_cannot_complete_from_delivering(): void
    {
        $order = $this->order('delivering', 'pickup', 'paid'); // data tidak lazim

        $this->assertSame([], $this->workflow->availableActions($order));
        $this->expectException(OrderTransitionException::class);
        $this->workflow->apply($order, 'complete', $this->admin);
    }

    // ---------- matriks lengkap ----------

    public function test_every_action_outside_the_allowed_set_is_rejected_without_side_effects(): void
    {
        foreach (OrderStatus::cases() as $status) {
            foreach (['pickup', 'delivery'] as $type) {
                foreach (['paid', 'waiting_verification', 'pending'] as $payment) {
                    $probe = $this->order($status->value, $type, $payment);
                    $allowed = $this->workflow->availableActions($probe);

                    foreach (array_diff(array_keys(OrderWorkflow::ACTIONS), $allowed) as $action) {
                        $order = $this->order($status->value, $type, $payment);
                        $paymentBefore = $order->payments()->first()->only(['status', 'verified_by', 'rejection_reason']);

                        try {
                            $this->workflow->apply($order, $action, $this->admin, 'alasan');
                            $this->fail("{$status->value}/{$type}/{$payment}: {$action} seharusnya ditolak");
                        } catch (OrderTransitionException) {
                            // diharapkan
                        }

                        $this->assertSame($status, $this->statusOf($order));
                        $this->assertSame(0, $order->statusHistories()->count());
                        $this->assertSame($paymentBefore, $order->payments()->first()->only(['status', 'verified_by', 'rejection_reason']));
                        $this->assertSame(1, $order->payments()->count());
                    }
                }
            }
        }
    }

    public function test_terminal_statuses_have_no_actions(): void
    {
        foreach (['completed', 'cancelled'] as $status) {
            $this->assertSame([], $this->workflow->availableActions($this->order($status, 'pickup', 'paid')));
        }
    }

    // ---------- pembatalan ----------

    public function test_cancel_is_allowed_only_before_ready_and_expires_unpaid_payments(): void
    {
        foreach (['pending_payment' => null, 'waiting_verification' => 'waiting_verification'] as $status => $payment) {
            $order = $this->order($status, 'pickup', $payment);
            $this->workflow->apply($order, 'cancel', $this->admin, 'Pelanggan membatalkan');

            $this->assertSame(OrderStatus::Cancelled, $this->statusOf($order));
            if ($payment) {
                $this->assertSame(PaymentStatus::Expired, $order->payments()->first()->status);
            }
            $this->assertSame('Pelanggan membatalkan', $order->statusHistories()->first()->note);
        }

        foreach (['ready', 'delivering', 'completed', 'cancelled'] as $status) {
            $order = $this->order($status, $status === 'delivering' ? 'delivery' : 'pickup', 'paid');

            try {
                $this->workflow->apply($order, 'cancel', $this->admin, 'alasan');
                $this->fail("pesanan {$status} tidak boleh dibatalkan");
            } catch (OrderTransitionException) {
                $this->assertSame(OrderStatus::from($status), $this->statusOf($order));
                $this->assertSame(0, $order->statusHistories()->count());
            }
        }
    }

    public function test_cancelling_a_processing_order_keeps_the_paid_record_but_removes_it_from_revenue(): void
    {
        $order = $this->order('processing', 'pickup', 'paid', ['created_at' => '2026-10-01 10:00:00']);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Ayam', 'quantity' => 2, 'unit_price' => 15000, 'line_total' => 30000]);
        $report = fn () => (new SalesReport(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-02')))->summary();

        $this->assertSame(30000, $report()['revenue']);

        $this->workflow->apply($order, 'cancel', $this->admin, 'Stok habis');

        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()->status, 'catatan uang masuk tidak dihapus');
        $this->assertSame(['revenue' => 0, 'orders' => 0, 'average' => 0], $report());
    }

    public function test_verify_moves_order_into_realized_revenue(): void
    {
        $order = $this->order('waiting_verification', 'pickup', 'waiting_verification', ['created_at' => '2026-10-01 10:00:00']);
        $report = fn () => (new SalesReport(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-02')))->summary()['revenue'];

        $this->assertSame(0, $report());
        $this->workflow->apply($order, 'verify_payment', $this->admin);
        $this->assertSame(30000, $report());
    }

    public function test_reject_and_cancel_require_a_reason_and_change_nothing_without_one(): void
    {
        $waiting = $this->order('waiting_verification', 'pickup', 'waiting_verification');
        foreach ([null, '', '   '] as $note) {
            foreach (['reject_payment', 'cancel'] as $action) {
                try {
                    $this->workflow->apply($waiting, $action, $this->admin, $note);
                    $this->fail("{$action} tanpa alasan seharusnya ditolak");
                } catch (OrderTransitionException $e) {
                    $this->assertSame('Alasan wajib diisi.', $e->getMessage());
                }
            }
        }

        $this->assertSame(OrderStatus::WaitingVerification, $this->statusOf($waiting));
        $this->assertSame(PaymentStatus::WaitingVerification, $waiting->payments()->first()->status);
        $this->assertSame(0, OrderStatusHistory::count());
    }

    // ---------- permintaan ganda & status usang ----------

    public function test_double_submit_does_not_duplicate_history_or_reverify(): void
    {
        $order = $this->order('waiting_verification', 'pickup', 'waiting_verification');

        $this->workflow->apply($order, 'verify_payment', $this->admin);
        $verifiedAt = $order->payments()->first()->verified_at;

        Carbon::setTestNow('2026-10-02 10:05:00');
        try {
            $this->workflow->apply($order, 'verify_payment', $this->admin); // objek $order masih usang, status di DB sudah processing
            $this->fail('klik ganda seharusnya ditolak');
        } catch (OrderTransitionException) {
            // diharapkan
        }

        $this->assertSame(1, $order->statusHistories()->count());
        $this->assertEquals($verifiedAt, $order->payments()->first()->verified_at, 'waktu verifikasi tidak ditimpa');
        $this->assertSame(OrderStatus::Processing, $this->statusOf($order));
    }

    public function test_stale_order_object_is_checked_against_current_database_status(): void
    {
        $stale = $this->order('waiting_verification', 'pickup', 'waiting_verification');
        $this->assertSame(OrderStatus::WaitingVerification, $stale->status);

        // Admin lain membatalkan lebih dulu.
        $this->workflow->apply(Order::find($stale->id), 'cancel', $this->admin, 'Dibatalkan admin lain');

        try {
            $this->workflow->apply($stale, 'verify_payment', $this->admin);
            $this->fail('verifikasi pada pesanan yang sudah dibatalkan seharusnya ditolak');
        } catch (OrderTransitionException) {
            // diharapkan
        }

        $this->assertSame(OrderStatus::Cancelled, $this->statusOf($stale));
        $this->assertNotSame(PaymentStatus::Paid, $stale->payments()->first()->status);
    }

    public function test_unknown_action_is_rejected(): void
    {
        $this->expectException(OrderTransitionException::class);
        $this->expectExceptionMessage('Aksi tidak dikenal.');

        $this->workflow->apply($this->order(), 'hapus_semua', $this->admin);
    }

    public function test_history_is_stored_in_jakarta_time_with_admin_and_default_note(): void
    {
        Carbon::setTestNow('2026-10-02 00:30:00'); // 00:30 WIB
        $order = $this->order();

        $this->workflow->apply($order, 'mark_proof_received', $this->admin);

        $history = $order->statusHistories()->firstOrFail();
        $this->assertSame('2026-10-02 00:30:00', $history->getRawOriginal('created_at'));
        $this->assertSame($this->admin->id, $history->changed_by);
        $this->assertSame('Siti Admin', $history->changedBy->name);
        $this->assertStringContainsString('WhatsApp', $history->note);
    }

    public function test_money_and_snapshot_fields_are_never_modified_by_transitions(): void
    {
        $order = $this->order('waiting_verification', 'delivery', 'waiting_verification', [
            'subtotal' => 30000, 'delivery_fee' => 5000, 'total' => 35000, 'delivery_area_name' => 'Tanah Sareal',
        ]);
        Payment::query()->update(['amount' => 35000]);
        $snapshot = $order->fresh()->only(['subtotal', 'delivery_fee', 'total', 'delivery_area_name', 'order_number', 'customer_name', 'customer_phone']);

        $this->workflow->apply($order, 'verify_payment', $this->admin);
        $this->workflow->apply($order, 'start_delivery', $this->admin);
        $this->workflow->apply($order, 'complete', $this->admin);

        $this->assertSame($snapshot, $order->fresh()->only(array_keys($snapshot)));
    }
}
